<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * ⚠ '*': $request->ip() 会返回客户端自带 X-Forwarded-For 里的伪造值 ——
     * 该头攻击者可控(fideloper 实现为信任直接对端并取 XFF 链最右未受信项,
     * 直连带假 XFF、经 CDN 带假 XFF 时取到的都是伪造项)。入口 nginx realip
     * (real_ip_header X-Forwarded-For, 只取最右)已把真实来源还原进 REMOTE_ADDR,
     * 应用内做安全相关的 IP 判定(限流分桶 / api_ip_allowlist 白名单)时
     * 必须用 REMOTE_ADDR, 勿用 $request->ip()(见 BackendRateLimit /
     * BackendToken / UserApiToken)。
     *
     * @var array
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
