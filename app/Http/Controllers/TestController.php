<?php

namespace App\Http\Controllers;

//use App\Http\Middleware\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Model\User;

class TestController extends Controller
{
    public function test(){
        echo __METHOD__;
    }
    public function index(){
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = "wechat";
        $tmpArr = array($token,$timestamp,$nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            $xml_str=file_get_contents("php://input");
            //Log::info($xml_str);
            $data = simplexml_load_string($xml_str,"SimpleXMLElement",LIBXML_NOCDATA);
            //用户扫码的openid
            $openid = $data->FromUserName;
            $access_token = $this->getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token=".$access_token."&openid="."$openid"."&lang=zh_CN";
            $user = json_encode($this->http_get($url),true);
            if(isset($user['errcode'])){
                file_put_contents('log.txt',$user['errcode']);
            }else{
                if($data->Event == "subscribe"){
                    $post = new User();
                    $data =[
                        "subscribe"=>$user["subscribe"],
                        "openid"=>$user["openid"],
                        "nickname"=>$user["nickname"],
                        "sex"=>$user["sex"],
                        "city"=>$user["city"],
                        "country"=>$user["country"],
                        "province"=>$user["province"],
                        "language"=>$user["language"],
                        "headimgurl"=>$user["headimgurl"],
                        "subscribe_time"=>$user["subscribe_time"],
                        "subscribe_scene"=>$user["subscribe_scene"],
                    ];
                    $name =  $post->insert($data);
                    $Content = "谢谢关注";
                }
            }
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
        return $token;
    }
    function http_get($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);//向那个url地址上面发送
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);//设置发送http请求时需不需要证书
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//设置发送成功后要不要输出1 不输出，0输出
        $output = curl_exec($ch);//执行
        curl_close($ch);    //关闭
        return $output;
    }


}
