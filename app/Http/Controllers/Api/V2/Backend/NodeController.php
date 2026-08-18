<?php

namespace App\Http\Controllers\Api\V2\Backend;

use App\Http\Controllers\Controller;
use App\Http\Models\SsNode;
use App\Http\Models\SsNodeInfo;
use App\Http\Models\SsNodeOnlineLog;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

/**
 * v2 后端节点 API 控制器(/api/v2/backend/*)
 *
 * 契约: xray-plugin-api docs/openapi.yaml(唯一事实源)
 * 服务对象: xray-plugin-api 节点插件(纯 HTTP 客户端, 无数据库访问)
 *
 * 关键语义(违反 = 生产事故):
 *  - GET /users 是全量快照, 名单里没有的用户就是该删的用户
 *  - 倍率只在这里乘: user.u/d 累加乘率值, user_traffic_log 存原始字节, ss_node.traffic_used 累加原始字节
 *  - 全部批量一次性写入, 禁止循环内逐条 INSERT, 禁止 ORM 读-改-写累加
 *  - 信封: 成功 {"data":{...}}, 失败 {"error":{...}}
 *
 * 与 SPanel 实现的差异(两套遗留 schema):
 *  - uuid 取值: ss_node.node_uuid 非空优先, 否则 user.vmess_id(与订阅层一致, 漏掉会导致独立凭据节点认证失败)
 *  - 用户过滤无 expire_in 列(到期由面板定时任务收敛到 enable), 无 is_admin 旁路 —— 与旧 srp 插件口径一致
 *  - 等级列 user.level, 心跳列 heartbeat_at
 */
class NodeController extends Controller
{
    /**
     * GET /users — 本节点有效用户全量快照
     *
     * 过滤清单(与旧 srp 插件 SQL 口径一致):
     *  1. enable=1  2. 有余量(transfer_enable > u+d)  3. 等级 level >= ss_node.level
     *  4. 分组匹配(ss_node.node_group=0 不限, 否则相等)
     *  5. 节点流量耗尽 → 空列表(200, 插件据此删光用户)
     *  6. 顺带刷新节点心跳
     */
    public function users(Request $request)
    {
        /** @var SsNode $node */
        $node = $request->attributes->get('node');
        $nodeUuid = trim((string)$node->node_uuid);

        // 心跳
        $node->heartbeat_at = date('Y-m-d H:i:s');
        $node->save();

        // 节点流量耗尽: 200 + 空列表
        if ((int)$node->traffic_limit !== 0
            && (int)$node->traffic_used >= (int)$node->traffic_limit) {
            return $this->ok(['users' => []]);
        }

        $query = User::query()
            ->where('enable', 1)
            ->whereRaw('`transfer_enable` > `u` + `d`')
            ->where('level', '>=', $node->level);

        if ((int)$node->node_group !== 0) {
            $query->where('node_group', (int)$node->node_group);
        }

        // 单条 SELECT 取全部所需列(最小披露)
        $usersRaw = $query->get(['id', 'username', 'vmess_id']);

        $users = [];
        foreach ($usersRaw as $u) {
            $users[] = [
                'id' => (int)$u->id,
                'email' => (string)$u->username,
                // 独立凭据节点优先(与订阅层 SubscribeController 一致)
                'uuid' => $nodeUuid !== '' ? $nodeUuid : (string)$u->vmess_id,
            ];
        }

        return $this->ok(['users' => $users]);
    }

    /**
     * POST /traffic — 批量上报流量增量(原始字节)
     *
     * 写路径(与 SPanel 同口径, 倍率只在这里):
     *  - user.u/d 累加乘率值(原子 CASE WHEN 单语句, t 刷新)
     *  - user_traffic_log 存原始字节 + 当次倍率 + 乘率后人类可读值(一条多 VALUES 批量 INSERT)
     *  - ss_node.traffic_used 累加原始字节(原子 UPDATE) + traffic_left 派生刷新
     */
    public function traffic(Request $request)
    {
        /** @var SsNode $node */
        $node = $request->attributes->get('node');

        $data = $request->input('data');
        if (!is_array($data) || !is_array($items = array_get($data, 'items'))) {
            return $this->err(400, 'BAD_REQUEST', 'data.items is required');
        }

        $rate = (float)$node->traffic_rate;
        $now = time();

        // 校验 + 按 userId 合并(防重复 id 导致 CASE 覆盖)
        // 严格 is_int: JSON 里超出 PHP int 范围的数字会解码成 float(如 1e30),
        // (int) 强转会产生垃圾正数直接污染计费 —— 盲盒测试发现的真 bug, 必须拒收
        $merged = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                return $this->err(400, 'BAD_REQUEST', 'item must be an object');
            }
            $userId = array_get($item, 'userId');
            $up = array_get($item, 'uplinkBytes');
            $down = array_get($item, 'downlinkBytes');
            if (!is_int($userId) || $userId <= 0 || !is_int($up) || $up < 0 || !is_int($down) || $down < 0) {
                return $this->err(400, 'BAD_REQUEST', 'invalid item fields');
            }
            if (!isset($merged[$userId])) {
                $merged[$userId] = [0, 0];
            }
            $merged[$userId][0] += $up;
            $merged[$userId][1] += $down;
        }

        $accepted = count($merged);
        if ($accepted === 0) {
            return $this->ok(['accepted' => 0]);
        }

        $nodeId = (int)$node->id;
        $totalRaw = 0;
        $rateStr = var_export($rate, true); // float 字面量, e.g. '0.5'
        if ($rateStr === '1.0') {
            $rateStr = '1';
        }

        // CASE WHEN 单语句原子累加(u/d 乘倍率), 值全部来自上面强转的整数
        $caseU = '';
        $caseD = '';
        $ids = [];
        foreach ($merged as $userId => $pair) {
            $ids[] = $userId;
            $caseU .= sprintf(' WHEN %d THEN u + %d * %s', $userId, $pair[0], $rateStr);
            $caseD .= sprintf(' WHEN %d THEN d + %d * %s', $userId, $pair[1], $rateStr);
            $totalRaw += $pair[0] + $pair[1];
        }
        $idList = implode(',', $ids);

        DB::transaction(function () use ($idList, $caseU, $caseD, $rateStr, $now, $nodeId, $merged, $totalRaw) {
            DB::update(
                "UPDATE `user` SET
                    `u` = CASE `id` {$caseU} END,
                    `d` = CASE `id` {$caseD} END,
                    `t` = {$now}
                WHERE `id` IN ({$idList})"
            );

            // 流量明细: 一条多 VALUES 批量 INSERT(每 500 行一批)
            $rows = [];
            foreach ($merged as $userId => $pair) {
                $rows[] = [
                    'user_id' => $userId,
                    'u' => $pair[0],
                    'd' => $pair[1],
                    'node_id' => $nodeId,
                    'rate' => (float)$rateStr,
                    'traffic' => flowAutoShow(($pair[0] + $pair[1]) * (float)$rateStr),
                    'log_time' => $now,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('user_traffic_log')->insert($chunk);
            }

            // 节点用量累加原始字节(原子 UPDATE)
            DB::update(
                "UPDATE `ss_node` SET `traffic_used` = `traffic_used` + {$totalRaw} WHERE `id` = {$nodeId}"
            );
        });

        // 派生字段刷新(剩余流量, 与 NodeApiController::status 口径一致): 取库内最新值重算
        $freshNode = SsNode::query()->find($node->id);
        if ($freshNode !== null) {
            $freshNode->traffic_left = max(0, $freshNode->traffic_limit - $freshNode->traffic_used);
            $freshNode->heartbeat_at = date('Y-m-d H:i:s');
            $freshNode->save();
        }

        return $this->ok(['accepted' => $accepted]);
    }

    /**
     * POST /status — 节点状态心跳
     *
     * 写 ss_node_info(uptime/load)+ 心跳; onlineUsers>0 写 ss_node_online_log。
     * 表结构与 SPanel 相同, 行为与旧插件直接写库一致。
     */
    public function status(Request $request)
    {
        /** @var SsNode $node */
        $node = $request->attributes->get('node');

        $data = $request->input('data');
        if (!is_array($data)
            || !array_has($data, ['uptimeSeconds', 'loadAverage', 'onlineUsers'])) {
            return $this->err(400, 'BAD_REQUEST', 'uptimeSeconds/loadAverage/onlineUsers are required');
        }

        $uptime = max(0, (int)array_get($data, 'uptimeSeconds'));
        $onlineUsers = max(0, (int)array_get($data, 'onlineUsers'));
        $loadAvg = array_get($data, 'loadAverage', []);
        $load = sprintf(
            '%.2f %.2f %.2f',
            (float)array_get($loadAvg, 'oneMinute', 0.0),
            (float)array_get($loadAvg, 'fiveMinutes', 0.0),
            (float)array_get($loadAvg, 'fifteenMinutes', 0.0)
        );

        $now = time();
        $info = new SsNodeInfo();
        $info->node_id = (int)$node->id;
        $info->uptime = $uptime;
        $info->load = $load;
        $info->log_time = $now;
        $info->save();

        if ($onlineUsers > 0) {
            $online = new SsNodeOnlineLog();
            $online->node_id = (int)$node->id;
            $online->online_user = $onlineUsers;
            $online->log_time = $now;
            $online->save();
        }

        $node->heartbeat_at = date('Y-m-d H:i:s', $now);
        $node->save();

        return $this->ok(['accepted' => true]);
    }

    /**
     * GET /healthz — 健康检查(无鉴权, 给 nginx/监控探活)
     */
    public function healthz()
    {
        return $this->ok(['status' => 'ok']);
    }

    private function ok(array $data)
    {
        return response()->json(['data' => $data], 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    private function err($status, $code, $message)
    {
        return response()->json(
            ['error' => ['code' => $code, 'message' => $message]],
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
