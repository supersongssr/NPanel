# GET /api/nodes — 获取所有节点信息

## 概述

查询面板数据库中全部节点（`ss_node` 表）的完整信息，按 `id` 升序返回。

该接口独立于 Node API v2 的 `node.api.token` 中间件组，自行验证 `API_TOKEN`，适用于外部系统（监控、运维面板等）批量拉取节点状态。

---

## 请求

| 项目 | 值 |
|------|-----|
| **URL** | `GET /api/nodes` |
| **Content-Type** | 无要求 |

### 认证

以下两种方式任选其一：

| 方式 | 说明 |
|------|------|
| **Bearer Header**（推荐） | `Authorization: Bearer <API_TOKEN>` |
| **Query Parameter** | `?token=<API_TOKEN>` |

> `API_TOKEN` 定义在 `.env` 文件中。Bearer Header 优先；若 Header 不存在则回退到查询参数。

---

## 响应

### 成功 (200)

```json
{
  "status": "success",
  "count": 3,
  "data": [
    {
      "id": 1,
      "name": "JP-Tokyo",
      "v2_name": "xhttp-hy2",
      "server": "n1.example.com",
      "ip": "203.0.113.10",
      "ipv6": "",
      "status": 1,
      "level": 2,
      "node_group": 2,
      "country_code": "jp",
      "node_country": "Japan",
      "node_city": "Tokyo",
      "traffic_rate": 1.0,
      "traffic_limit": 1099511627776,
      "traffic_used": 536870912000,
      "bandwidth": 100,
      "node_rxtx": "rxtx",
      "node_cpu": 2,
      "node_memory": 4.0,
      "node_disk": 40.0,
      "node_cost": 3.5,
      "node_health": 1,
      "heartbeat_at": "2026-05-31 10:00:00",
      "is_clone": 0,
      "node_ids": "1,2,3",
      "sort": 0,
      "reset_day": 1,
      "info": "测试节点",
      "node_unlock": "netflix=1&chatgpt=1",
      "v2_net": "xhttp",
      "v2_tls": 1,
      "v2_port": 443,
      "v2_host": "n1.example.com",
      "v2_path": "srp-xhttp",
      "v2_sni": "n1.example.com",
      "v2_flow": null,
      "v2_fp": "random",
      "v2_method": "none",
      "v2_alpn": "h2,http/1.1",
      "v2_mode": "auto",
      "created_at": "2026-01-01 00:00:00",
      "updated_at": "2026-05-31 10:00:00"
    }
  ]
}
```

### 主要字段说明

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | int | 节点 ID，主键 |
| `name` | string | 节点名称（格式：`{CountryCode}-{City}`） |
| `status` | int | 节点状态：`0` = 离线，`1` = 在线 |
| `ip` | string | IPv4 地址（IPv6-only 节点为空） |
| `ipv6` | string | IPv6 地址（IPv4-only 节点为空） |
| `server` | string | 节点域名（如 `n1.example.com`） |
| `v2_name` | string | 协议组名（如 `xhttp-hy2`、`vision-hy2`） |
| `v2_net` | string | 实际传输协议（如 `xhttp`、`tcp`、`hysteria2`、`ws`、`grpc`） |
| `level` | int | 节点等级（影响用户可见性） |
| `node_group` | int | 节点分组 |
| `country_code` | string | ISO 国家代码（小写，如 `jp`） |
| `node_country` | string | 国家名称 |
| `node_city` | string | 城市名称 |
| `traffic_rate` | float | 流量倍率 |
| `traffic_limit` | int | 月流量上限（字节） |
| `traffic_used` | float | 已用流量（字节） |
| `bandwidth` | int | 标称带宽（Mbps） |
| `node_rxtx` | string | 计费模式：`tx`（仅上行）/ `rxtx`（双向均值） |
| `node_cpu` | int | CPU 核心数 |
| `node_memory` | float | 内存（GB） |
| `node_disk` | float | 磁盘（GB） |
| `node_cost` | float | 月成本 |
| `node_health` | int | 健康状态：`0` = 拥塞，`1` = 健康 |
| `heartbeat_at` | datetime | 最后心跳时间 |
| `is_clone` | int | 克隆源节点 ID（`0` = 主节点） |
| `node_ids` | string | 主节点 + 所有克隆节点 ID（逗号分隔） |
| `node_unlock` | string | 解锁服务（query string 格式，如 `netflix=1&chatgpt=1`） |
| `reset_day` | int | 每月流量重置日 |

### 失败 (401)

未提供 Token 或 Token 不匹配时返回：

```json
{
  "status": "error",
  "message": "Unauthorized: invalid or missing token"
}
```

---

## 调用示例

### cURL

```bash
# Bearer Token（推荐）
curl -s -H "Authorization: Bearer your_api_token_here" \
  https://panel.example.com/api/nodes

# Query Parameter
curl -s "https://panel.example.com/api/nodes?token=your_api_token_here"
```

### Python

```python
import requests

API_URL = "https://panel.example.com/api/nodes"
TOKEN = "your_api_token_here"

resp = requests.get(API_URL, headers={"Authorization": f"Bearer {TOKEN}"})
data = resp.json()

if data["status"] == "success":
    print(f"共 {data['count']} 个节点")
    for node in data["data"]:
        print(f"  [{node['id']}] {node['name']} — {node['ip']} — {'在线' if node['status'] == 1 else '离线'}")
else:
    print(f"请求失败: {data.get('message')}")
```

### jq 过滤示例

```bash
# 仅列出在线节点的 ID、名称、IP
curl -s -H "Authorization: Bearer $TOKEN" https://panel.example.com/api/nodes \
  | jq '.data[] | select(.status == 1) | {id, name, ip}'

# 统计各国家节点数
curl -s -H "Authorization: Bearer $TOKEN" https://panel.example.com/api/nodes \
  | jq '[.data[].country_code] | group_by(.) | map({country: .[0], count: length})'

# 查找流量使用超过 80% 的节点
curl -s -H "Authorization: Bearer $TOKEN" https://panel.example.com/api/nodes \
  | jq '.data[] | select(.traffic_limit > 0 and (.traffic_used / .traffic_limit) > 0.8) | {id, name, usage_percent: ((.traffic_used / .traffic_limit * 100) | round)}'
```

---

## 注意事项

1. **无分页**：该接口返回所有节点，不提供分页参数。节点数量较大时请注意响应体大小。
2. **只读**：纯查询接口，不会修改任何数据。
3. **Token 安全**：避免将 Token 硬编码在客户端代码中，建议通过环境变量或密钥管理服务注入。
4. **敏感字段**：响应包含节点 IP、域名等敏感信息，请确保传输使用 HTTPS，且仅授权方可访问。
