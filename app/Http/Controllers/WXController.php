<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Model\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Redis;

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
            $xml_str=file_get_contents("php://input");
            //Log::info($xml_str);
            $data = simplexml_load_string($xml_str,"SimpleXMLElement",LIBXML_NOCDATA);
            //用户扫码的openid
            $openid = $data->FromUserName;
            $access_token = $this->getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid="."$openid"."&lang=zh_CN";
            $user = json_decode($this->http_get($url),true);
            if(isset($user['errcode'])){
                file_put_contents('log.txt',$user['errcode']);
            }else{
                if($data->Event == "subscribe"){
                        $first = User::where("openid",$user['openid'])->first();
                    if($first){
                        $datas =[
                            "subscribe"=>1,
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
                        User::where("openid",$user['openid'])->update($datas);
                        $Content = "欢迎回来";
                    }else {
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
                        $Content = "谢谢关注";
                    }
                }else{
                    User::where("openid",$user['openid'])->update(["subscribe"=>0]);
                    $Content = "取关成功";
                }
            }
        }
        echo $this->getMsg($data,$Content);
        echo $this->getMenu();
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
        $menu = ' {
             "button":[
             {	
                  "type":"click",
                  "name":"天气",
                  "key":"WX_weather"
              },
              {
                   "name":"菜单",
                   "sub_button":[
                   {	
                       "type":"view",
                       "name":"搜索",
                       "url":"http://www.soso.com/"
                    },
                    {
                       "type":"click",
                       "name":"赞一下我们",
                       "key":"V1001_GOOD"
                    }]
               }]
         }';
//        $client = new Client();
//        $resopnse = $client->request('POST',$url,[
//            'verify'=>false,
//            'body'=>json_encode($menu)
//        ]);
//        $data = $resopnse->getBody();
        $resopnse = file_get_contents($url);
        $data = json_encode($resopnse);
        return $data;
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

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * 上传素材
     */
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
