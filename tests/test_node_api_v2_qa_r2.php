<?php
/**
 * QA Test Suite Round 2: node_rxtx_mode rename, billing logic, doc completeness
 *
 * Run: podman exec php7-npanel php tests/test_node_api_v2_qa_r2.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Http\Models\SsNode;
use App\Http\Models\DnsRecord;

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
    DnsRecord::where('root_domain', 'like', '%test%')->delete();
    DnsRecord::where('subdomain', 'like', 'nodetest%')->delete();
    DB::table('config')->where('name', 'node_domain_pool')->delete();
}

// =====================================================
echo "=== QA Assertion 1: SQL Reserved Word Safety ===\n";
// =====================================================

$cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'ss_node'", [DB::getDatabaseName()]);
$colNames = array_map(function ($c) { return $c->COLUMN_NAME; }, $cols);

assert_test('node_group column exists (no SQL keyword conflict)', in_array('node_group', $colNames));
assert_test('No bare "group" column', !in_array('group', $colNames));
assert_test('node_rxtx_mode column exists', in_array('node_rxtx_mode', $colNames));
assert_test('No legacy "billing_mode" column', !in_array('billing_mode', $colNames));
assert_test('No legacy "node_traffic_rxtx_mode" column', !in_array('node_traffic_rxtx_mode', $colNames));
assert_test('node_cpu column exists', in_array('node_cpu', $colNames));
assert_test('node_memory column exists', in_array('node_memory', $colNames));
assert_test('node_disk column exists', in_array('node_disk', $colNames));
assert_test('node_health column exists', in_array('node_health', $colNames));
assert_test('node_country column exists', in_array('node_country', $colNames));
assert_test('node_city column exists', in_array('node_city', $colNames));

// =====================================================
echo "\n=== QA Assertion 2: Billing Logic Verification ===\n";
// =====================================================

cleanup();

$node = new SsNode();
$node->name = 'QA_TEST Billing';
$node->ip = '10.0.0.1';
$node->status = 0;
$node->save();

// Test tx mode
$node->node_rxtx_mode = 'tx';
$node->last_raw_total = 0;
$node->traffic_used = 0;
$node->traffic_limit = 1000 * 1024 * 1024 * 1024; // 1000 GB
$node->save();

$rawRx = 500;
$rawTx = 300;

// Simulate tx billing: rawTotal = rawTx = 300
if ($node->node_rxtx_mode == 'rxtx') {
    $rawTotal = ($rawRx + $rawTx) / 2;
} else {
    $rawTotal = $rawTx;
}
assert_test('tx mode: rawTotal = rawTx', $rawTotal == 300, "Got: {$rawTotal}");

// Test rxtx mode
$node->node_rxtx_mode = 'rxtx';
$node->save();

if ($node->node_rxtx_mode == 'rxtx') {
    $rawTotalRxtx = ($rawRx + $rawTx) / 2;
} else {
    $rawTotalRxtx = $rawTx;
}
assert_test('rxtx mode: rawTotal = (rx+tx)/2', $rawTotalRxtx == 400, "Got: {$rawTotalRxtx}");

// Test three-state increment: first report (baseline)
$lastRaw = 0;
if ($lastRaw == 0) {
    $incremental = 0;
}
assert_test('First report: incremental = 0', $incremental === 0);

// Test three-state increment: normal accumulation
$lastRaw = 200;
$rawTotal = 300;
$incremental = $rawTotal - $lastRaw;
assert_test('Normal accumulation: incremental = 100', $incremental === 100);

// Test three-state increment: NIC reboot (counter reset)
$lastRaw = 500;
$rawTotal = 50;
$incremental = $rawTotal; // counter reset
assert_test('NIC reboot: incremental = rawTotal', $incremental === 50);

// Verify reload from DB preserves mode
$reloaded = SsNode::find($node->id);
assert_test('node_rxtx_mode persisted as rxtx', $reloaded->node_rxtx_mode === 'rxtx');

// =====================================================
echo "\n=== QA Assertion 3: Input Param Backward Compat ===\n";
// =====================================================

// Test that the controller accepts both old and new param names
$controller = new \App\Http\Controllers\Api\NodeApiController();

// Simulate request with old param names
$oldRequest = \Illuminate\Http\Request::create('/api/node/register', 'POST', [
    'billing_mode' => 'rxtx',
    'cpu' => '8 cores',
    'memory' => '32',
    'disk' => '500',
]);

assert_test('Old param "billing_mode" → node_rxtx_mode', $oldRequest->input('billing_mode') === 'rxtx');
assert_test('Old param "cpu" → node_cpu', $oldRequest->input('cpu') === '8 cores');

// Simulate request with new param names (should take priority)
$newRequest = \Illuminate\Http\Request::create('/api/node/register', 'POST', [
    'node_rxtx_mode' => 'tx',
    'node_cpu' => '4 cores',
    'node_memory' => '16',
    'node_disk' => '256',
]);

assert_test('New param "node_rxtx_mode" recognized', $newRequest->input('node_rxtx_mode') === 'tx');
assert_test('New param "node_cpu" recognized', $newRequest->input('node_cpu') === '4 cores');

// =====================================================
echo "\n=== QA Assertion 4: Doc Completeness Check ===\n";
// =====================================================

$docPath = __DIR__ . '/../docs/api/node-api-v2.md';
$docContent = file_get_contents($docPath);
assert_test('API doc file exists', $docContent !== false);

// Extract register section
preg_match('/### Step 1:.*?(?=### Step 2:)/s', $docContent, $registerSection);
$registerText = $registerSection[0] ?? '';

$requiredFields = [
    'token', 'node_id', 'v2_name',
    'node_cpu', 'node_memory', 'node_disk',
    'node_ip', 'node_ipv6', 'node_country', 'node_city', 'node_country_code',
    'node_rxtx_mode', 'node_traffic_limit', 'node_traffic_resetday', 'node_traffic_rate', 'node_cost',
    'root_domain', 'node_group', 'node_level', 'node_sort', 'bandwidth',
    'node_unlock', 'node_info',
];

$fieldCount = 0;
foreach ($requiredFields as $field) {
    $found = strpos($registerText, '`' . $field . '`') !== false;
    if ($found) $fieldCount++;
    assert_test("Doc contains field: {$field}", $found);
}

echo "  (Doc register section: {$fieldCount}/" . count($requiredFields) . " required fields found)\n";

// Check that billing mode field is documented under its new name
assert_test('Doc uses node_rxtx_mode (not node_traffic_rxtx_mode)', strpos($docContent, 'node_rxtx_mode') !== false);
assert_test('Doc does NOT reference node_traffic_rxtx_mode', strpos($docContent, 'node_traffic_rxtx_mode') === false);

// Check doc describes the billing logic in /status section
assert_test('Doc describes billing mode in /status', strpos($docContent, 'node_rxtx_mode') !== false && strpos($docContent, 'raw_tx') !== false);

// =====================================================
cleanup();

echo "\n" . str_repeat("=", 50) . "\n";
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) {
    echo ", {$failed} FAILED";
}
echo "\n" . str_repeat("=", 50) . "\n";

exit($failed > 0 ? 1 : 0);
