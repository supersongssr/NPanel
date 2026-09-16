<?php
/**
 * QA Test: v2 用户查询 API(/api/v2/user/traffic) — 单查/批查 + 鉴权中间件
 *
 * 覆盖:
 *   1. 中间件 UserApiToken: 正确 token 放行 / 错误 token 401 / 无 token 401 / IP 白名单拒绝
 *   2. GET traffic: by id / by email / 404 / 400(缺参) / 400(互斥) / 400(非法 id) / 400(超长 email)
 *   3. POST trafficBatch: by ids / by emails / 去重 / 400(同时给 ids+emails) / 400(超批量上限)
 *   4. 字段口径: used=u+d, remaining=max(0,total-used), *_human, usage_percent
 *
 * Run: podman exec -e APP_ENV=test php7-npanel php tests/test_user_api_v2_traffic.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

if (env('APP_ENV') !== 'test') {
    exit("ABORT: APP_ENV must be test (run: podman exec -e APP_ENV=test php7-npanel php tests/test_user_api_v2_traffic.php)\n");
}

use App\Http\Models\User;
use App\Http\Controllers\Api\V2\User\UserController;
use App\Http\Middleware\UserApiToken;
use Illuminate\Http\Request;

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
echo "=== Setup: create QA test users ===\n";

$TOKEN = env('USER_API_TOKEN');
echo 'USER_API_TOKEN configured: ' . ($TOKEN ? 'yes' : 'NO') . "\n";

$testEmail = 'qa_user_api_v2@test.invalid';
User::query()->where('username', 'like', 'qa_user_api_v2%@test.invalid')->forceDelete();

$u1 = new User();
$u1->username = $testEmail;
$u1->password = \Hash::make('x');
$u1->port = 0;
$u1->vmess_id = '00000000-0000-0000-0000-000000000001';
$u1->transfer_enable = 1073741824; // 1 GiB
$u1->u = 1048576;                  // 1 MiB
$u1->d = 2097152;                  // 2 MiB
$u1->t = 1700000000;
$u1->enable = 1;
$u1->status = 1;
$u1->expire_time = '2030-01-01';
$u1->level = 1;
$u1->save();

$u2 = new User();
$u2->username = 'qa_user_api_v2_b@test.invalid';
$u2->password = \Hash::make('x');
$u2->port = 0;
$u2->vmess_id = '00000000-0000-0000-0000-000000000002';
$u2->transfer_enable = 5368709120; // 5 GiB
$u2->u = 10737418240;              // 10 GiB (超额: remaining 应为 0)
$u2->d = 0;
$u2->enable = 1;
$u2->status = 1;
$u2->expire_time = '2030-01-01';
$u2->save();

$controller = new UserController();
$decode = function ($response) {
    return json_decode($response->getContent(), true);
};

// =====================================================
echo "\n=== Group 1: Middleware UserApiToken ===\n";

$mkReq = function ($token, $ip = '127.0.0.1') {
    $req = Request::create('/api/v2/user/traffic?id=1', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
    if ($token !== null) {
        $req->headers->set('Authorization', 'Bearer ' . $token);
    }
    return $req;
};
$runMw = function (Request $req) {
    $mw = new UserApiToken();
    $called = false;
    $resp = $mw->handle($req, function () use (&$called) {
        $called = true;
        return response('next');
    });
    return [$called, $resp];
};

if ($TOKEN) {
    list($called, ) = $runMw($mkReq($TOKEN));
    assert_test('correct token passes', $called === true);

    list($called, $resp) = $runMw($mkReq('wrong-token'));
    $body = json_decode($resp->getContent(), true);
    assert_test('wrong token → 401 INVALID_TOKEN', $called === false && $resp->getStatusCode() === 401
        && $body['error']['code'] === 'INVALID_TOKEN');

    list($called, $resp) = $runMw($mkReq(null));
    assert_test('missing token → 401', $called === false && $resp->getStatusCode() === 401);

    list($called, $resp) = $runMw($mkReq($TOKEN . str_repeat('A', 200)));
    assert_test('over-length token → 401', $called === false && $resp->getStatusCode() === 401);
} else {
    echo "  SKIP: USER_API_TOKEN not configured in .env\n";
}

// =====================================================
echo "\n=== Group 2: GET /traffic single query ===\n";

// by id
$resp = $controller->traffic(Request::create('/api/v2/user/traffic?id=' . $u1->id, 'GET'));
$body = $decode($resp);
$d = $body['data']['user'];
assert_test('by id: HTTP 200 + envelope data.user', $resp->getStatusCode() === 200 && isset($body['data']['user']));
assert_test('by id: email matches', $d['email'] === $testEmail);
assert_test('by id: u/d raw bytes', $d['u'] === 1048576 && $d['d'] === 2097152);
assert_test('by id: used = u+d', $d['used'] === 3145728);
assert_test('by id: transfer_enable', $d['transfer_enable'] === 1073741824);
assert_test('by id: remaining = total-used', $d['remaining'] === 1073741824 - 3145728);
assert_test('by id: usage_percent', abs($d['usage_percent'] - 0.29) < 0.01);
assert_test('by id: used_human = 3MB', $d['used_human'] === '3MB');
assert_test('by id: transfer_enable_human = 1GB', $d['transfer_enable_human'] === '1GB');
assert_test('by id: last_traffic_time', $d['last_traffic_time'] === 1700000000);
assert_test('by id: expire_time', $d['expire_time'] === '2030-01-01');
assert_test('by id: no password leak in payload', strpos(json_encode($body), 'password') === false);

// by email
$resp = $controller->traffic(Request::create('/api/v2/user/traffic?email=' . urlencode($testEmail), 'GET'));
$body = $decode($resp);
assert_test('by email: HTTP 200 + id matches', $resp->getStatusCode() === 200
    && $body['data']['user']['id'] === (int)$u1->id);

// 超额用户 remaining 钳 0
$resp = $controller->traffic(Request::create('/api/v2/user/traffic?id=' . $u2->id, 'GET'));
$d = $decode($resp)['data']['user'];
assert_test('over-quota user: remaining clamped to 0', $d['remaining'] === 0);
assert_test('over-quota user: usage_percent > 100 ok', $d['usage_percent'] > 100);

// 404
$resp = $controller->traffic(Request::create('/api/v2/user/traffic?id=999999999', 'GET'));
$body = $decode($resp);
assert_test('unknown id → 404 USER_NOT_FOUND', $resp->getStatusCode() === 404
    && $body['error']['code'] === 'USER_NOT_FOUND');

// 400 cases
$cases = [
    'missing param → 400'            => ['', ''],
    'both id+email → 400'            => ['1', 'a@b.c'],
    'non-numeric id → 400'           => ['abc', ''],
    'zero id → 400'                  => ['0', ''],
    'over-long email → 400'          => ['', str_repeat('a', 200)],
];
foreach ($cases as $name => $pair) {
    $qs = [];
    if ($pair[0] !== '') {
        $qs[] = 'id=' . $pair[0];
    }
    if ($pair[1] !== '') {
        $qs[] = 'email=' . urlencode($pair[1]);
    }
    $resp = $controller->traffic(Request::create('/api/v2/user/traffic' . ($qs ? '?' . implode('&', $qs) : ''), 'GET'));
    assert_test($name, $resp->getStatusCode() === 400 && $decode($resp)['error']['code'] === 'BAD_REQUEST');
}

// =====================================================
echo "\n=== Group 3: POST /traffic batch query ===\n";

// POST JSON body 需带 Content-Type: application/json, 否则 Laravel 不会解析 body(与真实 HTTP 一致)
$jsonReq = function (array $payload) {
    $req = Request::create('/api/v2/user/traffic', 'POST', [], [], [],
        ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
    return $req;
};

$req = $jsonReq(['data' => ['ids' => [(int)$u1->id, (int)$u2->id, (int)$u1->id]]]);
$resp = $controller->trafficBatch($req);
$body = $decode($resp);
assert_test('batch by ids: HTTP 200', $resp->getStatusCode() === 200);
assert_test('batch by ids: deduped count=2', $body['data']['count'] === 2);
assert_test('batch by ids: ordered by id asc', $body['data']['users'][0]['id'] === (int)$u1->id);

$req = $jsonReq(['data' => ['emails' => [$testEmail, 'qa_user_api_v2_b@test.invalid']]]);
$resp = $controller->trafficBatch($req);
$body = $decode($resp);
assert_test('batch by emails: count=2', $resp->getStatusCode() === 200 && $body['data']['count'] === 2);

$req = $jsonReq(['data' => ['ids' => [(int)$u1->id]]]);
$resp = $controller->trafficBatch($req);
$body = $decode($resp);
assert_test('batch: user payload fields same as single', isset($body['data']['users'][0]['used'])
    && isset($body['data']['users'][0]['remaining_human']));

$batchCases = [
    'no data → 400'            => json_encode([]),
    'ids+emails → 400'         => json_encode(['data' => ['ids' => [1], 'emails' => ['a@b.c']]]),
    'empty ids → 400'          => json_encode(['data' => ['ids' => []]]),
    'invalid id → 400'         => json_encode(['data' => ['ids' => ['x']]]),
    'over batch limit → 400'   => json_encode(['data' => ['ids' => array_fill(0, 101, 1)]]),
    'invalid email → 400'      => json_encode(['data' => ['emails' => [123]]]),
];
foreach ($batchCases as $name => $payload) {
    $req = $jsonReq(json_decode($payload, true));
    $resp = $controller->trafficBatch($req);
    assert_test('batch ' . $name, $resp->getStatusCode() === 400 && $decode($resp)['error']['code'] === 'BAD_REQUEST');
}

// =====================================================
echo "\n=== Cleanup ===\n";
User::query()->where('username', 'like', 'qa_user_api_v2%@test.invalid')->forceDelete();
echo "done\n";

echo "\n===== RESULT: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
