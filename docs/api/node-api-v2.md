# Node API v2 接口文档

## 概述

Node API v2 是 NPanel 面向 **瘦节点、胖面板** 架构设计的新一代节点管理 API。节点端通过 5 步生命周期完成从申请 ID 到运行状态上报的全流程：

```
apply_id → register → resolve_dns → config → status(循环)
```

**设计理念：** 面板承担所有配置渲染、DNS 管理、流量计费、健康判定逻辑；节点端只需提供硬件信息和原始流量计数器，最大限度降低节点侧复杂度。

**Base URL:** `http://your-domain.com/api/node`

**认证方式:** Token 认证 — 所有接口均需携带 `token` 参数（或 `X-API-Token` 请求头），值为 `.env` 中 `API_TOKEN` 变量。

**适用版本:** commit `36f943a8` (2026-05-12) 及之后。

---

## 生命周期流程图

```
┌──────────────────────────────────────────────────────┐
│  Node API v2 生命周期                                  │
├──────────────────────────────────────────────────────┤
│                                                      │
│  Step 0   POST /apply_id       申请/回收节点 ID       │
│             ↓                                        │
│  Step 1   POST /register       注册节点 + 动态裂变     │
│             ↓                                        │
│  Step 2   POST /resolve_dns    集群级 DNS 三向对账     │
│             ↓                                        │
│  Step 3   POST /config         拉取 Xray 配置          │
│  Step 3.5 POST /nginx_config   拉取 Nginx 配置        │
│             ↓                                        │
│  Step 4   POST /status    ←─── 循环上报               │
│             ↑             状态/流量/健康               │
│             └──────────────────────────────────────── │
└──────────────────────────────────────────────────────┘
```

---

## 数据库变更

Node API v2 使用标准化字段命名（已通过 migration 完成对 legacy 字段的替换）：

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `v2_name` | string | 动态分配 | 单个协议名（如 `ws`、`hy2`），由 register 时从协议组拆分后分配 |
| `node_rxtx` | string | `tx` | 流量计费模式：`tx`（仅上行）或 `rxtx`（双向均值） |
| `node_cpu` | integer | nullable | CPU 核心数 |
| `node_memory` | float | nullable | 内存大小（GB） |
| `node_disk` | float | nullable | 磁盘大小（GB） |
| `node_group` | integer | 2 | 节点分组 ID |
| `node_country` | string | nullable | 国家名称（如 Japan） |
| `node_city` | string | nullable | 城市名称（如 Tokyo） |
| `node_health` | integer | 1 | 健康状态：0=拥塞，1=健康 |
| `is_clone` | integer | 0 | 克隆源节点 ID，`0` 表示主节点 |
| `node_ids` | text | nullable | 主节点专属字段，逗号分隔的 ID 字符串（如 `"42,43,44,45"`） |
| `last_raw_total` | bigint unsigned | 0 | 上一次上报的原始流量总值，用于增量计算 |
| `server_uptime` | bigint unsigned | 0 | 服务器运行时间（秒） |
| `last_traffic_reset_at` | datetime | nullable | 上次流量重置时间；`AutoResetNodeTraffic` 32 天安全网计时基线（全新身份由 `register` 在为 null 时初始化、`applyId` 回收时置 null、月度重置刷新；**同机重装带缓存 id 上报时保留既有基线不重置**——身份继承原则） |

---

## 系统配置项

协议预设、根域名等通用项由系统配置（`config` 表 / `config.default.php`）驱动；**域名池**则由 `.config.php` 的 `node_domain_map`（PHP 关联数组）驱动，**非硬编码**。域名池后台页面仅**只读展示**，修改请直接编辑 `.config.php` 的 `node_domain_map`（CF Token 不在页面明文展示）。

| 配置项 | 格式 | 位置 | 说明 |
|--------|------|------|--------|
| `node_root_domain` | string | config | 兜底主域名（如 `ssmail.win`），当域名池为空时使用 |
| `node_domain_map` | PHP 关联数组 | `.config.php` | 富字典格式域名池（domain => meta），详见下方 |
| `node_protocol_presets` | JSON object | config | 协议预设配置，详见下方 |

> 旧键 `node_domain_pool`（config 表 JSON 字符串）已**废弃**，仅作向后兼容回退；真实配置（含 `cf_token` 等敏感数据）请放 `.config.php` 的 `node_domain_map`。

**node_domain_map 结构（PHP 关联数组，domain => meta）：**
```php
// 配置在 .config.php 中 (示例); meta 为 domain => 数组
'ssmail.win' => [
    'zone_id'        => '45356a7ae9254b65b016839dbe141b28', // 必填: CF Zone ID
    'records_limit'  => 1000,                                // 可选: DNS 记录数上限, 默认 180
    'cdn'            => false,                               // 可选: true=走 CF CDN (供 xhttp-cdn 节点), 默认 false
    'cf_token'       => '',                                  // 可选: 跨账号域名填该账号 Token; 留空用全局 CLOUDFLARE_TOKEN. 与 cdn 正交
    'expire_date'    => '2027-01-01',                        // 可选: 仅记录/展示用
],
'backup.net' => [
    'zone_id'       => 'abc123def456',
    'records_limit' => 500,
],
```

- 每个域名为 key，value 为 meta 数组；`zone_id`（Cloudflare Zone ID）**必填**，`records_limit`（DNS 记录容量上限，默认 180）可选
- 可选 meta 键：`cf_token`（跨账号 Token，与 `cdn` 正交）、`cdn`（走 CF CDN，供 xhttp-cdn 节点选用）、`proxied`、`ech`、`expire_date`（详见 `config.default.php`）
- 域名池中的**第一个 key** 即为 primary domain（优先于 `node_root_domain`）
- 兼容纯数组格式 `["a.com", "b.com"]`（meta 为空，`records_limit` 默认 180）；此为旧版 `node_domain_pool` 的回退写法，新配置推荐用上述 meta 数组

**node_protocol_presets 结构：**
```json
{
    "threshold_mb": 2048,
    "high": "xhttp-hy2-ws-grpc",
    "low": "vision-hy2-ws-grpc"
}
```

- `threshold_mb`：内存阈值（MB），内部自动除以 1024 转换为 GB 后与 `node_memory`（GB）比较
- `high`：高配协议组名称
- `low`：低配协议组名称

---

## 接口详情

### Step 0: 申请节点 ID

节点首次启动时调用，向面板申请一个唯一的节点 ID。

**接口地址:** `POST /api/node/apply_id`

**请求参数:**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token |
| node_ip | string | 否 | - | 节点 IPv4 地址 |
| node_ipv6 | string | 否 | - | 节点 IPv6 地址 |

#### 节点 ID 回收机制

面板优先查找心跳超过 **32 天**的死亡节点复用其 ID（同时清理该节点的 `dns_records`），实现 ID 资源回收。若无死亡节点则创建新记录。**回收的旧身份会经 `NodeDefaults::resetToDefaults()` 全量重置为干净默认值**（含 `traffic_used=0`、`traffic_used_daily=0`、`last_traffic_reset_at=null` 及身份/指标/V2 字段），再交给后续 `register` 激活新基线，保证回收节点不残留前任计费/配置。

**此步骤决定了节点的 IP 栈命运**：apply_id 时写入的 `ip`/`ipv6` 会被 register 阶段强制执行单栈互斥。

**成功响应 (200):**
```json
{
    "node_id": 42
}
```

---

### Step 1: 注册节点信息与裂变

向面板注册节点详细信息，触发 **动态协议分配**、**裂变逻辑**、**阶梯等级引擎**、**IP 互斥**，并分配 **域名亲和性**。

**接口地址:** `POST /api/node/register`

**请求参数 (全量契约):**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token |
| node_id | integer | 是 | - | Step 0 返回的节点 ID |
| root_domain | string | 否 | - | 期望使用的根域名（域名亲和性） |
| v2_name | string | 否 | 动态分配 | **可选** 协议组名称（如 `xhttp-hy2-ws-grpc`），若提供则覆盖动态分配 |
| node_level | integer | 否 | 阶梯引擎 | **可选** 节点访问等级，若提供则覆盖阶梯引擎计算 |
| node_rxtx | string | 否 | `tx` | 计费模式：`tx` 或 `rxtx`（向后兼容 `node_rxtx_mode`、`billing_mode`） |
| node_cpu | integer | 否 | - | CPU 核心数（向后兼容 `cpu`） |
| node_memory | float | 否 | 0 | 内存大小（**GB**），未提供 `v2_name` 时决定协议组分配（向后兼容 `memory`） |
| node_disk | float | 否 | - | 磁盘大小（GB）（向后兼容 `disk`） |
| node_bandwidth | integer | 否 | 100 | 带宽（Mbps）（向后兼容 `bandwidth`） |
| node_unlock | string | 否 | - | 解锁信息，原始 Query String 格式（如 `Netflix=Yes&Gemini=No`） |
| node_info | string | 否 | - | 节点描述信息 |
| node_cost | float | 否 | 0 | 节点每 GB 流量成本，未提供 `node_level` 时决定节点等级 |
| node_group | integer | 否 | `2` | 节点分组 ID |
| node_traffic_limit | integer | 否 | 1000 | 每月流量额度（GB） |
| node_traffic_resetday | integer | 否 | 1 | 每月流量重置日期 |
| node_sort | integer | 否 | 0 | 节点排序权重 |
| node_traffic_rate | float | 否 | 1.0 | 流量计费倍率 |
| node_country_code | string | 否 | `un` | ISO 国家代码 (如 JP, US)，自动转大写用于命名、转小写存储 |
| node_country | string | 否 | - | 国家全称 |
| node_city | string | 否 | - | 城市全称 |
| node_ip | string | 否 | - | 节点 IPv4 地址（受 IP 互斥约束） |
| node_ipv6 | string | 否 | - | 节点 IPv6 地址（受 IP 互斥约束） |

#### IP 互斥 (Single-Stack Isolation)

`apply_id` 阶段已确定节点的 IPv4/IPv6 命运。`register` 阶段强制执行单栈隔离：

| apply_id 给出的 Fate | register 行为 |
|---|---|
| 仅 IPv4 | 接受 `node_ip`，强制置空 `ipv6` |
| 仅 IPv6 | 接受 `node_ipv6`，强制置空 `ip` |
| 两者都有 | 正常接受两个 |

子域名前缀也因此区分：IPv4 节点使用 `{random8}n{id}`（随机 8 位前缀降低连接域名特征被 GFW 识别追踪），IPv6 节点使用 `{random8}ipv6n{id}`。其中 ipv4 主节点直连 IP 无需 DNS；只有 ipv4 **clone** 节点的连接域名（`{random8}n{cloneId}.domain`）由 `resolve_dns` 建 A 记录，每个 clone 各自独立生成随机前缀。

#### 标准化命名

节点名称格式为 `{COUNTRY_CODE}-{City}`（如 `JP-Tokyo`）。`country_code` 自动转大写用于命名，同时转小写存储到 `country_code` 字段。

#### 动态协议分配

协议组按以下优先级决定：
1. **客户端指定**：若请求携带 `v2_name` 参数，直接使用该协议组。
2. **动态分配**：否则由面板根据 `node_memory`（GB）和 config 表的 `node_protocol_presets` 自动决定：
   - 读取 `threshold_mb`（默认 2048），除以 1024 转换为 GB
   - 若 `node_memory > threshold_gb` → 使用 `high` 协议组
   - 否则 → 使用 `low` 协议组

#### 裂变矩阵

根据 IP 栈类型和协议数量生成节点矩阵：
- **单栈**（仅 IPv4 或仅 IPv6）：4 个节点（1 主 + 3 克隆）
- **双栈**（IPv4 + IPv6）：8 个节点（1 主 + 7 克隆）

每个主/克隆节点被分配一个协议（如 `vision`、`hy2`、`ws`、`grpc`）。主节点占据第一个 slot，并强制只保留对应 IP 栈。

#### 克隆复用与死亡节点回收

重装触发 `/register` 时，不会盲目 `INSERT` 新记录。分两层复用：

1. **旧克隆复用**：通过 `WHERE is_clone = 主节点ID` 查出并更新已有克隆记录
2. **死亡节点回收**：若旧克隆不足，从全局心跳超时 > 32 天的死亡节点池中回收 ID，同时清理其 DNS 记录
3. 仅在两者都不足时才创建新节点

多余的旧克隆节点被降级（`is_clone = 0, status = 0`）。

#### V2 预设注入

每个克隆节点创建时自动注入协议预设（`V2_PRESETS` 常量），包括 `type`、`v2_net`、`v2_port`、`v2_tls`、`v2_flow`、`v2_fp`、`v2_alpn`、`v2_path`、`v2_servicename` 等字段。特殊规则：在 `vision+grpc` 组合中，grpc 端口自动设为 2053 避免端口冲突。

#### 阶梯等级引擎

节点等级按以下优先级决定：
- **客户端指定**：若请求携带 `node_level` 参数，直接使用该等级。
- **自动计算**：否则由 `node_cost` 驱动：
  - **主节点等级** = `max(1, floor(node_cost))`，最低为 1
  - **克隆节点等级** = `rand(主节点等级, min(5, 主节点等级+2))`，保证克隆等级 ≥ 主节点

#### 域名亲和性分配策略 (Domain Affinity)

系统按照以下优先级分配节点的 `root_domain`：
1. **优先使用请求携带的 `root_domain`**：从域名池 meta 中取 `records_limit`（默认 180），若 `dns_records` 表中该域名的记录数 < limit 则沿用。
2. **自动从域名池选取**：若未携带或已满，按 `node_domain_map` 字典顺序遍历，选取第一个记录数 < limit 的域名。
3. **兜底策略**：若全部溢出，使用域名池第一个 key 作为 primary domain（**优先于** `node_root_domain`）。仅当域名池完全为空时才回退到 `node_root_domain`。

#### node_ids 封卷

裂变完成后，主节点的 `node_ids` 字段写入**逗号分隔的 ID 字符串**（如 `"42,43,44,45"`），供 `resolve_dns` 集群级批量同步使用。

#### 成功响应 (200):
```json
{
    "status": "success",
    "node_id": 42,
    "clone_node_ids": [43, 44, 45],
    "node_ids": "42,43,44,45",
    "root_domain": "example.com",
    "v2_name": "vision-hy2-ws-grpc"
}
```

**响应字段：**

| 字段 | 说明 |
|---|---|
| `node_id` | 主节点 ID |
| `clone_node_ids` | 克隆节点 ID 数组 |
| `node_ids` | 逗号分隔的全量节点 ID 字符串 |
| `root_domain` | 分配的根域名 |
| `v2_name` | 协议组名称 |

---

### Step 2: 集群级 DNS 三向对账

同步主节点及其所有克隆节点的 DNS 记录到 Cloudflare。一次调用处理 `node_ids` 中包含的所有节点。

**接口地址:** `POST /api/node/resolve_dns`

**请求参数:**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token |
| node_id | integer | 是 | - | **主节点** ID（非克隆节点 ID） |
| force | boolean | 否 | `false` | 强制跳过缓存拦截，即使状态一致也重新同步 |

#### 集群级批量处理

1. 从主节点的 `node_ids` 字段解析出所有节点 ID（逗号分隔字符串 → 数组）
2. 遍历每个节点，根据其 `server` 字段（ipv4 clone 连接域名含随机前缀，如 `a1b2c3d4n42.ssmail.win`）解析出 subdomain（首个 `.` 之前整段）和 root_domain；subdomain 始终与 `server` 前缀对齐，与前缀是否随机解耦
3. 从 `node_domain_map` 配置动态获取该域名的 `zone_id`
4. 为每个节点生成 DNS 蓝图（blueprint），包含 subdomain、IP、proxied 状态
5. 对每个蓝图执行三向对账
6. 清理孤儿记录（节点不再需要的 record_type）

#### CDN Proxy 决策

从节点的 `v2_net` 字段（**非** `v2_name`）派生 DNS 记录的 `proxied` 状态：

| `v2_net` | `proxied` | 说明 |
|---|---|---|
| `ws` | `true` | WebSocket 需要 CDN 代理（橙色云） |
| `grpc` | `true` | gRPC 需要 CDN 代理 |
| `tcp` (vision) | `false` | VLESS Vision 仅 DNS 解析（灰色云） |
| `hysteria2` | `false` | HY2 使用 QUIC/UDP，无法走 CDN |
| `xhttp` | `true` | XHTTP 需要 CDN 代理 |

#### 三向对账场景

**Scene A (缓存拦截)：** subdomain + root_domain + ip_addr + proxied 完全一致 → **不调用** CF API，直接返回 `no_change`。

**Scene B (域名变更 → DELETE-before-CREATE)：** subdomain 或 root_domain 变动时：
1. 使用旧 `cf_record_id` 调用 CF `DELETE` 抹除旧解析
2. 删除本地 `dns_records` 行
3. 调用 CF `POST` 创建新解析
4. 成功后插入新 `dns_records` 行

**Scene C (新建)：** 本地无记录 → 调用 CF `POST` 创建 → 成功后插入 `dns_records`。

**Scene D (IP/代理变更 → PUT)：** 域名不变但 IP 或 `proxied` 变动 → 调用 CF `PUT` 更新 → 成功后更新本地记录。

**严格约束：** CF API 返回非 2xx 时，本地 `dns_records` 保持旧状态不变，允许下次请求继续对账。如果 Scene B 中 DELETE 成功但 POST 失败，本地旧记录已被删除，下次请求会重新创建。

#### 安全机制

- **90 秒全局超时** + 单节点 60 秒超时预警
- 孤儿记录检测：自动清理节点不再需要的 record_type
- 完整日志记录（每步操作、耗时、CF 返回）

#### 成功响应 (200):
```json
{
    "status": "success",
    "results": [
        {"action": "no_change", "success": true, "node_id": 42, "type": "A", "fqdn": "a1b2c3d4n42.ssmail.win", "proxied": false},
        {"action": "created", "success": true, "node_id": 43, "type": "A", "fqdn": "b2c3d4e5n43.ssmail.win", "cf_id": "abc123", "proxied": true}
    ],
    "stats": {
        "created": 1,
        "updated": 0,
        "deleted": 0,
        "skipped": 0,
        "failed": 0,
        "no_change": 1
    },
    "elapsed_s": 2.456,
    "message": "DNS cluster synchronized"
}
```

**`results[].action` 可能值：**

| action | 说明 |
|---|---|
| `no_change` | 缓存拦截，未调用 CF |
| `created` | 新建 CF 记录 + 本地记录 |
| `updated_ip` | IP 变更，CF PUT 成功 |
| `updated_proxied` | 仅 proxied 状态变更 |
| `deleted_orphan` | 清理孤儿记录 |
| `skipped_timeout` | 全局超时跳过 |
| `skipped_no_zone` | zone_id 缺失跳过 |
| `create_failed` / `update_failed` | CF 操作失败 |

---

### Step 3: 拉取 Xray 配置

**接口地址:** `GET /api/node/config`

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token |
| node_id | integer | 是 | 节点 ID |

**技术要点：**

1. **类型保留 JSON 注入：** 占位符 `__wsPort__` 会被替换为整数 `10011`。实现原理：模板先以 `json_decode($raw)` 解码保留 stdClass 类型信息，注入后通过 `restoreJsonObjectTypes()` 对照原始模板恢复 JSON 对象/数组类型。

2. **内部监听端口与外部连接端口分离：** 
   - **Xray 节点内部监听端口（下发到 `node.json`）：**
     在 `config` 接口中，面板通过变量注入的方式，将固定的端口占位符替换为具体数值下发给 Xray 模板。各个协议对应的内部端口如下：
     - `ws`：`__wsPort__` ➔ `10011` (路径 `srp-ws`)
     - `grpc`：`__grpcPort__` ➔ `10012` (服务名 `srp-grpc`)
     - `xhttp`：`__xhttpPort__` ➔ `10013` (路径 `srp-xhttp`)
     - `hy2`：`__hy2Port__` ➔ `443`
     - `vision`：`__visionPort__` ➔ `443`
   - **客户端连接端口（写入数据库 `v2_port` 字段）：**
     对于 `ws`, `xhttp`, `grpc`，外部通常通过 Nginx 等反代使用 443 端口连接，因此它们在数据库中预设的 `v2_port` 统一为 `443`。
     *(特例：当协议组中同时包含 `vision` 和 `grpc` 时，为了避免直连端口冲突，`grpc` 节点在数据库中的 `v2_port` 会自动修改为 `2053`)*。

3. **HY2 直连 IP 覆盖：** 当节点 `v2_net === 'hysteria2'` 时，配置中所有 `server` 字段被替换为节点的原始 IP 地址（HY2 使用 QUIC/UDP，无法走 CDN 代理）。

4. **流媒体解锁模板：** 从 `resources/templates/xray/unlock/{service}.json` 文件加载解锁规则，支持 config 表中的 `unlock_{service}_address/port/password/method` 变量注入。

---

### Step 3.5: 拉取 Nginx 配置

**接口地址:** `POST /api/node/nginx_config`

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token（支持 body 或 query string） |
| node_id | integer | 是 | 节点 ID |

**成功响应 (200):** `Content-Type: text/plain`

直接返回渲染后的 nginx 配置文本，无 JSON 包裹。节点侧直接写盘：

```bash
curl -sS -o /etc/nginx/conf.d/proxy.conf \
    -d "token=${API_TOKEN}&node_id=${NODE_ID}" \
    "${API_URL}/api/node/nginx_config"
nginx -t && systemctl restart nginx
```

**错误响应（纯文本，HTTP 状态码）：**

| 状态码 | 含义 |
|---|---|
| 200 | 成功，返回 conf 文本 |
| 401 | token 无效或缺失 |
| 403 | 节点已离线 |
| 404 | 节点不存在 |
| 500 | 模板缺失 |

**技术要点：**

1. **模板选择**：通过主节点的 `v2_name`（协议组名称）定位模板文件（`resources/templates/nginx/{v2_name}.conf`）。克隆节点自动回溯到主节点获取模板。
2. **两种模式的配置差异**：
   - `xhttp-hy2-ws-grpc`：完整 HTTPS 配置（80 重定向 + 443 TLS 终结 + ws/grpc/xhttp 分流 + 伪装站回落）
   - `vision-hy2-ws-grpc`：仅 HTTP 80 → HTTPS 301 重定向（Xray Vision 和 HY2 各自直接监听 443，nginx 只需处理 80 端口）
3. **变量注入**：模板中的占位符（如 `__wsPath__`、`__wsPort__`、`__nodeDomain__`）由面板一次性替换为实际值，节点无需处理。

---

### Step 4: 上报状态与流量

**接口地址:** `POST /api/node/status`

**请求参数:**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token |
| node_id | integer | 是 | - | 节点 ID |
| raw_rx | float | 否 | `0` | 原始接收字节总数 |
| raw_tx | float | 否 | `0` | 原始发送字节总数 |
| server_uptime | integer | 否 | *(沿用)* | 服务器运行时间（秒） |
| node_bandwidth | integer | 否 | *(沿用)* | 带宽（Mbps） |
| monitor | string | 否 | *(沿用)* | 节点监控地址，写入 `monitor_url`（仅当上报非空值时覆盖；为空或缺失则保留原值，避免空串误清。`monitor_url` 现由本心跳 `status()` 维护，不再由每日 cron 打包写入） |

#### 计费与健康引擎
- **计费模式**：依据 `node_rxtx` 字段执行 `tx`（仅上行）或 `rxtx`（双向均值）逻辑。
- **三态增量计算**：支持网卡重启归零检测（`rawTotal < lastRaw`），防止流量异常回滚。
- **健康引擎**：依据月度剩余流量自动判定 `node_health`（0=拥塞，1=健康）。
- **120G 融断**：剩余流量 < 120GB 时强制设置 `status = 0`。

**成功响应 (200):**
```json
{
    "status": "success",
    "node_status": 1
}
```

---

## Artisan 命令

### initDnsRecords

云端对账工具，同步 Cloudflare DNS 状态与本地 `dns_records` 表。

```bash
php artisan initDnsRecords
```

**执行逻辑：**
1. 遍历 `node_domain_map`（`.config.php`）包含的所有 Root Domain。
2. 调用 Cloudflare API 拉取该域名下**所有的 A 和 AAAA 记录**。
3. **Diff 清理**：查询本地 `dns_records` 表中该域名的 A/AAAA 记录，若某条记录在云端不存在，物理删除。
4. **关联入库**：遍历云端拉回的 A/AAAA 记录，通过 FQDN 匹配 `ss_node.server` 字段（ipv4 clone 连接域名含随机前缀，如 `a1b2c3d4n156.ssmail.win`），若匹配成功则写入或更新至 `dns_records` 表并绑定 `node_id`。

**安全约束：**
- 严格过滤 `A` 和 `AAAA` 类型，**绝不触碰** TXT、MX、CNAME 等非节点解析记录。
- 匹配方式为基于域名的 FQDN 精确匹配（`ss_node.server`），而非 IP 匹配，避免克隆节点共享 IP 导致的歧义。

---

## 环境依赖 (必需)

### .env 变量

| 变量 | 说明 |
|---|---|
| `API_TOKEN` | 接口认证密钥 |
| `CLOUDFLARE_EMAIL` | Cloudflare 账户邮箱 |
| `CLOUDFLARE_API_KEY` | Cloudflare API Key |
| `CLOUDFLARE_ZONE_ID` | Cloudflare 默认 Zone ID |

### 环境与配置

| 配置项 | 位置 | 说明 |
|---|---|---|
| `node_root_domain` | config | 兜底主域名 |
| `node_domain_map` | `.config.php` | 富字典格式域名池（domain => meta: zone_id, records_limit, cf_token, cdn …） |
| `node_protocol_presets` | config | 协议预设（threshold_mb, high, low） |

### 模板文件依赖

```
resources/templates/
├── xray/
│   ├── vision-hy2-ws-grpc.json
│   ├── xhttp-hy2-ws-grpc.json
│   └── unlock/
│       ├── openai.json
│       ├── netflix.json
│       └── ...
└── nginx/
    ├── vision-hy2-ws-grpc.conf
    └── xhttp-hy2-ws-grpc.conf
```

---

*文档更新时间: 2026-08-09 | 基于 commit: 2c985d9f*
