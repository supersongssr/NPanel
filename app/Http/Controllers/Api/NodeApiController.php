<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Models\SsNode;
use App\Http\Models\SsNodeLabel;
use App\Http\Models\Label;
use App\Components\Helpers;

class NodeApiController extends Controller
{
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
     * Case 01: Auth check
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
        $node->status = 0; // Maintenance
        $node->save();

        return response()->json(['node_id' => $node->id]);
    }

    /**
     * Step 1: Identity reconciliation and fission
     * Case 02: 4/8 total nodes, unique subdomains
     */
    public function register(Request $request)
    {
        $nodeId = $request->input('node_id');
        $token = $request->input('token');
        $v2Name = $request->input('v2_name', 'vision-hy2-ws-grpc');
        
        if ($token != env('API_TOKEN')) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
        }

        $node = SsNode::find($nodeId);
        if (!$node) {
            return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);
        }

        $sysConf = Helpers::systemConfig();
        $rootDomain = isset($sysConf['node_root_domain']) ? $sysConf['node_root_domain'] : 'example.com';

        // Update main node
        $node->v2_name = $v2Name;
        $node->billing_mode = $request->input('billing_mode', 'tx');
        $node->cpu = $request->input('cpu');
        $node->memory = $request->input('memory');
        $node->disk = $request->input('disk');
        $node->bandwidth = $request->input('bandwidth', 100);
        $node->node_unlock = $request->input('node_unlock', '');
        $node->info = $request->input('node_info', '');
        $node->level = $request->input('node_level', 1);
        $node->node_group = $request->input('node_group', 1);
        $node->node_cost = $request->input('node_cost', 0);
        $node->traffic_limit = $request->input('node_traffic_limit', 1000) * 1024 * 1024 * 1024;
        $node->reset_day = (int)$request->input('node_traffic_resetday', 1);
        $node->sort = $request->input('node_sort', 0);
        $node->traffic_rate = $request->input('node_traffic_rate', 1.0);
        $node->country_code = strtolower($request->input('node_country_code', 'un'));
        $node->ip = $request->input('node_ip', $node->ip);
        $node->ipv6 = $request->input('node_ipv6', $node->ipv6);
        $node->server = 'node' . $node->id . '.' . $rootDomain;
        $node->status = 1;
        $node->save();

        // Fission logic
        $protocols = ($v2Name == 'xhttp-hy2-ws-grpc') ? ['xhttp', 'hy2', 'ws', 'grpc'] : ['vision', 'hy2', 'ws', 'grpc'];
        $ips = [];
        if ($node->ip) $ips[] = ['type' => 'ipv4', 'addr' => $node->ip];
        if ($node->ipv6) $ips[] = ['type' => 'ipv6', 'addr' => $node->ipv6];

        $cloneIds = [];
        SsNode::where('is_clone', $nodeId)->delete();

        // Create clones to reach 4 (single stack) or 8 (dual stack) total nodes
        $totalTarget = count($protocols) * count($ips);
        $createdCount = 1; // Start with main node

        foreach ($ips as $ipInfo) {
            foreach ($protocols as $protocol) {
                if ($createdCount >= $totalTarget) break;
                
                $clone = new SsNode();
                $clone->name = $node->name . ' - ' . $protocol . ' (' . $ipInfo['type'] . ')';
                $clone->v2_name = $v2Name;
                $clone->is_clone = $nodeId;
                $clone->billing_mode = $node->billing_mode;
                $clone->ip = ($ipInfo['type'] == 'ipv4') ? $ipInfo['addr'] : '';
                $clone->ipv6 = ($ipInfo['type'] == 'ipv6') ? $ipInfo['addr'] : '';
                $clone->type = 3;
                $clone->level = $node->level;
                $clone->node_group = $node->node_group;
                $clone->traffic_rate = $node->traffic_rate;
                $clone->status = 1;
                $clone->save();
                
                $clone->server = 'node' . $clone->id . '.' . $rootDomain;
                $clone->save();
                
                $cloneIds[] = $clone->id;
                $createdCount++;
            }
        }

        return response()->json([
            'status' => 'success',
            'main_node_id' => $node->id,
            'clone_node_ids' => $cloneIds,
            'root_domain' => $rootDomain
        ]);
    }

    public function resolveDns(Request $request)
    {
        if ($err = $this->validateToken($request)) return $err;

        $nodeId = $request->input('node_id');
        $node = SsNode::find($nodeId);
        if (!$node) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);

        $sysConf = Helpers::systemConfig();
        $rootDomain = $sysConf['node_root_domain'] ?? 'example.com';
        $subdomain = 'node' . $nodeId;

        $dnsProvider = new \App\Components\DNS\CloudflareProvider();
        $success = true;
        if ($node->ip) $success = $success && $dnsProvider->updateRecord($rootDomain, $subdomain, $node->ip, 'A');
        if ($node->ipv6) $success = $success && $dnsProvider->updateRecord($rootDomain, $subdomain, $node->ipv6, 'AAAA');

        return response()->json(['status' => $success ? 'success' : 'error', 'message' => $success ? 'DNS updated' : 'DNS update failed']);
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
        $sysConf = Helpers::systemConfig();
        $rootDomain = $sysConf['node_root_domain'] ?? 'example.com';
        $nodeDomain = 'node' . ($node->is_clone ?: $node->id) . '.' . $rootDomain;

        $vars = [
            '__wsPath__' => '/ws' . $nodeId,
            '__wsPort__' => 10010, // Integer for Case 05
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
            // Case 05: If string is EXACTLY the placeholder and replacement is non-string, return replacement directly
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
        parse_str(str_replace(',', '&', $unlockStr), $unlocks);
        foreach ($unlocks as $key => $value) {
            if ($value == 1) {
                $tag = str_replace('unlock', '', $key);
                $config['outbounds'][] = ['protocol' => 'freedom', 'settings' => ['domainStrategy' => 'UseIPv4'], 'tag' => 'outbound-' . $tag];
                $config['routing']['rules'][] = ['type' => 'field', 'outboundTag' => 'outbound-' . $tag, 'domain' => ["geosite:" . strtolower($tag)]];
            }
        }
    }

    /**
     * Step 4: Real-time auditing
     * Case 03: DivisionByZero check
     * Case 04: rxtx billing
     */
    public function status(Request $request)
    {
        if ($err = $this->validateToken($request)) return $err;

        $nodeId = $request->input('node_id');
        $rawRx = (float)$request->input('raw_rx', 0);
        $rawTx = (float)$request->input('raw_tx', 0);

        $node = SsNode::find($nodeId);
        if (!$node) return response()->json(['status' => 'error', 'message' => 'Node not found'], 404);

        // Case 04: Billing logic
        if ($node->billing_mode == 'rxtx') {
            $rawTotal = ($rawRx + $rawTx) / 2;
        } else {
            $rawTotal = $rawTx;
        }

        // Three-state increment calculation
        $lastRaw = $node->last_raw_total ?: 0;
        if ($lastRaw == 0) {
            // Scene A: First report, establish baseline — zero increment
            $incremental = 0;
        } elseif ($rawTotal < $lastRaw) {
            // Scene B: NIC reboot, counter reset
            $incremental = $rawTotal;
        } else {
            // Scene C: Normal accumulation
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

        // Case 03: No DivisionByZero
        $daysInMonth = (int)date('t');
        $passedDays = (int)date('d');
        $remainingDays = $daysInMonth - $passedDays + 1;

        $avgUsed = $node->traffic_used / max($passedDays, 1);
        $avgRemaining = ($node->traffic_limit - $node->traffic_used) / max($remainingDays, 1);
        $node->health = ($avgUsed > $avgRemaining) ? 0 : 1;

        // 120G Meltdown (Status)
        if (($node->traffic_limit - $node->traffic_used) < (120 * 1024 * 1024 * 1024)) {
            $node->status = 0;
        }

        $node->save();
        return response()->json(['status' => 'success', 'node_status' => $node->status]);
    }
}
