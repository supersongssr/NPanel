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
 * 例外: POST /status 放行 —— status 是流量熔断(traffic_left<120GB → status=0)后的
 * 唯一恢复通道, 熔断节点必须仍能心跳(v1 /api/node/status 同样不因 status=0 拒绝),
 * 否则月度流量重置后节点永远无法自动恢复上线。
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

        // 禁用口径与现有节点接口一致: status=0 → users/traffic 403(配置/计费通道切断)。
        // 唯一例外: POST /status 放行 —— 熔断下线(traffic_left<120GB)的节点必须仍能
        // 心跳, 否则月度流量重置后无法自动恢复上线(v1 /api/node/status 从不因 status=0 拒绝)。
        if ((int)$node->status === 0 && !$request->is('*/v2/backend/status')) {
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
     * IP/CIDR 白名单匹配(逗号分隔; IPv4/IPv6 均支持, 精确 IP 与 CIDR 网段两种形式)。
     * 双栈归一: IPv4-mapped IPv6(::ffff:1.2.3.4)与 1.2.3.4 视为同一地址,
     * 避免双栈监听下 REMOTE_ADDR 形态不同导致白名单漏配。
     */
    private function ipAllowed($ip, $allowlist)
    {
        if ($ip === null || $ip === '') {
            return false;
        }
        $ipNorm = $this->normalizeIp($ip);
        foreach (explode(',', $allowlist) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if ($ipNorm === $this->normalizeIp($entry)) {
                return true; // 精确 IP(两侧归一后比对)
            }
            if (strpos($entry, '/') !== false && $this->cidrMatch($ipNorm, $entry)) {
                return true; // CIDR 网段
            }
        }
        return false;
    }

    /**
     * CIDR 匹配(inet_pton 二进制按位比较, IPv4/IPv6 通用)。
     * 协议族不一致(IPv4 地址 vs IPv6 网段, 或反之)不匹配;
     * /0 语义为该协议族全部地址。
     */
    private function cidrMatch($ip, $cidr)
    {
        list($subnet, $bitsStr) = explode('/', $cidr, 2);
        $bitsStr = trim((string)$bitsStr);
        if ($bitsStr === '' || !ctype_digit($bitsStr)) {
            // 前缀非数字(如 10.0.0.0/abc)不能当 /0 全放行 —— 直接失配
            return false;
        }
        $bits = (int)$bitsStr;

        $ipBin = $this->ptonNormalized($ip);
        $subnetBin = $this->ptonNormalized($subnet);
        if ($ipBin === null || $subnetBin === null) {
            return false; // 地址/网段非法
        }
        if (strlen($ipBin) !== strlen($subnetBin) || $bits > strlen($ipBin) * 8) {
            return false; // 协议族不一致或前缀超长(IPv4≤32, IPv6≤128)
        }

        // 前 bits 位逐段比较: 整字节直接比对, 剩余位按掩码
        $fullBytes = intdiv($bits, 8);
        $remBits = $bits % 8;
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }
        if ($remBits > 0) {
            $mask = (0xFF << (8 - $remBits)) & 0xFF;
            if ((ord($ipBin[$fullBytes]) & $mask) !== (ord($subnetBin[$fullBytes]) & $mask)) {
                return false;
            }
        }
        return true;
    }

    /**
     * inet_pton + IPv4-mapped 归一(::ffff:a.b.c.d → 4 字节 IPv4), 非法地址返回 null。
     * 注: ip2long 只认 IPv4 点分十进制, IPv6 一律 false —— 旧实现因此
     * 无法支持 IPv6 CIDR, 配了 IPv6 网段的节点会被误锁死(报错文案还伪装成 token 无效)。
     */
    private function ptonNormalized($addr)
    {
        $addr = trim((string)$addr);
        if ($addr === '') {
            return null;
        }
        $bin = @inet_pton($addr);
        if ($bin === false) {
            return null;
        }
        if (strlen($bin) === 16
            && substr($bin, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
            $bin = substr($bin, 12); // ::ffff:a.b.c.d → 纯 IPv4
        }
        return $bin;
    }

    /**
     * 文本地址归一(无法解析则原样返回, 与旧行为的字符串比对兑底一致)
     */
    private function normalizeIp($ip)
    {
        $bin = $this->ptonNormalized($ip);
        if ($bin === null) {
            return $ip;
        }
        $norm = @inet_ntop($bin);
        return $norm === false ? $ip : $norm;
    }
}
