<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Http\Models\SsNode;
use App\Services\NodeAddress\OptimizedIpPool;
use App\Services\NodeAddress\NodeAddressService;

/**
 * 每日轮换 xhttp-cdn 节点的 CF 优选 IP (缓存到 v2_cdn_ip).
 *
 * 背景:
 *   xhttp-cdn 模式下客户端连接 CF 优选 IP (非节点 IP / 非域名).
 *   优选 IP 长期固定易被封, 故每日轮换一次以分散风险.
 *
 * IP 来源 (重构后):
 *   CF 优选 IP 池 = CSV 文件 (env CF_BETTER_IPS_CSV), 由 OptimizedIpPool 解析.
 *   - CSV 按下载速度降序排序; 丢包率高的剔除.
 *   - 本命令每日把 CSV 中按 nodeId 错峰的「下一个」IP 写入 v2_cdn_ip 缓存.
 *   - 用户定期重新跑优选 IP 测速脚本刷新 CSV → 全部节点随之轮换.
 *
 * 关键点:
 *   - 只更新数据库 v2_cdn_ip, 不动 DNS (DNS 指向源站).
 *   - 订阅实时读库, 客户端下次拉订阅即生效.
 *   - register 不再写 v2_cdn_ip; 首次订阅时 NodeAddressService 会从 CSV 情性选取.
 *   - 仅一个优选 IP 时无法切换, 仅记录日志.
 *
 * 用法:
 *   php artisan autoRotateCdnIp            # 执行轮换
 *   php artisan autoRotateCdnIp --dry-run  # 仅预览
 *
 * 由 Console\Kernel 每日 04:30 调度.
 */
class AutoRotateCdnIp extends Command
{
    protected $signature = 'autoRotateCdnIp {--dry-run : 仅预览不更新}';
    protected $description = '每日轮换 xhttp-cdn 节点的 CF 优选 IP (CSV 来源, 缓存到 v2_cdn_ip)';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $tag = 'AutoRotateCdnIp';

        $this->info("=== {$tag} ===");
        $this->info('Dry-run: ' . ($dryRun ? 'YES' : 'NO'));
        $this->info('CSV 来源: ' . OptimizedIpPool::csvPath());

        // 强制重读 CSV (cron 每日运行, 避免读到旧缓存)
        OptimizedIpPool::clearCache();
        $ips = OptimizedIpPool::all();
        $ipCount = count($ips);
        $this->info("CF 优选 IP 数量 (CSV, 按速度降序): {$ipCount}");
        if ($ipCount > 0) {
            $preview = array_slice($ips, 0, 10);
            $this->info('Top 10: ' . implode(', ', $preview) . ($ipCount > 10 ? ' ...' : ''));
        }

        if ($ipCount === 0) {
            $msg = "CSV 无可用 CF 优选 IP (路径: " . OptimizedIpPool::csvPath() . "), 跳过轮换";
            $this->warn($msg);
            Log::warning("[{$tag}] {$msg}");
            return 0;
        }
        if ($ipCount === 1) {
            $msg = "CSV 仅有 1 个 CF 优选 IP, 无法轮换 (切换无意义), 跳过";
            $this->warn($msg);
            Log::info("[{$tag}] {$msg}");
            return 0;
        }

        // CDN 节点: v2_name 为 CDN 模式 (xhttp-cdn / xhttp-cdn-hy2) 或 v2_cdn='cf' (历史兼容).
        // isCdnNode 过滤掉 hysteria2 槽位 —— hy2 是 UDP 直连, 不走 CF CDN, 无需轮换 IP
        // (xhttp-cdn-hy2 集群里只有 xhttp 槽位需要轮换 CF 优选 IP).
        $nodes = SsNode::whereIn('v2_name', NodeAddressService::cdnV2Names())
            ->orWhere('v2_cdn', 'cf')
            ->get()
            ->filter(function ($node) {
                return NodeAddressService::isCdnNode($node);
            });
        $this->info("待轮换 CDN 节点数: {$nodes->count()}");
        $this->info('');

        if ($nodes->isEmpty()) {
            $this->info('无 CDN 节点, 退出');
            return 0;
        }

        $rotated = 0;
        $unchanged = 0;

        foreach ($nodes as $node) {
            $current = $node->v2_cdn_ip ?: '';
            $next = NodeAddressService::pickNextCdnIp($node->id, $current);

            if ($next === '' || $next === $current) {
                $unchanged++;
                $this->line("  节点 #{$node->id} ({$node->name}): 保持 {$current} (无可切换 IP)");
                continue;
            }

            $this->line("  节点 #{$node->id} ({$node->name}): {$current} -> {$next}" . ($dryRun ? '  [DRY-RUN]' : ''));

            if (!$dryRun) {
                $node->v2_cdn_ip = $next;
                $node->save();
            }
            $rotated++;
        }

        $this->info('');
        $this->info("轮换完成: rotated={$rotated}, unchanged={$unchanged}, dry-run=" . ($dryRun ? 'YES' : 'NO'));

        Log::info("[{$tag}] complete", [
            'total' => $nodes->count(),
            'rotated' => $rotated,
            'unchanged' => $unchanged,
            'ip_count' => $ipCount,
            'dry_run' => $dryRun,
        ]);

        return 0;
    }
}
