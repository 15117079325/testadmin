<?php

namespace App\Http\Controllers\Api;

use App\Events\SingleLoginEvent;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use ShaoZeMing\GeTui\Facade\GeTui;

class LoginController extends Controller
{


    /**
     * description:普通登录
     * @author Harcourt
     * @date 2018/7/20
     */
    public function accountLogin(Request $request)
    {
        $account = $request->input('account');
        $pass = $request->input('pass');
        $type = $request->input('type', '0');//1、手机号验证码登录2、用户名密码登录
        $clientid = $request->input('clientid');
        $device = $request->input('device');

        if (empty($account) || empty($pass) || !in_array($type, array('1', '2')) || empty($clientid) || empty($device) || !in_array($device,['ios','android'])) {
            return error('00000', '请求参数不全');
        }
        $now = time();
        if ($type == '1') {
            $where = [
                ['veri_mobile', '=', $account],
                ['veri_type', '=', '2']
            ];

            $msgData = DB::table('verify_num')->where($where)->first();

            if (empty($msgData) || $msgData->veri_gmt_expire <= $now || strcmp($msgData->veri_number, $pass) !== 0) {
                return error('20001', '验证码或者手机号不正确');
            }

            $user = DB::table('users')->select('users.user_id', 'new_status', 'user_status', 'device', 'clientid','nickname', 'token', 'chat_token', 'headimg', 'mobile_phone','user_cx_rank')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('mobile_phone', $account)->first();

            if(empty($user)) {
                return error('10001','用户不存在');
            }

            if ($user->user_status == '2') {
                return error('10001', '该账号已被禁用');
            }

        } else {
            $where = [
                ['user_name', $account],
            ];
            $user = DB::table('users')->select('users.user_id', 'new_status', 'user_status', 'device', 'clientid', 'nickname','token', 'chat_token','headimg', 'mobile_phone','password','ec_salt','user_cx_rank')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where($where)->first();


            if (empty($user)) {
                return error('10001', '用户不存在');

            }
            if ($user->user_status == '2') {
                return error('10001', '该账号已被禁用');

            }
            if( $user->ec_salt){
                $pass = md5($pass.$user->ec_salt);
            }
            if(strcmp($pass,$user->password) !== 0){
                return error('20002', '用户名或者密码错误');
            }


        }

        if($user && empty($user->nickname)){
            $nickname = '创新美'.rand(10000,99999);
            DB::table('users')->where('user_id',$user->user_id)->update(['nickname'=>$nickname]);
        }
        //发送通知
        event(new SingleLoginEvent($user,$clientid));

        $update_data = array(
            'device' => $device,
            'clientid' => $clientid,
            'last_time' => $now,
            'expire_time' => $now + LOGIN_EXPIRE_TIME
        );
        if (empty($user->token)) {
            $token = get_token($user->user_id);
            $update_data['token'] = $token;
            $user->token = $token;
        }
        if (empty($user->chat_token)) {
            //获取融云token

            $serverapi = new \ServerAPI();

            if(empty($user->headimg)){
                $user->headimg = strpos_domain('headimg/default.png');
                $update_data['headimg'] = $user->headimg;
            }

            $rong_chat = $serverapi->getToken($user->user_id, $user->mobile_phone, $user->headimg);

            $rong_chat = json_decode($rong_chat);

            if ($rong_chat->code == 200) {
                $chat_token = $rong_chat->token;
                $update_data['chat_token'] = $chat_token;
                $user->chat_token = $chat_token;
            }
        }
        DB::table('users')->where('user_id', $user->user_id)->update($update_data);
        $data = [
            'user_id'=>$user->user_id,
            'token'=>$user->token,
            'chat_token'=>$user->chat_token,
            'mobile'=>$user->mobile_phone,
            'roleRank'=>$user->user_cx_rank

        ];
        success($data, '登录成功');
    }

    /**
     * description:授权登录
     * @author Harcourt
     * @date 2018/7/20
     */
    public function authorizeLogin(Request $request)
    {
        $type = $request->input('type', '0');//1、微信登录2、qq登录

        $openid = $request->input('openid');
        $headimg = $request->input('headimg');
        $nickname = $request->input('nickname');

        $clientid = $request->input('clientid');

        $device = $request->input('device');



        if (empty($openid) || empty($headimg) || empty($nickname) || !in_array($type, array('1', '2')) || empty($clientid) || empty($device) ||!in_array($device,['ios','android'])) {
            return error('00000', '请求参数不全');
        }
        $now = time();
        $user = DB::table('users')->select('users.user_id', 'new_status', 'user_status', 'device', 'clientid', 'token', 'chat_token','nickname', 'headimg', 'mobile_phone','user_cx_rank')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('wxopenid', $openid)->orWhere('qqopenid', $openid)->first();
        if (empty($user)) {
            return error('20003', '请先完善账户信息');
        }
        if ($user && $user->user_status == '2') {
            return error('10001', '该账号已被禁用');
        }



        //发送通知
        event(new SingleLoginEvent($user,$clientid));

        $update_data = array(
            'device' => $device,
            'clientid' => $clientid,
            'last_time' => $now,
            'expire_time' => $now + LOGIN_EXPIRE_TIME
        );
        if (empty($user->token)) {
            $user->token = get_token($user->user_id);

            $update_data['token'] = $user->token;
        }
        if(empty($user->nickname)){
            $nickname = '创新美'.rand(10000,99999);
            $user->nickname = $nickname;
            $update_data['nickname'] = $nickname;
        }
        if (empty($user->chat_token)) {
            //获取融云token

            $serverapi = new \ServerAPI();

            if(empty($user->headimg)){
                $path = 'headimg/default.png';
                $user->headimg = strpos_domain($path);
                $update_data['headimg'] = $user->headimg;
            }

            $rong_chat = $serverapi->getToken($user->user_id, $user->nickname, $user->headimg);

            $rong_chat = json_decode($rong_chat);

            if ($rong_chat->code == 200) {
                $chat_token = $rong_chat->token;
                $update_data['chat_token'] = $chat_token;
                $user->chat_token = $chat_token;
            }
        }

        DB::table('users')->where('user_id', $user->user_id)->update($update_data);
        $data = [
            'user_id' => $user->user_id,
            'token' => $user->token,
            'chat_token' => $user->chat_token,
            'mobile'=>$user->mobile_phone,
            'roleRank'=>$user->user_cx_rank


        ];
        success($data, '登录成功');
    }
    /**
     * description:找回密码
     * @author Harcourt
     * @date 2018/8/1
     */
    public function resetPassword(Request $request)
    {
        $mobile = $request->input('mobile',0);
        $msg = $request->input('msg');
        $password = $request->input('password');
        $repassword = $request->input('repassword');
        if(empty($mobile) || empty($msg) || empty($password) || empty($repassword)){
            return error('00000', '参数不全');
        }
        if(strcmp($password,$repassword) !== 0){
            return error('40006','两次密码不一致');
        }
        $user = DB::table('users')->select('user_id','ec_salt')->where('mobile_phone',$mobile)->first();
        if(empty($user)){
            return error('10001','用户不存在');
        }
        $where = [
            ['veri_mobile',$mobile],
            ['veri_number',$msg],
            ['veri_type',5]
        ];
        $verify = DB::table('verify_num')->where($where)->first();
        if(empty($verify) || $verify->veri_gmt_expire <= time()){
            return error('20001', '验证码或者手机号不正确');
        }

        if($user->ec_salt){
            $password = md5($password.$user->ec_salt);
        }
        $aff_row = DB::table('users')->where('user_id',$user->user_id)->update(['password'=>$password]);
        success();
    }

}
