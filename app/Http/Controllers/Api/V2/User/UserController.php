<?php

namespace App\Http\Controllers\Api\V2\User;

use App\Http\Controllers\Controller;
use App\Http\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * v2 用户查询 API 控制器(/api/v2/user/*)
 *
 * 服务对象: 运维/监控/客服系统(纯 HTTP 客户端), 用于按 id/email 快速查询用户流量信息。
 * 只读接口, 不写任何数据。
 *
 * 信封(与 /api/v2/backend 一致):
 *  - 成功: {"data": {...}}
 *  - 失败: {"error": {"code": "...", "message": "..."}}
 *
 * 字段口径(与 sql/db.sql user 表一致):
 *  - u/d: 已上传/已下载字节(乘倍率后的计费值)
 *  - transfer_enable: 可用流量总额(字节)
 *  - used = u + d, remaining = max(0, transfer_enable - used)
 *  - *_human: flowAutoShow 人类可读单位
 *  - email 即 user.username(用户名(邮箱) 列)
 */
class UserController extends Controller
{
    /** 批量查询单次上限 */
    const BATCH_LIMIT = 100;

    /**
     * GET /traffic — 单用户流量查询
     *
     * 查询参数(二选一, 互斥):
     *  - id:    用户 id(正整数)
     *  - email: 用户邮箱(即 username 列, 精确匹配)
     */
    public function traffic(Request $request)
    {
        // 标量守卫: ?id[]=1 之类的数组参数强转 string 会触发 "Array to string
        // conversion" notice, 被 Laravel 转成异常后返回 500 而非契约的 400
        $rawId = $request->query('id', '');
        $rawEmail = $request->query('email', '');
        if (!is_string($rawId) || !is_string($rawEmail)) {
            return $this->err(400, 'BAD_REQUEST', 'id and email must be scalar strings');
        }
        $id = trim($rawId);
        $email = trim($rawEmail);

        if ($id === '' && $email === '') {
            return $this->err(400, 'BAD_REQUEST', 'id or email is required');
        }
        if ($id !== '' && $email !== '') {
            return $this->err(400, 'BAD_REQUEST', 'id and email are mutually exclusive, provide only one');
        }

        if ($id !== '') {
            if (!ctype_digit($id) || strlen($id) > 11 || (int)$id <= 0) {
                return $this->err(400, 'BAD_REQUEST', 'id must be a positive integer');
            }
            $user = User::query()->where('id', (int)$id)->first($this->columns());
        } else {
            if (mb_strlen($email) > 128) {
                return $this->err(400, 'BAD_REQUEST', 'email is too long');
            }
            $user = User::query()->where('username', $email)->first($this->columns());
        }

        if ($user === null) {
            return $this->err(404, 'USER_NOT_FOUND', 'user not found');
        }

        return $this->ok(['user' => $this->formatUser($user)]);
    }

    /**
     * POST /traffic — 批量用户流量查询
     *
     * 请求体: {"data": {"ids": [1,2,3]}} 或 {"data": {"emails": ["a@x.com","b@x.com"]}}
     * (ids / emails 二选一, 互斥, 单次最多 100 个)
     *
     * 响应: {"data":{"users":[{...},...]}}, 查不到的用户不出现在结果里(不报错)。
     */
    public function trafficBatch(Request $request)
    {
        $data = $request->input('data');
        if (!is_array($data)) {
            return $this->err(400, 'BAD_REQUEST', 'data is required and must be an object');
        }

        $ids = array_get($data, 'ids');
        $emails = array_get($data, 'emails');

        if ($ids === null && $emails === null) {
            return $this->err(400, 'BAD_REQUEST', 'data.ids or data.emails is required');
        }
        if ($ids !== null && $emails !== null) {
            return $this->err(400, 'BAD_REQUEST', 'data.ids and data.emails are mutually exclusive, provide only one');
        }

        $query = User::query();
        if ($ids !== null) {
            if (!is_array($ids) || count($ids) === 0) {
                return $this->err(400, 'BAD_REQUEST', 'data.ids must be a non-empty array');
            }
            if (count($ids) > self::BATCH_LIMIT) {
                return $this->err(400, 'BAD_REQUEST', 'data.ids exceeds batch limit ' . self::BATCH_LIMIT);
            }
            $cleanIds = [];
            foreach ($ids as $v) {
                // 严格 is_int: JSON 数字超出 PHP int 范围会解码成 float, 强转会污染查询
                if (!is_int($v) || $v <= 0) {
                    return $this->err(400, 'BAD_REQUEST', 'data.ids contains invalid id');
                }
                $cleanIds[(int)$v] = true; // 去重, 防 IN 列表重复
            }
            $query->whereIn('id', array_keys($cleanIds))->orderBy('id', 'asc');
        } else {
            if (!is_array($emails) || count($emails) === 0) {
                return $this->err(400, 'BAD_REQUEST', 'data.emails must be a non-empty array');
            }
            if (count($emails) > self::BATCH_LIMIT) {
                return $this->err(400, 'BAD_REQUEST', 'data.emails exceeds batch limit ' . self::BATCH_LIMIT);
            }
            $cleanEmails = [];
            foreach ($emails as $v) {
                if (!is_string($v) || trim($v) === '' || mb_strlen($v) > 128) {
                    return $this->err(400, 'BAD_REQUEST', 'data.emails contains invalid email');
                }
                $cleanEmails[trim($v)] = true;
            }
            $query->whereIn('username', array_keys($cleanEmails))->orderBy('id', 'asc');
        }

        $users = $query->get($this->columns());

        $list = [];
        foreach ($users as $user) {
            $list[] = $this->formatUser($user);
        }

        return $this->ok(['users' => $list, 'count' => count($list)]);
    }

    /**
     * 查询列(最小披露: 不查密码/passwd/vmess_id 等凭据列)
     */
    private function columns()
    {
        return ['id', 'username', 'u', 'd', 'transfer_enable', 't', 'enable', 'status',
                'expire_time', 'ban_time', 'level', 'traffic_reset_day'];
    }

    /**
     * 组装单个用户的流量信息
     */
    private function formatUser($user)
    {
        $u = (int)$user->u;
        $d = (int)$user->d;
        $total = (int)$user->transfer_enable;

        // u/d 理论上界 = bigint 饱和值(LEAST 封顶写入), 极端情况下 u+d 可能溢出 int 变 float, 原样输出
        $used = $u + $d;
        $remaining = $total > $used ? $total - $used : 0;
        $percent = $total > 0 ? round($used * 100 / $total, 2) : 0.0;

        return [
            'id'                     => (int)$user->id,
            'email'                  => (string)$user->username,
            'u'                      => $u,
            'd'                      => $d,
            'used'                   => $used,
            'transfer_enable'        => $total,
            'remaining'              => $remaining,
            'usage_percent'          => $percent,
            'u_human'                => flowAutoShow($u),
            'd_human'                => flowAutoShow($d),
            'used_human'             => flowAutoShow($used),
            'transfer_enable_human'  => flowAutoShow($total),
            'remaining_human'        => flowAutoShow($remaining),
            'last_traffic_time'      => (int)$user->t,
            'enable'                 => (int)$user->enable,
            'status'                 => (int)$user->status,
            'level'                  => (int)$user->level,
            'expire_time'            => (string)$user->expire_time,
            'ban_time'               => (int)$user->ban_time,
            'traffic_reset_day'      => (int)$user->traffic_reset_day,
        ];
    }

    private function ok(array $data)
    {
        return JsonResponse::create(['data' => $data], 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    private function err($status, $code, $message)
    {
        return JsonResponse::create(
            ['error' => ['code' => $code, 'message' => $message]],
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
