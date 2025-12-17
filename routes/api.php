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

    // API v2 节点管理
    Route::group(['prefix' => 'v2', 'namespace' => 'V2'], function () {
        // 获取可用节点ID
        Route::get('node/available', 'NodeController@getAvailableNodeId');
        // 节点配置信息上报
        Route::post('node/{id}/config', 'NodeController@reportConfig');
        // 节点状态信息上报
        Route::post('node/{id}/status', 'NodeController@reportStatus');
    });
});
