<?php

/**
 * v2 后端节点 API 路由(机器对机器: xray-plugin-api 节点插件 ↔ 面板)
 *
 * 独立文件、不经 RouteServiceProvider 的 'api' 中间件组挂载:
 *   'api' 组含 throttle:60,1 且 ThrottleRequests 按 $request->ip() 计数 ——
 *   同一出口 IP 的全部节点共享一个 60 req/min 桶(20 节点 × 3 请求/轮 ≈ 贴脸
 *   触顶, 429 又被插件按 RATE_LIMITED 退避重试, 恶性循环)。
 *
 * 但并非零限流: backend.ratelimit 提供基于 Redis 的按 IP 应用层限流(60 次/分钟),
 *   挂在 backend.token 之前 —— 被拦截的伪造 Bearer 请求不再触发 ss_node.api_token
 *   的 DB 查询。节点上报周期约 1 小时一轮, 合法流量远低于阈值, 不会误伤。
 *   【既定设计备注】阈值口径基于 "一个节点一个 id、一台机器一个独立出口 IP" 的正常
 *   部署形态(单节点仅 3 请求/轮, 远低于 60 次/分钟); 单 IP 聚合数十节点的共享出口
 *   IP 形态非预期部署, 真出现时再调高 BackendRateLimit::LIMIT 即可。
 *
 * 契约: xray-plugin-api docs/openapi.yaml; 每节点独立 Bearer token(ss_node.api_token)
 * 不挂 session/CSRF, 不与旧 node 组共用 env('API_TOKEN') 全局 key
 */

Route::group(['prefix' => 'v2/backend', 'namespace' => 'Api\V2\Backend', 'middleware' => ['backend.ratelimit', 'backend.token']], function () {
    Route::get('users', 'NodeController@users');
    Route::post('traffic', 'NodeController@traffic');
    Route::post('status', 'NodeController@status');
});

// 健康检查不鉴权(给 nginx/监控探活; 也不吃 throttle —— 探活高频是常态)
Route::get('v2/backend/healthz', 'Api\V2\Backend\NodeController@healthz');
