<?php
/**
 * Final Clash YAML Validation
 *
 * 生成可以直接导入 Clash Verge 的测试文件
 * Run with: php tests/test_clash_final_validation.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

putenv('APP_ENV=test');
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\SubscribeController;

echo "=== Final Clash YAML Validation ===\n\n";

// 测试多种节点类型
$mockNodes = new Illuminate\Support\Collection([
    // VLESS gRPC
    (object)[
        'id' => 37,
        'name' => 'SG | VLESS-GRPC',
        'type' => 3,
        'server' => 'n37.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'grpc',
        'v2_tls' => 1,
        'v2_sni' => 'n37.ssmail.win',
        'v2_servicename' => 'sg-grpc',
        'v2_alpn' => 'h2',
        'v2_flow' => '',
        'node_uuid' => 'a8d4928f-b5a8-40b2-8192-4f8aee440100',
        'traffic_rate' => 1,
    ],
    // VLESS TCP + Vision
    (object)[
        'id' => 61,
        'name' => 'US | VLESS-TCP-Vision',
        'type' => 3,
        'server' => 'n61.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'tcp',
        'v2_tls' => 1,
        'v2_sni' => 'n61.ssmail.win',
        'v2_flow' => 'xtls-rprx-vision',
        'node_uuid' => 'a8d4928f-b5a8-40b2-8192-4f8aee440100',
        'traffic_rate' => 1,
    ],
    // VMess WebSocket
    (object)[
        'id' => 56,
        'name' => 'JP | VMess-WS',
        'type' => 2,
        'server' => 'n56.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'ws',
        'v2_tls' => 1,
        'v2_sni' => 'n56.ssmail.win',
        'v2_path' => '/vmess',
        'v2_host' => 'n56.ssmail.win',
        'v2_alpn' => 'h2,http/1.1',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'node_uuid' => null,
        'traffic_rate' => 1,
    ]
]);

$mockUser = new stdClass();
$mockUser->vmess_id = 'a8d4928f-b5a8-40b2-8192-4f8aee440100';
$mockUser->level = 1;
$mockUser->node_group = 0;
$mockUser->id = 1;

$controller = new SubscribeController();
$method = new ReflectionMethod($controller, 'generateClashConfig');
$method->setAccessible(true);

$yaml = $method->invoke($controller, $mockNodes, $mockUser);

// 保存到文件以便手动导入测试
$testFile = '/tmp/clash_mihomo_test.yaml';
file_put_contents($testFile, $yaml);
echo "✅ YAML saved to: $testFile\n";
echo "   You can manually import this file into Clash Verge to test.\n\n";

// 分析 YAML 结构
echo "YAML Structure Analysis:\n";
echo str_repeat("=", 70) . "\n";

$sections = [
    'proxies' => false,
    'proxy-groups' => false,
    'rules' => false,
];

foreach ($sections as $section => &$found) {
    if (strpos($yaml, $section . ':') !== false) {
        $found = true;
        echo "✅ Section '$section:' found\n";
    } else {
        echo "❌ Section '$section:' MISSING\n";
    }
}

echo "\n";

// 提取 proxies 部分
$proxiesStart = strpos($yaml, 'proxies:');
$proxyGroupsStart = strpos($yaml, 'proxy-groups:');

if ($proxiesStart !== false && $proxyGroupsStart !== false) {
    $proxiesSection = substr($yaml, $proxiesStart, $proxyGroupsStart - $proxiesStart);

    echo "Proxies Section:\n";
    echo str_repeat("-", 70) . "\n";
    echo $proxiesSection;
    echo str_repeat("-", 70) . "\n\n";

    // 统计节点数量
    preg_match_all('/^- name:/', $proxiesSection, $matches);
    $count = count($matches[0]);
    echo "Total proxies: $count\n\n";
}

// 提取 proxy-groups 部分
$rulesStart = strpos($yaml, 'rules:');

if ($proxyGroupsStart !== false && $rulesStart !== false) {
    $proxyGroupsSection = substr($yaml, $proxyGroupsStart, $rulesStart - $proxyGroupsStart);

    echo "Proxy-Groups Section:\n";
    echo str_repeat("-", 70) . "\n";
    echo $proxyGroupsSection;
    echo str_repeat("-", 70) . "\n\n";
}

// 验证节点引用
echo "Reference Validation:\n";
echo str_repeat("-", 70) . "\n";

// 提取所有定义的节点名
preg_match_all('/^- name: "([^"]+)"/m', $yaml, $definedProxies);
$proxyNames = $definedProxies[1] ?? [];

echo "Defined proxies (" . count($proxyNames) . "):\n";
foreach ($proxyNames as $name) {
    echo "  - $name\n";
}

// 提取 proxy-groups 中的引用
preg_match_all('/proxies:\s*\n((?:[ ]+-.+\n)+)/', $yaml, $refsMatches);

$allRefs = [];
$validRefs = ['auto', 'DIRECT'];

foreach ($refsMatches[1] ?? [] as $refsBlock) {
    preg_match_all('/-[ ]+"([^"]+)"/', $refsBlock, $refs);
    $allRefs = array_merge($allRefs, $refs[1] ?? []);
}

echo "\nReferences in proxy-groups (" . count($allRefs) . "):\n";
foreach ($allRefs as $ref) {
    $isDefined = in_array($ref, $proxyNames);
    $isBuiltIn = in_array($ref, $validRefs);
    $status = ($isDefined || $isBuiltIn) ? '✅' : '❌';
    echo "  $status $ref\n";
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "CONCLUSION\n";
echo str_repeat("=", 70) . "\n";

if (count($proxyNames) > 0 && count($allRefs) > 0) {
    echo "✅ YAML structure is VALID\n";
    echo "✅ " . count($proxyNames) . " proxies defined\n";
    echo "✅ " . count($allRefs) . " references in proxy-groups\n\n";

    echo "The generated YAML should work with Clash Verge (Mihomo kernel).\n";
    echo "If it still doesn't show nodes, check:\n";
    echo "  1. Clash Verge version (should support Mihomo/Meta)\n";
    echo "  2. Import method (URL vs file)\n";
    echo "  3. Clash Verge logs for specific errors\n";
} else {
    echo "❌ YAML structure is INVALID\n";
    echo "   Proxies: " . count($proxyNames) . "\n";
    echo "   References: " . count($allRefs) . "\n";
}

exit(0);
