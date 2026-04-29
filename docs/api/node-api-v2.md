# Node API v2 接口文档

## 概述

Node API v2 是 NPanel 面向 **瘦节点、胖面板** 架构设计的新一代节点管理 API。节点端通过 5 步生命周期完成从申请 ID 到运行状态上报的全流程：

```
apply_id → register → resolve_dns → config → status(循环)
```

**设计理念：** 面板承担所有配置渲染、DNS 管理、流量计费、健康判定逻辑；节点端只需提供硬件信息和原始流量计数器，最大限度降低节点侧复杂度。

**Base URL:** `http://your-domain.com/api/node`

**认证方式:** Token 认证 — 所有接口均需携带 `token` 参数（或 `X-API-Token` 请求头），值为 `.env` 中 `API_TOKEN` 变量。

**适用版本:** commit `f6b34029` (2026-04-29) 及之后。

---

## 生命周期流程图

```
┌──────────────────────────────────────────────────────┐
│  Node API v2 生命周期                                  │
├──────────────────────────────────────────────────────┤
│                                                      │
│  Step 0   POST /apply_id       申请节点 ID            │
│             ↓                                        │
│  Step 1   POST /register       注册节点 + 动态裂变     │
│             ↓                                        │
│  Step 2   POST /resolve_dns    解析 DNS 记录          │
│             ↓                                        │
│  Step 3   POST /config         拉取 Xray 配置          │
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
| `v2_name` | string | 动态分配 | 单个协议名（如 `ws`、`hy2`），由 register 时根据内存自动分配协议组后拆分 |
| `node_rxtx` | string | `tx` | 流量计费模式：`tx`（仅上行）或 `rxtx`（双向均值） |
| `node_cpu` | integer | nullable | CPU 核心数 |
| `node_memory` | float | nullable | 内存大小（MB） |
| `node_disk` | float | nullable | 磁盘大小（GB） |
| `node_group` | integer | 1 | 节点分组 ID |
| `node_country` | string | nullable | 国家名称（如 Japan） |
| `node_city` | string | nullable | 城市名称（如 Tokyo） |
| `node_health` | integer | 1 | 健康状态：0=拥塞，1=健康 |
| `is_clone` | integer | 0 | 克隆源节点 ID，`0` 表示主节点 |
| `node_ids` | text (JSON) | nullable | 主节点专属字段，存储该节点矩阵中所有节点 ID 的 JSON 数组 |
| `last_raw_total` | float | 0 | 上一次上报的原始流量总值，用于增量计算 |
| `server_uptime` | integer | 0 | 服务器运行时间（秒） |

---

## 系统配置项（config 表）

协议分配和域名池由 config 表驱动，**非硬编码**，可通过后台管理界面修改：

| 配置项 | 格式 | 说明 |
|--------|------|------|
| `node_root_domain` | string | 主域名（如 `ssmail.win`） |
| `node_domain_pool` | JSON array | 备用域名池，如 `["backup1.com", "backup2.net"]` |
| `node_protocol_presets` | JSON object | 协议预设配置，详见下方 |

**node_protocol_presets 结构：**
```json
{
    "threshold_mb": 2048,
    "high": "xhttp-hy2-ws-grpc",
    "low": "vision-hy2-ws-grpc"
}
```

- `threshold_mb`：内存阈值（MB），大于此值使用 `high` 协议组，否则使用 `low`
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

**成功响应 (200):**
```json
{
    "node_id": 42
}
```

---

### Step 1: 注册节点信息与裂变

向面板注册节点详细信息，触发 **动态协议分配**、**裂变逻辑**、**阶梯等级引擎**，并分配 **域名亲和性**。

**接口地址:** `POST /api/node/register`

**请求参数 (全量契约):**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token |
| node_id | integer | 是 | - | Step 0 返回的节点 ID |
| root_domain | string | 否 | - | 期望使用的根域名（域名亲和性） |
| node_rxtx | string | 否 | `tx` | 计费模式：`tx` 或 `rxtx`（向后兼容 `node_rxtx_mode`、`billing_mode`） |
| node_cpu | integer | 否 | - | CPU 核心数 |
| node_memory | float | 是 | - | 内存大小（MB），**决定协议组分配** |
| node_disk | float | 否 | - | 磁盘大小（GB） |
| bandwidth | integer | 否 | 100 | 带宽（Mbps） |
| node_unlock | string | 否 | - | 解锁信息，原始 Query String 格式（如 `Netflix=Yes&Gemini=No`） |
| node_info | string | 否 | - | 节点描述信息 |
| node_cost | float | 否 | 0 | 节点每 GB 流量成本，**决定节点等级** |
| node_group | integer | 否 | 1 | 节点分组 ID |
| node_traffic_limit | integer | 否 | 1000 | 每月流量额度（GB） |
| node_traffic_resetday | integer | 否 | 1 | 每月流量重置日期 |
| node_sort | integer | 否 | 0 | 节点排序权重 |
| node_traffic_rate | float | 否 | 1.0 | 流量计费倍率 |
| node_country_code | string | 否 | `un` | ISO 国家代码 (如 JP, US) |
| node_country | string | 否 | - | 国家全称 |
| node_city | string | 否 | - | 城市全称 |
| node_ip | string | 否 | - | 节点 IPv4 地址 |
| node_ipv6 | string | 否 | - | 节点 IPv6 地址 |

**成功响应 (200):**
```json
{
    "status": "success",
    "main_node_id": 42,
    "clone_node_ids": [43, 44, 45],
    "node_ids": [42, 43, 44, 45],
    "root_domain": "example.com",
    "v2_name": "vision-hy2-ws-grpc"
}
```

#### 动态协议分配

协议组**不再由客户端指定**，而是由面板根据 `node_memory` 和 config 表的 `node_protocol_presets` 自动决定：

1. 读取 `node_protocol_presets.threshold_mb`（默认 2048 MB）
2. 若 `node_memory > threshold_mb` → 使用 `high` 协议组（如 `xhttp-hy2-ws-grpc`）
3. 否则 → 使用 `low` 协议组（如 `vision-hy2-ws-grpc`）
4. 协议组拆分为单个协议后随机打乱分配给各节点

#### 裂变矩阵

根据 IP 栈类型和协议数量生成节点矩阵：
- **单栈**（仅 IPv4 或仅 IPv6）：4 个节点（1 主 + 3 克隆）
- **双栈**（IPv4 + IPv6）：8 个节点（1 主 + 7 克隆）

每个主/克隆节点被分配一个随机协议（如 `vision`、`hy2`、`ws`、`grpc`），协议顺序被打乱。

#### 克隆复用（防无限增殖）

重装触发 `/register` 时，不会盲目 `INSERT` 新记录。优先通过 `WHERE is_clone = 主节点ID` 查出并**复用**旧的关联记录：
- 若旧记录数量足够 → 直接更新（复活）
- 若旧记录不足 → 仅补充创建缺少的记录
- 若旧记录多余 → 删除多余的记录

#### 阶梯等级引擎

节点等级由 `node_cost` 自动计算，**不接受客户端传入**：

- **主节点等级** = `max(1, floor(node_cost))`，最低为 1
- **克隆节点等级** = `rand(主节点等级, min(5, 主节点等级+2))`，保证克隆等级 ≥ 主节点

#### 域名亲和性分配策略 (Domain Affinity)

系统按照以下优先级分配节点的 `root_domain`：
1. **优先使用请求携带的 `root_domain`**：只要该域名在 `dns_records` 表中的记录数未超过 **180** 条。
2. **自动从域名池选取**：若未携带或已满，从配置项 `node_domain_pool`（JSON 数组）顺序选取第一个记录数 < 180 的域名。
3. **兜底策略**：若全部溢出，强制使用主域名 `node_root_domain`。

#### node_ids 封卷

裂变完成后，主节点的 `node_ids` 字段会写入包含矩阵中所有节点 ID 的 JSON 数组，如 `[42, 43, 44, 45]`。

---

### Step 2: 解析 DNS

同步本地数据库状态到 Cloudflare。

**接口地址:** `POST /api/node/resolve_dns`

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token |
| node_id | integer | 是 | 节点 ID |

**行为逻辑（三场景对账）：**
- **Scene A (静默拦截)**：若本地记录显示的 IP/域名与目标状态完全一致，则**不调用** Cloudflare API，直接返回成功。
- **Scene B (IP 更新)**：域名未变但 IP 变动，执行 Cloudflare `PUT` 更新。
- **Scene C (原子域名切换)**：域名发生变动，采用 **POST-before-DELETE** 策略：
    1. 先向 Cloudflare 申请创建新域名的 `POST` 记录。
    2. 只有 `POST` 成功后，才尝试 `DELETE` 旧记录。
    3. **脏状态回滚**：若 `POST` 失败，旧记录在数据库中将完好保留，确保服务不因 API 故障而彻底中断。

**成功响应 (200):**
```json
{
    "status": "success",
    "actions": ["no_change"],
    "message": "DNS reconciled"
}
```

---

### Step 3: 拉取 Xray 配置

**接口地址:** `POST /api/node/config`

> **注意：** 此接口已从 `GET` 改为 `POST`，`GET` 请求将返回 **405 Method Not Allowed**。

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token |
| node_id | integer | 是 | 节点 ID |

**技术要点：** 注入引擎支持 **类型保留**。例如占位符 `__wsPort__` 会被替换为整数 `10010` 而非字符串 `"10010"`，确保符合 Xray JSON Schema 要求。

---

### Step 4: 上报状态与流量

**接口地址:** `POST /api/node/status`

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token |
| node_id | integer | 是 | 节点 ID |
| raw_rx | float | 否 | 原始接收字节总数 |
| raw_tx | float | 否 | 原始发送字节总数 |
| server_uptime | integer | 否 | 服务器运行时间（秒） |

#### 计费与健康引擎
- **计费模式**：依据 `node_rxtx` 字段执行 `tx`（仅上行）或 `rxtx`（双向均值）逻辑。
- **三态增量计算**：支持网卡重启归零检测（`rawTotal < lastRaw`），防止流量异常回滚。
- **健康引擎**：依据月度剩余流量自动判定 `node_health`（0=拥塞，1=健康）。
- **120G 融断**：剩余流量 < 120GB 时强制设置 `status = 0`。

---

## Artisan 命令

### initDnsRecords

云端对账工具，同步 Cloudflare DNS 状态与本地 `dns_records` 表。

```bash
php artisan initDnsRecords
```

**执行逻辑：**
1. 遍历 `config` 表中 `node_domain_pool` 包含的所有 Root Domain。
2. 调用 Cloudflare API 拉取该域名下**所有的 A 和 AAAA 记录**。
3. **Diff 清理**：查询本地 `dns_records` 表中该域名的 A/AAAA 记录，若某条记录在云端不存在，物理删除。
4. **关联入库**：遍历云端拉回的 A/AAAA 记录，通过 FQDN 匹配 `ss_node.server` 字段（如 `n156.ssmail.win`），若匹配成功则写入或更新至 `dns_records` 表并绑定 `node_id`。

**安全约束：**
- 严格过滤 `A` 和 `AAAA` 类型，**绝不触碰** TXT、MX、CNAME 等非节点解析记录。
- 匹配方式为基于域名的 FQDN 精确匹配（`ss_node.server`），而非 IP 匹配，避免克隆节点共享 IP 导致的歧义。

---

## 环境依赖 (必需)

- `CLOUDFLARE_EMAIL` / `CLOUDFLARE_API_KEY` / `CLOUDFLARE_ZONE_ID`
- `node_root_domain` (主域名)
- `node_domain_pool` (备用域名池，JSON 数组格式)
- `node_protocol_presets` (协议预设，JSON 对象格式)

---

*文档更新时间: 2026-04-29 | 基于 commit: f6b34029*
