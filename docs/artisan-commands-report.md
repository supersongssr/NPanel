# Artisan Commands Analysis Report

This report provides an overview of the custom Artisan commands and scheduled tasks registered in the `app/Console/Kernel.php` of the project.

## Summary

There are **22** commands explicitly listed in the `$commands` array of `Kernel.php`, and **24** command files found in the `app/Console/Commands` directory. The application relies heavily on these commands for automated maintenance, statistics, and billing tasks.

## Registered Commands

The following commands are registered in `app/Console/Kernel.php`:

| Command Signature | Description |
| :--- | :--- |
| `autoJob` | 自动化任务 (Automation tasks) |
| `autoClearLog` | 自动清除日志 (Auto-clear logs) |
| `autoDecGoodsTraffic` | 自动扣减用户到期商品的流量 更新等级 标签 (Auto-deduct traffic for expired goods, update levels/labels) |
| `autoResetUserTraffic` | 自动重置用户可用流量 (Auto-reset user traffic) |
| `autoCheckNodeTCP` | 自动检测节点是否被TCP阻断 (Auto-check if nodes are TCP blocked) |
| `autoStatisticsNodeDailyTraffic` | 自动统计节点每日流量 (Daily node traffic statistics) |
| `autoStatisticsNodeHourlyTraffic` | 自动统计节点每小时流量 (Hourly node traffic statistics) |
| `autoStatisticsUserDailyTraffic` | 自动统计用户每日流量 (Daily user traffic statistics) |
| `autoStatisticsUserHourlyTraffic` | 自动统计用户每小时流量 (Hourly user traffic statistics) |
| `userTrafficAbnormalAutoWarning` | 用户流量异常警告 (Abnormal user traffic warning) |
| `userExpireAutoWarning` | 用户临近到期自动发邮件提醒 (Email reminder for nearing expiration) |
| `userTrafficAutoWarning` | 用户流量超过警告阈值自动发邮件提醒 (Email reminder for traffic warning threshold) |
| `upgradeUserLabels` | 初始化用户默认标签 (Initialize default user labels) |
| `upgradeUserPassword` | 用户密码升级(MD5->HASH) (Upgrade user passwords MD5 to HASH) |
| `upgradeUserSpeedLimit` | 升级用户限速字段，重置初始值 (Upgrade user speed limit fields) |
| `upgradeUserSubscribe` | 生成用户的订阅码 (Generate user subscription codes) |
| `upgradeUserVmessId` | 重新生成用户的vmess_id字段 (Regenerate user vmess_id fields) |
| `autoReportNode` | 自动报告节点昨日使用情况 (Auto-report node usage from yesterday) |
| `upgradeUserBannoPay` | 封禁疑似滥用邀请账户 (Ban accounts suspected of abusing invitations) |
| `AutoCheckNodeStatus` | 自动检查节点状态status (Auto-check node status) |
| `autoBanUserNoMoney` | 自动禁用余额低于0的用户 (Auto-ban users with balance < 0) |
| `Test` | Test测试 (Testing command) |

## Additional Commands (Not explicitly in $commands array but in directory)

These commands are found in `app/Console/Commands` and are likely loaded automatically via `$this->load(__DIR__.'/Commands')`:

| Command Signature | Description |
| :--- | :--- |
| `initDnsRecords` | Sync DNS records from Cloudflare: clean orphans, upsert matched A/AAAA records |
| `rate-limit:clear {code?}` | Clear subscription rate limit cache from Redis |

## Scheduled Tasks

The following tasks are scheduled in the `schedule` method:

| Command | Frequency | Time (if applicable) |
| :--- | :--- | :--- |
| `autoJob` | Every 30 minutes | - |
| `autoClearLog` | Every 30 minutes | - |
| `autoDecGoodsTraffic` | Hourly | - |
| `autoResetUserTraffic` | Daily | - |
| `autoCheckNodeTCP` | Hourly | - |
| `autoStatisticsNodeDailyTraffic` | Daily | 3:13 |
| `autoStatisticsNodeHourlyTraffic` | Hourly | - |
| `autoStatisticsUserDailyTraffic` | Daily | 3:17 |
| `autoStatisticsUserHourlyTraffic` | Hourly | - |
| `userTrafficAbnormalAutoWarning` | Hourly | - |
| `userExpireAutoWarning` | Daily | 20:00 |
| `userTrafficAutoWarning` | Daily | 10:30 |
| `autoReportNode` | Daily | 09:00 |
| `autoBanUserNoMoney` | Daily | 05:00 |

---
*Report generated on May 1, 2026*
