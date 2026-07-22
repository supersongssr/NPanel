<?php

namespace App\Http\Middleware;

use Closure;
use Redirect;

class isActive
{
    /**
     * 未激活账号（status == 0）强制跳转到激活页
     * 管理员账号放行；激活页与激活接口本身不应挂载此中间件，避免死循环
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = auth()->user();
        if ($user && !$user->is_admin && $user->status == 0) {
            return Redirect::to('activateSelf');
        }

        return $next($request);
    }
}
