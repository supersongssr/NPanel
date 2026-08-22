# 用户查询 API v2(/api/v2/user)使用说明

## 概述

`/api/v2/user` 是面向**运维 / 监控 / 客服系统**的机器对机器(系统 ↔ 面板)只读查询 API,
用于按用户 **id 或 email** 快速查询流量信息:总流量、已用流量、剩余流量、上行 `u`、下行 `d` 等。

与节点相关 API 互不相关:

| API | 用途 | 鉴权 |
|---|---|---|
| `/api/v2/user/*`(本文) | 按用户查流量 | 独立 Bearer token `USER_API_TOKEN` |
| `/api/v2/backend/*`(见 [backend-node-api-v2.md](backend-node-api-v2.md)) | 节点插件拉用户/上报流量 | 每节点 `ss_node.api_token` |
| `/api/node/*`(见 [node-api-v2.md](node-api-v2.md)) | 节点建站生命周期 | 全局 `API_TOKEN` |

**只读**: 本 API 不写任何数据。本 API 不挂 session/CSRF 中间件。

**Base URL:** `https://your-domain.com/api/v2/user`

**信封契约**(与 `/api/v2/backend` 一致):

- 成功: `{"data": {...}}`(HTTP 200)
- 失败: `{"error": {"code": "...", "message": "..."}}`

| 端点 | 方法 | 说明 |
|---|---|---|
| `/api/v2/user/traffic` | GET | 单用户查询:`?id=` 或 `?email=`(二选一) |
| `/api/v2/user/traffic` | POST | 批量查询:`{"data":{"ids":[...]}}` 或 `{"data":{"emails":[...]}}` |

---

## 鉴权(UserApiToken 中间件)

路由中间件别名 `user.api.token`(`app/Http/Middleware/UserApiToken.php`),仅接受:

```
Authorization: Bearer <USER_API_TOKEN>
```

- token 取自 `.env` 的 `USER_API_TOKEN`,建议 32 字节随机数的 base64url(43 字符):
  ```bash
  php -r 'echo rtrim(strtr(base64_encode(random_bytes(32)),"+/","-_"),"="),PHP_EOL;'
  ```
- **未配置 `USER_API_TOKEN` 时接口整体禁用(fail-closed,返回 503)**,避免忘配 key 变裸奔
- 比较使用 `hash_equals`(常数时间),防时序侧信道
- 可选 IP 白名单 `.env` 的 `USER_API_IP_ALLOWLIST`(逗号分隔 IP/CIDR,如 `1.2.3.4,10.0.0.0/8`):
  配置了就强制校验,不匹配返回 401(纵深防御)

### 错误信封

| HTTP | code | message | 场景 |
|---|---|---|---|
| 400 | `BAD_REQUEST` | (见各端点) | 参数校验失败 |
| 401 | `INVALID_TOKEN` | token is invalid or revoked | token 缺失/错误/超长,或 IP 不在白名单 |
| 404 | `USER_NOT_FOUND` | user not found | GET 单查未命中用户 |
| 503 | `API_DISABLED` | USER_API_TOKEN is not configured | 面板未配置 token,接口整体禁用 |

---

## GET /api/v2/user/traffic — 单用户查询

**查询参数**(`id` 与 `email` 二选一,同时给出返回 400):

| 参数 | 类型 | 约束 | 说明 |
|---|---|---|---|
| `id` | int | 正整数,≤11 位 | 用户 id |
| `email` | string | ≤128 字符 | 用户邮箱(即 `user.username` 列,**精确匹配**) |

### 示例

```bash
TOKEN="your-user-api-token"
BASE="https://your-domain.com/api/v2/user"

# 按 id 查询
curl -H "Authorization: Bearer $TOKEN" "$BASE/traffic?id=1"

# 按 email 查询
curl -H "Authorization: Bearer $TOKEN" "$BASE/traffic?email=user%40example.com"
```

### 成功响应(HTTP 200)

```json
{
  "data": {
    "user": {
      "id": 1,
      "email": "user@example.com",
      "u": 2097152,
      "d": 4194304,
      "used": 6291456,
      "transfer_enable": 1099511627776,
      "remaining": 1099505336320,
      "usage_percent": 0,
      "u_human": "2MB",
      "d_human": "4MB",
      "used_human": "6MB",
      "transfer_enable_human": "1TB",
      "remaining_human": "1023.99GB",
      "last_traffic_time": 1787074702,
      "enable": 1,
      "status": 1,
      "level": 1,
      "expire_time": "2099-01-01",
      "ban_time": 0,
      "traffic_reset_day": 0
    }
  }
}
```

### 字段说明(流量均为字节,`*_human` 为人类可读单位)

| 字段 | 说明 |
|---|---|
| `id` / `email` | 用户 id / 用户名(邮箱) |
| `u` | **已上传流量**(字节,乘节点倍率后的计费值) |
| `d` | **已下载流量**(字节,乘节点倍率后的计费值) |
| `used` | **已用流量** = `u + d` |
| `transfer_enable` | **总流量**(可用额度,字节) |
| `remaining` | **剩余流量** = `max(0, transfer_enable - used)`(超额钳 0) |
| `usage_percent` | 用量百分比(`used / transfer_enable * 100`,保留 2 位;超额可 > 100) |
| `u_human` / `d_human` / `used_human` / `transfer_enable_human` / `remaining_human` | 对应字段的 `flowAutoShow` 可读值(如 `1.5GB`) |
| `last_traffic_time` | 最后一次流量上报时间(Unix 时间戳,即 `user.t`) |
| `enable` | 代理状态:0-禁、1-启 |
| `status` | 账号状态:-1-禁用、0-未激活、1-正常 |
| `level` / `expire_time` / `ban_time` / `traffic_reset_day` | 等级 / 过期日期 / 封禁到期(Unix)/ 流量重置日 |

> ⚠️ 响应不包含密码、代理密码、UUID 等凭据字段(最小披露),如需凭据请走订阅接口。

### 失败响应

- `?id=999999999` → HTTP 404: `{"error":{"code":"USER_NOT_FOUND","message":"user not found"}}`
- 不带参数 / `id`+`email` 同给 / `id=abc` / `id=0` → HTTP 400 `BAD_REQUEST`

---

## POST /api/v2/user/traffic — 批量查询

**请求体**(`Content-Type: application/json`;`ids` 与 `emails` 二选一,同给返回 400;单次最多 **100** 个,自动去重):

```json
{"data": {"ids": [1, 2, 3]}}
```

```json
{"data": {"emails": ["a@example.com", "b@example.com"]}}
```

### 示例

```bash
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"data":{"ids":[1,2]}}' "$BASE/traffic"
```

### 成功响应(HTTP 200)

`users` 数组按 id 升序;**查不到的用户不出现(不报错)**,`count` 为实际命中数:

```json
{
  "data": {
    "users": [
      {"id": 1, "email": "...", "u": 0, "d": 0, "used": 0, "...": "同单查字段"},
      {"id": 2, "email": "...", "u": 0, "d": 0, "used": 0, "...": "同单查字段"}
    ],
    "count": 2
  }
}
```

### 失败响应(HTTP 400 `BAD_REQUEST`)

- 缺 `data` / `data` 非对象
- `ids` 与 `emails` 同给
- `ids`/`emails` 为空数组、含非法项(id 非正整数、email 非字符串/超 128 字符)
- 超过 100 个

---

## 配置清单(.env)

```ini
# v2 用户查询 API Bearer token(未配置则接口整体禁用 fail-closed)
USER_API_TOKEN=<43字符base64url>
# 可选 IP 白名单(逗号分隔 IP/CIDR,留空不启用)
USER_API_IP_ALLOWLIST=
```

## 实现位置

| 文件 | 说明 |
|---|---|
| `app/Http/Controllers/Api/V2/User/UserController.php` | 控制器(traffic / trafficBatch) |
| `app/Http/Middleware/UserApiToken.php` | 鉴权中间件 |
| `routes/api.php` | 路由组 `v2/user`(中间件 `user.api.token`) |
| `tests/test_user_api_v2_traffic.php` | QA 测试(36 项断言) |

## 测试

```bash
podman exec -e APP_ENV=test php7-npanel php tests/test_user_api_v2_traffic.php
```
