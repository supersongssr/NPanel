# Node API v2 接口文档

## 概述

Node API v2 是 NPanel 面向 **瘦节点、胖面板** 架构设计的新一代节点管理 API。节点端通过 5 步生命周期完成从申请 ID 到运行状态上报的全流程：

```
apply_id → register → resolve_dns → config → status(循环)
```

**设计理念：** 面板承担所有配置渲染、DNS 管理、流量计费、健康判定逻辑；节点端只需提供硬件信息和原始流量计数器，最大限度降低节点侧复杂度。

**Base URL:** `http://your-domain.com/api/node`

**认证方式:** Token 认证 — 所有接口均需携带 `token` 参数（或 `X-API-Token` 请求头），值为 `.env` 中配置的 `API_TOKEN`。

**适用版本:** commit `faf6276b` (2026-04-27) 及之后，含修复 `0a8a93d5`。

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

Node API v2 新增了以下 `ss_node` 表字段（通过 3 个 migration 文件）：

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `v2_name` | string | `vision-hy2-ws-grpc` | 协议模板名称，决定裂变协议组合 |
| `billing_mode` | string | `tx` | 流量计费模式：`tx`（仅上行）或 `rxtx`（双向均值） |
| `cpu` | string | nullable | CPU 型号/核心数 |
| `memory` | float | nullable | 内存大小（GB） |
| `disk` | float | nullable | 磁盘大小（GB） |
| `is_clone` | integer | - | 克隆源节点 ID，`null` 表示主节点 |
| `last_raw_total` | float | 0 | 上一次上报的原始流量总值，用于增量计算 |
| `health` | integer | 0 | 健康状态：0=不健康，1=健康 |
| `server_uptime` | integer | 0 | 服务器运行时间（秒） |
| `server_total_traffic` | - | - | 服务器总流量 |

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

> `node_ip` 和 `node_ipv6` 至少提供一个。

**请求示例:**
```bash
curl -X POST http://your-domain.com/api/node/apply_id \
  -d "token=your_api_token" \
  -d "node_ip=203.0.113.10" \
  -d "node_ipv6=2001:db8::1"
```

**成功响应 (200):**
```json
{
    "node_id": 42
}
```

**错误响应 (401):**
```json
{
    "status": "error",
    "message": "Invalid token"
}
```

**响应字段说明:**

| 字段名 | 类型 | 说明 |
|--------|------|------|
| node_id | integer | 新创建的节点 ID，初始状态为维护中 (`status=0`) |

**行为说明:**
- 面板自动创建一条 `ss_node` 记录
- 节点名称格式为 `New Node {ip}`
- 初始 `status = 0`（维护中），需在后续 register 步骤激活

---

### Step 1: 注册节点信息与裂变

向面板注册节点详细信息，并触发 **裂变逻辑** — 自动创建协议/协议栈克隆节点。

**接口地址:** `POST /api/node/register`

**请求参数:**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token |
| node_id | integer | 是 | - | Step 0 返回的节点 ID |
| v2_name | string | 否 | `vision-hy2-ws-grpc` | 协议模板名称 |
| billing_mode | string | 否 | `tx` | 计费模式：`tx` 或 `rxtx` |
| cpu | string | 否 | - | CPU 信息 |
| memory | float | 否 | - | 内存（GB） |
| disk | float | 否 | - | 磁盘（GB） |
| bandwidth | integer | 否 | 100 | 带宽（Mbps） |
| node_unlock | string | 否 | - | 解锁信息（逗号分隔键值对） |
| node_info | string | 否 | - | 节点描述信息 |
| node_level | integer | 否 | 1 | 节点等级（用户访问等级） |
| node_group | integer | 否 | 1 | 节点分组 |
| node_cost | float | 否 | 0 | 节点成本 |
| node_traffic_limit | integer | 否 | 1000 | 月流量限额（GB，自动转为字节） |
| node_traffic_resetday | integer | 否 | 1 | 每月流量重置日 |
| node_sort | integer | 否 | 0 | 节点排序权重 |
| node_traffic_rate | float | 否 | 1.0 | 流量倍率 |
| node_country_code | string | 否 | `un` | ISO 国家代码（自动转小写） |
| node_ip | string | 否 | 保持原值 | 节点 IPv4 |
| node_ipv6 | string | 否 | 保持原值 | 节点 IPv6 |

**请求示例:**
```bash
curl -X POST http://your-domain.com/api/node/register \
  -d "token=your_api_token" \
  -d "node_id=42" \
  -d "v2_name=vision-hy2-ws-grpc" \
  -d "billing_mode=tx" \
  -d "cpu=EPYC 4C/8T" \
  -d "memory=8" \
  -d "disk=80" \
  -d "bandwidth=1000" \
  -d "node_country_code=US" \
  -d "node_level=1" \
  -d "node_traffic_limit=2000"
```

**成功响应 (200):**
```json
{
    "status": "success",
    "main_node_id": 42,
    "clone_node_ids": [43, 44, 45],
    "root_domain": "example.com"
}
```

**错误响应:**

| HTTP 状态码 | 场景 |
|-------------|------|
| 401 | Token 无效 |
| 404 | 节点 ID 不存在 |

```json
// 404
{
    "status": "error",
    "message": "Node not found"
}
```

**响应字段说明:**

| 字段名 | 类型 | 说明 |
|--------|------|------|
| main_node_id | integer | 主节点 ID |
| clone_node_ids | array | 裂变产生的克隆节点 ID 列表 |
| root_domain | string | 系统配置的根域名（`node_root_domain`） |

#### 裂变逻辑详解

裂变根据 `v2_name` 和节点 IP 协议栈自动计算需要创建的克隆节点数量：

| v2_name | 协议组合 | 仅 IPv4 | 仅 IPv6 | 双栈 |
|---------|----------|---------|---------|------|
| `vision-hy2-ws-grpc` | vision, hy2, ws, grpc | 4 节点 (1+3) | 4 节点 (1+3) | 8 节点 (1+7) |
| `xhttp-hy2-ws-grpc` | xhttp, hy2, ws, grpc | 4 节点 (1+3) | 4 节点 (1+3) | 8 节点 (1+7) |

- 主节点算作第 1 个，克隆节点补齐剩余数量
- 每个克隆节点分配唯一子域名：`node{cloneId}.{rootDomain}`
- 克隆节点的 `is_clone` 字段指向主节点 ID
- 注册时会清除该主节点之前已有的所有克隆节点（`is_clone = nodeId`），再重新创建

---

### Step 2: 解析 DNS

将节点的子域名 DNS 记录指向其 IP 地址。通过 Cloudflare API 自动管理 DNS 记录。

**接口地址:** `POST /api/node/resolve_dns`

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token（支持 `X-API-Token` 请求头） |
| node_id | integer | 是 | 节点 ID |

**请求示例:**
```bash
curl -X POST http://your-domain.com/api/node/resolve_dns \
  -d "token=your_api_token" \
  -d "node_id=42"
```

**成功响应 (200):**
```json
{
    "status": "success",
    "message": "DNS updated"
}
```

**错误响应:**

| HTTP 状态码 | 场景 |
|-------------|------|
| 401 | Token 无效 |
| 404 | 节点 ID 不存在 |

```json
// DNS 更新失败
{
    "status": "error",
    "message": "DNS update failed"
}
```

**行为说明:**
- 根据 `ss_node` 中存储的 IP 地址自动创建/更新 DNS 记录
- 有 IPv4 → 创建/更新 A 记录：`node{id}.{rootDomain} → IPv4`
- 有 IPv6 → 创建/更新 AAAA 记录：`node{id}.{rootDomain} → IPv6`
- DNS TTL 设置为 120 秒
- 不启用 Cloudflare Proxy（`proxied: false`）
- 需要 `.env` 中配置 `CLOUDFLARE_EMAIL`、`CLOUDFLARE_API_KEY`、`CLOUDFLARE_ZONE_ID`

---

### Step 3: 拉取 Xray 配置

获取经过变量注入的完整 Xray JSON 配置，可直接用于启动 Xray-core。

**接口地址:** `GET /api/node/config`

**请求参数:**

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| token | string | 是 | API Token（支持 `X-API-Token` 请求头） |
| node_id | integer | 是 | 节点 ID |

**请求示例:**
```bash
curl -G http://your-domain.com/api/node/config \
  -d "token=your_api_token" \
  -d "node_id=42"
```

**成功响应 (200):**
```json
{
    "log": { ... },
    "inbounds": [ ... ],
    "outbounds": [ ... ],
    "routing": { ... },
    "ssrpanel": {
        "nodeId": 42,
        ...
    }
}
```

**错误响应:**

| HTTP 状态码 | 场景 |
|-------------|------|
| 401 | Token 无效 |
| 404 | 节点不存在 |
| 403 | 节点处于离线状态 (`status=0`) |
| 500 | 协议模板文件不存在 |

```json
// 403 - 节点离线
{
    "status": "error",
    "message": "Node is offline"
}

// 500 - 模板缺失
{
    "status": "error",
    "message": "Template not found"
}
```

#### 配置模板系统

模板文件位于 `resources/templates/xray/{v2_name}.json`，系统使用类型安全的变量注入引擎替换占位符：

| 占位符 | 替换值 | 类型 | 说明 |
|--------|--------|------|------|
| `__wsPath__` | `/ws{nodeId}` | string | WebSocket 路径 |
| `__wsPort__` | `10010` | int | WebSocket 端口（保持整数类型） |
| `__v2Fallback__` | `127.0.0.1` | string | 回落地址 |
| `__nodeDomain__` | `node{id}.{rootDomain}` | string | 节点子域名（克隆节点使用 `is_clone` 指向的主节点 ID） |
| `__HYSTERIA_URL__` | `https://www.bing.com` | string | Hysteria2 伪装 URL |
| `__v2ServiceName__` | `grpc{nodeId}` | string | gRPC 服务名 |
| `__inboundTags__` | `["proxy-vision", "proxy-hy2", "proxy-ws", "proxy-grpc"]` | array | 入站标签数组 |
| `__vlessVisionTag__` / `__v2Flow__` | `proxy-vision` / `xtls-rprx-vision` | string→string map | VLESS Vision 流控映射 |
| `__dbHost__` 等 | 数据库连接信息 | string | SSRPanel 数据库直连配置 |

> **类型保留（Case 05）：** 注入引擎会检测占位符是否完全匹配字符串。若替换值为非字符串类型（如 int、array），直接返回原始类型而非字符串化。这确保 Xray 配置中的端口号等字段保持为 JSON number 类型。

#### 解锁注入

若节点设置了 `node_unlock` 字段，系统会自动在配置中追加解锁 outbound 规则：

```
node_unlock 格式: "unlockNetflix=1,unlockChatGPT=1"
```

解析后自动添加：

```json
{
    "outbounds": [
        { "protocol": "freedom", "tag": "outbound-Netflix", ... }
    ],
    "routing": {
        "rules": [
            { "type": "field", "outboundTag": "outbound-Netflix", "domain": ["geosite:netflix"] }
        ]
    }
}
```

---

### Step 4: 上报状态与流量

节点周期性调用此接口上报原始流量计数器和运行状态。面板负责计算增量流量、计费、健康判定和流量限额检查。

**接口地址:** `POST /api/node/status`

**请求参数:**

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| token | string | 是 | - | API Token（支持 `X-API-Token` 请求头） |
| node_id | integer | 是 | - | 节点 ID |
| raw_rx | float | 否 | 0 | 网卡原始接收字节总数（NIC lifetime counter） |
| raw_tx | float | 否 | 0 | 网卡原始发送字节总数（NIC lifetime counter） |
| server_uptime | integer | 否 | 保持原值 | 服务器运行时间（秒） |

> **重要：** `raw_rx` / `raw_tx` 是网卡计数器的**累计值**，不是增量值。面板内部会计算增量。

**请求示例:**
```bash
curl -X POST http://your-domain.com/api/node/status \
  -d "token=your_api_token" \
  -d "node_id=42" \
  -d "raw_rx=10737418240" \
  -d "raw_tx=5368709120" \
  -d "server_uptime=86400"
```

**成功响应 (200):**
```json
{
    "status": "success",
    "node_status": 1
}
```

**错误响应:**

| HTTP 状态码 | 场景 |
|-------------|------|
| 401 | Token 无效 |
| 404 | 节点不存在 |

```json
// 401
{
    "status": "error",
    "message": "Unauthorized: invalid or missing token"
}
```

**响应字段说明:**

| 字段名 | 类型 | 说明 |
|--------|------|------|
| status | string | 请求状态 |
| node_status | integer | 节点当前状态：0=维护中（可能因 120G 融断），1=正常 |

#### 计费模式（billing_mode）

| 模式 | 计算公式 | 适用场景 |
|------|----------|----------|
| `tx` | `rawTotal = raw_tx` | 仅计费上行流量（默认） |
| `rxtx` | `rawTotal = (raw_rx + raw_tx) / 2` | 双向流量取均值 |

#### 三态增量计算

面板使用三态逻辑计算本次上报的流量增量：

```
lastRaw = 节点上一次的 last_raw_total

if lastRaw == 0:
    ┌─ Scene A: 首次上报（新节点/重装后）
    └─ incremental = 0（建立基线，不计费）

elif rawTotal < lastRaw:
    ┌─ Scene B: 网卡重启（计数器归零）
    └─ incremental = rawTotal（从零开始的新流量）

else:
    ┌─ Scene C: 正常累加
    └─ incremental = rawTotal - lastRaw
```

> **基线保护：** Scene A 确保新节点或重装节点首次上报时不会将网卡生命周期计数器误计为用户流量。

#### 健康引擎

系统根据月度流量消耗速率自动判定节点健康状态：

```
avgUsed     = traffic_used / passedDays
avgRemaining = (traffic_limit - traffic_used) / remainingDays

health = (avgUsed > avgRemaining) ? 0 : 1
```

- `health = 0`：当前日均消耗 > 剩余日均可用，流量将提前耗尽
- `health = 1`：流量消耗正常

> 使用 `max(passedDays, 1)` 和 `max(remainingDays, 1)` 防止除零错误。

#### 120G 融断机制

当节点剩余流量低于 120GB 时，系统自动将节点设为维护状态：

```
if (traffic_limit - traffic_used) < 120 * 1024^3:
    node.status = 0  // 触发融断
```

融断后 `config` 接口将返回 403（Node is offline），节点将无法拉取配置。

#### 月度流量重置

系统在每个计费周期的 `reset_day` 自动将 `traffic_used` 归零。重置条件：

```
满足以下任一条件即重置:
1. 今天 == reset_day 且 上次更新日期 != 今天
2. 上次更新日 < reset_day 且 今天 >= reset_day
3. 上次更新月份 != 当前月份 且 今天 >= reset_day
```

---

## 认证机制

所有 5 个接口均需 Token 认证。Token 的传递方式有两种：

### 方式一：请求参数
```
POST /api/node/status?token=your_api_token
```

### 方式二：HTTP Header
```
POST /api/node/status
X-API-Token: your_api_token
```

> `status`、`resolve_dns`、`config` 三个接口同时支持两种方式。`apply_id` 和 `register` 仅支持请求参数方式。

**Token 配置：** 在 `.env` 文件中设置 `API_TOKEN=your_secret_token`。

**认证失败统一响应 (401):**
```json
{
    "status": "error",
    "message": "Unauthorized: invalid or missing token"
}
```

---

## 错误码汇总

| HTTP 状态码 | 含义 | 触发接口 |
|-------------|------|----------|
| 200 | 成功 | 全部 |
| 401 | Token 无效或缺失 | 全部 |
| 403 | 节点处于离线状态 | config |
| 404 | 节点不存在 | register, resolve_dns, config, status |
| 500 | 模板文件缺失 | config |

---

## 典型调用序列

以下是一个完整的节点生命周期调用示例：

```bash
# 1. 申请 ID
NODE_ID=$(curl -s -X POST http://panel.example.com/api/node/apply_id \
  -d "token=secret" -d "node_ip=203.0.113.10" | jq -r '.node_id')
echo "Got node_id: $NODE_ID"

# 2. 注册（触发裂变）
curl -s -X POST http://panel.example.com/api/node/register \
  -d "token=secret" \
  -d "node_id=$NODE_ID" \
  -d "v2_name=vision-hy2-ws-grpc" \
  -d "billing_mode=tx" \
  -d "cpu=EPYC 4C" -d "memory=8" -d "disk=80" \
  -d "node_country_code=us" \
  -d "node_traffic_limit=2000"

# 3. 解析 DNS
curl -s -X POST http://panel.example.com/api/node/resolve_dns \
  -d "token=secret" -d "node_id=$NODE_ID"

# 4. 拉取配置并启动 Xray
curl -s -G http://panel.example.com/api/node/config \
  -d "token=secret" -d "node_id=$NODE_ID" > /etc/xray/config.json
xray run -c /etc/xray/config.json &

# 5. 循环上报（每 60 秒）
while true; do
  RX=$(cat /sys/class/net/eth0/statistics/rx_bytes)
  TX=$(cat /sys/class/net/eth0/statistics/tx_bytes)
  UPTIME=$(cat /proc/uptime | cut -d. -f1)
  curl -s -X POST http://panel.example.com/api/node/status \
    -d "token=secret" -d "node_id=$NODE_ID" \
    -d "raw_rx=$RX" -d "raw_tx=$TX" -d "server_uptime=$UPTIME"
  sleep 60
done
```

---

## 环境依赖

### .env 必需配置

| 变量名 | 说明 |
|--------|------|
| `API_TOKEN` | API 认证 Token |
| `CLOUDFLARE_EMAIL` | Cloudflare 账户邮箱（resolve_dns 使用） |
| `CLOUDFLARE_API_KEY` | Cloudflare API Key |
| `CLOUDFLARE_ZONE_ID` | Cloudflare Zone ID |
| `DB_HOST` / `DB_USERNAME` / `DB_PASSWORD` / `DB_DATABASE` | 数据库连接（注入到 Xray 配置模板） |

### 系统配置（`sys_config` 表）

| 键名 | 说明 |
|------|------|
| `node_root_domain` | 节点子域名根域，如 `3ups.top` |

### 文件依赖

| 路径 | 说明 |
|------|------|
| `resources/templates/xray/{v2_name}.json` | Xray 配置模板文件 |

---

*文档生成时间: 2026-04-27 | 基于 commits: faf6276b, 0a8a93d5*
