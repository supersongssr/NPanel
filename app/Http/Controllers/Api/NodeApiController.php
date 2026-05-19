<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Models\SsNode;
use App\Http\Models\DnsRecord;
use App\Components\Helpers;

class NodeApiController extends Controller
{
    const DEFAULT_RECORDS_LIMIT = 180;

    const CDN_REQUIRED_NETS = ['ws', 'grpc'];

    public function __construct()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private function parseDomainPool($sysConf)
    {
        $domainPool = [];
        $raw = $sysConf['node_domain_pool'] ?? '';

        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('node_domain_pool JSON parse error', ['error' => json_last_error_msg()]);
                $decoded = null;
            }

            if (is_array($decoded)) {
                $firstKey = array_key_first($decoded);
                if ($firstKey !== null && is_string($firstKey) && is_array($decoded[$firstKey])) {
                    $domainPool = $decoded;
                } else {
                    foreach (array_values(array_unique(array_filter($decoded))) as $domain) {
                        if (is_string($domain) && $domain !== '') {
                            $domainPool[$domain] = [];
                        }
                    }
                }
            }
        }

        $primaryDomain = array_key_first($domainPool) ?: ($sysConf['node_root_domain'] ?? 'example.com');

        return ['domainPool' => $domainPool, 'primaryDomain' => $primaryDomain];
    }

    private function getDomainLimit(array $meta)
    {
        return isset($meta['records_limit']) ? (int)$meta['records_limit'] : self::DEFAULT_RECORDS_LIMIT;
    }

    private function getDomainZoneId(array $meta, $domain = 'unknown')
    {
        $zoneId = $meta['zone_id'] ?? null;
        if (!$zoneId) {
            Log::error("[Node API] 未能从配置池中找到域名 {$domain} 的 Zone ID");
        }
        return $zoneId;
    }

    private function isNetCdnRequired($v2Net)
    {
        return in_array($v2Net, self::CDN_REQUIRED_NETS);
    }

    public function applyId(Request $request)
    {
        $nodeIp = $request->input('node_ip');
        $nodeIpv6 = $request->input('node_ipv6');

        $cutoff = date('Y-m-d H:i:s', strtotime('-32 days'));
        $node = SsNode::where(function($query) use ($cutoff) {
                $query->where('heartbeat_at', '<', $cutoff)
                      ->orWhere(function($q) use ($cutoff) {
                          $q->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
                      });
            })
            ->orderBy('heartbeat_at', 'desc')
            ->first();

        $recycled = false;
        if ($node) {
            $recycled = true;
            DnsRecord::where('node_id', $node->id)->delete();
        } else {
            $node = new SsNode();
        }

        $node->name = 'New Node ' . ($nodeIp ?: $nodeIpv6);
        $node->ip = $nodeIp ?: '';
        $node->ipv6 = $nodeIpv6 ?: '';
        $node->status = 0;
        $node->is_clone = 0;
        $node->save();

        Log::info('[Node API] ID 分配成功', [
            'ip' => $request->ip(),
            'node_id' => $node->id,
            'assigned_ip' => $node->ip,
            'assigned_ipv6' => $node->ipv6,
            'recycled' => $recycled
        ]);

        return response()->json(['node_id' => $node->id]);
    }

    public function register(Request $request)
    {
        $nodeId = $request->input('node_id');

        $node = SsNode::find($nodeId);
        if (!$node) {
            return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);
        }

        $sysConf = Helpers::systemConfig();

        $presets = json_decode($sysConf['node_protocol_presets'] ?? '{}', true);
        $thresholdMb = $presets['threshold_mb'] ?? 2048;
        $highProto = $presets['high'] ?? 'xhttp-hy2-ws-grpc';
        $lowProto = $presets['low'] ?? 'vision-hy2-ws-grpc';

        $nodeMemory = (float)$request->input('node_memory', $request->input('memory', 0));
        $thresholdGb = $thresholdMb / 1024;

        $v2Name = $request->input('v2_name');
        if (!$v2Name) {
            $v2Name = ($nodeMemory > $thresholdGb) ? $highProto : $lowProto;
        }

        $rootDomain = $this->resolveDomainAffinity($request->input('root_domain'), $sysConf);

        $nodeCost = (float)$request->input('node_cost', 0);
        $clientLevel = $request->input('node_level');
        $mainLevel = $clientLevel !== null ? (int)$clientLevel : max(1, (int)floor($nodeCost));

        // --- IP Mutual Exclusion ---
        // apply_id already stamped the node with its ip/ipv6 fate.
        // If apply_id gave IPv4 → only accept node_ip, null out ipv6.
        // If apply_id gave IPv6 → only accept node_ipv6, null out ip.
        $fateHasIpv4 = !empty($node->ip);
        $fateHasIpv6 = !empty($node->ipv6);

        $reportedIp = $request->input('node_ip');
        $reportedIpv6 = $request->input('node_ipv6');

        if ($fateHasIpv4 && !$fateHasIpv6) {
            $node->ip = $reportedIp ?: $node->ip;
            $node->ipv6 = null;
            Log::debug("[Node API] register: IPv4-fate node {$nodeId}, IPv6 nulled", [
                'node_id' => $nodeId, 'ip' => $node->ip, 'reported_ipv6_discarded' => $reportedIpv6
            ]);
        } elseif ($fateHasIpv6 && !$fateHasIpv4) {
            $node->ipv6 = $reportedIpv6 ?: $node->ipv6;
            $node->ip = null;
            Log::debug("[Node API] register: IPv6-fate node {$nodeId}, IPv4 nulled", [
                'node_id' => $nodeId, 'ipv6' => $node->ipv6, 'reported_ip_discarded' => $reportedIp
            ]);
        } else {
            $node->ip = $reportedIp ?: $node->ip;
            $node->ipv6 = $reportedIpv6 ?: $node->ipv6;
        }

        // --- Standardized naming: {CountryCode}-{City} ---
        $countryCode = strtoupper($request->input('node_country_code', ''));
        $city = $request->input('node_city', '');
        if ($countryCode && $city) {
            $node->name = $countryCode . '-' . $city;
        } elseif ($countryCode) {
            $node->name = $countryCode;
        }

        $node->v2_name = $v2Name;
        $node->node_rxtx = $request->input('node_rxtx', $request->input('node_rxtx_mode', $request->input('billing_mode', 'tx')));
        $node->node_cpu = $request->input('node_cpu', $request->input('cpu'));
        $node->node_memory = $nodeMemory;
        $node->node_disk = $request->input('node_disk', $request->input('disk'));
        $node->bandwidth = (int)$request->input('node_bandwidth', $request->input('bandwidth', 100));

        // --- Collect individual unlock_ parameters ---
        $unlockServices = [
            'netflix', 'disney', 'chatgpt', 'claude',
            'tiktok', 'bilibili', 'iqiyi', 'bahamut', 'mewatch',
            'bing', 'google_scholar', 'notebooklm'
        ];
        $unlockData = [];
        foreach ($unlockServices as $service) {
            $val = $request->input('unlock_' . $service);
            if ($val !== null && $val !== '') {
                $unlockData[$service] = $val;
            }
        }
        $node->node_unlock = urldecode(http_build_query($unlockData));

        $node->info = $request->input('node_info', '');
        $node->level = $mainLevel;
        $node->node_group = $request->input('node_group', 2);
        $node->node_cost = $nodeCost;
        $node->traffic_limit = $request->input('node_traffic_limit', 1000) * 1024 * 1024 * 1024;
        $node->reset_day = (int)$request->input('node_traffic_resetday', 1);
        $node->sort = $request->input('node_sort', 0);
        $node->traffic_rate = $request->input('node_traffic_rate', 1.0);
        $node->country_code = strtolower($request->input('node_country_code', 'un'));
        $node->node_country = $request->input('node_country');
        $node->node_city = $request->input('node_city');
        $node->status = 1;
        $node->save();

        // --- Fission matrix: build protocol × IP slots ---
        $protocols = $this->expandProtocols($v2Name);

        $ips = [];
        if ($node->ip) $ips[] = ['type' => 'ipv4', 'addr' => $node->ip];
        if ($node->ipv6) $ips[] = ['type' => 'ipv6', 'addr' => $node->ipv6];

        $slots = [];
        foreach ($ips as $ipInfo) {
            foreach ($protocols as $protocol) {
                $slots[] = ['protocol' => $protocol, 'ip_type' => $ipInfo['type'], 'addr' => $ipInfo['addr']];
            }
        }

        $totalTarget = count($slots);
        if ($totalTarget === 0) {
            $node->server = 'n' . $node->id . '.' . $rootDomain;
            $node->node_ids = (string)$node->id;
            $node->save();
            return response()->json([
                'status' => 'success',
                'node_id' => $node->id,
                'clone_node_ids' => [],
                'root_domain' => $rootDomain,
                'v2_name' => $v2Name,
            ]);
        }

        $existingClones = SsNode::where('is_clone', $nodeId)->get()->values();
        $cutoff = date('Y-m-d H:i:s', strtotime('-32 days'));

        $deadNodes = SsNode::where(function($query) use ($cutoff) {
                $query->where('heartbeat_at', '<', $cutoff)
                      ->orWhere(function($q) use ($cutoff) {
                          $q->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
                      });
            })
            ->where('id', '!=', $nodeId)
            ->where('is_clone', '!=', $nodeId)
            ->orderBy('heartbeat_at', 'desc')
            ->get();
        $deadIdx = 0;

        $cloneIds = [];

        $mainSlot = array_shift($slots);
        $mainIsIpv6 = $mainSlot['ip_type'] === 'ipv6';
        $mainPrefix = $mainIsIpv6 ? 'ipv6n' : 'n';
        $node->server = $mainPrefix . $node->id . '.' . $rootDomain;
        // Enforce single-stack on main node: zero out the opposite stack
        if ($mainIsIpv6) {
            $node->ip = null;
            $node->ipv6 = $mainSlot['addr'];
        } else {
            $node->ip = $mainSlot['addr'];
            $node->ipv6 = null;
        }
        $this->applyV2Preset($node, $mainSlot['protocol'], $rootDomain, $mainIsIpv6, $v2Name);
        $node->save();
        $allNodeIds = [$node->id];

        foreach ($slots as $i => $slot) {
            $isIpv6 = $slot['ip_type'] === 'ipv6';

            if ($i < $existingClones->count()) {
                $clone = $existingClones[$i];
                $clone->name = $node->name;
                $clone->v2_name = $v2Name;
                $clone->node_rxtx = $node->node_rxtx;
                $clone->ip = $isIpv6 ? '' : $slot['addr'];
                $clone->ipv6 = $isIpv6 ? $slot['addr'] : '';
                $clone->level = rand($mainLevel, min(5, $mainLevel + 2));
                $clone->node_group = $node->node_group;
                $clone->traffic_rate = $node->traffic_rate;
                $clone->status = 1;
                $clone->server = ($isIpv6 ? 'ipv6n' : 'n') . $clone->id . '.' . $rootDomain;
                $this->applyV2Preset($clone, $slot['protocol'], $rootDomain, $isIpv6, $v2Name);
                $clone->save();
            } else {
                $clone = null;
                if ($deadIdx < $deadNodes->count()) {
                    $clone = $deadNodes[$deadIdx++];
                    DnsRecord::where('node_id', $clone->id)->delete();
                } else {
                    $clone = new SsNode();
                }

                $clone->name = $node->name;
                $clone->v2_name = $v2Name;
                $clone->is_clone = $nodeId;
                $clone->node_rxtx = $node->node_rxtx;
                $clone->ip = $isIpv6 ? '' : $slot['addr'];
                $clone->ipv6 = $isIpv6 ? $slot['addr'] : '';
                $clone->level = rand($mainLevel, min(5, $mainLevel + 2));
                $clone->node_group = $node->node_group;
                $clone->traffic_rate = $node->traffic_rate;
                $clone->status = 1;
                $clone->save();

                $clone->server = ($isIpv6 ? 'ipv6n' : 'n') . $clone->id . '.' . $rootDomain;
                $this->applyV2Preset($clone, $slot['protocol'], $rootDomain, $isIpv6, $v2Name);
                $clone->save();
            }

            $cloneIds[] = $clone->id;
            $allNodeIds[] = $clone->id;
        }

        // Recycle excess old clones
        if ($existingClones->count() > count($slots)) {
            foreach ($existingClones->slice(count($slots)) as $excess) {
                $excess->is_clone = 0;
                $excess->status = 0;
                $excess->save();
                DnsRecord::where('node_id', $excess->id)->delete();
            }
        }

        $nodeIdsStr = implode(',', $allNodeIds);
        $node->node_ids = $nodeIdsStr;
        $node->save();

        Log::info('[Node API] 节点注册成功', [
            'node_id' => $node->id,
            'name' => $node->name,
            'protocol_group' => $v2Name,
            'domain_decision' => $rootDomain,
            'node_ids' => $allNodeIds,
            'total_nodes_generated' => count($allNodeIds),
            'deleted_count' => $existingClones->count() > count($slots) ? $existingClones->count() - count($slots) : 0,
            'ip' => $node->ip,
            'ipv6' => $node->ipv6,
        ]);

        return response()->json([
            'status' => 'success',
            'node_id' => $node->id,
            'clone_node_ids' => $cloneIds,
            'node_ids' => $nodeIdsStr,
            'root_domain' => $rootDomain,
            'v2_name' => $v2Name,
        ]);
    }

    const V2_PRESETS = [
        'vision' => [
            'type' => 3,
            'v2_net' => 'tcp',
            'v2_tls' => 1,
            'v2_port' => 443,
            'v2_flow' => 'xtls-rprx-vision',
            'v2_fp' => 'random',
            'v2_method' => 'none',
            'v2_encryption' => 'none',
            'v2_alter_id' => 0,
            'v2_type' => 'none',
        ],
        'xhttp' => [
            'type' => 3,
            'v2_net' => 'xhttp',
            'v2_tls' => 1,
            'v2_port' => 443,
            'v2_method' => 'none',
            'v2_encryption' => 'none',
            'v2_alter_id' => 0,
            'v2_type' => 'none',
            'v2_mode' => 'auto',
            'v2_alpn' => 'h2,http/1.1',
        ],
        'ws' => [
            'type' => 2,
            'v2_net' => 'ws',
            'v2_tls' => 1,
            'v2_port' => 443,
            'v2_method' => 'auto',
            'v2_encryption' => 'none',
            'v2_alter_id' => 0,
            'v2_type' => 'none',
        ],
        'grpc' => [
            'type' => 3,
            'v2_net' => 'grpc',
            'v2_tls' => 1,
            'v2_port' => 443,
            'v2_method' => 'none',
            'v2_encryption' => 'none',
            'v2_alter_id' => 0,
            'v2_type' => 'none',
            'v2_mode' => 'multi',
            'v2_alpn' => 'h2',
        ],
        'hy2' => [
            'type' => 5,
            'v2_net' => 'hysteria2',
            'v2_tls' => 1,
            'v2_port' => 443,
            'v2_method' => 'none',
            'v2_encryption' => 'none',
            'v2_alter_id' => 0,
            'v2_type' => 'none',
            'v2_alpn' => 'h3',
        ],
    ];

    private function expandProtocols($v2Name)
    {
        return explode('-', $v2Name);
    }

    private function applyV2Preset($node, $protocol, $rootDomain, $isIpv6, $modeName = '')
    {
        $preset = self::V2_PRESETS[$protocol] ?? null;
        if (!$preset) {
            Log::warning('[Node API] 无 v2 预设配置', ['protocol' => $protocol]);
            return;
        }

        foreach ($preset as $field => $value) {
            $node->{$field} = $value;
        }

        // Port override: in vision-hy2-ws-grpc mode, grpc must use 2053 to avoid port conflict
        $expandedMode = $this->expandProtocols($modeName);
        if ($protocol === 'grpc' && in_array('vision', $expandedMode)) {
            $node->v2_port = 2053;
        }

        $node->v2_host = $node->server;
        $node->v2_sni = $node->server;

        if ($protocol === 'ws') {
            $node->v2_path = 'srp-ws';
        }
        if ($protocol === 'grpc') {
            $node->v2_path = 'srp-grpc';
            $node->v2_servicename = 'srp-grpc';
        }
        if ($protocol === 'xhttp') {
            $node->v2_path = 'srp-xhttp';
        }
    }

    private function v2NetToInboundTag($v2Net)
    {
        static $map = [
            'xhttp' => 'proxy-xhttp',
            'hysteria2' => 'proxy-hy2',
            'ws' => 'proxy-ws',
            'grpc' => 'proxy-grpc',
            'tcp' => 'proxy-vision',
        ];
        return $map[$v2Net] ?? 'proxy-' . $v2Net;
    }

    private function resolveDomainAffinity($reportedDomain, $sysConf)
    {
        $parsed = $this->parseDomainPool($sysConf);
        $domainPool = $parsed['domainPool'];
        $primaryDomain = $parsed['primaryDomain'];

        if ($reportedDomain) {
            $limit = $this->getDomainLimit($domainPool[$reportedDomain] ?? []);
            $count = DnsRecord::where('root_domain', $reportedDomain)->count();
            if ($count < $limit) {
                return $reportedDomain;
            }
        }

        foreach ($domainPool as $domainName => $meta) {
            $limit = $this->getDomainLimit($meta);
            $count = DnsRecord::where('root_domain', $domainName)->count();
            if ($count < $limit) {
                return $domainName;
            }
        }

        return $primaryDomain;
    }

    public function resolveDns(Request $request)
    {
        set_time_limit(120);
        $startTime = microtime(true);

        $mainNodeId = $request->input('node_id');
        $force = (bool)$request->input('force', false);

        if (!$mainNodeId || !is_numeric($mainNodeId)) {
            Log::error("[Node API] resolveDns: invalid node_id", ['node_id' => $mainNodeId]);
            return response()->json(['status' => 'error', 'message' => 'Invalid node_id'], 400);
        }

        $mainNode = SsNode::find($mainNodeId);
        if (!$mainNode) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);

        $nodeIdsRaw = $mainNode->node_ids ?: (string)$mainNodeId;
        $nodeIds = array_map('intval', array_filter(explode(',', $nodeIdsRaw), 'strlen'));
        $nodeIds = array_unique($nodeIds);

        if (empty($nodeIds)) {
            Log::error("[Node API] resolveDns: node_ids is empty after parsing", [
                'node_id' => $mainNodeId, 'node_ids_raw' => $nodeIdsRaw
            ]);
            return response()->json(['status' => 'error', 'message' => 'No node_ids found'], 400);
        }

        $dnsProvider = app(\App\Components\DNS\CloudflareProvider::class);
        $sysConf = Helpers::systemConfig();
        $parsedPool = $this->parseDomainPool($sysConf);

        Log::debug("[Node API] resolveDns start", [
            'main_node_id' => (int)$mainNodeId,
            'node_ids' => $nodeIds,
            'force' => $force,
            'domain_pool_keys' => array_keys($parsedPool['domainPool']),
            'primary_domain' => $parsedPool['primaryDomain'],
        ]);

        $maxPerNode = 60;
        $globalDeadline = microtime(true) + 90;
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0, 'no_change' => 0];

        $results = [];
        foreach ($nodeIds as $idx => $id) {
            $nodeStart = microtime(true);

            if (microtime(true) > $globalDeadline) {
                $remaining = array_slice($nodeIds, $idx);
                Log::warning("[Node API] resolveDns global timeout, aborting", [
                    'processed' => count($results), 'remaining_nodes' => $remaining
                ]);
                foreach ($remaining as $skipId) {
                    $results[] = ['action' => 'skipped_timeout', 'success' => false, 'node_id' => $skipId, 'message' => 'Global timeout'];
                    $stats['skipped']++;
                }
                break;
            }

            $node = SsNode::find($id);
            if (!$node) {
                Log::warning("[Node API] resolveDns: node {$id} not found in DB, skipping");
                $stats['skipped']++;
                continue;
            }

            if (!$node->server) {
                Log::warning("[Node API] resolveDns: node {$id} has empty server field", ['node_id' => $id]);
                $stats['skipped']++;
                continue;
            }

            $serverParts = $this->parseServerField($node->server);
            $targetRootDomain = $serverParts ? $serverParts['root_domain'] : '';
            if (!$targetRootDomain) {
                Log::warning("[Node API] resolveDns: invalid server field for node {$id}", ['server' => $node->server]);
                $stats['skipped']++;
                continue;
            }

            $poolMeta = $parsedPool['domainPool'][$targetRootDomain] ?? null;
            if ($poolMeta === null) {
                Log::warning("[Node API] resolveDns: domain '{$targetRootDomain}' not in domain_pool config", [
                    'node_id' => $id, 'server' => $node->server
                ]);
            }

            $zoneId = $this->getDomainZoneId($poolMeta ?: [], $targetRootDomain);
            if (!$zoneId) {
                Log::error("[Node API] resolveDns: zone_id missing, cannot sync node {$id}", [
                    'domain' => $targetRootDomain
                ]);
                $results[] = ['action' => 'skipped_no_zone', 'success' => false, 'node_id' => $id, 'type' => null];
                $stats['failed']++;
                continue;
            }

            if (!$node->ip && !$node->ipv6) {
                Log::debug("[Node API] resolveDns: node {$id} has no IP/IPv6, no blueprints");
                $stats['skipped']++;
                continue;
            }

            // --- CDN Decision: derive from v2_net, NOT v2_name ---
            $v2Net = $node->v2_net ?: '';
            $expectedProxied = $this->isNetCdnRequired($v2Net);

            Log::debug("[Node API] resolveDns: CDN decision for node {$id}", [
                'node_id' => $id, 'v2_net' => $v2Net, 'v2_name' => $node->v2_name,
                'expected_proxied' => $expectedProxied,
            ]);

            $blueprints = [];
            if ($node->ip) {
                $blueprints[] = [
                    'type' => 'A',
                    'subdomain' => 'n' . $node->id,
                    'content' => $node->ip,
                    'root_domain' => $targetRootDomain,
                    'zone_id' => $zoneId,
                    'proxied' => $expectedProxied,
                ];
            }
            if ($node->ipv6) {
                $blueprints[] = [
                    'type' => 'AAAA',
                    'subdomain' => 'ipv6n' . $node->id,
                    'content' => $node->ipv6,
                    'root_domain' => $targetRootDomain,
                    'zone_id' => $zoneId,
                    'proxied' => $expectedProxied,
                ];
            }

            foreach ($blueprints as $blueprint) {
                $bpResult = $this->reconcileThreeWay(
                    $node->id, $blueprint, $dnsProvider, $force
                );
                $results[] = $bpResult;
                $action = $bpResult['action'] ?? 'unknown';
                if (isset($stats[$action])) {
                    $stats[$action]++;
                } elseif (!($bpResult['success'] ?? true)) {
                    $stats['failed']++;
                }
            }

            $expectedTypes = array_column($blueprints, 'type');
            $orphans = DnsRecord::where('node_id', $node->id)
                ->whereNotIn('record_type', $expectedTypes)
                ->get();

            foreach ($orphans as $orphan) {
                $orphanZoneId = $this->getDomainZoneId($parsedPool['domainPool'][$orphan->root_domain] ?? [], $orphan->root_domain);
                if ($orphanZoneId) {
                    $deleteOk = $dnsProvider->deleteRecord($orphan->cf_record_id, $orphanZoneId);
                    if (!$deleteOk) {
                        Log::warning("[Node API] resolveDns: orphan CF delete failed", [
                            'node_id' => $node->id, 'cf_id' => $orphan->cf_record_id,
                        ]);
                    }
                } else {
                    Log::warning("[Node API] resolveDns: orphan has no zone_id, CF record may leak", [
                        'node_id' => $node->id, 'orphan_root_domain' => $orphan->root_domain,
                    ]);
                }
                $orphan->delete();
                $results[] = ['action' => 'deleted_orphan', 'success' => true, 'node_id' => $node->id, 'type' => $orphan->record_type];
                $stats['deleted']++;
            }

            $nodeElapsed = round(microtime(true) - $nodeStart, 3);
            Log::debug("[Node API] resolveDns: node {$id} done in {$nodeElapsed}s", [
                'node_id' => $id, 'elapsed_s' => $nodeElapsed,
                'blueprints_count' => count($blueprints), 'orphans_count' => count($orphans),
            ]);
            if ($nodeElapsed > $maxPerNode) {
                Log::warning("[Node API] resolveDns: node {$id} slow, took {$nodeElapsed}s");
            }
        }

        $totalElapsed = round(microtime(true) - $startTime, 3);
        $allSuccess = !empty($results) && array_reduce($results, function ($carry, $r) {
            return $carry && ($r['success'] ?? true);
        }, true);

        Log::info("[Node API] resolveDns complete", [
            'main_node_id' => (int)$mainNodeId,
            'total_nodes' => count($nodeIds),
            'total_results' => count($results),
            'stats' => $stats,
            'elapsed_s' => $totalElapsed,
            'success' => $allSuccess,
        ]);

        return response()->json([
            'status' => $allSuccess ? 'success' : 'error',
            'results' => $results,
            'stats' => $stats,
            'elapsed_s' => $totalElapsed,
            'message' => $allSuccess ? 'DNS cluster synchronized' : 'Some DNS operations failed'
        ]);
    }

    private function reconcileThreeWay($nodeId, $blueprint, $dnsProvider, $force = false)
    {
        $sceneStart = microtime(true);
        $fqdn = $blueprint['subdomain'] . '.' . $blueprint['root_domain'];
        $expectedProxied = $blueprint['proxied'] ?? false;

        $localRecord = DnsRecord::where('node_id', $nodeId)
            ->where('record_type', $blueprint['type'])
            ->first();

        if ($localRecord) {
            Log::debug("[Node API] reconcileThreeWay: local record found", [
                'node_id' => $nodeId, 'type' => $blueprint['type'],
                'local_subdomain' => $localRecord->subdomain,
                'local_root_domain' => $localRecord->root_domain,
                'local_ip' => $localRecord->ip_addr,
                'local_cf_id' => $localRecord->cf_record_id,
                'local_proxied' => (bool)$localRecord->proxied,
            ]);
        }

        // Scene A: Cache Blocking
        if (!$force && $localRecord) {
            $isMatch = ($localRecord->subdomain === $blueprint['subdomain'] &&
                        $localRecord->root_domain === $blueprint['root_domain'] &&
                        $localRecord->ip_addr === $blueprint['content'] &&
                        (bool)$localRecord->proxied === $expectedProxied);

            if ($isMatch) {
                return [
                    'action' => 'no_change',
                    'success' => true,
                    'node_id' => $nodeId,
                    'type' => $blueprint['type'],
                    'fqdn' => $fqdn,
                    'proxied' => $expectedProxied,
                    'message' => 'Cache hit: state matches blueprint'
                ];
            }

            $diffFields = [];
            if ($localRecord->subdomain !== $blueprint['subdomain']) $diffFields[] = 'subdomain';
            if ($localRecord->root_domain !== $blueprint['root_domain']) $diffFields[] = 'root_domain';
            if ($localRecord->ip_addr !== $blueprint['content']) $diffFields[] = 'ip_addr';
            if ((bool)$localRecord->proxied !== $expectedProxied) $diffFields[] = 'proxied';

            Log::debug("[Node API] reconcileThreeWay: cache miss, diff: " . implode(',', $diffFields), [
                'node_id' => $nodeId, 'type' => $blueprint['type'],
                'local_proxied' => (bool)$localRecord->proxied,
                'expected_proxied' => $expectedProxied,
            ]);
        }

        // Scene B: Domain changed → DELETE old + recreate
        if ($localRecord && ($localRecord->subdomain !== $blueprint['subdomain'] || $localRecord->root_domain !== $blueprint['root_domain'])) {
            $oldZoneId = $this->getDomainZoneIdForRecord($localRecord);
            if ($oldZoneId) {
                $deleteOk = $dnsProvider->deleteRecord($localRecord->cf_record_id, $oldZoneId);
                Log::info("[Node API] reconcileThreeWay Scene B: domain changed, deleted old CF record", [
                    'node_id' => $nodeId, 'type' => $blueprint['type'],
                    'old_fqdn' => $localRecord->subdomain . '.' . $localRecord->root_domain,
                    'new_fqdn' => $fqdn,
                    'cf_delete_ok' => $deleteOk,
                    'cf_id' => $localRecord->cf_record_id,
                ]);
            } else {
                Log::warning("[Node API] reconcileThreeWay Scene B: cannot delete old CF record, zone_id missing", [
                    'node_id' => $nodeId, 'old_root_domain' => $localRecord->root_domain,
                    'cf_id' => $localRecord->cf_record_id,
                ]);
            }
            $localRecord->delete();
            $localRecord = null;
        }

        // Scene C: Creation
        if (!$localRecord) {
            Log::debug("[Node API] reconcileThreeWay Scene C: creating CF record", [
                'node_id' => $nodeId, 'type' => $blueprint['type'],
                'fqdn' => $fqdn, 'content' => $blueprint['content'],
                'proxied' => $expectedProxied,
            ]);

            $cfResult = $dnsProvider->createRecord(
                $blueprint['root_domain'],
                $blueprint['subdomain'],
                $blueprint['content'],
                $blueprint['type'],
                $blueprint['zone_id'],
                $expectedProxied
            );

            if (!$cfResult) {
                $elapsed = round(microtime(true) - $sceneStart, 3);
                Log::error("[Node API] reconcileThreeWay Scene C: CF create failed", [
                    'node_id' => $nodeId, 'type' => $blueprint['type'],
                    'fqdn' => $fqdn, 'zone_id' => $blueprint['zone_id'],
                    'proxied' => $expectedProxied, 'elapsed_s' => $elapsed,
                ]);
                return [
                    'action' => 'create_failed',
                    'success' => false,
                    'node_id' => $nodeId,
                    'type' => $blueprint['type'],
                    'fqdn' => $fqdn,
                ];
            }

            if (empty($cfResult['id'])) {
                Log::error("[Node API] reconcileThreeWay Scene C: CF returned success but no record id", [
                    'node_id' => $nodeId, 'cf_result' => $cfResult,
                ]);
                return [
                    'action' => 'create_failed',
                    'success' => false,
                    'node_id' => $nodeId,
                    'type' => $blueprint['type'],
                    'fqdn' => $fqdn,
                    'message' => 'CF returned no record ID',
                ];
            }

            DnsRecord::create([
                'node_id' => $nodeId,
                'root_domain' => $blueprint['root_domain'],
                'subdomain' => $blueprint['subdomain'],
                'record_type' => $blueprint['type'],
                'ip_addr' => $blueprint['content'],
                'cf_record_id' => $cfResult['id'],
                'proxied' => $expectedProxied,
            ]);

            $elapsed = round(microtime(true) - $sceneStart, 3);
            Log::info("[Node API] reconcileThreeWay Scene C: created", [
                'node_id' => $nodeId, 'type' => $blueprint['type'],
                'fqdn' => $fqdn, 'content' => $blueprint['content'],
                'cf_id' => $cfResult['id'], 'proxied' => $expectedProxied,
                'elapsed_s' => $elapsed,
            ]);

            return [
                'action' => 'created',
                'success' => true,
                'node_id' => $nodeId,
                'type' => $blueprint['type'],
                'fqdn' => $fqdn,
                'cf_id' => $cfResult['id'],
                'proxied' => $expectedProxied,
            ];
        }

        // Scene D: IP or proxied state changed → update
        if (empty($localRecord->cf_record_id)) {
            Log::error("[Node API] reconcileThreeWay Scene D: local record has no cf_record_id", [
                'node_id' => $nodeId, 'type' => $blueprint['type'],
                'local_record_id' => $localRecord->id,
            ]);
            $localRecord->delete();
            return [
                'action' => 'update_failed',
                'success' => false,
                'node_id' => $nodeId,
                'type' => $blueprint['type'],
                'fqdn' => $fqdn,
                'message' => 'Missing cf_record_id, cache cleared for retry',
            ];
        }

        $ipChanged = $localRecord->ip_addr !== $blueprint['content'];
        $proxiedChanged = (bool)$localRecord->proxied !== $expectedProxied;

        Log::debug("[Node API] reconcileThreeWay Scene D: updating", [
            'node_id' => $nodeId, 'type' => $blueprint['type'],
            'fqdn' => $fqdn, 'old_ip' => $localRecord->ip_addr,
            'new_ip' => $blueprint['content'], 'ip_changed' => $ipChanged,
            'old_proxied' => (bool)$localRecord->proxied,
            'new_proxied' => $expectedProxied, 'proxied_changed' => $proxiedChanged,
            'cf_id' => $localRecord->cf_record_id,
        ]);

        $cfResult = $dnsProvider->updateRecordById(
            $localRecord->cf_record_id,
            $blueprint['root_domain'],
            $blueprint['subdomain'],
            $blueprint['content'],
            $blueprint['type'],
            $blueprint['zone_id'],
            $expectedProxied
        );

        if (!$cfResult) {
            $elapsed = round(microtime(true) - $sceneStart, 3);
            Log::warning("[Node API] reconcileThreeWay Scene D: CF update failed, clearing cache", [
                'node_id' => $nodeId, 'cf_id' => $localRecord->cf_record_id,
                'fqdn' => $fqdn, 'elapsed_s' => $elapsed,
            ]);
            $localRecord->delete();
            return [
                'action' => 'update_failed',
                'success' => false,
                'node_id' => $nodeId,
                'type' => $blueprint['type'],
                'fqdn' => $fqdn,
            ];
        }

        $localRecord->ip_addr = $blueprint['content'];
        $localRecord->proxied = $expectedProxied;
        $localRecord->save();

        $elapsed = round(microtime(true) - $sceneStart, 3);
        $actionLabel = $proxiedChanged && !$ipChanged ? 'updated_proxied' : 'updated_ip';
        Log::info("[Node API] reconcileThreeWay Scene D: {$actionLabel}", [
            'node_id' => $nodeId, 'type' => $blueprint['type'],
            'fqdn' => $fqdn, 'ip' => $blueprint['content'],
            'proxied' => $expectedProxied, 'elapsed_s' => $elapsed,
        ]);

        return [
            'action' => $actionLabel,
            'success' => true,
            'node_id' => $nodeId,
            'type' => $blueprint['type'],
            'fqdn' => $fqdn,
            'proxied' => $expectedProxied,
        ];
    }

    private function getDomainZoneIdForRecord($dnsRecord)
    {
        $sysConf = Helpers::systemConfig();
        $parsedPool = $this->parseDomainPool($sysConf);
        return $this->getDomainZoneId($parsedPool['domainPool'][$dnsRecord->root_domain] ?? [], $dnsRecord->root_domain);
    }

    private function parseServerField($server)
    {
        if (!$server || !strpos($server, '.')) {
            return null;
        }
        $parts = explode('.', $server, 2);
        return ['subdomain' => $parts[0], 'root_domain' => $parts[1]];
    }

    public function config(Request $request)
    {

        $nodeId = $request->input('node_id');
        $node = SsNode::find($nodeId);
        if (!$node) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);
        if ($node->status == 0) return response()->json(['status' => 'error', 'message' => 'Node is offline'], 403);

        // --- Template selection: strictly via v2_name ---
        $v2Name = $node->v2_name ?: 'vision-hy2-ws-grpc';
        $templatePath = resource_path("templates/xray/{$v2Name}.json");
        if (!file_exists($templatePath)) return response()->json(['status' => 'error', 'message' => 'Template not found'], 500);

        $rawTemplate = json_decode(file_get_contents($templatePath));
        $config = json_decode(json_encode($rawTemplate), true);

        $serverParts = $this->parseServerField($node->server);
        $nodeDomain = $serverParts ? $serverParts['root_domain'] : $node->server;

        $expanded = $this->expandProtocols($v2Name);
        $inboundTags = array_map(function($proto) {
            return 'proxy-' . $proto;
        }, $expanded);

        $vars = [
            '__wsPath__' => 'srp-ws',
            '__wsPort__' => 10011,
            '__v2Fallback__' => '127.0.0.1',
            '__nodeDomain__' => $nodeDomain,
            '__HYSTERIA_URL__' => 'https://www.bing.com',
            '__v2ServiceName__' => 'srp-grpc',
            '__grpcPort__' => 10012,
            '__xhttpPath__' => 'srp-xhttp',
            '__xhttpPort__' => 10013,
            '__hy2Port__' => 443,
            '__visionPort__' => 443,
            '__dbHost__' => env('DB_REMOTE_HOST', env('DB_HOST', '127.0.0.1')),
            '__dbUser__' => env('DB_USERNAME', 'root'),
            '__dbPassword__' => env('DB_PASSWORD', ''),
            '__dbName__' => env('DB_DATABASE', 'npanel'),
        ];

        $config = $this->injectVariables($config, $vars);

        // --- Clone node: keep only the matching inbound ---
        if ($node->is_clone > 0) {
            $keepTag = $this->v2NetToInboundTag($node->v2_net);
            $config['inbounds'] = array_values(array_filter($config['inbounds'], function ($ib) use ($keepTag) {
                return ($ib['tag'] ?? '') === 'api' || ($ib['tag'] ?? '') === $keepTag;
            }));
            $inboundTags = [$keepTag];
            $expanded = [$keepTag];
        }

        if (isset($config['ssrpanel'])) {
            $config['ssrpanel']['nodeId'] = (int)$node->id;
            $config['ssrpanel']['user']['inboundTags'] = $inboundTags;

            if (in_array('proxy-vision', $inboundTags)) {
                $config['ssrpanel']['user']['flows'] = [
                    'proxy-vision' => 'xtls-rprx-vision'
                ];
            } else {
                unset($config['ssrpanel']['user']['flows']);
            }
        }

        if ($node->node_unlock) $this->applyUnlocks($config, $node->node_unlock);

        // --- HY2 Direct IP Override ---
        // Derive protocol from v2_net (NOT v2_name)
        $v2Net = $node->v2_net ?: '';
        if ($v2Net === 'hysteria2') {
            $directIp = $node->ip ?: $node->ipv6;
            if ($directIp) {
                Log::debug("[Node API] config: HY2 detected via v2_net, overriding server to direct IP", [
                    'node_id' => $node->id, 'v2_net' => $v2Net,
                    'direct_ip' => $directIp, 'original_server' => $node->server,
                ]);
                $this->overrideServerToIp($config, $directIp);
            } else {
                Log::warning("[Node API] config: HY2 detected but node has no IP/IPv6", [
                    'node_id' => $node->id,
                ]);
            }
        }

        $config = $this->restoreJsonObjectTypes($config, $rawTemplate);

        $logVars = $vars;
        $logVars['__dbPassword__'] = '******';

        Log::debug('[Node API] 配置下发', [
            'node_id' => $node->id,
            'template' => $v2Name,
            'v2_net' => $v2Net,
            'variables' => $logVars,
            'unlocks' => $node->node_unlock ? count(explode(',', $node->node_unlock)) : 0,
            'hy2_override' => ($v2Net === 'hysteria2'),
        ]);

        return response()->json($config, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function nginxConfig(Request $request)
    {
        $nodeId = $request->input('node_id');
        $node = SsNode::find($nodeId);
        if (!$node) return response('Node not found', 404);
        if ($node->status == 0) return response('Node is offline', 403);

        $mainNode = ($node->is_clone > 0) ? SsNode::find($node->is_clone) : $node;
        if (!$mainNode) return response('Main node not found', 500);

        $v2Name = $mainNode->v2_name ?: 'vision-hy2-ws-grpc';

        $templatePath = resource_path("templates/nginx/{$v2Name}.conf");
        if (!file_exists($templatePath)) return response('Template not found', 500);

        $conf = file_get_contents($templatePath);

        $serverParts = $this->parseServerField($node->server);
        $rootDomain = $serverParts ? $serverParts['root_domain'] : '';

        $conf = str_replace(
            ['__nodeDomainRegex__', '__nodeDomain__', '__xhttpPath__', '__xhttpPort__',
             '__wsPath__', '__wsPort__', '__v2ServiceName__', '__grpcPort__',
             '__httpProxyHost__'],
            [$rootDomain, $rootDomain, 'srp-xhttp', '10013',
             'srp-ws', '10011', 'srp-grpc', '10012',
             'npanel-nav.freessr.bid'],
            $conf
        );

        Log::debug('[Node API] nginx_config 下发', [
            'node_id' => $node->id,
            'main_node_id' => $mainNode->id,
            'v2_name' => $v2Name,
            'root_domain' => $rootDomain,
        ]);

        return response($conf, 200)->header('Content-Type', 'text/plain');
    }

    private function overrideServerToIp(&$config, $ip)
    {
        if (!is_array($config)) return;
        foreach ($config as $key => &$value) {
            if (is_array($value)) {
                $this->overrideServerToIp($value, $ip);
            } elseif (is_string($value) && $key === 'server') {
                $value = $ip;
            }
        }
    }

    private function injectVariables($data, $vars)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->injectVariables($value, $vars);
            }
            return $data;
        }

        if (is_string($data)) {
            if (isset($vars[$data])) {
                return $vars[$data];
            }
            foreach ($vars as $k => $v) {
                if (is_string($v) || is_numeric($v)) {
                    $data = str_replace($k, (string)$v, $data);
                }
            }
        }
        return $data;
    }

    private function restoreJsonObjectTypes($data, $template)
    {
        if ($template instanceof \stdClass) {
            $result = [];
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $tplValue = property_exists($template, $key) ? $template->$key : null;
                    $result[$key] = $this->restoreJsonObjectTypes($value, $tplValue);
                }
            }
            return (object)$result;
        }

        if (is_array($template)) {
            if (is_array($data)) {
                foreach ($data as $i => $value) {
                    $tplValue = isset($template[$i]) ? $template[$i] : null;
                    $data[$i] = $this->restoreJsonObjectTypes($value, $tplValue);
                }
            }
            return $data;
        }

        if (is_array($data)) {
            $isAssoc = !empty($data) && array_keys($data) !== range(0, count($data) - 1);
            if ($isAssoc) {
                $result = [];
                foreach ($data as $key => $value) {
                    $result[$key] = $this->restoreJsonObjectTypes($value, null);
                }
                return (object)$result;
            }

            foreach ($data as $i => $value) {
                $data[$i] = $this->restoreJsonObjectTypes($value, null);
            }
            return $data;
        }

        return $data;
    }

    private function applyUnlocks(&$config, $unlockStr)
    {
        $unlocks = [];
        parse_str(str_replace(',', '&', $unlockStr), $unlocks);

        $sysConf = Helpers::systemConfig();

        foreach ($unlocks as $key => $value) {
            $valLower = strtolower(trim((string)$value));
            // 匹配以 0, no, false, off 开头的字符串，说明节点无法解锁，需要下发远程解锁配置
            $isEnabled = preg_match('/^(0|no|false|off)/', $valLower) === 1;

            if ($isEnabled) {
                $service = strtolower(str_replace(['unlock', ' '], '', $key));

                if ($service === 'chatgpt') $service = 'openai';

                $templatePath = resource_path("templates/xray/unlock/{$service}.json");

                if (file_exists($templatePath)) {
                    $rawTemplate = file_get_contents($templatePath);

                    $addr = $sysConf["unlock_{$service}_address"] ?? '';
                    $port = $sysConf["unlock_{$service}_port"] ?? 8388;
                    $pwd = $sysConf["unlock_{$service}_password"] ?? '';
                    $method = $sysConf["unlock_{$service}_method"] ?? 'chacha20-ietf-poly1305';

                    if (empty($addr)) {
                        Log::warning("[Node API] Unlock service {$service} enabled but address is missing in DB.");
                        continue;
                    }

                    $rawTemplate = str_replace(
                        ['__address__', '__port__', '__password__', '__method__'],
                        [$addr, (string)$port, $pwd, $method],
                        $rawTemplate
                    );

                    $unlockData = json_decode($rawTemplate, true);
                    if ($unlockData) {
                        if (!empty($unlockData['outbounds'])) {
                            foreach ($unlockData['outbounds'] as $outbound) {
                                $config['outbounds'][] = $outbound;
                            }
                        }
                        if (!empty($unlockData['routing']['rules'])) {
                            foreach ($unlockData['routing']['rules'] as $rule) {
                                $config['routing']['rules'][] = $rule;
                            }
                        }
                    }
                } else {
                    Log::warning("[Node API] Unlock service {$service} has no template, skipping geosite rule.");
                }
            }
        }
    }

    public function status(Request $request)
    {
        $nodeId = $request->input('node_id');
        $rawRx = (float)$request->input('raw_rx', 0);
        $rawTx = (float)$request->input('raw_tx', 0);

        $node = SsNode::find($nodeId);
        if (!$node) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);

        if ($node->node_rxtx == 'rxtx') {
            $rawTotal = ($rawRx + $rawTx) / 2;
        } else {
            $rawTotal = $rawTx;
        }

        $lastRaw = $node->last_raw_total ?: 0;
        if ($lastRaw == 0) {
            $incremental = 0;
        } elseif ($rawTotal < $lastRaw) {
            $incremental = $rawTotal;
        } else {
            $incremental = $rawTotal - $lastRaw;
        }
        $node->last_raw_total = $rawTotal;

        $node->traffic_used += $incremental;
        $node->server_uptime = (int)$request->input('server_uptime', $node->server_uptime);
        $node->bandwidth = (int)$request->input('node_bandwidth', $node->bandwidth) + $node->level;
        $node->heartbeat_at = date('Y-m-d H:i:s');

        $passedDays = (int)date('d');
        $remainingDays = (int)date('t') - $passedDays + 1;

        $avgUsed = $node->traffic_used / max($passedDays, 1);
        $avgRemaining = ($node->traffic_limit - $node->traffic_used) / max($remainingDays, 1);
        $node->node_health = ($avgUsed > $avgRemaining) ? 0 : 1;

        if (($node->traffic_limit - $node->traffic_used) < (120 * 1024 * 1024 * 1024)) {
            $node->status = 0;
        }

        $node->save();

        $logData = [
            'node_id' => $nodeId,
            'raw_rx' => $rawRx,
            'raw_tx' => $rawTx,
            'incremental' => $incremental,
            'used' => $node->traffic_used,
            'limit' => $node->traffic_limit,
            'health' => $node->node_health
        ];

        if ($node->status == 0) {
            Log::warning('[Node API] 节点熔断下线', array_merge($logData, [
                'reason' => 'Remaining traffic < 120GB',
                'remaining' => $node->traffic_limit - $node->traffic_used
            ]));
        } elseif ($node->node_health == 0) {
            Log::warning('[Node API] 节点健康度预警', array_merge($logData, [
                'reason' => 'Consumption rate exceeds remaining budget'
            ]));
        } else {
            Log::info('[Node API] 状态上报', $logData);
        }

        return response()->json(['status' => 'success', 'node_status' => $node->status]);
    }
}
