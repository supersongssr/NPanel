<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * v2 后端节点 API 应用层限流(基于 Redis, 按来源 IP 计数)
 *
 * 背景: /api/v2/backend/* 刻意脱离 'api' 组的 throttle:60,1 —— ThrottleRequests
 * 无登录态时按 IP 计数, 多节点共享出口 IP 时会被 429 恶性循环。但移除后应用层
 * 零限流: 携带伪造 Bearer(≤64 字符)的未授权请求仍会触发 ss_node.api_token 的
 * DB 查询, 可低成本制造 DB 负载(审查 f-c490ed87)。
 *
 * 本中间件补上这层: Redis INCR + EXPIRE 固定窗口, 60 次/分钟/IP。
 * 对合法流量极其宽松 —— 节点上报周期约 1 小时一轮(3 请求/轮), 数百节点共享
 * 一个出口 IP 也远低于阈值, 只拦截明显的暴力刷量; 挂在 backend.token 之前,
 * 被拦截的请求不再进库查询。
 *
 * 【既定设计, 非 bug, 审查备注(2026-09 复审)】LIMIT=60 与被移除的 throttle:60,1
 * 数值相同, 但不会复现当年的 429 恶性循环: 正常部署形态是"一个节点一个 id、
 * 一台机器一个独立出口 IP"(每节点仅 3 请求/轮, 远低于阈值); 单 IP 聚合数十
 * 节点的共享出口 IP 形态不是预期部署, 真出现时再调 LIMIT 或改为可配置即可。
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
    /** 每窗口最大请求数 */
    const LIMIT = 60;

    /** 窗口长度(秒) */
    const WINDOW = 60;

    /** fail-open 告警节流间隔(秒): Redis 不可用时至多每该时长记一条 warning, 防日志风暴 */
    const WARN_THROTTLE_SECONDS = 60;

    /** @var int|null 上次 fail-open 告警时间戳(进程内节流, php-fpm 多 worker 各自独立) */
    private static $lastWarnAt = null;

    public function handle(Request $request, Closure $next)
    {
        $ip = (string)$request->ip();
        if ($ip === '') {
            $ip = 'unknown';
        }
        $key = 'backendapi:rl:' . $ip;

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

        if ($count > self::LIMIT) {
            return response()->json([
                'error' => ['code' => 'RATE_LIMITED', 'message' => 'too many requests'],
            ], 429, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        return $next($request);
    }
}
