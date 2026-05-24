# Auto Delete Expired DNS Records

## 概述

定时清理长期无心跳节点关联的 DNS 解析记录，用于释放 Cloudflare 记录额度，并避免本地 `dns_records` 与 Cloudflare 远端状态持续漂移。

本计划只负责 DNS 记录清理，不直接删除 `ss_node` 节点数据。节点是否禁用、复用、删除由现有节点注册/回收流程负责。

---

## where

1. **Cloudflare 删除结果增强**
   - `app/Components/DNS/CloudflareProvider.php`
   - 增加可区分 HTTP 状态码的删除方法，调用方必须能识别 404、403、429、网络错误等场景。

2. **共享 DNS 清理逻辑**
   - 建议新增 `app/Services/DnsRecordCleanupService.php`，或在现有组件中建立等价的共享服务。
   - 供以下入口统一调用：
     - 新定时命令 `AutoDeleteExpiredDns`
     - `NodeApiController::applyId()`
     - `NodeApiController::register()` 中复用死节点、回收多余 clone 的路径

3. **域名池解析复用**
   - 将 `NodeApiController::parseDomainPool()` 提取到 `App\Components\Helpers::parseDomainPool($sysConf)`。
   - `NodeApiController`、`InitDnsRecords`、新清理命令统一调用该方法。

4. **新建 Artisan 命令**
   - `app/Console/Commands/AutoDeleteExpiredDns.php`

5. **注册到调度器**
   - `app/Console/Kernel.php`
   - 必须使用 `withoutOverlapping()`，避免上一次任务未结束时重复执行。

6. **数据库索引迁移**
   - 新增 migration，而不是只写手工 SQL。
   - 至少覆盖 `ss_node.heartbeat_at` 和 `ss_node.created_at`，保证两个过期判断分支都能受益。

涉及模型/组件：
- `App\Http\Models\SsNode`
- `App\Http\Models\DnsRecord`
- `App\Components\DNS\CloudflareProvider`
- `App\Components\Helpers`

---

## why

1. **释放 Cloudflare 记录额度**
   Cloudflare 单域名记录数量有限，长期无心跳节点占用记录会导致新节点无法注册或 DNS 同步失败。

2. **保证一致性**
   本地 `dns_records.cf_record_id` 是后续重试清理远端记录的关键凭据。禁止在远端删除失败时直接删除本地记录。

3. **堵住现有泄漏入口**
   当前节点复用流程里存在直接删除本地 DNS 记录的路径。如果只新增定时任务，不改这些入口，Cloudflare 孤儿记录仍会产生。

4. **可观测、可回滚**
   `--dry-run` 必须能完整预览影响范围，失败场景必须保留本地记录和日志，便于下次任务自动重试或人工排查。

---

## input

定时命令输入：
- `--dry-run`：只预览，不调用 Cloudflare API，不写数据库。
- 可选 `--days=30`：过期天数，默认 30。若不实现该参数，必须在命令常量中明确默认值。
- 可选 `--limit=100`：每批节点数量，默认 100。

共享清理服务输入：
- `DnsRecord $record`
- `array $domainPool`
- `bool $dryRun`
- 可选上下文：`node_id`、调用来源、日志标签。

---

## output

命令输出必须包含：
- 扫描节点数
- 计划处理 DNS 记录数
- 成功删除数
- 404 视为已清理数
- 跳过数
- 失败数
- dry-run 预览列表或摘要

日志必须包含：
- `node_id`
- `dns_record_id`
- `fqdn`
- `record_type`
- `root_domain`
- `cf_record_id`
- `zone_id`
- Cloudflare HTTP 状态码或错误类型
- 调用来源

---

## do

### 1. 增强 Cloudflare 删除结果

当前 `CloudflareProvider::deleteRecord()` 只返回 `true/false`，无法区分 404、403、429、网络错误。必须新增结构化删除方法，例如：

```php
public function deleteRecordWithStatus($cfRecordId, $zoneId = null)
{
    // return [
    //     'success' => true|false,
    //     'not_found' => true|false,
    //     'rate_limited' => true|false,
    //     'http_code' => 200|404|429|null,
    //     'error' => '...',
    //     'response' => [...]
    // ];
}
```

兼容要求：
- 保留原 `deleteRecord()`，避免破坏现有调用方。
- 新清理逻辑必须使用结构化方法。
- 404 表示远端记录已不存在，可删除本地记录。
- 403、401、429、5xx、curl error 都不能删除本地记录。

### 2. 实现共享清理逻辑

共享逻辑必须执行以下流程：

```text
1. 组装 fqdn = subdomain.root_domain
2. dry-run：只记录 will_delete，不调用 API，不写 DB
3. 校验 root_domain 是否在 domainPool
4. 校验 zone_id 是否存在
5. 校验 cf_record_id 是否存在
6. 调用 CloudflareProvider::deleteRecordWithStatus()
7. 若 success=true 或 not_found=true：删除本地 DnsRecord
8. 若失败：保留本地 DnsRecord，记录 warning/error，等待下次重试
9. 每次 Cloudflare API 调用后都执行短暂 sleep，失败也必须 sleep
10. 若返回 429：记录 rate limit，并允许命令中止本轮或退避后继续
```

`cf_record_id` 为空时不能静默跳过。必须至少记录 warning，并在后续版本考虑通过 `root_domain + subdomain + record_type` 查询远端记录后删除。

### 3. 修复现有直接删除本地 DNS 的路径

以下路径不得继续直接 `DnsRecord::where(...)->delete()`：
- `NodeApiController::applyId()` 复用死节点时
- `NodeApiController::register()` 复用死节点作为 clone 时
- `NodeApiController::register()` 回收多余 clone 时

必须改为：
- 有 Cloudflare 配置时，调用共享清理逻辑。
- 远端删除成功或 404 后，才删除本地记录。
- 远端失败时保留本地记录，并在复用节点前记录风险日志。

如果某条路径处于请求生命周期内，不能无限阻塞请求。可设置短超时或只处理该节点少量记录，但不能直接丢弃本地清理凭据。

### 4. 新增定时命令

命令核心逻辑：

```php
$cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));

SsNode::with('dnsRecords')
    ->where(function ($q) use ($cutoff) {
        $q->where('heartbeat_at', '<', $cutoff)
          ->orWhere(function ($q2) use ($cutoff) {
              $q2->whereNull('heartbeat_at')
                 ->where('created_at', '<', $cutoff);
          });
    })
    ->orderBy('id')
    ->chunk(100, function ($nodes) {
        // 删除前重新检查 heartbeat_at，避免节点刚恢复心跳后被误清理
        // 只处理 A/AAAA 类型记录
        // 调用共享清理服务
    });
```

删除前必须二次校验节点仍然过期：
- `heartbeat_at < cutoff`
- 或 `heartbeat_at IS NULL AND created_at < cutoff`

只处理节点生成的 `A`、`AAAA` 记录，避免误删未来可能存在的 TXT、MX、CNAME 等记录。

### 5. 调度配置

建议每日低峰期执行：

```php
$schedule->command('autoDeleteExpiredDns')
    ->dailyAt('04:10')
    ->withoutOverlapping();
```

如果项目 Laravel 版本支持，可补充 `onOneServer()`；否则不要强行使用不兼容 API。

### 6. 索引迁移

新增 migration，要求幂等检查索引是否存在。

推荐索引：
- `ss_node(heartbeat_at)`
- `ss_node(created_at)`

原因：
- `heartbeat_at < cutoff` 使用 `heartbeat_at` 索引。
- `heartbeat_at IS NULL AND created_at < cutoff` 的分支依赖 `created_at` 过滤。

不要只在文档中要求手动执行：

```sql
ALTER TABLE ss_node ADD INDEX idx_heartbeat_at (heartbeat_at);
```

---

## must

1. **PHP 7.4 兼容**
   严禁使用 PHP 8+ 语法特性，例如命名参数、`match`、null safe operator、union type、`mixed` 类型、构造器属性提升、`readonly`、`enum`。

2. **API 成功或 404 才能删本地**
   任何无法确认远端已删除的场景，都必须保留本地 `DnsRecord`。

3. **必须修复现有回收入口**
   只新增定时任务是不完整的。现有直接删除本地 DNS 的回收路径必须同步改造。

4. **必须支持 dry-run**
   dry-run 下不得调用 Cloudflare API，不得写数据库。

5. **必须分批处理**
   使用 `chunk()` 或 `cursor()`，禁止一次性 `get()` 所有过期节点。

6. **必须防 N+1**
   查询节点时使用 `with('dnsRecords')`，或等价的批量加载方式。

7. **必须处理并发**
   调度使用 `withoutOverlapping()`；删除前二次校验节点过期状态。

8. **必须处理 rate limit**
   每次 Cloudflare API 调用后都 sleep。遇到 429 时不得删除本地记录，并应中止本轮或退避重试。

9. **Zone ID 缺失不得删本地**
   找不到 `zone_id` 时跳过并记录 warning。

10. **只处理节点 DNS 记录**
    默认只处理 `record_type in ('A', 'AAAA')`。除非另有明确业务确认，不处理 TXT、MX、CNAME 等记录。

---

## 测试与验证

### 自动测试建议

新增或扩展测试脚本，覆盖：
- dry-run 不调用 Cloudflare，不删除数据库。
- Cloudflare 删除成功后删除本地记录。
- Cloudflare 404 后删除本地记录。
- Cloudflare 403/429/5xx/curl error 后保留本地记录。
- zone_id 缺失时保留本地记录。
- `cf_record_id` 缺失时保留本地记录并记录 warning。
- 现有 `applyId()`、`register()` 回收路径不再直接删除本地 DNS。
- 只处理 A/AAAA，不处理 TXT/MX/CNAME。
- 节点在任务期间恢复心跳时不会被清理。

### 手工验证命令

测试脚本必须在 `.env` 中设置 `APP_ENV=test` 后运行。

示例：

```bash
podman exec php7-npanel php /var/www/NPanel/artisan autoDeleteExpiredDns --dry-run
```

如新增 PHP 类文件或批量修改 app 目录，完成后必须执行项目权限修复命令：

```bash
podman exec php7-npanel chown -R www-data:www-data /var/www/NPanel/app/
podman exec php7-npanel chmod -R 755 /var/www/NPanel/app/
podman exec php7-npanel php /var/www/NPanel/composer.phar dump-autoload
```

如果 `tests/` 目录新增测试文件，也需要对 `tests/` 执行对应 `chown` 和 `chmod`。

---

## 文件变更清单

| 操作 | 文件路径 | 说明 |
|------|----------|------|
| 修改 | `app/Components/DNS/CloudflareProvider.php` | 增加结构化删除方法，保留原布尔方法 |
| 修改 | `app/Components/Helpers.php` | 增加 `parseDomainPool` 静态方法 |
| 修改 | `app/Http/Controllers/Api/NodeApiController.php` | 使用共享域名池解析；回收路径改为安全 DNS 清理 |
| 修改 | `app/Console/Commands/InitDnsRecords.php` | 使用 `Helpers::parseDomainPool` |
| 新建 | `app/Console/Commands/AutoDeleteExpiredDns.php` | 定时清理命令 |
| 新建 | `app/Services/DnsRecordCleanupService.php` | 共享 DNS 清理逻辑 |
| 修改 | `app/Console/Kernel.php` | 注册命令和每日调度，增加 `withoutOverlapping()` |
| 新建 | `database/migrations/*_add_expired_dns_cleanup_indexes.php` | 为 `ss_node` 添加幂等索引 |
| 新增/修改 | `tests/` | 覆盖 dry-run、成功、404、失败保留、回收路径等场景 |

---

## 风险点

1. **请求内清理耗时**
   `applyId()` 和 `register()` 在请求中执行，清理 Cloudflare 可能增加响应时间。需要设置合理超时，必要时只处理当前节点少量记录。

2. **旧数据缺少 cf_record_id**
   这类记录无法按 ID 删除远端记录，需要后续用 FQDN/type 查询补救。

3. **配置缺失或 zone_id 错误**
   任务会保留本地记录，Cloudflare 记录不会释放。需要通过日志或 dry-run 输出提示人工修复配置。

4. **阈值与现有 32 天复用逻辑**
   当前节点复用逻辑使用 32 天阈值。若定时任务用 30 天，可能出现节点未复用但 DNS 已删除的窗口。上线前必须确认业务是否接受；不接受则将清理阈值统一为 32 天，或清理 DNS 前先禁用节点。
