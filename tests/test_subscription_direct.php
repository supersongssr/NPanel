<?php
/**
 * Test Subscription Generation Directly
 */

require_once __DIR__ . '/../vendor/autoload.php';

putenv('APP_ENV=dev');
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Direct Subscription Test ===\n\n";

use App\Http\Models\User;
use App\Http\Models\UserSubscribe;
use App\Http\Models\SsNode;

$code = 'ZecXb';

// 查找订阅
$subscribe = UserSubscribe::query()
    ->with('user')
    ->where('status', 1)
    ->where('code', $code)
    ->first();

if (!$subscribe) {
    echo "❌ Subscribe not found for code: $code\n";
    exit(1);
}

echo "✅ Subscribe found:\n";
echo "   User ID: {$subscribe->user_id}\n";
echo "   Status: {$subscribe->status}\n";
echo "   Code: {$subscribe->code}\n";

// 查找用户
$user = User::query()
    ->where('status', 1)
    ->where('enable', 1)
    ->where('id', $subscribe->user_id)
    ->first();

if (!$user) {
    echo "❌ User not found or disabled\n";
    exit(1);
}

echo "\n✅ User found:\n";
echo "   ID: {$user->id}\n";
echo "   Username: {$user->username}\n";
echo "   Level: {$user->level}\n";
echo "   Node Group: {$user->node_group}\n";

// 获取节点
$nodeList = SsNode::query()
    ->where('status', 1)
    ->where('is_subscribe', 1)
    ->where('node_group', $user->node_group)
    ->where('level', '<=', $user->level)
    ->orderBy('level', 'desc')
    ->orderBy('traffic_left_daily', 'desc')
    ->get();

echo "\n✅ Nodes found: " . $nodeList->count() . "\n";

if ($nodeList->isEmpty()) {
    echo "⚠️  No nodes available for this user\n";
}

// 生成 Clash 配置
try {
    $controller = new \App\Http\Controllers\SubscribeController();

    // 使用反射调用私有方法
    $method = new ReflectionMethod($controller, 'generateClashConfig');
    $method->setAccessible(true);

    $yaml = $method->invoke($controller, $nodeList, $user);

    echo "\n✅ YAML Generated successfully!\n";
    echo "   Length: " . strlen($yaml) . " bytes\n";
    echo "\n--- YAML Preview (first 50 lines) ---\n";
    echo implode("\n", array_slice(explode("\n", $yaml), 0, 50));
    echo "\n--- End Preview ---\n";

} catch (\Exception $e) {
    echo "\n❌ Error generating YAML:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n✅ Test completed successfully!\n";
