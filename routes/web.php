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


Route::get('/test1','TestController@test');
Route::get('/test2','TestController@test2');
Route::get('/indexs','TestController@index');