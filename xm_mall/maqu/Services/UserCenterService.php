<?php
namespace maqu\Services;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use maqu\Log;
use maqu\Models\Account;
use maqu\Models\AccountLog;
use maqu\Models\AdminUser;
use maqu\Models\IpSafeCheckLog;
use maqu\Models\MqAccountTransferApply;
use maqu\Models\ShopConfig;
use maqu\Models\User;
use maqu\Models\UserSafe;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * 用户中心服务层
 *
 * @author maqu
 *
 */
class UserCenterService extends BaseService {
    /**
     * 验证是否安全登录
     * @param int $belongsys
     * @return array
     */
    public function checkLoginSafeOnPC($belongsys = BELONG_SYS_ADMIN)
    {
        $_LANG = $GLOBALS['_LANG'];
        $request = Request::createFromGlobals();
        $username = $request->get('username');
        $password = $request->get('password');
        $captcha = $request->get('captcha');
        if (!$username || !$password || !$captcha) {
            return $this->args_invalid();
        }
        //检查验证码
        include_once(ROOT_PATH . 'includes/cls_captcha.php');
        $validator = new \captcha();
        if (!$validator->check_word($captcha)) {
            return $this->failure($_LANG['captcha_error']);
        }
        //检查密码
        $user = AdminUser::where('user_name', $username)->first();
        if (!$user) {
            /* 账号  */
            return $this->failure($_LANG['login_failure']);
        }
        if ($user->ec_salt) {
            if ($user->password != md5(md5($password) . $user->ec_salt)) {
                /* 密码 不正确的时候 retry_time + 1 */
               // return $this->loginFailureLocked($user->user_id,BELONG_SYS_ADMIN);

                return $this->failure($_LANG['login_failure']);
            }
        } else {
            if ($user->password != md5($password)) {
                /* 密码 不正确的时候 retry_time + 1 */
           //     return $this->loginFailureLocked($user->user_id,BELONG_SYS_ADMIN);
              return $this->failure($_LANG['login_failure']);
            }
        }
        //没有用户账号、或者未设置手机时、无须使用手机号码登录,先判断用户是否被锁定
//        $usersafe_info=UserSafe::where('user_id',$user->user_id)
//            ->where('belong_sys',BELONG_SYS_ADMIN)
//            ->where('locked',1)
//            ->first();
//        if($usersafe_info){
//            return $this->failure('您的账号已被锁定，请通过下方"忘记密码？"重置密码！');
//        }
        if (!$user->phone) {
            return $this->success('成功');
            /* 清空 retry_time 次数 */
//            $res=$this->resetRetryTimes($user->user_id,BELONG_SYS_ADMIN);
//            if($res){
//                return $this->success('成功');
//            }else{
//                return $this->failure('登录失败，请稍后再试！');
//            }

        }

        //如果为假 那么测试环境就不需要验证码
        if(ENABLE_IP_CHECK === false){
            return $this->success('成功');
        }

        //检查用户体系安装中心
        $ip = get_client_ip(1);

        //判断IP地址是否有变
        $last_log = IpSafeCheckLog::where('user_id', $user->user_id)
            ->where('belong_sys', $belongsys)
            ->where('ip_address', $ip)
            ->first();

        //加一个判断 如果是测试环境 后台登录的时候 IP变化也不需要手机验证


        if (!$last_log) {
            return [
                'result'=>false,
                'code'=> 'MSG_CD_NEED_SAFE_LOGIN',
                'message'=>'失败',
                'phone'=> $user->phone
            ];
//            return $this->jsonResult(RESPONSE_FAILURE, '失败', [
//                'phone' => $user->phone       //admin_user表的 phone
//            ], 'MSG_CD_NEED_SAFE_LOGIN', 0);
        }

        if (local_strtotime($last_log->expire_time) < time()) {
//            return $this->jsonResult(RESPONSE_FAILURE, '失败', [
//                'phone' =>  $user->phone       //admin_user表的 phone
//            ], 'MSG_CD_NEED_SAFE_LOGIN', 0);
            return [
                'result'=>false,
                'code'=> 'MSG_CD_NEED_SAFE_LOGIN',
                'message'=>'失败',
                'phone'=> $user->phone
            ];
        }
        return $this->success('成功');
        /* 清空 retry_time 次数 */
//        $res=$this->resetRetryTimes($user->user_id,BELONG_SYS_ADMIN);
//        if($res){
//            return $this->success('成功');
//        }else{
//            return $this->failure('登录失败，请稍后再试！');
//        }

    }

    /*
       * 如果IP变化->手机验证登录
       * @param $vt_mobile_type 验证码类型
       * return JSON
       */
    public function sendMobileCode($vt_mobile_type)
    {
        $request = Request::createFromGlobals();

        //接受 手机号码 验证码
        $mobile_phone = $request->get('phone');
     //   $captcha = $request->get('captcha');
        if (!$mobile_phone) {
            return $this->args_invalid();
        }

        //生成6位短信验证码
        $mobile_code = $this->rand_number(6);
        /* 发送激活短信 */
        include_once(ROOT_PATH . 'includes/cls_sms_alidayu.php');
        $demo = new \sms_alidayu(ALIYUN_ACCESS_KEY_ID, ALIYUN_ACCESS_KEY_APPSECRET);
        $response = $demo->sendSms(
            SMS_ALIDAYU_FREE_SIGN_NAME, // 短信签名
            SMS_ALIDAYU_TEMPLATE_CODE, // 短信模板编号
            $mobile_phone, // 短信接收者
            Array(  // 短信模板中字段的值
                "code" => $mobile_code
            )
        );
        switch ($response->Code) {
            case 'OK':

                if (!isset($count)) {
                    $ext_info = array(
                        "count" => 1
                    );
                } else {
                    $ext_info = array(
                        "count" => $count
                    );
                }
                    /* 保存手机号码到SESSION中 */
                    $_SESSION[$vt_mobile_type] = $mobile_phone;
                    /* 保存验证信息 */
                    include_once(ROOT_PATH . 'includes/lib_validate_record.php');
                    save_validate_record($mobile_phone, $mobile_code, $vt_mobile_type, time(), time() + 30 * 60, $ext_info);
                    return $this->success();
            case 'isv.BUSINESS_LIMIT_CONTROL':
                return $this->failure('发送太频繁，请稍后再试');
            default:
                return $this->failure('短信验证码发送失败');
        }
    }


    /*
       * 在user_safe表中加入一条数据，防止多用户登录
       * @param $user_id 用户id
       * @param $belong_sys 所属系统
       * return Boolean
       */
    public function loginAddUserSafe($user_id,$belong_sys)
    {
        //检查用户体系安装中心
        $ip = get_client_ip(1);
        //按照ip.time().随机数的方式 哈希一个唯一数
        $time=time();
        //得到一个随机数
        $rand_code = $this->rand_number(6);
        $access_token=md5($ip.$time.$rand_code);

        //把access_token 存入cookie ，session 。通过这个验证是否多用户登录了
        setcookie('access_token',$access_token);
        $_SESSION['access_token']=$access_token;
        //查看是否已经存在一条数据存在
        $user_info=UserSafe::where('user_id',$user_id)->where('belong_sys',$belong_sys)->first();

        //已经存在数据，就更新access_token 和 updated_time
        $date=local_date('Y-m-d H:i:s',$time);
        if($user_info){
            $user_info->access_token=$access_token;
            $user_info->updated_at=$date;
            $res=$user_info->save();
            if($res){
                return true;
            }
                return false;
        }
        //还没存在user_info,加入一数据/
        $userSafe=new UserSafe();
        $userSafe->user_id=$user_id;
        $userSafe->belong_sys=$belong_sys;  //会员中心
        $userSafe->retry_times=0;
        $userSafe->access_token=$access_token;   //加入登录令牌
        $userSafe->locked=0;
        $userSafe->created_at=$date;
        $userSafe->updated_at=$date;
        $res=$userSafe->save();
        if($res){
            return true;
        }
            return false;

    }


    /*
       * 用户密码多次输入错误，锁定用户
       * @param $user_id 用户id
       * @param $belong_sys 所属系统
       * return Boolean
       */
    public function loginFailureLocked($user_id,$belong_sys)
    {
        //查看是否已经存在一条数据存在
        $user_info=UserSafe::where('user_id',$user_id)->where('belong_sys',$belong_sys)->first();
        //已经存在数据，就更新 updated_time
        $time=time();
        $date=local_date("Y-m-d H:i:s" ,$time);
        if($user_info->retry_times>=2){
            /* 锁定用户 */
            $user_info->retry_times=3;
            $user_info->locked=1;
            $user_info->updated_at=$date;
            $user_info->save();

            return $this->failure('您的账号已被锁定，请通过下方"忘记密码？"重置密码！');
        }

        if($user_info){
            $user_info->updated_at=$date;
            /* 次数 加一 */
            $retry_times=$user_info->retry_times;
            $user_info->retry_times= $retry_times+1;
            $res=$user_info->save();
            if($res){
                $times=3-$user_info->retry_times;
                return $this->failure('您已输入错误用户名或密码'."$user_info->retry_times".'次'.',还可以输入'.$times.'次！');
            }
            return $this->failure('登录失败，请稍后再试！');
        }
        //还没存在user_info,加入一数据/
        $userSafe=new UserSafe();
        $userSafe->user_id=$user_id;
        $userSafe->belong_sys=$belong_sys;  //会员中心
        $userSafe->retry_times=1;
        $userSafe->access_token=0;   //加入登录令牌
        $userSafe->locked=0;
        $userSafe->created_at=$date;
        $userSafe->updated_at=$date;
        $res=$userSafe->save();
        if($res){
            return $this->failure('您已输入错误用户名或密码1次，还可以输入2次！');
        }
        return $this->failure('登录失败，请稍后再试！');
    }

    /*
     * 通过找回密码的方式解锁
     * @param $user_id 用户ID
     * @param $belong_sys 所属系统 1 前台 0 后台
     * */
    public function unlockedUser($user_id,$belong_sys){
        $user_info=UserSafe::where('user_id',$user_id)
                        ->where('belong_sys',$belong_sys)
                        ->first();
        if($user_info){
            $date=local_date('Y-m-d H:i:s',time());
            $user_info->retry_times=0;
            $user_info->locked=0;
            $user_info->updated_at=$date;
            return $user_info->save();
        }else{
            return false;
        }

    }


    /*
      * 后台解锁被锁定的管理员
      * @param $user_id 用户id
      * @param $belong_sys 所属系统
      * @param $locked 1 锁定 0 不锁
      * return Boolean
      */
    public function isLockedUser($user_id, $belong_sys, $locked)
    {
        //查看是否已经存在一条数据存在
        $user_info = UserSafe::where('user_id', $user_id)->where('belong_sys', $belong_sys)->first();

        if(!$user_info){
            $date = local_date('Y-m-d H:i:s', time());
            $user_info = new UserSafe();
            $user_info->user_id = $user_id;
            $user_info->belong_sys = $belong_sys;  //会员中心
            $user_info->retry_times = 0;
            $user_info->access_token = '';   //加入登录令牌
            $user_info->locked = $locked;
            $user_info->created_at = $date;
            $user_info->updated_at = $date;
        } else {
            $user_info->retry_times = 0;
            $user_info->locked = $locked;
        }

        $res = $user_info->save();
        if ($res) {
            return true;
        } else {
            return false;
        }

//        /* 解锁的时候*/
//        if (!$locked) {
//            /* 已经有数据的情况下*/
//            if ($user_info) {
//                $user_info->retry_times = 0;
//                $user_info->locked = $locked;
//                $res = $user_info->save();
//                if ($res) {
//                    return true;
//                } else {
//                    return false;
//                }
//            }
//            /* 没有数据的情况 */
//            $date = local_date('Y-m-d H:i:s', time());
//            $userSafe = new UserSafe();
//            $userSafe->user_id = $user_id;
//            $userSafe->belong_sys = $belong_sys;  //会员中心
//            $userSafe->retry_times = 0;
//            $userSafe->access_token = 0;   //加入登录令牌
//            $userSafe->locked = $locked;
//            $userSafe->created_at = $date;
//            $userSafe->updated_at = $date;
//            $res = $userSafe->save();
//            if ($res) {
//                return true;
//            } else {
//                return false;
//            }
//
//        } else {
//            /* 锁定用户的时候*/
//            if ($user_info) {
//                $user_info->locked = $locked;
//                $user_info->retry_times = 3;
//                $res = $user_info->save();
//                if ($res) {
//                    return true;
//                } else {
//                    return false;
//                }
//            }
//            /* 没有数据的情况 */
//            $date = local_date('Y-m-d H:i:s', time());
//            $userSafe = new UserSafe();
//            $userSafe->user_id = $user_id;
//            $userSafe->belong_sys = $belong_sys;  //会员中心
//            $userSafe->retry_times = 3;
//            $userSafe->access_token = 0;   //加入登录令牌
//            $userSafe->locked = $locked;
//            $userSafe->created_at = $date;
//            $userSafe->updated_at = $date;
//            $res = $userSafe->save();
//            if ($res) {
//                return true;
//            } else {
//                return false;
//            }
//
//        }

    }

    /* 当用户在三次之内登录成功 请0 user_safe的retry_time
     * @param $user_id 用户id
     * @param $belong_sys 所属系统
     * return Boolean
    */
    public function resetRetryTimes($user_id, $belong_sys){
        //查看是否已经存在一条数据存在
        $user_info = UserSafe::where('user_id', $user_id)->where('belong_sys', $belong_sys)->first();
        if(!$user_info){
            return true;
        }
        $date=local_date("Y-m-d H:i:s",time());
        $user_info->retry_times=0;
        $user_info->locked=0;
        $user_info->updated_at=$date;
        $res=$user_info->save();
        if($res){
            return true;
        }
            return false;

    }


    /**
     * check_ip
     * 是否需要开启短信验证
     * @param $user_id int
     * @param $belongsys int
     * @return $need_sms ture /false
     */
    public function  checkIp($user_id,$belongsys)
    {
        $need_sms = CHECK_SEND_SMS_FALSE;
        //检查用户体系安装中心
        $ip = get_client_ip(1);
//        Log::error('USERID'.$user_id);
//        $ip2 = real_ip();
//        Log::error('IPF'.$ip);
//        Log::error('IPS'.$ip2);
        //判断IP地址是否有变
        $last_log = IpSafeCheckLog::where('user_id',$user_id)
            ->where('belong_sys',$belongsys)
            ->where('ip_address',$ip)
            ->orderBy('check_time','desc')
            ->first();

        if(!$last_log){
            $need_sms = CHECK_SEND_SMS_TRUE;
        }
        $now = local_date('Y-m-d H:i:s',gmtime());
        if($last_log){
            if($last_log->expire_time < $now){
                $need_sms = CHECK_SEND_SMS_TRUE;
            }
        }
//        Log::error('RETURN'.$need_sms);
        return $need_sms;
    }

    /**
     * addIpCheckLog
     * 短信验证成功记录日志时间
     * @param $user_id int
     * @param $belongsys int
     * @return $need_sms ture /false
     */
    public function  addIpCheckLog($user_id,$belongsys)
    {
        //检查用户体系安装中心
        $ip = get_client_ip(1);
        $log = IpSafeCheckLog::where('user_id',$user_id)
            ->where('belong_sys',$belongsys)
            ->where('ip_address',$ip)
            ->orderBy('check_time','desc')
            ->first();

        if($log){
            $log = [
                'check_time'=> local_date('Y-m-d H:i:s',gmtime()),
                'expire_time'=>local_date('Y-m-d H:i:s',gmtime()+7*86400), //有效期7天
            ];
            IpSafeCheckLog::where('user_id',$user_id)
                ->where('ip_address',$ip)
                ->where('belong_sys',$belongsys)
                ->update($log);
        }else{
            $log = [
                'user_id'=>$user_id,
                'belong_sys'=>$belongsys,
                'ip_address'=>$ip,
                'check_time'=> local_date('Y-m-d H:i:s',gmtime()),
                'expire_time'=>local_date('Y-m-d H:i:s',gmtime()+7*86400), //有效期7天
            ];
            IpSafeCheckLog::insert($log);
        }
        return $this->success();
    }

    /**
     * 冻结
     * @param $account_id
     * @param $account_type
     * @param $amount
     * @param $desc
     * @param bool|true $withTrans
     * @return array
     * @throws \Exception
     */
    public function frozen($account_id,$account_type,$amount,$desc,$withTrans =true){

        if($withTrans){
            DB::beginTransaction();
        }
        try{
            //account
            $account =Account::where('account_id',$account_id)
                ->where('account_type',$account_type)
                ->first();

            if(!$account){
                return $this->failure("account:$account_id account_type:$account_type not exist.");
            }
            $account->frozen_money+=$amount;
            $account->money-=$amount;
            $account->update_at=time();

            //$account->save();
            DB::table('mq_account')
                ->where('account_id',$account_id)
                ->where('account_type',$account_type)
                ->update($account->toArray());

            //account_log
            $log = new AccountLog();
            $log_data=[
                'account_id'=>$account_id,
                'account_type'=>$account_type,
                'money'=>$account->money,
                'frozen_money'=>$account->frozen_money?$account->frozen_money:0,
                'change_money'=>$amount,
                'change_time'=>time(),
                'change_desc'=>$desc,
                'change_type'=>USER_ACCOUNT_CHANGE_FROZEN,
                'income_type'=>'-',
            ];
            $log->insert($log_data);

            if($withTrans){
                DB::commit();
            }

        } catch(\Exception $e){
            if($withTrans){
                DB::rollback();
            }
            throw $e;
        }

    }

    /**
     * 取消冻结
     * @param $account_id
     * @param $account_type
     * @param $amount
     * @param $desc
     * @param bool|true $withTrans
     * @return array
     * @throws \Exception
     */
    public function unfrozen($account_id,$account_type,$amount,$desc,$withTrans =true){

        if($withTrans){
            DB::beginTransaction();
        }

        try{

            //account
            $account =Account::where('account_id',$account_id)
                ->where('account_type',$account_type)
                ->first();

            if(!$account){
                return $this->failure("account:$account_id account_type:$account_type not exist.");
            }
            $account->frozen_money-=$amount;
            $account->money+=$amount;
            $account->update_at=time();

            //$account->save();
            DB::table('mq_account')
                ->where('account_id',$account_id)
                ->where('account_type',$account_type)
                ->update($account->toArray());

            //account_log
            $log = new AccountLog();
            $log_data=[
                'account_id'=>$account_id,
                'account_type'=>$account_type,
                'money'=>$account->money,
                'frozen_money'=>$account->frozen_money?$account->frozen_money:0,
                'change_money'=>$amount,
                'change_time'=>time(),
                'change_desc'=>$desc,
                'change_type'=>USER_ACCOUNT_CHANGE_UNFROZEN,
                'income_type'=>'-',
            ];
            $log->insert($log_data);
            if($withTrans){
                DB::commit();
            }

        } catch(\Exception $e){
            if($withTrans){
                DB::rollback();
            }
            throw $e;
        }

    }

    /**
     * transferApply
     * 大金额转账申请
     * @param $user_id int
     * @param $account_type varchar
     * @param $accept_account varchar //接收用户名
     * @param $transfer_money int 转账金额
     * @return $need_sms
     */
    public function transferApply($user_id,$account_type,$accept_account,$transfer_money){

        if(!$user_id || !$account_type || !$accept_account){
            return $this->failure('提交信息有误，请填写符合的数据');
        }
        if(!$transfer_money){
            return $this->failure('金额必须大于0。');
        }

        $user =User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_id',$user_id)
            ->first();

        if(!$user){
            return $this->failure('转出账号不存在');
        }
        if($user->user_cx_rank== 0){
            return $this->failure('该账户未激活');
        }
        if($user->status!=1){
            return $this->failure('您的账户未实名认证暂无转账权限，请先实名认证。');
        }

        //查询用户当日转账总和
        $user_limit = DB::table('mq_users_limit')->where('user_id',$user_id)->first();
        $today_starttime = local_strtotime(local_date('Y-m-d', gmtime()).' 00:00:00');
        $today_endtime = local_strtotime(local_date('Y-m-d', gmtime()).' 00:00:00') + 86399;
        //新美转账关闭
        $config = ShopConfig::get()->toArray();
        $configs = array_column($config,'value','code');
        switch ($account_type)
        {
            case 'cash':    //新美积分
                $xm_transfer_cash_close = $configs[CONFIG_CODE_xm_transfer_cash_close];
                $xm_transfer_cash_close_reason = $configs[CONFIG_CODE_xm_transfer_cash_close_reason];
                if($xm_transfer_cash_close){
                    return $this->failure($xm_transfer_cash_close_reason?$xm_transfer_cash_close_reason:'新美积分转账功能暂时关闭，请在节后操作。');
                }
                $xm_transfer_rate_cash_fee = $configs[CONFIG_CODE_xm_transfer_rate_cash_fee];

                if(!is_numeric($xm_transfer_rate_cash_fee) || $xm_transfer_rate_cash_fee<0 || $xm_transfer_rate_cash_fee>100){
                    return $this->failure('新美积分转账手续费设定不正确，请跟系统管理员联系。');
                }
                $cash_amount = AccountLog::where('account_id',$user->account_id)
                    ->where('income_type','-')->where('change_type',2)->where('account_type','cash')
                    ->where('change_time','>=',$today_starttime)
                    ->where('change_time','<=',$today_endtime)
//                    ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(change_time),'%Y-%m-%d') = DATE_FORMAT(NOW(),'%Y-%m-%d')")
                    ->SUM('change_money');
                //获取用户是否被限制
                if(isset($user_limit->start_time)){
                    if(($user_limit->end_time >= time() && $user_limit->start_time<=time() ) || ($user_limit->start_time<=time() && empty($user_limit->end_time)) ){ //如果时间有效 或者未设置即永久有效

                        if(isset($user_limit->user_limited) && $user_limit->user_limited==1){
                            return $this->failure('该账户被限制。');
                        }
                        if($user_limit->cash_limited>0){
                            if($transfer_money + $cash_amount > $user_limit->daily_cash_transfer_sum_limit){
                                return $this->failure('您本日的新美积分转账额度已经超限，请明天再来。');
                            }
                        }
                    }
                }
                $fee = $transfer_money*$xm_transfer_rate_cash_fee/100;
                break;
            case 'consume':
                $xm_transfer_rate_consume_fee = $configs[CONFIG_CODE_xm_transfer_rate_consume_fee];
                if(!is_numeric($xm_transfer_rate_consume_fee) || $xm_transfer_rate_consume_fee<0 || $xm_transfer_rate_consume_fee>100){
                    return $this->failure('消费积分转账手续费设定不正确，请跟系统管理员联系。');
                }
                $consume_amount = AccountLog::where('account_id',$user->account_id)
                    ->where('income_type','-')->where('change_type',2)->where('account_type','consume')
                    ->where('change_time','>=',$today_starttime)
                    ->where('change_time','<=',$today_endtime)
//                    ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(change_time),'%Y-%m-%d') = DATE_FORMAT(NOW(),'%Y-%m-%d')")
                    ->SUM('change_money');
                //获取用户是否被限制
                if(isset($user_limit->start_time)){ //如果有新美积分限制判断时间是否有效
                    if(($user_limit->end_time >= time() && $user_limit->start_time<=time() ) || ($user_limit->start_time<=time() && empty($user_limit->end_time)) ){ //如果时间有效 或者未设置即永久有效
                        if(isset($user_limit->user_limited) && $user_limit->user_limited==1){
                            return $this->failure('该账户被限制。');
                        }
                        if($user_limit->consume_limited>0){
                            if($transfer_money + $consume_amount > $user_limit->daily_consume_transfer_sum_limit){
                                return $this->failure('您本日的消费积分转账额度已经超限,请明天再来。');
                            }
                        }
                    }
                }
                $fee = $transfer_money*$xm_transfer_rate_consume_fee/100;
                break;
        }

        $user2 = User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_name',$accept_account)->first();

        if(!$user2){
            return $this->failure('转入账号不存在');
        }

        if($user->user_id == $user2->user_id){
            return $this->failure('无法给自己转账操作');
        }
        //用户账号
        $account =Account::where('account_id',$user->account_id)
            ->where('account_type',$account_type)
            ->first();

        if(!$account){
            return $this->failure("account:$user->account_id account_type:$account_type 不存在.");
        }
        //接收用户账号
        $account2 =Account::where('account_id',$user2->account_id)
            ->where('account_type',$account_type)
            ->first();

        if(!$account2){
            return $this->failure("account:$user2->account_id account_type:$account_type 不存在.");
        }

        if($account->money< $transfer_money + $fee){
            return $this->failure('优惠券不足');
        }

        DB::beginTransaction();


        try{

            //转账申请
            $apply = new MqAccountTransferApply();
            $apply_data=[
              'account_id'=>$user->account_id,
              'account_type'=>$account_type,
              'amount'=>$transfer_money,
              'create_at'=>time(),
              'update_at'=>time(),
              'add_user_id'=>$user->user_id,
              'status'=>XM_TRANSFER_STATUS_WAIT,
              'accept_id'=>$user2->user_id,
              'accept_account_id'=>$user2->account_id,
            ];
            $apply->insert($apply_data);
            //冻结积分
            $this->frozen($user->account_id,$account_type,$transfer_money,'大额转账冻结',$withTrans =false);
            DB::commit();
            return $this->success();
        } catch(\Exception $e){
            DB::rollback();
            throw $e;
        }
    }

    /**
     * transferApply
     * 大金额转账审核
     * @param $user_id int
     * @param $account_type varchar
     * @param $accept_account varchar //接收用户名
     * @param $transfer_money int 转账金额
     * @param $status int 审核状态
     * @param $desc varchar 审核描述
     * @return $need_sms
     */
    public function transferapproval($apply_id,$status,$desc){

        if(!$apply_id || !$status ){
            return $this->failure('提交信息有误，请填写符合的数据');
        }

        $apply = MqAccountTransferApply::find($apply_id);
        if(!$apply){
            return $this->failure('转账申请数据不存在');
        }
        if($apply->status != XM_TRANSFER_STATUS_WAIT){ //不是待审核状态
            return $this->failure('该记录已审核过，请勿重复审核！');
        }
        $user_id = $apply->add_user_id;
        $account_type = $apply->account_type;
        $accept_account = $apply->accept_id;
        $transfer_money = $apply->amount;

        if(!$transfer_money){
            return $this->failure('金额必须大于0。');
        }

        $user =User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_id',$user_id)
            ->first();

        if(!$user){
            return $this->failure('转出账号不存在');
        }
        if($user->user_cx_rank== 0){
            return $this->failure('该账户未激活');
        }
        if($user->status!=1){
            return $this->failure('您的账户未实名认证暂无转账权限，请先实名认证。');
        }

        //查询用户当日转账总和
        $user_limit = DB::table('mq_users_limit')->where('user_id',$user_id)->first();
        $today_starttime = local_strtotime(local_date('Y-m-d', gmtime()).' 00:00:00');
        $today_endtime = local_strtotime(local_date('Y-m-d', gmtime()).' 00:00:00') + 86399;
        //新美转账关闭
        $config = ShopConfig::get()->toArray();
        $configs = array_column($config,'value','code');
        switch ($account_type)
        {
            case 'cash':    //新美积分
                $xm_transfer_cash_close = $configs[CONFIG_CODE_xm_transfer_cash_close];
                $xm_transfer_cash_close_reason = $configs[CONFIG_CODE_xm_transfer_cash_close_reason];
                if($xm_transfer_cash_close){
                    return $this->failure($xm_transfer_cash_close_reason?$xm_transfer_cash_close_reason:'新美积分转账功能暂时关闭，请在节后操作。');
                }

                $cash_amount = AccountLog::where('account_id',$user->account_id)
                    ->where('income_type','-')->where('change_type',2)->where('account_type','cash')
                    ->where('change_time','>=',$today_starttime)
                    ->where('change_time','<=',$today_endtime)
//                    ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(change_time),'%Y-%m-%d') = DATE_FORMAT(NOW(),'%Y-%m-%d')")
                    ->SUM('change_money');
                //获取用户是否被限制
                if(isset($user_limit->start_time)){
                    if(($user_limit->end_time >= time() && $user_limit->start_time<=time() ) || ($user_limit->start_time<=time() && empty($user_limit->end_time)) ){ //如果时间有效 或者未设置即永久有效

                        if(isset($user_limit->user_limited) && $user_limit->user_limited==1){
                            return $this->failure('该账户被限制。');
                        }
                        if($user_limit->cash_limited>0){
                            if($transfer_money + $cash_amount > $user_limit->daily_cash_transfer_sum_limit){
                                return $this->failure('您本日的新美积分转账额度已经超限，请明天再来。');
                            }
                        }
                    }
                }

                break;
            case 'consume':
                $consume_amount = AccountLog::where('account_id',$user->account_id)
                    ->where('income_type','-')->where('change_type',2)->where('account_type','consume')
                    ->where('change_time','>=',$today_starttime)
                    ->where('change_time','<=',$today_endtime)
//                    ->whereRaw("DATE_FORMAT(FROM_UNIXTIME(change_time),'%Y-%m-%d') = DATE_FORMAT(NOW(),'%Y-%m-%d')")
                    ->SUM('change_money');
                //获取用户是否被限制
                if(isset($user_limit->start_time)){ //如果有新美积分限制判断时间是否有效
                    if(($user_limit->end_time >= time() && $user_limit->start_time<=time() ) || ($user_limit->start_time<=time() && empty($user_limit->end_time)) ){ //如果时间有效 或者未设置即永久有效
                        if(isset($user_limit->user_limited) && $user_limit->user_limited==1){
                            return $this->failure('该账户被限制。');
                        }
                        if($user_limit->consume_limited>0){
                            if($transfer_money + $consume_amount > $user_limit->daily_consume_transfer_sum_limit){
                                return $this->failure('您本日的消费积分转账额度已经超限,请明天再来。');
                            }
                        }
                    }
                }
                break;
        }

        $user2 = User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_id',$accept_account)->first();

        if(!$user2){
            return $this->failure('转入账号不存在');
        }

        if($user->user_id == $user2->user_id){
            return $this->failure('无法给自己转账操作');
        }
        //用户账号
        $account =Account::where('account_id',$user->account_id)
            ->where('account_type',$account_type)
            ->first();

        if(!$account){
            return $this->failure("account:$user->account_id account_type:$account_type 不存在.");
        }
        //接收用户账号
        $account2 =Account::where('account_id',$user2->account_id)
            ->where('account_type',$account_type)
            ->first();

        if(!$account2){
            return $this->failure("account:$user2->account_id account_type:$account_type 不存在.");
        }

        if($account_type=='consume'){

            $xm_transfer_rate_consume_fee = $configs[CONFIG_CODE_xm_transfer_rate_consume_fee];
            if(!is_numeric($xm_transfer_rate_consume_fee) || $xm_transfer_rate_consume_fee<0 || $xm_transfer_rate_consume_fee>100){
                return $this->failure('消费积分转账手续费设定不正确，请跟系统管理员联系。');
            }

            if(($user->invite_user_id == $user2->user_id) || ($user2->invite_user_id == $user->user_id)){
                $fee=0;
            } else {
                $fee= $transfer_money*$xm_transfer_rate_consume_fee/100;
            }
        } else {

            $xm_transfer_rate_cash_fee = $configs[CONFIG_CODE_xm_transfer_rate_cash_fee];

            if(!is_numeric($xm_transfer_rate_cash_fee) || $xm_transfer_rate_cash_fee<0 || $xm_transfer_rate_cash_fee>100){
                return $this->failure('新美积分转账手续费设定不正确，请跟系统管理员联系。');
            }
            $fee=$transfer_money*$xm_transfer_rate_cash_fee/100;
        }


        if($status == XM_TRANSFER_STATUS_SUCCESS){ //同意转账

            DB::beginTransaction();

            try {

                //审核通过
                $apply_data = [
                    'update_at'=>time(),
                    'status'=> XM_TRANSFER_STATUS_SUCCESS,
                    'app_result'=>$desc,
                ];
                DB::table('mq_account_transfer_apply')->where('id',$apply_id)->update($apply_data);

                //frozen money
                $frozen_money = $account->frozen_money-=$transfer_money;

                //account 转账转出
                DB::table('mq_account')->where('account_id',$user->account_id)
                        ->where('account_type',$account_type)
                        ->update([
                            'frozen_money'=>$frozen_money,
                            'update_at'=>time()
                        ]);
                //account_log
                $log = new AccountLog();
                $log_data=[
                    'account_id'=>$user->account_id,
                    'account_type'=>$account_type,
                    'money'=>$account->money,
                    'frozen_money'=>$account->frozen_money?$account->frozen_money:0,
                    'change_money'=>$transfer_money,
                    'change_time'=>time(),
                    'change_desc'=>"转账到对方账户".$user2->user_name.":".$transfer_money,
                    'change_type'=> USER_ACCOUNT_CHANGE_TRANSFER,
                    'income_type'=>'-',
                ];
                $log->insert($log_data);

                //扣除手续费
                $this->minusMoeny($user->account_id,$account_type,$fee,'转账手续费:'.$fee,USER_ACCOUNT_CHANGE_TRANSFER);
                //接收用户到账
                $this->addMoney($user2->account_id,$account_type,$transfer_money,'对方账户:'.$user->user_name.'转账到账:'.$transfer_money,USER_ACCOUNT_CHANGE_TRANSFER);

                //平台收取手续费
                $user_extra2 =DB::table('mq_users_extra')->where('user_id',SYSTEM_OFFICAL_ACCOUNT_USERID)
                    ->first();

                if($user_extra2){
                    $this->addMoney($user_extra2->account_id,$account_type,$fee,'转账申请ID:'.$user_id,USER_ACCOUNT_CHANGE_TRANSFER);
                }

                DB::commit();

            } catch(\Exception $e){
                DB::rollback();
                throw $e;
            }

        } else { //拒绝申请退还冻结

            DB::beginTransaction();

            try {
                //审核拒绝
                $apply_data = [
                    'update_at'=>time(),
                    'status'=> XM_TRANSFER_STATUS_FAILURE,
                    'app_result'=>$desc,
                ];
                DB::table('mq_account_transfer_apply')->where('id',$apply_id)->update($apply_data);
                //解冻
                $this->unfrozen($user->account_id,$account_type,$transfer_money,'转账审核拒绝',true);

                DB::commit();

            } catch(\Exception $e){
                DB::rollback();
                throw $e;
            }

        }

        return $this->success();
    }
    /*增加金额*/
    public function addMoney($account_id,$account_type,$amount,$desc,$change_type){

        //account
        $account =Account::where('account_id',$account_id)
            ->where('account_type',$account_type)
            ->first();

        if(!$account){
            return $this->failure("account:$account_id account_type:$account_type not exist.");
        }

        //account
        $account->money+=$amount;
        $account->update_at=time();

        //$account->save();
        DB::table('mq_account')
            ->where('account_id',$account_id)
            ->where('account_type',$account_type)
            ->update($account->toArray());

        //account_log
        $log = new AccountLog();
        $log_data=[
            'account_id'=>$account_id,
            'account_type'=>$account_type,
            'money'=>$account->money,
            'frozen_money'=>$account->frozen_money?$account->frozen_money:0,
            'change_money'=>$amount,
            'change_time'=>time(),
            'change_desc'=>$desc,
            'change_type'=>$change_type,
            'income_type'=>'+',
        ];
        $log->insert($log_data);
        return $this->success();
    }
    /*减少金额*/
    public function minusMoeny($account_id,$account_type,$amount,$desc,$change_type){

        //account
        $account =Account::where('account_id',$account_id)
            ->where('account_type',$account_type)
            ->first();

        if(!$account){
            return $this->failure("account:$account_id account_type:$account_type not exist.");
        }

        if($account->money<$amount){
            return $this->failure('优惠券不足');
        }

        //account
        $account->money-=$amount;
        $account->update_at=time();

        //$account->save();
        DB::table('mq_account')
            ->where('account_id',$account_id)
            ->where('account_type',$account_type)
            ->update($account->toArray());

        //account_log
        $log = new AccountLog();
        $log_data=[
            'account_id'=>$account_id,
            'account_type'=>$account_type,
            'money'=>$account->money,
            'frozen_money'=>$account->frozen_money?$account->frozen_money:0,
            'change_money'=>$amount,
            'change_time'=>time(),
            'change_desc'=>$desc,
            'change_type'=>$change_type,
            'income_type'=>'-',
        ];
        $log->insert($log_data);
        return $this->success();

    }

    /**
     * 随机生成指定长度的数字
     * @param number $length
     * @return number
     */
    private function  rand_number($length = 6)
    {
        if($length < 1)
        {
            $length = 6;
        }

        $min = 1;
        for($i = 0; $i < $length - 1; $i ++)
        {
            $min = $min * 10;
        }
        $max = $min * 10 - 1;

        return rand($min, $max);
    }


}
