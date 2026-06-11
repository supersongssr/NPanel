# Plan: 节点上报 status 时自动恢复 status=1

## where
`app/Http/Controllers/Api/NodeApiController.php` → `status()` 方法

## why
当前 `status()` 方法只有**熔断下线**逻辑（剩余流量 < 120GB → status=0），但没有**自动恢复**逻辑。

当月流量重置后，`traffic_used` 被清零、剩余流量恢复充裕，但 `status` 仍停留在 0，需要手动恢复。
这导致节点在流量重置后无法自动重新上线。

## how

在 `status()` 方法中，将现有熔断逻辑改造为**双向判断**：剩余流量 ≥ 120GB → status=1（恢复），剩余流量 < 120GB → status=0（熔断）。

### 执行顺序分析

| 剩余流量 | 最终 status | 说明 |
|----------|-------------|------|
| ≥ 120GB  | **1** ✅ 可用 | 自动恢复上线 |
| < 120GB  | **0** ❌ 熔断 | 保持熔断下线 |

### 具体修改位置

文件：`NodeApiController.php`
方法：`status()`

在以下代码块之后插入恢复逻辑：

```php
// 现有代码（第 ~1970 行）
if (
    $node->traffic_limit - $node->traffic_used <
    120 * 1024 * 1024 * 1024
) {
    $node->status = 0;
}

// ===== 替换为双向判断 =====
$trafficLeft = $node->traffic_limit - $node->traffic_used;
$threshold = 120 * 1024 * 1024 * 1024; // 120GB
if ($trafficLeft >= $threshold) {
    if ($node->status != 1) {
        Log::info("[Node API] 节点自动恢复上线", [
            "node_id" => $nodeId,
            "traffic_left_gb" => round($trafficLeft / 1024 / 1024 / 1024, 2),
        ]);
    }
    $node->status = 1;
} else {
    $node->status = 0;
}
```

## input
- 节点上报的 `raw_rx`, `raw_tx`（计算增量流量）
- 数据库中已有的 `traffic_limit`, `traffic_used`

## output
- 更新后的 `status` 字段（0 或 1）
- API 返回 `node_status` 反映最新状态

## do
1. 计算剩余流量 `traffic_left = traffic_limit - traffic_used`
2. 如果剩余流量 ≥ 120GB，设置 `status = 1`（自动恢复）
3. 如果剩余流量 < 120GB，设置 `status = 0`（熔断）
4. 如果之前 status 是 0 且被恢复为 1，记录 info 级别日志

## must
1. **使用 120GB 作为统一的熔断/恢复阈值**：≥ 120GB → status=1，< 120GB → status=0
2. **不修改 `register()` 等其他方法中的 status 设置逻辑**
3. **PHP 7.4 兼容**：不使用任何 PHP 8+ 特性
4. **新增日志**：仅在 status 从 0 恢复为 1 时记录，避免每次上报都刷日志

## 影响范围
- 仅影响 `status()` 方法
- 不影响 `register()`（注册时已硬设 status=1）
- 不影响 `config()`（config 检查 status==0 拒绝下发，恢复后自动解除）
