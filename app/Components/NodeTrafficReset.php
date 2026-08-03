<?php

namespace App\Components;

use App\Http\Models\SsNode;

/**
 * 节点流量重置 (单点模块).
 *
 * 收敛 "如何重置一个节点的流量计数" 到唯一入口, 供三处调用方共用, 避免逻辑散落:
 *   - NodeApiController::applyId()  : 旧节点身份回收 → 全清 (清零 + 刷新基线 now).
 *   - NodeApiController::register() : 仅在 last_traffic_reset_at 为 null (全新身份) 时初始化 now;
 *     带缓存 node_id 的同机重装【保留】既有基线, 不重置 (见下方"身份继承原则").
 *   - AutoResetNodeTraffic          : 月度正常重置 + 32 天安全网强制兜底.
 *
 * 重置语义: traffic_used = 0 + traffic_used_daily = 0 + last_traffic_reset_at = now().
 * "上次重置时间" 直接挂 ss_node (强一致, 随节点生命周期), 不再依赖外部 SQLite/Redis.
 *
 * ── 身份继承原则 ("带 id 上报 = 继承遗留信息; 新申请 id = 全新干净节点") ──
 * 节点生命周期里 last_traffic_reset_at / traffic_used 等"遗留信息"是否清零, 取决于身份来源:
 *   · 新申请 id (applyId): applyId 回收旧身份时调本模块 reset() 全清, 或新建空身份 → 全新.
 *   · 带 id 上报 (同机重装, applyId 被 proxyInstall.sh Step0 跳过): register 保留既有
 *     last_traffic_reset_at 与节点上报的 traffic_used, 计费时间线连续, 不被重置打断.
 * 这样安全网基线不会被"重装即刷新成 now()"而永久失明, 也不会让复用身份继承前任旧基线
 * (复用必经 applyId, 已被全清). register 处的判据就是 last_traffic_reset_at === null.
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
