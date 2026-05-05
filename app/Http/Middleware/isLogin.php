<?php

namespace App\Http\Middleware;

use Closure;
use Redirect;
use Redis;
use Log;

class isLogin
{
    /**
     * 校验是否已登录
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // 从 session 中取出 user_id，不触发数据库查询
        $sessionKey = "login_web_" . sha1("Illuminate\Auth\SessionGuard");
        $userId = $request->session()->get($sessionKey);

        if (is_null($userId)) {
            return Redirect::to("login");
        }

        // 防止 session cookie 被多 IP 滥用
        if ($this->checkSessionIpLimit($request)) {
            // 触发 IP 限制，直接踢出，不查数据库
            return Redirect::to("login");
        }

        // 验证通过后才触发数据库查询
        if (auth()->guest()) {
            return Redirect::to("login");
        }

        return $next($request);
    }

    /**
     * 检查同一 session 被多少个不同 IP 使用
     * 10分钟内超过5个不同IP则踢出登录
     *
     * @return bool true 表示触发限制已踢出
     */
    private function checkSessionIpLimit($request)
    {
        $sid = $request->session()->getId();
        $ip = getClientIP();
        $key = "session_ips:" . $sid;

        $added = Redis::sadd($key, $ip);

        if ($added === 1) {
            // 新 IP 加入
            $count = Redis::scard($key);

            if ($count > 5) {
                Redis::del($key);
                // 只清 session 数据，不调 auth()->logout() 避免触发数据库查询
                $request->session()->flush();
                return true;
            }

            // 每次有新 IP 都刷新 10 分钟 TTL
            Redis::expire($key, 600);
        }
        // 返回值 0 = 旧 IP，不做任何处理
        return false;
    }
}
