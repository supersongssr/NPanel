<?php
/**
 * QA Test Suite Round 3: Register refactor — dynamic protocols, clone reuse, tiered levels, config route, DNS safety
 *
 * Run: podman exec php7-npanel php tests/test_node_api_v2_qa_r3.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Models\SsNode;
use App\Http\Models\DnsRecord;
use App\Http\Models\Config;

$passed = 0;
$failed = 0;
$total = 0;

function assert_test($name, $condition, $message = '') {
    global $passed, $failed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  PASS: {$name}\n";
    } else {
        $failed++;
        echo "  FAIL: {$name}" . ($message ? " — {$message}" : "") . "\n";
    }
}

function cleanup() {
    SsNode::where('name', 'like', '%QA_TEST%')->forceDelete();
    DnsRecord::where('root_domain', 'like', '%test-qa%')->delete();
    DnsRecord::where('subdomain', 'like', 'nodeqa%')->delete();
}

// Save originals
$originalPresets = Config::where('name', 'node_protocol_presets')->value('value');
Config::where('name', 'node_protocol_presets')->update(['value' => json_encode([
    'threshold_mb' => 2048,
    'high' => 'xhttp-hy2-ws-grpc',
    'low' => 'vision-hy2-ws-grpc',
])]);

cleanup();

$controller = new \App\Http\Controllers\Api\NodeApiController();
$apiToken = env('API_TOKEN');

// =====================================================
echo "=== QA Assertion 1: Dynamic Fission — 1GB Single-Stack = Low Protocol (4 nodes) ===\n";
// =====================================================

// Create a node first
$node1 = new SsNode();
$node1->name = 'QA_TEST_Node_1G';
$node1->ip = '10.0.0.100';
$node1->status = 0;
$node1->save();

$req1 = Request::create('/api/node/register', 'POST', [
    'token' => $apiToken,
    'node_id' => $node1->id,
    'node_ip' => '10.0.0.100',
    'node_memory' => 1024,
    'node_cost' => 1.5,
]);
$resp1 = $controller->register($req1);
$data1 = json_decode($resp1->getContent(), true);

assert_test('register returns success', ($data1['status'] ?? '') === 'success', 'Response: ' . json_encode($data1));

$v2Name = $data1['v2_name'] ?? '';
assert_test('1GB memory gets low protocol group', $v2Name === 'vision-hy2-ws-grpc', "Got: {$v2Name}");

$nodeIds = $data1['node_ids'] ?? [];
$cloneIds = $data1['clone_node_ids'] ?? [];
assert_test('4 nodes total in node_ids', count($nodeIds) === 4, "Got: " . count($nodeIds));
assert_test('3 clone node IDs', count($cloneIds) === 3, "Got: " . count($cloneIds));

$mainNode1 = SsNode::find($node1->id);
$sealedIds = json_decode($mainNode1->node_ids, true);
assert_test('node_ids field sealed with 4 IDs', count($sealedIds) === 4, "Got: " . count($sealedIds));
assert_test('node_ids includes main node', in_array($node1->id, $sealedIds));
assert_test('node_rxtx field is tx', $mainNode1->node_rxtx === 'tx', "Got: {$mainNode1->node_rxtx}");
assert_test('main node v2_name is a single protocol', in_array($mainNode1->v2_name, ['vision', 'hy2', 'ws', 'grpc']), "Got: {$mainNode1->v2_name}");

// =====================================================
echo "\n=== QA Assertion 2: Dynamic Fission — 4GB = High Protocol (4 nodes) ===\n";
// =====================================================

$node2 = new SsNode();
$node2->name = 'QA_TEST_Node_4G';
$node2->ip = '10.0.0.200';
$node2->status = 0;
$node2->save();

$req2 = Request::create('/api/node/register', 'POST', [
    'token' => $apiToken,
    'node_id' => $node2->id,
    'node_ip' => '10.0.0.200',
    'node_memory' => 4096,
    'node_cost' => 1.5,
]);
$resp2 = $controller->register($req2);
$data2 = json_decode($resp2->getContent(), true);

$v2Name2 = $data2['v2_name'] ?? '';
assert_test('4GB memory gets high protocol group', $v2Name2 === 'xhttp-hy2-ws-grpc', "Got: {$v2Name2}");

$nodeIds2 = $data2['node_ids'] ?? [];
assert_test('4 nodes for single-stack high protocol', count($nodeIds2) === 4, "Got: " . count($nodeIds2));

// =====================================================
echo "\n=== QA Assertion 3: Tiered Level Engine ===\n";
// =====================================================

$node3 = new SsNode();
$node3->name = 'QA_TEST_Level';
$node3->ip = '10.0.0.300';
$node3->status = 0;
$node3->save();

$req3 = Request::create('/api/node/register', 'POST', [
    'token' => $apiToken,
    'node_id' => $node3->id,
    'node_ip' => '10.0.0.300',
    'node_memory' => 1024,
    'node_cost' => 2.8,
]);
$resp3 = $controller->register($req3);
$data3 = json_decode($resp3->getContent(), true);

$mainNode3 = SsNode::find($node3->id);
assert_test('Main node level = floor(2.8) = 2', $mainNode3->level === 2, "Got: {$mainNode3->level}");

$cloneNodeIds3 = $data3['clone_node_ids'] ?? [];
$foundLowLevel = false;
$levels = [];
foreach ($cloneNodeIds3 as $cid) {
    $clone = SsNode::find($cid);
    if (!$clone) continue;
    $levels[] = $clone->level;
    if ($clone->level < $mainNode3->level) $foundLowLevel = true;
}
assert_test('No clone has level < main level (2)', !$foundLowLevel, 'Levels: ' . implode(',', $levels));
assert_test('All clone levels in [2,5] range', count(array_filter($levels, function($l) { return $l >= 2 && $l <= 5; })) === count($levels), 'Levels: ' . implode(',', $levels));

// Test edge: node_cost = 0 → level = 1
$node3b = new SsNode();
$node3b->name = 'QA_TEST_Level_Zero';
$node3b->ip = '10.0.0.301';
$node3b->status = 0;
$node3b->save();

$req3b = Request::create('/api/node/register', 'POST', [
    'token' => $apiToken,
    'node_id' => $node3b->id,
    'node_ip' => '10.0.0.301',
    'node_memory' => 1024,
    'node_cost' => 0,
]);
$resp3b = $controller->register($req3b);
$mainNode3b = SsNode::find($node3b->id);
assert_test('node_cost=0 → level = 1 (minimum)', $mainNode3b->level === 1, "Got: {$mainNode3b->level}");

// =====================================================
echo "\n=== QA Assertion 4: Clone Reuse (Anti-Proliferation) ===\n";
// =====================================================

$cloneCountBefore = SsNode::where('is_clone', $node1->id)->count();
assert_test('Clones exist before re-register', $cloneCountBefore === 3, "Found: {$cloneCountBefore}");

$req4 = Request::create('/api/node/register', 'POST', [
    'token' => $apiToken,
    'node_id' => $node1->id,
    'node_ip' => '10.0.0.100',
    'node_memory' => 1024,
    'node_cost' => 1.5,
]);
$resp4 = $controller->register($req4);

$cloneCountAfter = SsNode::where('is_clone', $node1->id)->count();
assert_test('Clone count unchanged after re-register', $cloneCountAfter === 3, "Before: {$cloneCountBefore}, After: {$cloneCountAfter}");

$totalNodeCount = SsNode::where('name', 'like', '%QA_TEST_Node_1G%')->count();
assert_test('No proliferation — still 4 total nodes', $totalNodeCount === 4, "Found: {$totalNodeCount}");

// =====================================================
echo "\n=== QA Assertion 5: Config Route — GET returns 405, POST does not ===\n";
// =====================================================

// Use the route to test HTTP method matching
$routes = app('router')->getRoutes();
$configRoute = null;
foreach ($routes as $route) {
    if ($route->uri() === 'api/node/config') {
        $configRoute = $route;
        break;
    }
}
assert_test('Config route exists', $configRoute !== null);
if ($configRoute) {
    $methods = $configRoute->methods();
    assert_test('Config route only allows POST', in_array('POST', $methods) && !in_array('GET', $methods), 'Methods: ' . implode(',', $methods));
}

// Directly test the controller method works with POST request
$node1->status = 1;
$node1->save();
$reqConfig = Request::create('/api/node/config', 'POST', ['token' => $apiToken, 'node_id' => $node1->id]);
try {
    $respConfig = $controller->config($reqConfig);
    assert_test('POST /config does not throw 405', true);
} catch (\Exception $e) {
    assert_test('POST /config does not throw 405', false, $e->getMessage());
}

// =====================================================
echo "\n=== QA Assertion 6: node_unlock stored as raw query string ===\n";
// =====================================================

$node5 = new SsNode();
$node5->name = 'QA_TEST_Unlock';
$node5->ip = '10.0.0.50';
$node5->status = 0;
$node5->save();

$unlockStr = 'Netflix=Yes&Gemini=No';
$req5 = Request::create('/api/node/register', 'POST', [
    'token' => $apiToken,
    'node_id' => $node5->id,
    'node_ip' => '10.0.0.50',
    'node_memory' => 1024,
    'node_unlock' => $unlockStr,
]);
$controller->register($req5);

$node5Reloaded = SsNode::find($node5->id);
assert_test('node_unlock stored as raw query string', $node5Reloaded->node_unlock === $unlockStr, "Got: {$node5Reloaded->node_unlock}");

// =====================================================
echo "\n=== QA Assertion 7: Config-driven protocol — no hardcoding ===\n";
// =====================================================

$presets = json_decode(Config::where('name', 'node_protocol_presets')->value('value'), true);
assert_test('Config has threshold_mb', isset($presets['threshold_mb']) && $presets['threshold_mb'] == 2048);
assert_test('Config has high protocol', isset($presets['high']) && $presets['high'] === 'xhttp-hy2-ws-grpc');
assert_test('Config has low protocol', isset($presets['low']) && $presets['low'] === 'vision-hy2-ws-grpc');

$controllerCode = file_get_contents(base_path('app/Http/Controllers/Api/NodeApiController.php'));
assert_test('No hardcoded "2G" in controller', strpos($controllerCode, '"2G"') === false && strpos($controllerCode, "'2G'") === false);
assert_test('No hardcoded > 2048 comparison in controller', strpos($controllerCode, '> 2048') === false);
assert_test('No hardcoded "vision-hy2-ws-grpc" in controller logic (only defaults)', substr_count($controllerCode, 'vision-hy2-ws-grpc') <= 3, 'Found ' . substr_count($controllerCode, 'vision-hy2-ws-grpc') . ' occurrences');

// =====================================================
echo "\n=== QA Assertion 8: DB Schema — node_rxtx and node_ids ===\n";
// =====================================================

$cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'ss_node'", [DB::getDatabaseName()]);
$colNames = array_map(function ($c) { return $c->COLUMN_NAME; }, $cols);

assert_test('node_rxtx column exists', in_array('node_rxtx', $colNames));
assert_test('node_rxtx_mode column gone', !in_array('node_rxtx_mode', $colNames), 'Still found old column!');
assert_test('node_ids column exists', in_array('node_ids', $colNames));

// =====================================================
echo "\n=== QA Assertion 9: DNS Reconciliation — type filter safety ===\n";
// =====================================================

$commandCode = file_get_contents(base_path('app/Console/Commands/InitDnsRecords.php'));
assert_test('initDnsRecords filters A/AAAA for cleanup', strpos($commandCode, "whereIn('record_type', ['A', 'AAAA']") !== false);
assert_test('initDnsRecords does NOT touch TXT', strpos($commandCode, "'TXT'") === false);
assert_test('initDnsRecords does NOT touch MX', strpos($commandCode, "'MX'") === false);
assert_test('initDnsRecords does NOT touch CNAME', strpos($commandCode, "'CNAME'") === false);
assert_test('initDnsRecords listRecords only A/AAAA', strpos($commandCode, "['A', 'AAAA']") !== false);
assert_test('initDnsRecords matches by subdomain (server field), not IP', strpos($commandCode, "where('server'") !== false && strpos($commandCode, "where('ip'") === false, 'Must use server-based matching');
assert_test('initDnsRecords no IP-based matching', strpos($commandCode, "where('ipv6'") === false, 'Must not use ipv6-based matching');

// =====================================================
// Cleanup & Summary
// =====================================================
cleanup();

Config::where('name', 'node_protocol_presets')->update(['value' => $originalPresets]);

echo "\n" . str_repeat("=", 50) . "\n";
echo "Results: {$passed} passed, {$failed} failed, {$total} total\n";
echo ($failed === 0 ? "ALL TESTS PASSED" : "SOME TESTS FAILED") . "\n";
echo str_repeat("=", 50) . "\n";

exit($failed > 0 ? 1 : 0);
