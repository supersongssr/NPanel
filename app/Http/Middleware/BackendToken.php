<?php

namespace App\Http\Middleware;

use App\Http\Models\SsNode;
use Closure;
use Illuminate\Http\Request;

/**
 * v2 后端节点 API 鉴权中间件(/api/v2/backend/*)
 *
 * 标准 Bearer token, 每节点一个独立 token(ss_node.api_token 唯一索引精确匹配)。
 * 参照现有 NodeApiToken 的 Bearer 解析写法, 但不使用 env('API_TOKEN') 全局共享 key
 * —— 一个 key 泄露等于全部节点沦陷。
 *
 * 错误信封(契约 docs/openapi.yaml):
 *   401 {"error":{"code":"INVALID_TOKEN","message":"token is invalid or revoked"}}
 *   403 {"error":{"code":"NODE_DISABLED","message":"node is disabled"}}
 *
 * 禁用口径: ss_node.status == 0, 与现有节点接口(/api/node/config 返回 403)一致。
 * 节点流量耗尽不算"禁用" —— GET /users 返回 200+空列表(插件据此删光用户)。
 */
class BackendToken
{
    public function handle(Request $request, Closure $next)
    {
        $error = $this->authenticate($request, $node);
        if ($error !== null) {
            return response()->json([
                'error' => ['code' => $error['code'], 'message' => $error['message']],
            ], $error['status'], ['Content-Type' => 'application/json; charset=utf-8']);
        }

        // 节点对象传给控制器(免二次查询)
        $request->attributes->set('node', $node);
        return $next($request);
    }

    /**
     * @param Request $request
     * @param SsNode|null $node 鉴权成功时输出节点对象
     * @return array|null 失败时返回 ['status'=>int,'code'=>string,'message'=>string]
     */
    private function authenticate(Request $request, &$node)
    {
        $header = (string)$request->header('Authorization', '');
        $token = '';
        if (stripos($header, 'Bearer ') === 0) {
            $token = trim(substr($header, 7));
        }

        // 长度守卫: 契约 token 为 43 字符, 异常长度直接 401, 不进库
        if ($token === '' || strlen($token) > 64) {
            return ['status' => 401, 'code' => 'INVALID_TOKEN', 'message' => 'token is invalid or revoked'];
        }

        $node = SsNode::query()->where('api_token', $token)->first();
        if ($node === null) {
            return ['status' => 401, 'code' => 'INVALID_TOKEN', 'message' => 'token is invalid or revoked'];
        }

        // 禁用口径与现有节点接口一致: status=0
        if ((int)$node->status === 0) {
            return ['status' => 403, 'code' => 'NODE_DISABLED', 'message' => 'node is disabled'];
        }

        // 可选 IP 白名单(纵深防御): 配置了就强制校验
        $allowlist = trim((string)$node->api_ip_allowlist);
        if ($allowlist !== '' && !$this->ipAllowed($request->ip(), $allowlist)) {
            return ['status' => 401, 'code' => 'INVALID_TOKEN', 'message' => 'token is invalid or revoked'];
        }

        return null;
    }

    /**
     * IP/CIDR 白名单匹配(逗号分隔; 支持 1.2.3.4 与 10.0.0.0/8 两种形式)
     */
    private function ipAllowed($ip, $allowlist)
    {
        if ($ip === null || $ip === '') {
            return false;
        }
        foreach (explode(',', $allowlist) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (strpos($entry, '/') !== false) {
                if ($this->cidrMatch($ip, $entry)) {
                    return true;
                }
            } elseif ($ip === $entry) {
                return true;
            }
        }
        return false;
    }

    private function cidrMatch($ip, $cidr)
    {
        list($subnet, $bits) = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $bits = (int)$bits;
        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits)) & 0xFFFFFFFF;
        return (($ipLong & $mask) === ($subnetLong & $mask));
    }
}
