<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Http\Models\SsNode;
use App\Components\NodeTrafficReset;
use App\Services\Notification\NotifyService;
use Illuminate\Support\Facades\Log;

/**
 * 自动重置节点流量.
 *
 * 三段式逻辑:
 *   1. 正常月度重置: 按 reset_day 匹配当天应重置的节点 (LEAST(reset_day, 当月天数) = 今天),
 *      经 NodeTrafficReset::reset() 清零 traffic_used/daily 并刷新 last_traffic_reset_at.
 *   2. 懒初始化基线: last_traffic_reset_at 仍为 NULL 的节点 (迁移后新建 / 漏回填) 统一补 now,
 *      不动 traffic_used 不告警. 一条 SQL 完成.
 *   3. 安全网兜底: 主节点 (is_clone=0) 若上次重置距今超过 FORCE_RESET_DAYS 天
 *      且 traffic_used > 0, 强制经 NodeTrafficReset::reset() 清零 + 记录时间
 *      + 发送 error notify. 防止定时任务停跑 / 时钟漂移 / reset_day 错改导致节点流量
 *      永不归零、被熔断 (status=0) 后无声卡死.
 *
 * "上次重置时间" 直接挂 ss_node.last_traffic_reset_at (强一致, 随节点生命周期),
 * 不再依赖外部 SQLite/Redis 存储.
 * 详见: app/Components/NodeTrafficReset.php
 *       app/Http/Controllers/Api/NodeApiController.php (register 设基线 / applyId 回收重置)
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

        // 守卫: 列缺失 (代码已上线但 migrate 未跑成功) 时直接告警退出,
        // 避免后续 SQL 引用不存在列导致整个命令 500 + 日志刷屏.
        if (!Schema::hasColumn('ss_node', 'last_traffic_reset_at')) {
            Log::error('[AutoResetNodeTraffic] ss_node 缺少 last_traffic_reset_at 列, 请先执行 php artisan migrate');
            return;
        }

        $today = (int) date('d');
        $daysInMonth = (int) date('t');
        $now = date('Y-m-d H:i:s');

        // --- 1. 正常月度重置: 按 reset_day 匹配当天应重置的节点 (主节点 + clone) ---
        $resetNodes = SsNode::whereRaw('LEAST(reset_day, ?) = ?', [$daysInMonth, $today])
            ->get();

        $resetCount = 0;
        foreach ($resetNodes as $node) {
            NodeTrafficReset::reset($node, $now); // 清零 traffic_used/daily + 刷新 last_traffic_reset_at
            $resetCount++;
        }

        // --- 2. 懒初始化基线: last_traffic_reset_at 仍为 NULL 的节点补 now (不动流量, 不告警) ---
        // 迁移后新建 / 漏回填 / 列被外部清空的节点, 统一补成 now 作为安全网计时基线.
        // 一条 SQL 完成, 不逐条 save (数百节点无压力).
        $initCount = SsNode::whereNull('last_traffic_reset_at')->update([
            'last_traffic_reset_at' => $now,
        ]);

        // --- 3. 安全网兜底: 上次重置超过 FORCE_RESET_DAYS 天的主节点强制重置 + 告警 ---
        // 仅主节点 (is_clone=0) 参与判定: 主节点是流量计费单元, clone 不独立计费 (不调 status()).
        // traffic_used=0 的节点无流量可重置, 跳过避免误报.
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::FORCE_RESET_DAYS . ' days'));

        $overdueNodes = SsNode::where('is_clone', 0)
            ->where('traffic_used', '>', 0)
            ->where('last_traffic_reset_at', '<', $cutoff)
            ->get();

        $forcedCount = 0;
        foreach ($overdueNodes as $node) {
            // 快照旧值 (reset 后 last_traffic_reset_at 会被覆盖成 $now)
            $lastReset = $node->last_traffic_reset_at ?: '从未';
            $staleDays = $lastReset !== '从未'
                ? (int) round((time() - strtotime($lastReset)) / 86400)
                : -1;

            NodeTrafficReset::reset($node, $now);
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
