# 节点后端 API v2(/api/v2/backend)接口文档

## 概述

`/api/v2/backend` 是面向 **xray-plugin-api 节点插件** 的机器对机器(节点 ↔ 面板)API:
节点插件是纯 HTTP 客户端、无数据库访问,通过本 API 拉取本节点有效用户全量快照、
上报流量增量与状态心跳。**契约唯一事实源: xray-plugin-api `docs/openapi.yaml`**,
本文为面板侧实现说明。

与节点生命周期 API(`/api/node/*`,见 [node-api-v2.md](node-api-v2.md))互不相关:
后者负责 apply_id → register → resolve_dns → config → status 的节点建站流程,
使用全局 `API_TOKEN`;本 API 使用**每节点独立 Bearer token**,不共用全局 key,
也不与旧 node 组共用 env('API_TOKEN')(一个全局 key 泄露等于全部节点沦陷)。

**Base URL:** `http://your-domain.com/api/v2/backend`

**信封契约:**
- 成功: `{"data": {...}}`(HTTP 200)
- 失败: `{"error": {"code": "...", "message": "..."}}`

| 端点 | 方法 | 鉴权 | 说明 |
|---|---|---|---|
| `/api/v2/backend/users` | GET | Bearer 每节点 token | 本节点有效用户全量快照 |
| `/api/v2/backend/traffic` | POST | Bearer 每节点 token | 批量上报流量增量(原始字节) |
| `/api/v2/backend/status` | POST | Bearer 每节点 token | 节点状态心跳 |
| `/api/v2/backend/healthz` | GET | **无鉴权** | 健康检查(给 nginx/监控探活) |

本 API 不挂 session/CSRF 中间件。

---

## 鉴权(BackendToken 中间件)

路由中间件别名 `backend.token`(`app/Http/Middleware/BackendToken.php`),仅接受:

```
Authorization: Bearer <ss_node.api_token>
```

- token 取自 `ss_node.api_token`(全表唯一索引精确匹配,43 字符 base64url);
  为空或长度 > 64 直接 401,不进库查询
- `ss_node.status == 0` 的禁用节点返回 403 `NODE_DISABLED`(与 `/api/node/config` 返回 403 口径一致)
- 可选 IP 白名单 `ss_node.api_ip_allowlist`(逗号分隔 IP/CIDR,如 `1.2.3.4,10.0.0.0/8`):
  配置了就强制校验,不匹配返回 401(纵深防御,非唯一鉴权因素)
- 节点流量耗尽**不算禁用** —— `GET /users` 返回 200 + 空列表(插件据此删光用户)

### 错误信封

| HTTP | code | message | 场景 |
|---|---|---|---|
| 401 | `INVALID_TOKEN` | token is invalid or revoked | token 缺失/错误/超长,或 IP 不在白名单 |
| 403 | `NODE_DISABLED` | node is disabled | 节点 status=0 |
| 400 | `BAD_REQUEST` | (见各端点) | 参数校验失败,整批拒收 |
| 500 | `INTERNAL` | traffic write failed / status write failed | 事务内写入异常(连接断/死锁等),返回契约信封而非 Laravel 默认异常页 |

### ss_node 新增列(migration `2026_08_18_140000_add_api_token_to_ss_node_table`)

| 字段 | 类型 | 说明 |
|---|---|---|
| `api_token` | varchar(64), nullable, unique | 每节点独立 Bearer token,32 字节随机数的 base64url(43 字符),列位于 `heartbeat_at` 之后 |
| `api_ip_allowlist` | varchar(255), nullable | 可选 IP/CIDR 白名单,逗号分隔;NULL=不启用 |

migration 幂等:up()/down() 均先做列存在性检查,重复执行(含手工 ALTER 过的环境)不报错。

### 生成 token(artisan)

```bash
php artisan node:generate-api-tokens            # 给所有 api_token 为空的节点生成(已有 token 的节点保持不变)
php artisan node:generate-api-tokens --show     # 仅列出各节点 token, 不生成
php artisan node:generate-api-tokens --node=3   # 查看/生成单节点
```

生成后 token **无需手工填写**:`POST /api/node/config` 会自动把 `panelApi` 段（baseURL + token）注入下发的 config.json，详见下节。

---

## panelApi 段自动下发（`/api/node/config` 双段兼容）

`resources/templates/xray/*.json` 全部模板内置 `panelApi` 段（占位符 `__panelBaseURL__` / `__panelToken__`），由 `NodeApiController::config` 按节点 token 状态动态处理：

| 场景 | 下发行为 |
|---|---|
| `ss_node.api_token` 已签发 | 保留 `panelApi` 段并填真实值：`api.baseURL` = `rtrim(NODE_API_BASE_URL ?: app.url, '/') . '/api/v2/backend'`（专用域名优先，回退 `app.url`）、`api.token` = 节点 api_token，`user.inboundTags` / `flows` 与 `ssrpanel` 段同管线按协议组填充；**同时保留 `ssrpanel` 段** —— 新旧插件都可用（xray 核心忽略未知顶级键），DB 凭据保留 = 回滚能力 |
| `api_token` 未签发 | 删除 `panelApi` 段，输出与旧版完全一致（零回归） |
| env `NODE_API_DROP_MYSQL_CREDENTIALS=true` | 额外删除 `ssrpanel` 段，节点不再持有 MySQL 凭据（Phase 4 收尾开关） |

下发日志中 panelApi token 打码（仅露前 4 后 4 位），与插件侧口径一致。

---

## GET /users — 本节点有效用户全量快照

**语义(关键):** 返回的是**全量快照**,名单里没有的用户就是该删的用户。

处理顺序:
1. 刷新节点心跳(`ss_node.heartbeat_at`)
2. 节点流量耗尽(`traffic_limit != 0 && traffic_used >= traffic_limit`)→ 200 + 空列表

过滤清单(与旧 srp 插件 SQL 口径一致):
1. `user.enable = 1`
2. 有余量:`user.transfer_enable > u + d`
3. 等级:`user.level >= ss_node.level`
4. 分组:`ss_node.node_group != 0` 时要求 `user.node_group` 相等(为 0 不限)
5. 无 `expire_in` 过滤、无 `is_admin` 旁路 —— 到期由面板定时任务收敛到 `enable`

**成功响应 (200):**
```json
{
    "data": {
        "users": [
            {"id": 1, "email": "user@example.com", "uuid": "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"}
        ]
    }
}
```

| 字段 | 说明 |
|---|---|
| `id` | 用户 ID |
| `email` | `user.username` |
| `uuid` | `ss_node.node_uuid` 非空优先(独立凭据节点,与订阅层 SubscribeController 一致),否则 `user.vmess_id` |

---

## POST /traffic — 批量上报流量增量(原始字节)

**请求体:**
```json
{
    "data": {
        "items": [
            {"userId": 1, "uplinkBytes": 1024, "downlinkBytes": 2048}
        ]
    }
}
```

### 校验(违反即 400 `BAD_REQUEST`,整批拒收)

- `data.items` 必须为对象数组
- `userId` 严格 `is_int` 且 > 0;`uplinkBytes`/`downlinkBytes` 严格 `is_int` 且 >= 0
  —— JSON 中超出 PHP int 范围的数字会解码成 float(如 `1e30`),`(int)` 强转会产生
  垃圾正数直接污染计费,因此**拒收字符串/超大数字**
- 单条字节上限 `1 << 60`(≈1.15EB):超过必为恶意/损坏数据,防 bigint 溢出
- 同一 `userId` 多条会**合并求和**后再入账(防重复 id 导致 CASE 覆盖);
  合并求和或全批总和溢出 int 同样 400(`item sum overflow` / `batch total overflow`)

### 写路径(倍率只在这里乘)

| 表 | 写入 |
|---|---|
| `user` | `u`/`d` 原子 CASE WHEN 单语句累加**乘率值**(节点 `traffic_rate`),并以 `LEAST(..., 9223372036854775807)` bigint 饱和封顶(而非报错);同时刷新 `t` |
| `user_traffic_log` | 存**原始字节** + 当次倍率 + 乘率后人类可读值,一条多 VALUES 批量 INSERT(每 500 行一批) |
| `ss_node` | `traffic_used` 原子累加**原始字节**;事务成功后 best-effort 刷新 `traffic_left` 与心跳(失败仅记日志,不影响已入账流量,下一轮重算) |

> 倍率始终以小数字面量参与运算(bigint + decimal 不触发 MySQL 1690 溢出错),
> 配合 LEAST 封顶实现**饱和而非报错** —— 报错会让插件 at-least-once 无限重试,
> 卡死整节点上报。

**成功响应 (200):**
```json
{"data": {"accepted": 12}}
```
`accepted` = 按 `userId` 去重合并后的用户数;空批次返回 `{"accepted": 0}`。

---

## POST /status — 节点状态心跳

**请求体:**
```json
{
    "data": {
        "uptimeSeconds": 86400,
        "loadAverage": {"oneMinute": 0.1, "fiveMinutes": 0.2, "fifteenMinutes": 0.3},
        "onlineUsers": 5
    }
}
```

三个字段 `uptimeSeconds` / `loadAverage` / `onlineUsers` 均必填,缺失 400 `BAD_REQUEST`。

**写路径:**
1. 写 `ss_node_info`:`uptime`、`load`(`"x.xx x.xx x.xx"` 格式)、`log_time`
2. `onlineUsers > 0` 时写 `ss_node_online_log`
3. 刷新 `ss_node` 每日派生字段(与 `/api/node/status` 的 NodeApiController 同口径,
   保持管理页展示新鲜):`server_uptime`、`traffic_left`、`traffic_used_daily`、
   `traffic_left_daily`、`node_health`(按 `reset_day` 日历折算已过/剩余天数)及 `heartbeat_at`

**成功响应 (200):**
```json
{"data": {"accepted": true}}
```

---

## GET /healthz — 健康检查(无鉴权)

给 nginx / 监控探活,不做任何鉴权与库查询:

```json
{"data": {"status": "ok"}}
```

---

## 与 SPanel 实现的差异(两套遗留 schema)

- `uuid` 取值:`ss_node.node_uuid` 非空优先,否则 `user.vmess_id`(与订阅层一致,
  漏掉会导致独立凭据节点认证失败)
- 用户过滤无 `expire_in` 列(到期由面板定时任务收敛到 `enable`),无 `is_admin` 旁路
  —— 与旧 srp 插件口径一致
- 等级列 `user.level`,心跳列 `ss_node.heartbeat_at`

---

*文档更新时间: 2026-08-19 | 基于 commit: 93f2fa81*
