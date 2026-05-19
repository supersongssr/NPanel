# 流量重置逻辑优化：Cron 驱动重置（已实施）

## 变更摘要

将节点流量重置从 `status()` API 方法中解耦，改为独立的 Cron 定时任务驱动。

## 涉及文件

| 文件 | 操作 | 说明 |
|------|------|------|
| `app/Console/Commands/AutoResetNodeTraffic.php` | 新建 | 每天 00:01 执行，按 `reset_day` 重置节点流量 |
| `app/Console/Kernel.php` | 修改 | 注册命令 + 添加调度 |
| `app/Http/Controllers/Api/NodeApiController.php` | 修改 | 删除 `status()` 中的重置逻辑（原 L1240-1248） |

## 核心逻辑

```sql
-- 每天执行的查询
SELECT * FROM ss_node WHERE LEAST(reset_day, 当月天数) = 今天日期
```

- `LEAST()` MySQL 原生处理短月：`reset_day=31` 在 2 月 28 天时等价于 28
- 只清零 `traffic_used`（`last_raw_total` 是网卡物理观测值，不属于任何周期）
- 节点离线也照常重置（直接操作数据库）

## 解决的问题

1. **状态语义污染** — 不再依赖 `updated_at` 判断重置
2. **短月边界** — `LEAST(reset_day, daysInMonth)` 自动取齐
3. **跨期离线** — Cron 直接操作 DB，无视节点在线状态
