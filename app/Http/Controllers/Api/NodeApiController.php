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

    private function validateToken(Request $request)
    {
        $token = $request->input('token') ?: $request->header('X-API-Token');
        if (!$token || $token !== env('API_TOKEN')) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized: invalid or missing token'], 401);
        }
        return null;
    }

    public function __construct()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Parse node_domain_pool from config.
     * Supports both new dict format {domain: {zone_id, records_limit}} and legacy array format [domain].
     * Returns ['domainPool' => [domain => meta, ...], 'primaryDomain' => string]
     */
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
                // Detect format: dict (associative with string keys) vs legacy flat array
                $firstKey = array_key_first($decoded);
                if ($firstKey !== null && is_string($firstKey) && is_array($decoded[$firstKey])) {
                    // New dict format: {"domain.com": {"zone_id": "...", "records_limit": 180}}
                    $domainPool = $decoded;
                } else {
                    // Legacy array format: ["domain.com", ...] → convert to dict
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

    /**
     * Get the records_limit for a specific domain from pool metadata.
     */
    private function getDomainLimit(array $meta)
    {
        return isset($meta['records_limit']) ? (int)$meta['records_limit'] : self::DEFAULT_RECORDS_LIMIT;
    }

    /**
     * Get the zone_id for a specific domain from pool metadata.
     * Logs error if missing.
     */
    private function getDomainZoneId(array $meta, $domain = 'unknown')
    {
        $zoneId = $meta['zone_id'] ?? null;
        if (!$zoneId) {
            Log::error("[Node API] 未能从配置池中找到域名 {$domain} 的 Zone ID");
        }
        return $zoneId;
    }

    /**
     * Step 0: Apply Node ID
     */
    public function applyId(Request $request)
    {
        if ($request->input('token') != env('API_TOKEN')) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
        }

        $nodeIp = $request->input('node_ip');
        $nodeIpv6 = $request->input('node_ipv6');

        // Recycle logic: find nodes with no heartbeat for > 32 days (ordered by most recent)
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
            // Clear legacy DNS records for recycled ID
            DnsRecord::where('node_id', $node->id)->delete();
        } else {
            $node = new SsNode();
        }

        $node->name = 'New Node ' . ($nodeIp ?: $nodeIpv6);
        $node->ip = $nodeIp ?: '';
        $node->ipv6 = $nodeIpv6 ?: '';
        $node->status = 0;
        $node->is_clone = 0; // Reset to main node
        $node->save();

        Log::info('[Node API] ID 分配成功', [
            'ip' => $request->ip(),
            'node_id' => $node->id,
            'recycled' => $recycled
        ]);

        return response()->json(['node_id' => $node->id]);
    }

    /**
     * Step 1: Identity reconciliation, fission, and domain affinity allocation.
     *
     * Dynamic protocol assignment based on memory and DB config.
     * Clone reuse (anti-proliferation): revives old clone records instead of blind insert.
     * Tiered level engine: main node level = floor(cost), clones >= main level.
     */
    public function register(Request $request)
    {
        $nodeId = $request->input('node_id');
        $token = $request->input('token');

        if ($token != env('API_TOKEN')) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
        }

        $node = SsNode::find($nodeId);
        if (!$node) {
            return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);
        }

        $sysConf = Helpers::systemConfig();

        // --- Dynamic protocol presets from config (anti-hardcoding) ---
        $presets = json_decode($sysConf['node_protocol_presets'] ?? '{}', true);
        $thresholdMb = $presets['threshold_mb'] ?? 2048;
        $highProto = $presets['high'] ?? 'xhttp-hy2-ws-grpc';
        $lowProto = $presets['low'] ?? 'vision-hy2-ws-grpc';

        $nodeMemory = (float)$request->input('node_memory', $request->input('memory', 0));
        $thresholdGb = $thresholdMb / 1024;

        // v2_name: prefer client-reported value, fallback to dynamic allocation
        $v2Name = $request->input('v2_name');
        if (!$v2Name) {
            $v2Name = ($nodeMemory > $thresholdGb) ? $highProto : $lowProto;
        }

        $rootDomain = $this->resolveDomainAffinity($request->input('root_domain'), $sysConf);

        // --- Level: prefer client-reported node_level, fallback to tiered engine ---
        $nodeCost = (float)$request->input('node_cost', 0);
        $clientLevel = $request->input('node_level');
        $mainLevel = $clientLevel !== null ? (int)$clientLevel : max(1, (int)floor($nodeCost));

        // --- Update main node with standardized fields ---
        $node->v2_name = $v2Name;
        $node->node_rxtx = $request->input('node_rxtx', $request->input('node_rxtx_mode', $request->input('billing_mode', 'tx')));
        $node->node_cpu = $request->input('node_cpu', $request->input('cpu'));
        $node->node_memory = $nodeMemory;
        $node->node_disk = $request->input('node_disk', $request->input('disk'));
        $node->bandwidth = (int)$request->input('node_bandwidth', $request->input('bandwidth', 100));
        $node->node_unlock = (string)$request->input('node_unlock', ''); // Force raw string storage
        $node->info = $request->input('node_info', '');
        $node->level = $mainLevel;
        $node->node_group = $request->input('node_group', 1);
        $node->node_cost = $nodeCost;
        $node->traffic_limit = $request->input('node_traffic_limit', 1000) * 1024 * 1024 * 1024;
        $node->reset_day = (int)$request->input('node_traffic_resetday', 1);
        $node->sort = $request->input('node_sort', 0);
        $node->traffic_rate = $request->input('node_traffic_rate', 1.0);
        $node->country_code = strtolower($request->input('node_country_code', 'un'));
        $node->node_country = $request->input('node_country');
        $node->node_city = $request->input('node_city');
        $node->ip = $request->input('node_ip', $node->ip);
        $node->ipv6 = $request->input('node_ipv6', $node->ipv6);
        $node->server = 'node' . $node->id . '.' . $rootDomain;
        $node->status = 1;
        $node->save();

        // --- Fission matrix: build protocol × IP slots ---
        $protocols = $this->expandProtocols($v2Name);
        shuffle($protocols); // randomize protocol assignment across nodes

        $ips = [];
        if ($node->ip) $ips[] = ['type' => 'ipv4', 'addr' => $node->ip];
        if ($node->ipv6) $ips[] = ['type' => 'ipv6', 'addr' => $node->ipv6];

        // Build the full target slot list: (protocol, ipType, ipAddr)
        $slots = [];
        foreach ($ips as $ipInfo) {
            foreach ($protocols as $protocol) {
                $slots[] = ['protocol' => $protocol, 'ip_type' => $ipInfo['type'], 'addr' => $ipInfo['addr']];
            }
        }

        $totalTarget = count($slots);
        if ($totalTarget === 0) {
            return response()->json([
                'status' => 'success',
                'node_id' => $node->id,
                'clone_node_ids' => [],
                'root_domain' => $rootDomain,
                'v2_name' => $v2Name,
            ]);
        }

        // --- Clone reuse (anti-proliferation) ---
        $existingClones = SsNode::where('is_clone', $nodeId)->get()->values();
        $cutoff = date('Y-m-d H:i:s', strtotime('-32 days'));

        // Pre-fetch available dead nodes for recycling (to avoid duplicate selection in loop)
        $deadNodes = SsNode::where(function($query) use ($cutoff) {
                $query->where('heartbeat_at', '<', $cutoff)
                      ->orWhere(function($q) use ($cutoff) {
                          $q->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
                      });
            })
            ->where('id', '!=', $nodeId)
            ->where('is_clone', '!=', $nodeId) // exclude nodes we are already reusing
            ->orderBy('heartbeat_at', 'desc')
            ->get();
        $deadIdx = 0;

        $cloneIds = [];

        // Slot 0 = main node itself (first protocol, first IP)
        // Keep original v2_name (e.g. "xhttp-hy2-ws-grpc") on main node for template lookup.
        // Only clone nodes get single-protocol v2_name.
        $mainSlot = array_shift($slots);
        $this->applyV2Preset($node, $mainSlot['protocol'], $rootDomain, $mainSlot['ip_type'] === 'ipv6');
        $node->save();
        $allNodeIds = [$node->id];

        // Reuse existing clones for remaining slots
        foreach ($slots as $i => $slot) {
            if ($i < $existingClones->count()) {
                // Revive existing clone
                $clone = $existingClones[$i];
                $clone->name = $node->name . ' - ' . $slot['protocol'] . ' (' . $slot['ip_type'] . ')';
                $clone->v2_name = $slot['protocol'];
                $clone->node_rxtx = $node->node_rxtx;
                $clone->ip = ($slot['ip_type'] == 'ipv4') ? $slot['addr'] : '';
                $clone->ipv6 = ($slot['ip_type'] == 'ipv6') ? $slot['addr'] : '';
                $clone->type = 3;
                $clone->level = rand($mainLevel, min(5, $mainLevel + 2)); // Staircase level engine [floor, floor+1, floor+2]
                $clone->node_group = $node->node_group;
                $clone->traffic_rate = $node->traffic_rate;
                $clone->status = 1;
                $clone->server = 'node' . $clone->id . '.' . $rootDomain;
                $this->applyV2Preset($clone, $slot['protocol'], $rootDomain, $slot['ip_type'] === 'ipv6');
                $clone->save();
            } else {
                // Need to create new clone - but first try to recycle dead nodes
                $clone = null;
                if ($deadIdx < $deadNodes->count()) {
                    $clone = $deadNodes[$deadIdx++];
                    // Clear legacy DNS records for recycled ID
                    DnsRecord::where('node_id', $clone->id)->delete();
                } else {
                    $clone = new SsNode();
                }

                $clone->name = $node->name . ' - ' . $slot['protocol'] . ' (' . $slot['ip_type'] . ')';
                $clone->v2_name = $slot['protocol'];
                $clone->is_clone = $nodeId;
                $clone->node_rxtx = $node->node_rxtx;
                $clone->ip = ($slot['ip_type'] == 'ipv4') ? $slot['addr'] : '';
                $clone->ipv6 = ($slot['ip_type'] == 'ipv6') ? $slot['addr'] : '';
                $clone->type = 3;
                $clone->level = rand($mainLevel, min(5, $mainLevel + 2)); // Staircase level engine
                $clone->node_group = $node->node_group;
                $clone->traffic_rate = $node->traffic_rate;
                $clone->status = 1;
                $clone->save();

                $clone->server = 'node' . $clone->id . '.' . $rootDomain;
                $this->applyV2Preset($clone, $slot['protocol'], $rootDomain, $slot['ip_type'] === 'ipv6');
                $clone->save();
            }

            $cloneIds[] = $clone->id;
            $allNodeIds[] = $clone->id;
        }

        // --- Recycle excess old clones into the public pool ---
        if ($existingClones->count() > count($slots)) {
            foreach ($existingClones->skip(count($slots)) as $excess) {
                $excess->is_clone = 0;
                $excess->status = 0;
                $excess->save();
                // Clear DNS records for released node
                DnsRecord::where('node_id', $excess->id)->delete();
            }
        }

        // --- Seal node_ids into main node ---
        $nodeIdsStr = implode(',', $allNodeIds);
        $node->node_ids = $nodeIdsStr;
        $node->save();

        Log::info('[Node API] 节点注册成功', [
            'node_id' => $node->id,
            'protocol_group' => $v2Name,
            'domain_decision' => $rootDomain,
            'node_ids' => $allNodeIds,
            'total_nodes_generated' => count($allNodeIds),
            'deleted_count' => $existingClones->count() > count($slots) ? $existingClones->count() - count($slots) : 0,
            'ip' => $node->ip,
            'ipv6' => $node->ipv6
        ]);

        $response = [
            'status' => 'success',
            'node_id' => $node->id,
            'clone_node_ids' => $cloneIds,
            'node_ids' => $nodeIdsStr,
            'root_domain' => $rootDomain,
            'v2_name' => $v2Name,
            'proxy_host' => 'npanel-nav.freessr.bid',
        ];

        // --- xhttp-hy2-ws-grpc mode: emit paths and fixed ports ---
        $expanded = $this->expandProtocols($v2Name);
        if (in_array('xhttp', $expanded) || in_array('ws', $expanded) || in_array('grpc', $expanded)) {
            $response['ws_path'] = 'srp-ws';
            $response['ws_port'] = 10011;
            $response['grpc_service_name'] = 'srp-grpc';
            $response['grpc_port'] = 10012;
            $response['xhttp_path'] = 'srp-xhttp';
            $response['xhttp_port'] = 10013;
        }

        if (in_array('hy2', $expanded)) {
            $response['hy2_port'] = 443;
        }
        if (in_array('vision', $expanded)) {
            $response['vision_port'] = 443;
        }

        return response()->json($response);
    }

    /**
     * Protocol → v2_* field preset mapping.
     * Each protocol maps to the exact v2_* fields SubscribeController needs
     * to generate correct subscription links (vmess://, vless://, hy2://).
     */
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

    /**
     * Expand a protocol group string into individual protocol names.
     * e.g. "vision-hy2-ws-grpc" → ["vision", "hy2", "ws", "grpc"]
     */
    private function expandProtocols($v2Name)
    {
        return explode('-', $v2Name);
    }

    /**
     * Apply v2_* preset fields to a node based on its single-protocol name.
     * Static fields come from V2_PRESETS; dynamic fields (host, sni, path) computed from node context.
     *
     * @param SsNode  $node
     * @param string  $protocol   Single protocol (e.g. "ws", "grpc", "hy2")
     * @param string  $rootDomain
     * @param bool    $isIpv6
     */
    private function applyV2Preset($node, $protocol, $rootDomain, $isIpv6)
    {
        $preset = self::V2_PRESETS[$protocol] ?? null;
        if (!$preset) {
            Log::warning('[Node API] 无 v2 预设配置', ['protocol' => $protocol]);
            return;
        }

        // Apply static fields from preset
        foreach ($preset as $field => $value) {
            $node->{$field} = $value;
        }

        // Dynamic fields: v2_host/v2_sni = server field (node{id}.{domain})
        $node->v2_host = $node->server;
        $node->v2_sni = $node->server;

        // Protocol-specific dynamic fields
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

    /**
     * Domain Affinity Resolution:
     * Reads node_domain_pool (JSON dict or legacy array) from config.
     * Priority 1: Reuse reported root_domain if its dns_records count < its records_limit.
     * Priority 2: Pick first available domain from pool with count < records_limit.
     * Fallback: Use primary domain (first key in pool).
     */
    private function resolveDomainAffinity($reportedDomain, $sysConf)
    {
        $parsed = $this->parseDomainPool($sysConf);
        $domainPool = $parsed['domainPool'];
        $primaryDomain = $parsed['primaryDomain'];

        // Priority 1: Check reported domain first (Allow custom affinity)
        if ($reportedDomain) {
            $limit = $this->getDomainLimit($domainPool[$reportedDomain] ?? []);
            $count = DnsRecord::where('root_domain', $reportedDomain)->count();
            if ($count < $limit) {
                return $reportedDomain;
            }
        }

        // Priority 2: Pick first available domain from pool
        foreach ($domainPool as $domainName => $meta) {
            $limit = $this->getDomainLimit($meta);
            $count = DnsRecord::where('root_domain', $domainName)->count();
            if ($count < $limit) {
                return $domainName;
            }
        }

        // All domains full — use primary as last resort
        return $primaryDomain;
    }

    /**
     * Step 2: DB-Driven Status Synchronization (Three-way Reconciliation).
     *
     * Logic:
     * 1. Get latest node state from ss_node (Source of Truth).
     * 2. Calculate expected DNS records (Blueprints).
     * 3. Compare with local dns_records (Cache).
     * 4. Perform atomic CF operations ONLY when a diff is detected.
     * 5. Handle ID recycling (DELETE + POST) and IP updates (PUT).
     */
    public function resolveDns(Request $request)
    {
        if ($err = $this->validateToken($request)) return $err;

        $mainNodeId = $request->input('node_id');
        $force = (bool)$request->input('force', false);
        $mainNode = SsNode::find($mainNodeId);
        if (!$mainNode) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);

        $nodeIds = explode(',', $mainNode->node_ids ?: $mainNodeId);
        $dnsProvider = app(\App\Components\DNS\CloudflareProvider::class);
        $sysConf = Helpers::systemConfig();
        $parsedPool = $this->parseDomainPool($sysConf);

        $results = [];
        foreach ($nodeIds as $id) {
            $node = SsNode::find($id);
            if (!$node) continue;

            $serverParts = $this->parseServerField($node->server);
            $targetRootDomain = $serverParts ? $serverParts['root_domain'] : '';
            if (!$targetRootDomain) {
                Log::warning("[Node API] Skip DNS sync for node {$id}: Invalid server field '{$node->server}'");
                continue;
            }

            $zoneId = $this->getDomainZoneId($parsedPool['domainPool'][$targetRootDomain] ?? [], $targetRootDomain);
            if (!$zoneId) {
                Log::error("[Node API] Skip DNS sync for node {$id}: Zone ID missing for {$targetRootDomain}");
                continue;
            }

            // Expected blueprints for this node
            $blueprints = [];
            if ($node->ip) {
                $blueprints[] = [
                    'type' => 'A',
                    'subdomain' => 'node' . $node->id,
                    'content' => $node->ip,
                    'root_domain' => $targetRootDomain,
                    'zone_id' => $zoneId
                ];
            }
            if ($node->ipv6) {
                $blueprints[] = [
                    'type' => 'AAAA',
                    'subdomain' => 'ipv6node' . $node->id,
                    'content' => $node->ipv6,
                    'root_domain' => $targetRootDomain,
                    'zone_id' => $zoneId
                ];
            }

            foreach ($blueprints as $blueprint) {
                $results[] = $this->reconcileThreeWay(
                    $node->id, $blueprint, $dnsProvider, $force
                );
            }

            // Cleanup orphaned records in DB (e.g. node previously had IPv4, now only IPv6)
            $expectedTypes = array_column($blueprints, 'type');
            $orphans = DnsRecord::where('node_id', $node->id)
                ->whereNotIn('record_type', $expectedTypes)
                ->get();
            
            foreach ($orphans as $orphan) {
                $orphanZoneId = $this->getDomainZoneId($parsedPool['domainPool'][$orphan->root_domain] ?? [], $orphan->root_domain);
                if ($orphanZoneId) {
                    $dnsProvider->deleteRecord($orphan->cf_record_id, $orphanZoneId);
                }
                $orphan->delete();
                $results[] = ['action' => 'deleted_orphan', 'success' => true, 'node_id' => $node->id, 'type' => $orphan->record_type];
            }
        }

        $allSuccess = !empty($results) && array_reduce($results, function ($carry, $r) {
            return $carry && ($r['success'] ?? true);
        }, true);

        return response()->json([
            'status' => $allSuccess ? 'success' : 'error',
            'results' => $results,
            'message' => $allSuccess ? 'DNS cluster synchronized' : 'Some DNS operations failed'
        ]);
    }

    /**
     * Three-way reconciliation: Node State vs Local DB vs Cloudflare.
     */
    private function reconcileThreeWay($nodeId, $blueprint, $dnsProvider, $force = false)
    {
        $localRecord = DnsRecord::where('node_id', $nodeId)
            ->where('record_type', $blueprint['type'])
            ->first();

        // Scene A: Cache Blocking (Performance optimization)
        if (!$force && $localRecord) {
            $isMatch = ($localRecord->subdomain === $blueprint['subdomain'] &&
                        $localRecord->root_domain === $blueprint['root_domain'] &&
                        $localRecord->ip_addr === $blueprint['content']);
            
            if ($isMatch) {
                return [
                    'action' => 'no_change',
                    'success' => true,
                    'node_id' => $nodeId,
                    'type' => $blueprint['type'],
                    'message' => 'Cache hit: state matches blueprint'
                ];
            }
        }

        // Scene B: Subdomain or Root Domain changed (ID Recycling / Dirty Data Cleanup)
        // If domain changed, we MUST DELETE the old one and POST a new one.
        if ($localRecord && ($localRecord->subdomain !== $blueprint['subdomain'] || $localRecord->root_domain !== $blueprint['root_domain'])) {
            $oldZoneId = $this->getDomainZoneIdForRecord($localRecord);
            if ($oldZoneId) {
                $dnsProvider->deleteRecord($localRecord->cf_record_id, $oldZoneId);
            }
            $localRecord->delete();
            $localRecord = null; // Proceed to Scene C: Creation
        }

        // Scene C: Creation or Re-creation
        if (!$localRecord) {
            $cfResult = $dnsProvider->createRecord(
                $blueprint['root_domain'],
                $blueprint['subdomain'],
                $blueprint['content'],
                $blueprint['type'],
                $blueprint['zone_id']
            );

            if (!$cfResult) {
                return ['action' => 'create_failed', 'success' => false, 'node_id' => $nodeId, 'type' => $blueprint['type']];
            }

            DnsRecord::create([
                'node_id' => $nodeId,
                'root_domain' => $blueprint['root_domain'],
                'subdomain' => $blueprint['subdomain'],
                'record_type' => $blueprint['type'],
                'ip_addr' => $blueprint['content'],
                'cf_record_id' => $cfResult['id'],
            ]);

            return ['action' => 'created', 'success' => true, 'node_id' => $nodeId, 'type' => $blueprint['type']];
        }

        // Scene D: IP Address Update (Same domain, diff IP)
        $cfResult = $dnsProvider->updateRecordById(
            $localRecord->cf_record_id,
            $blueprint['root_domain'],
            $blueprint['subdomain'],
            $blueprint['content'],
            $blueprint['type'],
            $blueprint['zone_id']
        );

        if (!$cfResult) {
            // Ghost sync check: if update failed because ID is invalid, clear cache to force creation next time
            Log::warning("[Node API] DNS update failed for node {$nodeId}, clearing cache", ['cf_id' => $localRecord->cf_record_id]);
            $localRecord->delete();
            return ['action' => 'update_failed', 'success' => false, 'node_id' => $nodeId, 'type' => $blueprint['type']];
        }

        $localRecord->ip_addr = $blueprint['content'];
        $localRecord->save();

        return ['action' => 'updated_ip', 'success' => true, 'node_id' => $nodeId, 'type' => $blueprint['type']];
    }

    /**
     * Look up zone_id for an existing DnsRecord's root_domain from the domain pool.
     */
    private function parseServerField($server)
    {
        if (!$server || !strpos($server, '.')) {
            return null;
        }
        $parts = explode('.', $server, 2);
        return ['subdomain' => $parts[0], 'root_domain' => $parts[1]];
    }

    /**
     * Case 05: Type-preserved JSON injection
     */
    public function config(Request $request)
    {
        if ($err = $this->validateToken($request)) return $err;

        $nodeId = $request->input('node_id');
        $node = SsNode::find($nodeId);
        if (!$node) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);
        if ($node->status == 0) return response()->json(['status' => 'error', 'message' => 'Node is offline'], 403);

        $v2Name = $node->v2_name ?: 'vision-hy2-ws-grpc';
        $templatePath = resource_path("templates/xray/{$v2Name}.json");
        if (!file_exists($templatePath)) return response()->json(['status' => 'error', 'message' => 'Template not found'], 500);

        $rawTemplate = json_decode(file_get_contents($templatePath));
        $config = json_decode(json_encode($rawTemplate), true);

        // __nodeDomain__ replacement refers to root_domain (e.g. example.com)
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
            '__dbHost__' => env('DB_HOST', '127.0.0.1'),
            '__dbUser__' => env('DB_USERNAME', 'root'),
            '__dbPassword__' => env('DB_PASSWORD', ''),
            '__dbName__' => env('DB_DATABASE', 'npanel'),
        ];

        $config = $this->injectVariables($config, $vars);

        if (isset($config['ssrpanel'])) {
            $config['ssrpanel']['nodeId'] = (int)$node->id;
            $config['ssrpanel']['user']['inboundTags'] = $inboundTags;
            
            // Dynamic flows allocation
            if (in_array('vision', $expanded)) {
                $config['ssrpanel']['user']['flows'] = [
                    'proxy-vision' => 'xtls-rprx-vision'
                ];
            } else {
                unset($config['ssrpanel']['user']['flows']);
            }
        }

        if ($node->node_unlock) $this->applyUnlocks($config, $node->node_unlock);

        // Restore JSON object types lost by json_decode(..., true).
        // json_decode with true converts all {} to [] and {"0":{}} to [0=>[...]],
        // but Xray expects objects for stats, settings, policy.levels, etc.
        $config = $this->restoreJsonObjectTypes($config, $rawTemplate);

        // Mask sensitive data for logging
        $logVars = $vars;
        $logVars['__dbPassword__'] = '******';

        Log::debug('[Node API] 配置下发', [
            'node_id' => $node->id,
            'template' => $v2Name,
            'variables' => $logVars,
            'unlocks' => $node->node_unlock ? count(explode(',', $node->node_unlock)) : 0
        ]);

        return response()->json($config, 200, [], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
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

    /**
     * Recursively restore JSON object types lost by json_decode(..., true).
     *
     * Logic:
     * 1. If template is stdClass -> force data to (object), recurse children.
     * 2. If template is array -> keep data as array, zip-recurse children.
     * 3. If template is null/primitive:
     *    - If data is associative array -> force to (object), recurse children.
     *    - If data is sequential array -> keep as array, recurse children.
     *    - Else return as-is.
     */
    private function restoreJsonObjectTypes($data, $template)
    {
        // Case 1: Template is an object
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

        // Case 2: Template is an array
        if (is_array($template)) {
            if (is_array($data)) {
                foreach ($data as $i => $value) {
                    $tplValue = isset($template[$i]) ? $template[$i] : null;
                    $data[$i] = $this->restoreJsonObjectTypes($value, $tplValue);
                }
            }
            return $data;
        }

        // Case 3: Extra data or template-less recursion
        if (is_array($data)) {
            $isAssoc = !empty($data) && array_keys($data) !== range(0, count($data) - 1);
            if ($isAssoc) {
                $result = [];
                foreach ($data as $key => $value) {
                    $result[$key] = $this->restoreJsonObjectTypes($value, null);
                }
                return (object)$result;
            }
            
            // Sequential array
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
        // Support both comma-separated and query-string formats
        parse_str(str_replace(',', '&', $unlockStr), $unlocks);

        $sysConf = Helpers::systemConfig();

        foreach ($unlocks as $key => $value) {
            // Support 1, "1", "Yes", "true"
            $isEnabled = in_array(strtolower((string)$value), ['1', 'yes', 'true', 'on']);
            if ($isEnabled) {
                $service = strtolower(str_replace(['unlock', ' '], '', $key));
                
                // Special mapping for common aliases
                if ($service === 'chatgpt') $service = 'openai';

                $templatePath = resource_path("templates/xray/unlock/{$service}.json");
                
                if (file_exists($templatePath)) {
                    $rawTemplate = file_get_contents($templatePath);
                    
                    // Retrieve specific unlock config from DB
                    $addr = $sysConf["unlock_{$service}_address"] ?? '';
                    $port = $sysConf["unlock_{$service}_port"] ?? 8388;
                    $pwd = $sysConf["unlock_{$service}_password"] ?? '';
                    $method = $sysConf["unlock_{$service}_method"] ?? 'chacha20-ietf-poly1305';

                    if (empty($addr)) {
                        Log::warning("[Node API] Unlock service {$service} enabled but address is missing in DB.");
                        continue;
                    }

                    // Replace placeholders with DB values
                    $rawTemplate = str_replace(
                        ['__address__', '__port__', '__password__', '__method__'],
                        [$addr, (string)$port, $pwd, $method],
                        $rawTemplate
                    );

                    $unlockData = json_decode($rawTemplate, true);
                    if ($unlockData) {
                        // Merge outbounds
                        if (!empty($unlockData['outbounds'])) {
                            foreach ($unlockData['outbounds'] as $outbound) {
                                $config['outbounds'][] = $outbound;
                            }
                        }
                        // Merge routing rules
                        if (!empty($unlockData['routing']['rules'])) {
                            foreach ($unlockData['routing']['rules'] as $rule) {
                                $config['routing']['rules'][] = $rule;
                            }
                        }
                    }
                } else {
                    // Fallback to direct freedom outbound if no template exists
                    $config['outbounds'][] = [
                        'protocol' => 'freedom',
                        'settings' => ['domainStrategy' => 'UseIPv4'],
                        'tag' => 'outbound-' . $service
                    ];
                    $config['routing']['rules'][] = [
                        'type' => 'field',
                        'outboundTag' => 'outbound-' . $service,
                        'domain' => ["geosite:" . $service]
                    ];
                }
            }
        }
    }

    /**
     * Step 4: Real-time auditing with rxtx billing and health engine.
     */
    public function status(Request $request)
    {
        if ($err = $this->validateToken($request)) return $err;

        $nodeId = $request->input('node_id');
        $rawRx = (float)$request->input('raw_rx', 0);
        $rawTx = (float)$request->input('raw_tx', 0);

        $node = SsNode::find($nodeId);
        if (!$node) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);

        // Billing logic
        if ($node->node_rxtx == 'rxtx') {
            $rawTotal = ($rawRx + $rawTx) / 2;
        } else {
            $rawTotal = $rawTx;
        }

        // Three-state increment calculation
        $lastRaw = $node->last_raw_total ?: 0;
        if ($lastRaw == 0) {
            $incremental = 0;
        } elseif ($rawTotal < $lastRaw) {
            $incremental = $rawTotal;
        } else {
            $incremental = $rawTotal - $lastRaw;
        }
        $node->last_raw_total = $rawTotal;

        // Reset check
        $today = (int)date('d');
        $lastUpdate = $node->updated_at;
        if (($today == $node->reset_day && $lastUpdate->format('Y-m-d') != date('Y-m-d')) ||
            ($lastUpdate->day < $node->reset_day && $today >= $node->reset_day) ||
            ($lastUpdate->month != date('m') && $today >= $node->reset_day)) {
            $node->traffic_used = 0;
        }

        $node->traffic_used += $incremental;
        $node->server_uptime = (int)$request->input('server_uptime', $node->server_uptime);
        $node->bandwidth = (int)$request->input('node_bandwidth', $node->bandwidth);
        $node->heartbeat_at = date('Y-m-d H:i:s');

        // Health engine (no DivisionByZero)
        $passedDays = (int)date('d');
        $remainingDays = (int)date('t') - $passedDays + 1;

        $avgUsed = $node->traffic_used / max($passedDays, 1);
        $avgRemaining = ($node->traffic_limit - $node->traffic_used) / max($remainingDays, 1);
        $node->node_health = ($avgUsed > $avgRemaining) ? 0 : 1;

        // 120G Meltdown
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
