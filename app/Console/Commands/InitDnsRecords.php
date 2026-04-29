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

        // Build domain pool: primary + JSON pool
        $domains = [];
        $primary = $sysConf['node_root_domain'] ?? null;
        if ($primary) $domains[] = $primary;

        if (!empty($sysConf['node_domain_pool'])) {
            $pool = json_decode($sysConf['node_domain_pool'], true);
            if (is_array($pool)) {
                $domains = array_unique(array_merge($domains, $pool));
            }
        }

        if (empty($domains)) {
            $this->error('No domains configured. Set node_root_domain and/or node_domain_pool in config.');
            return 1;
        }

        $dnsProvider = app(CloudflareProvider::class);

        foreach ($domains as $domain) {
            $this->info("Processing domain: {$domain}");

            // Fetch A and AAAA records from CF
            $cfRecords = [];
            foreach (['A', 'AAAA'] as $type) {
                $records = $dnsProvider->listRecords($domain, $type);
                if ($records === false) {
                    $this->warn("  Failed to fetch {$type} records from Cloudflare for {$domain}");
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
            $localRecords = DnsRecord::where('root_domain', $domain)
                ->whereIn('record_type', ['A', 'AAAA'])
                ->get();

            $orphansDeleted = 0;
            foreach ($localRecords as $local) {
                if ($local->cf_record_id && !isset($cfById[$local->cf_record_id])) {
                    $this->warn("  Deleting orphan: {$local->subdomain}.{$domain} ({$local->record_type}) - CF record missing");
                    $local->delete();
                    $orphansDeleted++;
                }
            }
            $this->info("  Cleaned {$orphansDeleted} orphaned local records");

            // --- Phase 2: Upsert CF records that match known node IPs ---
            $upserted = 0;
            foreach ($cfRecords as $cfRecord) {
                $ip = $cfRecord['content'];
                $type = $cfRecord['type'];

                // Find a node that has this IP
                $node = null;
                if ($type === 'A') {
                    $node = SsNode::where('ip', $ip)->first();
                } elseif ($type === 'AAAA') {
                    $node = SsNode::where('ipv6', $ip)->first();
                }

                if (!$node) continue;

                // Parse subdomain from CF record name (e.g. "node123.example.com" → "node123")
                $fullName = $cfRecord['name'];
                $subdomain = $fullName;
                if (strpos($fullName, '.' . $domain) !== false) {
                    $subdomain = str_replace('.' . $domain, '', $fullName);
                }

                // Upsert into dns_records
                $existing = DnsRecord::where('cf_record_id', $cfRecord['id'])->first();
                if (!$existing) {
                    $existing = DnsRecord::where('root_domain', $domain)
                        ->where('subdomain', $subdomain)
                        ->where('record_type', $type)
                        ->first();
                }

                if ($existing) {
                    $existing->node_id = $node->id;
                    $existing->root_domain = $domain;
                    $existing->subdomain = $subdomain;
                    $existing->record_type = $type;
                    $existing->ip_addr = $ip;
                    $existing->cf_record_id = $cfRecord['id'];
                    $existing->cf_zone_id = $cfRecord['zone_id'] ?? null;
                    $existing->save();
                } else {
                    DnsRecord::create([
                        'node_id' => $node->id,
                        'root_domain' => $domain,
                        'subdomain' => $subdomain,
                        'record_type' => $type,
                        'ip_addr' => $ip,
                        'cf_record_id' => $cfRecord['id'],
                        'cf_zone_id' => $cfRecord['zone_id'] ?? null,
                    ]);
                }
                $upserted++;
            }
            $this->info("  Upserted {$upserted} records linked to nodes");
        }

        $this->info('DNS reconciliation complete.');
        return 0;
    }
}
