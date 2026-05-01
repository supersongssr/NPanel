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

        $node = new SsNode();
        $node->name = 'New Node ' . ($nodeIp ?: $nodeIpv6);
        $node->ip = $nodeIp ?: '';
        $node->ipv6 = $nodeIpv6 ?: '';
        $node->status = 0;
        $node->save();

        Log::info('[Node API] ID 申请成功', [
            'ip' => $request->ip(),
            'new_node_id' => $node->id
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
        $node->bandwidth = $request->input('bandwidth', 100);
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
                'main_node_id' => $node->id,
                'clone_node_ids' => [],
                'root_domain' => $rootDomain,
                'v2_name' => $v2Name,
            ]);
        }

        // --- Clone reuse (anti-proliferation) ---
        $existingClones = SsNode::where('is_clone', $nodeId)->get()->values();

        $cloneIds = [];

        // Slot 0 = main node itself (first protocol, first IP)
        // Keep original v2_name (e.g. "xhttp-hy2-ws-grpc") on main node for template lookup.
        // Only clone nodes get single-protocol v2_name.
        $mainSlot = array_shift($slots);
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
                $clone->save();
            } else {
                // Need to create new clone
                $clone = new SsNode();
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
                $clone->save();
            }

            $cloneIds[] = $clone->id;
            $allNodeIds[] = $clone->id;
        }

        // Delete excess old clones if any
        if ($existingClones->count() > count($slots)) {
            foreach ($existingClones->skip(count($slots)) as $excess) {
                $excess->delete();
            }
        }

        // --- Seal node_ids into main node ---
        $nodeIdsStr = implode(',', $allNodeIds);
        $node->node_ids = $nodeIdsStr;
        $node->save();

        Log::info('[Node API] 节点注册成功', [
            'main_node_id' => $node->id,
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
            'main_node_id' => $node->id,
            'clone_node_ids' => $cloneIds,
            'node_ids' => $nodeIdsStr,
            'root_domain' => $rootDomain,
            'v2_name' => $v2Name,
            'proxy_host' => 'npanel-nav.freessr.bid',
        ];

        // --- xhttp-hy2-ws-grpc mode: emit paths and fixed ports ---
        $expanded = $this->expandProtocols($v2Name);
        if (in_array('xhttp', $expanded) || in_array('ws', $expanded) || in_array('grpc', $expanded)) {
            $response['ws_path'] = '/ws' . $nodeId;
            $response['ws_port'] = 10011;
            $response['grpc_service_name'] = 'grpc' . $nodeId;
            $response['grpc_port'] = 10012;
            $response['xhttp_path'] = '/xhttp' . $nodeId;
            $response['xhttp_port'] = 10013;
        }

        return response()->json($response);
    }

    /**
     * Expand a protocol group string into individual protocol names.
     * e.g. "vision-hy2-ws-grpc" → ["vision", "hy2", "ws", "grpc"]
     */
    private function expandProtocols($v2Name)
    {
        return explode('-', $v2Name);
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
     * Step 2: DB-Driven Diff DNS resolution.
     *
     * Reads target state from ss_node, current state from dns_records.
     * Only calls CF API when diff is detected.
     * Only updates dns_records AFTER CF API confirms success.
     */
    public function resolveDns(Request $request)
    {
        if ($err = $this->validateToken($request)) return $err;

        $nodeId = $request->input('node_id');
        $node = SsNode::find($nodeId);
        if (!$node) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);

        // Parse target state from ss_node.server (format: node{id}.{root_domain})
        $targetParts = $this->parseServerField($node->server);
        if (!$targetParts) {
            return response()->json(['status' => 'error', 'message' => 'Invalid server field format'], 500);
        }
        $targetSubdomain = $targetParts['subdomain'];
        $targetRootDomain = $targetParts['root_domain'];

        // Look up zone_id from domain pool for this root domain
        $sysConf = Helpers::systemConfig();
        $parsed = $this->parseDomainPool($sysConf);
        $zoneId = $this->getDomainZoneId($parsed['domainPool'][$targetRootDomain] ?? [], $targetRootDomain);

        if (!$zoneId) {
            return response()->json(['status' => 'error', 'message' => "Zone ID missing for domain {$targetRootDomain}"], 500);
        }

        $dnsProvider = app(\App\Components\DNS\CloudflareProvider::class);
        $results = [];

        // Process each IP type the node has
        $ipEntries = [];
        if ($node->ip) $ipEntries[] = ['type' => 'A', 'addr' => $node->ip];
        if ($node->ipv6) $ipEntries[] = ['type' => 'AAAA', 'addr' => $node->ipv6];

        foreach ($ipEntries as $entry) {
            $result = $this->reconcileDnsRecord(
                $nodeId, $dnsProvider, $targetSubdomain, $targetRootDomain, $entry['type'], $entry['addr'], $zoneId
            );
            $results[] = $result;
        }

        $allSuccess = !empty($results) && array_reduce($results, function ($carry, $r) {
            return $carry && $r['success'];
        }, true);

        $actions = array_map(function ($r) {
            return $r['action'];
        }, $results);

        return response()->json([
            'status' => $allSuccess ? 'success' : 'error',
            'actions' => $actions,
            'message' => $allSuccess ? 'DNS reconciled' : 'Some DNS operations failed',
        ]);
    }

    /**
     * Reconcile a single DNS record (one node × one IP type).
     *
     * Scene A: Same domain + same IP → skip CF entirely.
     * Scene B: Same domain + diff IP → PUT update via CF.
     * Scene C: Diff domain → POST new + DELETE old via CF (Atomic swap).
     */
    private function reconcileDnsRecord($nodeId, $dnsProvider, $targetSubdomain, $targetRootDomain, $type, $targetIp, $zoneId)
    {
        $oldRecord = DnsRecord::where('node_id', $nodeId)
            ->where('record_type', $type)
            ->first();

        // Scene A: No old record exists — pure creation
        if (!$oldRecord) {
            return $this->createDnsRecord($dnsProvider, $nodeId, $targetSubdomain, $targetRootDomain, $type, $targetIp, $zoneId);
        }

        $sameDomain = ($oldRecord->root_domain === $targetRootDomain && $oldRecord->subdomain === $targetSubdomain);
        $sameIp = ($oldRecord->ip_addr === $targetIp);

        // Scene A: Identical — no-op, intercept CF call
        if ($sameDomain && $sameIp) {
            Log::info('[Node API] DNS 对账跳过', [
                'node_id' => $nodeId,
                'domain' => $targetRootDomain,
                'subdomain' => $targetSubdomain,
                'ip' => $targetIp,
                'reason' => 'Identical'
            ]);
            return ['action' => 'no_change', 'success' => true, 'message' => 'DNS already up-to-date'];
        }

        // Scene B: Same domain, different IP — PUT update
        if ($sameDomain && !$sameIp) {
            return $this->updateDnsRecordIp($dnsProvider, $oldRecord, $targetIp, $zoneId);
        }

        // Scene C: Domain changed — POST new + DELETE old
        $oldZoneId = $this->getDomainZoneIdForRecord($oldRecord);
        if (!$oldZoneId) {
            return ['action' => 'swap_failed', 'success' => false, 'message' => "Old domain Zone ID missing: {$oldRecord->root_domain}"];
        }
        return $this->swapDnsRecord($dnsProvider, $oldRecord, $nodeId, $targetSubdomain, $targetRootDomain, $type, $targetIp, $oldZoneId, $zoneId);
    }

    /**
     * Look up zone_id for an existing DnsRecord's root_domain from the domain pool.
     */
    private function getDomainZoneIdForRecord($record)
    {
        $sysConf = Helpers::systemConfig();
        $parsed = $this->parseDomainPool($sysConf);
        return $this->getDomainZoneId($parsed['domainPool'][$record->root_domain] ?? [], $record->root_domain);
    }

    /**
     * Create a brand new DNS record via CF, then persist to dns_records.
     */
    private function createDnsRecord($dnsProvider, $nodeId, $subdomain, $rootDomain, $type, $ip, $zoneId)
    {
        $cfResult = $dnsProvider->createRecord($rootDomain, $subdomain, $ip, $type, $zoneId);
        if (!$cfResult) {
            Log::error("[Node API] DNS 创建失败 (CF API Error)", [
                'node_id' => $nodeId,
                'domain' => $subdomain . '.' . $rootDomain,
                'type' => $type,
                'ip' => $ip
            ]);
            return ['action' => 'create_failed', 'success' => false, 'message' => 'CF create failed'];
        }

        // CF success → persist locally
        DnsRecord::create([
            'node_id' => $nodeId,
            'root_domain' => $rootDomain,
            'subdomain' => $subdomain,
            'record_type' => $type,
            'ip_addr' => $ip,
            'cf_record_id' => $cfResult['id'],
        ]);

        Log::info('[Node API] DNS 记录创建成功', [
            'node_id' => $nodeId,
            'domain' => $subdomain . '.' . $rootDomain,
            'type' => $type,
            'ip' => $ip,
            'cf_id' => $cfResult['id']
        ]);

        return ['action' => 'created', 'success' => true, 'message' => 'DNS record created'];
    }

    /**
     * Scene B: Update IP on existing record. CF PUT first, then update DB.
     */
    private function updateDnsRecordIp($dnsProvider, $oldRecord, $newIp, $zoneId)
    {
        $cfResult = $dnsProvider->updateRecordById(
            $oldRecord->cf_record_id,
            $oldRecord->root_domain,
            $oldRecord->subdomain,
            $newIp,
            $oldRecord->record_type,
            $zoneId
        );
        if (!$cfResult) {
            Log::error("[Node API] DNS 更新失败 (CF API Error)", [
                'node_id' => $oldRecord->node_id,
                'domain' => $oldRecord->subdomain . '.' . $oldRecord->root_domain,
                'old_ip' => $oldRecord->ip_addr,
                'new_ip' => $newIp
            ]);
            return ['action' => 'update_failed', 'success' => false, 'message' => 'CF update failed'];
        }

        Log::info('[Node API] DNS IP 更新成功', [
            'node_id' => $oldRecord->node_id,
            'domain' => $oldRecord->subdomain . '.' . $oldRecord->root_domain,
            'old_ip' => $oldRecord->ip_addr,
            'new_ip' => $newIp
        ]);

        // CF success → update local record
        $oldRecord->ip_addr = $newIp;
        $oldRecord->cf_record_id = $cfResult['id'];
        $oldRecord->save();

        return ['action' => 'updated_ip', 'success' => true, 'message' => 'IP updated'];
    }

    /**
     * Scene C: Domain changed. POST new to CF first, then DELETE old.
     * This ensures the target survives if deletion fails.
     */
    private function swapDnsRecord($dnsProvider, $oldRecord, $nodeId, $newSubdomain, $newRootDomain, $type, $newIp, $oldZoneId, $newZoneId)
    {
        // Step 1: Create new record in CF (POST first to ensure target survives failure)
        $cfResult = $dnsProvider->createRecord($newRootDomain, $newSubdomain, $newIp, $type, $newZoneId);
        if (!$cfResult) {
            Log::error("[Node API] DNS 跨域迁移失败 (CF POST Error)", [
                'node_id' => $nodeId,
                'old_domain' => $oldRecord->subdomain . '.' . $oldRecord->root_domain,
                'new_domain' => $newSubdomain . '.' . $newRootDomain,
                'ip' => $newIp
            ]);
            return ['action' => 'swap_failed', 'success' => false, 'message' => 'CF create failed, swap aborted'];
        }

        // Step 2: Delete old record from CF (POST succeeded, so old is now redundant)
        $deleteOk = $dnsProvider->deleteRecord($oldRecord->cf_record_id, $oldZoneId);
        if (!$deleteOk) {
            Log::warning("[Node API] DNS 旧记录清理失败 (Orphan left on CF)", [
                'cf_id' => $oldRecord->cf_record_id,
                'domain' => $oldRecord->subdomain . '.' . $oldRecord->root_domain
            ]);
        }

        Log::info('[Node API] DNS 跨域迁移成功', [
            'node_id' => $nodeId,
            'old_domain' => $oldRecord->subdomain . '.' . $oldRecord->root_domain,
            'new_domain' => $newSubdomain . '.' . $newRootDomain,
            'ip' => $newIp
        ]);

        // Step 3: Atomic replacement in local DB
        $oldRecord->delete();
        DnsRecord::create([
            'node_id' => $nodeId,
            'root_domain' => $newRootDomain,
            'subdomain' => $newSubdomain,
            'record_type' => $type,
            'ip_addr' => $newIp,
            'cf_record_id' => $cfResult['id'],
        ]);

        return ['action' => 'swapped_domain', 'success' => true, 'message' => 'Domain swapped'];
    }

    /**
     * Parse node.server field "node123.example.com" into subdomain + root_domain.
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

        $config = json_decode(file_get_contents($templatePath), true);
        $nodeDomain = $node->server;

        $vars = [
            '__wsPath__' => '/ws' . $nodeId,
            '__wsPort__' => 10010,
            '__v2Fallback__' => '127.0.0.1',
            '__nodeDomain__' => $nodeDomain,
            '__HYSTERIA_URL__' => 'https://www.bing.com',
            '__v2ServiceName__' => 'grpc' . $nodeId,
            '__inboundTags__' => ['proxy-vision', 'proxy-hy2', 'proxy-ws', 'proxy-grpc'],
            '__vlessVisionTag__' => 'proxy-vision',
            '__v2Flow__' => 'xtls-rprx-vision',
            '__dbHost__' => env('DB_HOST', '127.0.0.1'),
            '__dbUser__' => env('DB_USERNAME', 'root'),
            '__dbPassword__' => env('DB_PASSWORD', ''),
            '__dbName__' => env('DB_DATABASE', 'npanel'),
        ];

        $config = $this->injectVariables($config, $vars);
        if (isset($config['ssrpanel'])) $config['ssrpanel']['nodeId'] = (int)$node->id;
        if ($node->node_unlock) $this->applyUnlocks($config, $node->node_unlock);

        // Mask sensitive data for logging
        $logVars = $vars;
        $logVars['__dbPassword__'] = '******';

        Log::debug('[Node API] 配置下发', [
            'node_id' => $node->id,
            'template' => $v2Name,
            'variables' => $logVars,
            'unlocks' => $node->node_unlock ? count(explode(',', $node->node_unlock)) : 0
        ]);

        return response()->json($config);
    }

    private function injectVariables($data, $vars)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === 'inboundTags' && is_array($value) && count($value) === 1 && $value[0] === '__inboundTags__') {
                    $data[$key] = $vars['__inboundTags__'];
                    continue;
                }
                $data[$key] = $this->injectVariables($value, $vars);
            }
            if (isset($data['flows']) && isset($data['flows']['__vlessVisionTag__'])) {
                $data['flows'][$vars['__vlessVisionTag__']] = $vars['__v2Flow__'];
                unset($data['flows']['__vlessVisionTag__']);
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

    private function applyUnlocks(&$config, $unlockStr)
    {
        $unlocks = [];
        // Support both comma-separated and query-string formats
        parse_str(str_replace(',', '&', $unlockStr), $unlocks);

        foreach ($unlocks as $key => $value) {
            // Support 1, "1", "Yes", "true"
            $isEnabled = in_array(strtolower((string)$value), ['1', 'yes', 'true', 'on']);
            if ($isEnabled) {
                $tag = str_replace(['unlock', ' '], '', $key);
                $config['outbounds'][] = [
                    'protocol' => 'freedom',
                    'settings' => ['domainStrategy' => 'UseIPv4'],
                    'tag' => 'outbound-' . $tag
                ];
                $config['routing']['rules'][] = [
                    'type' => 'field',
                    'outboundTag' => 'outbound-' . $tag,
                    'domain' => ["geosite:" . strtolower($tag)]
                ];
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
