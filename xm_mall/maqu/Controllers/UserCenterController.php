<?php
namespace maqu\Controllers;

use Illuminate\Support\Facades\Input;
use maqu\Log;
use maqu\Models\AdminUser;
use maqu\Services\UserCenterService;


/**
 *
 * 配置同步控制器
 *
 * @author maqu
 *
 */
class UserCenterController extends Controller {

    public function test(){
        Log::info('testtet');
        var_dump('test');
        die();
    }

    /**
     * 验证是否安全登录
     */
    public function checkSafe(){

        //url:post usercenter/check_safe
        //参数:username 用户名
        //     password 密码
        //     captcha 验证码
        //失败返回值：
        //正常返回指针
        $_LANG = $GLOBALS['_LANG'];
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';

        if(!$username || !$password || !$captcha){
            return $this->args_invalid();
        }

        //检查验证码
//        include_once(ROOT_PATH . 'includes/cls_captcha.php');
//
//        $validator = new \captcha();
//        if (!$validator->check_word($captcha)) {
//            return $this->failure($_LANG['captcha_error']);
//        }

        //检查密码
        $user = AdminUser::where('user_name',$username)->first();
        if(!$user){
            return $this->failure($_LANG['login_faild']);
        }

        if($user->ec_salt){

            if($user->password != md5(md5($password).$user->ec_salt)){

                return $this->failure($_LANG['login_faild']);
            }
        } else {

            if($user->password != md5($password)){

                return $this->failure($_LANG['login_faild']);
            }
        }

        //检查用户体系安装中心
        $ip = get_client_ip(1);

        $ucenterService = new UserCenterService();
        $res = $ucenterService->checkLoginSafeOnPC($user,BELONG_SYS_ADMIN,$ip,$_SERVER['HTTP_USER_AGENT'],[
            'username'=>$username,
            'password'=>$password,
            'captcha '=>$captcha
        ]);

        if($res['result']!=true){
            return $this->jsonResult(RESPONSE_FAILURE,$res['msg'],$res['data'],'ERR_CD_IPCHANGED',0);
        }

        return $this->success();

    }


    /*
     * IP变化 验证用户手机号
     */
    public function  checkPhoneSafe(){
        $mobile_phone = isset($_POST['phone']) ? trim($_POST['phone']) : ''; //接收 手机号
        $ucenterService = new UserCenterService();

        $ucenterService->checkPhoneLoginSafe($mobile_phone);  //调用service方法，发送短信



    }

//    /**
//     * 如果IP变化->手机验证登录
//     */
//    public function checkPhoneSafe(){
//        // 生成6位短信验证码
//        $mobile_code = $this->rand_number(6);
//        $mobile_phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
//        /* 发送激活短信 */
//        require (ROOT_PATH . 'includes/cls_sms_alidayu.php');
//        $demo = new sms_alidayu(ALIYUN_ACCESS_KEY_ID,ALIYUN_ACCESS_KEY_APPSECRET);
//        $response = $demo->sendSms(
//            SMS_ALIDAYU_FREE_SIGN_NAME, // 短信签名
//            SMS_ALIDAYU_TEMPLATE_CODE, // 短信模板编号
//            $mobile_phone, // 短信接收者
//            Array(  // 短信模板中字段的值
//                "code"=>$mobile_code
//            )
//        );
//
//        switch($response->Code){
//            case 'OK':
//
//                if(! isset($count))
//                {
//                    $ext_info = array(
//                        "count" => 1
//                    );
//                }
//                else
//                {
//                    $ext_info = array(
//                        "count" => $count
//                    );
//                }
//
//                // 保存手机号码到SESSION中
//                $_SESSION[VT_MOBILE_VALIDATE] = $mobile_phone;
//                // 保存验证信息
//                save_validate_record($mobile_phone, $mobile_code, VT_MOBILE_VALIDATE, time(), time() + 30 * 60, $ext_info);
//                echo 'ok';
//
//                break;
//            case 'isv.BUSINESS_LIMIT_CONTROL':
//                echo '发送太频繁，请稍后再试。';
//                break;
//            default:
//                echo '短信验证码发送失败';
//                break;
//        }
//    }
//
//    /**
//     * 随机生成指定长度的数字
//     *
//     * @param number $length
//     * @return number
//     */
//   private function  rand_number($length = 6)
//    {
//        if($length < 1)
//        {
//            $length = 6;
//        }
//
//        $min = 1;
//        for($i = 0; $i < $length - 1; $i ++)
//        {
//            $min = $min * 10;
//        }
//        $max = $min * 10 - 1;
//
//        return rand($min, $max);
//    }
}
