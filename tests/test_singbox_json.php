<?php
/**
 * Test Sing-box JSON Output
 *
 * Run with: php tests/test_singbox_json.php
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

echo "=== Sing-box JSON Output Test ===\n\n";

use App\Http\Models\UserSubscribe;
use App\Http\Models\SsNode;
use App\Http\Models\User;
use App\Http\Controllers\SubscribeController;

// Get subscription
$subscribe = UserSubscribe::query()->where('code', 'SsXa1')->first();
if (!$subscribe) {
    echo "❌ Subscription not found\n";
    exit(1);
}

// Get user
$user = User::query()->where('id', $subscribe->user_id)->first();
if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

// Get nodes
$nodes = SsNode::query()
    ->where('status', 1)
    ->where('is_subscribe', 1)
    ->where('node_group', $user->node_group)
    ->where('level', '<=', $user->level)
    ->orderBy('level', 'desc')
    ->orderBy('traffic_left_daily', 'desc')
    ->get();

echo "Nodes: {$nodes->count()}\n\n";

// Generate Sing-box config
$controller = new SubscribeController();
$method = new ReflectionMethod($controller, 'generateSingboxConfig');
$method->setAccessible(true);
$json = $method->invoke($controller, $nodes, $user);

if ($json === false || $json === null) {
    echo "❌ Failed to generate JSON\n";
    exit(1);
}

echo "Generated JSON:\n";
echo str_repeat("=", 70) . "\n";
echo $json . "\n";
echo str_repeat("=", 70) . "\n\n";

// Save to file
file_put_contents('/tmp/singbox_config.json', $json);
echo "Saved to /tmp/singbox_config.json\n\n";

// Validate JSON
$config = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "✅ JSON is valid\n\n";

// Check for invalid fields
$errors = [];

// Check outbounds
if (isset($config['outbounds'])) {
    foreach ($config['outbounds'] as $idx => $outbound) {
        if ($outbound['type'] === 'selector') {
            if (isset($outbound['filter'])) {
                $errors[] = "Outbound[$idx]: selector has invalid 'filter' field";
            }
            echo "✅ Selector outbound found (tag: {$outbound['tag']})\n";
            echo "   - outbounds: " . implode(', ', $outbound['outbounds']) . "\n";
            if (isset($outbound['default'])) {
                echo "   - default: {$outbound['default']}\n";
            }
        }
    }
}

if (empty($errors)) {
    echo "\n✅ Sing-box configuration validation passed\n";
    exit(0);
} else {
    echo "\n❌ Validation errors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
    exit(1);
}
