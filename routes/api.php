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
});
