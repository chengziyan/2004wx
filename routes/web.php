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
    Route::get('/token','WXController@getAccessToken');
    Route::get('/guzzle2','WXController@guzzle2');
    Route::get('/getMenu','WXController@getMenu');
});

Route::get('/guzzle1','TestController@guzzle1');

Route::prefix('xcx')->group(function (){
    Route::get('/test','Xcx\ApiController@test');   //小程序测试
    Route::post('/login','Xcx\ApiController@userLogin');   //小程序个人中心登录
    Route::get('homelogin','Xcx\ApiController@homeLogin');  //小程序首页登录
    Route::get('/goodslist','Xcx\ApiController@goodsList'); //小程序商品列表
    Route::get('/detail','Xcx\ApiController@detail'); //小程序商品列表
    Route::get('addFav','Xcx\ApiController@addFav');    //商品收藏
    Route::get('addcart','Xcx\ApiController@addCart');    //加入购物车
    Route::get('cartlist','Xcx\ApiController@cartList');    //购物车列表
    Route::get('del-cart','Xcx\ApiController@delCart');    //删除购物车列表商品

});




