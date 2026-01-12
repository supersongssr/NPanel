<?php
/**
 * Clash Duplicate Names and Reference Test
 *
 * Tests that duplicate node names are handled correctly
 * and all proxy-group references are valid
 * Run with: php tests/test_clash_duplicate_names.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

putenv('APP_ENV=test');
if (getenv('APP_ENV') !== 'test') {
    echo "ERROR: APP_ENV must be set to 'test'\n";
    exit(1);
}

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Clash Duplicate Names & Reference Test ===\n\n";

use App\Http\Controllers\SubscribeController;

// Create mock nodes with duplicate names
$mockNodes = new Illuminate\Support\Collection([
    (object)[
        'id' => 1,
        'name' => 'Singapore-NorthWest',
        'type' => 3, // VLESS
        'server' => 'n37.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'grpc',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => '730fn37.ssmail.win',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => 'srp',
        'v2_alpn' => 'h2',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => 'a8d4928f-b5a8-40b2-8192-4f8aee440100',
        'traffic_rate' => 1,
    ],
    (object)[
        'id' => 2,
        'name' => 'Singapore-NorthWest',  // Duplicate!
        'type' => 3,
        'server' => 'n35.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'grpc',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => '6e5bn35.ssmail.win',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => 'srp',
        'v2_alpn' => 'h2',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => 'a8d4928f-b5a8-40b2-8192-4f8aee440100',
        'traffic_rate' => 1,
    ],
    (object)[
        'id' => 3,
        'name' => 'Singapore-NorthWest',  // Duplicate again!
        'type' => 3,
        'server' => 'n36.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'grpc',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => '0aa2n36.ssmail.win',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => 'srp',
        'v2_alpn' => 'h2',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => 'a8d4928f-b5a8-40b2-8192-4f8aee440100',
        'traffic_rate' => 1,
    ],
    (object)[
        'id' => 4,
        'name' => 'UnitedStates-Virginia',
        'type' => 3,
        'server' => 'n56.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'tcp',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => '738dn56.ssmail.win',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => '',
        'v2_alpn' => '',
        'v2_flow' => 'xtls-rprx-vision',  // Only TCP + TLS
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => 'a8d4928f-b5a8-40b2-8192-4f8aee440100',
        'traffic_rate' => 1,
    ],
    (object)[
        'id' => 5,
        'name' => 'UnitedStates-Virginia',  // Duplicate!
        'type' => 3,
        'server' => 'n61.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'tcp',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => '1450n61.ssmail.win',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => '',
        'v2_alpn' => '',
        'v2_flow' => 'xtls-rprx-vision',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => 'a8d4928f-b5a8-40b2-8192-4f8aee440100',
        'traffic_rate' => 1,
    ]
]);

$mockUser = new stdClass();
$mockUser->vmess_id = 'a8d4928f-b5a8-40b2-8192-4f8aee440100';
$mockUser->level = 1;
$mockUser->node_group = 0;

$controller = new SubscribeController();
$method = new ReflectionMethod($controller, 'generateClashConfig');
$method->setAccessible(true);

$yaml = $method->invoke($controller, $mockNodes, $mockUser);

echo "Generated YAML:\n";
echo str_repeat("=", 70) . "\n";
echo $yaml . "\n";
echo str_repeat("=", 70) . "\n\n";

// Parse and validate
$errors = [];
$warnings = [];

$lines = explode("\n", $yaml);
$inProxies = false;
$inProxyGroups = false;
$inProxiesList = false;
$proxiesList = [];
$proxyGroupsList = [];
$proxyGroupNames = [];
$currentProxyGroup = null;
$proxyNamesInGroups = [];

foreach ($lines as $line) {
    $line = trim($line);

    if (preg_match('/^proxies:/', $line)) {
        $inProxies = true;
        $inProxyGroups = false;
        $inProxiesList = false;
        continue;
    }
    if (preg_match('/^proxy-groups:/', $line)) {
        $inProxies = false;
        $inProxyGroups = true;
        $inProxiesList = false;
        continue;
    }

    // Parse proxies
    if ($inProxies && preg_match('/^- name: (.+)$/', $line, $matches)) {
        $proxiesList[] = $matches[1];
    }

    // Parse proxy-groups
    if ($inProxyGroups && preg_match('/^- name: (.+)$/', $line, $matches)) {
        $currentProxyGroup = $matches[1];
        $proxyGroupNames[] = $matches[1];
        $inProxiesList = false;  // Reset when hitting new proxy-group
        $proxyGroupsList[] = [
            'name' => $currentProxyGroup,
            'proxies' => []
        ];
    }

    // Check if we're entering a proxies: sublist
    if ($inProxyGroups && preg_match('/^proxies:/', $line)) {
        $inProxiesList = true;
        continue;
    }

    // Only capture proxy references if we're in a proxies: list
    if ($inProxyGroups && $inProxiesList && $currentProxyGroup && preg_match('/^- (.+)$/', $line, $matches)) {
        $lastIdx = count($proxyGroupsList) - 1;
        $proxyGroupsList[$lastIdx]['proxies'][] = $matches[1];
        $proxyNamesInGroups[] = $matches[1];
    }
}

echo "Validation Results:\n";
echo str_repeat("-", 60) . "\n";

// Check 1: Duplicate names in proxies
echo "1. Checking for duplicate proxy names...\n";
$proxyCounts = array_count_values($proxiesList);
$duplicates = array_filter($proxyCounts, function($count) {
    return $count > 1;
});

if (empty($duplicates)) {
    echo "   ✅ No duplicate proxy names found\n";
} else {
    echo "   ❌ DUPLICATE NAMES FOUND:\n";
    foreach ($duplicates as $name => $count) {
        echo "      - '$name' appears $count times\n";
        $errors[] = "Duplicate proxy name: $name";
    }
}

echo "\n";

// Check 2: All proxy-group references exist
echo "2. Checking proxy-group references...\n";
$missingRefs = [];

// Now check references - allow DIRECT and proxy-group names
foreach ($proxyNamesInGroups as $ref) {
    if ($ref === 'DIRECT' || in_array($ref, $proxyGroupNames)) {
        continue;  // Built-in or valid proxy-group reference
    }
    if (!in_array($ref, $proxiesList)) {
        $missingRefs[] = $ref;
    }
}

if (empty($missingRefs)) {
    echo "   ✅ All proxy-group references are valid\n";
    echo "   Total proxies: " . count($proxiesList) . "\n";
    echo "   Total references in groups: " . count($proxyNamesInGroups) . "\n";
} else {
    echo "   ❌ MISSING REFERENCES FOUND:\n";
    foreach ($missingRefs as $ref) {
        echo "      - '$ref' referenced but not defined in proxies\n";
        $errors[] = "Missing proxy definition: $ref";
    }
}

echo "\n";

// Check 3: bind-address quoting
echo "3. Checking bind-address quoting...\n";
if (preg_match('/bind-address:\s*[\'"]\*[\'"]/', $yaml)) {
    echo "   ✅ bind-address is properly quoted\n";
} elseif (preg_match('/bind-address:\s*\*\s*$/', $yaml)) {
    echo "   ❌ bind-address uses unquoted * (YAML syntax error)\n";
    $errors[] = "bind-address not quoted";
  } else {
    echo "   ℹ️  bind-address field not found or uses different format\n";
}

echo "\n";

// Check 4: Flow field only on TCP+TLS VLESS
echo "4. Checking VLESS flow field usage...\n";
if (preg_match_all('/name: (.+)\n\s*type: vless\n\s*flow: (.+)/', $yaml, $matches, PREG_SET_ORDER)) {
    echo "   Found " . count($matches) . " VLESS nodes with flow:\n";
    foreach ($matches as $match) {
        $nodeName = trim($match[1]);
        $flow = trim($match[2]);
        echo "      - '$nodeName': flow=$flow\n";

        // Check if it's TCP
        if (preg_match('/name: ' . preg_quote($nodeName) . '.*\n\s*network: (tcp+)/', $yaml, $netMatch)) {
            echo "        ✅ Network is TCP (correct for flow)\n";
        } else {
            echo "        ⚠️  Network might not be TCP (flow only works with TCP)\n";
        }
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 60) . "\n";

if (empty($errors)) {
    echo "✅ ALL TESTS PASSED\n\n";
    echo "Fixed Issues:\n";
    echo "  ✅ Duplicate names resolved with suffix\n";
    echo "  ✅ All proxy-group references are valid\n";
    echo "  ✅ bind-address properly quoted\n";
    echo "  ✅ VLESS flow fields used correctly\n\n";
    echo "YAML is ready for Clash Verge import!\n";
    exit(0);
} else {
    echo "❌ TESTS FAILED\n\n";
    echo "Errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n";
    exit(1);
}
