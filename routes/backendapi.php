<?php

/**
 * v2 后端节点 API 路由(机器对机器: xray-plugin-api 节点插件 ↔ 面板)
 *
 * 独立文件、不经 RouteServiceProvider 的 'api' 中间件组挂载:
 *   'api' 组含 throttle:60,1 且 ThrottleRequests 按 $request->ip() 计数 ——
 *   同一出口 IP 的全部节点共享一个 60 req/min 桶(20 节点 × 3 请求/轮 ≈ 贴脸
 *   触顶, 429 又被插件按 RATE_LIMITED 退避重试, 恶性循环)。本组自带
 *   backend.token 强鉴权, 限流交给鉴权失败率与 nginx 层, 不吃 IP 限流。
 *
 * 契约: xray-plugin-api docs/openapi.yaml; 每节点独立 Bearer token(ss_node.api_token)
 * 不挂 session/CSRF, 不与旧 node 组共用 env('API_TOKEN') 全局 key
 */

Route::group(['prefix' => 'v2/backend', 'namespace' => 'Api\V2\Backend', 'middleware' => 'backend.token'], function () {
    Route::get('users', 'NodeController@users');
    Route::post('traffic', 'NodeController@traffic');
    Route::post('status', 'NodeController@status');
});

// 健康检查不鉴权(给 nginx/监控探活; 也不吃 throttle —— 探活高频是常态)
Route::get('v2/backend/healthz', 'Api\V2\Backend\NodeController@healthz');
