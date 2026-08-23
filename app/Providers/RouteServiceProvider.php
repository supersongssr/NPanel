<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapBackendApiRoutes();

        $this->mapWebRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }

    /**
     * v2 后端节点 API(xray-plugin-api 插件 ↔ 面板, 机器对机器)。
     *
     * 刻意不挂 'api' 组的 throttle:60,1 —— ThrottleRequests 无登录态时按
     * $request->ip() 计数, 同一出口 IP 的全部节点共享 60 req/min 桶,
     * 20 节点 × 每轮 3 请求(默认 60s 周期)即 60/min 触顶, 429 会被插件当
     * RATE_LIMITED 退避重试形成恶性循环。鉴权由 backend.token 承担。
     *
     * @return void
     */
    protected function mapBackendApiRoutes()
    {
        Route::prefix('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/backendapi.php'));
    }
}
