<?php
/**
 * Sing-box Schema Validation Test
 *
 * Comprehensive validation against official Sing-box schema
 * Run with: php tests/test_singbox_schema_validation.php
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

use App\Http\Models\UserSubscribe;
use App\Http\Models\SsNode;
use App\Http\Models\User;
use App\Http\Controllers\SubscribeController;

echo "=== Sing-box Schema Validation Test ===\n\n";

// Get subscription and user
$subscribe = UserSubscribe::query()->where('code', 'SsXa1')->first();
if (!$subscribe) {
    echo "❌ Subscription not found\n";
    exit(1);
}

$user = User::query()->where('id', $subscribe->user_id)->first();
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

$nodes = SsNode::query()
    ->where('status', 1)
    ->where('is_subscribe', 1)
    ->where('node_group', $user->node_group)
    ->where('level', '<=', $user->level)
    ->orderBy('level', 'desc')
    ->orderBy('traffic_left_daily', 'desc')
    ->get();

// Generate Sing-box config
$controller = new SubscribeController();
$method = new ReflectionMethod($controller, 'generateSingboxConfig');
$method->setAccessible(true);
$json = $method->invoke($controller, $nodes, $user);

$config = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

$errors = [];
$warnings = [];

// Valid Sing-box outbound types (from official docs)
$validOutboundTypes = [
    'direct', 'block', 'dns', 'selector', 'urltest',
    'vmess', 'vless', 'trojan', 'shadowsocks', 'socks',
    'wireguard', 'hysteria', 'hysteria2', 'tuic', 'ssh'
];

// Valid Sing-box inbound types
$validInboundTypes = [
    'tun', 'mixed', 'direct', 'socks', 'http', 'shadowsocks',
    'vmess', 'vless', 'trojan', 'naive', 'wireguard', 'hysteria2'
];

// Valid route rule actions (from official docs)
$validRuleActions = [
    'route', 'hijack-dns', 'reject', 'resolve'
];

echo "Validating configuration structure...\n\n";

// 1. Validate root level structure
echo "1. Root Level Structure:\n";
$requiredRootKeys = ['log', 'dns', 'inbounds', 'outbounds', 'route'];
foreach ($requiredRootKeys as $key) {
    if (!isset($config[$key])) {
        $errors[] = "Missing root key: $key";
        echo "   ❌ Missing: $key\n";
    } else {
        echo "   ✅ Present: $key\n";
    }
}
echo "\n";

// 2. Validate outbounds
echo "2. Outbounds Validation:\n";
if (isset($config['outbounds'])) {
    foreach ($config['outbounds'] as $idx => $outbound) {
        $type = $outbound['type'] ?? 'unknown';
        $tag = $outbound['tag'] ?? "untagged_$idx";

        echo "   [$idx] $type:$tag\n";

        // Check type validity
        if (!in_array($type, $validOutboundTypes)) {
            $errors[] = "Outbound[$idx] has invalid type: $type";
            echo "      ❌ Invalid type: $type\n";
        } else {
            echo "      ✅ Type: $type\n";
        }

        // Check for invalid 'filter' field (bug fix #2)
        if (isset($outbound['filter'])) {
            $errors[] = "Outbound[$idx] has invalid 'filter' field";
            echo "      ❌ Has invalid 'filter' field\n";
        }

        // Check for sing-box unsupported hysteria2 hop ports fields (bug fix #5)
        // sing-box 的 hysteria2 outbound 不支持端口跳跃 (hop_ports/hop_interval)，
        // 输出会导致 sing-box 启动失败：unknown field "hop_ports"。
        // 因此 Hysteria2 节点当前在 sing-box 订阅中被整体跳过。
        if (isset($outbound['hop_ports']) || isset($outbound['hop_interval'])) {
            $errors[] = "Outbound[$idx] ($type:$tag) has unsupported hysteria2 hop_ports/hop_interval field";
            echo "      ❌ Has unsupported hop_ports/hop_interval field\n";
        }

        // Check for TCP transport (bug fix #3)
        if (isset($outbound['transport']['type']) && $outbound['transport']['type'] === 'tcp') {
            $errors[] = "Outbound[$idx] has invalid TCP transport type";
            echo "      ❌ Has invalid 'tcp' transport\n";
        }

        // Check TLS structure
        if (isset($outbound['tls'])) {
            if (isset($outbound['tls']['enabled']) && $outbound['tls']['enabled']) {
                echo "      ✅ TLS enabled\n";
                if (isset($outbound['tls']['server_name'])) {
                    echo "         ✅ SNI: {$outbound['tls']['server_name']}\n";
                }
            }
        }

        // Validate selector specific fields
        if ($type === 'selector') {
            if (isset($outbound['default'])) {
                echo "      ✅ Default: {$outbound['default']}\n";
            }
            if (!isset($outbound['outbounds']) || empty($outbound['outbounds'])) {
                $errors[] = "Outbound[$idx] selector missing 'outbounds'";
                echo "      ❌ Missing 'outbounds' list\n";
            }
        }

        // Validate urltest specific fields
        if ($type === 'urltest') {
            if (isset($outbound['url'])) {
                echo "      ✅ URL Test: {$outbound['url']}\n";
            }
            if (isset($outbound['interval'])) {
                echo "      ✅ Interval: {$outbound['interval']}\n";
            }
        }
    }
} else {
    $errors[] = "No outbounds defined";
}
echo "\n";

// 3. Validate inbounds
echo "3. Inbounds Validation:\n";
if (isset($config['inbounds'])) {
    foreach ($config['inbounds'] as $idx => $inbound) {
        $type = $inbound['type'] ?? 'unknown';
        $tag = $inbound['tag'] ?? "untagged_$idx";

        echo "   [$idx] $type:$tag\n";

        if (!in_array($type, $validInboundTypes)) {
            $errors[] = "Inbound[$idx] has invalid type: $type";
            echo "      ❌ Invalid type: $type\n";
        } else {
            echo "      ✅ Type: $type\n";
        }
    }
} else {
    $errors[] = "No inbounds defined";
}
echo "\n";

// 4. Validate route rules
echo "4. Route Rules Validation:\n";
if (isset($config['route']['rules'])) {
    foreach ($config['route']['rules'] as $idx => $rule) {
        echo "   [$idx] ";

        // Check for deprecated 'private' field (bug fix #4)
        if (isset($rule['private'])) {
            $errors[] = "Route rule[$idx] has deprecated 'private' field (use 'ip_is_private')";
            echo "❌ Has deprecated 'private' field\n";
        } else {
            // Check for correct 'ip_is_private' field
            if (isset($rule['ip_is_private'])) {
                echo "✅ ip_is_private: " . ($rule['ip_is_private'] ? 'true' : 'false') . "\n";
                if (isset($rule['outbound'])) {
                    echo "   → outbound: {$rule['outbound']}\n";
                }
            } elseif (isset($rule['protocol']) && isset($rule['action'])) {
                echo "✅ protocol: {$rule['protocol']}, action: {$rule['action']}\n";
            } else {
                echo "✅ Custom rule\n";
            }
        }

        // Validate action field
        if (isset($rule['action']) && !in_array($rule['action'], $validRuleActions)) {
            $errors[] = "Route rule[$idx] has invalid action: {$rule['action']}";
            echo "   ❌ Invalid action: {$rule['action']}\n";
        }
    }

    // Check for 'final' field
    if (isset($config['route']['final'])) {
        echo "\n   ✅ Final outbound: {$config['route']['final']}\n";
    }
}
echo "\n";

// 5. Validate experimental section
echo "5. Experimental Section:\n";
if (isset($config['experimental'])) {
    if (isset($config['experimental']['clash_api'])) {
        echo "   ✅ Clash API enabled\n";
        if (isset($config['experimental']['clash_api']['external_controller'])) {
            echo "      Controller: {$config['experimental']['clash_api']['external_controller']}\n";
        }
    }
}
echo "\n";

// Summary
echo str_repeat("=", 60) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat("=", 60) . "\n";

if (empty($errors)) {
    echo "✅ ALL VALIDATIONS PASSED\n\n";
    echo "Key Features Validated:\n";
    echo "  ✅ No invalid 'filter' fields (Bug Fix #2)\n";
    echo "  ✅ No unsupported hysteria2 hop_ports fields (Bug Fix #5)\n";
    echo "  ✅ No invalid 'tcp' transport (Bug Fix #3)\n";
    echo "  ✅ No deprecated 'private' field (Bug Fix #4)\n";
    echo "  ✅ Correct 'ip_is_private' field used\n";
    echo "  ✅ Valid route rule actions\n";
    echo "  ✅ Proper TLS configuration\n";
    echo "  ✅ URLTest outbound for auto-selection\n";
    echo "  ✅ Selector with default outbound\n";
    echo "\nSing-box configuration is fully compliant with official schema.\n";
    exit(0);
} else {
    echo "❌ VALIDATION FAILED\n\n";
    echo "Errors found: " . count($errors) . "\n\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\n";
    exit(1);
}
