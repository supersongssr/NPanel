<?php
/**
 * QA Test: applyId 死节点回收竞态守卫 — 原子认领 + 占位心跳
 *
 * 覆盖 (对应 NodeApiController::applyId 守卫逻辑):
 *   1. 回收: 心跳超期(status=0)死节点被 applyId 复用同一 ID, 且认领(条件 UPDATE
 *      占位心跳)在 CF DNS 清理之前即已落库 —— 回收后行内 heartbeat_at ≈ now;
 *      身份经 resetToDefaults 全量重置 (traffic_used=0 / is_clone=0 / ip=上报值).
 *   2. 保护: 占位心跳使该节点不再命中死节点判定 —— 再次 applyId 不会复用同一 ID
 *      (防 autoReclaimDeadNodes 夜间误删 / register 回收池误捡的同一机制).
 *   3. 布局: fixture 无 DNS 记录, DnsSyncer cleanup 早退, 不触发真实 CF API.
 *
 * 不覆盖: 认领影响 0 行的降级路径需在 select→claim 间注入并发写, 单进程测试无法
 * 确定性构造, 由代码审查保证 (同源条件条件 UPDATE, 见控制器内注释).
 *
 * Run: podman exec -e APP_ENV=test php7-npanel php tests/test_node_apply_id_guard.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

if (env('APP_ENV') !== 'test') {
    exit("ABORT: APP_ENV must be test (run: podman exec -e APP_ENV=test php7-npanel php tests/test_node_apply_id_guard.php)\n");
}

use App\Http\Controllers\Api\NodeApiController;
use App\Http\Models\SsNode;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assert_test($name, $condition, $message = '')
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  PASS: {$name}\n";
    } else {
        $failed++;
        echo "  FAIL: {$name}" . ($message ? " — {$message}" : '') . "\n";
    }
}

// =====================================================
echo "=== Setup: cleanup QA leftovers + create dead-node fixture ===\n";

$MARKER_IP = '10.255.255.254';   // TEST-NET-3, 仅测试标记用
SsNode::query()->whereIn('name', ['qa_apply_id_guard', "New Node {$MARKER_IP}"])->delete();

// fixture: status=0 + 心跳 40 天前 (> 默认 dns_expire_days=30) → 命中死节点判定;
// 自增 id 保证为全表最大 id → applyId 的 orderBy(id desc)->first() 必先命中它,
// 测试不受库里其它死节点影响.
$fixture = new SsNode();
$fixture->name = 'qa_apply_id_guard';
$fixture->ip = '192.0.2.1';
$fixture->status = 0;
$fixture->is_clone = 0;
$fixture->traffic_used = 12345;
$fixture->heartbeat_at = date('Y-m-d H:i:s', strtotime('-40 days'));
$fixture->save();
$fixtureId = $fixture->id;
echo "fixture node#{$fixtureId} (heartbeat=-40d)\n";

$controller = new NodeApiController();

// =====================================================
echo "\n=== Group 1: applyId 回收死节点 + 原子认领占位心跳 ===\n";

$req = Illuminate\Http\Request::create('/api/node/apply_id', 'POST', [
    'node_ip' => $MARKER_IP,
    'node_ipv6' => '',
]);
$resp = $controller->applyId($req);
$body = json_decode($resp->getContent(), true);
assert_test('recycle: HTTP 200 + node_id', $resp->getStatusCode() === 200 && isset($body['node_id']));
assert_test('recycle: 复用 fixture 同一 ID', $body['node_id'] === $fixtureId,
    'got ' . var_export($body['node_id'] ?? null, true) . ", want {$fixtureId}");

$row = SsNode::find($fixtureId);
assert_test('claim: 占位心跳已落库 (≈now, 非 fixture 的 -40d)',
    $row !== null && strtotime($row->heartbeat_at) > time() - 300,
    'heartbeat_at=' . var_export($row ? $row->heartbeat_at : null, true));
assert_test('reset: traffic_used 清零', (int)$row->traffic_used === 0);
assert_test('reset: ip=上报值 / status=0 / is_clone=0 / name 规范',
    $row->ip === $MARKER_IP && (int)$row->status === 0 && (int)$row->is_clone === 0
    && $row->name === "New Node {$MARKER_IP}");

// =====================================================
echo "\n=== Group 2: 占位心跳保护 — 不再命中死节点判定 ===\n";

$cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
$stillDead = DB::table('ss_node')->where('id', $fixtureId)
    ->where(function ($q) use ($cutoff) {
        $q->where('heartbeat_at', '<', $cutoff)
          ->orWhere(function ($q2) use ($cutoff) {
              $q2->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
          });
    })
    ->exists();
assert_test('guard: 占位心跳后不命中死节点 scope', $stillDead === false);

$maxIdBefore = (int) SsNode::max('id');
$req2 = Illuminate\Http\Request::create('/api/node/apply_id', 'POST', [
    'node_ip' => '10.255.255.253',
    'node_ipv6' => '',
]);
$resp2 = $controller->applyId($req2);
$body2 = json_decode($resp2->getContent(), true);
$id2 = isset($body2['node_id']) ? (int)$body2['node_id'] : -1;
assert_test('guard: 二次 applyId 不复用同一 ID', $id2 !== (int)$fixtureId,
    "got {$id2}, want != {$fixtureId}");
// 二次调用若为全新自增行 (> maxIdBefore) 则为本次测试产物, 记录待清理;
// 若复用了库里其它死节点 (非本测试产物) 则保留不动.
$id2IsNew = $id2 > $maxIdBefore;

// =====================================================
echo "\n=== Cleanup ===\n";
SsNode::query()->where('id', $fixtureId)->delete();
if ($id2IsNew && $id2 > 0) {
    SsNode::query()->where('id', $id2)->delete();
    echo "removed fixture #{$fixtureId} + new #{$id2}\n";
} else {
    echo "removed fixture #{$fixtureId}\n";
}

echo "\n===== RESULT: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
