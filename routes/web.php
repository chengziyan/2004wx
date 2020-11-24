<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
		// phpinfo();
    return view('welcome');
});

Route::get('/test1','WXController@test');

Route::prefix('/index')->group(function (){
    Route::get('/','WXController@index');
    Route::post('/','WXController@wxEvent');
    Route::any('/','WXController@quote');
    Route::get('/token','WXController@getAccessToken');
    Route::get('/guzzle2','WXController@guzzle2');
    Route::get('/getMenu','WXController@getMenu');
});

Route::get('/guzzle1','TestController@guzzle1');

Route::prefix('xcx')->group(function (){
    Route::get('/test','Xcx\ApiController@test');   //小程序测试
    Route::any('/login','Xcx\ApiController@login');   //小程序登录
    Route::get('/goodslist','Xcx\ApiController@goodsList'); //小程序商品列表
    Route::get('/detail','Xcx\ApiController@detail'); //小程序商品列表
    Route::get('/cart','Xcx\ApiController@cart'); //小程序商品购物车

});


