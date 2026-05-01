<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Components\Helpers;
use App\Components\DNS\CloudflareProvider;
use App\Http\Models\Config;
use App\Http\Models\DnsRecord;
use App\Http\Models\SsNode;

class InitDnsRecords extends Command
{
    protected $signature = 'initDnsRecords';
    protected $description = 'Sync DNS records from Cloudflare: clean orphans, upsert matched A/AAAA records';

    /**
     * where: dns_records table + Cloudflare API
     * why: Keep local DNS state in sync with cloud truth. Clean stale records, capture new ones.
     * how: For each domain in the pool, fetch A/AAAA from CF, diff against local, reconcile.
     *
     * must: Only touch A and AAAA records. Never delete TXT, MX, CNAME, etc.
     */
    public function handle()
    {
        $sysConf = Helpers::systemConfig();

        // Build domain pool from node_domain_pool (dict or legacy array)
        $domains = [];
        if (!empty($sysConf['node_domain_pool'])) {
            $pool = json_decode($sysConf['node_domain_pool'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('node_domain_pool JSON parse error: ' . json_last_error_msg());
                return 1;
            }

            if (is_array($pool)) {
                $firstKey = array_key_first($pool);
                if ($firstKey !== null && is_string($firstKey) && is_array($pool[$firstKey])) {
                    // New dict format: {"domain.com": {"zone_id": "...", ...}}
                    $domains = $pool;
                } else {
                    // Legacy array format: ["domain.com", ...]
                    foreach (array_values(array_unique(array_filter($pool))) as $domain) {
                        if (is_string($domain) && $domain !== '') {
                            $domains[$domain] = [];
                        }
                    }
                }
            }
        }

        // Fallback to node_root_domain if pool is empty
        if (empty($domains) && !empty($sysConf['node_root_domain'])) {
            $domains[$sysConf['node_root_domain']] = [];
        }

        if (empty($domains)) {
            $this->error('No domains configured. Set node_domain_pool in config.');
            return 1;
        }

        $dnsProvider = app(CloudflareProvider::class);

        foreach ($domains as $domainName => $meta) {
            $zoneId = $meta['zone_id'] ?? null;
            $this->info("Processing domain: {$domainName}" . ($zoneId ? " (zone: {$zoneId})" : ''));

            // Fetch A and AAAA records from CF using zone_id from config
            $cfRecords = [];
            foreach (['A', 'AAAA'] as $type) {
                $records = $dnsProvider->listRecords($domainName, $type, $zoneId);
                if ($records === false) {
                    $this->warn("  Failed to fetch {$type} records from Cloudflare for {$domainName}");
                    continue;
                }
                foreach ($records as $r) {
                    $cfRecords[] = $r;
                }
            }

            $this->info("  Found " . count($cfRecords) . " A/AAAA records on Cloudflare");

            // Build a lookup: cf_record_id => cf record data
            $cfById = [];
            foreach ($cfRecords as $r) {
                $cfById[$r['id']] = $r;
            }

            // --- Phase 1: Clean orphaned local records ---
            // Only touch A and AAAA type records
            $localRecords = DnsRecord::where('root_domain', $domainName)
                ->whereIn('record_type', ['A', 'AAAA'])
                ->get();

            $orphansDeleted = 0;
            foreach ($localRecords as $local) {
                if ($local->cf_record_id && !isset($cfById[$local->cf_record_id])) {
                    $this->warn("  Deleting orphan: {$local->subdomain}.{$domainName} ({$local->record_type}) - CF record missing");
                    $local->delete();
                    $orphansDeleted++;
                }
            }
            $this->info("  Cleaned {$orphansDeleted} orphaned local records");

            // --- Phase 2: Upsert CF records that match nodes by subdomain ---
            // Match CF record FQDN against ss_node.server (e.g. "n156.ssmail.win")
            // This avoids ambiguity from shared IPs across clones.
            $upserted = 0;
            foreach ($cfRecords as $cfRecord) {
                $ip = $cfRecord['content'];
                $type = $cfRecord['type'];
                $fullName = $cfRecord['name'];

                // Find node by matching server field (= subdomain.root_domain)
                $node = SsNode::where('server', $fullName)->first();
                if (!$node) continue;

                // Parse subdomain from CF record name (e.g. "n156.ssmail.win" → "n156")
                $subdomain = $fullName;
                if (strpos($fullName, '.' . $domainName) !== false) {
                    $subdomain = str_replace('.' . $domainName, '', $fullName);
                }

                // Upsert into dns_records
                $existing = DnsRecord::where('cf_record_id', $cfRecord['id'])->first();
                if (!$existing) {
                    $existing = DnsRecord::where('root_domain', $domainName)
                        ->where('subdomain', $subdomain)
                        ->where('record_type', $type)
                        ->first();
                }

                if ($existing) {
                    $existing->node_id = $node->id;
                    $existing->root_domain = $domainName;
                    $existing->subdomain = $subdomain;
                    $existing->record_type = $type;
                    $existing->ip_addr = $ip;
                    $existing->cf_record_id = $cfRecord['id'];
                    $existing->cf_zone_id = $cfRecord['zone_id'] ?? $zoneId;
                    $existing->save();
                } else {
                    DnsRecord::create([
                        'node_id' => $node->id,
                        'root_domain' => $domainName,
                        'subdomain' => $subdomain,
                        'record_type' => $type,
                        'ip_addr' => $ip,
                        'cf_record_id' => $cfRecord['id'],
                        'cf_zone_id' => $cfRecord['zone_id'] ?? $zoneId,
                    ]);
                }
                $upserted++;
            }
            $this->info("  Upserted {$upserted} records linked to nodes by subdomain");
        }

        $this->info('DNS reconciliation complete.');
        return 0;
    }
}
