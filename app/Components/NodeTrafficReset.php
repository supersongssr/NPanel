<?php

namespace App\Components;

use App\Http\Models\SsNode;

/**
 * 节点流量重置 (单点模块).
 *
 * 收敛 "如何重置一个节点的流量计数" 到唯一入口, 供三处调用方共用, 避免逻辑散落:
 *   - NodeApiController::applyId()  : 旧节点身份回收 (清零 + 刷新基线, 杜绝残留误报).
 *   - NodeApiController::register() : 注册设基线 (仅写时间, 信任节点上报的 traffic_used).
 *   - AutoResetNodeTraffic          : 月度正常重置 + 32 天安全网强制兜底.
 *
 * 重置语义: traffic_used = 0 + traffic_used_daily = 0 + last_traffic_reset_at = now().
 * "上次重置时间" 直接挂 ss_node (强一致, 随节点生命周期), 不再依赖外部 SQLite/Redis.
 *
 * 详见: app/Console/Commands/AutoResetNodeTraffic.php
 *       app/Http/Controllers/Api/NodeApiController.php (register / applyId)
 */
class NodeTrafficReset
{
    /**
     * 重置节点流量计数: 清零 traffic_used / traffic_used_daily + 刷新 last_traffic_reset_at.
     *
     * @param  SsNode       $node
     * @param  string|null  $timestamp  重置时间; null = now(). 由调用方统一传 date('Y-m-d H:i:s').
     * @return void
     */
    public static function reset(SsNode $node, $timestamp = null)
    {
        $node->traffic_used = 0;
        $node->traffic_used_daily = 0;
        $node->last_traffic_reset_at = $timestamp ?: date('Y-m-d H:i:s');
        $node->save();
    }
}
