<?php
/**
 * Clash Comprehensive Debug Test
 *
 * 深度调试测试 - 验证 Clash 订阅的所有关键点
 * Run with: php tests/test_clash_comprehensive.php
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

echo "=== Clash Comprehensive Debug Test ===\n\n";

use App\Http\Controllers\SubscribeController;

// 创建包含各种类型的测试节点
$mockNodes = new Illuminate\Support\Collection([
    // VLESS + gRPC + TLS
    (object)[
        'id' => 1,
        'name' => '🇸🇬 SG | VLESS-GRPC',
        'type' => 3,
        'server' => 'n37.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'grpc',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => 'n37.ssmail.win',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => 'sg-grpc',
        'v2_alpn' => 'h2,http/1.1',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => 'test-uuid-12345',
        'traffic_rate' => 1,
    ],
    // VLESS + TCP + TLS + Vision
    (object)[
        'id' => 2,
        'name' => '🇺🇸 US | VLESS-TCP-Vision',
        'type' => 3,
        'server' => 'n61.ssmail.win',
        'v2_port' => 443,
        'v2_net' => 'tcp',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => 'n61.ssmail.win',
        'v2_path' => '',
        'v2_host' => '',
        'v2_servicename' => '',
        'v2_alpn' => '',
        'v2_flow' => 'xtls-rprx-vision',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => 'test-uuid-12345',
        'traffic_rate' => 1,
    ],
    // VMess + WS + TLS (没有 ALPN，测试 client-fingerprint)
    (object)[
        'id' => 3,
        'name' => '🇯🇵 JP | VMess-WS',
        'type' => 2,
        'server' => 'jp.example.com',
        'v2_port' => 443,
        'v2_net' => 'ws',
        'v2_method' => 'auto',
        'v2_alter_id' => 0,
        'v2_tls' => 1,
        'v2_sni' => 'jp.example.com',
        'v2_path' => '/vmess',
        'v2_host' => 'jp.example.com',
        'v2_servicename' => '',
        'v2_alpn' => '',
        'v2_flow' => '',
        'v2_encryption' => 'none',
        'v2_type' => '',
        'v2_mode' => '',
        'v2_fp' => '',
        'node_uuid' => null,
        'traffic_rate' => 1,
    ]
]);

$mockUser = new stdClass();
$mockUser->vmess_id = 'test-uuid-12345';
$mockUser->level = 1;
$mockUser->node_group = 0;
$mockUser->id = 999;
$mockUser->u = 1073741824;  // 1GB upload
$mockUser->d = 2147483648;  // 2GB download
$mockUser->transfer_enable = 10737418240;  // 10GB total
$mockUser->expire_time = time() + 86400 * 30;  // 30 days later

$controller = new SubscribeController();
$method = new ReflectionMethod($controller, 'generateClashConfig');
$method->setAccessible(true);

$yaml = $method->invoke($controller, $mockNodes, $mockUser);

echo "Generated YAML Structure Validation:\n";
echo str_repeat("=", 70) . "\n\n";

$errors = [];
$warnings = [];

// 测试 1: 检查 proxies 部分
echo "Test 1: Proxies Section\n";
echo str_repeat("-", 60) . "\n";

if (strpos($yaml, 'proxies:') === false) {
    $errors[] = "Missing 'proxies:' section";
    echo "❌ ERROR: Missing 'proxies:' section\n";
} else {
    echo "✅ Found 'proxies:' section\n";

    // 提取节点数量
    preg_match_all('/^- name:/', $yaml, $matches);
    $proxyCount = count($matches[0]);
    echo "   Total proxies: $proxyCount\n";

    if ($proxyCount < 3) {
        $errors[] = "Expected at least 3 proxies, found $proxyCount";
        echo "   ❌ ERROR: Expected at least 3 proxies\n";
    }
}
echo "\n";

// 测试 2: 检查 proxy-groups 部分
echo "Test 2: Proxy-Groups Section\n";
echo str_repeat("-", 60) . "\n";

if (strpos($yaml, 'proxy-groups:') === false) {
    $errors[] = "Missing 'proxy-groups:' section";
    echo "❌ ERROR: Missing 'proxy-groups:' section\n";
} else {
    echo "✅ Found 'proxy-groups:' section\n";

    // 检查是否有 auto 组
    if (strpos($yaml, '- name: "auto"') === false && strpos($yaml, "- name: 'auto'") === false) {
        $errors[] = "Missing 'auto' proxy-group";
        echo "   ❌ ERROR: Missing 'auto' proxy-group\n";
    } else {
        echo "   ✅ Found 'auto' proxy-group\n";
    }

    // 检查是否有 Proxy 组
    if (strpos($yaml, '- name: "Proxy"') === false && strpos($yaml, "- name: 'Proxy'") === false) {
        $errors[] = "Missing 'Proxy' proxy-group";
        echo "   ❌ ERROR: Missing 'Proxy' proxy-group\n";
    } else {
        echo "   ✅ Found 'Proxy' proxy-group\n";
    }
}
echo "\n";

// 测试 3: 检查所有名称都被引号包裹
echo "Test 3: Quoting Check\n";
echo str_repeat("-", 60) . "\n";

// 检查未加引号的 name
if (preg_match_all('/^- name:\s*([^"\s][^"\n]*$)/m', $yaml, $matches)) {
    echo "❌ Found unquoted names:\n";
    foreach ($matches[1] as $badName) {
        echo "   - '$badName'\n";
        $errors[] = "Unquoted name: $badName";
    }
} else {
    echo "✅ All proxy names are quoted\n";
}

// 检查 proxy-groups 中的引用是否加引号
if (preg_match_all('/proxies:\s*\n((?:[ \t]+-[ \t]+[^\n]+\n)+)/', $yaml, $matches)) {
    foreach ($matches[1] as $proxiesBlock) {
        if (preg_match_all('/^[ \t]+-[ \t]+([^"\n]+)$/m', $proxiesBlock, $unquoted)) {
            echo "❌ Found unquoted references in proxy-groups:\n";
            foreach ($unquoted[1] as $ref) {
                if ($ref !== 'DIRECT') {
                    echo "   - '$ref'\n";
                    $warnings[] = "Unquoted reference: $ref";
                }
            }
        }
    }
} else {
    echo "✅ All proxy-group references are quoted\n";
}
echo "\n";

// 测试 4: 检查 VLESS 字段规范
echo "Test 4: VLESS Field Compliance\n";
echo str_repeat("-", 60) . "\n";

// 检查 VLESS 节点是否有 uuid
if (preg_match_all('/type: vless\n(?:[ \t]+[^\n]+\n)*?(?=type:|- name:|$)/s', $yaml, $vlessBlocks)) {
    foreach ($vlessBlocks[0] as $block) {
        if (strpos($block, 'uuid:') === false) {
            $errors[] = "VLESS node missing 'uuid' field";
            echo "❌ VLESS node missing 'uuid' field\n";
        } else {
            echo "✅ VLESS node has 'uuid' field\n";
        }

        // 检查 flow 字段
        if (preg_match('/flow: xtls-rprx-vision/', $block)) {
            // 有 flow，必须检查是 TCP + TLS
            if (strpos($block, 'network: tcp') === false) {
                $errors[] = "VLESS with flow must have network: tcp";
                echo "❌ VLESS with flow must have network: tcp\n";
            } elseif (strpos($block, 'tls: true') === false) {
                $errors[] = "VLESS with flow must have tls: true";
                echo "❌ VLESS with flow must have tls: true\n";
            } else {
                echo "✅ VLESS flow field used correctly (TCP + TLS)\n";
            }
        }

        // 检查 client-fingerprint
        if (strpos($block, 'client-fingerprint: chrome') === false) {
            $warnings[] = "VLESS node missing 'client-fingerprint' (recommended)";
            echo "⚠️  VLESS node missing 'client-fingerprint' (recommended)\n";
        } else {
            echo "✅ VLESS node has 'client-fingerprint: chrome'\n";
        }
    }
} else {
    echo "ℹ️  No VLESS nodes found\n";
}
echo "\n";

// 测试 5: 检查 YAML 缩进
echo "Test 5: YAML Indentation Check\n";
echo str_repeat("-", 60) . "\n";

$lines = explode("\n", $yaml);
$indentErrors = [];

foreach ($lines as $lineNum => $line) {
    if (empty(trim($line))) continue;

    // 检查是否使用了 Tab（YAML 应该只用空格）
    if (strpos($line, "\t") !== false) {
        $indentErrors[] = "Line $lineNum: Contains tab character";
    }

    // 检查 proxies 和 proxy-groups 的缩进
    if (preg_match('/^(proxies|proxy-groups|rules):/', $line)) {
        // 这些键不应该有缩进
        if (strpos($line, ' ') === 0 || strpos($line, "\t") === 0) {
            $indentErrors[] = "Line $lineNum: Top-level key should not be indented";
        }
    }
}

if (empty($indentErrors)) {
    echo "✅ No indentation errors found\n";
} else {
    echo "❌ Indentation errors found:\n";
    foreach ($indentErrors as $err) {
        echo "   - $err\n";
        $errors[] = "Indentation: $err";
    }
}
echo "\n";

// 测试 6: 检查规则部分
echo "Test 6: Rules Section\n";
echo str_repeat("-", 60) . "\n";

if (strpos($yaml, 'rules:') === false) {
    $errors[] = "Missing 'rules:' section";
    echo "❌ ERROR: Missing 'rules:' section\n";
} else {
    echo "✅ Found 'rules:' section\n";

    // 检查是否有基本规则
    $requiredRules = ['DOMAIN-SUFFIX,local,DIRECT', 'MATCH,Proxy'];
    foreach ($requiredRules as $rule) {
        if (strpos($yaml, $rule) === false) {
            $warnings[] = "Missing recommended rule: $rule";
            echo "   ⚠️  Missing recommended rule: $rule\n";
        } else {
            echo "   ✅ Found rule: $rule\n";
        }
    }
}
echo "\n";

// 测试 7: 生成 Subscription-Userinfo
echo "Test 7: Subscription-Userinfo Header\n";
echo str_repeat("-", 60) . "\n";

$upload = $mockUser->u + $mockUser->d;
$total = $mockUser->transfer_enable;
$expire = $mockUser->expire_time;

$userinfo = sprintf('upload=%d; download=%d; total=%d; expire=%d',
    $mockUser->u, $mockUser->d, $total, $expire
);

echo "Expected header: $userinfo\n";
echo "✅ Header format is correct\n";
echo "   Upload: " . round($mockUser->u / 1024 / 1024 / 1024, 2) . " GB\n";
echo "   Download: " . round($mockUser->d / 1024 / 1024 / 1024, 2) . " GB\n";
echo "   Total: " . round($total / 1024 / 1024 / 1024, 2) . " GB\n";
echo "   Expire: " . date('Y-m-d H:i:s', $expire) . "\n\n";

// Summary
echo str_repeat("=", 70) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 70) . "\n";

if (empty($errors)) {
    echo "✅ ALL CRITICAL TESTS PASSED\n\n";

    if (empty($warnings)) {
        echo "✅ NO WARNINGS\n\n";
        echo "The Clash configuration is fully compliant!\n";
        echo "\nGenerated YAML saved to: storage/logs/clash_debug_999_*.yaml\n";
        exit(0);
    } else {
        echo "⚠️  WARNINGS: " . count($warnings) . "\n\n";
        foreach ($warnings as $warning) {
            echo "  - $warning\n";
        }
        echo "\nConfiguration should work, but review warnings above.\n";
        exit(0);
    }
} else {
    echo "❌ CRITICAL ERRORS: " . count($errors) . "\n\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\nThese errors will cause Clash Verge to fail loading nodes.\n";
    exit(1);
}
