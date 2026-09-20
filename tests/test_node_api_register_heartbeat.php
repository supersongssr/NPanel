<?php
/**
 * QA: register 占位心跳竞态收口 + API_TOKEN/DB 凭据收敛 config(nodeapi)
 *
 * 背景 (两条审查修复):
 *  1. register 全程不给主节点/复用中的既有 clone 刷新 heartbeat_at —— 同机重装
 *     (proxyInstall.sh Step0 见缓存 NODE_ID 即跳过 applyId 直接 register) 且已死超
 *     dns_expire_days 的节点, 从 register 落库到首次 status() 上报的窗口内仍是
 *     "心跳超期"死相, 可被并发装机的 applyId (候选选取/原子认领均无 status 闸门,
 *     orderBy id desc 优先挑最近死掉的高 id) 认领劫走, 两台机器争用同一节点行。
 *     修复: register 即刻刷新 main 与复用 clone 的心跳 (与 persistCleanNode /
 *     persistCloneIdentity 的占位心跳同语义)。
 *  2. 运行时 env() 在 config:cache 后恒为 null: /api/node/* 鉴权 (API_TOKEN) 会
 *     全线 401, config() 的 ssrpanel 段会把回退默认值 (127.0.0.1/root/空密码)
 *     错填进下发给旧插件的直连库凭据。修复: API_TOKEN 与 DB_* 收敛到
 *     config/nodeapi.php, 控制器/中间件统一 config() 读取。
 *
 * 验证:
 *  1. register 前把 main 心跳拨回 40 天前 (模拟死透后同机重装) → register 落库后
 *     main 心跳恢复新鲜 (不再命中 deadHeartbeatScope)。
 *  2. 二次 register (clone 全部走复用分支) 前把 main+clone 心跳全部拨回 40 天前 →
 *     复用分支同样刷新每个 clone 的心跳。
 *  3. config('nodeapi.api_token') 与 env('API_TOKEN') 同源且非空; listAll 端到端:
 *     错 token 401 / 对 token 200 (鉴权改读 config 后行为不变)。
 *  4. config('nodeapi.db_*') 与旧 env 口径一致 (DB_REMOTE_HOST 优先); 无 token 节点
 *     config() 下发的 ssrpanel.mysql.host 即该值 (不再直读运行时 env)。
 *
 * Run: podman exec -e APP_ENV=test php7-npanel php tests/test_node_api_register_heartbeat.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Models\SsNode;
use App\Http\Controllers\Api\NodeApiController;

if (env('APP_ENV') !== 'test') {
    fwrite(STDERR, "ABORT: APP_ENV must be 'test'\n");
    exit(2);
}

$passed = 0;
$failed = 0;

function assert_test($name, $condition, $detail = '')
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  PASS: {$name}\n";
    } else {
        $failed++;
        echo "  FAIL: {$name}" . ($detail ? " -- {$detail}" : "") . "\n";
    }
}

function mockRequest($method, $data = [], $headers = [])
{
    return Request::create('/api/node', $method, $data, [], [], $headers);
}

$controller = new NodeApiController();
$token = env('API_TOKEN');
$staleAt = date('Y-m-d H:i:s', strtotime('-40 days'));
$freshFloor = strtotime('-2 minutes');

// ============================================================
echo "=== Setup: applyId 分配节点 ===\n";
// ============================================================

$reqApply = mockRequest('POST', ['node_ip' => '10.98.98.98', 'token' => $token]);
$nodeId = json_decode($controller->applyId($reqApply)->getContent(), true)['node_id'];
echo "  node_id: {$nodeId}\n";

$regData = [
    'node_id'            => $nodeId,
    'token'              => $token,
    'v2_name'            => 'xhttp-hy2',
    'node_ip'            => '10.98.98.98',
    'node_country_code'  => 'HK',
    'node_city'          => 'HeartbeatQa',
    'node_level'         => 1,
    'node_group'         => 2,
    'node_bandwidth'     => 100,
    'node_traffic_limit' => 1000,
    'node_traffic_rate'  => 1.0,
    'node_rxtx'          => 'tx',
];

// ============================================================
echo "=== Test 1: 死透节点同机重装 → register 刷新 main 心跳 ===\n";
// ============================================================

// 模拟: 机器死超 dns_expire_days 后带缓存 NODE_ID 重装 (跳过 applyId 直接 register)
SsNode::where('id', $nodeId)->update(['heartbeat_at' => $staleAt]);
$before = SsNode::find($nodeId)->heartbeat_at;

$resp = $controller->register(mockRequest('POST', $regData));
$body = json_decode($resp->getContent(), true);
assert_test('register 成功', ($body['status'] ?? '') === 'success');

$after = SsNode::find($nodeId);
assert_test('register 落库后 main 心跳恢复新鲜 (占位心跳写入)',
    strtotime($after->heartbeat_at) >= $freshFloor,
    "before={$before} after={$after->heartbeat_at}");

// ============================================================
echo "=== Test 2: 二次 register (clone 复用分支) 同样刷新 clone 心跳 ===\n";
// ============================================================

$cloneIds = SsNode::where('is_clone', $nodeId)->pluck('id')->all();
echo "  clones: " . implode(',', $cloneIds) . "\n";
assert_test('首次 register 已裂变 clone', count($cloneIds) > 0);

// 模拟: 再次死透后重装 —— main 与全部既有 clone 心跳拨回 40 天前
SsNode::where('id', $nodeId)->update(['heartbeat_at' => $staleAt]);
if ($cloneIds) {
    SsNode::whereIn('id', $cloneIds)->update(['heartbeat_at' => $staleAt]);
}

$resp2 = $controller->register(mockRequest('POST', $regData));
$body2 = json_decode($resp2->getContent(), true);
assert_test('二次 register 成功', ($body2['status'] ?? '') === 'success');

$cloneIds2 = SsNode::where('is_clone', $nodeId)->pluck('id')->all();
assert_test('clone 全部复用 (无新增)', count($cloneIds2) === count($cloneIds),
    "before=" . count($cloneIds) . " after=" . count($cloneIds2));

$allFresh = true;
$staleList = [];
foreach (SsNode::where('is_clone', $nodeId)->get() as $c) {
    if (strtotime($c->heartbeat_at) < $freshFloor) {
        $allFresh = false;
        $staleList[] = $c->id . ':' . $c->heartbeat_at;
    }
}
assert_test('复用分支刷新每个 clone 的心跳', $allFresh, implode(' ', $staleList));

$main2 = SsNode::find($nodeId);
assert_test('二次 register 后 main 心跳仍新鲜',
    strtotime($main2->heartbeat_at) >= $freshFloor, "after={$main2->heartbeat_at}");

// ============================================================
echo "=== Test 3: API_TOKEN 收敛 config — 鉴权行为不变 ===\n";
// ============================================================

assert_test('config(nodeapi.api_token) 与 env(API_TOKEN) 同源且非空',
    config('nodeapi.api_token') === $token && $token !== '',
    "config=" . var_export(config('nodeapi.api_token'), true));

$resp401 = $controller->listAll(mockRequest('GET', [], ['HTTP_AUTHORIZATION' => 'Bearer wrong-token']));
assert_test('listAll 错 token → 401', $resp401->status() === 401);

$resp200 = $controller->listAll(mockRequest('GET', ['token' => $token]));
assert_test('listAll 对 token → 200 (经 config 读取)', $resp200->status() === 200);

// ============================================================
echo "=== Test 4: ssrpanel DB 凭据经 config 下发 (不再直读运行时 env) ===\n";
// ============================================================

$expectHost = env('DB_REMOTE_HOST', env('DB_HOST', '127.0.0.1'));
assert_test('config(nodeapi.db_host) 与旧 env 口径一致 (DB_REMOTE_HOST 优先)',
    config('nodeapi.db_host') === $expectHost, "config=" . var_export(config('nodeapi.db_host'), true));

// 无 token 的裸节点 (模拟手工清空/迁移残留): panelApi 被裁, ssrpanel 段保留 →
// 其 mysql.host 应来自 config(nodeapi.db_host), 而非运行时 env 直读
$bare = new SsNode();
$bare->name = 'QA-Bare-NoToken';
$bare->v2_name = 'xhttp-hy2';
$bare->server = 'qa-bare.example.com';
$bare->ip = '10.97.97.97';
$bare->is_clone = 0;
$bare->status = 1;
$bare->level = 1;
$bare->heartbeat_at = date('Y-m-d H:i:s');
$bare->api_token = null;
$bare->save();

$respCfg = $controller->config(mockRequest('POST', ['node_id' => $bare->id]));
$cfg = json_decode($respCfg->getContent(), true);
assert_test('无 token 节点 config() 保留 ssrpanel 段',
    isset($cfg['ssrpanel']['mysql']['host']),
    'ssrpanel.mysql missing');
if (isset($cfg['ssrpanel']['mysql']['host'])) {
    assert_test('ssrpanel.mysql.host 来自 config(nodeapi.db_host)',
        $cfg['ssrpanel']['mysql']['host'] === $expectHost,
        "got={$cfg['ssrpanel']['mysql']['host']} expect={$expectHost}");
    assert_test('ssrpanel.mysql.dbname 与 config 一致',
        $cfg['ssrpanel']['mysql']['dbname'] === config('nodeapi.db_name'),
        "got={$cfg['ssrpanel']['mysql']['dbname']}");
}

// ============================================================
echo "=== Cleanup ===\n";
// ============================================================

$delIds = array_merge([$nodeId], $cloneIds, [$bare->id]);
SsNode::whereIn('id', $delIds)->delete();
echo "  已删除 node_id=" . implode(',', $delIds) . "\n";

echo "\n========== RESULT: {$passed} passed, {$failed} failed ==========\n";
exit($failed > 0 ? 1 : 0);
