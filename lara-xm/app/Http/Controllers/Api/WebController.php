<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class WebController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['index']);

    }

    /**
     * description:注册
     * @author Harcourt
     * @date 2018/7/18
     */
    public function index(Request $request)
    {

        $re_mobile = $request->input('re_mobile');
//        $username = $request->input('username');
//        $password = $request->input('password');
        $u_mobile = $request->input('u_mobile');
        $msg = $request->input('msg');

        if (empty($re_mobile) || empty($u_mobile)  || empty($msg) ) {
            //|| empty($username) || empty($password)
            return error('00000', '请求参数不全');
        }

        $verification = new \Verification();

        // if (!$verification->fun_phone($re_mobile)) {
        //     return error('01000', '请输入合法的推荐人手机号码');
        // }
        if (!$verification->fun_phone($u_mobile)) {
            return error('01000', '请输入合法的手机号码');
        }
        $reUser = DB::table('users')->select('users.user_id', 'new_status', 'user_status','users.mobile_phone','users.user_like')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('invite', $re_mobile)->first();

        if(empty($reUser) || $reUser->user_status == '2'){
            return error('10001','推荐人不存在');
        }
        $msgData = DB::table('verify_num')->where([['veri_mobile', '=', $u_mobile], ['veri_type', '=', '1']])->first();
        if (empty($msgData) || strcmp($msg, $msgData->veri_number) !== 0
            || (time() - $msgData->veri_gmt_expire) >= 0) {
                return error('10003', '验证码输入错误或已过期');
        }

        $user = DB::table('users')->where('mobile_phone', $u_mobile)->first();
        if ($user) {
            return error('10002','手机号已注册');
        }
//        $user = DB::table('users')->where('user_name',$username)->first();
//        if($user){
//            return error('10002','用户名已注册');
//        }

        $now = time();
        $rand_name = '火单' . rand(10000, 99999);

        $path = 'headimg/default.png';
        $insert_data = array(
            'user_name' => $u_mobile,//$username
//            'password'=>md5($password),
            'headimg' => $path,
            'mobile_phone' => $u_mobile,
            'last_time' => $now,
            'reg_time' => $now,
            'nickname' => $rand_name,
        );


        DB::beginTransaction();

        $insert_id = DB::table('users')->insertGetId($insert_data, 'user_id');

        //关系链入库
        user_like($reUser,$insert_id);

        //用户注册送抽奖次数

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
        }

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
        Redis::lpush('newRegisterUser',$insert_id);

        $data = [
            'user_id' => $insert_id,
            'token' => $token,
            'chat_token' => $chat_token,
            'mobile' => $u_mobile,
            'roleRank'=> 0

        ];
        success($data, '注册成功');
    }



    /**
     * description:短信验证
     * @author Harcourt
     * @date 2018/7/18
     */
    public function sendMsg(Request $request)
    {
        $user_id = $request->input('user_id');
        if(empty($user_id)){
            return error('00000','参数不全');
        }
        $where = [
            ['user_id',$user_id],
            ['belong_sys',2]
        ];
        $safeCheck = DB::table('ip_safecheck_log')->where($where)->first();

        $needMsg = '0';

        if(empty($safeCheck)){
            $needMsg = '1';
        }else{
            $ip = get_client_ip();
            if(strcmp($ip,$safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= time()){
                $needMsg = '1';
            }
        }
        $u_mobile = '';
        if($needMsg == '1'){
            $u_mobile = DB::table('users')->where('user_id',$user_id)->value('mobile_phone');

            $type = 5;
            $verify_num = rand('100000', '999999');
            $expire = time() + 5 * 60;
            $exitMsg = DB::table('verify_num')->where([['veri_mobile', $u_mobile], ['veri_type', $type]])->first();

            if ($exitMsg && $exitMsg->veri_gmt_create >= time() - 60) {
                return error('00003', '短信60秒只允许发送一次');
            }
            $result = sendSms($u_mobile, $verify_num, $type);
            if (empty($result)) {
                return error('00001', '短信发送失败');
            }

            $code = $result['Code'];
            switch ($code) {
                case 'OK':
                    if ($exitMsg) {
                        $update_data = array(
                            'veri_number' => $verify_num,
                            'veri_gmt_expire' => $expire,
                            'veri_gmt_create' => time()
                        );
                        DB::table('verify_num')->where('veri_id', $exitMsg->veri_id)->update($update_data);
                    } else {
                        $insert_data = array(
                            'veri_mobile' => $u_mobile,
                            'veri_number' => $verify_num,
                            'veri_gmt_expire' => $expire,
                            'veri_gmt_create' => time(),
                            'veri_type' => $type
                        );
                        DB::table('verify_num')->insert($insert_data);
                    }
                    break;
                case 'isv.BUSINESS_LIMIT_CONTROL':
                    return error('00002', '您发送验证码太过于频繁，请稍后重试');
                    break;
                default:
                    return error('00001', '短信发送次数被限制，请稍后重试');
                    break;
            }
        }
        $data = [
            'needMsg'=>$needMsg,
            'mobile'=>$u_mobile
        ];
        success($data);
    }

    /**
     * description:身份验证
     * @author Harcourt
     * @date 2018/8/8
     */
    public function validateMsg(Request $request)
    {
        $user_id = $request->input('user_id');
        $mobile = $request->input('mobile');
        $msg = $request->input('msg');
        if(empty($user_id) || empty($mobile) || empty($msg)){
            return error('00000', '请求参数不全');
        }
        $where = [
            ['veri_mobile',$mobile],
            ['veri_number',$msg],
            ['veri_type',5]
        ];
        $verify = DB::table('verify_num')->where($where)->first();
        $now = time();
        if(empty($verify) || $verify->veri_gmt_expire <= $now){
            return error('20001', '验证码或者手机号不正确');
        }
        $ch_where = [
            ['user_id',$user_id],
            ['belong_sys',2]
        ];
        $ip = get_client_ip();
        $safeCheck = DB::table('ip_safecheck_log')->where($ch_where)->first();

        if(empty($safeCheck)){
            //直接身份验证ip插入

            $check_insert_data = [
                'user_id'=>$user_id,
                'belong_sys'=>2,
                'ip_address'=>$ip,
                'check_time'=>date('Y-m-d H:i:s',$now) ,
                'expire_time'=>date('Y-m-d H:i:s',$now + IP_EXPIRE_TIME)

            ];
            DB::table('ip_safecheck_log')->insertGetId($check_insert_data,'log_id');
        }else{
                $update_insert_data = [
                    'ip_address'=>$ip,
                    'check_time'=>date('Y-m-d H:i:s',$now) ,
                    'expire_time'=>date('Y-m-d H:i:s',$now + IP_EXPIRE_TIME)
                ];
                DB::table('ip_safecheck_log')->where('log_id',$safeCheck->log_id)->update($update_insert_data);

        }
        success();
    }


    /**
     * @param $gc_id
     * @param $operatorUserId
     * @param $operatorNickname
     * @param $targetUserIds
     * @param $targetUserDisplayNames
     * @author Harcourt
     * @date 2018/8/31
     */
    function joinGroupNoti($gc_id, $operatorUserId, $operatorNickname, $targetUserIds, $targetUserDisplayNames)
    {


        $content = array(
            'operatorUserId' => $operatorUserId,
            'operation' => 'Add',
            'data' => array(
                'operatorNickname' => $operatorNickname,
                'targetUserIds' => [(string)$targetUserIds],
                'targetUserDisplayNames' => [$targetUserDisplayNames]
            ),
            "message" => $targetUserDisplayNames . "已经进群了",
            'extra' => ''

        );
        $content = json_encode($content);
        $serverapi = new \ServerAPI();
        $ret =  $serverapi->messageGroupPublish((string)$operatorUserId, (string)$gc_id, 'RC:GrpNtf', $content);
    }

    /**
     * description:生成邀请码
     * @author libaowei
     */
    public function invite_code($lenght = 11,$user_id = 0) {
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
