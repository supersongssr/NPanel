<?php
/**
 * Test All Subscription Formats
 *
 * Tests Clash YAML and Sing-box JSON outputs
 * Run with: php tests/test_all_subscription_formats.php
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

echo "=== All Subscription Formats Test ===\n\n";

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

$controller = new SubscribeController();
$allPassed = true;

// Test 1: Clash YAML
echo "Test 1: Clash YAML Format\n";
echo str_repeat("-", 50) . "\n";
$method = new ReflectionMethod($controller, 'generateClashConfig');
$method->setAccessible(true);
$clashYaml = $method->invoke($controller, $nodes, $user);

if ($clashYaml) {
    // Save to file
    file_put_contents('/tmp/clash_subscription.yaml', $clashYaml);

    // Basic validation
    $lines = explode("\n", $clashYaml);
    $hasProxies = false;
    $hasProxyGroups = false;
    $hasRules = false;

    foreach ($lines as $line) {
        if (preg_match('/^proxies:/', $line)) $hasProxies = true;
        if (preg_match('/^proxy-groups:/', $line)) $hasProxyGroups = true;
        if (preg_match('/^rules:/', $line)) $hasRules = true;
    }

    if ($hasProxies && $hasProxyGroups && $hasRules) {
        echo "✅ Clash YAML structure valid\n";
        echo "   - Has proxies section\n";
        echo "   - Has proxy-groups section\n";
        echo "   - Has rules section\n";
        echo "   Saved to /tmp/clash_subscription.yaml\n";
    } else {
        echo "❌ Clash YAML missing required sections\n";
        $allPassed = false;
    }
} else {
    echo "❌ Failed to generate Clash YAML\n";
    $allPassed = false;
}

echo "\n";

// Test 2: Sing-box JSON
echo "Test 2: Sing-box JSON Format\n";
echo str_repeat("-", 50) . "\n";
$method = new ReflectionMethod($controller, 'generateSingboxConfig');
$method->setAccessible(true);
$singboxJson = $method->invoke($controller, $nodes, $user);

if ($singboxJson) {
    // Save to file
    file_put_contents('/tmp/singbox_subscription.json', $singboxJson);

    // Validate JSON
    $config = json_decode($singboxJson, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $hasOutbounds = isset($config['outbounds']);
        $hasInbounds = isset($config['inbounds']);
        $hasRoute = isset($config['route']);
        $hasFilter = false;

        // Check for invalid 'filter' field in selectors
        foreach ($config['outbounds'] ?? [] as $outbound) {
            if ($outbound['type'] === 'selector' && isset($outbound['filter'])) {
                $hasFilter = true;
                break;
            }
        }

        if ($hasOutbounds && $hasInbounds && $hasRoute && !$hasFilter) {
            echo "✅ Sing-box JSON structure valid\n";
            echo "   - Has outbounds section\n";
            echo "   - Has inbounds section\n";
            echo "   - Has route section\n";
            echo "   - No invalid 'filter' field\n";
            echo "   Saved to /tmp/singbox_subscription.json\n";
        } else {
            echo "❌ Sing-box JSON has issues:\n";
            if (!$hasOutbounds) echo "   - Missing outbounds\n";
            if (!$hasInbounds) echo "   - Missing inbounds\n";
            if (!$hasRoute) echo "   - Missing route\n";
            if ($hasFilter) echo "   - Has invalid 'filter' field\n";
            $allPassed = false;
        }
    } else {
        echo "❌ Sing-box JSON is invalid: " . json_last_error_msg() . "\n";
        $allPassed = false;
    }
} else {
    echo "❌ Failed to generate Sing-box JSON\n";
    $allPassed = false;
}

echo "\n";
echo str_repeat("=", 50) . "\n";

if ($allPassed) {
    echo "✅ ALL TESTS PASSED\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED\n";
    exit(1);
}
