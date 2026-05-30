<?php
/**
 * Bug Fix Test Suite: Baseline & Meltdown
 *
 * Tests two critical fixes:
 *   Bug 1: First-report baseline — NIC historical traffic must NOT be billed
 *   Bug 2: 120GB meltdown — precise threshold verification
 */

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
$pass = 0;
$fail = 0;

function assert_test($name, $condition, $detail = '') {
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  PASS: {$name}\n";
    } else {
        $fail++;
        echo "  FAIL: {$name}" . ($detail ? " -- {$detail}" : "") . "\n";
    }
}

// ============================================================
echo "=== Bug Fix 1: First-Report Baseline Protection ===\n";
// ============================================================

// Create a new node
$req0 = mockRequest('POST', ['node_ip' => '5.5.5.5', 'token' => $token]);
$res0 = $controller->applyId($req0);
$nodeId = json_decode($res0->getContent(), true)['node_id'];
echo "  Created node_id: {$nodeId}\n";

// Register with 2000GB limit
$req1 = mockRequest('POST', [
    'node_id' => $nodeId,
    'token' => $token,
    'v2_name' => 'vision-hy2',
    'node_traffic_limit' => 2000,
    'node_traffic_resetday' => 1,
    'billing_mode' => 'tx',
]);
$controller->register($req1);

// Manually ensure clean baseline state
$node = SsNode::find($nodeId);
$node->last_raw_total = 0;
$node->traffic_used = 0;
$node->status = 1;
$node->save();

echo "  last_raw_total before report: " . $node->last_raw_total . "\n";

// Simulate a server that has transferred 5TB in its lifetime (NIC counter)
$hugeTx = 5 * 1024 * 1024 * 1024 * 1024; // 5 TB
$reqFirst = mockRequest('POST', [
    'node_id' => $nodeId,
    'token' => $token,
    'raw_rx' => 0,
    'raw_tx' => $hugeTx,
    'server_uptime' => 3600,
]);
$resFirst = $controller->status($reqFirst);
$node = SsNode::find($nodeId);

assert_test('first report: traffic_used stays 0', $node->traffic_used == 0,
    "traffic_used = " . $node->traffic_used . " (expected 0)");
assert_test('first report: last_raw_total saved as baseline', $node->last_raw_total == $hugeTx,
    "last_raw_total = " . $node->last_raw_total . " (expected {$hugeTx})");
assert_test('first report: status stays 1 (no meltdown)', $node->status == 1,
    "status = " . $node->status);

echo "  traffic_used after first report: " . $node->traffic_used . "\n";
echo "  last_raw_total after first report: " . round($node->last_raw_total / 1024 / 1024 / 1024, 2) . " GB\n";

// Now send a normal second report: tx increased by 100GB from baseline
$secondTx = $hugeTx + 100 * 1024 * 1024 * 1024;
$reqSecond = mockRequest('POST', [
    'node_id' => $nodeId,
    'token' => $token,
    'raw_rx' => 0,
    'raw_tx' => $secondTx,
    'server_uptime' => 7200,
]);
$controller->status($reqSecond);
$node = SsNode::find($nodeId);

$expectedUsed = 100 * 1024 * 1024 * 1024;
assert_test('second report: 100GB increment counted correctly',
    abs($node->traffic_used - $expectedUsed) < 1024,
    "traffic_used = " . round($node->traffic_used / 1024 / 1024 / 1024, 2) . " GB (expected 100 GB)");

echo "  traffic_used after second report: " . round($node->traffic_used / 1024 / 1024 / 1024, 2) . " GB\n";

// Cleanup
SsNode::where('id', $nodeId)->delete();
SsNode::where('is_clone', $nodeId)->delete();

// ============================================================
echo "\n=== Bug Fix 2: Precise 120GB Meltdown ===\n";
// ============================================================

// Create another node
$req0b = mockRequest('POST', ['node_ip' => '6.6.6.6', 'token' => $token]);
$res0b = $controller->applyId($req0b);
$nodeId2 = json_decode($res0b->getContent(), true)['node_id'];

// Register with 2000GB limit
$req1b = mockRequest('POST', [
    'node_id' => $nodeId2,
    'token' => $token,
    'v2_name' => 'vision-hy2',
    'node_traffic_limit' => 2000,
    'node_traffic_resetday' => 1,
    'billing_mode' => 'tx',
]);
$controller->register($req1b);

// Establish baseline with a first report
$node2 = SsNode::find($nodeId2);
$node2->last_raw_total = 0;
$node2->traffic_used = 0;
$node2->status = 1;
$node2->save();

// First report to establish baseline (100GB reported, should be 0 increment)
$baselineTx = 100 * 1024 * 1024 * 1024;
$reqBaseline = mockRequest('POST', [
    'node_id' => $nodeId2,
    'token' => $token,
    'raw_rx' => 0,
    'raw_tx' => $baselineTx,
    'server_uptime' => 3600,
]);
$controller->status($reqBaseline);
$node2 = SsNode::find($nodeId2);
assert_test('baseline established: traffic_used = 0', $node2->traffic_used == 0);

// Now push traffic to reach exactly 1881GB used (leaving 119GB, which is < 120GB)
// 1881 GB in bytes
$targetUsedGB = 1881;
$targetUsedBytes = $targetUsedGB * 1024 * 1024 * 1024;
// We need incremental = targetUsedBytes, so new tx = baseline + targetUsedBytes
$meltdownTx = $baselineTx + $targetUsedBytes;

$reqMeltdown = mockRequest('POST', [
    'node_id' => $nodeId2,
    'token' => $token,
    'raw_rx' => 0,
    'raw_tx' => $meltdownTx,
    'server_uptime' => 7200,
]);
$controller->status($reqMeltdown);
$node2 = SsNode::find($nodeId2);

$usedGB = round($node2->traffic_used / 1024 / 1024 / 1024, 2);
$limitGB = round($node2->traffic_limit / 1024 / 1024 / 1024, 2);
$remainGB = round(($node2->traffic_limit - $node2->traffic_used) / 1024 / 1024 / 1024, 2);

echo "  Used: {$usedGB} GB, Limit: {$limitGB} GB, Remaining: {$remainGB} GB\n";

assert_test('used traffic is ~1881 GB', abs($usedGB - $targetUsedGB) < 0.1,
    "used = {$usedGB} GB");
assert_test('remaining is ~119 GB', abs($remainGB - 119) < 0.1,
    "remaining = {$remainGB} GB");
assert_test('meltdown triggered: status = 0', $node2->status == 0,
    "status = " . $node2->status);

// Cleanup
SsNode::where('id', $nodeId2)->delete();
SsNode::where('is_clone', $nodeId2)->delete();

// ============================================================
echo "\n=== Edge Case: Reboot Detection Still Works ===\n";
// ============================================================

$req0c = mockRequest('POST', ['node_ip' => '7.7.7.7', 'token' => $token]);
$res0c = $controller->applyId($req0c);
$nodeId3 = json_decode($res0c->getContent(), true)['node_id'];

$controller->register(mockRequest('POST', [
    'node_id' => $nodeId3, 'token' => $token,
    'node_traffic_limit' => 2000, 'node_traffic_resetday' => 1,
    'billing_mode' => 'tx',
]));

// Establish baseline
$controller->status(mockRequest('POST', [
    'node_id' => $nodeId3, 'token' => $token, 'raw_rx' => 0, 'raw_tx' => 500 * 1024 * 1024 * 1024, 'server_uptime' => 3600,
]));
$node3 = SsNode::find($nodeId3);
assert_test('baseline: traffic_used = 0', $node3->traffic_used == 0);

// Normal increment
$controller->status(mockRequest('POST', [
    'node_id' => $nodeId3, 'token' => $token, 'raw_rx' => 0, 'raw_tx' => 550 * 1024 * 1024 * 1024, 'server_uptime' => 7200,
]));
$node3 = SsNode::find($nodeId3);
assert_test('normal: 50GB counted', abs($node3->traffic_used / 1024 / 1024 / 1024 - 50) < 0.01);

// Reboot: tx drops to 10GB
$controller->status(mockRequest('POST', [
    'node_id' => $nodeId3, 'token' => $token, 'raw_rx' => 0, 'raw_tx' => 10 * 1024 * 1024 * 1024, 'server_uptime' => 60,
]));
$node3 = SsNode::find($nodeId3);
$totalUsedGB = round($node3->traffic_used / 1024 / 1024 / 1024, 2);
// After reboot: incremental = 10GB (the current NIC counter after reboot)
// Total = 50 + 10 = 60 GB
assert_test('reboot: 10GB added (total ~60GB)', abs($totalUsedGB - 60) < 0.01,
    "total = {$totalUsedGB} GB");

SsNode::where('id', $nodeId3)->delete();
SsNode::where('is_clone', $nodeId3)->delete();

// ============================================================
echo "\n============================================\n";
echo "Results: {$pass} passed, {$fail} failed\n";
echo "============================================\n";

exit($fail > 0 ? 1 : 0);
