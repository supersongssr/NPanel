<?php
/**
 * Test actual subscription generation
 *
 * Run with: php tests/test_subscription_generation.php
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

echo "=== Testing Subscription Generation ===\n\n";

use App\Http\Models\UserSubscribe;
use App\Http\Models\SsNode;

// Get a subscription
$subscribe = UserSubscribe::query()->where('code', 'SsXa1')->first();

if (!$subscribe) {
    echo "❌ Subscription not found\n";
    exit(1);
}

echo "Subscription found:\n";
echo "  Code: {$subscribe->code}\n";
echo "  User ID: {$subscribe->user_id}\n";
echo "  Status: {$subscribe->status}\n\n";

// Check available nodes
$nodeCount = SsNode::query()
    ->where('status', 1)
    ->where('is_subscribe', 1)
    ->count();

echo "Available subscribe nodes: {$nodeCount}\n\n";

if ($nodeCount === 0) {
    echo "⚠️  No nodes available - will generate empty YAML config\n\n";
}

// Simulate calling the controller's generateClashConfig
// We need to include the controller file
require_once __DIR__ . '/../app/Http/Controllers/SubscribeController.php';

$controller = new \App\Http\Controllers\SubscribeController();
$reflection = new ReflectionClass($controller);

// Make the private method accessible for testing
$method = $reflection->getMethod('generateDirectSubscribe');
$method->setAccessible(true);

// Get user first
use App\Http\Models\User;
$user = User::query()->where('id', $subscribe->user_id)->first();

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "User found:\n";
echo "  ID: {$user->id}\n";
echo "  Username: {$user->username}\n";
echo "  Level: {$user->level}\n";
echo "  Node Group: {$user->node_group}\n\n";

// Generate Clash config
$nodes = SsNode::query()
    ->where('status', 1)
    ->where('is_subscribe', 1)
    ->where('node_group', $user->node_group)
    ->where('level', '<=', $user->level)
    ->orderBy('level', 'desc')
    ->orderBy('traffic_left_daily', 'desc')
    ->get();

echo "Nodes accessible to user: {$nodes->count()}\n\n";

// Use reflection to call private method
$method = new ReflectionMethod($controller, 'generateClashConfig');
$method->setAccessible(true);
$yaml = $method->invoke($controller, $nodes, $user);

if ($yaml === false || $yaml === null) {
    echo "❌ Failed to generate YAML\n";
    exit(1);
}

echo "Generated YAML (first 50 lines):\n";
echo str_repeat("=", 70) . "\n";
$lines = explode("\n", $yaml);
echo implode("\n", array_slice($lines, 0, min(50, count($lines))));
if (count($lines) > 50) {
    echo "\n... (" . (count($lines) - 50) . " more lines)";
}
echo "\n" . str_repeat("=", 70) . "\n\n";

// Save to file for inspection
file_put_contents('/tmp/actual_subscription.yaml', $yaml);
echo "Full YAML saved to /tmp/actual_subscription.yaml\n\n";

// Basic validation
$issues = [];
foreach ($lines as $i => $line) {
    $lineNum = $i + 1;

    // Check for tabs
    if (strpos($line, "\t") !== false) {
        $issues[] = "Line $lineNum: Contains tab";
    }

    // Check for invalid indentation
    if (strlen($line) > 0 && !ctype_space($line)) {
        $trimmed = ltrim($line);
        $indent = strlen($line) - strlen($trimmed);
        if ($indent % 2 !== 0) {
            $issues[] = "Line $lineNum: Invalid indentation: '$line'";
        }
    }

    // Check for key with no value
    if (preg_match('/^[\s]*[\w\-]+:\s*$/', $line) && !preg_match('/^[\s]*-/', $line)) {
        // This might be okay for nested objects, but check if next line is properly indented
        if (!isset($lines[$i + 1]) || trim($lines[$i + 1]) === '') {
            $issues[] = "Line $lineNum: Key with no value and no following content: '$line'";
        }
    }
}

if (empty($issues)) {
    echo "✅ YAML validation passed\n";
    exit(0);
} else {
    echo "❌ YAML validation issues:\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
    exit(1);
}
