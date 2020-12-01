<?php

namespace App\Http\Controllers\Xcx;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Model\User;
use App\Model\Goods;
use App\Model\Cart;
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

    /**
     * @param Request $request
     * @return array
     * 小程序首页登录
     */
    public function homeLogin(Request $request){
        //接收code
        $code = Request()->get('code');
//        dd($code);
        //使用code
        $url = 'https://api.weixin.qq.com/sns/jscode2session?appid='.env('WX_XCX_APPID').'&secret='.env('WX_XCX_APPSEC').'&js_code='.$code.'&grant_type=authorization_code';

        $data = json_decode(file_get_contents($url),true);
//        dd($data);

        //自定义登录状态
        if(isset($data['errcode'])){
            //错误
            $response = [
                'errno' => '50001',
                'msg' => '登录失败',
            ];
        }else{  //成功
            $openid = $data['openid'];          //用户OpenID
            //判断新用户 老用户
            $u = User::where(['openid' => $openid])->first();
            if ($u) {
                // TODO 老用户
                $uid = $u->user_id;
            } else {
                // TODO 新用户
                $u_info = [
                    'openid' => $openid,
                    'add_time' => time(),
                    'type' => 3        //小程序
                ];

                $uid = User::insertGetId($u_info);
            }

            //生成token
            $token = sha1($data['openid'].$data['session_key'].mt_rand(0,99999));
            //保存token
            $redis_xcx_key = 'xcx_token:'.$token;
            Redis::set($redis_xcx_key,time());
            $login_info = [
                'uid' => $uid,
                'user_name' => "",
                'login_time' => date('Y-m-d H:i:s'),
                'login_ip' => $request->getClientIp(),
                'token' => $token,
                'openid'    => $openid
            ];
            //保存登录信息
            Redis::hMset($redis_xcx_key, $login_info);
            //设置过期时间
            Redis::expire($redis_xcx_key,7200);
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

    /**
     * @param Request $request
     * @return array
     * 小程序个人中心登录(有报错)
     */
    public function userLogin(Request $request){
        $token = $request->get('token');

        //获取用户信息
        $userinfo = json_decode(Request()->get('u'), true);
//        dd($userinfo);

        $redis_xcx_key = 'xcx_token:' . $token;
        $openid = Redis::hget($redis_xcx_key, 'openid'); //用户OpenID

        $u = User::where(['openid' => $openid])->first();
        dd($u);
        if($u->update_time == 0){     // 未更新过资料
            //因为用户已经在首页登录过 所以只需更新用户信息表
            $u_info = [
                'nickname' => $userinfo['nickName'],
                'sex' => $userinfo['gender'],
                'language' => $userinfo['language'],
                'city' => $userinfo['city'],
                'province' => $userinfo['province'],
                'country' => $userinfo['country'],
                'headimgurl' => $userinfo['avatarUrl'],
                'update_time'   => time()
            ];
            User::where(['openid' => $openid])->update($u_info);
        }

        $response = [
            'errno' => 0,
            'msg' => 'ok',
        ];

        return $response;
    }

    /**
     * @param Request $request
     * @return array
     * 小程序首页列表
     */
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

    /**
     * @param Request $reques
     * @return array
     * 小程序详情页面
     */
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

    /**
     * 收藏
     */
    public function addFav(Request $request){
        $goodsid = $request->get('id');
        $uid = 2345;
        $redis_key = 'ss:goods:fav:'.$uid;      // 用户收藏的商品有序集合
        Redis::Zadd($redis_key,time(),$goodsid);       //将商品id加入有序集合，并给排序值

        $response = [
            'errno' => 0,
            'msg'   => 'ok'
        ];

        return $response;

    }

    /**
     * 加入购物车
     */
    public function addcart(Request $request){
        $goodsid = $request->get('goodsid');

        $uid = $_SERVER['uid'];

        //查询商品的价格
        $price = Goods::find($goodsid)->shop_price;

        //将商品存储购物车表 或 Redis
        $info = [
            'goods_id'  => $goodsid,
            'uid'       => $uid,
            'goods_num' => 1,
            'add_time'  => time(),
            'cart_price' => $price
        ];

        $id = Cart::insertGetId($info);
        if($id)
        {
            $response = [
                'errno' => 0,
                'msg'   => 'ok'
            ];
        }else{
            $response = [
                'errno' => 50002,
                'msg'   => '加入购物车失败'
            ];
        }

        return $response;
    }

    /**
     * 购物车列表
     */
    public function cartList()
    {
        $uid =Cart::get('u_id')->toArray();
        $uid = $uid[0]["u_id"];
//        dd($uid);
        $goods = Cart::where(['u_id'=>$uid])->get();
//        dd($goods);
        if($goods)      //购物车有商品
        {
            $goods = $goods->toArray();
//            dd($goods);
            foreach($goods as $k=>&$v)
            {
                //根据购物表的goods_id去商品表中查询goods_id 所对应商品数据
                $g = Goods::find($v['goods_id']);
//                dd($g);
                $v['goods_name'] = $g->goods_name;
            }
        }else{          //购物车无商品
            $goods = [];
        }

        $response = [
            'errno' => 0,
            'msg'   => 'ok',
            'data'  => [
                'list'  => $goods
            ]
        ];
        return $response;

    }


    /**
     * 删除购物车商品
     */
    public function delCart(Request $request){
        $goods_id = $request->get('goods');
        $goods_arr =  explode(',',$goods_id);

        $res = Cart::whereIn('goods_id',$goods_arr)->delete();
//        dd($res);
        if($res)        //删除成功
        {
            $response = [
                'errno' => 0,
                'msg'   => 'ok'
            ];
        }else{
            $response = [
                'errno' => 50002,
                'msg'   => '内部错误'
            ];
        }
        return $response;
    }

}
