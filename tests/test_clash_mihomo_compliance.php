<?php
/**
 * Clash Mihomo Compliance Test
 *
 * 严格按照 Mihomo 内核规范验证生成的配置
 * Run with: php tests/test_clash_mihomo_compliance.php
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

echo "=== Clash Mihomo Kernel Compliance Test ===\n\n";

use App\Http\Controllers\SubscribeController;

// 测试用例 1: VLESS TCP + Vision
$testNodes = new Illuminate\Support\Collection([
    (object)[
        'id' => 61,
        'name' => 'UnitedStates-Virginia',
        'type' => 3, // VLESS
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
$mockUser->id = 1;

$controller = new SubscribeController();
$method = new ReflectionMethod($controller, 'generateClashConfig');
$method->setAccessible(true);

$yaml = $method->invoke($controller, $testNodes, $mockUser);

echo "Generated YAML (first 100 lines):\n";
echo str_repeat("=", 70) . "\n";
$lines = explode("\n", $yaml);
foreach (array_slice($lines, 0, min(100, count($lines))) as $line) {
    echo $line . "\n";
}
echo str_repeat("=", 70) . "\n\n";

echo "Mihomo Compliance Check:\n";
echo str_repeat("-", 70) . "\n";

$errors = [];
$warnings = [];

// 1. 检查 YAML 基本结构
echo "1. YAML Structure\n";

$requiredSections = ['proxies:', 'proxy-groups:', 'rules:'];
foreach ($requiredSections as $section) {
    if (strpos($yaml, $section) === false) {
        $errors[] = "Missing required section: $section";
        echo "   ❌ Missing: $section\n";
    } else {
        echo "   ✅ Found: $section\n";
    }
}
echo "\n";

// 2. 检查 proxies 数量
echo "2. Proxy Count\n";

preg_match_all('/^- name:/', $yaml, $nameMatches);
$proxyCount = count($nameMatches[0]);
echo "   Total proxies found: $proxyCount\n";

if ($proxyCount == 0) {
    $errors[] = "No proxies found in YAML";
    echo "   ❌ ERROR: No proxies defined!\n";
} else {
    echo "   ✅ Proxies defined\n";
}
echo "\n";

// 3. 检查 VLESS 节点规范
echo "3. VLESS Node Specification (Mihomo Wiki)\n";

// 提取 VLESS 节点块
if (preg_match('/type: vless\n(?:[ \t]+[^\n]+\n)*/', $yaml, $vlessMatches)) {
    $vlessBlock = $vlessMatches[0];
    echo "   VLESS Block:\n";
    echo "   " . str_replace("\n", "\n   ", trim($vlessBlock)) . "\n\n";

    // 必填字段检查
    $requiredFields = ['type: vless', 'uuid:', 'udp:', 'tls:'];
    foreach ($requiredFields as $field) {
        if (strpos($vlessBlock, $field) === false) {
            $errors[] = "VLESS missing required field: $field";
            echo "   ❌ Missing required field: $field\n";
        } else {
            echo "   ✅ Has: $field\n";
        }
    }

    // 禁止字段检查（VMess 字段）
    $forbiddenFields = ['alterId:', 'cipher:'];
    foreach ($forbiddenFields as $field) {
        if (strpos($vlessBlock, $field) !== false) {
            $errors[] = "VLESS has forbidden field: $field";
            echo "   ❌ FORBIDDEN field found: $field\n";
        } else {
            echo "   ✅ No forbidden field: $field\n";
        }
    }

    // flow 字段检查
    if (preg_match('/flow:\s*(\S+)/', $vlessBlock, $flowMatch)) {
        $flow = $flowMatch[1];
        echo "   Flow: $flow\n";

        // flow 必须配合 TCP + TLS
        if (strpos($vlessBlock, 'network: tcp') === false) {
            $errors[] = "flow field requires network: tcp";
            echo "   ❌ flow requires network: tcp\n";
        } elseif (strpos($vlessBlock, 'tls: true') === false) {
            $errors[] = "flow field requires tls: true";
            echo "   ❌ flow requires tls: true\n";
        } else {
            echo "   ✅ flow used correctly (TCP + TLS)\n";
        }
    }

    // client-fingerprint 检查
    if (strpos($vlessBlock, 'client-fingerprint:') !== false) {
        echo "   ✅ Has client-fingerprint (recommended)\n";
    } else {
        $warnings[] = "Missing client-fingerprint (recommended by Mihomo)";
        echo "   ⚠️  Missing client-fingerprint (recommended)\n";
    }
} else {
    echo "   ℹ️  No VLESS nodes found\n";
}
echo "\n";

// 4. 检查 proxy-groups 引用
echo "4. Proxy-Groups References\n";

// 提取所有节点名称
preg_match_all('/proxies:\s*\n(?:[ \t]+-[ \t]+name:\s*"([^"]+)"\s*\n)+/', $yaml, $proxyNameMatches);
$proxyNames = $proxyNameMatches[1] ?? [];

echo "   Defined proxies: " . count($proxyNames) . "\n";
foreach ($proxyNames as $name) {
    echo "      - $name\n";
}

// 提取 proxy-groups 中的引用
preg_match_all('/proxy-groups:\s*\n(?:[ \t]+-[ \t]+\S+\s*\n(?:[ \t]+[^\n]+\n)*?(?:[ \t]+proxies:\s*\n((?:[ \t]+-[ \t]+[^\n]+\n)+))+/', $yaml, $groupsMatches);

$allRefs = [];
if (!empty($groupsMatches[1])) {
    foreach ($groupsMatches[1] as $refsBlock) {
        preg_match_all('/-[ \t]+"([^"]+)"/', $refsBlock, $refMatches);
        $allRefs = array_merge($allRefs, $refMatches[1] ?? []);
    }
}

echo "\n   References in proxy-groups: " . count($allRefs) . "\n";
foreach ($allRefs as $ref) {
    echo "      - $ref\n";
}

// 验证所有引用都存在
$missingRefs = array_diff($allRefs, $proxyNames);
$missingRefs = array_filter($missingRefs, function($ref) {
    return $ref !== 'auto' && $ref !== 'DIRECT';
});

echo "\n";
if (empty($missingRefs)) {
    echo "   ✅ All proxy-group references are valid\n";
} else {
    $errors[] = "Missing proxy references: " . implode(', ', $missingRefs);
    echo "   ❌ Invalid references: " . implode(', ', $missingRefs) . "\n";
}
echo "\n";

// 5. 检查 YAML 语法问题
echo "5. YAML Syntax Validation\n";

$syntaxErrors = [];

// 检查 Tab 字符
if (preg_match('/\t/', $yaml)) {
    $syntaxErrors[] = "Contains tab characters (YAML requires spaces)";
}

// 检查未加引号的特殊字符
if (preg_match('/^- name:\s*[^"][^"\s]*[#|\[\]]/', $yaml)) {
    $syntaxErrors[] = "Unquoted name with special characters (#|[])";
}

// 检查冒号后的空格
if (preg_match('/^\s*[^\s]+:[^ \s]/m', $yaml)) {
    // 排除 URL 等合法情况
    if (!preg_match_all('/^\s+(url|servername):\s*["\']?https?:/m', $yaml)) {
        $syntaxErrors[] = "Missing space after colon";
    }
}

if (empty($syntaxErrors)) {
    echo "   ✅ No obvious syntax errors\n";
} else {
    foreach ($syntaxErrors as $err) {
        $errors[] = "Syntax: $err";
        echo "   ❌ $err\n";
    }
}
echo "\n";

// 6. 测试 YAML 解析（如果可用）
echo "6. YAML Parse Test\n";

if (extension_loaded('yaml')) {
    $parsed = yaml_parse($yaml);
    if ($parsed === false) {
        $errors[] = "YAML parse failed";
        echo "   ❌ YAML parse failed\n";
    } else {
        echo "   ✅ YAML successfully parsed\n";

        if (isset($parsed['proxies']) && is_array($parsed['proxies'])) {
            echo "   Parsed " . count($parsed['proxies']) . " proxies\n";
        }
        if (isset($parsed['proxy-groups']) && is_array($parsed['proxy-groups'])) {
            echo "   Parsed " . count($parsed['proxy-groups']) . " proxy-groups\n";
        }
    }
} else {
    echo "   ℹ️  YAML extension not available, skipping parse test\n";
}
echo "\n";

// Summary
echo str_repeat("=", 70) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 70) . "\n";

if (empty($errors)) {
    echo "✅ NO CRITICAL ERRORS\n\n";

    if (empty($warnings)) {
        echo "✅ NO WARNINGS\n\n";
        echo "Configuration is fully Mihomo compliant!\n";
        echo "\nIf Clash Verge still doesn't show nodes:\n";
        echo "1. Check the Content-Type header is 'text/yaml; charset=utf-8'\n";
        echo "2. Verify the subscription URL is correct\n";
        echo "3. Check Clash Verge logs for parse errors\n";
        echo "4. Try importing the saved YAML file manually\n";
        exit(0);
    } else {
        echo "⚠️  WARNINGS: " . count($warnings) . "\n\n";
        foreach ($warnings as $warning) {
            echo "  - $warning\n";
        }
        echo "\nConfiguration should work, but review warnings.\n";
        exit(0);
    }
} else {
    echo "❌ CRITICAL ERRORS: " . count($errors) . "\n\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\nThese errors will cause Mihomo to reject the configuration.\n";
    exit(1);
}
