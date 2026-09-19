<?php
/**
 * QA Test: register clone 回收竞态收口 + clone 等级钳制 (审查修复验证)
 *
 * 背景 (复审发现的两个缺陷):
 *   1. [concurrency] register 从死节点池捡旧 id 当 clone 时无原子认领 —— CF 清理
 *      秒级窗口内行内仍是死状态, autoReclaimDeadNodes/管理员删除可命中, 之后
 *      save() UPDATE 0 行仍静默成功, 幽灵 id 流入 node_ids; 并发 register/applyId
 *      也会捡到同一行互相覆盖. 修复 = applyId 同款"原子认领 (条件 UPDATE 占位
 *      心跳) + 落库后回查存在性" (acquireCloneForSlot / persistCloneIdentity).
 *   2. [logic] node_level 由节点侧自由上报, 超过 5 时 rand(大, 小) 在 PHP 7.4 返回
 *      false 落库成等级 0 (高等级专属 clone 变全员可见). 修复 = 随机区间钳制到 [1,5].
 *
 * 覆盖:
 *   A. node_level=9 注册 → 所有 clone 等级恰为 5 (lo=hi=5, 旧代码会落 0),
 *      主节点 level 仍按上报值 9 原样保存.
 *   B. 池内最高 id 死节点被 register 认领为 clone: is_clone=mainId, status=1,
 *      占位心跳非 NULL 且 >= 调用时刻 (认领写入的心跳没有被落库清回 NULL),
 *      node_ids / clone_node_ids 均含该 id.
 *   C. (反射直测 acquireCloneForSlot) 候选 1 已被并发删除 → 认领 0 行自动换
 *      候选 2; 返回 clone 指向候选 2, 池游标前移 2.
 *
 * 注: 幽灵降级路径 (认领后落库前被管理员删行) 与 applyId 已上线收口结构完全
 * 同款 (save 落空 → exists 回查 → new SsNode 重落), 无单进程注入点, 不在单测覆盖.
 *
 * 隔离性: QA 高位 id (>= QA_BASE_ID), 不触碰既有行; dns_records 无记录 →
 * safeCleanupNodeDns 零 CF API 调用. finally 中清理全部测试行.
 *
 * Run: podman exec -e APP_ENV=test php7-npanel php /var/www/NPanel/tests/test_register_clone_claim_and_level.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Http\Controllers\Api\NodeApiController;
use App\Http\Models\SsNode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

if (env('APP_ENV') !== 'test') {
    fwrite(STDERR, "ABORT: APP_ENV must be 'test'\n");
    exit(2);
}

const QA_BASE_ID = 9900;

$passed = 0;
$failed = 0;
function assert_test($name, $cond, $detail = '')
{
    global $passed, $failed;
    if ($cond) {
        echo "  \033[32m✓\033[0m {$name}\n";
        $passed++;
    } else {
        echo "  \033[31m✗\033[0m {$name}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "========================================\n";
echo "  register clone 认领竞态 + 等级钳制测试\n";
echo "========================================\n\n";

// 与 register / autoReclaimDeadNodes 同源的 cutoff (dns_expire_days, 默认 30)
$sysConf = \App\Components\Helpers::systemConfig();
$days = isset($sysConf['dns_expire_days']) ? (int) $sysConf['dns_expire_days'] : 30;
if ($days < 1) {
    $days = 30;
}
$cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

$ctrl = new NodeApiController();
$mainId = null;
$qaIds = [];

try {
    // —— 前置清理: 保险起见清掉 QA 高位区残留 ——
    DB::table('ss_node')->where('id', '>=', QA_BASE_ID)->delete();

    // ============================================================
    echo "=== A + B: applyId 建主节点 → 造池内死节点 → register ===\n\n";
    // ============================================================

    // 主节点走 applyId 真实路径 (先建主, 再插死节点, 避免被 applyId 自己认领)
    $t0 = time();
    $resp = $ctrl->applyId(Request::create('/api/node/apply_id', 'POST', array(
        'node_ip'   => '203.0.113.77',
        'node_ipv6' => '2001:db8:77::1',
    )));
    $mainId = (int) $resp->getData(true)['node_id'];
    echo "  main node_id: {$mainId}\n";

    // 造一个池内最高 id 的死节点 (register 池 orderBy id desc → 首个 clone 槽位必命中)
    $highestDead = SsNode::where(function ($query) use ($cutoff) {
        $query->where('heartbeat_at', '<', $cutoff)
            ->orWhere(function ($q) use ($cutoff) {
                $q->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
            });
    })->max('id');
    $poolId = max(QA_BASE_ID, (int) $highestDead + 1);
    $qaIds[] = $poolId;
    $expired = date('Y-m-d H:i:s', strtotime('-90 days'));
    DB::table('ss_node')->insert(array(
        'id'           => $poolId,
        'name'         => 'QA-REGISTER-POOL-' . $poolId,
        'status'       => 0,
        'heartbeat_at' => $expired,
        'created_at'   => $expired,
        'updated_at'   => date('Y-m-d H:i:s'),
    ));
    echo "  pool dead node id: {$poolId}\n";

    // register: vision-hy2 = 3 协议 × ipv4+ipv6 = 6 槽位 → main + 5 clone;
    // node_level=9 (超范围) 触发等级钳制路径
    $t1 = time();
    $respReg = $ctrl->register(Request::create('/api/node/register', 'POST', array(
        'node_id'            => $mainId,
        'node_ip'            => '203.0.113.77',
        'node_ipv6'          => '2001:db8:77::1',
        'v2_name'            => 'vision-hy2',
        'node_level'         => 9,
        'node_traffic_limit' => 2000,
        'billing_mode'       => 'tx',
    )));
    $regData = $respReg->getData(true);
    echo "  register: clones = [" . implode(',', $regData['clone_node_ids']) . "]\n";

    // —— A: 等级钳制 ——
    $clones = SsNode::where('is_clone', $mainId)->get();
    assert_test('A1: main + 5 clone 全部生成 (6 槽位)', $clones->count() === 5,
        'clone 数 = ' . $clones->count());

    $main = SsNode::find($mainId);
    assert_test('A2: 主节点 level 按上报值原样保存 (9)', (int) $main->level === 9,
        'main level = ' . var_export($main->level, true));

    $levels = $clones->pluck('level')->map(function ($l) { return (int) $l; })->all();
    assert_test('A3: node_level=9 时所有 clone 等级恰为 5 (lo=hi=5, 旧代码会落 0)',
        count($levels) === 5 && count(array_unique($levels)) === 1 && $levels[0] === 5,
        'levels = [' . implode(',', $levels) . ']');
    assert_test('A4: clone 等级全部落在合法区间 [1,5]',
        count(array_filter($levels, function ($l) { return $l >= 1 && $l <= 5; })) === count($levels),
        'levels = [' . implode(',', $levels) . ']');

    // —— B: 原子认领 + 占位心跳 ——
    $poolRow = DB::table('ss_node')->where('id', $poolId)->first();
    assert_test('B1: 池内死节点被认领为 clone (is_clone=mainId, status=1)',
        $poolRow !== null && (int) $poolRow->is_clone === $mainId && (int) $poolRow->status === 1,
        'is_clone=' . var_export($poolRow ? $poolRow->is_clone : null, true)
            . ' status=' . var_export($poolRow ? $poolRow->status : null, true));
    assert_test('B2: 认领的 clone 出现在 register 响应 clone_node_ids',
        in_array($poolId, $regData['clone_node_ids'], false),
        'clone_node_ids = [' . implode(',', $regData['clone_node_ids']) . ']');
    assert_test('B3: 主节点 node_ids 含被认领 clone id',
        in_array((string) $poolId, explode(',', (string) $main->node_ids)),
        'node_ids = ' . $main->node_ids);

    $hbTs = $poolRow && $poolRow->heartbeat_at ? strtotime($poolRow->heartbeat_at) : 0;
    assert_test('B4: 占位心跳保留 (非 NULL, >= register 时刻; 未被落库清回 NULL)',
        $poolRow && $poolRow->heartbeat_at !== null && $hbTs >= $t1 - 60,
        'heartbeat_at = ' . var_export($poolRow ? $poolRow->heartbeat_at : null, true));

    // 认领后行内心跳新鲜 + status=1 → 死节点判定/autoReclaim 守卫均不再命中
    $stillDead = SsNode::where('status', 0)
        ->where(function ($q) use ($cutoff) {
            $q->where('heartbeat_at', '<', $cutoff)
              ->orWhere(function ($q2) use ($cutoff) {
                  $q2->whereNull('heartbeat_at')->where('created_at', '<', $cutoff);
              });
        })->where('id', $poolId)->exists();
    assert_test('B5: 认领后不再命中死节点判定 (autoReclaim 不会硬删)', $stillDead === false);

    // ============================================================
    echo "\n=== C: 反射直测 acquireCloneForSlot — 认领失败换下一个 ===\n";
    // ============================================================

    $base = max(QA_BASE_ID + 50, $poolId + 1);
    $c1Id = $base;      // 候选 1: 造出后立刻删行 → 模拟"选取后被并发回收/删除"
    $c2Id = $base + 1;  // 候选 2: 有效死节点 → 应被认领
    $qaIds = array_merge($qaIds, [$c1Id, $c2Id]);
    foreach (array($c1Id => 'QA-C1', $c2Id => 'QA-C2') as $cid => $nm) {
        DB::table('ss_node')->insert(array(
            'id'           => $cid,
            'name'         => $nm . '-' . $cid,
            'status'       => 0,
            'heartbeat_at' => $expired,
            'created_at'   => $expired,
            'updated_at'   => date('Y-m-d H:i:s'),
        ));
    }

    $c1 = SsNode::find($c1Id);   // 模型先加载 (模拟池查询时行还在)
    DB::table('ss_node')->where('id', $c1Id)->delete(); // 选取后行被并发删除
    $c2 = SsNode::find($c2Id);

    $deadNodes = new \Illuminate\Database\Eloquent\Collection([$c1, $c2]);
    $deadIdx = 0;
    $method = new ReflectionMethod($ctrl, 'acquireCloneForSlot');
    $method->setAccessible(true);
    $clone = $method->invokeArgs($ctrl, array($main, 'vision-hy2', 2, 4, $deadNodes, &$deadIdx, $cutoff));

    assert_test('C1: 候选 1 (已删行) 认领 0 行被跳过, 返回候选 2',
        (int) $clone->id === $c2Id,
        '返回 clone id = ' . var_export($clone->id, true) . ' expect = ' . $c2Id);
    assert_test('C2: 池游标前移 2 (两个候选都被消费)', $deadIdx === 2,
        'deadIdx = ' . $deadIdx);
    $c2Row = DB::table('ss_node')->where('id', $c2Id)->first();
    assert_test('C3: 候选 2 已落库为 clone (is_clone=mainId, status=1, 等级在 [2,4])',
        $c2Row !== null && (int) $c2Row->is_clone === $mainId && (int) $c2Row->status === 1
        && (int) $c2Row->level >= 2 && (int) $c2Row->level <= 4,
        'is_clone=' . var_export($c2Row ? $c2Row->is_clone : null, true)
            . ' level=' . var_export($c2Row ? $c2Row->level : null, true));
} finally {
    if ($mainId !== null) {
        DB::table('ss_node')->where('id', $mainId)->delete();
        DB::table('ss_node')->where('is_clone', $mainId)->delete();
        DB::table('dns_records')->whereIn('node_id', [$mainId])->delete();
        DB::table('ss_node_traffic_daily')->whereIn('node_id', [$mainId])->delete();
    }
    if (!empty($qaIds)) {
        DB::table('ss_node')->whereIn('id', $qaIds)->delete();
        DB::table('dns_records')->whereIn('node_id', $qaIds)->delete();
        DB::table('ss_node_traffic_daily')->whereIn('node_id', $qaIds)->delete();
    }
    echo "\n[cleanup] 测试节点 (main #{$mainId}, qa [" . implode(',', $qaIds) . "]) 已清理\n";
}

echo "\n========================================\n";
echo "结果: {$passed} passed, {$failed} failed\n";
echo "========================================\n";
exit($failed > 0 ? 1 : 0);
