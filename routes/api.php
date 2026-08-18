<?php

Route::group(['namespace' => 'Api'], function () {
    Route::resource('yzy', 'YzyController');
    Route::resource('trimepay', 'TrimepayController');
    Route::resource('alipay', 'AlipayController');
    Route::resource('f2fpay', 'F2fpayController');

    // 定制客户端
    Route::any('login', 'LoginController@login');

    // PING检测
    Route::get('ping', 'PingController@ping');

    //song SSN API
    Route::post('ssn_sub/{id}', 'PingController@ssn_sub');
    Route::post('ssn_v2/{id}', 'PingController@ssn_v2');
    // sdo2022-04-13 clonepay api
    Route::post('clonepay', 'PingController@clonepay');
    Route::post('simple_api_tools', 'PingController@simpleApiTools'); //sdo 2024-11-12
    Route::get('node_config', 'PingController@getNodeConfig');
    Route::get('nodes', 'NodeApiController@listAll');
    Route::get('node/new', 'PingController@getNewNode'); // Get a new available node

    // New Node API for thin node, fat panel architecture
    Route::group(['prefix' => 'node', 'middleware' => 'node.api.token'], function () {
        Route::post('apply_id', 'NodeApiController@applyId');
        Route::post('register', 'NodeApiController@register');
        Route::post('resolve_dns', 'NodeApiController@resolveDns');
        Route::post('config', 'NodeApiController@config');
        Route::post('nginx_config', 'NodeApiController@nginxConfig');
        Route::post('status', 'NodeApiController@status');
        Route::post('unlock_check', 'NodeApiController@unlockCheck');
    });

    // v2 后端节点 API(机器对机器: xray-plugin-api 节点插件 ↔ 面板)
    // 契约: xray-plugin-api docs/openapi.yaml; 每节点独立 Bearer token(ss_node.api_token)
    // 不与旧 node 组共用 env('API_TOKEN') 全局 key; 不挂 session/CSRF
    Route::group(['prefix' => 'v2/backend', 'middleware' => 'backend.token'], function () {
        Route::get('users', 'V2\Backend\NodeController@users');
        Route::post('traffic', 'V2\Backend\NodeController@traffic');
        Route::post('status', 'V2\Backend\NodeController@status');
    });
});

// 健康检查不鉴权(给 nginx/监控探活)
Route::get('v2/backend/healthz', 'Api\V2\Backend\NodeController@healthz');
