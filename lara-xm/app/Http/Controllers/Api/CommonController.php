<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use ShaoZeMing\GeTui\Facade\GeTui;

class CommonController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate')->only(['checkIP']);
    }

    /**
     * description:检查兑换中心是否关闭
     * @author Harcourt
     * @date 2018/9/17
     */
    public function dealContent(Request $request)
    {
        $type = $request->input('type', 1);
        $content = DB::table('trading_hall_explain')->where('type', $type)->first();
        $data = [
            'content' => $content->content
        ];
        success($data);
    }

    /**
     * description:是否要审核
     * @author Harcourt
     * @date 2018/9/11
     */
    public function checkEnv()
    {

        $data = [
            'env' => '1',//1、审核中2、未审核
            'version' => '2.3.5',//稳定版本号
            'isUpdate' => '0',//是否强制更新0、否1、是
            'latestVersion' => '20',//安卓最新版本
            'url' => 'https://www.pgyer.com/2QQ4'
        ];
        success($data);
    }

    /**
     * description:检查兑换中心是否关闭
     * @author Harcourt
     * @date 2018/9/17
     */
    public function checkTradeClosed()
    {
        $config = DB::table('master_config')->where('code', 'xm_trade_switch')->first();
        $isClosed = '0';
        $notes = '';
        if ($config && $config->value == 1) {
            $notes = $config->notes;
            $isClosed = '1';
        }
        $data = [
            'isClosed' => $isClosed,
            'notes' => $notes
        ];
        success($data);
    }

    /**
     * description:短信验证
     * @author Harcourt
     * @date 2018/7/18
     */
    public function sendMsg(Request $request)
    {
        //1、用户注册验证码2、登录确认验证码3、手机号改绑4、身份验证用户密码修改
        $u_mobile = $request->input('u_mobile');
        $type = $request->input('type', '1');
        $verification = new \Verification();
        if (empty($u_mobile) || !in_array($type, array('1', '2', '3', '4', '5'))) {
            return error('00000', '请求参数不全');
        }
        if (!$verification->fun_phone($u_mobile)) {
            return error('01000', '请输入合法的手机号码');
        }
        $verify_num = rand('100000', '999999');


        $expire = time() + 5 * 60;


        $exitMsg = DB::table('verify_num')->where([[
            'veri_mobile', $u_mobile
        ], ['veri_type', $type]])->first();

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
                success('', '短信发送成功');
                break;
            case 'isv.BUSINESS_LIMIT_CONTROL':
                error('00002', '您发送验证码太过于频繁，请稍后重试');
                break;
            default:
                error('00001', '短信发送次数被限制，请稍后重试');
                break;
        }


    }

    /**
     * description:身份验证
     * @author Harcourt
     * @date 2018/8/8
     */
    public function validateMsg(Request $request)
    {
        $mobile = $request->input('mobile');
        $msg = $request->input('msg');
        if (empty($mobile) || empty($msg)) {
            return error('00000', '请求参数不全');
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
        success();
    }

    /**
     * description:获取所有地址
     * @author Harcourt
     * @date 2018/8/10
     */
    public function getRegion()
    {
        $regions = DB::table('region')->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        $tree = array();
        foreach ($regions as $region) {
            $tree[$region['region_id']] = $region;
            $tree[$region['region_id']]['children'] = array();
        }
        foreach ($tree as $key => $item) {
            if ($item['parent_id'] != 0) {
                $tree[$item['parent_id']]['children'][] = &$tree[$key];//注意：此处必须传引用否则结果不对
                if ($tree[$key]['children'] == null) {
                    unset($tree[$key]['children']); //如果children为空，则删除该children元素（可选）
                }
            }
        }
        foreach ($tree as $key => $value) {
            if ($value['parent_id'] != 0) {
                unset($tree[$key]);
            }
        }
        echo json_encode($tree[1]['children']);

    }

    /**
     * description:操作提示
     * @author Harcourt
     * @date 2018/8/17
     */
    public function getTips()
    {

        $masterConfigs = DB::table('master_config')->get();
        $activate_tip = "操作提示:\n";
        foreach ($masterConfigs as $masterConfig) {
            if ($masterConfig->code == 'precharge_propo') {
                $activate_rate = $masterConfig->value;
            }
            if ($masterConfig->code == 'precharge_min') {
                $min = $masterConfig->value;
            }
            if ($masterConfig->code == 'precharge_max') {
                $max = $masterConfig->value;
            }

        }
        $activate_tip .= '1、H积分和消费积分比例为' . $activate_rate . "\n" . '2、输入值相加后数值必须是100的整数倍，且范围在' . $min . '-' . $max . "之间\n";
        $data['activate_tip']['tip'] = $activate_tip;
        $data['activate_tip']['total_min'] = $min;
        $data['activate_tip']['total_max'] = $max;
        $data['activate_tip']['m_consume_rate'] = $activate_rate;

        $shop_configs = DB::table('shop_config')->where('parent_id', 54)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        $code = array_column($shop_configs, 'code');
        $value = array_column($shop_configs, 'value');
        $shopConfigs = array_combine($code, $value);

//        if($shopConfigs['xm_transfer_cash_close']){
//            $tip_limit = "5、当前禁止转账\n";
//        }else{
//            $tip_limit = '';
//        }
        $tip_limit = '';
        if ($shopConfigs['xm_cash_large_transfer']) {
            if ($tip_limit) {
                $large_limit_no = '6、';
            } else {
                $large_limit_no = '5、';
            }
            $large_limit = '大金额转账验证,超过' . $shopConfigs['xm_cash_large_amount_value'] . '需后台验证才能到达目标账户，且期间无法使用此功能';
        } else {
            $large_limit = '';
            $large_limit_no = '';
        }
        $data['transfer_tip']['tip'] = "操作提示:\n1、T积分转账只能转给团队内的人,转账服务费为" . $shopConfigs['xm_transfer_rate_cash_fee'] . "%\n2、T积分可以转入H积分进行购买H单\n3、T积分可到积分市场进行求购或者出售\n4、转账金额为100的整数倍,如200，1000\n" . $tip_limit . $large_limit_no . $large_limit;
        $data['transfer_tip']['is_close'] = $shopConfigs['xm_transfer_cash_close'];
        $data['transfer_tip']['daily_limit'] = $shopConfigs['xm_cash_large_amount_value'];

        $data['transferM_tip']['tip'] = "操作提示:\n1、H积分转账只能转给团队内的人,转账服务费为" . $shopConfigs['xm_transfer_rate_cash_fee'] . "%\n2、H积分可用于购买H单，激活团队，积分倍增\n3、H积分积分可转入T积分每天1%释放到T积分\n4、转账金额为100的整数倍,如200，1000\n" . $large_limit_no . $large_limit;
        $data['transferM_tip']['is_close'] = $shopConfigs['xm_transfer_cash_close'];
        $data['transferM_tip']['daily_limit'] = $shopConfigs['xm_cash_large_amount_value'];

        $xm_tip = "操作提示:\n1、H积分可用于购买H单\n2、H积分可用于激活团队，积分倍增\n3、H积分积分可转入T积分每天1%释放到T积分";

        if (array_key_exists('xm_m_close_transfer', $shopConfigs) && $shopConfigs['xm_m_close_transfer'] == 0) {
            $xm_tip .= "\n4、H积分转账只能转给团队内的人,转账服务费为" . $shopConfigs['xm_transfer_rate_cash_fee'] . "%\n5、转账金额为100的整数倍,如200，1000\n";
            if ($large_limit) {
                $xm_tip .= '6、' . $large_limit;
            }


        }
        $data['xm_tip']['tip'] = $xm_tip;

        success($data);
    }

    /**
     * description:app注册协议
     * @author Harcourt
     * @date 2018/9/11
     */
    public function getProtocol()
    {
        $content = DB::table('trading_hall_explain')->where('type', 3)->value('content');

        return view('api.description', ['des' => $content]);
    }

    /**
     * description:web注册协议
     * @author Harcourt
     * @date 2018/9/11
     */
    public function getWebProtocol()
    {
        $content = DB::table('trading_hall_explain')->where('type', 3)->value('content');
        echo $content;
//        return view('api.protocol',['des'=>$content]);
    }


    /**
     * description:检查ip
     * @author Harcourt
     * @date 2018/9/8
     */
    public function checkIP(Request $request)
    {
        $user_id = $request->input('user_id', 0);

        $where = [
            ['user_id', $user_id],
            ['belong_sys', 2]
        ];
        $safeCheck = DB::table('ip_safecheck_log')->where($where)->first();
        $needMsg = '0';
        if (empty($safeCheck)) {
            $needMsg = '1';
        } else {
            $ip = get_client_ip();

            if (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= time()) {
                $needMsg = '1';
            }

        }
        $data = [
            'needMsg' => $needMsg
        ];
        success($data);
    }


    /**
     * 只保留字符串首尾字符，隐藏中间用*代替（两个字符时只显示第一个）
     * @param string $user_name 姓名
     * @return string 格式化后的姓名
     */
    function substr_cut($user_name)
    {
        $strlen = mb_strlen($user_name, 'utf-8');
        $firstStr = mb_substr($user_name, 0, 1, 'utf-8');
        $lastStr = mb_substr($user_name, -1, 1, 'utf-8');
        return $strlen == 2 ? $firstStr . str_repeat('*', mb_strlen($user_name, 'utf-8') - 1) : $firstStr . str_repeat("*", $strlen - 2) . $lastStr;
    }


    function getPrize($rate, $lottery)
    {
        $prize_arrs = DB::table('lottery')->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        $arr = array_column($prize_arrs, 'lo_rate', 'lo_position');
        $actor = 100;
        $sum = array_sum($arr) * $actor;
        foreach ($arr as &$v) {
//            $v = $v*$actor*$rate;
            $v = $v * $rate;
        }


        asort($arr);

        $result = '';

        //概率数组的总概率精度
        $proSum = array_sum($arr);

        //概率数组循环
        foreach ($arr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }

        $iswin = '0';
        $prizeAmount = '0';
        if ($result - 1 < 0) {
            $data = [
                'iswin' => $iswin,
                'prizeAmount' => $prizeAmount,
                'pos' => '1',
            ];
            return $data;
        }

        $prize = $prize_arrs[$result - 1];


        //按钮 1、苹果2、橙子3、菠萝4、西瓜5、双7 6、BAR
        $pos = $prize['lo_position'];
        $target = 0;
        if (in_array($pos, [4, 7, 8, 15])) {
            //苹果
            $target = 1;
        } elseif (in_array($pos, [1, 9, 10])) {
            //橙子
            $target = 2;
        } elseif (in_array($pos, [11, 12, 16])) {
            //菠萝
            $target = 3;
        } elseif (in_array($pos, [5, 6])) {
            //西瓜
            $target = 4;
        } elseif (in_array($pos, [13, 14])) {
            //双7
            $target = 5;
        } elseif (in_array($pos, [2, 3])) {
            //BAR
            $target = 6;
        }
        $posAmounts = array_column($lottery, 'amount', 'pos');

        if (array_key_exists($target, $posAmounts)) {
            $prizeAmount = $prize['lo_multiple'] * $posAmounts[$target];
            $iswin = '1';
        }
        $data = [
            'iswin' => $iswin,
            'prizeAmount' => $prizeAmount,
            'pos' => $pos
        ];
        return $data;

    }

    function get_rand($rate, $proArr)
    {
        $result = '';

        //概率数组的总概率精度
        $proSum = array_sum($proArr);

        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }


        return $result;
    }

    public function test(Request $request)
    {
        $order_no = $request->input('order_no');
        $tokenName = $request->input('token');
        if (empty($order_no)) {
            echo "no debug";
            exit();
        }

        if ($tokenName != 'huodan6379') {
            echo "no debug";
            exit();
        }

        $redis = app('redis.connection');
        $redis->rpush('orderPay', $order_no);
        echo 123213;
        die;
        dd(rtrim('this is a test, trat,', ','));
        $str = '2018-10-5';//2018-10-02 00:00:00
//        dd(strtotime($str));//1538323200
        $b = strtotime($str) - 15 * 24 * 60 * 60;//2018-09-16 00:00:00
        dd(date('Y-m-d H:i:s', $b));
        dd(url('api/share/url?remobile=15757166509'));
        dd('this is a test');

        $num = 311;//18660
        $res = [];
        $lottery = [
            ['pos' => '1', 'amount' => '10'],
            ['pos' => '2', 'amount' => '10'],
            ['pos' => '3', 'amount' => '10'],
            ['pos' => '4', 'amount' => '10'],
            ['pos' => '5', 'amount' => '10'],
            ['pos' => '6', 'amount' => '10'],
        ];
        $rate = 1;
        for ($i = 0; $i < $num; $i++) {
            $res[] = $this->getPrize($rate, $lottery);
        }
        $prizeAmounts = array_column($res, 'prizeAmount');
        $sum = array_sum($prizeAmounts);
//       10 [13190,13710,10550,12690,13400,14940,12840,13290,13030]
//       5 [13740,11370,12380,11840,14250,14440,13500,15980,15210]
//       1 [11670,14860,12920,12300,13290,13580,13140,14320,12100]

        dd($sum);

//         $users = DB::table('users')->where('user_id','>',22558)->get();
//        $serverapi = new \ServerAPI();
//
//        foreach ($users as $user) {
//            $rong_chat = $serverapi->getToken($user->user_id, $user->nickname, strpos_domain($user->headimg));
//
//            $rong_chat = json_decode($rong_chat);
//            $data = ['chat_token'=>$rong_chat->token];
//            DB::table('users')->where('user_id',$user->user_id)->update($data);
//            echo $user->user_id,'<br />'.$rong_chat->token,'<br />';
//        }
//        $group = DB::table('group_chat')->where('gc_id',37)->first();
//        $uids = json_decode($group->gc_uid,true);
//        if(in_array(22589,$uids)){
//            echo 'yes';
//        }else{
//            echo 'no';
//        }
//        dd($group);


//        $serverapi = new \ServerAPI();
//        $group_chats = DB::table('group_chat')->where('gc_delete',1)->get();
////        dd($group_chats);
//        foreach ($group_chats as $group_chat) {
////            $gcuids = json_decode($group_chat->gc_uid,true);
////
////            if(in_array(21298,$gcuids)){
////                continue;
////            }
//            $user_id = $group_chat->user_id;
//            $gc_id = $group_chat->gc_id;
//            $targetUserIds = ['13911'];
//
////            $ret = $serverapi->groupJoin($targetUserIds, $gc_id, $group_chat->gc_title);
////
////            $ret = json_decode($ret, TRUE);
////
////
////            if ($ret && $ret['code'] == '200') {
//                $user = DB::table('users')->where('user_id',$group_chat->user_id)->first();
//                $targetUserDisplayNames = ['dg888'];
//                $content = array(
//                    'operatorUserId' => (string)$user_id,
//                    'operation' => 'Add',
//                    'data' => array(
//                        'operatorNickname' => $user->nickname,
//                        'targetUserIds' => $targetUserIds,
//                        'targetUserDisplayNames' => $targetUserDisplayNames
//                    ),
//                    "message" =>   "dg888已经进群了",
//                    'extra' => ''
//
//                );
//                $content = json_encode($content);
//                $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:GrpNtf', $content);
////                echo $group_chat->gc_id,'<br />';
////            }
//
//        }


//        $a = [32,63,65,75];
//        $res = DB::table('trade')->where('trade_status','<',2)->update(['trade_status'=>4]);
//        dd($res);
//        $trades = DB::table('trade')->where('trade_status','<',2)->get();
//        foreach ($trades as $trade) {
//            $total_num = round($trade->trade_num *(1 + $trade->cost_rate/100),2);
//            $user_id = $trade->user_id;
////            echo $total_num.'==='.$user_id,'<br />';
//            DB::update('UPDATE xm_tps set unlimit = unlimit + ?,freeze = freeze - ? WHERE user_id = ?',[$total_num,$total_num,$user_id]);
//
//        }
        //打印sql；
//        DB::connection()->enableQueryLog();
//
//        $log = DB::getQueryLog();
//        dd($log);


    }

    /**
     * description:找出所有上级
     * @author douhao
     * @date 2018/8/24
     */
    private function get_up($user_id, &$str = '')
    {
        $user_info = DB::table('mq_users_extra')->select('invite_user_id', 'user_id')->where('user_id', '=', $user_id)->first();
        if ($user_info->invite_user_id != 0) {
            $str .= $user_info->invite_user_id . ',';
            $this->get_up($user_info->invite_user_id, $str);
        }
        return $str;
    }

    /**
     * description:web注册协议
     * @author libaowei
     * @date 2019/9/12
     */
    public function web_getProtocol()
    {
        $content = DB::table('trading_hall_explain')->where('type', 3)->value('content');
        $content = str_replace("用户协议", "", $content);
        $content = str_replace("<p>&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; </p>", "", $content);

        success($content);
    }


}
