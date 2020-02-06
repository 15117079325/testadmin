<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{

    public function __construct()
    {
//        $this->middleware('userLoginValidate')->except(['getBankList']);
    }

    public function getBankList()
    {
        $banklist = [
            '中国工商银行', '招商银行', '中国建设银行',
            '中国农业银行', '中国银行', '上海浦东发展银行',
            '交通银行', '中国民生银行', '深圳发展银行',
            '广东发展银行', '中信银行', '华夏银行',
            '兴业银行', '广州市农村信用合作社', '广州市商业银行',
            '上海农村商业银行', '中国邮政储蓄',
            '中国光大银行', '上海银行', '北京银行',
            '渤海银行', '北京农村商业银行',
        ];
        success($banklist);
    }

    /**
     * description:检查是否设置过密码
     * @author Harcourt
     * @date 2018/8/1
     */
    public function checkAccount(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $tableName = 'users';
        $fieldName = 'password';
        $userPassword = DB::table($tableName)->where('user_id', $user_id)->pluck($fieldName)->first();
        $hasPassword = $userPassword ? '1' : '0';
        $tableName = 'mq_users_extra';
        $fieldName = 'pay_password';
        $payPassword = DB::table($tableName)->where('user_id', $user_id)->pluck($fieldName)->first();
        $hasPayPassword = $payPassword ? '1' : '0';
        $bank = DB::table('user_bankinfo')->where('user_id', $user_id)->first();
        $hasBank = $bank ? '1' : '0';
        $aliAccount = DB::table('alipay_account')->where('user_id', $user_id)->first();
        $hasAliAccount = $aliAccount ? '1' : '0';

        $hasAuthenticate = DB::table('mq_users_extra')->where('user_id', $user_id)->value('status');
        if ($hasAuthenticate == null) {
            $hasAuthenticate = '0';
        }

        $data = [
            'hasAuthenticate' => (string)$hasAuthenticate,
            'hasPassword' => $hasPassword,
            'hasPayPassword' => $hasPayPassword,
            'hasBank' => $hasBank,
            'hasAliAccount' => $hasAliAccount

        ];
        success($data);
    }

    /**
     * description:绑定支付宝
     * @author Harcourt
     * @date 2018/8/1
     */
    public function bindAliAccount(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $account = $request->input('account');
        $owner = $request->input('owner');

        if (empty($user_id) || empty($account) || empty($owner)) {
            return error('00000', '参数不全');
        }

        $userAuth = DB::table('mq_users_extra')->select('status', 'real_name')->where('user_id', $user_id)->first();
        if (empty($userAuth->status)) {
            return error('40011', '未实名认证');
        }
        if ($userAuth->status == 2) {
            return error('10005', '实名认证信息已提交,请耐心等待审核');
        }

        if (strcmp($owner, $userAuth->real_name) !== 0) {
            return error('10007', '请填写本人支付宝');
        }

        $aliAccount = DB::table('alipay_account')->where('user_id', $user_id)->first();
        if ($aliAccount) {
            $update_data = [
                'ac_account' => $account,
                'ac_owner' => $owner
            ];
            DB::table('alipay_account')->where('ac_id', $aliAccount->ac_id)->update($update_data);
            success();
        } else {
            $insert_data = array(
                'user_id' => $user_id,
                'ac_account' => $account,
                'ac_owner' => $owner
            );
            $insert_id = DB::table('alipay_account')->insertGetId($insert_data, 'ac_id');
            if ($insert_id) {
                success();
            } else {
                error('99999', '操作失败');
            }
        }
    }

    /**
     * description:绑定银行卡
     * @author Harcourt
     * @date 2018/8/1
     */
    public function bindBank(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $bank_name = $request->input('bank_name');
        $account = $request->input('account');
        $owner_name = $request->input('owner_name');
        $branch = $request->input('branch');
        $city = $request->input('city');

        if (empty($user_id) || empty($bank_name) || empty($account) || empty($owner_name) || empty($branch) || empty($city)) {
            return error('00000', '参数不全');
        }

        $userAuth = DB::table('mq_users_extra')->select('status', 'real_name')->where('user_id', $user_id)->first();
        if (empty($userAuth->status)) {
            return error('40011', '未实名认证');
        }
        if ($userAuth->status == 2) {
            return error('10005', '实名认证信息已提交,请耐心等待审核');
        }

        if (strcmp($owner_name, $userAuth->real_name) !== 0) {
            return error('10006', '请填写本人银行卡');
        }

        $bank = DB::table('user_bankinfo')->where('user_id', $user_id)->first();
        if ($bank) {
            return error('40002', '已绑定银行卡，请先解除绑定');
        }

        $insert_data = array(
            'user_id' => $user_id,
            'bank_name' => $bank_name,
            'account' => $account,
            'owner_name' => $owner_name,
            'branch' => $branch,
            'city' => $city,
        );
        $insert_id = DB::table('user_bankinfo')->insertGetId($insert_data, 'bank_id');
        if ($insert_id) {
            success();
        } else {
            error('99999', '操作失败');
        }
    }

    /**
     * description:银行卡或者支付宝
     * @author Harcourt
     * @date 2018/8/24
     */
    public function info(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 0);//1、银行卡2、支付宝
        if (empty($user_id) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');
        }
        if ($type == 1) {
            $info = DB::table('user_bankinfo')->select('bank_name', 'account', 'owner_name', 'branch', 'city')->where('user_id', $user_id)->first();
            $msg = '未绑定银行卡,请先绑定';

        } else {
            $info = DB::table('alipay_account')->select('ac_account', 'ac_owner')->where('user_id', $user_id)->first();
            $msg = '未绑定支付宝,请先绑定';
        }
        if (empty($info)) {
            return error('40003', $msg);
        }

        success($info);
    }


    /**
     * description:解除绑定
     * @author Harcourt
     * @date 2018/8/1
     */
    public function untieBank(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $pay_password = $request->input('pay_password');

        if (empty($user_id) || empty($pay_password)) {
            return error('00000', '参数不全');
        }

        $bank = DB::table('user_bankinfo')->where('user_id', $user_id)->first();
        if (empty($bank)) {
            return error('40003', '未绑定银行卡,请先绑定');
        }

        $user_extra = DB::table('mq_users_extra')->where('user_id', $user_id)->first();

        if (empty($user_extra) || empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }

        if (strcmp($pay_password, $user_extra->pay_password) !== 0) {
            return error('40005', '支付密码不正确');
        }

        $aff_row = DB::table('user_bankinfo')->where('user_id', $user_id)->delete();

        if ($aff_row) {
            success();
        } else {
            error('99999', '操作失败');
        }
    }

    /**
     * description:设置密码
     * @author Harcourt
     * @date 2018/8/1
     */
    public function setPassword(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $password = $request->input('password');
        $repassword = $request->input('repassword');
        $type = $request->input('type', 0);
        if (empty($user_id) || empty($password) || empty($repassword) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');
        }
        if (strcmp($password, $repassword) !== 0) {
            return error('40006', '两次密码不一致');
        }
        if ($type == 1) {
            $tableName = 'users';
            $fieldName = 'password';
            $pwd_salt = DB::table($tableName)->select('password', 'ec_salt')->where('user_id', $user_id)->first();
            if ($pwd_salt && $pwd_salt->ec_salt) {
                $password = md5($password . $pwd_salt->ec_salt);
            }
        } else {
            $tableName = 'mq_users_extra';
            $fieldName = 'pay_password';

        }

        $userPassword = DB::table($tableName)->where('user_id', $user_id)->pluck($fieldName)->first();
        if ($userPassword) {
            return error('40007', '密码已存在');
        }
        $aff_row = DB::table($tableName)->where('user_id', $user_id)->update([$fieldName => $password]);
        if ($aff_row) {
            success();
        } else {
            error('99999', '操作失败');
        }
    }

    /**
     * description:修改密码
     * @author Harcourt
     * @date 2018/8/1
     */
    public function modifyPassword(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $oldpassword = $request->input('oldpassword');
        $password = $request->input('password');
        $repassword = $request->input('repassword');
        $type = $request->input('type', 0);
//        dd($repassword);
        if (empty($user_id) || empty($oldpassword) || empty($password) || empty($repassword) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');
        }
        if (strcmp($password, $repassword) !== 0) {
            return error('40006', '两次密码不一致');
        }
        if (strcmp($password, $oldpassword) == 0) {
            return error('40008', '新密码不能和原密码一样');
        }
        if ($type == 1) {
            $tableName = 'users';
            $fieldName = 'password';
            $pwd_salt = DB::table($tableName)->select('password', 'ec_salt')->where('user_id', $user_id)->first();
            if (!empty($pwd_salt->ec_salt)) {
                $userPassword = md5($oldpassword . $pwd_salt->ec_salt);
                $password = md5($password . $pwd_salt->ec_salt);

                if (strcmp($pwd_salt->password, $userPassword) !== 0) {
                    return error('40009', '原密码错误');
                }
            } else {
                $userPassword = $pwd_salt->password;
            }

        } else {
            $tableName = 'mq_users_extra';
            $fieldName = 'pay_password';
            $userPassword = DB::table($tableName)->where('user_id', $user_id)->pluck($fieldName)->first();

        }
        if (strcmp($oldpassword, $userPassword) !== 0) {
            return error('40009', '原密码错误');
        }
        $aff_row = DB::table($tableName)->where('user_id', $user_id)->update([$fieldName => $password]);
        if ($aff_row) {
            success();
        } else {
            error('99999', '操作失败');
        }
    }

    /**
     * description:找回支付密码
     * @author Harcourt
     * @date 2018/8/1
     */
    public function resetPassword(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $mobile = $request->input('mobile', 0);
        $msg = $request->input('msg');
        $password = $request->input('password');
        $repassword = $request->input('repassword');
        if (empty($user_id) || empty($mobile) || empty($msg) || empty($password) || empty($repassword)) {
            return error('00000', '参数不全');
        }
        if (strcmp($password, $repassword) !== 0) {
            return error('40006', '两次密码不一致');
        }

        $user_mobile = DB::table('users')->where('user_id', $user_id)->pluck('mobile_phone')->first();
        if (empty($user_mobile) || $mobile != $user_mobile) {
            return error('99998', '非法操作');
        }

        $where = [
            ['veri_mobile', $mobile],
            ['veri_number', $msg],
            ['veri_type', 5]
        ];
        $verify = DB::table('verify_num')->where($where)->first();
        if (empty($verify) || $verify->veri_gmt_expire <= time()) {
            return error('20001', '验证码或者手机号不正确');
        }

        $aff_row = DB::table('mq_users_extra')->where('user_id', $user_id)->update(['pay_password' => $password]);
//        if($aff_row){
        success();
//        }else{
//            error('99999','操作失败');
//        }
    }

    /**
     * description:检查是否实名认证
     * @author Harcourt
     * @date 2018/8/1
     */
    public function checkAuthenticate(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $status = DB::table('mq_users_extra')->where('user_id', $user_id)->pluck('status')->first();
        if (empty($status)) {
            return error('40011', '未实名认证');
        } elseif ($status == 2) {
            return error('10005', '实名认证信息已提交,请耐心等待审核');
        }
        success();
    }

    /**
     * description:实名认证
     * @author Harcourt
     * @date 2018/8/1
     */
    public function doAuthentic(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $real_name = $request->input('real_name');
        $card = $request->input('card');
        $face_card = $request->input('face_card');
        $back_card = $request->input('back_card');

//        $user_id = 1;
//        $real_name = '邹里良';
//        $card = '360122198907240937';
//        $face_card = 'appImage/1566112506483.jpg';
//        $back_card = 'appImage/1566112506483.jpg';

        $cardCheck = new \CardAli();
        $result = $cardCheck->sendCard(IMAGE_DOMAIN . $face_card);
        $resultArr = json_decode($result, true);

        if ($resultArr['code'] != 200) {
            return error('40015', '请上传清晰的证件照');
        }

        $checkData = isset($resultArr['data']) ? $resultArr['data'] : [];
        if (!in_array($card, $checkData) || !in_array($real_name, $checkData)) {
            return error('40013', '请填写正确身份信息');
        }

        if (empty($user_id) || empty($real_name) || empty($card) || empty($face_card) || empty($back_card)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();
        if (!$verification->fun_idcard($card)) {
            return error('40012', '请填写正确身份证号');
        }
        $user = DB::table('mq_users_extra')->select('user_id', 'status')->where('user_id', $user_id)->first();
        if (empty($user)) {
            return error('99998', '非法操作');
        }
        if ($user->status == 2) {
            return error('10005', '实名认证信息已提交,请耐心等待审核');

        }
        if ($user->status == 1) {
            return error('40013', '已经实名认过了');
        }
        $hasCard_user_id = DB::table('mq_users_extra')->where('card', $card)->value('user_id');
        if ($hasCard_user_id && $hasCard_user_id != $user_id) {
            return error('40032', '一个身份证号，只能认证一个账号');
        }

        $hinvite_user_id = DB::table('mq_users_extra')->where('user_id', $user_id)->value('invite_user_id');
        addDrawUser(3, $hinvite_user_id, 1);

//        $url = strpos_domain($face_card);
        /*调用验证接口*/
//        $msg = $this->curl_url($url, 1);
//        if ($msg['status'] == 'success') {
//
//            $return = $this->api_check_card($msg['data'], 'face'); // 1验证正面
//
//            if (!$return) {
//                //请求失败
//                return error('40029','请上传了清晰并且正确的身份证照片');
////                show_message('您上传了不够清晰或错误的身份证。', '返回上一页', 'user.php?act=profile');
//            } else {
//
//                $output = json_decode($return, true);
//                $datevalue = json_decode($output['outputs'][0]['outputValue']['dataValue'], true);
//
//                if ($card != $datevalue['num']) {
//                    return error('40030','输入的身份证号与上传身份证照片不一致,请检查后重新上传');
////                    show_message('输入的身份证号与上传身份证照片不一致,请检查后重新上传', '返回上一页', 'user.php?act=profile');
//                }
//            }
//        } else {
//            return error('40031','身份证图片上传失败');
////            show_message('身份证图片上传失败。', '返回上一页', 'user.php?act=profile');
//        }

        $update_data = array(
            'real_name' => $real_name,
            'card' => $card,
            'face_card' => $face_card,
            'back_card' => $back_card,
            'status' => 2
        );
        $aff_row = DB::table('mq_users_extra')->where('user_id', $user_id)->update($update_data);

        if ($aff_row) {
            success();
        } else {
            error('99999', '操作失败');
        }
    }


    /**
     * 身份证识别
     * @param $pic 身份证图片
     * @param $type 1 正面 2反面
     * @return array
     */
    function api_check_card($input, $type)
    {


        /*
        * 参数判断
        */
        if (!$input || !$type) {
            return [];
        }
        /*
         * 接口调用
         */
        $host = "http://dm-51.data.aliyun.com";
        $path = "/rest/160601/ocr/ocr_idcard.json";
        $method = "POST";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . ALIYUN_APPCODE);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type" . ":" . "application/json; charset=UTF-8");
        $querys = "";
        $bodys = "{\"inputs\":[{\"image\":{\"dataType\":50,\"dataValue\":\"$input\"},\"configure\":{\"dataType\":50,\"dataValue\":\"{\\\"side\\\":\\\"$type\\\"}\"}}]}";
//    $bodys = $input;
//    echo $bodys;die();

        $url = $host . $path;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        return curl_exec($curl);
    }

    function curl_url($url, $type = 0, $timeout = 30)
    {

        $msg = ['code' => 2100, 'status' => 'error', 'msg' => '未知错误！'];
        $imgs = ['image/jpeg' => 'jpeg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/png' => 'png',
            'text/html' => 'html',
            'text/plain' => 'txt',
            'image/pjpeg' => 'jpg',
            'image/x-png' => 'png',
            'image/x-icon' => 'ico',
        ];
//	if(!stristr($url,'https')  ){
        //		$msg['code']= 2101;
        //		$msg['msg'] = 'url地址不正确!';
        //		return $msg;
        //	}
        $dir = pathinfo($url);
        //var_dump($dir);
        $host = $dir['dirname'];
        $refer = $host . '/';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_REFERER, $refer); //伪造来源地址
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //返回变量内容还是直接输出字符串,0输出,1返回内容
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1); //在启用CURLOPT_RETURNTRANSFER的时候，返回原生的（Raw）输出
        curl_setopt($ch, CURLOPT_HEADER, 0); //是否输出HEADER头信息 0否1是
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); //超时时间
        $data = curl_exec($ch);
        //$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        //$httpContentType = curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
        $info = curl_getinfo($ch);
        curl_close($ch);
        $httpCode = intval($info['http_code']);
        $httpContentType = $info['content_type'];
        $httpSizeDownload = intval($info['size_download']);

        if ($httpCode != '200') {
            $msg['code'] = 2102;
            $msg['msg'] = 'url返回内容不正确！';
            return $msg;
        }
        if ($type > 0 && !isset($imgs[$httpContentType])) {
            $msg['code'] = 2103;
            $msg['msg'] = 'url资源类型未知！';
            return $msg;
        }
        if ($httpSizeDownload < 1) {
            $msg['code'] = 2104;
            $msg['msg'] = '内容大小不正确！';
            return $msg;
        }
        $msg['code'] = 200;
        $msg['status'] = 'success';
        $msg['msg'] = '资源获取成功';
        if ($type == 0 or $httpContentType == 'text/html') {
            $msg['data'] = $data;
        }

        $base_64 = base64_encode($data);
        if ($type == 1) {
            $msg['data'] = $base_64;
        } elseif ($type == 2) {
            $msg['data'] = "data:{$httpContentType};base64,{$base_64}";
        } elseif ($type == 3) {
            $msg['data'] = "<img src='data:{$httpContentType};base64,{$base_64}' />";
        } else {
            $msg['msg'] = '未知返回需求！';
        }
        unset($info, $data, $base_64);
        return $msg;

    }

    /**
     * description:意见反馈
     * @author Harcourt
     * @date 2018/8/1
     */
    public function doFeedBack(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $content = $request->input('content');
        $imgs = $request->input('imgs');
        if (empty($user_id) || empty($content)) {
            return error('00000', '参数不全');
        }
        if ($imgs) {
            $imgsArr = json_decode($imgs, true);
            $imgs = implode('|', $imgsArr);
        }
        $insert_data = array(
            'user_id' => $user_id,
            'fb_content' => base64_encode($content),
            'fb_imgs' => $imgs,
            'fb_gmt_create' => time()
        );
        $insert_id = DB::table('feedbacks')->insertGetId($insert_data, 'fb_id');
        if ($insert_id) {
            success();
        } else {
            error('99999', '操作失败');
        }
    }

    /**
     * description:账户激活
     * @author Harcourt
     * @date 2018/8/17
     */
    public function logout(Request $request)
    {
        $user_id = $request->input('user_id', 0);

        $update_data = [
            'clientid' => '',
            'device' => ''
        ];
        DB::table('users')->where('user_id', $user_id)->update($update_data);
        success();
    }


}
