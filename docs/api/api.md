# NPanel API 接口文档

## 概述

NPanel API 提供节点管理、端口检测、支付处理等功能。所有 API 接口都需要进行 token 验证以确保安全性。

**Base URL:** `http://your-domain.com/api`

**认证方式:** 
- 大部分接口使用 `API_TOKEN`（在 `.env` 文件中配置）
- 部分接口使用 MD5 签名验证

---

## 1. 端口检测工具

### 1.1 端口连通性检测

**接口地址:** `GET /api/ping`

**接口描述:** 检测指定主机和端口的连通性

**请求参数:**

| 参数名 | 类型 | 必填 | 默认值 | 描述 |
|--------|------|------|--------|------|
| token | string | 是 | - | API_TOKEN，需在 .env 中配置 |
| host | string | 是 | - | 检测地址，可以是域名、IPv4、IPv6 |
| port | integer | 否 | 22 | 检测端口 |
| transport | string | 否 | tcp | 检测协议，支持 tcp、udp |
| timeout | float | 否 | 0.5 | 检测超时时间（秒），建议不超过3秒 |

**示例请求:**
```
GET /api/ping?token=your_api_token&host=www.baidu.com&port=80&transport=tcp&timeout=1.0
```

**响应格式:**
```json
{
    "status": 1,
    "message": "port open"
}
```

**响应字段说明:**

| 字段名 | 类型 | 描述 |
|--------|------|------|
| status | integer | 状态码：1=端口开放，0=端口关闭 |
| message | string | 状态描述：port open/port close |

**错误响应:**
```json
{
    "status": 0,
    "message": "token invalid"
}
```

---

## 2. 节点管理 API

### 2.1 节点订阅信息上报

**接口地址:** `POST /api/ssn_sub/{id}`

**请求格式:** `application/x-www-form-urlencoded`

**接口描述:** 节点上报订阅状态、流量、在线人数等信息

**路径参数:**

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| id | integer | 是 | 节点ID |

**请求参数:**

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| token | string | 是 | API_TOKEN |
| status | integer | 否 | 节点状态：0=离线，1=在线 |
| health | integer | 否 | 健康状态：0=不可订阅，1=可订阅 |
| online | integer | 否 | 在线用户数 |
| traffic | integer | 否 | 总流量（字节） |
| traffic_used | integer | 否 | 已使用流量（字节） |
| traffic_used_daily | integer | 否 | 日已使用流量（字节） |
| traffic_left | integer | 否 | 剩余流量（字节） |
| traffic_left_daily | integer | 否 | 日剩余流量（字节） |
| daily | integer | 否 | 日负载量 |

**示例请求:**
```
POST /api/ssn_sub/123
Content-Type: application/x-www-form-urlencoded

token=your_api_token&status=1&health=1&online=50&traffic=1073741824&traffic_used=536870912
```

**成功响应格式:**
```json
{
    "ret": 1,
    "msg": "node update success 0193",
    "status": "success",
    "message": "Node updated successfully"
}
```

**错误处理:** Token 验证失败或节点不存在时返回错误 JSON。

---

### 2.2 获取可用节点

**接口地址:** `GET /api/node/new`

**接口描述:** 获取一个可用的节点ID用于上传配置。查询符合条件的节点并返回其ID和v2_host信息。

**请求参数:**

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| token | string | 是 | API_TOKEN，需在 .env 中配置 |

**查询条件:**
- 节点ID > 9
- 节点心跳时间超过7天（604800秒）
- 节点状态为 0（维护中）

**示例请求:**
```
GET /api/node/new?token=your_api_token
```

**成功响应格式:**
```json
{
    "status": "success",
    "message": "Node found",
    "data": {
        "node_id": 100,
        "v2_host": "n100.3ups.top"
    }
}
```

**响应字段说明:**

| 字段名 | 类型 | 描述 |
|--------|------|------|
| status | string | 请求状态：success/error |
| message | string | 状态描述 |
| data | object | 节点信息对象 |
| data.node_id | integer | 可用节点的ID |
| data.v2_host | string | 节点的v2_host配置值 |

**注意事项:**
- 获取节点后会自动更新其心跳时间
- 按照ID升序返回第一个符合条件的节点
- **如果没有可用节点，系统会自动创建一个新的维护状态节点。**

---

### 2.3 节点配置信息查询

**接口地址:** `GET /api/node_config`

**接口描述:** 根据节点ID快速获取节点的配置信息，包括v2_host等关键配置参数

**请求参数:**

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| token | string | 是 | API_TOKEN，需在 .env 中配置 |
| node_id | integer | 是 | 节点ID |

**示例请求:**
```
GET /api/node_config?token=your_api_token&node_id=123
```

**成功响应格式:**
```json
{
    "status": "success",
    "data": {
        "node_id": 123,
        "name": "US-Node-1",
        "v2_host": "example.com",
        "server": "server.example.com",
        "v2_port": 443,
        "v2_method": "aes-128-gcm",
        "v2_net": "ws",
        "v2_type": "none",
        "v2_path": "/path",
        "v2_tls": 1,
        "v2_sni": "sni.example.com",
        "type": 2
    }
}
```

**响应字段说明:**

| 字段名 | 类型 | 描述 |
|--------|------|------|
| status | string | 请求状态：success/error |
| data | object | 节点配置信息对象 |
| data.node_id | integer | 节点ID |
| data.name | string | 节点名称 |
| data.v2_host | string | **V2ray伪装的域名** |
| data.server | string | 服务器域名地址 |
| data.v2_port | integer | V2ray端口 |
| data.v2_method | string | V2ray加密方式 |
| data.v2_net | string | V2ray传输协议（tcp/ws/grpc等） |
| data.v2_type | string | V2ray伪装类型 |
| data.v2_path | string | V2ray WS/H2路径 |
| data.v2_tls | integer | TLS类型：0=无，1=tls，2=xtls |
| data.v2_sni | string | SNI服务器名称指示 |
| data.type | integer | 节点类型：1=SS，2=Vmess，3=Vless，4=Trojan，5=Hysteria2 |

---

### 2.4 节点V2信息上报

**接口地址:** `POST /api/ssn_v2/{id}`

**请求格式:** `application/x-www-form-urlencoded`

**接口描述:** 节点上报详细的V2协议配置信息

**路径参数:**

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| id | integer | 是 | 节点ID |

**请求参数:**

#### 基础信息
| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| token | string | 是 | API_TOKEN |
| node_name | string | 否 | 节点名称 |
| node_country_code | string | 否 | 国家代码（自动转为小写） |
| node_info | string | 否 | 节点信息 |
| node_from | string | 否 | 节点来源 |
| node_expire | string | 否 | 到期时间 |
| node_cost | integer | 否 | 节点成本 |
| node_level | integer | 否 | 节点等级 |
| node_group | string | 否 | 节点分组 |
| node_traffic_limit | integer | 否 | 流量限制（GB，自动转为字节） |
| node_sort | integer | 否 | 节点排序 |
| node_traffic_rate | float | 否 | 流量倍率 |
| node_bandwidth | integer | 否 | 带宽 |
| node_ip | string | 否 | IPv4地址 |
| node_ipv6 | string | 否 | IPv6地址 |
| node_unlock | string | 否 | 解锁信息 |
| server_uptime | string | 否 | 服务器运行时间 |
| server_total_traffic | string | 否 | 服务器总流量 |

#### V2协议参数
| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| v2 | string | 否 | 协议类型：ss/vmess/vless/trojan/hysteria2 |
| v2_add | string | 否 | 服务器地址 |
| v2_port | integer | 否 | 端口 |
| v2_aid | integer | 否 | VMess额外ID |
| v2_scy | string | 否 | 加密方式 |
| v2_net | string | 否 | 传输协议 |
| v2_type | string | 否 | 传输类型 |
| v2_host | string | 否 | 伪装域名 |
| v2_path | string | 否 | 伪装路径 |
| v2_tls | string | 否 | TLS类型：空/tls/xtls |
| v2_sni | string | 否 | SNI |
| v2_alpn | string | 否 | ALPN |
| v2_ecpt | string | 否 | VLESS加密 |
| v2_flow | string | 否 | XTLS流控 |
| v2_uuid | string | 否 | 节点UUID |
| v2_cdn | string | 否 | CDN地址 |
| v2_cdn_ip | string | 否 | CDN IP |
| v2_mode | string | 否 | GRPC模式 |
| v2_servicename | string | 否 | GRPC服务名 |
| v2_fp | string | 否 | 指纹 (fingerprint) |
| v2_id | integer | 否 | 克隆源节点ID |

**示例请求:**
```
POST /api/ssn_v2/123
Content-Type: application/x-www-form-urlencoded

token=your_api_token&node_name=TestNode&node_country_code=US&v2=vmess&v2_add=example.com&v2_port=443&v2_scy=auto&v2_net=ws&v2_tls=tls
```

**响应:** 无内容返回（HTTP 200）

**错误处理:** Token 验证失败时直接终止执行

---

## 3. 支付相关 API

### 3.1 克隆支付回调

**接口地址:** `POST /api/clonepay`

**请求格式:** `application/x-www-form-urlencoded`

**接口描述:** 处理第三方支付平台的回调通知

**前置条件:** 
- 系统需开启 clonepay 功能
- 请求IP必须在安全IP列表中

**请求参数:**

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| from | string | 是 | 支付来源标识 |
| order | string | 是 | 订单号 |
| money | float | 是 | 支付金额（元） |
| email | string | 是 | 用户邮箱 |
| time | integer | 是 | 时间戳 |
| salt | string | 是 | 随机盐值 |
| sign | string | 是 | 签名 |

**签名算法:**
```
signStr = salt + '&' + YYYYMMDD + '&' + order + '&' + money + '&' + paytoken
sign = MD5(signStr)
```

**示例请求:**
```
POST /api/clonepay
Content-Type: application/x-www-form-urlencoded

from=alipay&order=20241211001&money=10.00&email=user@example.com&time=1702310400&salt=abc123&sign=md5hash
```

**响应:** 
- 成功：无内容返回（HTTP 200）
- 失败：返回错误信息，如 `&error=订单已被记录`

---

## 4. 工具类 API

### 4.1 简单API工具集

**接口地址:** `POST /api/simple_api_tools`

**请求格式:** `application/x-www-form-urlencoded`

**接口描述:** 提供多种实用工具功能，包括获取IP、时间、节点ID等

**认证方式:** MD5(API_TOKEN + salt) == token

**请求参数:**

| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| token | string | 是 | MD5(API_TOKEN + salt) |
| salt | string | 是 | 随机盐值 |
| ip | string | 否 | 任意值表示获取客户端IP |
| time | string | 否 | 任意值表示获取当前时间戳 |
| due_time | string | 否 | 任意值表示获取有效期时间戳 |
| new_node_id | string | 否 | 任意值表示获取新节点ID (ID > 99) |

**示例请求:**
```
POST /api/simple_api_tools
Content-Type: application/x-www-form-urlencoded

token=5d41402abc4b2a76b9719d911017c592&salt=hello&ip=1&time=1&due_time=1
```

**响应格式:**
```json
{
    "ip": "192.168.1.100",
    "time": 1702310400,
    "due_time": 1702310700,
    "new_node_id": 123
}
```

---

## 5. 错误码说明

### 通用错误码

| 错误码 | 描述 | 解决方案 |
|--------|------|----------|
| token invalid | Token无效 | 检查.env文件中的API_TOKEN配置 |
| token-empty | Token为空 | 请提供有效的token参数 |
| token-invalid | Token验证失败 | 检查token生成算法是否正确 |

### 业务错误码

| 错误码 | 描述 | 解决方案 |
|--------|------|----------|
| node-empty | 无可用节点 | 检查节点状态和配置 |
| no-param | 无有效参数 | 请提供至少一个功能参数 |
| 订单已被记录 | 订单号重复 | 检查订单号是否已处理 |

---

## 6. 开发注意事项

1. **Token安全:** 请妥善保管API_TOKEN，避免泄露
2. **IP限制:** clonepay接口有IP白名单限制
3. **流量单位:** 流量相关参数统一使用字节作为单位
4. **时间格式:** 时间戳统一使用Unix时间戳

---

*文档最后更新时间: 2026-04-23*
