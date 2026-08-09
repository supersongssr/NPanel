<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Components\Helpers;
use App\Http\Models\SsNode;
use App\Http\Models\SsGroupNode;
use App\Http\Models\SsNodeLabel;
use App\Http\Models\SsNodeInfo;
use App\Http\Models\SsNodeOnlineLog;
use App\Http\Models\SsNodeTrafficDaily;
use App\Http\Models\SsNodeTrafficHourly;
use App\Http\Models\UserTrafficDaily;
use App\Http\Models\UserTrafficHourly;
use App\Http\Models\UserTrafficLog;
use App\Http\Models\DnsRecord;
use App\Http\Models\SsNodeIp;
use App\Services\NodeAddress\DnsSyncer;

/**
 * 死节点比例回收 (硬删除)
 *
 * 设计文档: plans/nodes/autoReclaimDeadNodes.md
 *
 * 机制 (比例制 + 绝对下限):
 *   - 死节点 = status=0 AND 心跳超 dns_expire_days (与 AutoDeleteExpiredDns / clone 回收同源定义)
 *   - 触发:  dead > alive  (等价 dead/total > 50%)  且  dead >= min_dead (默认 500)
 *   - 配额:  删除 (dead - alive) 个, 删到 dead == alive (恰好回归 50%)
 *   - 选取:  id ASC (回收池"死寂端": 回收走 id DESC 取高 id, 删除从低 id 裁, 对回收零损耗)
 *
 * 安全:
 *   - status=0 闸门: 在用节点 status=1, 永不进候选集
 *   - 删除前先调 DnsSyncer::cleanupNodeRecords 清理 Cloudflare 远端 DNS (不只是删本地行)
 *   - 级联 13 张表 (delNode 9 张 + dns_records + ss_node_ip + ss_node_deny), 避免残留
 *   - 保留区 id<100 (PingController 预留)
 *   - 强制 --dry-run 预演, withoutOverlapping 防重叠
 *
 * 配置 (config.default.php / .config.php, 与 dns_expire_days 同源):
 *   - node_recycle_ratio      触发比例, 默认 0.50
 *   - node_recycle_min_dead   死节点绝对下限, 默认 500
 *   - dns_expire_days         复用为 cutoff 阈值 (与 DNS 清理 / clone 回收三者一致, 避免窗口错位)
 */
class AutoReclaimDeadNodes extends Command
{
    /** 死/总节点 触发比例默认值 */
    const DEFAULT_RATIO = 0.50;

    /** 死节点绝对下限: 低于此值无存储压力, 不折腾 */
    const DEFAULT_MIN_DEAD = 500;

    /** 保留区: id<100 为 PingController 预留的固定节点, 永不删除 */
    const RESERVE_ID_FLOOR = 99;

    protected $signature = 'autoReclaimDeadNodes
        {--dry-run : 仅预览不删除}
        {--ratio= : 覆盖触发比例 (0~1, 留空读配置 node_recycle_ratio, 仍无则默认 0.50)}
        {--min-dead= : 覆盖死节点绝对下限 (留空读配置 node_recycle_min_dead, 仍无则默认 500)}';

    protected $description = '死节点比例回收: 死节点超总节点 50% 时硬删除冗余, 删前先清理 CF 远端 DNS';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $tag = 'AutoReclaimDeadNodes';

        $sysConf = Helpers::systemConfig();
        $cutoff = $this->resolveCutoff($sysConf);
        $ratio = $this->resolveRatio($sysConf);
        $minDead = $this->resolveMinDead($sysConf);

        $this->info("=== {$tag} ===");
        $this->info("Cutoff: {$cutoff} (dns_expire_days)");
        $this->info("Ratio threshold: {$ratio} | Min dead: {$minDead}");
        $this->info("Dry-run: " . ($dryRun ? 'YES' : 'NO'));
        $this->info('');

        // —— 死节点基础查询 (status=0 + 心跳超期 + 保留区) ——
        $deadQuery = SsNode::where('status', 0)
            ->where(function ($q) use ($cutoff) {
                $q->where('heartbeat_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
                  });
            })
            ->where('id', '>', self::RESERVE_ID_FLOOR);

        $total = SsNode::count();
        $dead = (clone $deadQuery)->count();
        $alive = $total - $dead;  // 不变量: 删死节点不影响 alive
        $currentRatio = $total > 0 ? round($dead / $total, 4) : 0;

        $this->info("--- 节点统计 ---");
        $this->info("Total: {$total} | Dead: {$dead} | Alive: {$alive} | dead/total = {$currentRatio}");
        $this->info('');

        // —— 判断条件 ——
        if ($dead < $minDead) {
            $this->info("[SKIP] 死节点 {$dead} < 下限 {$minDead}, 无存储压力, 跳过");
            Log::info("{$tag} skipped: dead({$dead}) < min_dead({$minDead})", compact('total', 'dead', 'alive', 'currentRatio', 'ratio'));
            return 0;
        }

        // 目标死节点数: 删后 dead_new/(alive+dead_new) <= ratio, alive 为不变量
        //   推导: dead_new <= ratio*alive/(1-ratio)
        //   ratio=0.5 时 targetDead = alive  (即"回归到 dead==alive, 恰好 50%")
        //   ratio=0.3 时 targetDead = 0.3*alive/0.7 ≈ 0.43*alive
        $targetDead = $alive > 0 ? (int) floor($ratio * $alive / (1 - $ratio)) : 0;
        if ($dead <= $targetDead) {
            $this->info("[SKIP] 死节点 {$dead} <= 目标 {$targetDead} (ratio={$ratio}), 占比未超, 跳过");
            Log::info("{$tag} skipped: dead({$dead}) <= targetDead({$targetDead})", compact('total', 'dead', 'alive', 'currentRatio', 'ratio'));
            return 0;
        }

        // —— 配额: 删到 dead == targetDead (回归 ratio) ——
        $deleteCount = $dead - $targetDead;
        $this->info("[TRIGGER] 将删除 {$deleteCount} 个最老死节点 (id ASC), 回归 dead={$targetDead} (ratio={$ratio})");
        $this->info('');

        // —— 选取 victim: id ASC (回收池死寂端), 走主键索引零 filesort ——
        $victims = (clone $deadQuery)
            ->orderBy('id', 'asc')
            ->limit($deleteCount)
            ->get(['id', 'name', 'heartbeat_at', 'created_at', 'status']);

        $stats = array(
            'planned' => $deleteCount,
            'deleted' => 0,
            'failed' => 0,
            'deleted_ids' => array(),
        );

        foreach ($victims as $node) {
            // 二次校验: 命令运行期间节点可能恢复心跳/被启用, 重新确认仍为死节点
            $stillDead = ($node->status === 0)
                && ($node->heartbeat_at === null
                    ? ($node->created_at !== null && $node->created_at < $cutoff)
                    : ($node->heartbeat_at < $cutoff));
            if (!$stillDead) {
                $this->line("  Node#{$node->id} \"{$node->name}\" [SKIP] 运行期间状态恢复, 跳过");
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY] would del Node#{$node->id} \"{$node->name}\" hb={$node->heartbeat_at} created={$node->created_at}");
                $stats['deleted']++;
                continue;
            }

            try {
                $this->cascadeDelete($node->id, $tag);
                $this->info("  [DELETED] Node#{$node->id} \"{$node->name}\"");
                $stats['deleted']++;
                $stats['deleted_ids'][] = $node->id;
            } catch (\Exception $e) {
                $this->error("  [FAILED] Node#{$node->id} \"{$node->name}\" — " . $e->getMessage());
                $stats['failed']++;
            }
        }

        $this->info('');
        $this->info("--- Summary ---");
        $this->info("Planned: {$stats['planned']} | Deleted: {$stats['deleted']} | Failed: {$stats['failed']}");
        if ($dryRun) {
            $this->warn('[DRY-RUN] No changes were made.');
        }

        Log::info("{$tag} completed", $stats);

        return 0;
    }

    /**
     * 级联删除节点: 先清 Cloudflare 远端 DNS, 再删 ss_node 本体 + 12 张关联表.
     *
     * 顺序关键: 必须先调 DnsSyncer 清理远端 CF 记录 (否则只删本地行会留下 CF 孤儿记录,
     * 永远释放不了额度). DnsSyncer 内部按记录 root_domain 解析对应 cf_token (跨账号域名),
     * 失败会强制删本地并告警, 不抛异常.
     *
     * @param int $nodeId
     * @param string $tag 日志标签
     * @throws \Exception 数据库事务失败时抛出 (上层 catch 记录, 节点保留待下次)
     */
    protected function cascadeDelete($nodeId, $tag)
    {
        // 1. 先清理 Cloudflare 远端 DNS (复用 NodeApiController::safeCleanupNodeDns 同一入口)
        try {
            $syncer = new DnsSyncer();
            $syncer->cleanupNodeRecords($nodeId, $tag . '.cascadeDelete');
        } catch (\Exception $e) {
            // DnsSyncer 内部已有 try/catch + 强制删本地兜底, 此处仅记录, 不阻断节点删除
            Log::warning("{$tag} DnsSyncer cleanup exception for node#{$nodeId}: " . $e->getMessage());
        }

        // 2. 级联删本地表 (事务包裹, 失败回滚保留节点)
        DB::beginTransaction();
        try {
            SsNode::where('id', $nodeId)->delete();
            SsGroupNode::where('node_id', $nodeId)->delete();
            SsNodeLabel::where('node_id', $nodeId)->delete();
            SsNodeInfo::where('node_id', $nodeId)->delete();
            SsNodeOnlineLog::where('node_id', $nodeId)->delete();
            SsNodeTrafficDaily::where('node_id', $nodeId)->delete();
            SsNodeTrafficHourly::where('node_id', $nodeId)->delete();
            UserTrafficDaily::where('node_id', $nodeId)->delete();
            UserTrafficHourly::where('node_id', $nodeId)->delete();
            UserTrafficLog::where('node_id', $nodeId)->delete();
            DnsRecord::where('node_id', $nodeId)->delete();   // 兜底: DnsSyncer 只清 A/AAAA, 此处清剩余类型 + 残留
            SsNodeIp::where('node_id', $nodeId)->delete();
            DB::table('ss_node_deny')->where('node_id', $nodeId)->delete();  // 节点封禁记录 (无模型, 用 DB facade)
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("{$tag} cascade 失败 node#{$nodeId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 解析 cutoff 时间: 复用 dns_expire_days (与 AutoDeleteExpiredDns / clone 回收三者一致).
     * 调整阈值只需改 config 的 dns_expire_days, 无需改代码.
     */
    protected function resolveCutoff(array $sysConf)
    {
        $days = isset($sysConf['dns_expire_days']) ? (int) $sysConf['dns_expire_days'] : 30;
        if ($days < 1) {
            $days = 30;
        }
        return date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    }

    /**
     * 解析触发比例: CLI --ratio > config node_recycle_ratio > 默认 0.50.
     */
    protected function resolveRatio(array $sysConf)
    {
        $cliRatio = (float) $this->option('ratio');
        if ($cliRatio > 0 && $cliRatio < 1) {
            return $cliRatio;
        }
        if (isset($sysConf['node_recycle_ratio'])) {
            $cfgRatio = (float) $sysConf['node_recycle_ratio'];
            if ($cfgRatio > 0 && $cfgRatio < 1) {
                return $cfgRatio;
            }
        }
        return self::DEFAULT_RATIO;
    }

    /**
     * 解析死节点绝对下限: CLI --min-dead > config node_recycle_min_dead > 默认 500.
     */
    protected function resolveMinDead(array $sysConf)
    {
        $cliMin = (int) $this->option('min-dead');
        if ($cliMin >= 1) {
            return $cliMin;
        }
        if (isset($sysConf['node_recycle_min_dead'])) {
            $cfgMin = (int) $sysConf['node_recycle_min_dead'];
            if ($cfgMin >= 1) {
                return $cfgMin;
            }
        }
        return self::DEFAULT_MIN_DEAD;
    }
}
