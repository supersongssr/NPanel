<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\SsNode;
use App\Services\Notification\NotifyService;
use Illuminate\Support\Facades\Log;

/**
 * 自动重置节点流量.
 *
 * 两段式逻辑:
 *   1. 正常月度重置: 按 reset_day 匹配当天应重置的节点 (LEAST(reset_day, 当月天数) = 今天),
 *      清零 traffic_used 并记录 traffic_reset_at = now().
 *   2. 懒初始化基线: traffic_reset_at 为空的老节点 (迁移前从未记录重置时间) 设为 now(),
 *      不动 traffic_used 不告警. 避免首次启用安全网时老节点被误判"从未重置"而全量清零.
 *   3. 安全网兜底: 主节点 (is_clone=0) 若 traffic_reset_at 距今超过 FORCE_RESET_DAYS 天
 *      且 traffic_used > 0, 强制清零 traffic_used + 记录 traffic_reset_at
 *      + 发送 error notify. 防止定时任务停跑 / 时钟漂移 / reset_day 错改导致节点流量
 *      永不归零、被 120GB 熔断 (status=0) 后无声卡死.
 *
 * 详见: app/Http/Models/SsNode.php (traffic_reset_at 字段)
 *       app/Http/Controllers/Api/NodeApiController.php (register 初始化基线)
 */
class AutoResetNodeTraffic extends Command
{
    /**
     * 流量重置安全网阈值: 超过该天数未成功重置则强制重置 + 告警.
     * 月度重置最长周期 ~31 天 (reset_day 超过当月天数时取当月天数), 32 天留 1 天容差.
     */
    const FORCE_RESET_DAYS = 32;

    protected $signature = 'autoResetNodeTraffic';
    protected $description = '自动重置节点流量（按 reset_day）+ 32 天未重置强制兜底告警';

    public function handle()
    {
        $jobStartTime = microtime(true);

        $today = (int) date('d');
        $daysInMonth = (int) date('t');
        $now = date('Y-m-d H:i:s');

        // --- 1. 正常月度重置: 按 reset_day 匹配当天应重置的节点 (主节点 + clone) ---
        $resetNodes = SsNode::whereRaw('LEAST(reset_day, ?) = ?', [$daysInMonth, $today])
            ->get();

        $resetCount = 0;
        foreach ($resetNodes as $node) {
            $node->traffic_used = 0;
            $node->traffic_reset_at = $now; // 记录本次重置时间
            $node->save();
            $resetCount++;
        }

        // --- 2. 懒初始化基线: traffic_reset_at 为空的老节点设为当前时间 ---
        // 迁移后所有现有节点初始为 NULL. 首次每日运行时, 将这些 NULL 节点的 traffic_reset_at
        // 设为 now 作为安全网计时基线. 只记录时间, 不动 traffic_used, 不告警 ——
        // 避免老节点被误判为"从未重置"而触发全量强制清零 (会一次性清零所有节点累计流量).
        // 新节点 (register) 已初始化, 此处主要兜迁移前的老数据.
        $initCount = SsNode::whereNull('traffic_reset_at')->update(['traffic_reset_at' => $now]);

        // --- 3. 安全网兜底: 超过 FORCE_RESET_DAYS 天未重置的主节点强制重置 + 告警 ---
        // 仅主节点 (is_clone=0) 参与判定: 主节点是流量计费单元, clone 不独立计费 (不调 status()).
        // traffic_used=0 的节点无流量可重置, 跳过避免误报 (其 traffic_reset_at 由正常月度重置刷新).
        // (经步骤 2 后无 NULL 节点, 此处只匹配非 NULL 且过期的.)
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::FORCE_RESET_DAYS . ' days'));
        $overdueNodes = SsNode::where('is_clone', 0)
            ->where('traffic_used', '>', 0)
            ->where('traffic_reset_at', '<', $cutoff)
            ->get();

        $forcedCount = 0;
        foreach ($overdueNodes as $node) {
            // 先快照旧值, 保存后 traffic_reset_at 会被覆盖成 $now
            $lastReset = $node->traffic_reset_at ?: '从未';
            $staleDays = $node->traffic_reset_at
                ? (int) round((time() - strtotime($node->traffic_reset_at)) / 86400)
                : -1;

            $node->traffic_used = 0;
            $node->traffic_reset_at = $now;
            $node->save();
            $forcedCount++;

            $title = '⚠️ 节点流量强制重置';
            $content = sprintf(
                "节点 **%s** (ID:%d, %s) 超过 %d 天未重置流量 (上次重置: %s, 距今 %s), 已强制清零.\n" .
                "请检查 `autoResetNodeTraffic` 定时任务是否正常运行 / 系统时钟是否准确.",
                $node->name ?: ('Node#' . $node->id),
                $node->id,
                $node->ip ?: ($node->ipv6 ?: 'no-ip'),
                self::FORCE_RESET_DAYS,
                $lastReset,
                $staleDays > 0 ? ($staleDays . ' 天') : '从未重置'
            );

            app(NotifyService::class)->error($title, $content, [
                'node_id' => $node->id,
                'node_name' => $node->name,
                'node_ip' => $node->ip ?: ($node->ipv6 ?: ''),
                'last_reset' => $lastReset,
                'stale_days' => $staleDays,
            ]);

            Log::error('[AutoResetNodeTraffic] 强制重置节点流量 (超过' . self::FORCE_RESET_DAYS . '天未重置)', [
                'node_id' => $node->id,
                'node_name' => $node->name,
                'last_reset' => $lastReset,
                'stale_days' => $staleDays,
            ]);
        }

        $jobEndTime = microtime(true);
        $jobUsedTime = round(($jobEndTime - $jobStartTime), 4);

        Log::info('执行定时任务【' . $this->description . '】，正常重置 ' . $resetCount . ' 个节点，懒初始化 ' . $initCount . ' 个节点，强制重置 ' . $forcedCount . ' 个节点，耗时' . $jobUsedTime . '秒');
    }
}
