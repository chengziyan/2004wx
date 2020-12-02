<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\User;
use App\Model\Media;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;
use Log;

class WXController extends Controller
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
            echo $_GET['echostr'];
        }else{
            echo "111";
        }
    }


    public function wxEvent()
    {
        $xml_str = file_get_contents("php://input");
        //记录日志
        file_put_contents('wx_event.log', $xml_str);
        $data = simplexml_load_string($xml_str, "SimpleXMLElement", LIBXML_NOCDATA);
        $msgType = $data->MsgType;
        switch ($msgType) {
            case 'event':
                if ($data->Event == "subscribe") {
                    $openid = $data->FromUserName;
                    $access_token = $this->getAccessToken();
                    $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $access_token . "&openid=" . "$openid" . "&lang=zh_CN";
                    $user = json_decode($this->http_get($url), true);
                    if (isset($user['errcode'])) {
                        file_put_contents('wx_event.log', $user['errcode']);
                    } else {
                        $user_id = User::where('openid', $openid)->first();
                        if ($user_id) {
                            $user_id->subscribe = 1;
                            $user_id->save();
                            $Contentt = "欢迎回来！";
                        } else {
                            $post = new User();
                            $datas = [
                                "subscribe" => $user["subscribe"],
                                "openid" => $user["openid"],
                                "nickname" => $user["nickname"],
                                "sex" => $user["sex"],
                                "city" => $user["city"],
                                "country" => $user["country"],
                                "province" => $user["province"],
                                "language" => $user["language"],
                                "headimgurl" => $user["headimgurl"],
                                "subscribe_time" => $user["subscribe_time"],
                                "subscribe_scene" => $user["subscribe_scene"],
                            ];
                            $name = $post->insert($datas);
                            $Contentt = "谢谢关注";
                        }
                        if ($data->Event == "unsubscribe") {
                            User::where("openid", $user['openid'])->update(["subscribe" => 0]);
                            $Contentt = "取关成功";
                        }
                        echo $this->getMsg($data, $Contentt);
                        break;
                    }
                }
            case 'text':
                    $content = urlencode($data->Content);
                    $appkey = '5eed9b01db7f654c50efce3e7e97ed55';
                    $url = "http://api.tianapi.com/txapi/pinyin/index?key=".$appkey."&text=".$content;
                    $response = json_decode(file_get_contents($url),true);
                    $content = "";
                    if($response['code']==200){
                        $content .= "查询的拼音：" . $response;
                    }else {
                        echo "返回错误，状态消息：" . $response['msg'];
                    }
                echo $this->getMsg($data,$content);
                break;
        }

    }

    public function getMsg($data,$Content){
        $ToUserName = $data->FromUserName;
        $FromUserName = $data->ToUserName;
        $CreateTime = time();
        $MsgType = "text";

        $xml = "<xml>
                  <ToUserName><![CDATA[%s]]></ToUserName>
                  <FromUserName><![CDATA[%s]]></FromUserName>
                  <CreateTime>%s</CreateTime>
                  <MsgType><![CDATA[%s]]></MsgType>
                  <Content><![CDATA[%s]]></Content>
                </xml>";
        echo sprintf($xml,$ToUserName,$FromUserName,$CreateTime,$MsgType,$Content);
    }

//    public function getMenu(){
//        $access_token = $this->getAccessToken();
//        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;
//        $menu = [
//
//            'button' => [
//
//                [
//                    'type' => 'click',
//                    'name' => '签到',
//                    'key'  => 'V1001_TODAY_QQ'
//                ],
//                [
//                    'name'=>'商城',
//                    'sub_button'=>[
//                        [
//                            'type'=>'view',
//                            'name'=>'京东好货',
//                            'url'=>'http://www.jd.com'
//
//                        ],
//
//                        [
//                            'type' => 'view',
//                            'name' => '商城',
//                            'url'=>'http://2004wyr.comcto.com'
//
//                        ]
//                    ]
//                ],
//                [
//                    'type' => 'view',
//                    'name' => 'BILIBILI',
//                    'url'  => 'http://www.bilibili.com'
//                ],
//            ]
//        ];
//        $data = $this->curl($url,$menu);
//        return $data;
//    }


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
//            $client = new Client();
//            $response = $client->request('GET',$url,['verify'=>false]);
//            $json_str = $response->getBody();

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

    function guzzle2(){
        $access_token = $this->getAccessToken();
        $type = "image";
        $url = 'https://api.weixin.qq.com/cgi-bin/media/upload?access_token='.$access_token.'&type='.$type;
//        echo $url;die;
        $client = new Client();
        $response = $client->request('POST',$url,[
            'verify' => false, //忽略https证书 验证
            'multipart' => [
                [
                    'name' => 'media',
                    'contents' => fopen('asd.jpg','r') //上传的文件路径
                ]
            ]
        ]);
        $data = $response->getBody();
        echo $data;
    }

    function curl($url,$menu){
        //初始化
        $ch = curl_init();
        //设置
        curl_setopt($ch,CURLOPT_URL,$url);//设置提交地址
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);//设置返回值返回字符串
        curl_setopt($ch,CURLOPT_POST,1);//post提交方式
        curl_setopt($ch,CURLOPT_POSTFIELDS,$menu);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);


        //执行
        $output = curl_exec($ch);
        //关闭
        curl_close($ch);
        return $output;
    }


}
