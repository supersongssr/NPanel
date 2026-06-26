<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Components\Helpers;
use App\Http\Models\DnsRecord;
use App\Http\Models\SsNode;
use App\Services\DnsRecordCleanupService;

/**
 * 定时删除超过指定天数无心跳节点的 DNS 解析记录
 *
 * 阈值优先级: 命令行 --days > config 表 dns_expire_days > DEFAULT_EXPIRE_DAYS (32).
 * 定时任务 (Console\Kernel 每日 04:10) 无命令行参数时读取 dns_expire_days 配置.
 *
 * 清理范围：
 * - Cloudflare 服务商的 DNS 记录（远端, 含独立 Token 的 CDN 域名）
 * - 本地 dns_records 表记录
 *
 * DNS 模块独立性:
 * - CDN 域名 (node_domain_pool 中 cdn:true + cf_token) 使用独立 Cloudflare Token,
 *   与全局 CLOUDFLARE_TOKEN 隔离 (防封号). 本命令按记录根域名解析对应 Token,
 *   否则用全局 Token 删 CDN 域名记录会因鉴权失败而残留 (永远释放不了).
 *
 * 安全约束：
 * - 只处理 A/AAAA 类型记录
 * - CF 删除成功或 404 才删本地，失败保留
 * - 支持 --dry-run 预览
 * - 分批 chunk 处理，避免内存溢出
 */
class AutoDeleteExpiredDns extends Command
{
    /**
     * Default expiry threshold in days (命令行 / 配置均未指定时回退值).
     */
    const DEFAULT_EXPIRE_DAYS = 32;

    /**
     * Chunk size for node queries.
     */
    const CHUNK_SIZE = 100;

    protected $signature = 'autoDeleteExpiredDns {--dry-run : 仅预览不删除} {--days= : 过期天数阈值 (留空则读配置 dns_expire_days, 仍无则默认 32)}';
    protected $description = '自动删除超过指定天数无心跳节点的DNS解析记录（含独立Token的CDN域名, Cloudflare远端）';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $tag = 'AutoDeleteExpiredDns';

        // 解析系统配置 + 域名池 (早于阈值计算: dns_expire_days 来自配置表)
        $sysConf = Helpers::systemConfig();
        $parsed = Helpers::parseDomainPool($sysConf);
        $domainPool = $parsed['domainPool'];

        // 阈值优先级: 命令行 --days > 配置 dns_expire_days > 默认值
        $days = $this->resolveExpireDays($sysConf);
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $this->info("=== {$tag} ===");
        $this->info("Cutoff: {$cutoff} ({$days} days)");
        $this->info("Dry-run: " . ($dryRun ? 'YES' : 'NO'));
        $this->info('');

        if (empty($domainPool)) {
            $this->warn('No domain pool configured, nothing to do.');
            return 0;
        }

        $cleanupService = new DnsRecordCleanupService();

        $stats = array(
            'nodes_scanned'    => 0,
            'records_deleted'  => 0,
            'records_failed'   => 0,
            'records_skipped'  => 0,
            'cf_deleted'       => 0,
            'cf_deleted_404'   => 0,
            'cf_failed'        => 0,
            'cdn_token_records'=> 0,
            'rate_limited'     => false,
        );

        // Query expired nodes in chunks
        SsNode::where(function ($query) use ($cutoff) {
            $query->where('heartbeat_at', '<', $cutoff)
                  ->orWhere(function ($q) use ($cutoff) {
                      $q->whereNull('heartbeat_at')
                        ->where('created_at', '<', $cutoff);
                  });
        })
        ->orderBy('id')
        ->chunk(self::CHUNK_SIZE, function ($nodes) use ($dryRun, $cutoff, $domainPool, $cleanupService, $tag, &$stats) {
            foreach ($nodes as $node) {
                // Double-check: re-verify the node is still expired (may have received heartbeat since query)
                $stillExpired = false;
                if ($node->heartbeat_at === null) {
                    $stillExpired = ($node->created_at !== null && $node->created_at < $cutoff);
                } else {
                    $stillExpired = ($node->heartbeat_at < $cutoff);
                }

                if (!$stillExpired) {
                    $this->line("  Node#{$node->id} [SKIP] heartbeat recovered, skipping");
                    continue;
                }

                $records = DnsRecord::where('node_id', $node->id)
                    ->whereIn('record_type', array('A', 'AAAA'))
                    ->get();

                if ($records->isEmpty()) {
                    continue;
                }

                $stats['nodes_scanned']++;
                $this->line("  Node#{$node->id} \"{$node->name}\" — {$records->count()} DNS record(s), heartbeat_at={$node->heartbeat_at}");

                foreach ($records as $record) {
                    // DNS 模块已独立: CDN 域名 (cdn:true + cf_token) 使用独立 Token,
                    // 必须按记录的根域名解析对应的 CloudflareProvider, 否则用全局 Token
                    // 删除 CDN 域名记录会因鉴权失败而永远残留.
                    $dnsProvider = DnsRecordCleanupService::resolveProvider($record->root_domain, $domainPool);
                    $poolMeta = isset($domainPool[$record->root_domain]) && is_array($domainPool[$record->root_domain])
                        ? $domainPool[$record->root_domain]
                        : array();
                    if (!empty($poolMeta['cdn']) && !empty($poolMeta['cf_token'])) {
                        $stats['cdn_token_records']++;
                    }
                    $result = $cleanupService->cleanupRecord($record, $dnsProvider, $domainPool, $dryRun, $tag);

                    switch ($result['action']) {
                        case 'will_delete':
                            $this->info("    [DRY-RUN] {$result['fqdn']} ({$record->record_type}) cf={$record->cf_record_id}");
                            $stats['records_deleted']++;
                            break;
                        case 'deleted':
                            $this->info("    [DELETED] {$result['fqdn']} ({$record->record_type})");
                            $stats['records_deleted']++;
                            $stats['cf_deleted']++;
                            if (!$dryRun) {
                                $record->delete();
                            }
                            break;
                        case 'deleted_404':
                            $this->info("    [DELETED-404] {$result['fqdn']} ({$record->record_type}) already gone on CF");
                            $stats['records_deleted']++;
                            $stats['cf_deleted_404']++;
                            if (!$dryRun) {
                                $record->delete();
                            }
                            break;
                        case 'failed_rate_limited':
                            $this->warn("    [RATE-LIMITED] {$result['fqdn']} — aborting batch");
                            $stats['records_failed']++;
                            $stats['cf_failed']++;
                            $stats['rate_limited'] = true;
                            // 退出整个 chunk 回调并返回 false, 让 Laravel chunk() 停止拉取后续分页.
                            // 注意: break 3 只能跳出 switch + 两层 foreach, 回调仍会正常返回 null,
                            // chunk() 会继续拉取下一页并再次请求 CF API, 导致限流失效.
                            return false;
                        case 'skipped_type':
                        case 'skipped_no_cf_id':
                        case 'skipped_no_zone':
                            $this->warn("    [SKIP] {$result['fqdn']} — {$result['message']}");
                            $stats['records_skipped']++;
                            break;
                        default:
                            // failed_curl, failed_error, etc.
                            $this->error("    [FAILED] {$result['fqdn']} — {$result['message']}");
                            $stats['records_failed']++;
                            $stats['cf_failed']++;
                            break;
                    }
                }
            }
        });

        $this->info('');
        $this->info("--- Summary ---");
        $this->info("Nodes scanned:   {$stats['nodes_scanned']}");
        $this->info("Records deleted: {$stats['records_deleted']} (CF ok: {$stats['cf_deleted']}, CF 404: {$stats['cf_deleted_404']})");
        $this->info("Records failed:  {$stats['records_failed']}");
        $this->info("Records skipped: {$stats['records_skipped']}");
        if ($stats['rate_limited']) {
            $this->warn('Rate limited by Cloudflare: batch aborted early, remaining nodes deferred to next run.');
        }
        if ($stats['cdn_token_records'] > 0) {
            $this->info("CDN-token records (独立Token删除): {$stats['cdn_token_records']}");
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] No changes were made.');
        }

        Log::info("{$tag} completed", $stats);

        return 0;
    }

    /**
     * 解析过期天数阈值.
     *
     * 优先级: 命令行 --days > config 表 dns_expire_days > DEFAULT_EXPIRE_DAYS.
     * 定时任务 (无命令行参数) 时 --days 为空, 回退到配置 dns_expire_days, 仍无则用默认值.
     * 在 config 表新增一行: name=dns_expire_days, value=整数 (天数) 即可自由调整阈值,
     * 无需改动代码.
     *
     * @param array $sysConf Helpers::systemConfig() 结果
     * @return int
     */
    protected function resolveExpireDays(array $sysConf)
    {
        $cliDays = (int) $this->option('days');
        if ($cliDays >= 1) {
            return $cliDays;
        }

        if (isset($sysConf['dns_expire_days'])) {
            $cfgDays = (int) $sysConf['dns_expire_days'];
            if ($cfgDays >= 1) {
                return $cfgDays;
            }
        }

        return self::DEFAULT_EXPIRE_DAYS;
    }
}
