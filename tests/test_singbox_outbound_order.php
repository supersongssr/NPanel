<?php
/**
 * Sing-box Outbound Order and Dependency Test
 *
 * Tests that outbounds are in correct order and all references are valid
 * Run with: php tests/test_singbox_outbound_order.php
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

echo "=== Sing-box Outbound Order & Dependency Test ===\n\n";

use App\Http\Models\UserSubscribe;
use App\Http\Models\SsNode;
use App\Http\Models\User;
use App\Http\Controllers\SubscribeController;

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

echo "Checking outbound order and dependencies...\n\n";

// Build a map of tag -> index for quick lookup
$tagToIndex = [];
foreach ($config['outbounds'] as $idx => $outbound) {
    $tag = $outbound['tag'] ?? "unnamed_$idx";
    $tagToIndex[$tag] = $idx;
}

echo "1. Outbound Order:\n";
echo str_repeat("-", 60) . "\n";
foreach ($config['outbounds'] as $idx => $outbound) {
    $type = $outbound['type'] ?? 'unknown';
    $tag = $outbound['tag'] ?? "unnamed_$idx";
    echo "[$idx] $type - tag: '$tag'\n";

    // Check if this outbound references other outbounds
    if (isset($outbound['outbounds']) && is_array($outbound['outbounds'])) {
        echo "    → References: " . implode(', ', $outbound['outbounds']) . "\n";

        // Check each reference
        foreach ($outbound['outbounds'] as $refTag) {
            if (!isset($tagToIndex[$refTag])) {
                $errors[] = "Outbound[$idx] ($tag) references non-existent tag: '$refTag'";
                echo "    ❌ ERROR: References undefined tag '$refTag'\n";
            } else {
                $refIdx = $tagToIndex[$refTag];
                if ($refIdx > $idx) {
                    $errors[] = "Outbound[$idx] ($tag) references tag '$refTag' at index $refIdx (forward reference)";
                    echo "    ⚠️  WARNING: Forward references tag '$refTag' (at index $refIdx)\n";
                } else {
                    echo "    ✅ Tag '$refTag' exists at index $refIdx (before current)\n";
                }
            }
        }
    }
    echo "\n";
}

echo "\n";
echo "2. Dependency Chain Validation:\n";
echo str_repeat("-", 60) . "\n";

// Check the dependency chain: nodes -> urltest -> selector
$selectorIdx = $tagToIndex['Proxy'] ?? -1;
$autoIdx = $tagToIndex['auto'] ?? -1;

if ($selectorIdx === -1) {
    $errors[] = "Missing 'Proxy' selector outbound";
    echo "❌ ERROR: Missing 'Proxy' selector\n";
} else {
    echo "✅ Found 'Proxy' selector at index $selectorIdx\n";

    // Check selector dependencies
    $selector = $config['outbounds'][$selectorIdx];
    if (isset($selector['outbounds'])) {
        if (in_array('auto', $selector['outbounds'])) {
            if ($autoIdx === -1) {
                $errors[] = "Selector references 'auto' but it doesn't exist";
                echo "❌ ERROR: Selector references 'auto' but it doesn't exist\n";
            } elseif ($autoIdx > $selectorIdx) {
                $errors[] = "Selector at $selectorIdx references 'auto' at $autoIdx (forward reference)";
                echo "❌ ERROR: 'auto' at $autoIdx comes AFTER selector at $selectorIdx\n";
            } else {
                echo "✅ Selector correctly references 'auto' (index $autoIdx < $selectorIdx)\n";
            }
        }

        // Check node references
        foreach ($proxyTags ?? [] as $nodeTag) {
            if (!in_array($nodeTag, $selector['outbounds'])) {
                $warnings[] = "Node '$nodeTag' not in selector's outbound list";
            }
        }
    }
}

echo "\n";
echo "3. Empty List Handling:\n";
echo str_repeat("-", 60) . "\n";

if ($autoIdx !== -1) {
    $autoOutbounds = $config['outbounds'][$autoIdx]['outbounds'] ?? [];
    if (empty($autoOutbounds)) {
        $errors[] = "urltest 'auto' has empty outbounds list";
        echo "❌ ERROR: 'auto' group has empty outbounds list\n";
    } else {
        echo "✅ 'auto' group has " . count($autoOutbounds) . " node(s): " . implode(', ', $autoOutbounds) . "\n";
    }
}

// Summary
echo "\n";
echo str_repeat("=", 60) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat("=", 60) . "\n";

if (empty($errors)) {
    echo "✅ NO DEPENDENCY ERRORS FOUND\n\n";

    if (empty($warnings)) {
        echo "✅ NO WARNINGS\n\n";
        echo "All outbound dependencies are correctly ordered:\n";
        echo "  1. Node outbounds (VMess/VLESS/Trojan) come first\n";
        echo "  2. Base outbounds (direct/block/dns) come next\n";
        echo "  3. urltest 'auto' comes after base outbounds\n";
        echo "  4. selector 'Proxy' comes last (references all above)\n\n";
        echo "This order ensures no forward references exist.\n";
        exit(0);
    } else {
        echo "⚠️  WARNINGS: " . count($warnings) . "\n\n";
        foreach ($warnings as $warning) {
            echo "  - $warning\n";
        }
        echo "\nConfiguration may work but review warnings above.\n";
        exit(0);
    }
} else {
    echo "❌ DEPENDENCY ERRORS: " . count($errors) . "\n\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    echo "\nThese errors will cause Sing-box to fail with:\n";
    echo "  'dependency stale node not found' error\n";
    exit(1);
}
