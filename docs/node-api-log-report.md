# NodeApiController 日志审计报告

> 生成日期: 2026-05-01
> 审计对象: `app/Http/Controllers/Api/NodeApiController.php`
> 分支: `feature/node-api-v2`

---

## 1. 现状概览

NodeApiController 包含 **6 个公共端点** 和若干私有方法，但仅有 **3 处** `Log::` 调用：

| # | 行号 | 级别 | 消息 | 所在方法 |
|---|------|------|------|----------|
| 1 | L46 | `Log::warning` | `node_domain_pool JSON parse error: ...` | `parseDomainPool()` |
| 2 | L502 | `Log::error` | `resolve_dns: CF POST failed for node {id}, aborting swap...` | `swapDnsRecord()` |
| 3 | L509 | `Log::warning` | `resolve_dns: CF DELETE failed for record {id} after successful POST...` | `swapDnsRecord()` |

**结论**：6 个端点中仅 `resolveDns`（DNS 协调）有部分日志覆盖，其余 5 个端点 **零日志**。

---

## 2. 与项目日志规范的对比

### 2.1 项目日志规范（基线）

通过审计项目内其他控制器、组件、中间件，归纳出以下规范：

#### Log:: Facade 使用规范

| 特征 | 项目规范 | NodeApiController 现状 |
|------|----------|----------------------|
| **日志密度** | 支付回调每步操作都有 `Log::info()` | 大部分端点无任何日志 |
| **请求入口记录** | 记录完整请求参数 + 来源 IP（如 `var_export($request->all(), true)`） | 无 |
| **异常捕获** | 在 `catch` 块中用 `Log::error()` 记录 `$e->getMessage()` | 无 try-catch |
| **中文标签** | 使用 `【服务名】` 标签标识来源（如 `【有赞云】`、`【支付宝国际】`） | 使用英文消息 |
| **成功路径** | 关键操作成功后用 `Log::info()` 记录 | 无成功日志 |
| **IP 追踪** | 多处使用 `getClientIp()` 记录来源 | 无 IP 记录 |

#### 数据库日志规范

项目使用多个数据库日志表记录业务操作：

| 日志表 | 类型 | NodeApiController 是否使用 |
|--------|------|--------------------------|
| `email_log` (type=1/2/3) | 通知发送记录 | **否** |
| `user_balance_log` | 余额变动 | N/A |
| `user_traffic_modify_log` | 流量修改 | N/A |
| `user_login_log` | 登录记录 | N/A |
| `coupon_log` | 优惠券使用 | N/A |
| `referral_log` | 推荐返利 | N/A |

> **注**: DNS 操作无对应的数据库日志表。

---

## 3. 逐端点审计

### 3.1 `applyId()` — 节点申请（L91-L108）

```where```: `NodeApiController::applyId`
```input```: token, node_ip, node_ipv6
```output```: node_id (JSON)
```do```: 创建新节点记录

**当前日志**: 无

**缺失**:
- [ ] 请求入口日志（谁在申请、IP 是什么）
- [ ] 创建成功日志（新节点 ID）
- [ ] Token 验证失败日志（潜在的安全审计需求）

**风险**: 无法追踪谁在何时创建了节点，也无法发现未授权的申请尝试。

---

### 3.2 `register()` — 节点注册/裂变（L117-L293）

```where```: `NodeApiController::register`
```input```: node_id, token, node_memory, v2_name, node_ip, node_ipv6, node_cost, node_level, root_domain 等
```output```: main_node_id, clone_node_ids, node_ids, root_domain, v2_name, ws_path 等
```do```: 身份协调、域名分配、协议裂变（克隆节点）、路径分配

**当前日志**: 无

**缺失**:
- [ ] 请求入口日志（关键输入参数：node_id, memory, v2_name, protocols）
- [ ] 协议分配决策日志（选择了哪些协议、为什么）
- [ ] 域名亲和性决策日志（选择了哪个域名、各域名当前记录数）
- [ ] 克隆创建/复用/删除日志
- [ ] Level 分配日志（mainLevel 计算结果）
- [ ] 最终响应摘要日志

**风险**: 这是最复杂的端点，涉及域名分配、协议裂变、克隆管理。无日志意味着：
- 无法回溯域名分配决策
- 无法诊断克隆节点为何被创建或删除
- 无法追踪协议分配变更历史

---

### 3.3 `resolveDns()` — DNS 协调（L347-L396）

```where```: `NodeApiController::resolveDns`
```input```: node_id, token
```output```: actions 数组, status
```do```: 对比 ss_node 目标状态与 dns_records 当前状态，通过 CF API 做增量同步

**当前日志**: 2 条（均在 `swapDnsRecord` 私有方法中）

**已有**:
- L502: CF POST 失败时 `Log::error`
- L509: CF DELETE 失败时 `Log::warning`

**缺失**:
- [ ] 请求入口日志（node_id）
- [ ] 解析 server 字段结果日志
- [ ] Scene A（无变更）命中日志
- [ ] Scene B（IP 更新）成功日志
- [ ] Scene C（域名交换）成功日志
- [ ] 每条 DNS 记录操作的完整 diff 日志（旧 → 新）
- [ ] CF API 调用前后的详细数据

**风险**: DNS 协调是节点上线的关键步骤。仅有失败日志而无成功/跳过日志，无法确认：
- 节点 DNS 是否已正确设置
- IP 变更是否已正确传播
- 域名迁移是否成功完成

---

### 3.4 `config()` — 节点配置下发（L542-L579）

```where```: `NodeApiController::config`
```input```: node_id, token
```output```: 完整 Xray JSON 配置
```do```: 加载模板、注入变量、应用解锁规则

**当前日志**: 无

**缺失**:
- [ ] 请求入口日志
- [ ] 模板选择日志（使用哪个 v2_name 模板）
- [ ] 变量注入结果摘要
- [ ] 解锁规则应用日志

**风险**: 配置下发直接影响节点运行。如果模板缺失或变量注入错误，无法通过日志回溯。

---

### 3.5 `status()` — 节点心跳与计费（L639-L696）

```where```: `NodeApiController::status`
```input```: node_id, token, raw_rx, raw_tx, server_uptime
```output```: node_status
```do```: 流量计费、周期重置、健康检测、120G 熔断

**当前日志**: 无

**缺失**:
- [ ] 请求入口日志（node_id, raw_rx, raw_tx）
- [ ] 流量增量计算日志（incremental 值）
- [ ] 周期重置触发日志
- [ ] 健康状态变更日志
- [ ] 120G 熔断触发日志（**关键**：节点被下线时应有告警级日志）
- [ ] 计费模式选择日志（rxtx vs tx）

**风险**: 120G 熔断会将节点 status 设为 0（下线），但 **没有任何日志记录这一关键决策**。运营人员无法通过日志发现节点因何被下线。

---

### 3.6 `validateToken()` — Token 校验（L17-L24）

**当前日志**: 无

**缺失**:
- [ ] Token 验证失败日志（IP、请求路径）

**风险**: 与项目中其他 Token 验证（如支付回调）相比，缺少对未授权访问的记录。

---

## 4. 与项目内其他 API 控制器的对比

### 支付回调控制器（标杆）

以 `YzyController` 和 `AlipayController` 为例，它们在每个关键节点都有日志：

```
请求入口 → Log::info(完整请求参数 + IP)
签名验证 → Log::info(本地签名 vs 远程签名)
验证失败 → Log::info(失败原因 + IP)
数据解析 → Log::info(解析结果)
业务处理 → Log::info(处理结果)
异常捕获 → Log::error(异常信息)
```

### NodeApiController 对比

```
请求入口 → 无
Token 验证 → 无
业务处理 → 无（DNS swap 除外）
异常捕获 → 无（无 try-catch）
关键决策 → 无
成功结果 → 无
```

**差距**: 支付回调有 10-20+ 条日志/端点，NodeApiController 平均 0.5 条/端点。

---

## 5. 语言一致性

| 控制器 | 日志语言 | 说明 |
|--------|----------|------|
| AdminController | 中文 | `'编辑用户信息异常：'` |
| AuthController | 中文 | `'重置密码邮件发送失败: '` |
| YzyController | 中文 | `'【有赞云】回调接口[GET]：'` |
| AlipayController | 中文 | `'【支付宝国际】回调交易支付'` |
| isForbidden 中间件 | 中文 | `'识别到机器人访问('` |
| **NodeApiController** | **英文** | `'node_domain_pool JSON parse error: '` |

**建议**: NodeApiController 的日志应统一使用中文，与项目其他部分保持一致。

---

## 6. 综合评分

| 维度 | 评分 | 说明 |
|------|------|------|
| **覆盖广度** | 1/5 | 仅 3 条日志，覆盖 1/6 端点的部分场景 |
| **成功路径** | 0/5 | 无任何成功操作日志 |
| **异常处理** | 1/5 | 无 try-catch，仅 DNS swap 有失败日志 |
| **可追溯性** | 1/5 | 缺少请求参数、IP、决策依据的记录 |
| **规范一致性** | 1/5 | 英文消息，无 `【标签】` 前缀，无数据库日志 |
| **综合** | **0.8/5** | 亟需改进 |

---

## 7. 改进建议

### 优先级 P0 — 立即修复

1. **`status()` 熔断日志**: 当 120G 熔断触发时，添加 `Log::warning` + Telegram 通知
2. **`register()` 关键决策日志**: 记录域名分配、协议裂变、克隆创建/删除
3. **`resolveDns()` 操作日志**: 记录每次 DNS 变更的完整 diff（旧→新）

### 优先级 P1 — 短期改进

4. **统一入口日志**: 每个端点在方法开头添加 `Log::info` 记录关键参数
5. **Token 验证失败日志**: 记录 IP 和请求路径
6. **语言统一**: 将英文日志改为中文，与项目其他部分一致
7. **添加 try-catch**: `register()` 和 `config()` 方法应有异常捕获

### 优先级 P2 — 中期优化

8. **DNS 操作日志表**: 考虑创建 `dns_operation_log` 数据库表，记录每次 DNS 变更的详细 diff
9. **结构化日志**: 使用 `Log::info('message', ['context' => $data])` 格式，便于后续日志分析
10. **标签前缀**: 添加 `【NodeAPI】` 标签，便于 `grep` 过滤

---

## 附录 A: 建议添加的日志语句清单

### applyId()
```php
// 方法开头
Log::info("【NodeAPI】节点申请：IP={$nodeIp}, IPv6={$nodeIpv6}, 来源=" . getClientIp());

// Token 验证失败
Log::warning("【NodeAPI】节点申请 Token 验证失败，来源=" . getClientIp());

// 创建成功
Log::info("【NodeAPI】节点创建成功：ID={$node->id}");
```

### register()
```php
// 方法开头
Log::info("【NodeAPI】节点注册：ID={$nodeId}, Memory={$nodeMemory}GB, V2Name={$v2Name}");

// 域名分配决策
Log::info("【NodeAPI】域名亲和性分配：选择 {$rootDomain}（节点 {$nodeId}）");

// 协议裂变
Log::info("【NodeAPI】协议裂变：节点 {$nodeId} → " . implode(',', $protocols) . ", 共 " . count($slots) . " 个槽位");

// 克隆操作
Log::info("【NodeAPI】克隆复用：节点 {$clone->id} 分配 {$slot['protocol']}({$slot['ip_type']})");
Log::info("【NodeAPI】克隆创建：新节点 {$clone->id} 分配 {$slot['protocol']}({$slot['ip_type']})");

// 删除多余克隆
Log::warning("【NodeAPI】删除多余克隆：节点 {$excess->id}");

// Level 分配
Log::info("【NodeAPI】Level 分配：主节点 {$mainLevel}, 克隆 " . implode(',', $cloneIds));
```

### resolveDns()
```php
// 方法开头
Log::info("【NodeAPI】DNS 协调：节点 {$nodeId}, 目标 {$targetSubdomain}.{$targetRootDomain}");

// Scene A (无变更)
Log::info("【NodeAPI】DNS 无变更：节点 {$nodeId}, {$type} 记录已是最新");

// Scene B (IP 更新)
Log::info("【NodeAPI】DNS IP 更新：节点 {$nodeId}, {$type}: {$oldRecord->ip_addr} → {$targetIp}");

// Scene C (域名交换)
Log::info("【NodeAPI】DNS 域名交换：节点 {$nodeId}, {$oldRecord->subdomain}.{$oldRecord->root_domain} → {$targetSubdomain}.{$targetRootDomain}");
```

### status()
```php
// 方法开头
Log::info("【NodeAPI】节点心跳：ID={$nodeId}, RX={$rawRx}, TX={$rawTx}");

// 流量重置
Log::info("【NodeAPI】流量周期重置：节点 {$nodeId}");

// 健康状态变更
if ($node->isDirty('node_health')) {
    Log::warning("【NodeAPI】节点健康状态变更：{$nodeId} → " . ($node->node_health ? '健康' : '不健康'));
}

// 120G 熔断（关键！）
if ($node->status == 0) {
    Log::warning("【NodeAPI】节点 120G 熔断下线：{$nodeId}, 剩余 " . round(($node->traffic_limit - $node->traffic_used) / 1073741824, 2) . "GB");
}
```

### validateToken()
```php
// Token 验证失败
Log::warning("【NodeAPI】Token 验证失败：来源=" . $request->ip() . ", 路径=" . $request->path());
```
