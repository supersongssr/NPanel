when: 2026-06-26T19:00:00
where:
  - app/Services/DnsRecordCleanupService.php
  - app/Console/Commands/AutoDeleteExpiredDns.php
  - app/Console/Commands/TestAutoDeleteExpiredDns.php
  - app/Services/NodeAddress/DnsSyncer.php
  - tests/test_auto_delete_expired_dns.php
  - docs/del-node-or-back-node-when-used.md
why:
  - 自动删除无心跳节点 DNS 功能 (AutoDeleteExpiredDns) 已存在, 但存在关键缺陷: DNS 模块独立后
    CDN 域名 (cdn:true + cf_token) 使用独立 Cloudflare Token, 而定时清理用单一全局 Token 删所有记录,
    导致 CDN 域名记录因鉴权失败永远残留 (DNS 槽位永远释放不了, 违背功能初衷).
  - 清理阈值原先硬编码默认 30 天, 定时任务无命令行参数时无法自由调整.
how:
  - DnsRecordCleanupService 新增静态 resolveProvider($rootDomain, $domainPool): CDN 域名
    (cdn:true + cf_token) 返回 withToken() 独立 Provider, 其余返回全局 app(CloudflareProvider).
    作为「创建/更新 (DnsSyncer)」与「定时删除 (AutoDeleteExpiredDns)」唯一的 Token 选择入口.
  - AutoDeleteExpiredDns: 移除单一全局 Provider, 改为按记录根域名 resolveProvider (每条记录独立);
    新增 cdn_token_records 统计; DEFAULT_EXPIRE_DAYS 30→32; 新增 resolveExpireDays() 实现
    优先级 --days > config dns_expire_days > 默认 32; chunk 闭包 use 列表移除已删的 $dnsProvider.
  - DnsSyncer::providerForDomain 重构为委托 resolveProvider (DRY, 仅保留按域名实例缓存).
  - config 表新增一行 name=dns_expire_days value=整数天数 即可调整定时任务阈值, 无需改代码.
must:
  - 删除 CDN 域名记录必须用其独立 cf_token, 否则鉴权失败导致远端记录残留 (本地回滚保留待重试)
  - 阈值优先级固定: 命令行 --days > 配置 dns_expire_days > DEFAULT_EXPIRE_DAYS(32)
  - 本地记录仅在 CF 删除成功 / 404 时才删, 失败 (429/curl/error) 必须保留待重试
  - 仅处理 A/AAAA 记录; dry-run 不调用 CF API 也不删 DB
  - PHP 7.4 兼容 (无 8+ 语法); 新建文件 chown www-data + dump-autoload
test:
  - app/Console/Commands/TestAutoDeleteExpiredDns.php 新增测试 9 (resolveProvider: CDN 独立 Token vs 全局)
  - tests/test_auto_delete_expired_dns.php (9 项: 真实域名池 Token 选择 / 阈值优先级 / dry-run 遍历 CDN 记录)
status: done
