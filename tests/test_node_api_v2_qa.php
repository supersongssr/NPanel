<?php
/**
 * QA Test Suite for Node API v2: Field Standardization, Domain Affinity, DB-Driven Diff
 *
 * Run: podman exec php7-npanel php tests/test_node_api_v2_qa.php
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
    // Clean up test nodes
    SsNode::where('name', 'like', '%QA_TEST%')->forceDelete();
    // Clean up test DNS records
    DnsRecord::where('root_domain', 'like', '%test%')->delete();
    DnsRecord::where('subdomain', 'like', 'nodetest%')->delete();
    DnsRecord::where('subdomain', 'like', 'nodetestfill%')->delete();
    // Clean up test config entries
    DB::table('config')->where('name', 'node_domain_pool')->delete();
}

// =====================================================
echo "=== QA Assertion 1: Field Migration Verification ===\n";
// =====================================================

$cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'ss_node'", [DB::getDatabaseName()]);
$colNames = array_map(function ($c) { return $c->COLUMN_NAME; }, $cols);

assert_test('node_cpu column exists', in_array('node_cpu', $colNames));
assert_test('node_memory column exists', in_array('node_memory', $colNames));
assert_test('node_disk column exists', in_array('node_disk', $colNames));
assert_test('node_health column exists', in_array('node_health', $colNames));
assert_test('node_rxtx column exists', in_array('node_rxtx', $colNames));
assert_test('node_country column exists', in_array('node_country', $colNames));
assert_test('node_city column exists', in_array('node_city', $colNames));
assert_test('Old "cpu" column removed', !in_array('cpu', $colNames));
assert_test('Old "memory" column removed', !in_array('memory', $colNames));
assert_test('Old "disk" column removed', !in_array('disk', $colNames));
assert_test('Old "health" column removed', !in_array('health', $colNames));
assert_test('Old "billing_mode" column removed', !in_array('billing_mode', $colNames));
assert_test('Old "rxtx_mode" column removed', !in_array('rxtx_mode', $colNames));

// =====================================================
echo "\n=== QA Assertion 2: dns_records Table Structure ===\n";
// =====================================================

$dnsCols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'dns_records'", [DB::getDatabaseName()]);
$dnsColNames = array_map(function ($c) { return $c->COLUMN_NAME; }, $dnsCols);

assert_test('dns_records.node_id exists', in_array('node_id', $dnsColNames));
assert_test('dns_records.root_domain exists', in_array('root_domain', $dnsColNames));
assert_test('dns_records.subdomain exists', in_array('subdomain', $dnsColNames));
assert_test('dns_records.record_type exists', in_array('record_type', $dnsColNames));
assert_test('dns_records.ip_addr exists', in_array('ip_addr', $dnsColNames));
assert_test('dns_records.cf_record_id exists', in_array('cf_record_id', $dnsColNames));

// =====================================================
echo "\n=== QA Assertion 3: SsNode Model Field Mapping ===\n";
// =====================================================

cleanup();

$node = new SsNode();
$node->name = 'QA_TEST Node';
$node->ip = '1.2.3.4';
$node->ipv6 = '2001:db8::1';
$node->status = 0;
$node->save();

assert_test('Node created with ID', $node->id > 0);

// Test new field writes
$node->node_cpu = 4;
$node->node_memory = 8.0;
$node->node_disk = 100.0;
$node->node_health = 1;
$node->node_rxtx = 'rxtx';
$node->node_country = 'US';
$node->node_city = 'Los Angeles';
$node->save();

// Reload from DB
$reloaded = SsNode::find($node->id);
assert_test('node_cpu read/write', $reloaded->node_cpu === 4);
assert_test('node_memory read/write', $reloaded->node_memory == 8.0);
assert_test('node_disk read/write', $reloaded->node_disk == 100.0);
assert_test('node_health read/write', $reloaded->node_health == 1);
assert_test('node_rxtx read/write', $reloaded->node_rxtx === 'rxtx');
assert_test('node_country read/write', $reloaded->node_country === 'US');
assert_test('node_city read/write', $reloaded->node_city === 'Los Angeles');

// =====================================================
echo "\n=== QA Assertion 4: Domain Affinity Logic ===\n";
// =====================================================

// Test 4a: Insert DNS records to simulate domain occupancy
$testDomainA = 'test-a.example.com';
$testDomainB = 'test-b.example.com';
$primaryDomain = DB::table('config')->where('name', 'node_root_domain')->value('value') ?: 'example.com';

// Inject test domains into config pool for affinity testing
$poolKey = 'node_domain_pool';
$existingPool = DB::table('config')->where('name', $poolKey)->first();
if (!$existingPool) {
    DB::table('config')->insert(['name' => $poolKey, 'value' => $testDomainA . ',' . $testDomainB]);
} else {
    DB::table('config')->where('name', $poolKey)->update(['value' => $testDomainA . ',' . $testDomainB]);
}
// Reload sysConf after injecting pool
$sysConf = \App\Components\Helpers::systemConfig();

// Fill domain A with 175 records (under 180 limit)
for ($i = 0; $i < 175; $i++) {
    DnsRecord::create([
        'node_id' => 99999,
        'root_domain' => $testDomainA,
        'subdomain' => 'nodetestfill' . $i,
        'record_type' => 'A',
        'ip_addr' => "10.0.0.{$i}",
        'cf_record_id' => 'fake-cf-id-' . $i,
    ]);
}

$countA = DnsRecord::where('root_domain', $testDomainA)->count();
assert_test('Domain A has 175 records', $countA === 175, "Actual: {$countA}");

// Domain B has 0 records
$countB = DnsRecord::where('root_domain', $testDomainB)->count();
assert_test('Domain B has 0 records', $countB === 0, "Actual: {$countB}");

// Test domain affinity resolution via controller logic
$controller = new \App\Http\Controllers\Api\NodeApiController();

// Use reflection to access the private resolveDomainAffinity method
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('resolveDomainAffinity');
$method->setAccessible(true);

// Test Priority 1: Reported domain with capacity < 180
$result = $method->invoke($controller, $testDomainA, $sysConf);
assert_test('Priority 1: Affinity domain accepted (175 < 180)', $result === $testDomainA, "Got: {$result}");

// Fill domain A to 181
for ($i = 175; $i < 181; $i++) {
    DnsRecord::create([
        'node_id' => 99999,
        'root_domain' => $testDomainA,
        'subdomain' => 'nodetestfill' . $i,
        'record_type' => 'A',
        'ip_addr' => "10.0.1.{$i}",
        'cf_record_id' => 'fake-cf-id-' . $i,
    ]);
}

$countA = DnsRecord::where('root_domain', $testDomainA)->count();
assert_test('Domain A filled to 181', $countA === 181, "Actual: {$countA}");

// Test Overflow: Reported domain at capacity should fall through
$result = $method->invoke($controller, $testDomainA, $sysConf);
assert_test('Overflow: Full domain rejected (181 >= 180)', $result !== $testDomainA, "Got: {$result}");

// Test no reported domain — should pick from pool or primary
$result = $method->invoke($controller, null, $sysConf);
assert_test('No domain reported: Fallback works', !empty($result), "Got: {$result}");

// =====================================================
echo "\n=== QA Assertion 5: DnsRecord Model CRUD ===\n";
// =====================================================

$dnsRec = DnsRecord::create([
    'node_id' => $node->id,
    'root_domain' => 'test-crud.example.com',
    'subdomain' => 'nodetestcrud',
    'record_type' => 'A',
    'ip_addr' => '5.6.7.8',
    'cf_record_id' => 'cf-12345',
]);

assert_test('DnsRecord created', $dnsRec->id > 0);
assert_test('DnsRecord node_id correct', $dnsRec->node_id === $node->id);
assert_test('DnsRecord root_domain correct', $dnsRec->root_domain === 'test-crud.example.com');

// Update
$dnsRec->ip_addr = '9.10.11.12';
$dnsRec->save();
$reloadedDns = DnsRecord::find($dnsRec->id);
assert_test('DnsRecord IP updated', $reloadedDns->ip_addr === '9.10.11.12');

// Delete
$dnsRec->delete();
assert_test('DnsRecord deleted', DnsRecord::find($dnsRec->id) === null);

// =====================================================
echo "\n=== QA Assertion 6: parseServerField ===\n";
// =====================================================

$parseMethod = $ref->getMethod('parseServerField');
$parseMethod->setAccessible(true);

$parsed = $parseMethod->invoke($controller, 'node123.example.com');
assert_test('Parse server: subdomain', $parsed['subdomain'] === 'node123');
assert_test('Parse server: root_domain', $parsed['root_domain'] === 'example.com');

$parsed = $parseMethod->invoke($controller, null);
assert_test('Parse server: null returns null', $parsed === null);

$parsed = $parseMethod->invoke($controller, 'invalid');
assert_test('Parse server: no dot returns null', $parsed === null);

// =====================================================
echo "\n=== QA Assertion 7: CloudflareProvider Methods Exist ===\n";
// =====================================================

$cfProvider = new \App\Components\DNS\CloudflareProvider();
assert_test('CloudflareProvider instantiated', $cfProvider !== null);
assert_test('createRecord method exists', method_exists($cfProvider, 'createRecord'));
assert_test('updateRecordById method exists', method_exists($cfProvider, 'updateRecordById'));
assert_test('deleteRecord method exists', method_exists($cfProvider, 'deleteRecord'));

// =====================================================
// Cleanup
// =====================================================
cleanup();

echo "\n" . str_repeat("=", 50) . "\n";
echo "Results: {$passed}/{$total} passed";
if ($failed > 0) {
    echo ", {$failed} FAILED";
}
echo "\n" . str_repeat("=", 50) . "\n";

exit($failed > 0 ? 1 : 0);
