<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Log;
class TestController extends Controller
{
    public function test(){
        echo __METHOD__;
    }
    public function xml(){
        $res=$this->index();
        echo $res;
    }
    public function index(){
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = "wechat";
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            $xml_str=file_get_contents("php://input");
            Log::info($xml_str);
            $data = simplexml_load_string($xml_str);
            $Content = "谢谢关注";
        }
        $info = $this->getMsg($data,$Content);
    }
    public function getMsg($data,$Content){
        $ToUserName = $data->FromUserName;
        $FromUserName = $data->$ToUserName;
        $CreateTime = time();
        $MsgType = "text";

        $xml = "<xml>
                  <ToUserName><![CDATA[%s]]></ToUserName>
                  <FromUserName><![CDATA[%s]]></FromUserName>
                  <CreateTime>%s</CreateTime>
                  <MsgType><![CDATA[%s]]></MsgType>
                  <Content><![CDATA[%s]]></Content>
                </xml>";
        $info = sprintf($xml,$ToUserName,$FromUserName,$CreateTime,$MsgType,$Content);
        Log::info($info);
        echo $info;
    }

    /**
     * 获取access_token
     */
    public function getAccessToken(){
        $key = 'wx:access_token';
        $token = Redis::get($key);
        //检查是否有token
        if($token){
            echo "有缓存";echo "<br>";
        }else{
            echo "无缓存";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".env('WX_APPID')."&secret=".env('WX_APPSEC');
            $response = file_get_contents($url);

            $data = json_decode($response,true);
            $token = $data['access_token'];

            Redis::set($key,$token);
            Redis::expire($key,3600);
        }
        echo "access_token".$token;
    }

}
