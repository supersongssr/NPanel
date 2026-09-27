<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * v2 后端节点 API 应用层限流(基于 Redis, 按 REMOTE_ADDR 计数)
 *
 * 背景: /api/v2/backend/* 刻意脱离 'api' 组的 throttle:60,1 —— ThrottleRequests
 * 无登录态时按 IP 计数, 多节点共享出口 IP 时会被 429 恶性循环。但移除后应用层
 * 零限流: 携带伪造 Bearer(≤64 字符)的未授权请求仍会触发 ss_node.api_token 的
 * DB 查询, 可低成本制造 DB 负载(审查 f-c490ed87)。
 *
 * 本中间件补上这层: Redis INCR + EXPIRE 固定窗口, 默认 60 次/分钟/IP(可经
 * BACKEND_API_RATE_LIMIT 配置覆盖, 见 config/backendapi.php), 挂在
 * backend.token 之前, 被拦截的请求不再进库查询。
 *
 * 分桶必须用 REMOTE_ADDR 而非 $request->ip()(审查 f-ad403d09): TrustProxies
 * 配置为 '*'(fideloper 实现为"信任当前直接对端", 并从 XFF 链剔除受信项后
 * 取最右未受信项)时, $request->ip() 返回客户端自带 X-Forwarded-For 里的
 * 伪造值 —— 直连带假 XFF、或经 CDN 但带假 XFF(前置代理追加的真实 IP 恰被
 * 当受信项剔除)都可控, 每个请求换一个假 IP 即可拿到全新桶, 限流完全失效。
 * 面板入口 nginx 已配置 real_ip_header X-Forwarded-For(默认 recursive off,
 * 只取最右侧、即前置代理追加的真实来源): 经 CDN 的流量 REMOTE_ADDR 已被
 * realip 还原为真实客户端 IP; 直连源站的流量 REMOTE_ADDR 即 TCP 对端,
 * nginx 不采信其自带 XFF —— 两种路径下 REMOTE_ADDR 均不可伪造。
 *
 * 【既定设计, 非 bug】阈值口径基于 "一个节点一个 id、一台机器一个独立出口 IP"
 * 的部署前提(单节点每轮仅 3 请求, 即使分钟级上报周期也远低于 60 次/分钟);
 * 多节点共享单一出口 IP 的形态在当前部署中不存在, 真出现时调高
 * BACKEND_API_RATE_LIMIT 即可, 无需改代码。
 *
 * fail-open: Redis 不可用时放行 + warning 日志(限流是旁路防护, 不能因 Redis
 * 故障阻断节点上报主链路); 告警按 WARN_THROTTLE_SECONDS 节流, 防 Redis 故障
 * 与刷量叠加时逐请求落日志把日志灌爆。
 *
 * 超限响应为契约信封(插件已有退避逻辑):
 *   429 {"error":{"code":"RATE_LIMITED","message":"too many requests"}}
 */
class BackendRateLimit
{
    /** 每窗口最大请求数(默认; 运行时经 config backendapi.rate_limit 覆盖) */
    const DEFAULT_LIMIT = 60;

    /** 窗口长度(秒) */
    const WINDOW = 60;

    /** fail-open 告警节流间隔(秒): Redis 不可用时至多每该时长记一条 warning, 防日志风暴 */
    const WARN_THROTTLE_SECONDS = 60;

    /** @var int|null 上次 fail-open 告警时间戳(进程内节流, php-fpm 多 worker 各自独立) */
    private static $lastWarnAt = null;

    public function handle(Request $request, Closure $next)
    {
        // 限流分桶用 REMOTE_ADDR(不经 TrustProxies 的 XFF 换算, 伪造头换不了桶)
        $ip = (string)$request->server('REMOTE_ADDR');
        if ($ip === '') {
            $ip = 'unknown';
        }
        $key = 'backendapi:rl:' . $ip;
        $limit = max(1, (int)config('backendapi.rate_limit', self::DEFAULT_LIMIT));

        try {
            $count = Redis::incr($key);
            // 固定窗口: 首次计数设置过期; TTL<0 兜底(进程在 INCR 与 EXPIRE 之间崩溃
            // 会留下无 TTL 的孤儿键, 永久锁死该 IP, 这里补上过期自愈)
            if ($count === 1 || Redis::ttl($key) < 0) {
                Redis::expire($key, self::WINDOW);
            }
        } catch (\Exception $e) {
            // fail-open 告警节流(进程内): Redis 故障 + 被刷的组合下, 逐请求落日志会把
            // 日志灌爆(审查修复); 每 WARN_THROTTLE_SECONDS 秒至多记一条
            $nowTs = time();
            if (self::$lastWarnAt === null || $nowTs - self::$lastWarnAt >= self::WARN_THROTTLE_SECONDS) {
                self::$lastWarnAt = $nowTs;
                Log::warning('[BackendApi v2] rate limit redis unavailable, fail-open', [
                    'ip' => $ip,
                    'err' => $e->getMessage(),
                ]);
            }
            return $next($request);
        }

        if ($count > $limit) {
            return response()->json([
                'error' => ['code' => 'RATE_LIMITED', 'message' => 'too many requests'],
            ], 429, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return $next($request);
    }
}
