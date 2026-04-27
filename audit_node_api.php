<?php

use App\Http\Models\SsNode;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$apiToken = env('API_TOKEN');
$host = 'test-npanel.freessr.bid';

function callApi($method, $path, $data = []) {
    global $host;
    $url = "https://151.245.106.151/api/" . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Host: $host"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } else if ($method == 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true), 'raw' => $response];
}

$failures = [];

echo "--- Case 01: Token Protection ---\n";
$res = callApi('POST', 'node/apply_id', ['node_ip' => '1.2.3.4']);
if ($res['code'] !== 401 && $res['code'] !== 403) {
    $failures[] = "Case 01: apply_id allowed without token. Code: {$res['code']}, Output: {$res['raw']}";
} else {
    echo "[PASS] apply_id blocked without token\n";
}

$res = callApi('POST', 'node/status', ['node_id' => 1, 'raw_rx' => 100, 'raw_tx' => 100]);
if ($res['code'] !== 401 && $res['code'] !== 403) {
    $failures[] = "Case 01: status allowed without token. Code: {$res['code']}, Output: {$res['raw']}";
} else {
    echo "[PASS] status blocked without token\n";
}

echo "\n--- Case 02: Node Cloning & Subdomains ---\n";
$res = callApi('POST', 'node/apply_id', ['token' => $apiToken, 'node_ip' => '1.2.3.4', 'node_ipv6' => '2001:db8::1']);
$nodeId = $res['body']['node_id'] ?? null;
if (!$nodeId) {
    die("ERROR: Could not apply node ID for Case 02\n");
}

callApi('POST', 'node/register', [
    'token' => $apiToken,
    'node_id' => $nodeId,
    'v2_name' => 'vision-hy2-ws-grpc',
    'node_ip' => '1.2.3.4',
    'node_ipv6' => '2001:db8::1',
    'node_traffic_limit' => 1000,
    'billing_mode' => 'rxtx'
]);

$clones = SsNode::where('is_clone', $nodeId)->get();
$totalNodes = 1 + $clones->count();
if ($totalNodes !== 8) {
    $failures[] = "Case 02: Expected 8 nodes (1 main + 7 clones), found $totalNodes";
} else {
    echo "[PASS] 8 nodes created\n";
}

$mainNode = SsNode::find($nodeId);
$subdomains = [$mainNode->server];
foreach($clones as $c) $subdomains[] = $c->server;
$uniqueSubdomains = array_unique($subdomains);
if (count($uniqueSubdomains) !== 8) {
    $failures[] = "Case 02: Expected 8 unique subdomains, found " . count($uniqueSubdomains);
} else {
    echo "[PASS] 8 unique subdomains\n";
}

echo "\n--- Case 03: Zero Division Check ---\n";
$mainNode->reset_day = (int)date('d');
$mainNode->traffic_used = 100 * 1024 * 1024;
$mainNode->save();

$res = callApi('POST', 'node/status', ['node_id' => $nodeId, 'raw_rx' => 0, 'raw_tx' => 0]);
if ($res['code'] !== 200) {
    $failures[] = "Case 03: status failed with reset_day = today. Code: {$res['code']}, Output: {$res['raw']}";
} else {
    echo "[PASS] No division by zero\n";
}

echo "\n--- Case 04: Traffic Formula Audit ---\n";
$mainNode->node_traffic_rxtx_mode = 'rxtx';
$mainNode->traffic_used = 0;
$mainNode->last_raw_total = 0;
$mainNode->save();

// Establish baseline: raw_rx=10G, raw_tx=30G -> rawTotal = 20G
callApi('POST', 'node/status', ['node_id' => $nodeId, 'raw_rx' => 10 * 1024 * 1024 * 1024, 'raw_tx' => 30 * 1024 * 1024 * 1024]);

// Next report: raw_rx=20G, raw_tx=60G -> rawTotal = 40G. Incremental = 20G.
callApi('POST', 'node/status', ['node_id' => $nodeId, 'raw_rx' => 20 * 1024 * 1024 * 1024, 'raw_tx' => 60 * 1024 * 1024 * 1024]);

$mainNode->refresh();
$trafficGB = $mainNode->traffic_used / (1024*1024*1024);
if (abs($trafficGB - 20) > 0.001) {
    $failures[] = "Case 04: Expected 20GB increase, got $trafficGB GB";
} else {
    echo "[PASS] rxtx billing correct\n";
}

echo "\n--- Case 05: 120G Meltdown ---\n";
$mainNode->traffic_limit = 1000 * 1024 * 1024 * 1024;
$mainNode->traffic_used = (1000 - 119.9) * 1024 * 1024 * 1024; // Left < 120G
$mainNode->status = 1;
$mainNode->save();

callApi('POST', 'node/status', ['node_id' => $nodeId, 'raw_rx' => 0, 'raw_tx' => 0]);
$mainNode->refresh();
if ($mainNode->status !== 0) {
    $failures[] = "Case 05: Node status not changed to 0 when remaining traffic < 120G. Status: {$mainNode->status}";
} else {
    echo "[PASS] Node disabled at 120G threshold\n";
}

$res = callApi('GET', 'node/config', ['node_id' => $nodeId]);
if ($res['code'] !== 403) {
    $failures[] = "Case 05: config allowed for disabled node. Code: {$res['code']}";
} else {
    echo "[PASS] config blocked for disabled node\n";
}

echo "\n--- Case 06: JSON Integrity ---\n";
$mainNode->status = 1;
$mainNode->save();
$v2Name = $mainNode->v2_name ?: 'vision-hy2-ws-grpc';
$tplDir = resource_path("templates/xray");
@mkdir($tplDir, 0755, true);
file_put_contents("$tplDir/{$v2Name}.json", json_encode([
    'ssrpanel' => ['user' => ['inboundTags' => ['__inboundTags__']]],
    'inbound' => ['port' => '__wsPort__']
]));

$res = callApi('GET', 'node/config', ['node_id' => $nodeId]);
$cfg = $res['body'];
if (!is_array($cfg['ssrpanel']['user']['inboundTags'])) {
    $failures[] = "Case 06: inboundTags is not a JSON array. Value: " . json_encode($cfg['ssrpanel']['user']['inboundTags']);
} else {
    echo "[PASS] inboundTags is array\n";
}
if (!is_int($cfg['inbound']['port'])) {
    $failures[] = "Case 06: port is not an Integer. Type: " . gettype($cfg['inbound']['port']);
} else {
    echo "[PASS] port is integer\n";
}

// Final Cleanup
$mainNode->delete();
SsNode::where('is_clone', $nodeId)->delete();
@unlink("$tplDir/{$v2Name}.json");

echo "\n--- AUDIT RESULTS ---\n";
if (empty($failures)) {
    echo "ALL CASES PASSED.\n";
} else {
    foreach ($failures as $f) {
        echo "[FAILED] $f\n";
    }
}
