<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * v2 用户查询 API 鉴权中间件(/api/v2/user/*)
 *
 * 标准 Bearer token, 取自 env('USER_API_TOKEN')(与节点的全局 API_TOKEN、
 * 每节点 ss_node.api_token 均不共用 —— 用户 API 暴露邮箱/流量等敏感信息, 独立 key 便于单独吊销)。
 *
 * 错误信封(与 backend.token / docs/api 契约一致):
 *   401 {"error":{"code":"INVALID_TOKEN","message":"token is invalid or revoked"}}
 *   503 {"error":{"code":"API_DISABLED","message":"USER_API_TOKEN is not configured"}}
 *
 * 未配置 USER_API_TOKEN 时整体 fail-closed(拒绝所有请求), 避免上线时忘配 key 变成裸奔。
 *
 * 可选 IP 白名单: env('USER_API_IP_ALLOWLIST'), 逗号分隔 IP/CIDR(如 1.2.3.4,10.0.0.0/8),
 * 配置了就强制校验, 不匹配返回 401(纵深防御)。
 */
class UserApiToken
{
    /** token 长度上限: 正常为 43 字符 base64url, 异常超长直接 401, 不做比较 */
    const MAX_TOKEN_LENGTH = 128;

    public function handle(Request $request, Closure $next)
    {
        $configured = trim((string)env('USER_API_TOKEN', ''));

        // fail-closed: 未配置即整体禁用
        if ($configured === '') {
            return $this->deny(503, 'API_DISABLED', 'USER_API_TOKEN is not configured');
        }

        $header = (string)$request->header('Authorization', '');
        $token = '';
        if (stripos($header, 'Bearer ') === 0) {
            $token = trim(substr($header, 7));
        }

        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH
            || !hash_equals($configured, $token)) {
            return $this->deny(401, 'INVALID_TOKEN', 'token is invalid or revoked');
        }

        // 可选 IP 白名单(纵深防御): 配置了就强制校验
        $allowlist = trim((string)env('USER_API_IP_ALLOWLIST', ''));
        if ($allowlist !== '' && !$this->ipAllowed($request->ip(), $allowlist)) {
            return $this->deny(401, 'INVALID_TOKEN', 'token is invalid or revoked');
        }

        return $next($request);
    }

    private function deny($status, $code, $message)
    {
        return response()->json(
            ['error' => ['code' => $code, 'message' => $message]],
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
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
