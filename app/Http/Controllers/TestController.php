<?php

namespace App\Http\Controllers;

//use App\Http\Middleware\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Model\User;
use GuzzleHttp\Client;

class TestController extends WXController
{
    function guzzle1(){
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('WX_APPID')."&secret=".env('WX_APPSEC');
        //使用guzzle发送get请求
        $client = new Client();
        $response = $client->request('GET',$url,['verify'=>false]);
        $json_str = $response->getBody();
        echo $json_str;
    }

}
