<?php

require 'vendor/autoload.php';
require 'tests/CreatesApplication.php';
$app = (new class { use \Tests\CreatesApplication; })->createApplication();

use App\Http\Models\SsNode;
use App\Http\Controllers\Api\NodeApiController;
use Illuminate\Http\Request;

function mockRequest($method, $data = []) {
    return Request::create('/api/node', $method, $data);
}

$controller = new NodeApiController();
$token = env('API_TOKEN');

echo "--- 🟢 Case 01: Auth & ID Allocation ---\n";
// No token
$req0_fail = mockRequest('POST', ['node_ip' => '1.2.3.4']);
$res0_fail = $controller->applyId($req0_fail);
echo "Auth Fail Code: " . $res0_fail->getStatusCode() . " (Expected 401)\n";

// With token
$req0_success = mockRequest('POST', ['node_ip' => '1.2.3.4', 'node_ipv6' => '::1', 'token' => $token]);
$res0_success = $controller->applyId($req0_success);
$data0 = json_decode($res0_success->getContent(), true);
$nodeId = $data0['node_id'];
echo "Apply ID Success: Node ID $nodeId\n";

echo "\n--- 🟢 Case 02: Fission & Subdomain Independence ---\n";
$req1 = mockRequest('POST', [
    'node_id' => $nodeId,
    'token' => $token,
    'v2_name' => 'vision-hy2-ws-grpc',
    'node_ip' => '1.2.3.4',
    'node_ipv6' => '::1'
]);
$res1 = $controller->register($req1);
$data1 = json_decode($res1->getContent(), true);
$totalNodes = count($data1['clone_node_ids']) + 1;
echo "Total Nodes Generated: $totalNodes (Expected 8)\n";

// Check subdomains
$nodes = SsNode::where('id', $nodeId)->orWhere('is_clone', $nodeId)->get();
$servers = $nodes->pluck('server')->toArray();
$uniqueServers = array_unique($servers);
echo "Unique Servers: " . count($uniqueServers) . " (Expected " . count($servers) . ")\n";

echo "\n--- 🔴 Case 03: Division by Zero & Meltdown ---\n";
// Call register to set everything up correctly including traffic_limit
$controller->register(mockRequest('POST', [
    'node_id' => $nodeId,
    'token' => $token,
    'node_traffic_limit' => 2000,
    'node_traffic_resetday' => (int)date('d'),
]));

$node = SsNode::find($nodeId);
$node->traffic_used = 0;
$node->save();

echo "Limit in DB: " . ($node->traffic_limit / 1024 / 1024 / 1024) . " GB\n";

// Reported huge traffic to trigger meltdown (跌破 120GB)
$req3 = mockRequest('POST', [
    'node_id' => $nodeId,
    'raw_rx' => 1950 * 1024 * 1024 * 1024,
    'raw_tx' => 0,
    'server_uptime' => 1000
]);
$res3 = $controller->status($req3);
$node = SsNode::find($nodeId);
echo "Status after meltdown: " . $node->status . " (Expected 0)\n";
echo "Health calculated: " . $node->health . "\n";

echo "\n--- 🟡 Case 04: Billing Formula (rxtx) ---\n";
$node->billing_mode = 'rxtx';
$node->last_raw_total = 0;
$node->traffic_used = 0;
$node->save();

// raw_rx +10GB, raw_tx +30GB -> (10+30)/2 = 20GB incremental
$req4 = mockRequest('POST', [
    'node_id' => $nodeId,
    'raw_rx' => 10 * 1024 * 1024 * 1024,
    'raw_tx' => 30 * 1024 * 1024 * 1024,
]);
$controller->status($req4);
$node = SsNode::find($nodeId);
echo "Traffic Used: " . ($node->traffic_used / 1024 / 1024 / 1024) . " GB (Expected 20)\n";

echo "\n--- 🔴 Case 05: Type Integrity in JSON ---\n";
// Reset status to 1 for config access
$node->status = 1;
$node->save();

$req5 = mockRequest('GET', ['node_id' => $nodeId]);
$res5 = $controller->config($req5);
$config = json_decode($res5->getContent(), true);

$destType = gettype($config['inbounds'][0]['settings']['fallbacks'][0]['dest']);
$tagsType = gettype($config['ssrpanel']['user']['inboundTags']);

echo "Dest Type: $destType (Expected integer or double/number)\n";
echo "InboundTags Type: $tagsType (Expected array)\n";

if ($destType !== 'integer' && $destType !== 'double') {
    echo "❌ Case 05 Failed: Dest is not a number!\n";
}
if ($tagsType !== 'array') {
    echo "❌ Case 05 Failed: InboundTags is not an array!\n";
}

// Cleanup
SsNode::where('id', $nodeId)->delete();
SsNode::where('is_clone', $nodeId)->delete();
echo "\nCleanup done.\n";
