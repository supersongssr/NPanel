<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Components\Helpers;
use App\Components\DNS\CloudflareProvider;
use App\Http\Models\DnsRecord;
use App\Http\Models\SsNode;

class InitDnsRecords extends Command
{
    protected $signature = 'initDnsRecords {--execute : 确认执行DNS同步}';
    protected $description = 'Sync DNS records from Cloudflare: clean orphans, upsert matched A/AAAA records';

    public function handle()
    {
        $sysConf = Helpers::systemConfig();

        // Build domain pool
        $domains = [];
        $parsed = Helpers::parseDomainPool($sysConf);
        $domains = $parsed['domainPool'];

        // Fallback to node_root_domain if pool is empty
        if (empty($domains) && !empty($sysConf['node_root_domain'])) {
            $domains[$sysConf['node_root_domain']] = [];
        }
        // Also set primary domain from parseDomainPool if no root_domain fallback
        if (empty($domains) && !empty($parsed['primaryDomain'])) {
            $domains[$parsed['primaryDomain']] = [];
        }

        if (empty($domains)) {
            $this->error('No domains configured.');
            return 1;
        }

        $dnsProvider = app(CloudflareProvider::class);
        $configUpdated = false;

        foreach ($domains as $domainName => $meta) {
            $zoneId = $meta['zone_id'] ?? null;

            // --- Auto-Discovery Logic ---
            if (empty($zoneId) || strpos($zoneId, '8d8f') !== false) {
                $this->warn("  Zone ID missing or suspicious for {$domainName}. Attempting auto-discovery...");
                $discoveredId = $dnsProvider->getZoneIdByName($domainName);
                if ($discoveredId) {
                    $this->info("  Discovered Zone ID for {$domainName}: {$discoveredId}");
                    $zoneId = $discoveredId;
                    $domains[$domainName]['zone_id'] = $discoveredId;
                    $configUpdated = true;
                } else {
                    $this->error("  Failed to discover Zone ID for {$domainName}. Skipping.");
                    continue;
                }
            }

            $this->info("Processing domain: {$domainName} (zone: {$zoneId})");

            $cfRecords = [];
            foreach (['A', 'AAAA'] as $type) {
                $records = $dnsProvider->listRecords($domainName, $type, $zoneId);

                // Retry discovery on 403
                if ($records === false && !empty($zoneId)) {
                    $this->warn("  Access denied with Zone ID {$zoneId}. Retrying with fresh discovery...");
                    $discoveredId = $dnsProvider->getZoneIdByName($domainName);
                    if ($discoveredId && $discoveredId !== $zoneId) {
                        $this->info("  Discovered NEW Zone ID: {$discoveredId}");
                        $zoneId = $discoveredId;
                        $domains[$domainName]['zone_id'] = $discoveredId;
                        $configUpdated = true;
                        $records = $dnsProvider->listRecords($domainName, $type, $zoneId);
                    }
                }

                if ($records === false) {
                    $this->warn("  Failed to fetch {$type} records for {$domainName}");
                    continue;
                }
                foreach ($records as $r) {
                    $cfRecords[] = $r;
                }
            }

            $this->info("  Found " . count($cfRecords) . " A/AAAA records on Cloudflare");

            $cfById = [];
            foreach ($cfRecords as $r) {
                if (is_array($r) && isset($r['id'])) {
                    $cfById[$r['id']] = $r;
                }
            }

            // Phase 1: Clean orphans
            $localRecords = DnsRecord::where('root_domain', $domainName)
                ->whereIn('record_type', ['A', 'AAAA'])
                ->get();

            $orphansDeleted = 0;
            foreach ($localRecords as $local) {
                if ($local->cf_record_id && !isset($cfById[$local->cf_record_id])) {
                    $this->warn("  Deleting orphan: {$local->subdomain}.{$domainName} ({$local->record_type})");
                    $local->delete();
                    $orphansDeleted++;
                }
            }
            $this->info("  Cleaned {$orphansDeleted} orphaned local records");

            // Phase 2: Upsert
            $upserted = 0;
            $linked = 0;
            $orphan = 0;
            foreach ($cfRecords as $cfRecord) {
                $ip = $cfRecord['content'] ?? null;
                $type = $cfRecord['type'] ?? null;
                $fullName = $cfRecord['name'] ?? null;
                $cfId = $cfRecord['id'] ?? null;

                if (!$ip || !$type || !$fullName || !$cfId) continue;

                $node = SsNode::where('server', $fullName)->first();
                $nodeId = $node ? $node->id : 0;

                $subdomain = $fullName;
                if (strpos($fullName, '.' . $domainName) !== false) {
                    $subdomain = str_replace('.' . $domainName, '', $fullName);
                }

                $existing = DnsRecord::where('cf_record_id', $cfId)->first();
                if (!$existing) {
                    $existing = DnsRecord::where('root_domain', $domainName)
                        ->where('subdomain', $subdomain)
                        ->where('record_type', $type)
                        ->first();
                }

                if ($existing) {
                    $existing->node_id = $nodeId;
                    $existing->root_domain = $domainName;
                    $existing->subdomain = $subdomain;
                    $existing->record_type = $type;
                    $existing->ip_addr = $ip;
                    $existing->cf_record_id = $cfId;
                    $existing->save();
                } else {
                    DnsRecord::create([
                        'node_id' => $nodeId,
                        'root_domain' => $domainName,
                        'subdomain' => $subdomain,
                        'record_type' => $type,
                        'ip_addr' => $ip,
                        'cf_record_id' => $cfId,
                    ]);
                }
                $upserted++;
                if ($nodeId > 0) { $linked++; } else { $orphan++; }
            }
            $this->info("  Upserted {$upserted} records (linked: {$linked}, orphan: {$orphan})");
        }

        if ($configUpdated) {
            // node_domain_pool 已迁移至 config.default.php / .config.php, 代码不再写 DB.
            // 仅输出发现的 Zone ID, 由管理员手工更新 .config.php.
            $this->info('Discovered Zone IDs (node_domain_pool 已迁移至配置文件, 请手工更新 .config.php):');
            foreach ($domains as $domainName => $meta) {
                if (!empty($meta['zone_id'])) {
                    $this->info("  {$domainName} => zone_id: {$meta['zone_id']}");
                }
            }
        }

        $this->info('DNS reconciliation complete.');
        return 0;
    }
}
