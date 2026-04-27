# Node API v2 接口文档

## 概述

Node API v2 是 NPanel 面向 **瘦节点、胖面板** 架构设计的新一代节点管理 API。节点端通过 5 步生命周期完成从申请 ID 到运行状态上报的全流程：

```
apply_id → register → resolve_dns → config → status(循环)
```

**设计理念：** 面板承担所有配置渲染、DNS 管理、流量计费、健康判定逻辑；节点端只需提供硬件信息和原始流量计数器，最大限度降低节点侧复杂度。

**Base URL:** `http://your-domain.com/api/node`

**认证方式:** Token 认证 — 所有接口均需携带 `token` 参数（或 `X-API-Token` 请求头），值为 `.env` 中配置的 `API_TOKEN`。

**适用版本:** commit `4600a679` (2026-04-27) 及之后。

---

## 生命周期流程图

```
┌──────────────────────────────────────────────────────┐
│  Node API v2 生命周期                                  │
├──────────────────────────────────────────────────────┤
│                                                      │
│  Step 0   POST /apply_id       申请节点 ID            │
│             ↓                                        │
│  Step 1   POST /register       注册节点信息 + 裂变     │
│             ↓                                        │
│  Step 2   POST /resolve_dns    解析 DNS 记录          │
│             ↓                                        │
│  Step 3   GET  /config         拉取 Xray 配置          │
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
| `v2_name` | string | `vision-hy2-ws-grpc` | 协议模板名称，决定裂变协议组合 |
| `node_traffic_rxtx_mode` | string | `tx` | 流量计费模式：`tx`（仅上行）或 `rxtx`（双向均值） |
| `node_cpu` | string | nullable | CPU 型号/核心数 |
| `node_memory` | float | nullable | 内存大小（GB） |
| `node_disk` | float | nullable | 磁盘大小（GB） |
| `node_country` | string | nullable | 国家名称（如 Japan） |
| `node_city` | string | nullable | 城市名称（如 Tokyo） |
| `node_health` | integer | 1 | 健康状态：0=不健康（拥塞），1=健康 |
| `is_clone` | integer | - | 克隆源节点 ID，`0` 表示主节点 |
| `last_raw_total` | float | 0 | 上一次上报的原始流量总值，用于增量计算 |
| `server_uptime` | integer | 0 | 服务器运行时间（秒） |

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

向面板注册节点详细信息，触发 **裂变逻辑** 并分配 **域名亲和性**。

**接口地址:** `POST /api/node/register`

**请求参数:**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token |
| node_id | integer | 是 | - | Step 0 返回的节点 ID |
| root_domain | string | 否 | - | 期望使用的根域名（域名亲和性） |
| v2_name | string | 否 | `vision-hy2-ws-grpc` | 协议模板名称 |
| billing_mode | string | 否 | `tx` | 计费模式：`tx` 或 `rxtx` |
| cpu | string | 否 | - | CPU 信息 |
| memory | float | 否 | - | 内存（GB） |
| disk | float | 否 | - | 磁盘（GB） |
| node_country | string | 否 | - | 国家名称 |
| node_city | string | 否 | - | 城市名称 |
| node_country_code | string | 否 | `un` | ISO 国家代码 |
| ... | ... | ... | ... | 其他业务参数（bandwidth, level, group 等） |

**成功响应 (200):**
```json
{
    "status": "success",
    "main_node_id": 42,
    "clone_node_ids": [43, 44, 45],
    "root_domain": "example.com"
}
```

#### 域名亲和性分配策略 (Domain Affinity)
系统按照以下优先级分配节点的 `root_domain`：
1. **优先使用请求携带的 `root_domain`**：只要该域名在 `dns_records` 表中的记录数未超过 **180** 条。
2. **自动从域名池选取**：若未携带或已满，从配置项 `node_domain_pool` 顺序选取第一个记录数 < 180 的域名。
3. **兜底策略**：若全部溢出，强制使用主域名 `node_root_domain`。

---

### Step 2: 解析 DNS

同步本地数据库状态到 Cloudflare。

**接口地址:** `POST /api/node/resolve_dns`

**行为逻辑（三场景对账）：**
- **Scene A (静默拦截)**：若本地记录显示的 IP/域名与目标状态完全一致，则**不调用** Cloudflare API，直接返回成功。
- **Scene B (IP 更新)**：域名未变但 IP 变动，执行 Cloudflare `PUT` 更新。
- **Scene C (原子域名切换)**：域名发生变动，采用 **POST-before-DELETE** 策略：
    1. 先向 Cloudflare 申请创建新域名的 `POST` 记录。
    2. 只有 `POST` 成功后，才尝试 `DELETE` 旧记录。
    3. **脏状态回滚**：若 `POST` 失败，旧记录在数据库中将完好损保留，确保服务不因 API 故障而彻底中断。

**成功响应 (200):**
```json
{
    "status": "success",
    "actions": ["no_change"], 
    "message": "DNS reconciled"
}
```
*`actions` 可能包含: `no_change`, `updated_ip`, `swapped_domain`, `created`*

---

### Step 3: 拉取 Xray 配置

**接口地址:** `GET /api/node/config`

> **技术要点：** 注入引擎支持 **类型保留**。例如占位符 `__wsPort__` 会被替换为整数 `10010` 而非字符串 `"10010"`，确保符合 Xray JSON Schema 要求。

---

### Step 4: 上报状态与流量

**接口地址:** `POST /api/node/status`

#### 计费与健康引擎
- **计费模式**：依据 `node_traffic_rxtx_mode` 字段执行 `tx` 或 `rxtx` 逻辑。
- **三态增量计算**：支持网卡重启归零检测（`rawTotal < lastRaw`），防止流量异常回滚。
- **健康引擎**：依据月度剩余流量自动判定 `node_health`（0=拥塞，1=健康）。
- **120G 融断**：剩余流量 < 120GB 时强制设置 `status = 0`。

---

## 环境依赖 (必需)

- `CLOUDFLARE_EMAIL` / `CLOUDFLARE_API_KEY` / `CLOUDFLARE_ZONE_ID`
- `node_root_domain` (主域名)
- `node_domain_pool` (备用域名池，逗号分隔)

---

*文档生成时间: 2026-04-27 | 基于 commit: 4600a679*
