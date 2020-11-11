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

Route::prifix('/index')->group(function (){
    Route::get('/','WXController@index');
    Route::post('/','WXController@wxEvent');
    Route::get('/token','WXController@getAccessToken');
    Route::get('/guzzle2','WXController@guzzle2');
    Route::get('/getMenu','WXController@getMenu');
});

Route::get('/guzzle1','TestController@guzzle1');


