<?php

namespace App\Components;

use App\Http\Models\SsNode;
use Illuminate\Support\Facades\DB;

/**
 * 节点流量重置 (单点模块).
 *
 * 收敛 "如何重置一个节点的流量计数" 到唯一入口, 避免逻辑散落:
 *   - AutoResetNodeTraffic : 月度正常重置 + 32 天安全网强制兜底.
 *   (旧注释中的 applyId/register 调用方已改走 NodeDefaults, 不再调本模块)
 *
 * 重置语义: 单条原子 UPDATE 直接写 traffic_used = 0 / traffic_used_daily = 0 /
 * last_traffic_reset_at = now(), 不经过 ORM 读-改-写。写入的是常量(不依赖读到的旧值),
 * InnoDB 行锁下与 v2 traffic() 的原子累加(traffic_used = traffic_used + N)按执行顺序
 * 串行化, 任一顺序的结果都是确定的("重置后新流量从 0 起算 / 重置前旧流量随旧周期归零"),
 * 不存在丢失更新窗口(代码审查修复).
 *
 * "上次重置时间" 直接挂 ss_node (强一致, 随节点生命周期), 不依赖外部 SQLite/Redis.
 *
 * ── 身份继承原则 ("带 id 上报 = 继承遗留信息; 新申请 id = 全新干净节点") ──
 * 节点生命周期里 last_traffic_reset_at / traffic_used 等"遗留信息"是否清零, 取决于身份来源:
 *   · 新申请 id (applyId): applyId 回收旧身份时经 NodeDefaults 全清, 或新建空身份 → 全新.
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
     * 实现为单条原子 UPDATE(不经 ORM 读-改-写, 不触发模型事件);
     * 内存模型属性同步刷新, 保证调用方后续读取仍看到重置后的值。
     *
     * @param  SsNode       $node
     * @param  string|null  $timestamp  重置时间; null = now(). 由调用方统一传 date('Y-m-d H:i:s').
     * @return void
     */
    public static function reset(SsNode $node, $timestamp = null)
    {
        $ts = $timestamp ?: date('Y-m-d H:i:s');

        // 原子单条 UPDATE: 行锁与并发流量上报(traffic_used = traffic_used + N)
        // 按执行顺序串行化, 不存在丢失更新窗口
        DB::table('ss_node')->where('id', (int)$node->id)->update([
            'traffic_used' => 0,
            'traffic_used_daily' => 0,
            'last_traffic_reset_at' => $ts,
        ]);

        // 同步内存模型属性(仅同步, 不再 save —— 写库已由上面的原子 UPDATE 完成)
        $node->traffic_used = 0;
        $node->traffic_used_daily = 0;
        $node->last_traffic_reset_at = $ts;
    }
}
