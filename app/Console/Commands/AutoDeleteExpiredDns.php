<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Components\DNS\CloudflareProvider;
use App\Components\Helpers;
use App\Http\Models\DnsRecord;
use App\Http\Models\SsNode;
use App\Services\DnsRecordCleanupService;

/**
 * 定时删除超过30天无心跳节点的 DNS 解析记录
 *
 * 清理范围：
 * - Cloudflare 服务商的 DNS 记录（远端）
 * - 本地 dns_records 表记录
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
     * Default expiry threshold in days.
     */
    const DEFAULT_EXPIRE_DAYS = 30;

    /**
     * Chunk size for node queries.
     */
    const CHUNK_SIZE = 100;

    protected $signature = 'autoDeleteExpiredDns {--dry-run : 仅预览不删除} {--days=30 : 过期天数阈值}';
    protected $description = '自动删除超过指定天数无心跳节点的DNS解析记录（含Cloudflare远端）';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $days = (int) $this->option('days');
        if ($days < 1) {
            $days = self::DEFAULT_EXPIRE_DAYS;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $tag = 'AutoDeleteExpiredDns';

        $this->info("=== {$tag} ===");
        $this->info("Cutoff: {$cutoff} ({$days} days)");
        $this->info("Dry-run: " . ($dryRun ? 'YES' : 'NO'));
        $this->info('');

        // Parse domain pool once
        $sysConf = Helpers::systemConfig();
        $parsed = Helpers::parseDomainPool($sysConf);
        $domainPool = $parsed['domainPool'];

        if (empty($domainPool)) {
            $this->warn('No domain pool configured, nothing to do.');
            return 0;
        }

        $dnsProvider = app(CloudflareProvider::class);
        $cleanupService = new DnsRecordCleanupService();

        $stats = array(
            'nodes_scanned'    => 0,
            'records_deleted'  => 0,
            'records_failed'   => 0,
            'records_skipped'  => 0,
            'cf_deleted'       => 0,
            'cf_deleted_404'   => 0,
            'cf_failed'        => 0,
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
        ->chunk(self::CHUNK_SIZE, function ($nodes) use ($dryRun, $cutoff, $dnsProvider, $domainPool, $cleanupService, $tag, &$stats) {
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
                            break 3; // Break out of all loops
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

        if ($dryRun) {
            $this->warn('[DRY-RUN] No changes were made.');
        }

        Log::info("{$tag} completed", $stats);

        return 0;
    }
}
