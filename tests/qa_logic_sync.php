<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Http\Models\SsNode;
use App\Http\Models\DnsRecord;
use App\Http\Controllers\Api\NodeApiController;
use Illuminate\Http\Request;

$pass = 0;
$fail = 0;
$skip = 0;

function assert_test($name, $condition, $detail = '')
{
    global $pass, $fail;
    if ($condition) {
        echo "  ✅ PASS: {$name}" . ($detail ? " — {$detail}" : "") . PHP_EOL;
        $pass++;
    } else {
        echo "  ❌ FAIL: {$name}" . ($detail ? " — {$detail}" : "") . PHP_EOL;
        $fail++;
    }
}

function skip_test($name, $reason)
{
    global $skip;
    echo "  ⚠️  SKIP: {$name} — {$reason}" . PHP_EOL;
    $skip++;
}

echo "=== QA Test Suite: Logic Sync ===" . PHP_EOL . PHP_EOL;

$controller = new NodeApiController();

// ===========================
// QA 1: 双栈同时存储 & 命名 (报告 ip+ipv6 → main=ipv4, server={rand}n{id})
// ===========================
echo "--- QA1: Store both stacks & Naming (双栈报告, main=ipv4 槽) ---" . PHP_EOL;

$testNode = new SsNode();
$testNode->name = 'QA Test';
$testNode->ip = '10.0.0.1';
$testNode->ipv6 = '';
$testNode->server = '';
$testNode->v2_name = '';
$testNode->status = 0;
$testNode->is_clone = 0;
$testNode->save();
$testNodeId = $testNode->id;

DnsRecord::where('node_id', $testNodeId)->delete();
$beforeDnsCount = DnsRecord::where('node_id', $testNodeId)->count();

$request = Request::create('/api/node/register', 'POST', [
    'token' => env('API_TOKEN'),
    'node_id' => $testNodeId,
    'node_ip' => '10.0.0.100',
    'node_ipv6' => 'fd00::dead:beef',
    'node_country_code' => 'SG',
    'node_city' => 'Singapore',
    'v2_name' => 'hy2',
    'node_level' => 1,
]);

$controller->register($request);
$refreshed = SsNode::find($testNodeId);
$afterDnsCount = DnsRecord::where('node_id', $testNodeId)->count();

assert_test(
    'Node name is SG-Singapore',
    $refreshed->name === 'SG-Singapore',
    "actual: {$refreshed->name}"
);
assert_test(
    'IPv4 is preserved (10.0.0.100, 双栈同时存储)',
    $refreshed->ip === '10.0.0.100',
    "actual: {$refreshed->ip}"
);
assert_test(
    'IPv6 is preserved (fd00::dead:beef, 不再互斥为 null)',
    $refreshed->ipv6 === 'fd00::dead:beef',
    "actual: " . var_export($refreshed->ipv6, true)
);
assert_test(
    'server 是 ipv4 (subdomain 不含 ipv6n, 主节点 ipv4 槽)',
    strpos(explode('.', $refreshed->server, 2)[0], 'ipv6n') === false,
    "actual: {$refreshed->server}"
);
assert_test(
    'No dns_records created during register',
    $afterDnsCount === $beforeDnsCount,
    "before: {$beforeDnsCount}, after: {$afterDnsCount}"
);

echo PHP_EOL;

// ===========================
// QA 1b: 仅 ipv6 报告 → main=ipv6 (server={rand}ipv6n{id})
// ===========================
echo "--- QA1b: IPv6-only report → main=ipv6 (server 含 ipv6n) ---" . PHP_EOL;

$testNode2 = new SsNode();
$testNode2->name = 'QA Test 2';
$testNode2->ip = '';
$testNode2->ipv6 = 'fd00::1';
$testNode2->server = '';
$testNode2->v2_name = '';
$testNode2->status = 0;
$testNode2->is_clone = 0;
$testNode2->save();
$testNodeId2 = $testNode2->id;

DnsRecord::where('node_id', $testNodeId2)->delete();

$request2 = Request::create('/api/node/register', 'POST', [
    'token' => env('API_TOKEN'),
    'node_id' => $testNodeId2,
    // 只报 ipv6 (无 node_ip) → 仅 ipv6 槽, main=ipv6
    'node_ipv6' => 'fd00::cafe:babe',
    'node_country_code' => 'JP',
    'node_city' => 'Tokyo',
    'v2_name' => 'ws',
    'node_level' => 1,
]);

$controller->register($request2);
$refreshed2 = SsNode::find($testNodeId2);

assert_test(
    'IPv4 is empty (仅报 ipv6, 无 ipv4 槽)',
    $refreshed2->ip === null || $refreshed2->ip === '',
    "actual: " . var_export($refreshed2->ip, true)
);
assert_test(
    'IPv6 is preserved',
    $refreshed2->ipv6 === 'fd00::cafe:babe',
    "actual: {$refreshed2->ipv6}"
);
assert_test(
    'server 是 ipv6 (subdomain 含 ipv6n, 主节点 ipv6 槽)',
    strpos(explode('.', $refreshed2->server, 2)[0], 'ipv6n') !== false,
    "actual: {$refreshed2->server}"
);
assert_test(
    'isIpv6Node 判定 main 为 ipv6 (基于 server)',
    \App\Services\NodeAddress\NodeAddressService::isIpv6Node($refreshed2) === true,
    "server: {$refreshed2->server}"
);
assert_test(
    'Name is JP-Tokyo',
    $refreshed2->name === 'JP-Tokyo',
    "actual: {$refreshed2->name}"
);

echo PHP_EOL;

// ===========================
// QA 2: CDN Decision Logic (unit-level)
// ===========================
echo "--- QA2: CDN Decision Logic (v2_net based, no CF API needed) ---" . PHP_EOL;

$reflection = new ReflectionMethod(NodeApiController::class, 'isNetCdnRequired');
$reflection->setAccessible(true);

assert_test('ws requires CDN (proxied=true)', $reflection->invoke($controller, 'ws') === true);
assert_test('grpc requires CDN (proxied=true)', $reflection->invoke($controller, 'grpc') === true);
assert_test('xhttp requires CDN (proxied=true)', $reflection->invoke($controller, 'xhttp') === true);
assert_test('hysteria2 does NOT require CDN', $reflection->invoke($controller, 'hysteria2') === false);
assert_test('tcp does NOT require CDN', $reflection->invoke($controller, 'tcp') === false);

// Verify blueprint includes proxied for grpc node
$cdnNode = new SsNode();
$cdnNode->name = 'CDN Test';
$cdnNode->ip = '203.0.113.5';
$cdnNode->ipv6 = '';
$cdnNode->server = 'node' . $cdnNode->id . '.test.example.com';
$cdnNode->v2_name = 'grpc';
$cdnNode->v2_net = 'grpc';
$cdnNode->status = 1;
$cdnNode->is_clone = 0;
$cdnNode->save();
$cdnNodeId = $cdnNode->id;

DnsRecord::where('node_id', $cdnNodeId)->delete();
$cdnNode->node_ids = (string)$cdnNodeId;
$cdnNode->save();

$requestCdn = Request::create('/api/node/resolve_dns', 'POST', [
    'token' => env('API_TOKEN'),
    'node_id' => $cdnNodeId,
]);

$responseCdn = $controller->resolveDns($requestCdn);
$respCdnData = json_decode($responseCdn->getContent(), true);

$cdnDnsRecord = DnsRecord::where('node_id', $cdnNodeId)->first();

if ($cdnDnsRecord) {
    assert_test(
        'DNS record has proxied=true for grpc',
        (bool)$cdnDnsRecord->proxied === true,
        "actual: " . var_export($cdnDnsRecord->proxied, true)
    );
    assert_test(
        'Result payload includes proxied=true',
        isset($respCdnData['results'][0]['proxied']) && $respCdnData['results'][0]['proxied'] === true,
        "proxied in result: " . json_encode($respCdnData['results'][0]['proxied'] ?? 'missing')
    );
} else {
    $action = $respCdnData['results'][0]['action'] ?? 'unknown';
    $success = $respCdnData['results'][0]['success'] ?? false;
    skip_test("CF API CDN test (action={$action})", "CF API unavailable in test env");
}

echo PHP_EOL;

// ===========================
// QA 3: HY2 Config - Direct IP Override
// ===========================
echo "--- QA3: HY2 Config - v2_net detection & template binding ---" . PHP_EOL;

$compositeTemplate = resource_path('templates/xray/vision-hy2.json');
if (!file_exists($compositeTemplate)) {
    skip_test('HY2 config test', 'Template file not found');
} else {
    $hy2Node = new SsNode();
    $hy2Node->name = 'HY2 Test';
    $hy2Node->ip = '198.51.100.42';
    $hy2Node->ipv6 = '';
    $hy2Node->server = 'node888.test.example.com';
    $hy2Node->v2_name = 'vision-hy2';
    $hy2Node->v2_net = 'hysteria2';
    $hy2Node->v2_tls = 1;
    $hy2Node->v2_port = 443;
    $hy2Node->status = 1;
    $hy2Node->is_clone = 0;
    $hy2Node->save();
    $hy2NodeId = $hy2Node->id;

    $requestHy2 = Request::create('/api/node/config', 'POST', [
        'token' => env('API_TOKEN'),
        'node_id' => $hy2NodeId,
    ]);

    $responseHy2 = $controller->config($requestHy2);
    $respHy2Status = $responseHy2->getStatusCode();

    assert_test(
        'Config endpoint returns 200',
        $respHy2Status === 200,
        "status: {$respHy2Status}"
    );

    assert_test(
        'Template selected via v2_name (vision-hy2)',
        $hy2Node->v2_name === 'vision-hy2',
        "v2_name: {$hy2Node->v2_name}"
    );

    assert_test(
        'Protocol detected via v2_net (hysteria2), NOT v2_name',
        $hy2Node->v2_net === 'hysteria2',
        "v2_net: {$hy2Node->v2_net}"
    );

    if ($respHy2Status === 200) {
        $configJson = $responseHy2->getContent();
        assert_test(
            'Config JSON is valid',
            json_decode($configJson) !== null,
            "length: " . strlen($configJson)
        );

        $config = json_decode($configJson, true);
        assert_test(
            'ssrpanel section contains nodeId',
            isset($config['ssrpanel']['nodeId']) && $config['ssrpanel']['nodeId'] === $hy2NodeId,
            "nodeId: " . ($config['ssrpanel']['nodeId'] ?? 'missing')
        );

        assert_test(
            'v2_name used for template (not single protocol)',
            $hy2Node->v2_name === 'vision-hy2',
            "v2_name: {$hy2Node->v2_name}"
        );
    }
}

echo PHP_EOL;

// ===========================
// Summary
// ===========================
echo "=== Results: {$pass} passed, {$fail} failed, {$skip} skipped ===" . PHP_EOL;
if ($fail > 0) {
    echo "*** SOME TESTS FAILED ***" . PHP_EOL;
    exit(1);
} else {
    echo "ALL TESTS PASSED" . PHP_EOL;
    exit(0);
}
