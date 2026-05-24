# Auto Delete Expired DNS Records QA

## 验收目标

验证长期无心跳节点的 DNS 清理功能满足以下目标：

1. 只清理过期节点关联的 DNS 记录，不直接删除 `ss_node` 节点。
2. Cloudflare 远端删除成功或明确返回 404 时，才删除本地 `dns_records`。
3. Cloudflare 删除失败、限流、鉴权失败、网络异常、配置缺失时，本地记录必须保留。
4. `--dry-run` 只预览，不调用 Cloudflare API，不写数据库。
5. 现有节点复用入口不再直接删除本地 DNS，避免产生 Cloudflare 孤儿记录。
6. 定时任务分批执行、避免 N+1、支持并发保护，并兼容 PHP 7.4。

---

## 验收范围

### 必须覆盖

- `CloudflareProvider` 结构化删除结果。
- 共享 DNS 清理逻辑。
- `AutoDeleteExpiredDns` Artisan 命令。
- `NodeApiController::applyId()` 死节点复用路径。
- `NodeApiController::register()` clone 复用与多余 clone 回收路径。
- `Helpers::parseDomainPool()` 域名池解析。
- `InitDnsRecords` 域名池解析回归。
- `Kernel` 调度注册与 `withoutOverlapping()`。
- `ss_node.heartbeat_at`、`ss_node.created_at` 索引迁移。

### 不在本次范围

- 删除 `ss_node` 节点记录。
- 自动修复缺失 `cf_record_id` 的历史记录。
- 真实 Cloudflare 生产账号压测。

---

## 测试环境要求

1. PHP 必须使用 7.4 容器环境。
2. `.env` 必须设置：

```dotenv
APP_ENV=test
```

3. 测试不得依赖真实 Cloudflare 删除生产记录。必须使用 fake/mock provider，或专用测试 zone。
4. 测试数据必须使用可识别前缀，例如：

```text
qa-expired-dns-
qa-active-dns-
qa-recycle-dns-
```

5. 测试结束后必须清理测试创建的 `ss_node`、`dns_records`、相关配置项。

---

## 测试数据矩阵

| 场景 | 节点状态 | DNS 类型 | cf_record_id | zone_id | Cloudflare 返回 | 期望 |
|------|----------|----------|--------------|---------|-----------------|------|
| 过期节点成功删除 | `heartbeat_at < cutoff` | A | 有 | 有 | 2xx success | 删除本地记录 |
| 过期节点远端不存在 | `heartbeat_at < cutoff` | AAAA | 有 | 有 | 404 | 删除本地记录 |
| 过期节点鉴权失败 | `heartbeat_at < cutoff` | A | 有 | 有 | 401/403 | 保留本地记录 |
| 过期节点限流 | `heartbeat_at < cutoff` | A | 有 | 有 | 429 | 保留本地记录 |
| 过期节点服务端失败 | `heartbeat_at < cutoff` | A | 有 | 有 | 5xx | 保留本地记录 |
| 过期节点网络错误 | `heartbeat_at < cutoff` | A | 有 | 有 | curl error | 保留本地记录 |
| 缺少 zone_id | `heartbeat_at < cutoff` | A | 有 | 无 | 不调用 API | 保留本地记录 |
| 缺少 cf_record_id | `heartbeat_at < cutoff` | A | 无 | 有 | 不调用 API | 保留本地记录并 warning |
| 未过期节点 | `heartbeat_at >= cutoff` | A | 有 | 有 | 不调用 API | 保留本地记录 |
| 从未心跳但新建未过期 | `heartbeat_at IS NULL AND created_at >= cutoff` | A | 有 | 有 | 不调用 API | 保留本地记录 |
| 从未心跳且新建已过期 | `heartbeat_at IS NULL AND created_at < cutoff` | A | 有 | 有 | 2xx success | 删除本地记录 |
| 非节点 DNS 类型 | 过期 | TXT/MX/CNAME | 有 | 有 | 不调用 API | 保留本地记录 |

---

## 功能测试用例

### QA-01 dry-run 不产生副作用

**准备**
- 创建一个超过阈值的节点。
- 创建关联 `A`、`AAAA` DNS 记录。
- 配置有效 `node_domain_pool` 和 `zone_id`。
- fake provider 记录调用次数。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns --dry-run
```

**验收**
- Cloudflare fake provider 调用次数为 0。
- `dns_records` 数量不变。
- 命令输出包含 planned/will delete 信息。
- 输出包含扫描节点数、计划处理记录数、跳过数、失败数。

### QA-02 Cloudflare 删除成功后删除本地记录

**准备**
- 过期节点关联一条 `A` 记录。
- fake provider 返回 `success=true`、`http_code=200`。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- fake provider 被调用 1 次。
- 本地 `DnsRecord` 被删除。
- 命令 success 计数加 1。
- 日志包含 `node_id`、`dns_record_id`、`fqdn`、`cf_record_id`、`zone_id`。

### QA-03 Cloudflare 404 后删除本地记录

**准备**
- 过期节点关联一条 `AAAA` 记录。
- fake provider 返回 `success=false`、`not_found=true`、`http_code=404`。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 本地 `DnsRecord` 被删除。
- 命令 not_found 或 equivalent 计数加 1。
- 不能记录为 failed。

### QA-04 403/401 不删除本地记录

**准备**
- 过期节点关联一条 `A` 记录。
- fake provider 返回 `http_code=403` 或 `401`。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 本地 `DnsRecord` 仍存在。
- 命令 failed 计数加 1。
- 日志包含 HTTP 状态码。
- 下次任务仍可重试该记录。

### QA-05 429 限流不删除本地记录

**准备**
- 创建多条过期节点 DNS 记录。
- fake provider 对第一条或中间某条返回 `rate_limited=true`、`http_code=429`。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 返回 429 的记录保留。
- 命令输出或日志明确 rate limit。
- 本轮命令按实现策略中止或退避继续，但不得删除 429 对应本地记录。
- 每次 API 调用后有 sleep 逻辑，失败路径也不能跳过。

### QA-06 5xx 或网络错误不删除本地记录

**准备**
- fake provider 返回 `http_code=500`，或模拟 curl error。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 本地记录仍存在。
- failed 计数加 1。
- 日志包含错误类型。

### QA-07 zone_id 缺失时跳过

**准备**
- `node_domain_pool` 中存在 root domain，但没有 `zone_id`。
- 过期节点有关联 `A` 记录。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 不调用 Cloudflare provider。
- 本地记录仍存在。
- skipped 计数加 1。
- 日志级别至少为 warning。

### QA-08 cf_record_id 缺失时跳过并告警

**准备**
- 过期节点有关联 `A` 记录，但 `cf_record_id` 为 `NULL` 或空字符串。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 不调用 Cloudflare delete by id。
- 本地记录仍存在。
- 日志包含 `missing_cf_record_id` 或等价信息。

### QA-09 未过期节点不处理

**准备**
- 创建 `heartbeat_at` 为当前时间的节点。
- 创建关联 `A` 记录。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 不调用 Cloudflare provider。
- 本地记录仍存在。
- 命令扫描结果不应把该节点计入过期处理数。

### QA-10 `heartbeat_at IS NULL` 分支正确

**准备**
- 节点 A：`heartbeat_at = NULL`，`created_at < cutoff`。
- 节点 B：`heartbeat_at = NULL`，`created_at >= cutoff`。
- 两个节点各有一条 `A` 记录。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- 节点 A 的记录按 Cloudflare 返回结果处理。
- 节点 B 的记录不处理。

### QA-11 只处理 A/AAAA

**准备**
- 过期节点关联 `A`、`AAAA`、`TXT`、`MX`、`CNAME` 记录。
- fake provider 对 A/AAAA 返回成功。

**执行**

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

**验收**
- `A`、`AAAA` 本地记录被删除。
- `TXT`、`MX`、`CNAME` 本地记录保留。
- provider 不接收 TXT/MX/CNAME 删除调用。

### QA-12 删除前二次心跳校验

**准备**
- 命令第一次查询时节点符合过期条件。
- 在执行删除前，将该节点 `heartbeat_at` 更新为当前时间。

**执行**
- 通过测试桩或分步测试触发二次校验。

**验收**
- 不调用 Cloudflare provider。
- 本地记录保留。
- skipped 计数加 1。

---

## 回收路径回归测试

### QA-13 applyId 复用死节点不再直接删除本地 DNS

**准备**
- 创建一个超过现有复用阈值的死节点。
- 给该节点创建一条带 `cf_record_id` 的 DNS 记录。
- fake provider 返回失败，例如 500。

**执行**
- 调用 `NodeApiController::applyId()` 对应接口或控制器方法。

**验收**
- 该节点被复用的业务流程按预期继续或返回可解释结果。
- DNS 本地记录没有被直接删除。
- 日志记录 Cloudflare 删除失败和保留本地记录。
- 代码中该路径不再出现直接 `DnsRecord::where("node_id", $node->id)->delete()`。

### QA-14 register 复用死节点作为 clone 时安全清理

**准备**
- 创建主节点注册场景，触发 clone 创建。
- 准备一个可复用死节点，并绑定 DNS 记录。
- fake provider 返回成功。

**执行**
- 调用 `register()` 对应接口或控制器方法。

**验收**
- 复用死节点前调用共享清理逻辑。
- Cloudflare 成功后本地旧 DNS 被删除。
- 新 clone 注册流程正常完成。

### QA-15 register 回收多余 clone 时安全清理

**准备**
- 主节点已有多个 clone。
- 新注册数据减少 slot 数量，触发多余 clone 回收。
- 多余 clone 绑定 DNS 记录。
- fake provider 返回 403。

**执行**
- 调用 `register()` 对应接口或控制器方法。

**验收**
- 多余 clone 状态按业务逻辑回收。
- DNS 本地记录保留。
- 日志记录 403 和保留原因。
- 不产生直接本地 delete。

---

## 代码审查验收点

### CloudflareProvider

- [ ] 保留原 `deleteRecord($cfRecordId, $zoneId = null)`。
- [ ] 新增结构化删除方法。
- [ ] 结构化结果包含 `success`、`not_found`、`rate_limited`、`http_code`、`error`。
- [ ] 404 不被混同为普通失败。
- [ ] 401/403/429/5xx/curl error 均可被调用方识别。

### DnsRecordCleanupService

- [ ] dry-run 不调用 API，不写 DB。
- [ ] success 或 404 才删除本地记录。
- [ ] 失败时保留本地记录。
- [ ] zone_id 缺失时保留本地记录。
- [ ] cf_record_id 缺失时保留本地记录。
- [ ] 每次 API 调用后都 sleep。
- [ ] 日志上下文字段完整。

### AutoDeleteExpiredDns

- [ ] 支持 `--dry-run`。
- [ ] 如实现 `--days`，默认值为 30 或与业务确认后的值。
- [ ] 如实现 `--limit`，默认值为 100。
- [ ] 使用 `with('dnsRecords')` 或等价预加载。
- [ ] 使用 `chunk()` 或 `cursor()`。
- [ ] 查询过期逻辑覆盖 `heartbeat_at < cutoff`。
- [ ] 查询过期逻辑覆盖 `heartbeat_at IS NULL AND created_at < cutoff`。
- [ ] 删除前二次校验节点仍过期。
- [ ] 只处理 `A`、`AAAA`。
- [ ] 命令输出包含完整统计。

### NodeApiController

- [ ] `parseDomainPool()` 已迁移为 `Helpers::parseDomainPool()` 或委托调用。
- [ ] `applyId()` 不再直接删除本地 DNS。
- [ ] `register()` 复用死节点路径不再直接删除本地 DNS。
- [ ] `register()` 回收多余 clone 路径不再直接删除本地 DNS。
- [ ] 请求内清理有明确超时或边界，不能无限阻塞。

### InitDnsRecords

- [ ] 使用 `Helpers::parseDomainPool()`。
- [ ] 现有 A/AAAA 同步行为未回退。
- [ ] `--execute` 行为未被破坏。

### Kernel

- [ ] 命令已注册。
- [ ] 调度已添加。
- [ ] 调度包含 `withoutOverlapping()`。
- [ ] 未使用当前 Laravel 版本不支持的 API。

### Migration

- [ ] 新增索引 migration。
- [ ] migration 幂等检查索引是否存在。
- [ ] 覆盖 `ss_node.heartbeat_at`。
- [ ] 覆盖 `ss_node.created_at`。
- [ ] `down()` 可接受，不能破坏已有业务索引。

### PHP 7.4

- [ ] 未使用命名参数。
- [ ] 未使用 `match`。
- [ ] 未使用 null safe operator。
- [ ] 未使用 union type。
- [ ] 未使用 `mixed` 类型。
- [ ] 未使用构造器属性提升。
- [ ] 未使用 `readonly`、`enum`、attribute。

---

## 命令验收

### 列出命令

```bash
podman exec php7-npanel php /var/www/NPanel/artisan list | grep -i delete
```

验收：
- 能看到 `autoDeleteExpiredDns` 或实现中约定的命令名。

### dry-run

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns --dry-run
```

验收：
- 命令退出码为 0。
- 输出包含 dry-run 标记。
- 数据库无变更。

### 正式执行

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns
```

验收：
- 命令退出码为 0。
- 输出完整统计。
- 成功、404、失败、跳过场景符合测试矩阵。

---

## 数据库验收

### 索引检查

```sql
SHOW INDEX FROM ss_node;
```

验收：
- 存在覆盖 `heartbeat_at` 的索引。
- 存在覆盖 `created_at` 的索引。

### 本地记录保留规则

失败场景执行后检查：

```sql
SELECT id, node_id, root_domain, subdomain, record_type, cf_record_id
FROM dns_records
WHERE subdomain LIKE 'qa-expired-dns-%';
```

验收：
- 远端失败、缺配置、缺 `cf_record_id` 的记录仍存在。
- 远端成功或 404 的记录不存在。

---

## 日志验收

每条失败或跳过日志必须可定位具体记录。

必须包含：
- `node_id`
- `dns_record_id`
- `fqdn`
- `record_type`
- `root_domain`
- `cf_record_id`
- `zone_id`
- `http_code` 或错误类型
- `source`

验收：
- 403、429、5xx、curl error 都能从日志区分。
- zone_id 缺失和 cf_record_id 缺失有明确 warning。
- dry-run 日志或输出明确不会执行 API 和 DB 写入。

---

## 发布前检查

- [ ] 所有自动测试通过。
- [ ] dry-run 在测试环境输出符合预期。
- [ ] 没有真实生产 Cloudflare 记录被测试误删。
- [ ] `app/` 新增或批量修改 PHP 文件后已执行权限修复。
- [ ] 如新增测试文件，`tests/` 权限已修复。
- [ ] `composer dump-autoload` 已执行。
- [ ] 定时任务时间确认不会与其他重任务冲突。
- [ ] 阈值 30 天或 32 天已与现有复用逻辑确认一致。
- [ ] 生产上线前先执行一次 `--dry-run` 并保存输出。

权限修复命令：

```bash
podman exec php7-npanel chown -R www-data:www-data /var/www/NPanel/app/
podman exec php7-npanel chmod -R 755 /var/www/NPanel/app/
podman exec php7-npanel php /var/www/NPanel/composer.phar dump-autoload
```

如新增测试文件：

```bash
podman exec php7-npanel chown -R www-data:www-data /var/www/NPanel/tests/
podman exec php7-npanel chmod -R 755 /var/www/NPanel/tests/
```

---

## 验收结论模板

```text
验收日期:
验收环境:
代码版本:
执行人:

自动测试:
- 通过:
- 失败:

手工 dry-run:
- 通过/失败:
- 输出摘要:

正式测试执行:
- 通过/失败:
- 成功删除:
- 404 清理:
- 跳过:
- 失败保留:

遗留问题:

结论:
- 通过 / 不通过
```
