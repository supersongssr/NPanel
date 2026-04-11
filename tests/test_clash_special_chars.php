<?php
/**
 * Clash Special Characters Test
 *
 * Tests that node names with special characters (|, #, emojis)
 * are properly quoted in YAML output
 * Run with: php tests/test_clash_special_chars.php
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

echo "=== Clash Special Characters Test ===\n\n";

use App\Http\Controllers\SubscribeController;

// Create mock nodes with special characters
$mockNodes = new Illuminate\Support\Collection([
    (object)[
        'id' => 1,
        'name' => '🇸🇬 Singapore | #37',
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
        'name' => '🇺🇸 US | #56 [Premium]',
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

echo "Generated YAML (showing proxies and proxy-groups):\n";
echo str_repeat("=", 70) . "\n";

// Extract and show relevant sections
$lines = explode("\n", $yaml);
$inProxies = false;
$inProxyGroups = false;
$showLines = false;

foreach ($lines as $line) {
    $trimmed = trim($line);

    if (preg_match('/^proxies:/', $trimmed)) {
        $inProxies = true;
        $inProxyGroups = false;
        $showLines = true;
    }
    if (preg_match('/^proxy-groups:/', $trimmed)) {
        $inProxies = false;
        $inProxyGroups = true;
        $showLines = true;
    }
    if (preg_match('/^rules:/', $trimmed)) {
        $showLines = false;
    }

    if ($showLines) {
        echo $line . "\n";
    }
}

echo str_repeat("=", 70) . "\n\n";

// Validate
echo "Validation Results:\n";
echo str_repeat("-", 60) . "\n";

$errors = [];

// Check 1: Names with special characters must be quoted
echo "1. Checking special character quoting...\n";

if (preg_match('/name:\s*[^"\s].*[\|#\[\]]/', $yaml)) {
    echo "   ❌ Found unquoted names with special characters!\n";
    preg_match_all('/name:\s*([^"\n].*[\|#\[\]])/', $yaml, $matches);
    foreach ($matches[1] as $badName) {
        echo "      - '$badName' should be quoted\n";
        $errors[] = "Unquoted name with special chars: $badName";
    }
} else {
    echo "   ✅ All names with special characters are quoted\n";
}

echo "\n";

// Check 2: Extract proxy names and check they match in proxy-groups
echo "2. Checking name consistency between proxies and proxy-groups...\n";

$proxiesList = [];
$proxyGroupRefs = [];

$lines = explode("\n", $yaml);
$inProxies = false;
$inProxyGroups = false;
$inProxiesList = false;

foreach ($lines as $line) {
    $trimmed = trim($line);

    if (preg_match('/^proxies:/', $trimmed)) {
        $inProxies = true;
        $inProxyGroups = false;
        $inProxiesList = false;
        continue;
    }
    if (preg_match('/^proxy-groups:/', $trimmed)) {
        $inProxies = false;
        $inProxyGroups = true;
        $inProxiesList = false;
        continue;
    }

    // Parse proxy names - 使用 trimmed 版本
    if ($inProxies && preg_match('/^- name:\s*"(.+)"/', $trimmed, $matches)) {
        $proxiesList[] = stripslashes($matches[1]);
    } elseif ($inProxies && preg_match('/^- name:\s*(.+)/', $trimmed, $matches)) {
        $proxiesList[] = $matches[1];
    }

    // Check if entering proxies list within proxy-groups (必须在 name 检查之后)
    if ($inProxyGroups && preg_match('/^proxies:/', $trimmed)) {
        $inProxiesList = true;
        continue;
    }

    // Reset on new proxy-group (必须在 proxies 检查之后)
    if ($inProxyGroups && preg_match('/^- name:/', $trimmed)) {
        $inProxiesList = false;
    }

    // Parse proxy-group references - 使用 trimmed 版本（包括 auto）
    if ($inProxyGroups && $inProxiesList && preg_match('/^-\s*"(.+)"/', $trimmed, $matches)) {
        $ref = stripslashes($matches[1]);
        if ($ref !== 'DIRECT') {
            $proxyGroupRefs[] = $ref;
        }
    } elseif ($inProxyGroups && $inProxiesList && preg_match('/^-\s*(.+)/', $trimmed, $matches)) {
        if ($matches[1] !== 'DIRECT') {
            $proxyGroupRefs[] = $matches[1];
        }
    }

    // DEBUG: 输出当前行状态 (已禁用)
    // if ($inProxyGroups && (strpos($trimmed, 'proxies:') !== false || strpos($trimmed, '- name:') !== false || strpos($trimmed, '- "') !== false)) {
    //     $debug = "Line: $trimmed | inProxyGroups=$inProxyGroups | inProxiesList=$inProxiesList";
    //     echo "DEBUG: $debug\n";
    // }
}

echo "   Proxies defined: " . count($proxiesList) . "\n";
foreach ($proxiesList as $proxy) {
    echo "      - '$proxy'\n";
}

echo "\n";
echo "   References in proxy-groups: " . count($proxyGroupRefs) . "\n";
foreach ($proxyGroupRefs as $ref) {
    echo "      - '$ref'\n";
}

// Check if all refs exist
echo "\n";
$missingRefs = array_diff($proxyGroupRefs, $proxiesList);
if (empty($missingRefs)) {
    echo "   ✅ All proxy-group references exist in proxies list\n";
} else {
    echo "   ❌ Missing references:\n";
    foreach ($missingRefs as $ref) {
        echo "      - '$ref'\n";
        $errors[] = "Missing proxy: $ref";
    }
}

echo "\n";

// Check 3: Validate YAML syntax
echo "3. Checking YAML syntax...\n";

// Try to parse with a basic YAML parser
$syntaxErrors = [];

// Check for common YAML errors
if (preg_match('/^\s+-\s[^:\s]*$/m', $yaml)) {
    $syntaxErrors[] = "List item without value";
}

if (preg_match('/\t/', $yaml)) {
    $syntaxErrors[] = "Tabs found (should use spaces)";
}

if (empty($syntaxErrors)) {
    echo "   ✅ No obvious YAML syntax errors\n";
} else {
    echo "   ❌ Syntax errors found:\n";
    foreach ($syntaxErrors as $err) {
        echo "      - $err\n";
        $errors[] = "YAML syntax: $err";
    }
}

echo "\n";
echo str_repeat("=", 60) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 60) . "\n";

if (empty($errors)) {
    echo "✅ ALL TESTS PASSED\n\n";
    echo "Features Validated:\n";
    echo "  ✅ Special characters are properly quoted\n";
    echo "  ✅ Names match between proxies and proxy-groups\n";
    echo "  ✅ No YAML syntax errors\n\n";
    echo "Configuration is ready for Clash Verge!\n";
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
