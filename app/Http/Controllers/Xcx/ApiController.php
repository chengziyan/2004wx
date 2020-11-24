<?php

namespace App\Http\Controllers\Xcx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Model\User;
use App\Model\Goods;

class ApiController extends Controller
{
    //
    public function test(){
        $goodsInfo = [
            'goods_id' => '12345',
            'goods_name' => 'Android',
        ];
        return json_encode($goodsInfo);
    }

    public function login(Request $request){
        //接收code
        $code = Request()->get('code');

        //获取用户信息
        $userinfo=json_decode(Request()->get('u'),true);
//        dd($userinfo);

        //使用code
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.env('WX_XCX_APPID').'&secret='.env('WX_XCX_APPSEC').'&js_code='.$code.'&grant_type=authorization_code';

        $data = json_decode(file_get_contents($url),true);

        //自定义登录状态
        if(isset($data['errcode'])){
            //错误
            $response = [
                'errno' => '50001',
                'msg' => '登录失败',
            ];
        }else{  //成功
            $openid = $data['openid'];
            $u = User::where(['openid'=>$openid])->first();
            //判断新老用户
            if($u){
                //TODO 老用户
            }else{
                //新用户入库
                $u_info = [
                    'openid'=>$openid,
                    'nickname'=>$userinfo['nickName'],
                    'sex'=>$userinfo['gender'],
                    'language'=>$userinfo['language'],
                    'city'=>$userinfo['city'],
                    'province'=>$userinfo['province'],
                    'country'=>$userinfo['country'],
                    'headimgurl'=>$userinfo['avatarUrl'],
                    'add_time'=>time(),
                    'type'=>3
                ];
                User::insertGetId($u_info);
            }

            //生成token
            $token = sha1($data['openid'].$data['session_key'].mt_rand(0,99999));
            //保存token
            $redis_xcx_key = 'xcx_token'.$token;
            Redis::set($redis_xcx_key,time());
            Redis::expire($redis_xcx_key,3600);
            $response = [
                'errno' => '0',
                'msg' => 'ok',
                'data' => [
                    'token'=> $token
                ]
            ];
        }
        return $response;
    }

    public function goodsList(Request $request){
//        $goods = Goods::select('goods_id','goods_name','shop_price','goods_img')->limit(10)->get()->toArray();
        $page_size = $request->get('ps');
        $goods = Goods::select('goods_id','goods_name','shop_price','goods_img')->paginate($page_size);
        $response = [
            'errno'=>0,
            'msg '=>'ok',
            'data'=>[
                'goods'=>$goods->items()
            ]
        ];
        return $response;
    }

    public function  detail(Request $reques){
        $goods_id = Request()->goods_id;
        $gs = Goods::select('goods_id','goods_name','shop_price','goods_image')->where('goods_id',$goods_id)->first()->toArray();
//        dd($gs);
        $response=[
            'goods_id'=>$gs['goods_id'],
            'goods_name'=>$gs['goods_name'],
            'shop_price'=>$gs['shop_price'],
            'goods_image'=>explode('|',$gs['goods_image'])
        ];
//        dd($response);
        return $response;
    }

    public function cart(Request $request){
        $goods_id = Request()->goods_id;
        dd($goods_id);
    }
}
