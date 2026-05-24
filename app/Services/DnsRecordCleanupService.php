<?php

namespace App\Services;

use App\Components\DNS\CloudflareProvider;
use App\Components\Helpers;
use App\Http\Models\DnsRecord;
use Illuminate\Support\Facades\Log;

/**
 * Shared DNS record cleanup logic.
 *
 * Used by:
 * - AutoDeleteExpiredDns scheduled command
 * - NodeApiController (node recycling paths)
 *
 * Contract: local DnsRecord is only deleted after Cloudflare confirms
 * deletion (success) or confirms the record no longer exists (404).
 * On any other failure the local record is preserved for retry.
 */
class DnsRecordCleanupService
{
    /**
     * Microseconds to sleep after each Cloudflare API call.
     *
     * @var int
     */
    private static $apiSleepUs = 200000; // 0.2s

    /**
     * Delete a single DnsRecord both from Cloudflare and locally.
     *
     * @param DnsRecord        $record
     * @param CloudflareProvider $dnsProvider
     * @param array            $domainPool  Normalized domain pool from Helpers::parseDomainPool()
     * @param bool             $dryRun
     * @param string           $context     Log tag, e.g. 'AutoDeleteExpiredDns' or 'NodeApi'
     *
     * @return array [
     *     'action'  => string  deleted|deleted_404|skipped_no_cf_id|skipped_no_zone|failed_cf|failed_rate_limited|failed_error
     *     'success' => bool    Whether the local record can be safely removed
     *     'fqdn'    => string
     *     'message' => string
     * ]
     */
    public function cleanupRecord(DnsRecord $record, CloudflareProvider $dnsProvider, array $domainPool, $dryRun = false, $context = 'DnsCleanup')
    {
        $fqdn = $record->subdomain . '.' . $record->root_domain;

        // Only process A/AAAA records generated for nodes
        if (!in_array($record->record_type, array('A', 'AAAA'), true)) {
            return array(
                'action'  => 'skipped_type',
                'success' => false,
                'fqdn'    => $fqdn,
                'message' => 'Record type ' . $record->record_type . ' not in scope',
            );
        }

        // No cf_record_id — cannot target CF record for deletion
        if (empty($record->cf_record_id)) {
            Log::warning("[{$context}] No cf_record_id for DnsRecord#{$record->id} ({$fqdn}), keeping local record");
            return array(
                'action'  => 'skipped_no_cf_id',
                'success' => false,
                'fqdn'    => $fqdn,
                'message' => 'Missing cf_record_id, local record preserved',
            );
        }

        // Resolve zone_id from domain pool
        $poolMeta = isset($domainPool[$record->root_domain]) ? $domainPool[$record->root_domain] : null;
        $zoneId = null;
        if (is_array($poolMeta) && !empty($poolMeta['zone_id'])) {
            $zoneId = $poolMeta['zone_id'];
        }

        if (empty($zoneId)) {
            Log::warning("[{$context}] No zone_id for root_domain={$record->root_domain}, DnsRecord#{$record->id} ({$fqdn})");
            return array(
                'action'  => 'skipped_no_zone',
                'success' => false,
                'fqdn'    => $fqdn,
                'message' => 'Missing zone_id, local record preserved',
            );
        }

        if ($dryRun) {
            return array(
                'action'  => 'will_delete',
                'success' => true,
                'fqdn'    => $fqdn,
                'message' => "[DRY-RUN] Would delete {$fqdn} ({$record->record_type}) cf_id={$record->cf_record_id}",
            );
        }

        // Call Cloudflare structured delete
        $status = $dnsProvider->deleteRecordWithStatus($record->cf_record_id, $zoneId);

        // Sleep after every CF API call (including failures) to respect rate limits
        usleep(self::$apiSleepUs);

        if ($status['success']) {
            // CF confirmed deleted or 404 (already gone) — safe to remove local
            $action = !empty($status['not_found']) ? 'deleted_404' : 'deleted';
            Log::info("[{$context}] Deleted CF record for {$fqdn}", array(
                'dns_record_id' => $record->id,
                'node_id'       => $record->node_id,
                'cf_record_id'  => $record->cf_record_id,
                'zone_id'       => $zoneId,
                'action'        => $action,
                'http_code'     => $status['http_code'],
            ));
            return array(
                'action'  => $action,
                'success' => true,
                'fqdn'    => $fqdn,
                'message' => "CF deleted ({$action})",
            );
        }

        // Failure — preserve local record for retry
        $action = 'failed_error';
        if (!empty($status['rate_limited'])) {
            $action = 'failed_rate_limited';
        } elseif (!empty($status['curl_error'])) {
            $action = 'failed_curl';
        }

        Log::warning("[{$context}] CF delete failed for {$fqdn}", array(
            'dns_record_id' => $record->id,
            'node_id'       => $record->node_id,
            'cf_record_id'  => $record->cf_record_id,
            'zone_id'       => $zoneId,
            'action'        => $action,
            'http_code'     => $status['http_code'],
            'error'         => $status['error'],
        ));

        return array(
            'action'  => $action,
            'success' => false,
            'fqdn'    => $fqdn,
            'message' => "CF delete failed: {$status['error']}",
        );
    }

    /**
     * Cleanup all DNS records for a given node ID.
     *
     * @param int               $nodeId
     * @param CloudflareProvider $dnsProvider
     * @param array             $domainPool
     * @param bool              $dryRun
     * @param string            $context
     *
     * @return array ['deleted' => int, 'failed' => int, 'skipped' => int]
     */
    public function cleanupNodeRecords($nodeId, CloudflareProvider $dnsProvider, array $domainPool, $dryRun = false, $context = 'DnsCleanup')
    {
        $stats = array('deleted' => 0, 'failed' => 0, 'skipped' => 0);

        $records = DnsRecord::where('node_id', $nodeId)
            ->whereIn('record_type', array('A', 'AAAA'))
            ->get();

        foreach ($records as $record) {
            $result = $this->cleanupRecord($record, $dnsProvider, $domainPool, $dryRun, $context);

            if ($result['success']) {
                if (!$dryRun) {
                    $record->delete();
                }
                $stats['deleted']++;
            } elseif (substr($result['action'], 0, 7) === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }
}
