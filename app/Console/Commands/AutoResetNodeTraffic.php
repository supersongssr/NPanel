<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Models\SsNode;
use App\Components\NodeTrafficResetStore;
use App\Services\Notification\NotifyService;
use Illuminate\Support\Facades\Log;

/**
 * 自动重置节点流量.
 *
 * 三段式逻辑:
 *   1. 正常月度重置: 按 reset_day 匹配当天应重置的节点 (LEAST(reset_day, 当月天数) = 今天),
 *      清零 traffic_used 并记录重置时间 (Redis: NodeTrafficResetStore).
 *   2. 懒初始化基线: Redis 中无重置时间记录的老节点 (迁移前 / Redis 重启后) 补写当前时间,
 *      不动 traffic_used 不告警. 避免首次启用安全网时老节点被误判"从未重置"而全量清零.
 *   3. 安全网兜底: 主节点 (is_clone=0) 若上次重置距今超过 FORCE_RESET_DAYS 天
 *      且 traffic_used > 0, 强制清零 traffic_used + 记录重置时间
 *      + 发送 error notify. 防止定时任务停跑 / 时钟漂移 / reset_day 错改导致节点流量
 *      永不归零、被 120GB 熔断 (status=0) 后无声卡死.
 *
 * "上次重置时间" 为非关键参数, 存 Redis Hash (NodeTrafficResetStore), 不写入 ss_node.
 * 详见: app/Components/NodeTrafficResetStore.php
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
            $node->save();
            NodeTrafficResetStore::set($node->id, $now); // 记录本次重置时间 (Redis)
            $resetCount++;
        }

        // --- 2. 懒初始化基线: Redis 中缺失重置时间记录的节点补写当前时间 ---
        // 迁移前 / Redis 重启后, 全部节点的重置时间记录为空. 首次每日运行时补成 now 作为
        // 安全网计时基线. 只记录时间, 不动 traffic_used, 不告警 ——
        // 避免老节点被误判为"从未重置"而触发全量强制清零 (会一次性清零所有节点累计流量).
        // 新节点 (register) 已写入, 此处主要兜老数据 / Redis 重启.
        $allNodeIds = SsNode::pluck('id')->all();
        $initCount = NodeTrafficResetStore::initMissing($allNodeIds, $now);

        // --- 3. 安全网兜底: 上次重置超过 FORCE_RESET_DAYS 天的主节点强制重置 + 告警 ---
        // 仅主节点 (is_clone=0) 参与判定: 主节点是流量计费单元, clone 不独立计费 (不调 status()).
        // traffic_used=0 的节点无流量可重置, 跳过避免误报.
        $cutoff = strtotime('-' . self::FORCE_RESET_DAYS . ' days');
        $allResetTimes = NodeTrafficResetStore::all(); // [nodeId => "Y-m-d H:i:s"]
        $overdueIds = [];
        foreach ($allResetTimes as $id => $ts) {
            if (strtotime($ts) < $cutoff) {
                $overdueIds[] = (int) $id;
            }
        }

        // Redis 过期时间与 MySQL 节点状态交叉: 仅留 is_clone=0 且 traffic_used>0 的主节点.
        $overdueNodes = empty($overdueIds)
            ? collect()
            : SsNode::whereIn('id', $overdueIds)
                ->where('is_clone', 0)
                ->where('traffic_used', '>', 0)
                ->get();

        $forcedCount = 0;
        foreach ($overdueNodes as $node) {
            // 快照旧值 (保存后 Redis 记录会被覆盖成 $now)
            $lastReset = NodeTrafficResetStore::get($node->id) ?: '从未';
            $staleDays = $lastReset !== '从未'
                ? (int) round((time() - strtotime($lastReset)) / 86400)
                : -1;

            $node->traffic_used = 0;
            $node->save();
            NodeTrafficResetStore::set($node->id, $now);
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
