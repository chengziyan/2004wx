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

Route::get('/test1','CController@test');

Route::post('/index','WXController@index');
Route::get('/token','WXController@getAccessToken');
Route::get('/guzzle2','WXController@guzzle2');

Route::get('/guzzle1','TestController@guzzle1');


