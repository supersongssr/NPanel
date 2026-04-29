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
    const DOMAIN_CAPACITY_LIMIT = 180;

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
        $v2Name = ($nodeMemory > $thresholdMb) ? $highProto : $lowProto;

        $rootDomain = $this->resolveDomainAffinity($request->input('root_domain'), $sysConf);

        // --- Tiered level engine: main node level = max(1, floor(cost)) ---
        $nodeCost = (float)$request->input('node_cost', 0);
        $mainLevel = max(1, (int)floor($nodeCost));

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
        $mainSlot = array_shift($slots);
        $node->v2_name = $mainSlot['protocol'];
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
        $node->node_ids = json_encode($allNodeIds);
        $node->save();

        return response()->json([
            'status' => 'success',
            'main_node_id' => $node->id,
            'clone_node_ids' => $cloneIds,
            'node_ids' => $allNodeIds,
            'root_domain' => $rootDomain,
            'v2_name' => $v2Name,
        ]);
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
     * Priority 1: Reuse reported root_domain if its dns_records count < 180.
     * Priority 2: Pick first available domain from pool if count < 180.
     * Fallback: Use node_root_domain from config.
     */
    private function resolveDomainAffinity($reportedDomain, $sysConf)
    {
        $primaryDomain = $sysConf['node_root_domain'] ?? 'example.com';

        // Build pool: primary first, then JSON array backups
        $domainPool = [$primaryDomain];
        if (!empty($sysConf['node_domain_pool'])) {
            $decoded = json_decode($sysConf['node_domain_pool'], true);
            if (is_array($decoded)) {
                $domainPool = array_merge($domainPool, $decoded);
            }
        }
        $domainPool = array_unique(array_filter($domainPool));

        // Priority 1: Check reported domain first (Allow custom affinity)
        if ($reportedDomain) {
            $count = DnsRecord::where('root_domain', $reportedDomain)->count();
            if ($count < self::DOMAIN_CAPACITY_LIMIT) {
                return $reportedDomain;
            }
        }

        // Priority 2: Pick first available domain from pool
        foreach ($domainPool as $domain) {
            $count = DnsRecord::where('root_domain', $domain)->count();
            if ($count < self::DOMAIN_CAPACITY_LIMIT) {
                return $domain;
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

        $dnsProvider = app(\App\Components\DNS\CloudflareProvider::class);
        $results = [];

        // Process each IP type the node has
        $ipEntries = [];
        if ($node->ip) $ipEntries[] = ['type' => 'A', 'addr' => $node->ip];
        if ($node->ipv6) $ipEntries[] = ['type' => 'AAAA', 'addr' => $node->ipv6];

        foreach ($ipEntries as $entry) {
            $result = $this->reconcileDnsRecord(
                $nodeId, $dnsProvider, $targetSubdomain, $targetRootDomain, $entry['type'], $entry['addr']
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
    private function reconcileDnsRecord($nodeId, $dnsProvider, $targetSubdomain, $targetRootDomain, $type, $targetIp)
    {
        $oldRecord = DnsRecord::where('node_id', $nodeId)
            ->where('record_type', $type)
            ->first();

        // Scene A: No old record exists — pure creation
        if (!$oldRecord) {
            return $this->createDnsRecord($dnsProvider, $nodeId, $targetSubdomain, $targetRootDomain, $type, $targetIp);
        }

        $sameDomain = ($oldRecord->root_domain === $targetRootDomain && $oldRecord->subdomain === $targetSubdomain);
        $sameIp = ($oldRecord->ip_addr === $targetIp);

        // Scene A: Identical — no-op, intercept CF call
        if ($sameDomain && $sameIp) {
            return ['action' => 'no_change', 'success' => true, 'message' => 'DNS already up-to-date'];
        }

        // Scene B: Same domain, different IP — PUT update
        if ($sameDomain && !$sameIp) {
            return $this->updateDnsRecordIp($dnsProvider, $oldRecord, $targetIp);
        }

        // Scene C: Domain changed — POST new + DELETE old
        return $this->swapDnsRecord($dnsProvider, $oldRecord, $nodeId, $targetSubdomain, $targetRootDomain, $type, $targetIp);
    }

    /**
     * Create a brand new DNS record via CF, then persist to dns_records.
     */
    private function createDnsRecord($dnsProvider, $nodeId, $subdomain, $rootDomain, $type, $ip)
    {
        $cfResult = $dnsProvider->createRecord($rootDomain, $subdomain, $ip, $type);
        if (!$cfResult) {
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
            'cf_zone_id' => $cfResult['zone_id'] ?? null,
        ]);

        return ['action' => 'created', 'success' => true, 'message' => 'DNS record created'];
    }

    /**
     * Scene B: Update IP on existing record. CF PUT first, then update DB.
     */
    private function updateDnsRecordIp($dnsProvider, $oldRecord, $newIp)
    {
        $cfResult = $dnsProvider->updateRecordById(
            $oldRecord->cf_record_id,
            $oldRecord->root_domain,
            $oldRecord->subdomain,
            $newIp,
            $oldRecord->record_type
        );
        if (!$cfResult) {
            return ['action' => 'update_failed', 'success' => false, 'message' => 'CF update failed'];
        }

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
    private function swapDnsRecord($dnsProvider, $oldRecord, $nodeId, $newSubdomain, $newRootDomain, $type, $newIp)
    {
        // Step 1: Create new record in CF (POST first to ensure target survives failure)
        $cfResult = $dnsProvider->createRecord($newRootDomain, $newSubdomain, $newIp, $type);
        if (!$cfResult) {
            Log::error("resolve_dns: CF POST failed for node {$nodeId}, aborting swap to preserve old record");
            return ['action' => 'swap_failed', 'success' => false, 'message' => 'CF create failed, swap aborted'];
        }

        // Step 2: Delete old record from CF (POST succeeded, so old is now redundant)
        $deleteOk = $dnsProvider->deleteRecord($oldRecord->cf_record_id);
        if (!$deleteOk) {
            Log::warning("resolve_dns: CF DELETE failed for record {$oldRecord->cf_record_id} after successful POST. Orphan left on CF.");
        }

        // Step 3: Atomic replacement in local DB
        $oldRecord->delete();
        DnsRecord::create([
            'node_id' => $nodeId,
            'root_domain' => $newRootDomain,
            'subdomain' => $newSubdomain,
            'record_type' => $type,
            'ip_addr' => $ip,
            'cf_record_id' => $cfResult['id'],
            'cf_zone_id' => $cfResult['zone_id'] ?? null,
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
        return response()->json(['status' => 'success', 'node_status' => $node->status]);
    }
}
