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

    private function index(){
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = "wechat";
        $tmpArr = array($token,$timestamp,$nonce);
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);

        if ($tmpStr == $signature) {
            return true;
        }else{
            return false;
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
                if($data->Content=="天气"){
                    $content = "请输入您想查询的城市的天气，比如'北京'";
                }else {
                    $city = urlencode($data->Content);
                    $key = "082bb9f8a7d308862337d2976f6dd414";
                    $url = "http://apis.juhe.cn/simpleWeather/query?city=" . $city . "&key=" . $key;
                    $weather = json_decode($this->http_get($url), true);
                    $content = "";
                    if ($weather['error_code'] == 0) {
                        $today = $weather['result']['realtime'];
                        $content .= "查询天气的城市:" . $weather['result']['city'] . "\n";
                        $content .= "天气详细情况" . $today['info'] . "\n";
                        $content .= "温度" . $today['temperature'] . "\n";
                        $content .= "湿度" . $today['humidity'] . "\n";
                        $content .= "风向" . $today['direct'] . "\n";
                        $content .= "风力" . $today['power'] . "\n";
                        $content .= "空气质量指数" . $today['aqi'] . "\n";
                    }
                }
                file_put_contents("weacher.log",$xml_str);
                echo $this->getMsg($data,$content);
                break;
        }
        echo $this->getMenu();
        if($msgType == "image"){
            $datas = [
                "tousername"=>$data->ToUserName,
                "fromusername"=>$data->FromUserName,
                "createtime"=>$data->CreateTime,
                "msgtype"=>$data->MsgType,
                "picurl"=>$data->PicUrl,
                "msgid" =>$data->MsgId,
                "mediaid"=>$data->MediaId,
            ];
            $image = new Media();
            $images = Media::where('picurl',$datas['picurl'])->first();
            if(!$images){
                $images=$image->insert($datas);
            }
            $token = $this->getAccessToken();
            $media = $data->MediaId;
            $url = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=".$token."&media_id=".$media;
            $url = file_get_contents($url);
            file_put_contents("image.jpg",$url);
            $Content = "图片";
            echo $this->getMsg($data,$Content);
        }else if ($msgType == "voice") {
            $access_token = $this->getAccessToken();
            Log::info("====语音====" . $access_token);
            $url = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=" . $access_token . "&media_id=" . $data->MediaId;
            $get = file_get_contents($url);
            file_put_contents("voice.amr", $get);
            $Content = "语音";
            $this->getMsg($data, $Content);
        } else if ($msgType == "video") {
            $access_token = $this->getAccessToken();
            Log::info("====视频====" . $access_token);
            $url = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=" . $access_token . "&media_id=" . $data->MediaId;
            $get = file_get_contents($url);
            file_put_contents("video.mp4", $get);
            $Content = "视频";
            $this->getMsg($data, $Content);
        } else if ($msgType == "CLICK") {
            if ($data->EventKey == "V1001_TODAY_QQ") {
                $key = "qiandao";
                $openid = (string)$data->FromUserName;
                //sismember 命令判断成员元素是否是集合的成员。
                $slsMember = Redis::sismember($key, $openid);
                //是成员元素  返回 1  已签到
                if ($slsMember == "1") {
                    $Content = "已签到过了哦！";
                    $this->getMsg($data, $Content);
                } else {
                    $Content = "签到成功";
                    Redis::sAdd($key, $openid);
                    $this->getMsg($data, $Content);

                }
//                Log::info("=====slemenber=======".$slsMember);
            }
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

    public function getMenu(){
        $access_token = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;
        $menu = [

            'button' => [

                [
                    'type' => 'click',
                    'name' => '签到',
                    'key'  => 'V1001_TODAY_QQ'
                ],
                [
                    'name'=>'商城',
                    'sub_button'=>[
                        [
                            'type'=>'view',
                            'name'=>'京东好货',
                            'url'=>'http://www.jd.com'

                        ],

                        [
                            'type' => 'view',
                            'name' => '商城',
                            'url'=>'http://2004wyr.comcto.com'

                        ]
                    ]
                ],
                [
                    'type' => 'view',
                    'name' => 'BILIBILI',
                    'url'  => 'http://www.bilibili.com'
                ],
            ]
        ];
//        $client = new Client();
//        $resopnse = $client->request('POST',$url,[
//            'verify'=>false,
//            'body'=>json_encode($menu,JSON_UNESCAPED_UNICODE)
//        ]);
//        $data = $resopnse->getBody();
        $response = file_get_contents($url);
        $data = json_decode($response,true);
        return $data;
    }


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
}
