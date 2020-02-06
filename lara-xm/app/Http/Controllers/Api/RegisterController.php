<?php

namespace App\Http\Controllers\Api;

use App\Events\SingleLoginEvent;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

//use ShaoZeMing\GeTui\Facade\GeTui;

class RegisterController extends Controller
{


    /**
     * description:注册
     * @author Harcourt
     * @date 2018/7/18
     */
    public function index(Request $request)
    {
        $re_mobile = $request->input('re_mobile');
        $u_mobile = $request->input('u_mobile');
        $msg = $request->input('msg');
        $type = $request->input('type', '0');//1、手机号注册2、微信注册3、qq注册
        $openid = $request->input('openid');
        $headimg = $request->input('headimg');
        $nickname = $request->input('nickname');
        $clientid = $request->input('clientid');
        $device = $request->input('device');
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $def_sign = $distriBution['sign_def']->value;

        if (empty($u_mobile) || !in_array($type, array('1', '2', '3')) || empty($clientid) || empty($device) || empty($msg) || !in_array($device, ['ios', 'android'])) {
            return error('00000', '请求参数不全');
        }

        if ($type != '1' && (empty($openid) || empty($headimg) || empty($nickname))) {
            return error('00000', '请求参数不全');
        }

        $verification = new \Verification();


        if (!$verification->fun_phone($u_mobile)) {
            return error('01000', '请输入合法的手机号码');
        }
        $user = DB::table('users')->where('mobile_phone', $u_mobile)->first();
        $reUser = [];
        if ($re_mobile) {

            $reUser = DB::table('users')->select('users.user_id', 'new_status', 'user_status', 'users.mobile_phone', 'users.user_like')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('invite', $re_mobile)->first();


        }
        $msgData = DB::table('verify_num')->where([['veri_mobile', '=', $u_mobile], ['veri_type', '=', '1']])->first();
//        if (empty($msgData) || strcmp($msg, $msgData->veri_number) !== 0
//            || (time() - $msgData->veri_gmt_expire) >= 0) {
//                return error('10003', '验证码输入错误或已过期');
//        }
        $now = time();
        $rand_name = '火单' . rand(10000, 99999);
        if ($type == '1') {

            if ($user) {
                return error('10002', '用户已存在');
            }

            // if (!$verification->fun_phone($re_mobile)) {
            //     return error('01000', '请输入合法的推荐人手机号码');
            // }

            if (empty($reUser) || $reUser->user_status == '2') {
                return error('10001', '推荐人不存在');
            }
            $path = 'headimg/default.png';
            $insert_data = array(
                'user_name' => $u_mobile,
                'headimg' => $path,
                'mobile_phone' => $u_mobile,
                'last_time' => $now,
                'reg_time' => $now,
                'is_new' => 1,
                'cut_wigth' => $def_sign,
                'device' => $device,
                'nickname' => $rand_name,
                'expire_time' => $now + LOGIN_EXPIRE_TIME
            );

            DB::beginTransaction();

            $insert_id = DB::table('users')->insertGetId($insert_data, 'user_id');

            //关系链入库
            user_like($reUser, $insert_id);

            //用户注册送抽奖次数
            addDrawUser(1, $insert_id, 1);
            //新增记录
            addDrawcont(1, $insert_id);

            //新增记录
            addDrawcont(3, $reUser->user_id);


            if (empty($insert_id)) {
                DB::rollBack();
                return error('99999', '操作失败');
            }

            $account = DB::table('user_account')->where('user_id', $insert_id)->first();
            if (empty($account)) {
                DB::table('user_account')->insert([
                    'user_id' => $insert_id,
                    'create_at' => $now,
                    'update_at' => $now
                ]);
            }
            $token = get_token($insert_id);

            $update_data['token'] = $token;

            //获取融云token

            $serverapi = new \ServerAPI();

            $rong_chat = $serverapi->getToken($insert_id, $rand_name, strpos_domain($path));

            $rong_chat = json_decode($rong_chat);

            if ($rong_chat->code == 200) {
                $chat_token = $rong_chat->token;
                $update_data['chat_token'] = $chat_token;
            } else {
                $chat_token = '';
            }
            $update_data['clientid'] = $clientid;

            DB::table('users')->where('user_id', $insert_id)->update($update_data);
            //生成邀请码
            $invite_code = $this->invite_code();
            $extra_data = array(
                'user_id' => $insert_id,
                'new_status' => $reUser->new_status,
                'invite_user_id' => $reUser->user_id,
                'invite_code' => $reUser->mobile_phone,
                'invite' => $invite_code
            );
            $ex_id = DB::table('mq_users_extra')->insertGetId($extra_data, 'ex_id');


            DB::table('mq_users_limit')->insert(['user_id' => $insert_id]);

            if (empty($ex_id)) {
                DB::rollBack();
                return error('99999', '操作失败');
            } else {
                DB::commit();
            }
            Redis::lpush('newRegisterUser', $insert_id);
            $data = [
                'user_id' => $insert_id,
                'token' => $token,
                'chat_token' => $chat_token,
                'mobile' => $u_mobile,
                'roleRank' => 0
            ];

            success($data, '注册成功');

        } else {
            //第三方注册
            $wxopenid = '';
            $qqopenid = '';
            if ($type == '2') {
                $wxopenid = $openid;
            } else {
                $qqopenid = $openid;
            }

            if (empty($user)) {
                if (empty($reUser) || $reUser->user_status == '2') {
                    return error('10001', '推荐人不存在');
                }
                $insert_data = array(
                    'wxopenid' => $wxopenid,
                    'qqopenid' => $qqopenid,
                    'user_name' => $u_mobile,
                    'headimg' => $headimg,
                    'nickname' => $nickname,
                    'mobile_phone' => $u_mobile,
                    'device' => $device,
                    'last_time' => $now,
                    'reg_time' => $now,
                    'is_new' => 1,
                    'cut_wigth' => $def_sign,
                    'expire_time' => $now + LOGIN_EXPIRE_TIME
                );
                DB::beginTransaction();
                $insert_id = DB::table('users')->insertGetId($insert_data);

                //关系链入库
                user_like($reUser, $insert_id);

                //用户注册送抽奖次数
                addDrawcont(1, $insert_id);

                //新增记录
                addDrawcont(3, $reUser->user_id);

                if (empty($insert_id)) {
                    DB::rollBack();
                    return error('99999', '操作失败');
                }
                $account = DB::table('user_account')->where('user_id', $insert_id)->first();
                if (empty($account)) {
                    DB::table('user_account')->insert([
                        'user_id' => $insert_id,
                        'create_at' => $now,
                        'update_at' => $now
                    ]);
                }
                $token = get_token($insert_id);

                $update_data['token'] = $token;

                //获取融云token

                $serverapi = new \ServerAPI();

                $rong_chat = $serverapi->getToken($insert_id, $nickname, $headimg);

                $rong_chat = json_decode($rong_chat);

                if ($rong_chat->code == 200) {
                    $chat_token = $rong_chat->token;
                    $update_data['chat_token'] = $chat_token;
                } else {
                    $chat_token = '';
                }
                $update_data['clientid'] = $clientid;

                DB::table('users')->where('user_id', $insert_id)->update($update_data);
                $invite_code = $this->invite_code();
                $extra_data = array(
                    'user_id' => $insert_id,
                    'new_status' => $reUser->new_status,
                    'invite_user_id' => $reUser->user_id,
                    'invite_code' => $reUser->mobile_phone,
                    'invite' => $invite_code
                );
                $ex_id = DB::table('mq_users_extra')->insertGetId($extra_data, 'ex_id');


                DB::table('mq_users_limit')->insert(['user_id' => $insert_id]);


                if (empty($ex_id)) {
                    DB::rollBack();
                    return error('99999', '操作失败');
                } else {
                    DB::commit();
                }
                Redis::lpush('newRegisterUser', $insert_id);
                $data = [
                    'user_id' => $insert_id,
                    'token' => $token,
                    'chat_token' => $chat_token,
                    'mobile' => $u_mobile,
                    'roleRank' => 0
                ];
                success($data, '注册成功');


            } else {
                $token = get_token($user->user_id);
                $update_data = array(
                    'token' => $token,
                    'device' => $device,
                    'clientid' => $clientid,
                    'last_time' => $now,
                    'reg_time' => $now,
                    'expire_time' => $now + LOGIN_EXPIRE_TIME
                );
                if (empty($user->nickname)) {
                    $update_data['nickname'] = $nickname;
                }
                if ($type == '2') {
                    $update_data['wxopenid'] = $openid;
                } else {
                    $update_data['qqopenid'] = $openid;
                }
                if (empty($user->headimg)) {
                    $update_data['headimg'] = $headimg;
                }
                if (empty($user->chat_token)) {
                    //获取融云token

                    $serverapi = new \ServerAPI();

                    $rong_chat = $serverapi->getToken($user->user_id, $nickname, $headimg);
                    $rong_chat = json_decode($rong_chat);

                    if ($rong_chat->code == 200) {
                        $chat_token = $rong_chat->token;
                        $update_data['chat_token'] = $chat_token;
                        $user->chat_token = $chat_token;
                    }
                }
                DB::table('users')->where('user_id', $user->user_id)->update($update_data);

                $roleRank = DB::table('mq_users_extra')->where('user_id', $user->user_id)->value('user_cx_rank');
                if ($roleRank == null) {
                    $roleRank = 0;
                }
                $data = [
                    'user_id' => $user->user_id,
                    'token' => $token,
                    'chat_token' => $user->chat_token,
                    'mobile' => $u_mobile,
                    'roleRank' => $roleRank

                ];
                success($data, '注册成功');
            }
        }

        event(new SingleLoginEvent($user, $clientid));


    }


    /**
     * description:注册
     * @author Harcourt
     * @date 2018/9/5
     */
    public function webRegister(Request $request)
    {
        $mobile = $request->input('remobile');

        $content = DB::table('trading_hall_explain')->where('type', 3)->value('content');

        return view('api.test', ['remobile' => $mobile, 'protocol' => $content]);
    }

    /**
     * description:注册
     * @author Harcourt
     * @date 2018/9/5
     */
    public function download()
    {

        $data = [
            'android_url' => 'https://www.pgyer.com/2QQ4',
            'ios_url' => 'https://itunes.apple.com/cn/app/id1470089753?mt=8'
        ];
        return view('api.download', $data);
    }

    /**
     * description:生成邀请码
     * @author libaowei
     */
    public function invite_code($lenght = 11, $user_id = 0)
    {
        //如果random_bytes函数存在
        if (function_exists("random_bytes")) {
            //生成随机数,长度为指定的长度的一半并
            $bytes = random_bytes(ceil($lenght / 2));
            //如果openssl_random_pseudo_bytes函数存在
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            //生成随机字符串,长度为指定的长度的一半并
            $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
        } else {
            throw new Exception("生成失败");
        }
        //返回一个十六进制，并截取到指定长度
        return substr(bin2hex($bytes), 0, $lenght);
    }

}
