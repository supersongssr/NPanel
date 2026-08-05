# 流量重置逻辑优化：Cron 驱动重置（已实施）

## 变更摘要

将节点流量重置从 `status()` API 方法中解耦，改为独立的 Cron 定时任务驱动。

## 涉及文件

| 文件 | 操作 | 说明 |
|------|------|------|
| `app/Console/Commands/AutoResetNodeTraffic.php` | 新建 | 每天 00:01 执行，按 `reset_day` 重置节点流量 + 32 天安全网兜底 |
| `app/Components/NodeTrafficReset.php` | 新建 | 单点重置模块：清零 `traffic_used`/`traffic_used_daily` + 刷新 `last_traffic_reset_at`（供 cron / applyId / register 共用） |
| `database/migrations/2026_07_30_000000_add_last_traffic_reset_at_to_ss_node_table.php` | 新建 | 给 `ss_node` 增加 `last_traffic_reset_at` 列并回填 NOW() |
| `app/Console/Kernel.php` | 修改 | 注册命令 + 添加调度 |
| `app/Http/Controllers/Api/NodeApiController.php` | 修改 | 删除 `status()` 中的重置逻辑（原 L1240-1248）；register 初始化基线 / applyId 回收全清 |

## 核心逻辑

```sql
-- 每天执行的查询
SELECT * FROM ss_node WHERE LEAST(reset_day, 当月天数) = 今天日期
```

- `LEAST()` MySQL 原生处理短月：`reset_day=31` 在 2 月 28 天时等价于 28
- 重置经单点模块 `NodeTrafficReset::reset()`：清零 `traffic_used` + `traffic_used_daily`，并刷新 `last_traffic_reset_at`（`last_raw_total` 是网卡物理观测值，不属于任何周期，不清）
- "上次重置时间" 挂 `ss_node.last_traffic_reset_at`（强一致，随节点生命周期），不再依赖外部 SQLite/Redis
- 节点离线也照常重置（直接操作数据库）

## 32 天安全网兜底（2026-08 扩展）

`AutoResetNodeTraffic` 在正常月度重置之外新增三段式逻辑：

1. **正常月度重置**：按 `reset_day` 匹配当天应重置的节点（主节点 + clone），经 `NodeTrafficReset::reset()` 清零并刷新基线。
2. **懒初始化基线**：`last_traffic_reset_at` 仍为 NULL 的节点（迁移后新建 / 漏回填）一条 SQL 统一补 `NOW()`，不动流量、不告警。
3. **安全网兜底**：主节点（`is_clone=0`）若**在线**（近 `FORCE_RESET_DAYS=32` 天内有心跳）且上次重置距今超 32 天、`traffic_used>0`，判定月度 cron 停跑 / 时钟漂移 / `reset_day` 错改，强制清零 + 刷新基线 + 发**单条聚合** error notify（防止多节点连发触发 Telegram 速率限制）。

"在线" 限定把死节点（无心跳/心跳超期）交给 `applyId` 回收流程，也避免误伤刚 register（`heartbeat_at` 尚为 null）的新身份。

## 解决的问题

1. **状态语义污染** — 不再依赖 `updated_at` 判断重置
2. **短月边界** — `LEAST(reset_day, daysInMonth)` 自动取齐
3. **跨期离线** — Cron 直接操作 DB，无视节点在线状态
