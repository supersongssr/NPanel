# Node API v2 开发总结

> 分支: `feature/node-api-v2` | 基线: `NPanel` | 日期: 2026-05-12

---

## 1. 项目背景

NPanel 原有的节点管理依赖 `PingController@ssn_v2` 等老旧接口，采用"胖节点、瘦面板"架构——节点端自行处理协议配置、DNS 绑定、流量计算等逻辑。随着节点规模扩大和多协议（vision/xhttp/ws/grpc/hy2）并行的需求，这种模式暴露出以下问题：

- 节点端脚本复杂度高，升级困难
- DNS 记录无本地状态跟踪，对账依赖 Cloudflare 实时查询
- 流量计费模式（tx/rxtx）分散在多处，字段命名混乱
- 克隆节点（裂变）逻辑硬编码，无法动态调整

Node API v2 的核心设计理念是 **"瘦节点、胖面板"**：面板承担所有配置渲染、DNS 管理、流量计费、健康判定逻辑，节点端只需提供硬件信息和原始流量计数器。

---

## 2. 架构设计

### 2.1 生命周期

```
apply_id → register → resolve_dns → config → status(循环)
```

| 步骤 | 接口 | 职责 |
|---|---|---|
| Step 0 | `POST /api/node/apply_id` | 分配/回收节点 ID |
| Step 1 | `POST /api/node/register` | 注册硬件信息、裂变、域名亲和分配 |
| Step 2 | `POST /api/node/resolve_dns` | DB-Driven Diff 三向对账同步 Cloudflare |
| Step 3 | `GET /api/node/config` | 类型保留 JSON 注入生成 Xray 配置 |
| Step 4 | `POST /api/node/status` | 流量计费、健康引擎、熔断机制循环上报 |

### 2.2 核心设计模式

**裂变矩阵 (Fission Matrix):** 一个物理节点注册后，面板根据 `protocol × IP stack` 组合自动生成多个虚拟节点（克隆节点）。例如双栈节点 + 4 协议 = 8 个虚拟节点。每个虚拟节点拥有独立的子域名和协议预设。

**域名亲和性 (Domain Affinity):** 节点重装时上报旧的 `root_domain`，面板优先沿用该域名（前提是占用数未达上限），保障 DNS 无缝继承。域名池支持富 JSON 配置（per-domain `zone_id`、`records_limit`）。

**DB-Driven Diff 三向对账:** `resolve_dns` 以本地 `dns_records` 表为状态基准（Old State），以 `ss_node` 中的最新数据为目标状态（Target State）。只有 Cloudflare API 返回 HTTP 2xx 后才更新本地记录，避免"脏对账"。

**CDN Proxy 决策:** 从 `v2_net`（非 `v2_name`）字段派生 DNS 记录的 `proxied` 状态。`ws`/`grpc` 协议需要 CDN 代理（橙色云），`vision`/`hy2` 协议仅做 DNS 解析（灰色云）。

---

## 3. 数据库变更

### 3.1 ss_node 表字段标准化

通过 14 个 Migration 文件完成，字段命名遵循前缀规范（服务器属性用 `node_`，代理属性用 `v2_`）：

| 旧字段 | 最终字段 | 类型 | 重命名链 |
|---|---|---|---|
| `cpu` | `node_cpu` | INT | cpu → node_cpu |
| `memory` | `node_memory` | DOUBLE(8,2) | memory → node_memory |
| `disk` | `node_disk` | DOUBLE(8,2) | disk → node_disk |
| `health` | `node_health` | TINYINT UNSIGNED | health → node_health |
| `billing_mode` | `node_rxtx` | VARCHAR(255) | billing_mode → node_traffic_rxtx_mode → node_rxtx_mode → node_rxtx |
| - | `node_country` | VARCHAR(64) | 新增 |
| - | `node_city` | VARCHAR(64) | 新增 |
| - | `node_ids` | TEXT | 新增（裂变矩阵 ID 封装） |
| `rxtx_mode` | *(已删除)* | - | 冗余字段，合并至 node_rxtx |

**Migration 使用 Raw SQL 而非 Doctrine DBAL：** Laravel 5.6 的 `Schema::hasColumn()` 和 `renameColumn()` 依赖 `doctrine/dbal`，项目未安装该依赖。所有列检测使用 `INFORMATION_SCHEMA.COLUMNS` 查询，列重命名使用 `ALTER TABLE ... CHANGE COLUMN` 语句。

### 3.2 dns_records 表（新建）

```sql
CREATE TABLE dns_records (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    node_id     INT UNSIGNED NOT NULL,
    root_domain VARCHAR(255) NOT NULL,
    subdomain   VARCHAR(255) NOT NULL,
    record_type VARCHAR(8) NOT NULL,      -- 'A' / 'AAAA'
    ip_addr     VARCHAR(255) NOT NULL,
    cf_record_id VARCHAR(255) NOT NULL,   -- Cloudflare 返回的记录 ID
    proxied     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP,
    UNIQUE (subdomain, root_domain, record_type),
    INDEX (node_id),
    INDEX (root_domain)
);
```

**演进历史：**
- 初版包含 `cf_zone_id` 列，后删除（改为从 `node_domain_pool` 配置动态获取）
- 后期新增 `proxied` 列，用于跟踪 CDN 代理状态

### 3.3 config 表新增条目

| 配置名 | 类型 | 说明 |
|---|---|---|
| `node_domain_pool` | JSON | 富字典格式域名池。示例: `{"ssmail.win": {"zone_id": "xxx", "records_limit": 1000}}` |
| `node_protocol_presets` | JSON | 动态协议阈值。`{"threshold_mb": 2048, "high": "xhttp-hy2-ws-grpc", "low": "vision-hy2-ws-grpc"}` |

---

## 4. 接口实现细节

### 4.1 POST /api/node/apply_id

**核心逻辑：节点 ID 回收。** 查找心跳超过 32 天的死亡节点复用其 ID，同时清理该节点的 `dns_records`。若无死亡节点则创建新记录。

**返回值:** `{ "node_id": 42 }`

### 4.2 POST /api/node/register

这是最复杂的接口（约 250 行），包含以下子系统：

#### 4.2.1 动态协议选择
```
node_memory > threshold (默认 2GB) → xhttp-hy2-ws-grpc (高性能)
node_memory <= threshold           → vision-hy2-ws-grpc (标准)
```
节点也可通过 `v2_name` 参数显式指定。

#### 4.2.2 IP 互斥 (Single-Stack Isolation)
`apply_id` 阶段已确定节点的 IPv4/IPv6 命运。`register` 阶段强制执行：
- 如果 apply_id 给了 IPv4 → 只接受 node_ip，将 ipv6 置空
- 如果 apply_id 给了 IPv6 → 只接受 node_ipv6，将 ip 置空

子域名前缀也因此区分：IPv4 用 `{random8}n{id}`，IPv6 用 `{random8}ipv6n{id}`（随机 8 位前缀降低连接域名/SNI 特征被识别追踪；clone 连接域名前缀由 `resolve_dns` 从 `server` 字段解析，与前缀是否随机解耦）。

#### 4.2.3 裂变矩阵构建
```
slots = protocols × ips
```
主节点占据第一个 slot，剩余 slot 分配给克隆节点。克隆节点优先复用已有的 `is_clone = mainNodeId` 记录，不足时从死亡节点池回收，仍不足才新建。多余的旧克隆节点被降级（`is_clone = 0, status = 0`）。

克隆节点的 `level` 在主节点 level 基础上随机 +0~2（上限 5），实现分层可见性。

#### 4.2.4 V2 预设注入
每种协议有预定义的 `v2_*` 字段常量映射（`V2_PRESETS` 数组），包括 type、v2_net、v2_port、v2_tls、v2_flow、v2_alpn 等。`vision+grpc` 组合中 grpc 端口特殊设为 2053 避免端口冲突。

#### 4.2.5 标准化命名
节点名称格式: `{COUNTRY_CODE}-{City}`，如 `JP-Tokyo`。country_code 自动转大写。

#### 4.2.6 输入参数向后兼容
| 新参数名 | 旧参数名（仍兼容） |
|---|---|
| `node_rxtx` | `node_rxtx_mode`, `billing_mode` |
| `node_cpu` | `cpu` |
| `node_memory` | `memory` |
| `node_disk` | `disk` |
| `node_bandwidth` | `bandwidth` |

### 4.3 POST /api/node/resolve_dns

**集群级批量同步：** 一次调用处理主节点及其所有克隆节点的 DNS 记录。通过 `node_ids` 字段获取所有需要同步的节点 ID。

**三向对账场景:**

| 场景 | 条件 | CF API 调用 | 本地操作 |
|---|---|---|---|
| A (缓存拦截) | subdomain + root_domain + ip + proxied 完全一致 | 无 | 无 |
| B (域名变更) | subdomain 或 root_domain 变动 | DELETE 旧 + POST 新 | 删旧行 + 插新行 |
| C (新建) | 本地无记录 | POST 创建 | 插入新行 |
| D (IP/代理变更) | 域名不变但 IP 或 proxied 变动 | PUT 更新 | 更新行 |

**严格约束:** CF API 返回非 2xx 时，本地 `dns_records` 保持旧状态不变，允许下次请求继续对账。Scene B 中如果 DELETE 成功但 POST 失败，旧本地记录已被删除，下次请求会重新创建。

**安全机制:**
- 90 秒全局超时 + 单节点 60 秒超时
- 孤儿记录检测（node 有 DNS 记录但当前不需要的 record_type）
- `force` 参数强制跳过缓存拦截
- 完整的 Log 记录（每步操作、耗时、CF 返回）

**CDN 代理决策逻辑:**
```php
const CDN_REQUIRED_NETS = ['ws', 'grpc'];
$proxied = in_array($node->v2_net, self::CDN_REQUIRED_NETS);
```

### 4.4 GET /api/node/config

**类型保留 JSON 注入（Type-Preserved JSON Injection）:**

模板中占位符如 `__wsPort__` 被替换为整数 `10011` 而非字符串 `"10011"`。实现原理：
1. 模板先以 `json_decode($raw)` 解码（得到 stdClass 对象，保留类型信息）
2. 再转数组进行变量注入
3. 注入后通过 `restoreJsonObjectTypes()` 对照原始模板恢复 stdClass/array 类型

**HY2 直连 IP 覆盖:**
当 `v2_net === 'hysteria2'` 时，将配置中所有 `server` 字段替换为节点的原始 IP（绕过域名，因为 HY2 使用 QUIC/UDP 无法走 CDN）。

**流媒体解锁模板:**
从 `resources/templates/xray/unlock/{service}.json` 文件加载解锁规则，支持配置中的 `unlock_{service}_address/port/password/method` 变量注入。

### 4.5 POST /api/node/status

**计费模式:**

| `node_rxtx` 值 | 计算公式 |
|---|---|
| `tx` (默认) | `rawTotal = raw_tx` |
| `rxtx` | `rawTotal = (raw_rx + raw_tx) / 2` |

**三态增量计算:**

| 场景 | 条件 | 增量值 |
|---|---|---|
| A (首次上报) | `last_raw_total == 0` | 0（建基线，不计费） |
| B (网卡重启) | `rawTotal < last_raw_total` | `rawTotal`（计数器归零） |
| C (正常累加) | `rawTotal >= last_raw_total` | `rawTotal - last_raw_total` |

**健康引擎:**
```
avgUsed = traffic_used / max(passedDays, 1)
avgRemaining = (traffic_limit - traffic_used) / max(remainingDays, 1)
node_health = (avgUsed > avgRemaining) ? 0 : 1
```

**120G 熔断:** `(traffic_limit - traffic_used) < 120GB` → `status = 0`

---

## 5. 新增文件清单

### 5.1 应用代码

| 文件 | 行数 | 说明 |
|---|---|---|
| `app/Http/Controllers/Api/NodeApiController.php` | 1271 | 5 步生命周期主控制器 |
| `app/Http/Models/DnsRecord.php` | 25 | DNS 状态跟踪模型 |
| `app/Http/Models/SsNode.php` | *(修改)* | 更新 $fillable, $casts, 添加 dnsRecords 关系 |
| `app/Components/DNS/CloudflareProvider.php` | 198 | CF API 封装（CRUD + zone_id 动态传入） |
| `app/Components/DNS/DnsProviderInterface.php` | 17 | DNS Provider 接口 |
| `app/Console/Commands/InitDnsRecords.php` | 185 | Artisan 命令：初始化 dns_records 全量同步 |
| `app/Http/Controllers/SystemCommandController.php` | 104 | 系统命令控制器（含 DNS 批量操作入口） |

### 5.2 数据库迁移（14 个）

| 文件 | 说明 |
|---|---|
| `2026_04_27_111342_add_v2_name_and_hardware_to_ss_node_table.php` | 新增 v2_name, cpu, memory, disk |
| `2026_04_27_120000_add_rxtx_mode_to_ss_node_table.php` | 新增 rxtx_mode（后删除） |
| `2026_04_27_120525_add_billing_mode_to_ss_node_table.php` | 新增 billing_mode |
| `2026_04_27_200000_standardize_ss_node_fields.php` | 5 字段标准化重命名 |
| `2026_04_27_200001_create_dns_records_table.php` | 创建 dns_records 表 |
| `2026_04_27_210000_enforce_int_node_cpu.php` | node_cpu 类型改为 INT |
| `2026_04_27_210000_rename_rxtx_mode_field.php` | node_traffic_rxtx_mode → node_rxtx_mode |
| `2026_04_29_100000_rename_rxtx_mode_and_add_node_ids.php` | node_rxtx_mode → node_rxtx + 新增 node_ids |
| `2026_04_29_100001_seed_node_config_defaults.php` | 种子数据：protocol_presets 默认值 |
| `2026_05_01_172415_drop_cf_zone_id_from_dns_records_table.php` | 删除 cf_zone_id（改为动态获取） |
| `2026_05_03_000001_add_proxied_to_dns_records_table.php` | 新增 proxied 字段 |
| `2026_05_05_000000_add_host_pools_to_config_table.php` | config 表种子：host_pools |
| `2026_05_07_000000_add_pow_config_to_config_table.php` | config 表种子：PoW 配置 |

### 5.3 测试文件

| 文件 | 行数 | 覆盖范围 |
|---|---|---|
| `tests/test_node_api_v2_qa.php` | 254 | 字段迁移、DNS CRUD、域名亲和性、容量溢出 |
| `tests/test_node_api_v2_qa_r2.php` | 199 | SQL 保留字安全、计费逻辑、参数向后兼容、文档完整性 |
| `tests/test_node_api_v2_qa_r3.php` | 333 | 裂变矩阵、V2 预设、集群级 DNS 对账 |
| `tests/test_node_api_cycle.php` | 234 | 完整生命周期模拟 |
| `tests/qa_logic_sync.php` | 293 | 逻辑对齐断言 |

### 5.4 文档

| 文件 | 说明 |
|---|---|
| `docs/api/node-api-v2.md` | API 契约文档（310 行） |
| `docs/how-resolve-dns-api-works.md` | resolve_dns 三向对账详解 |
| `docs/how-main-id-know-clone-ids.md` | 裂变矩阵 node_ids 字段说明 |
| `docs/node-register-clone-id.md` | register 裂变流程 |
| `docs/node-get-id-how.md` | apply_id ID 分配机制 |
| `docs/node-api-log-report.md` | 日志格式参考 |
| `docs/proxy-node-way.md` | 代理架构说明 |
| `docs/del-node-or-back-node-when-used.md` | 节点删除/回收策略 |
| `docs/error-node-config-error.md` | 常见错误排查 |

---

## 6. 路由注册

```php
// routes/api.php
Route::group(['prefix' => 'node'], function () {
    Route::post('apply_id', 'NodeApiController@applyId');
    Route::post('register', 'NodeApiController@register');
    Route::post('resolve_dns', 'NodeApiController@resolveDns');
    Route::get('config', 'NodeApiController@config');
    Route::post('status', 'NodeApiController@status');
});
```

---

## 7. 环境依赖

### .env 必需配置

| 变量 | 说明 |
|---|---|
| `API_TOKEN` | 接口认证密钥 |
| `CLOUDFLARE_EMAIL` | Cloudflare 账户邮箱 |
| `CLOUDFLARE_API_KEY` | Cloudflare API Key |
| `CLOUDFLARE_ZONE_ID` | Cloudflare 默认 Zone ID |

### config 表必需配置

| 配置名 | 说明 |
|---|---|
| `node_root_domain` | 主域名（兜底域名） |
| `node_domain_pool` | 富 JSON 域名池（含 per-domain zone_id, records_limit） |
| `node_protocol_presets` | 协议阈值配置（threshold_mb, high, low） |

### 模板文件依赖

```
resources/templates/xray/
├── vision-hy2-ws-grpc.json
├── xhttp-hy2-ws-grpc.json
└── unlock/
    ├── openai.json
    ├── netflix.json
    └── ...
```

---

## 8. 已知技术债

| # | 问题 | 严重性 | 建议 |
|---|---|---|---|
| 1 | 3 个冗余 rxtx 迁移链（billing_mode → node_traffic_rxtx_mode → node_rxtx_mode → node_rxtx）可压缩 | 低 | 仅影响全新安装，生产环境无需处理 |
| 2 | `docs/api/node-api-v2.md` 中部分参数名仍为 `node_rxtx_mode`，实际主参数为 `node_rxtx` | 中 | 合并前更新文档 |
| 3 | CloudflareProvider 每次 resolve_dns 调用都 `new` 新实例，未使用连接复用 | 低 | 大规模节点时可考虑 HTTP 连接池 |
| 4 | `resolve_dns` 全局 90 秒超时为硬编码，大规模集群（>100 节点）可能不够 | 低 | 可抽取为配置项 |

---

## 9. 统计

| 指标 | 数值 |
|---|---|
| 新增代码行数 | 6,542 |
| 修改文件数 | 47 |
| 提交数 (vs NPanel) | 20 |
| 迁移文件数 | 14 |
| 测试文件数 | 6 |
| 接口端点数 | 5 |
| QA 断言总数 | 95+ (Round 1: 47 + Round 2: 48) |
