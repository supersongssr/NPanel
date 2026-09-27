<?php

/**
 * v2 后端节点 API 路由(机器对机器: xray-plugin-api 节点插件 ↔ 面板)
 *
 * 独立文件、不经 RouteServiceProvider 的 'api' 中间件组挂载:
 *   'api' 组含 throttle:60,1 且 ThrottleRequests 按 $request->ip() 计数 ——
 *   同一出口 IP 的全部节点共享一个 60 req/min 桶(20 节点 × 3 请求/轮 ≈ 贴脸
 *   触顶, 429 又被插件按 RATE_LIMITED 退避重试, 恶性循环)。
 *
 * 但并非零限流: backend.ratelimit 提供基于 Redis 的按 REMOTE_ADDR 应用层限流
 *   (默认 60 次/分钟, BACKEND_API_RATE_LIMIT 可配), 挂在 backend.token 之前 ——
 *   被拦截的伪造 Bearer 请求不再触发 ss_node.api_token 的 DB 查询。
 *   分桶用 REMOTE_ADDR 而非 $request->ip(): TrustProxies='*' 下后者返回客户端
 *   自带 XFF 里的伪造值, 换个假 IP 就能换新桶; 入口 nginx realip(只取 XFF
 *   最右侧)已把真实来源放进 REMOTE_ADDR, 伪造头换不了桶(审查 f-ad403d09)。
 *   【既定设计备注】阈值口径基于 "一个节点一个 id、一台机器一个独立出口 IP" 的
 *   部署前提(单节点每轮 3 请求, 即使分钟级上报周期也远低于阈值); 多节点共享
 *   单一出口 IP 的形态在当前部署中不存在, 真出现时调高 BACKEND_API_RATE_LIMIT
 *   即可(config/backendapi.php), 无需改代码。
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
