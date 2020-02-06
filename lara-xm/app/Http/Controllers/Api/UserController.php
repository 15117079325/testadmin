<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UserController extends Controller
{
    public function __construct()
    {
//        $this->middleware('userLoginValidate')->except(['info']);
    }

    /**
     * description:用户信息
     * @author Harcourt
     * @date 2018/8/7
     */
    public function info(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }

        $user = DB::table('users')->select('user_id', 'user_name', 'nickname', 'headimg', 'sex', 'birthday', 'mobile_phone')->where('user_id', $user_id)->first();

        if (empty($user)) {
            return error('99998', '非法操作');
        }
        $user->headimg = strpos_domain($user->headimg);
        $user_extra = DB::table('mq_users_extra')->select('status', 'invite_user_id', 'user_cx_rank')->where('user_id', $user_id)->first();
        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }

        $has_invite = '0';
        $inviteName = '';
        if ($user_extra->invite_user_id) {
            $has_invite = '1';
            $inviteName = DB::table('users')->where('user_id', $user_extra->invite_user_id)->pluck('user_name')->first();
        }

        $degree = $user_extra->user_cx_rank;
        switch ($degree) {
            case USER_RANK_NORMAL:
                $degreeDes = '普通会员';
                break;
            case USER_RANK_PRIMARY:
                $degreeDes = '初级会员';
                break;
            case USER_RANK_MIDDLE:
                $degreeDes = '中级会员';
                break;
            case USER_RANK_SENIOR:
                $degreeDes = '高级会员';
                break;
        }
        $user->status = $user_extra->status;
        $user->degree = $degreeDes;
        $user->has_invite = $has_invite;
        $user->invite_name = $inviteName;

        $owhere = [
            ['user_id', $user_id],
            ['order_status', '<', 5],
            ['order_cancel', 1],
            ['order_delete', 1]
        ];
        $order_counts = DB::table('orders')->selectRaw('order_status,count(*) as num')->where($owhere)->groupBy('order_status')->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        $res = [];


//        1、待支付2、待发货3、待收货4、待评价5、已完成
        foreach ($order_counts as $order_count) {
            if ($order_count['order_status'] == 1) {
                $keyName = 'waitPay_num';
            } elseif ($order_count['order_status'] == 2) {
                $keyName = 'waitSend_num';
            } elseif ($order_count['order_status'] == 3) {
                $keyName = 'waitSure_num';
            } else {
                $keyName = 'waitComment_num';
            }
            $res[$keyName] = $order_count['num'];
        }
        $keyNames = ['waitPay_num', 'waitSend_num', 'waitSure_num', 'waitComment_num'];
        $keyNum = count($keyNames);

        for ($i = 0; $i < $keyNum; $i++) {
            if (!array_key_exists($keyNames[$i], $res)) {
                $res[$keyNames[$i]] = 0;
            }
        }

        $user->order_counts = $res;

        success($user);


    }

    /**
     * description:修改头像
     * @author Harcourt
     * @date 2018/8/8
     */
    public function modifyInfo(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type');
        $info = $request->input('info');
        if (empty($user_id) || !in_array($type, array('1', '2', '3', '4'))) {
            return error('00000', '参数不全');
        }
        if ($type == 1) {
            $key = 'headimg';
        } elseif ($type == 2) {
            $key = 'birthday';
        } elseif ($type == 3) {
            $key = 'sex';
        } else {
            $key = 'nickname';
        }
        $update_data = [$key => $info];
        $aff = DB::table('users')->where('user_id', $user_id)->update($update_data);
        if ($type == 1) {
            $info = strpos_domain($info);
        }
        success($info);

    }

    /**
     * description:改绑手机号
     * @author Harcourt
     * @date 2018/8/8
     */
    public function rebindMobile(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $oldmobile = $request->input('oldmobile');
        $mobile = $request->input('mobile');
        $msg = $request->input('msg');

        if (empty($user_id) || empty($oldmobile) || empty($mobile) || empty($msg)) {
            return error('00000', '参数不全');
        }

        $user_mobile = DB::table('users')->where('user_id', $user_id)->pluck('mobile_phone')->first();
        if (empty($user_mobile) || $oldmobile != $user_mobile) {
            return error('99998', '非法操作');
        }
        $new_id = DB::table('users')->where('mobile_phone', $mobile)->pluck('user_id')->first();
        if ($new_id) {
            return error('10002', '用户已存在');
        }
        $where = [
            ['veri_mobile', $mobile],
            ['veri_number', $msg],
            ['veri_type', 5]
        ];
        $verify = DB::table('verify_num')->where($where)->first();
        if (empty($verify) || $verify->ver_gmt_expire <= time()) {
            return error('20001', '验证码或者手机号不正确');
        }
        $update_data = ['mobile_phone' => $mobile];
        $insert_data = [
            'user_id' => $user_id,
            'rb_old_mobile' => $oldmobile,
            'rb_new_mobile' => $mobile,
            'rb_gmt_create' => time()
        ];
        DB::beginTransaction();
        DB::table('users')->where('user_id', $user_id)->update($update_data);
        $insert_id = DB::table('rebind_mobile')->insertGetId($insert_data, 'rb_id');
        if (empty($insert_id)) {
            DB::rollBack();
            error('99999', '操作失败');
        } else {
            DB::commit();
            success();
        }
    }

    /**
     * description:每日签到
     * @author Harcourt
     * @date 2018/8/14
     */
    public function signIn(Request $request)
    {
        $user_id = $request->input('user_id', 0);

        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $now = time();

        $nowDay = date('Y-m-d', $now);
        $where = [
            ['si_gmt_create', '>=', strtotime($nowDay)],
            ['si_gmt_create', '<', strtotime($nowDay . '+ 1 day')],
            ['user_id', $user_id]
        ];
        $sign = DB::table('sign_daily')->where($where)->first();
        if ($sign) {
            return error('20004', '今天已经签到过了');
        }


        $daily_sign_consume = DB::table('master_config')->where('code', 'daily_sign_consume')->pluck('value')->first();
        if ($daily_sign_consume === null) {
            return error('99997', '暂时无法操作');
        }

        DB::beginTransaction();

        DB::table('tps')->where('user_id', $user_id)->increment('shopp', $daily_sign_consume);

        $sign_data = [
            'user_id' => $user_id,
            'si_amount' => $daily_sign_consume,
            'si_gmt_create' => $now
        ];
        $si_id = DB::table('sign_daily')->insertGetId($sign_data, 'si_id');
        $surplus = DB::table('tps')->where('user_id', $user_id)->pluck('shopp')->first();

        $flow_data = [
            'user_id' => $user_id,
            'type' => 3,
            'status' => 1,
            'amount' => $daily_sign_consume,
            'create_at' => $now,
            'surplus' => $surplus,
            'notes' => '每天签到获得消费积分'
        ];
        $foid = DB::table('flow_log')->insertGetId($flow_data);

        if (empty($si_id) || empty($foid)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            return success(['consume_score' => (string)$daily_sign_consume], '签到成功,获得' . $daily_sign_consume . '消费积分');
        }


    }

    /**
     * description:用户账户信息
     * @author Harcourt
     * @date 2018/8/17
     */
    public function score(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $user_account = DB::table('user_account')->where('user_id', $user_id)->first();
        if (empty($user_account)) {
            return error('99998', '非法操作');
        }
        $result = [
            'total_balance' => (string)round($user_account->balance + $user_account->release_balance + $user_account->pending_balance, 2),
            'useable_balance' => $user_account->balance,
            'release_balance' => $user_account->release_balance,
        ];

        success($result);
    }

    public function score_old(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $res = DB::table('xps')->select('xps.amount as total_m_score', 'xps.frozen as frozen_m_score', 'xps.unlimit as useable_m_score', 'xps.reling as reling_m_score', 'tps.unlimit as total_t_score', 'tps.shopp as useable_consume_score', 'tps.gold_pool as total_gold_pool')->join('tps', 'xps.user_id', '=', 'tps.user_id')->where('xps.user_id', $user_id)->first();
        if (empty($res)) {
            return error('99998', '非法操作');
        }
        $isCloseM = DB::table('shop_config')->where('code', 'xm_m_close_transfer')->value('value');
        if ($isCloseM == null) {
            $isCloseM = '0';
        }
        if ($isCloseM == 0) {
            $new_status = DB::table('mq_users_extra')->where('user_id', $user_id)->value('new_status');

            if (empty($new_status)) {
                return error('99998', '非法操作');
            }
            $my_ret = explode('-', $new_status); // 我的服务商是谁

            if (count($my_ret) == 5 && $my_ret[3] == 1) {
                $isCloseM = 1;
            }
        }
        $res->is_m_close = $isCloseM;

        success($res);
    }

    /**
     * description:账号激活
     * @author Harcourt
     * @date 2018/8/17
     */
    public function activate(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $account = $request->input('account');
        $m_score = $request->input('m_score');
        $consume_score = $request->input('consume_score');
        $password = $request->input('password');

        if (empty($user_id) || empty($account) || empty($m_score) || empty($consume_score) || empty($password)) {
            return error('00000', '参数不全');
        }
        $total_score = $m_score + $consume_score;
        if ($total_score % 100 != 0) {
            return error('99995', '账户激活总数量必须为100的整数倍');
        }

        $masterConfigs = DB::table('master_config')->where('tip', 'c')->get();

        if (empty($masterConfigs)) {
            return error('99998', '非法操作');
        }

        $rate = $m_score / $consume_score;
        $min = '0';
        $max = '0';
        $activateRate = '0';
        $totalRate = '0';
        $firstRate = '0';
        $leftRate = '0';
        $couponRate = '0';
        foreach ($masterConfigs as $masterConfig) {
            if ($masterConfig->code == 'precharge_min') {
                $min = $masterConfig->value;
            }
            if ($masterConfig->code == 'precharge_max') {
                $max = $masterConfig->value;
            }
            if ($masterConfig->code == 'precharge_propo') {
                $aRate = explode(':', $masterConfig->value);
                if (count($aRate) == 2) {
                    $activateRate = $aRate[0] / $aRate[1];
                }
            }
            if ($masterConfig->code == 'surplus_propo') {
                $aRate = explode(':', $masterConfig->value);
                if (count($aRate)) {
                    $firstRate = $aRate[0];
                    $totalRate = array_sum($aRate);
                    $leftRate = $totalRate - $firstRate;
                }
            }
            if ($masterConfig->code == 'coupon_propo') {
                $aRate = explode(':', $masterConfig->value);
                if (count($aRate) == 2) {
                    $couponRate = $aRate[1];
                }
            }

        }
        if ($total_score < $min || $total_score > $max || $rate != $activateRate) {
            return error('99995', '消费激活总数量范围' . $min . '~' . $max);
        }


        $user_extra = DB::table('mq_users_extra')->select('user_status', 'user_cx_rank', 'invite_user_id', 'new_status', 'pay_password', 'xps.unlimit as useable_m_score', 'tps.shopp as useable_consume_score')->join('xps', 'xps.user_id', '=', 'mq_users_extra.user_id')->join('tps', 'tps.user_id', '=', 'mq_users_extra.user_id')->where('mq_users_extra.user_id', $user_id)->first();

        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }
        $userName = DB::table('users')->where('user_id', $user_id)->pluck('user_name')->first();
        if (empty($userName)) {
            return error('99998', '非法操作');

        }
        if ($user_extra->user_cx_rank == 0) {
            return error('40015', '自己的账号未激活，无法帮别人激活');
        }
        $to_user = DB::table('users')->select('user_cx_rank', 'users.user_id', 'invite_user_id', 'team_number')->join('mq_users_extra', 'mq_users_extra.user_id', '=', 'users.user_id')->where('user_name', $account)->first();
        if (empty($to_user)) {
            return error('40016', '被激活账号不存在');
        }
        if ($to_user->user_id == $user_id) {
            return error('40017', '只能帮别人激活');
        }
        $isActivate = '0';
        if (empty($to_user->user_cx_rank)) {
            $isActivate = '1';

        }
        if (empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (strcmp($password, $user_extra->pay_password) !== 0) {
            return error('40005', '支付密码不正确');
        }

        if ($user_extra->useable_m_score < $m_score || $user_extra->useable_consume_score < $consume_score) {
            return error('40014', '余额不足');
        }
        $toUser_tps = DB::table('tps')->select('coupon as left_coupon', 'shopp as useable_consume', 'surplus as wait_consume')->where('user_id', $to_user->user_id)->first();

        //xm_customs_apply
        //xm_flow_log
        //xm_tps
        //xm_xps
        $redis_name = 'userActivate-' . $user_id;
        if (Redis::exists($redis_name)) {
            return error('99994', '处理中...');
        } else {
            Redis::set($redis_name, '1');
        }
        $now = time();
        DB::beginTransaction();
        $customs_appay = [
            'from_user_id' => $user_id,
            'to_user_id' => $to_user->user_id,
            'xpoints' => $m_score,
            'cpoints' => $consume_score,
            'surplus' => $leftRate * $total_score,
            'surpro' => $leftRate,
            'points' => $totalRate * $total_score,
            'create_at' => $now,
            'update_at' => $now
        ];
        $customs_appay_id = DB::table('customs_apply')->insertGetId($customs_appay, 'id');
        if (empty($customs_appay_id)) {
            DB::rollBack();
            Redis::del($redis_name);

            return error('99999', '操作失败');
        }
        DB::update('UPDATE xm_xps SET amount = amount - ?,unlimit = unlimit - ?  WHERE user_id = ?', [$m_score, $m_score, $user_id]);
        DB::update('UPDATE xm_tps SET shopp = shopp - ? WHERE user_id = ?', [$consume_score, $user_id]);

        DB::update('UPDATE xm_tps SET coupon = coupon + ?,shopp = shopp + ?,surplus = surplus + ? WHERE user_id = ?', [$total_score * $couponRate, $total_score * $firstRate, $total_score * $leftRate, $to_user->user_id]);


        $flow_data = [
            'user_id' => $user_id,
            'amount' => $m_score,
            'type' => 1,
            'status' => 2,
            'surplus' => $user_extra->useable_m_score - $m_score,
            'notes' => '消费激活-' . $account,
            'create_at' => $now,
            'target_type' => 3,
            'target_id' => $customs_appay_id
        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        $flow_data['amount'] = $consume_score;
        $flow_data['type'] = 3;
        $flow_data['surplus'] = $user_extra->useable_consume_score - $consume_score;
        $flow_data['notes'] = '消费激活-' . $account;
        $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        $flow_data['status'] = 1;
        $flow_data['target_type'] = 2;
        $flow_data['user_id'] = $to_user->user_id;
        $flow_data['notes'] = '被消费激活-' . $userName;
        $flow_data['amount'] = $total_score * $firstRate;
        $flow_data['surplus'] = $toUser_tps->useable_consume + $total_score * $firstRate;
        $foid3 = DB::table('flow_log')->insertGetId($flow_data, 'foid');


        //被消费激活赠送购物券-
        $flow_data['type'] = 5;
        $flow_data['notes'] = '被消费激活赠送购物券-' . $userName;
        $flow_data['amount'] = $total_score * $couponRate;
        $flow_data['surplus'] = $toUser_tps->left_coupon + $total_score * $couponRate;
        $foid4 = DB::table('flow_log')->insertGetId($flow_data, 'foid');


        //待释放 被消费激活剩余积分-
        $flow_data['type'] = 6;
        $flow_data['notes'] = '被消费激活剩余积分-' . $userName;
        $flow_data['amount'] = $total_score * $leftRate;
        $flow_data['surplus'] = $toUser_tps->wait_consume + $total_score * $leftRate;
        $foid5 = DB::table('flow_log')->insertGetId($flow_data, 'foid');


        if (empty($foid1) || empty($foid2) || empty($foid3) || empty($foid4) || empty($foid5)) {
            DB::rollBack();
            Redis::del($redis_name);

            error('99999', '操作失败');
        }

        //1、$to_user->user_id 那层团队奖
        $bol = $this->bonusWeishang((array)$to_user, $m_score, $account, $customs_appay_id, 2, $isActivate);
        if ($isActivate) {
            DB::table('mq_users_extra')->where('user_id', $to_user->user_id)->update(['user_cx_rank' => 1]);
        }
        if ($bol) {
            DB::commit();
            Redis::del($redis_name);

            success();
        } else {
            DB::rollBack();
            Redis::del($redis_name);
            error('99999', '操作失败');
        }


    }

    /**
     * @param $user_extra2 被报单人用户信息
     * @param $cash_money 报单使用新美积分部分
     */
    function bonusWeishang($user_extra2, $cash_money, $user_name, $target_id, $target_type, $isActivate)
    {

        $percent_3w = 5; //3w提成
        $percent_10w = 10;//10w提成
        $percent_30w = 15;//30w提成

        $percent_rest = 0; //剩余可分派点数
        $last_rank = 0;  //上一个等级
        $current_percent = 0;
        $last_percent = 0;//上一个提成百分比
        $jicha = 0; //级差
        $percent_total = 0;//可分配总点数
        $calc_percent = 0;//实际获得计算的百分比


        $percent_total = max($percent_3w, $percent_10w, $percent_30w);
        $amount = 0;//提成金额
        $percent_rest = $percent_total;
        $now = time();
        while (true) {

            //首次激活，更新团队人数，根据人数升级服务中心
//            if($isActivate && $percent_rest != 0){
//                DB::table('mq_users_extra')->where('user_id',$user_extra2['invite_user_id'])->increment('team_number',1);
//
//                if($user_extra2['team_number'] + 1 >= TEAM_30_NUMBER){
//                    $now_rank = 4;
//                }elseif ($user_extra2['team_number'] + 1 >= TEAM_10_NUMBER){
//                    $now_rank = 3;
//                }elseif ($user_extra2['team_number'] + 1 >= TEAM_3_NUMBER){
//                    $now_rank = 2;
//                }else{
//                    $now_rank = 1;
//                }
//                //原等级不变
//                if($user_extra2['user_cx_rank'] < $now_rank){
//                    DB::table('mq_users_extra')->where('user_id',$user_extra2['invite_user_id'])->update(['user_cx_rank'=>$now_rank]);
//                }
//
//            }

            //送完则退出处理
            if ($percent_rest == 0) {
                while (true) {
                    //更新到奖金池
                    if ($user_extra2['user_cx_rank'] == 4) {
                        $amount1 = 0.01 * $cash_money;
                        $ret = DB::update(' UPDATE xm_tps SET gold_pool=gold_pool+? WHERE user_id=?', [$amount1, $user_extra2['user_id']]);

                        $gold_pool = DB::table('tps')->where('user_id', $user_extra2['user_id'])->pluck('gold_pool')->first();
                        if ($ret) {
                            $notes = $user_name . '消费激活服务商获得平级奖励' . $amount1;
                            $insert_data = [
                                'user_id' => $user_extra2['user_id'],
                                'amount' => $amount1,
                                'surplus' => $gold_pool,
                                'type' => 4,
                                'status' => 1,
                                'notes' => $notes,
                                'create_at' => $now,
                                'target_type' => $target_type,
                                'target_id' => $target_id
                            ];
                            DB::table('flow_log')->insertGetId($insert_data, 'foid');
                        }
                        break;
                    } elseif ($user_extra2['invite_user_id']) {
                        $user_extra2 = $this->getUserExtra($user_extra2['invite_user_id']);
                    } else {
                        break;
                    }


                    if (!$user_extra2) {
                        break;
                    }


                    //已达到顶级用户则退出
                    if (!$user_extra2['invite_user_id']) {
                        break;
                    }
                }
                break;
            }

            //是服务中心
            if (in_array($user_extra2['user_cx_rank'], [2, 3, 4])) {
                if ($user_extra2['user_cx_rank'] > $last_rank) {
                    if ($user_extra2['user_cx_rank'] == 2) {
                        $current_percent = $percent_3w;
                    } else if ($user_extra2['user_cx_rank'] == 3) {
                        $current_percent = $percent_10w;
                    } else if ($user_extra2['user_cx_rank'] == 4) {
                        $current_percent = $percent_30w;
                    }
                    $jicha = $current_percent - $last_percent;
                    //提成
                    $calc_percent = min($percent_rest, $jicha);
                    $amount = $cash_money * $calc_percent / 100;
                    //更新到奖金池
                    $ret = DB::update('UPDATE xm_tps SET gold_pool=gold_pool+? WHERE user_id=?', [$amount, $user_extra2['user_id']]);
                    $gold_pool = DB::table('tps')->where('user_id', $user_extra2['user_id'])->pluck('gold_pool')->first();

                    if ($ret) {
                        $notes = $user_name . '消费激活服务商获得奖励' . $amount;

                        $insert_data = [
                            'user_id' => $user_extra2['user_id'],
                            'amount' => $amount,
                            'surplus' => $gold_pool,
                            'type' => 4,
                            'status' => 1,
                            'notes' => $notes,
                            'create_at' => $now,
                            'target_type' => $target_type,
                            'target_id' => $target_id
                        ];
                        DB::table('flow_log')->insertGetId($insert_data, 'foid');
                    }
                    //剩余信息
                    $last_percent = $current_percent;
                    $last_rank = $user_extra2['user_cx_rank'];
                    $percent_rest = $percent_rest - $calc_percent;

                }

            }
            //已达到顶级永和则退出
            if (!$user_extra2['invite_user_id']) {
                break;
            }
            $user_extra2 = $this->getUserExtra($user_extra2['invite_user_id']);

            if (!$user_extra2) {
                break;
            }

        }

        return true;
    }


    function getUserExtra($user_id)
    {
        $user_extra = DB::table('mq_users_extra')->select('user_id', 'user_cx_rank', 'invite_user_id', 'team_number')->where('user_id', $user_id)->first();
        return (array)$user_extra;
    }


    /**
     * description:团队内转T积分
     * @author Harcourt
     * @date 2018/8/22
     */
    public function transfer(Request $request)
    {
        //不能给自己转账，支付密码extra_users，余额验证xps，对方账户是否存在users，是否属于同一团队new_status
        //个人转账是否限制users_limit flow_log shop_config  transfer_apply
        //1、团队限制2、当日转账额度限制xps
        $user_id = $request->input('user_id', 0);
        $account = $request->input('account');
        $m_score = $request->input('m_score');
        $password = $request->input('password');
        $needMsg = $request->input('needMsg', '0');
        $msg = $request->input('msg', '无');
        if (empty($user_id) || empty($account) || empty($m_score) || empty($password)) {
            return error('00000', '参数不全');
        }
        //查询转账的用户是否实名认证
        $me = DB::table('mq_users_extra')->where([['user_id', $user_id], ['status', '!=', 1]])->first();
        if (isset($me)) {
            return error('10009', '未实名认证，请先认证!');
        }

        if (is_int($m_score < 0)) {
            return error('99995', '转账金额需要大于0');
        }
        $ch_where = [
            ['user_id', $user_id],
            ['belong_sys', 2]
        ];
        // $ip = get_client_ip();
        // $safeCheck = DB::table('ip_safecheck_log')->where($ch_where)->first();
        $now = time();
        // if (empty($safeCheck) && (empty($msg) || empty($needMsg))) {
        //     return error('10008', '需重新认证身份');
        // }
        // if ($safeCheck && (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now) && (empty($msg) || empty($needMsg))) {
        //     return error('10008', '需重新认证身份');
        // }
        $userAccountPhone = DB::table('users')->select('user_name', 'mobile_phone')->where('user_id', $user_id)->first();
        if (empty($userAccountPhone)) {
            return error('99998', '非法操作');
        }
        $userAccount = $userAccountPhone->user_name;
        if (strcmp($userAccount, $account) == 0) {
            return error('40025', '无法给自己转账');
        }
        // if ($needMsg && $msg) {
        //     $where = [
        //         ['veri_mobile', $userAccountPhone->mobile_phone],
        //         ['veri_number', $msg],
        //         ['veri_type', 5]
        //     ];
        //     $verify = DB::table('verify_num')->where($where)->first();

        //     if (empty($verify) || $verify->veri_gmt_expire <= $now) {
        //         return error('20001', '验证码或者手机号不正确');
        //     }

        //     if (empty($safeCheck)) {
        //         //直接身份验证ip插入

        //         $check_insert_data = [
        //             'user_id' => $user_id,
        //             'belong_sys' => 2,
        //             'ip_address' => $ip,
        //             'check_time' => date('Y-m-d H:i:s', $now),
        //             'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)

        //         ];
        //         DB::table('ip_safecheck_log')->insertGetId($check_insert_data, 'log_id');
        //     } else {
        //         if ($safeCheck && (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now)) {
        //             $update_insert_data = [
        //                 'ip_address' => $ip,
        //                 'check_time' => date('Y-m-d H:i:s', $now),
        //                 'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)
        //             ];
        //             DB::table('ip_safecheck_log')->where('log_id', $safeCheck->log_id)->update($update_insert_data);

        //         }


        //     }
        // }

        $user_limit = DB::table('mq_users_limit')->select('cash_limited', 'daily_cash_transfer_sum_limit as daily_limit')->where('user_id', $user_id)->first();
        if (empty($user_limit)) {
            return error('99999', '操作失败');
        }
        if ($user_limit->cash_limited == 1 && $m_score > $user_limit->daily_limit) {
            //有限制
            return error('40021', '超出转账额度,今天无法进行转账');
        }


        $nowDay = date('Y-m-d', $now);
        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');
        $twhere = [
            ['from_user', $user_id],
            ['status', '<', 2],
            ['type', 2],
            ['create_at', '>=', $day_start],
            ['create_at', '<', $day_end],
        ];

        $transfer_applies = DB::table('transfer_apply')->select('amount', 'status')->where($twhere)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        foreach ($transfer_applies as $transfer_apply) {
            if ($transfer_apply['status'] == 0) {
                return error('40022', '转账失败,您还有未审核的转账申请');
            }
        }
        if ($transfer_applies) {
            $totalAmount = array_sum(array_column($transfer_applies, 'amount'));

        } else {
            $totalAmount = 0;
        }
        if ($user_limit->cash_limited == 1 && $totalAmount + $m_score > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }
        $fwhere = [
            ['user_id', $user_id],
            ['type', 2],
            ['create_at', '>=', $day_start],
            ['create_at', '<', $day_end],
            ['notes', 'like', '%转账%']
        ];
        $flowAmount = DB::table('flow_log')->selectRaw('sum(amount) as amount')->where($fwhere)->pluck('amount')->first();
        if ($user_limit->cash_limited == 1 && $flowAmount + $m_score > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }

        $shop_configs = DB::table('shop_config')->select('code', 'value')->where('parent_id', 54)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        $code = array_column($shop_configs, 'code');
        $value = array_column($shop_configs, 'value');
        $shopConfigs = array_combine($code, $value);

        if (array_key_exists('xm_transfer_cash_close', $shopConfigs) && $shopConfigs['xm_transfer_cash_close'] == 1) {
            return error('40023', '转账功能暂时关闭');
        }


        //新美积分转账消耗比 xm_transfer_rate_cash_fee 10
        $costRate = '0';
        if (array_key_exists('xm_transfer_rate_cash_fee', $shopConfigs)) {
            $costRate = $shopConfigs['xm_transfer_rate_cash_fee'];
        }
        //手续费
        $trfee = round($m_score * $costRate / 100, 2);
        $totalM = $m_score + $trfee;

        if ($user_limit->cash_limited == 1 && $flowAmount + $totalM > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }


        $user_extra = DB::table('mq_users_extra')->select('user_status', 'user_cx_rank', 'invite_user_id', 'new_status', 'pay_password', 'user_account.balance as useable_t_score', 'user_account.pending_balance as freeze_t_score')->join('user_account', 'user_account.user_id', '=', 'mq_users_extra.user_id')->where('mq_users_extra.user_id', $user_id)->first();

        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }

        $to_user_extra = DB::table('users')->select('users.user_id', 'mq_users_extra.new_status')->join('mq_users_extra', 'mq_users_extra.user_id', '=', 'users.user_id')->where('user_name', $account)->first();
        if (empty($to_user_extra)) {
            return error('40024', '转账对象不存在');
        }

        $my_ret = explode('-', $user_extra->new_status); // 我的服务商是谁
        $you_ret = explode('-', $to_user_extra->new_status); // 对方的服务商是谁

        if ($my_ret[0] != $you_ret[0]) {
            return error('40026', '不同团队之间无法进行转账');
        }


//        $t_all = DB::table('master_config')->where('code', 'xm_t_all')->value('amount');
//        if ($t_all == null) {
        $t_all = 0;
//        }

        if ($user_extra->useable_t_score < $totalM) {
            return error('40014', '余额不足');
        }
        if (empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (strcmp($password, $user_extra->pay_password) !== 0) {
            return error('40005', '支付密码不正确');
        }


        if (array_key_exists('xm_cash_large_transfer', $shopConfigs) && $shopConfigs['xm_cash_large_transfer'] == 1 && array_key_exists('xm_cash_large_amount_value', $shopConfigs) && $shopConfigs['xm_cash_large_amount_value'] <= $m_score) {
            //开启最大值验证 最大值数额 xm_cash_large_amount_value
            $transfer_apply_data = [
                'from_user' => $user_id,
                'to_user' => $to_user_extra->user_id,
                'type' => 2,
                'amount' => $m_score,
                'trfee' => $trfee,
                'create_at' => $now
            ];
            DB::beginTransaction();
            $trid = DB::table('transfer_apply')->insertGetId($transfer_apply_data, 'trid');
//            $aff_row = DB::update('UPDATE xm_tps SET unlimit = unlimit - ? WHERE user_id = ?',[$m_score,$user_id]);//|| empty($aff_row)
            if (empty($trid)) {
                DB::rollBack();
                return error('99999', '操作失败');
            } else {
                DB::commit();
                $data = [
                    'msg' => '为了您的账户安全本次转账需经过管理员审核才能到账，请耐心等待！'
                ];
                return success($data);
            }

        }
        $to_user_t = DB::table('user_account')->select('balance', 'pending_balance')->where('user_id', $to_user_extra->user_id)->first();
        if ($to_user_t) {
            $toUserTotal_t = $to_user_t->balance + $to_user_t->pending_balance;
        } else {
            $toUserTotal_t = '0';
        }

        $redis_name = 'transfer-' . $user_id;
        if (Redis::exists($redis_name)) {
            return error('99994', '处理中...');
        } else {
            Redis::set($redis_name, '1');
        }

        DB::beginTransaction();
        $aff_row1 = DB::table('user_account')->where('user_id', $user_id)->decrement('balance', $totalM);
        $aff_row2 = DB::table('user_account')->where('user_id', $to_user_extra->user_id)->increment('balance', $m_score);

//        DB::update('UPDATE xm_master_config SET amount = amount + ? WHERE code = ?', [$trfee, 'xm_t_all']);

        $userTotal_t = $user_extra->useable_t_score + $user_extra->freeze_t_score;

        $flow_data = [
            'user_id' => $user_id,
            'type' => 2,
            'status' => 2,
            'amount' => $m_score,
            'surplus' => $userTotal_t - $totalM,
            'notes' => '转账给：' . $account,
            'create_at' => $now,
        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        $flow_data['user_id'] = $to_user_extra->user_id;
        $flow_data['status'] = 1;
        $flow_data['surplus'] = $toUserTotal_t + $m_score;
        $flow_data['notes'] = $userAccount . ' 转账给我';
        $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        if ($trfee) {
            $flow_data['user_id'] = $user_id;
            $flow_data['status'] = 2;
            $flow_data['amount'] = $trfee;
            $flow_data['surplus'] = $userTotal_t - $totalM;
            $flow_data['notes'] = '转账手续费';
            $foid3 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

            $flow_data['user_id'] = 0;
            $flow_data['status'] = 1;
            $flow_data['amount'] = $trfee;
            $flow_data['surplus'] = $t_all + $trfee;
            $flow_data['notes'] = '转账手续费';
            $flow_data['isall'] = 1;
            $foid4 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
            if (empty($foid3) || empty($foid4)) {
                DB::rollBack();
                Redis::del($redis_name);
                return error('99999', '操作失败');
            }
        }


        if (empty($aff_row1) || empty($aff_row2) || empty($foid1) || empty($foid2)) {
            DB::rollBack();
            Redis::del($redis_name);
            return error('99999', '操作失败');
        } else {
            DB::commit();
            Redis::del($redis_name);

            $data = [
                'msg' => '转账成功'
            ];
            return success($data);
        }

    }

    public function transfer_old(Request $request)
    {
        //不能给自己转账，支付密码extra_users，余额验证xps，对方账户是否存在users，是否属于同一团队new_status
        //个人转账是否限制users_limit flow_log shop_config  transfer_apply
        //1、团队限制2、当日转账额度限制xps
        $user_id = $request->input('user_id', 0);
        $account = $request->input('account');
        $m_score = $request->input('m_score');
        $password = $request->input('password');
        $needMsg = $request->input('needMsg', '0');
        $msg = $request->input('msg', '无');
        if (empty($user_id) || empty($account) || empty($m_score) || empty($password)) {
            return error('00000', '参数不全');
        }
        if (!is_int($m_score % 100)) {
            return error('99995', '请按提示填写');
        }
        $ch_where = [
            ['user_id', $user_id],
            ['belong_sys', 2]
        ];
        $ip = get_client_ip();
        $safeCheck = DB::table('ip_safecheck_log')->where($ch_where)->first();
        $now = time();
        if (empty($safeCheck) && (empty($msg) || empty($needMsg))) {
            return error('10008', '需重新认证身份');
        }
        if ($safeCheck && (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now) && (empty($msg) || empty($needMsg))) {
            return error('10008', '需重新认证身份');
        }
        $userAccountPhone = DB::table('users')->select('user_name', 'mobile_phone')->where('user_id', $user_id)->first();
        if (empty($userAccountPhone)) {
            return error('99998', '非法操作');
        }
        $userAccount = $userAccountPhone->user_name;
        if (strcmp($userAccount, $account) == 0) {
            return error('40025', '无法给自己转账');
        }
        if ($needMsg && $msg) {
            $where = [
                ['veri_mobile', $userAccountPhone->mobile_phone],
                ['veri_number', $msg],
                ['veri_type', 5]
            ];
            $verify = DB::table('verify_num')->where($where)->first();

            if (empty($verify) || $verify->veri_gmt_expire <= $now) {
                return error('20001', '验证码或者手机号不正确');
            }

            if (empty($safeCheck)) {
                //直接身份验证ip插入

                $check_insert_data = [
                    'user_id' => $user_id,
                    'belong_sys' => 2,
                    'ip_address' => $ip,
                    'check_time' => date('Y-m-d H:i:s', $now),
                    'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)

                ];
                DB::table('ip_safecheck_log')->insertGetId($check_insert_data, 'log_id');
            } else {
                if ($safeCheck && (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now)) {
                    $update_insert_data = [
                        'ip_address' => $ip,
                        'check_time' => date('Y-m-d H:i:s', $now),
                        'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)
                    ];
                    DB::table('ip_safecheck_log')->where('log_id', $safeCheck->log_id)->update($update_insert_data);

                }


            }
        }

        $user_limit = DB::table('mq_users_limit')->select('cash_limited', 'daily_cash_transfer_sum_limit as daily_limit')->where('user_id', $user_id)->first();
        if (empty($user_limit)) {
            return error('99999', '操作失败');
        }
        if ($user_limit->cash_limited == 1 && $m_score > $user_limit->daily_limit) {
            //有限制
            return error('40021', '超出转账额度,今天无法进行转账');
        }


        $nowDay = date('Y-m-d', $now);
        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');
        $twhere = [
            ['from_user', $user_id],
            ['status', '<', 2],
            ['type', 2],
            ['create_at', '>=', $day_start],
            ['create_at', '<', $day_end],
        ];

        $transfer_applies = DB::table('transfer_apply')->select('amount', 'status')->where($twhere)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        foreach ($transfer_applies as $transfer_apply) {
            if ($transfer_apply['status'] == 0) {
                return error('40022', '转账失败,您还有未审核的转账申请');
            }
        }
        if ($transfer_applies) {
            $totalAmount = array_sum(array_column($transfer_applies, 'amount'));

        } else {
            $totalAmount = 0;
        }
        if ($user_limit->cash_limited == 1 && $totalAmount + $m_score > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }
        $fwhere = [
            ['user_id', $user_id],
            ['type', 2],
            ['create_at', '>=', $day_start],
            ['create_at', '<', $day_end],
            ['notes', 'like', '%转账%']
        ];
        $flowAmount = DB::table('flow_log')->selectRaw('sum(amount) as amount')->where($fwhere)->pluck('amount')->first();
        if ($user_limit->cash_limited == 1 && $flowAmount + $m_score > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }

        $shop_configs = DB::table('shop_config')->select('code', 'value')->where('parent_id', 54)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        $code = array_column($shop_configs, 'code');
        $value = array_column($shop_configs, 'value');
        $shopConfigs = array_combine($code, $value);

        if (array_key_exists('xm_transfer_cash_close', $shopConfigs) && $shopConfigs['xm_transfer_cash_close'] == 1) {
            return error('40023', '转账功能暂时关闭');
        }


        //新美积分转账消耗比 xm_transfer_rate_cash_fee 10
        $costRate = '0';
        if (array_key_exists('xm_transfer_rate_cash_fee', $shopConfigs)) {
            $costRate = $shopConfigs['xm_transfer_rate_cash_fee'];
        }
        //手续费
        $trfee = round($m_score * $costRate / 100, 2);
        $totalM = $m_score + $trfee;

        if ($user_limit->cash_limited == 1 && $flowAmount + $totalM > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }


        $user_extra = DB::table('mq_users_extra')->select('user_status', 'user_cx_rank', 'invite_user_id', 'new_status', 'pay_password', 'tps.unlimit as useable_t_score', 'tps.freeze as freeze_t_score')->join('tps', 'tps.user_id', '=', 'mq_users_extra.user_id')->where('mq_users_extra.user_id', $user_id)->first();

        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }

        $to_user_extra = DB::table('users')->select('users.user_id', 'mq_users_extra.new_status')->join('mq_users_extra', 'mq_users_extra.user_id', '=', 'users.user_id')->where('user_name', $account)->first();
        if (empty($to_user_extra)) {
            return error('40024', '转账对象不存在');
        }

        $my_ret = explode('-', $user_extra->new_status); // 我的服务商是谁
        $you_ret = explode('-', $to_user_extra->new_status); // 对方的服务商是谁

        if ($my_ret[0] != $you_ret[0]) {
            return error('40026', '不同团队之间无法进行转账');
        }


        $t_all = DB::table('master_config')->where('code', 'xm_t_all')->value('amount');
        if ($t_all == null) {
            $t_all = 0;
        }

        if ($user_extra->useable_t_score < $totalM) {
            return error('40014', '余额不足');
        }
        if (empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (strcmp($password, $user_extra->pay_password) !== 0) {
            return error('40005', '支付密码不正确');
        }


        if (array_key_exists('xm_cash_large_transfer', $shopConfigs) && $shopConfigs['xm_cash_large_transfer'] == 1 && array_key_exists('xm_cash_large_amount_value', $shopConfigs) && $shopConfigs['xm_cash_large_amount_value'] <= $m_score) {
            //开启最大值验证 最大值数额 xm_cash_large_amount_value
            $transfer_apply_data = [
                'from_user' => $user_id,
                'to_user' => $to_user_extra->user_id,
                'type' => 2,
                'amount' => $m_score,
                'trfee' => $trfee,
                'create_at' => $now
            ];
            DB::beginTransaction();
            $trid = DB::table('transfer_apply')->insertGetId($transfer_apply_data, 'trid');
//            $aff_row = DB::update('UPDATE xm_tps SET unlimit = unlimit - ? WHERE user_id = ?',[$m_score,$user_id]);//|| empty($aff_row)
            if (empty($trid)) {
                DB::rollBack();
                return error('99999', '操作失败');
            } else {
                DB::commit();
                $data = [
                    'msg' => '为了您的账户安全本次转账需经过管理员审核才能到账，请耐心等待！'
                ];
                return success($data);
            }

        }
        $to_user_t = DB::table('tps')->select('unlimit', 'freeze')->where('user_id', $to_user_extra->user_id)->first();
        if ($to_user_t) {
            $toUserTotal_t = $to_user_t->unlimit + $to_user_t->freeze;
        } else {
            $toUserTotal_t = '0';
        }

        $redis_name = 'transfer-' . $user_id;
        if (Redis::exists($redis_name)) {
            return error('99994', '处理中...');
        } else {
            Redis::set($redis_name, '1');
        }

        DB::beginTransaction();
        $aff_row1 = DB::update('UPDATE xm_tps SET unlimit = unlimit - ? WHERE user_id = ?', [$totalM, $user_id]);
        $aff_row2 = DB::update('UPDATE xm_tps SET unlimit = unlimit + ? WHERE user_id = ?', [$m_score, $to_user_extra->user_id]);

        DB::update('UPDATE xm_master_config SET amount = amount + ? WHERE code = ?', [$trfee, 'xm_t_all']);

        $userTotal_t = $user_extra->useable_t_score + $user_extra->freeze_t_score;

        $flow_data = [
            'user_id' => $user_id,
            'type' => 2,
            'status' => 2,
            'amount' => $m_score,
            'surplus' => $userTotal_t - $totalM,
            'notes' => '转账给：' . $account,
            'create_at' => $now,
        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        $flow_data['user_id'] = $to_user_extra->user_id;
        $flow_data['status'] = 1;
        $flow_data['surplus'] = $toUserTotal_t + $m_score;
        $flow_data['notes'] = $userAccount . ' 转账给我';
        $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        if ($trfee) {
            $flow_data['user_id'] = $user_id;
            $flow_data['status'] = 2;
            $flow_data['amount'] = $trfee;
            $flow_data['surplus'] = $userTotal_t - $totalM;
            $flow_data['notes'] = '转账手续费';
            $foid3 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

            $flow_data['user_id'] = 0;
            $flow_data['status'] = 1;
            $flow_data['amount'] = $trfee;
            $flow_data['surplus'] = $t_all + $trfee;
            $flow_data['notes'] = '转账手续费';
            $flow_data['isall'] = 1;
            $foid4 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
            if (empty($foid3) || empty($foid4)) {
                DB::rollBack();
                Redis::del($redis_name);
                return error('99999', '操作失败');
            }
        }


        if (empty($aff_row1) || empty($aff_row2) || empty($foid1) || empty($foid2)) {
            DB::rollBack();
            Redis::del($redis_name);
            return error('99999', '操作失败');
        } else {
            DB::commit();
            Redis::del($redis_name);

            $data = [
                'msg' => '转账成功'
            ];
            return success($data);
        }

    }


    /**
     * description:团队内转H积分
     * @author Harcourt
     * @date 2018/8/22
     */
    public function transferM(Request $request)
    {
        //不能给自己转账，支付密码extra_users，余额验证xps，对方账户是否存在users，是否属于同一团队new_status
        //个人转账是否限制users_limit flow_log shop_config  transfer_apply
        //1、团队限制2、当日转账额度限制xps
        $user_id = $request->input('user_id', 0);
        $account = $request->input('account');
        $m_score = $request->input('m_score');
        $password = $request->input('password');
        $needMsg = $request->input('needMsg', '0');
        $msg = $request->input('msg', '无');
        if (empty($user_id) || empty($account) || empty($m_score) || empty($password)) {
            return error('00000', '参数不全');
        }
        if (!is_int($m_score % 100)) {
            return error('99995', '请按提示填写');
        }
        $ch_where = [
            ['user_id', $user_id],
            ['belong_sys', 2]
        ];
        $ip = get_client_ip();
        $safeCheck = DB::table('ip_safecheck_log')->where($ch_where)->first();
        $now = time();
        if (empty($safeCheck) && (empty($msg) || empty($needMsg))) {
            return error('10008', '需重新认证身份');
        }
        if ($safeCheck && (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now) && (empty($msg) || empty($needMsg))) {
            return error('10008', '需重新认证身份');
        }
        $userAccountPhone = DB::table('users')->select('user_name', 'mobile_phone')->where('user_id', $user_id)->first();
        if (empty($userAccountPhone)) {
            return error('99998', '非法操作');
        }
        $userAccount = $userAccountPhone->user_name;
        if (strcmp($userAccount, $account) == 0) {
            return error('40025', '无法给自己转账');
        }
        if ($needMsg && $msg) {
            $where = [
                ['veri_mobile', $userAccountPhone->mobile_phone],
                ['veri_number', $msg],
                ['veri_type', 5]
            ];
            $verify = DB::table('verify_num')->where($where)->first();

            if (empty($verify) || $verify->veri_gmt_expire <= $now) {
                return error('20001', '验证码或者手机号不正确');
            }

            if (empty($safeCheck)) {
                //直接身份验证ip插入

                $check_insert_data = [
                    'user_id' => $user_id,
                    'belong_sys' => 2,
                    'ip_address' => $ip,
                    'check_time' => date('Y-m-d H:i:s', $now),
                    'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)

                ];
                DB::table('ip_safecheck_log')->insertGetId($check_insert_data, 'log_id');
            } else {
                if ($safeCheck && (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now)) {
                    $update_insert_data = [
                        'ip_address' => $ip,
                        'check_time' => date('Y-m-d H:i:s', $now),
                        'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)
                    ];
                    DB::table('ip_safecheck_log')->where('log_id', $safeCheck->log_id)->update($update_insert_data);

                }


            }
        }

        $user_limit = DB::table('mq_users_limit')->select('cash_limited', 'daily_cash_transfer_sum_limit as daily_limit')->where('user_id', $user_id)->first();
        if (empty($user_limit)) {
            return error('99999', '操作失败');
        }
        if ($user_limit->cash_limited == 1 && $m_score > $user_limit->daily_limit) {
            //有限制
            return error('40021', '超出转账额度,今天无法进行转账');
        }


        $nowDay = date('Y-m-d', $now);
        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');
        $twhere = [
            ['from_user', $user_id],
            ['status', '<', 2],
            ['type', 2],
            ['create_at', '>=', $day_start],
            ['create_at', '<', $day_end],
        ];

        $transfer_applies = DB::table('transfer_apply')->select('amount', 'status')->where($twhere)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        foreach ($transfer_applies as $transfer_apply) {
            if ($transfer_apply['status'] == 0) {
                return error('40022', '转账失败,您还有未审核的转账申请');
            }
        }
        if ($transfer_applies) {
            $totalAmount = array_sum(array_column($transfer_applies, 'amount'));

        } else {
            $totalAmount = 0;
        }
        if ($user_limit->cash_limited == 1 && $totalAmount + $m_score > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }
        $fwhere = [
            ['user_id', $user_id],
            ['type', 1],
            ['create_at', '>=', $day_start],
            ['create_at', '<', $day_end],
            ['notes', 'like', '%转账%']
        ];
        $flowAmount = DB::table('flow_log')->selectRaw('sum(amount) as amount')->where($fwhere)->pluck('amount')->first();
        if ($user_limit->cash_limited == 1 && $flowAmount + $m_score > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }

        $shop_configs = DB::table('shop_config')->select('code', 'value')->where('parent_id', 54)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        $code = array_column($shop_configs, 'code');
        $value = array_column($shop_configs, 'value');
        $shopConfigs = array_combine($code, $value);

        if (array_key_exists('xm_transfer_cash_close', $shopConfigs) && $shopConfigs['xm_transfer_cash_close'] == 1) {
            return error('40023', '转账功能暂时关闭');
        }
        if (array_key_exists('xm_m_close_transfer', $shopConfigs) && $shopConfigs['xm_m_close_transfer'] == 1) {
            return error('40023', '转账功能暂时关闭');
        }
        //新美积分转账消耗比 xm_transfer_rate_cash_fee 10
        $costRate = '0';
        if (array_key_exists('xm_transfer_rate_cash_fee', $shopConfigs)) {
            $costRate = $shopConfigs['xm_transfer_rate_cash_fee'];
        }

//        $t_all = DB::table('master_config')->where('code','xm_t_all')->value('amount');
//        if($t_all == null){
//            $t_all = 0;
//        }
        //手续费
        $trfee = round($m_score * $costRate / 100, 2);
        $totalM = $m_score + $trfee;

        if ($user_limit->cash_limited == 1 && $flowAmount + $totalM > $user_limit->daily_limit) {
            return error('40021', '超出转账额度,今天无法进行转账');
        }


//        $user_extra = DB::table('mq_users_extra')->select('user_status','user_cx_rank','invite_user_id','new_status','pay_password','tps.unlimit as useable_t_score','tps.freeze as freeze_t_score')->join('tps','tps.user_id','=','mq_users_extra.user_id')->where('mq_users_extra.user_id',$user_id)->first();
        $user_extra = DB::table('mq_users_extra')->select('user_status', 'user_cx_rank', 'invite_user_id', 'new_status', 'pay_password', 'xps.unlimit as useable_t_score', 'xps.frozen as freeze_t_score', 'xps.amount')->join('xps', 'xps.user_id', '=', 'mq_users_extra.user_id')->where('mq_users_extra.user_id', $user_id)->first();

        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }
        $my_ret = explode('-', $user_extra->new_status); // 我的服务商是谁

        if (count($my_ret) == 5 && $my_ret[3] == 1) {
            return error('40023', '转账功能暂时关闭');
        }

        $to_user_extra = DB::table('users')->select('users.user_id', 'mq_users_extra.new_status')->join('mq_users_extra', 'mq_users_extra.user_id', '=', 'users.user_id')->where('user_name', $account)->first();
        if (empty($to_user_extra)) {
            return error('40024', '转账对象不存在');
        }

        $you_ret = explode('-', $to_user_extra->new_status); // 对方的服务商是谁

        if (count($you_ret) == 5 && $you_ret[3] == 1) {
            return error('40023', '对方转账功能暂时关闭');
        }

        if ($my_ret[0] != $you_ret[0]) {
            return error('40026', '不同团队之间无法进行转账');
        }


        if ($user_extra->useable_t_score < $totalM) {
            return error('40014', '余额不足');
        }
        if (empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (strcmp($password, $user_extra->pay_password) !== 0) {
            return error('40005', '支付密码不正确');
        }


        if (array_key_exists('xm_cash_large_transfer', $shopConfigs) && $shopConfigs['xm_cash_large_transfer'] == 1 && array_key_exists('xm_cash_large_amount_value', $shopConfigs) && $shopConfigs['xm_cash_large_amount_value'] <= $m_score) {
            //开启最大值验证 最大值数额 xm_cash_large_amount_value
            $transfer_apply_data = [
                'from_user' => $user_id,
                'to_user' => $to_user_extra->user_id,
                'type' => 0,
                'amount' => $m_score,
                'trfee' => $trfee,
                'create_at' => $now
            ];
            DB::beginTransaction();
            $trid = DB::table('transfer_apply')->insertGetId($transfer_apply_data, 'trid');
//            $aff_row = DB::update('UPDATE xm_tps SET unlimit = unlimit - ? WHERE user_id = ?',[$m_score,$user_id]);//|| empty($aff_row)
            if (empty($trid)) {
                DB::rollBack();
                return error('99999', '操作失败');
            } else {
                DB::commit();
                $data = [
                    'msg' => '为了您的账户安全本次转账需经过管理员审核才能到账，请耐心等待！'
                ];
                return success($data);
            }

        }
        $to_user_t = DB::table('xps')->select('unlimit', 'frozen as freeze', 'amount')->where('user_id', $to_user_extra->user_id)->first();
        if ($to_user_t) {
//            $toUserTotal_t = $to_user_t->unlimit + $to_user_t->freeze;
            $toUserTotal_t = $to_user_t->amount;
        } else {
            $toUserTotal_t = '0';
        }

        $redis_name = 'transferM-' . $user_id;
        if (Redis::exists($redis_name)) {
            return error('99994', '处理中...');
        } else {
            Redis::set($redis_name, '1');
        }

        DB::beginTransaction();
        $aff_row1 = DB::update('UPDATE xm_xps SET unlimit = unlimit - ?,amount = amount - ? WHERE user_id = ?', [$totalM, $totalM, $user_id]);
        $aff_row2 = DB::update('UPDATE xm_xps SET unlimit = unlimit + ?,amount = amount + ? WHERE user_id = ?', [$m_score, $m_score, $to_user_extra->user_id]);

//        DB::update('UPDATE xm_master_config SET amount = amount + ? WHERE code = ?',[$trfee,'xm_t_all']);

//        $userTotal_t =$user_extra->useable_t_score + $user_extra->freeze_t_score;
        $userTotal_t = $user_extra->amount;

        $flow_data = [
            'user_id' => $user_id,
            'type' => 1,
            'status' => 2,
            'amount' => $m_score,
            'surplus' => $userTotal_t - $totalM,
            'notes' => '转账给：' . $account,
            'create_at' => $now,
        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        $flow_data['user_id'] = $to_user_extra->user_id;
        $flow_data['status'] = 1;
        $flow_data['surplus'] = $toUserTotal_t + $m_score;
        $flow_data['notes'] = $userAccount . ' 转账给我';
        $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        if ($trfee) {
            $flow_data['user_id'] = $user_id;
            $flow_data['status'] = 2;
            $flow_data['amount'] = $trfee;
            $flow_data['surplus'] = $userTotal_t - $totalM;
            $flow_data['notes'] = '转账手续费';
            $foid3 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

//            $flow_data['user_id'] = 0;
//            $flow_data['status'] = 1;
//            $flow_data['amount'] = $trfee;
//            $flow_data['surplus'] = $t_all + $trfee;
//            $flow_data['notes'] = '转账手续费';
//            $flow_data['isall'] = 1;
//            $foid4 = DB::table('flow_log')->insertGetId($flow_data,'foid');
            if (empty($foid3)) {
                DB::rollBack();
                Redis::del($redis_name);
                return error('99999', '操作失败');
            }
        }


        if (empty($aff_row1) || empty($aff_row2) || empty($foid1) || empty($foid2)) {
            DB::rollBack();
            Redis::del($redis_name);
            return error('99999', '操作失败');
        } else {
            DB::commit();
            Redis::del($redis_name);

            $data = [
                'msg' => '转账成功'
            ];
            return success($data);
        }

    }


    /**
     * description:获取客服
     * @author Harcourt
     * @date 2018/8/31
     */
    public function getCustomService(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 3);
        if (empty($user_id) || !in_array($type, [1, 2, 3])) {
            return error('00000', '参数不全');
        }

        $redis_name = 'customService-' . $type . '-' . $user_id;
        $now = time();
        $flag = false;
        if (Redis::exists($redis_name)) {
            $res = Redis::get($redis_name);
            $result = json_decode($res, true);
            $where = [
                ['user_id', $result['service_id']],
                ['type', $type]
            ];
            $service_user = DB::table('service_users')->where($where)->first();
            if (empty($service_user)) {
                $flag = true;

            } else {
                $id = $result['service_id'];
                $result['update_at'] = $now;
                Redis::set($redis_name, json_encode($result));
            }

        } else {
            $flag = true;
        }
        if ($flag) {
            $ids = DB::table('service_users')->where('type', $type)->pluck('user_id')->toArray();
            if (empty($ids)) {
                return error('20005', '客服不存在');
            }
            $id = $ids[array_rand($ids, 1)];
            $result = [
                'service_id' => $id,
                'update_at' => $now,
            ];
            Redis::set($redis_name, json_encode($result));
        }


        success($id);
    }

    /**
     * description:分享链接
     * @author Harcourt
     * @date 2018/9/5
     */
    public function shareUrl(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $invite_code = DB::table('mq_users_extra')->where('user_id', $user_id)->value('invite');
        if (empty($invite_code)) {
            return error('99998', '非法操作');
        }
        $data['url'] = url('http://web.myls1688.com/#/register?invite_code=' . $invite_code);
        $data['invite'] = $invite_code;
        success($data);
    }

    /******************我的业绩(购买H单) START**********************/
    /**
     * description:我的业绩（暂停）
     * @author Harcourt
     * @date 2018/9/21
     */
    public function achievement(Request $request)
    {
        //T积分转入H积分    T 积分转入新美积分

        $user_id = $request->input('user_id', 0);
        $day_type = $request->input('day_type', 0);//0、昨天1、近7天2、近30天
        $show_type = $request->input('show_type', 0);//1、列表2、图表
        $page = $request->input('page', 0);

//        $user_id = 22560;
//        $day_type = 2;
//        $show_type = 2;

        if (empty($user_id) || !in_array($day_type, [0, 1, 2]) || !in_array($show_type, [1, 2])) {
            return error('00000', '参数不全');
        }
        if ($day_type == 0 && $show_type == 2) {
            return error('00000', '参数不全');
        }
        $where = [
            ['user_id', $user_id]
        ];
        $now = time();
        $today = date('Y-m-d 00:00:00', $now);

        if ($day_type == 1) {
            $start = strtotime($today) - 6 * 24 * 60 * 60;

        } else if ($day_type == 2) {
            $start = strtotime($today) - 29 * 24 * 60 * 60;
        }
        if ($day_type == 0) {
            $start = strtotime('yesterday');
            $now = strtotime($today);
        }

        $between = [$start, $now];

        if ($show_type == 1) {

            $lists = DB::table('trade_performance')->select('tp_gmt_create as gb_day', 'tp_num')->where($where)->whereBetween('tp_gmt_create', $between)->orderBy('tp_id', 'desc')->limit(20)->offset(20 * $page)->get();
            foreach ($lists as $list) {
                $list->gb_day = date('Y-m-d H:i:s', $list->gb_day);
            }
        } else {
            $lists = $this->getDayAmount($user_id, $day_type);
        }

        success($lists);


    }

    function getDayAmount($user_id, $type)
    {
        $now = time();
        if ($type == 1) {
            $num = 6;
            $step = 1;
        } else {
            $num = 29;
            $step = 3;
        }
        $sql = 'SELECT ';
        $last_end = strtotime(date('Y-m-d'));
        $dayKeys = [];
        for ($i = $num; $i > 0; $i = $i - $step) {
            $start_day = date('Y/m/d', strtotime('-' . ($i) . ' days'));
            $start = strtotime($start_day);


            if ($i - $step >= 0) {
                $end_day = date('Y/m/d', strtotime('-' . ($i - $step) . ' days'));
                $end = strtotime($end_day);
                $sql .= 'SUM(CASE WHEN  tp_gmt_create BETWEEN ' . $start . ' AND ' . $end . ' THEN tp_num else 0 END) AS tp' . ($i - $step) . ',';
                $last_end = $end;
                $sday_start = date('m/d', strtotime($start_day));
                $sday_end = date('m/d', strtotime($end_day));
                $dayKeys[] = $sday_start . '-' . $sday_end;
            } else {
                break;
            }

        }
        $dayKeys[] = date('m/d') . '-至今';
        $sql .= ' SUM(CASE WHEN  tp_gmt_create BETWEEN ' . $last_end . ' AND ' . $now . ' THEN tp_num else 0 END) AS tpl FROM xm_trade_performance FORCE INDEX (u_t) WHERE user_id=' . $user_id . ' GROUP BY user_id';
        $lists = DB::select($sql);
        $num = count($dayKeys);
        $listArr = [];
        if (count($lists) == 0) {
            for ($i = 0; $i < $num; $i++) {
                $listArr[] = '0.00';
            }
        } else {
            $listArr = (array)$lists[0];
        }
        $res = [];
        $listValues = array_values($listArr);

        for ($i = 0; $i < $num; $i++) {
            $res[] = [
                'gb_day' => $dayKeys[$i],
                'tp_num' => $listValues[$i]
            ];
        }

        return $res;

    }
    /******************我的业绩(购买H单) END**********************/


    /******************团队业绩(T=>H) START**********************/
    /**
     * description:团队业绩(T=>H)
     * @author Harcourt
     * @date 2018/10/9
     */
    public function teamAchievement(Request $request)
    {
        //T积分转入H积分/T 积分转入新美积分

        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 0);//0、总业绩1、直推业绩
        $day_type = $request->input('day_type', 0);//0、昨天1、近7天2、近30天
        $show_type = $request->input('show_type', 0);//1、列表2、图表
        $page = $request->input('page', 0);


        if (empty($user_id) || !in_array($type, [0, 1]) || !in_array($day_type, [0, 1, 2]) || !in_array($show_type, [1, 2])) {
            return error('00000', '参数不全');
        }
        if ($day_type == 0 && $show_type == 2) {
            return error('00000', '参数不全');
        }
        $where = [
            ['user_id', $user_id]
        ];
        $now = time();
        $today = date('Y-m-d 00:00:00', $now);

        if ($day_type == 1) {
            $start = strtotime($today) - 6 * 24 * 60 * 60;

        } else if ($day_type == 2) {
            $start = strtotime($today) - 29 * 24 * 60 * 60;
        }
        if ($day_type == 0) {
            $start = strtotime('yesterday');
            $now = strtotime($today);
        }


        if ($show_type == 1) {
            $lists = $this->getListData($user_id, $start, $now, $type, $page);
        } else {
            $lists = $this->getDayTeamAmount($user_id, $day_type, $type);
        }

        $totalAmount = $this->getTeamTotalAmount($user_id, $start, $now, $type);

        $data = [
            'list' => $lists,
            'total' => $totalAmount
        ];

        success($data);


    }


    function getTeamTotalAmount($user_id, $start = 0, $end = 0, $type = 0)
    {
        if ($start && $end) {
            $sql = "SELECT SUM(tp_num) as total FROM xm_trade_performance WHERE tp_gmt_create >= " . $start . " AND tp_gmt_create < " . $end . " AND FIND_IN_SET(" . $user_id . ",tp_top_user_ids)";

        } else {
            $sql = "SELECT SUM(tp_num) as total FROM xm_trade_performance WHERE FIND_IN_SET(" . $user_id . ",tp_top_user_ids)";

        }
        if ($type == 1) {
            $children = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->pluck('user_id')->toArray();
            $childrenStr = implode(',', (array)$children);
            if ($childrenStr) {
                $sql .= " AND user_id IN (" . $childrenStr . ")";
            } else {
                $sql .= " AND user_id != " . $user_id;
            }
        }
        $reults = DB::select($sql);
        $amount = 0;
        foreach ($reults as $reult) {
            $amount = $reult->total;
            if (empty($amount)) {
                $amount = 0;
            }
        }
        return $amount;
    }

    function getListData($user_id, $start, $end, $type = 0, $page = 0)
    {
        $sql = "SELECT tp_gmt_create as gb_day,tp_num , user_name FROM xm_trade_performance WHERE tp_gmt_create >= " . $start . " AND tp_gmt_create < " . $end . " AND FIND_IN_SET(" . $user_id . ",tp_top_user_ids)";
        if ($type == 1) {
            $children = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->pluck('user_id')->toArray();
            $childrenStr = implode(',', (array)$children);
            if ($childrenStr) {
                $sql .= " AND user_id IN (" . $childrenStr . ")";
            } else {
                $sql .= " AND user_id != " . $user_id;
            }
        }
        $sql .= "  ORDER BY tp_id DESC LIMIT 20 OFFSET " . 20 * $page;
        $results = DB::select($sql);
        foreach ($results as $result) {
            $result->gb_day = date('Y-m-d H:i:s', $result->gb_day);
        }
        return $results;
    }


    function getDayTeamAmount($user_id, $day_type, $type)
    {
        $now = time();
        if ($day_type == 1) {
            $num = 6;
            $step = 1;
        } else {
            $num = 29;
            $step = 3;
        }
        $sql = 'SELECT ';
        $last_end = strtotime(date('Y-m-d'));
        $dayKeys = [];
        for ($i = $num; $i > 0; $i = $i - $step) {
            $start_day = date('Y/m/d', strtotime('-' . ($i) . ' days'));
            $start = strtotime($start_day);


            if ($i - $step >= 0) {
                $end_day = date('Y/m/d', strtotime('-' . ($i - $step) . ' days'));
                $end = strtotime($end_day);
                $sql .= 'SUM(CASE WHEN  tp_gmt_create BETWEEN ' . $start . ' AND ' . $end . ' THEN tp_num else 0 END) AS tp' . ($i - $step) . ',';
                $last_end = $end;
                $sday_start = date('m/d', strtotime($start_day));
                $sday_end = date('m/d', strtotime($end_day));
                $dayKeys[] = $sday_start . '-' . $sday_end;
            } else {
                break;
            }

        }
        $dayKeys[] = date('m/d') . '-至今';
        $sql .= ' SUM(CASE WHEN  tp_gmt_create BETWEEN ' . $last_end . ' AND ' . $now . ' THEN tp_num else 0 END) AS tpl FROM xm_trade_performance FORCE INDEX (u_t) WHERE FIND_IN_SET(' . $user_id . ',tp_top_user_ids)';

        if ($type == 1) {
            $children = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->pluck('user_id')->toArray();
            $childrenStr = implode(',', (array)$children);
            if ($childrenStr) {
                $sql .= " AND user_id IN (" . $childrenStr . ")";
            } else {
                $sql .= " AND user_id != " . $user_id;
            }
        }
        $lists = DB::select($sql);
        $num = count($dayKeys);
        $listArr = [];
        if (count($lists) == 0) {
            for ($i = 0; $i < $num; $i++) {
                $listArr[] = '0.00';
            }
        } else {
            $listArr = (array)$lists[0];
        }
        $res = [];
        $listValues = array_values($listArr);

        for ($i = 0; $i < $num; $i++) {
            $res[] = [
                'gb_day' => $dayKeys[$i],
                'tp_num' => $listValues[$i]
            ];
        }

        return $res;

    }
    /******************团队业绩(T=>H) END**********************/


    /******************团队业绩(购买H单) START**********************/

    /**
     * description:团队业绩(购买H单)
     * @author Harcourt
     * @date 2018/10/9
     */
    public function hAchievement(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 0);//0、总业绩1、直推业绩
        $search_day = $request->input('search_day', 0);//搜索的时间，默认当前时间戳 2018-9-1
        $show_type = $request->input('show_type', 0);//1、列表2、图表
        $page = $request->input('page', 0);


        if (empty($user_id) || !in_array($type, [0, 1]) || !in_array($show_type, [1, 2])) {
            return error('00000', '参数不全');
        }

        $now = time();
        $today = date('Y-m-d 00:00:00', $now);

        if ($search_day == 0) {
            $start = strtotime($today) - 15 * 24 * 60 * 60;
            $end = strtotime($today);
        } else {
            $start = strtotime($search_day) - 15 * 24 * 60 * 60;
            $end = strtotime($search_day);
        }

        if ($show_type == 1) {
            $lists = $this->getHListData($user_id, $start, $end, $type, $page);
        } else {
            $lists = $this->getHDayTeamAmount($user_id, $type, $search_day);
        }

        $totalAmount = $this->getHTeamTotalAmount($user_id, $start, $end, $type);

        $data = [
            'list' => $lists,
            'total' => $totalAmount
        ];

        success($data);


    }

    function getHTeamTotalAmount($user_id, $start = 0, $end = 0, $type = 0)
    {
        // if ($start && $end) {
        //     $sql = "SELECT SUM(tp_num) as total FROM xm_trade_performance WHERE tp_gmt_create >= " . $start . " AND tp_gmt_create < " . $end . " AND FIND_IN_SET(" . $user_id . ",tp_top_user_ids) AND user_id !=".$user_id;

        // } else {
        $sql = "SELECT SUM(tp_num) as total FROM xm_trade_performance WHERE FIND_IN_SET(" . $user_id . ",tp_top_user_ids)";

        // }
        if ($type == 1) {
            $children = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->pluck('user_id')->toArray();
            $childrenStr = implode(',', (array)$children);
            if ($childrenStr) {
                $sql .= " AND user_id IN (" . $childrenStr . ")";
            } else {
                $sql .= " AND user_id != " . $user_id;
            }
        }
        $reults = DB::select($sql);
        $amount = 0;
        foreach ($reults as $reult) {
            $amount = $reult->total;
            if (empty($amount)) {
                $amount = 0;
            }
        }
        return $amount;
    }

    function getHListData($user_id, $start, $end, $type = 0, $page = 0)
    {
        $sql = "SELECT tp_gmt_create as gb_day,tp_num , user_name FROM xm_trade_performance WHERE tp_gmt_create >= " . $start . " AND tp_gmt_create < " . $end . " AND FIND_IN_SET(" . $user_id . ",tp_top_user_ids) AND user_id !=" . $user_id;

        if ($type == 1) {
            $children = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->pluck('user_id')->toArray();
            $childrenStr = implode(',', (array)$children);
            if ($childrenStr) {
                $sql .= " AND user_id IN (" . $childrenStr . ")";
            } else {
                $sql .= " AND user_id != " . $user_id;
            }
        }
        $sql .= " ORDER BY tp_id DESC  LIMIT 20 OFFSET " . 20 * $page;
        $results = DB::select($sql);
        foreach ($results as $result) {
            $result->gb_day = date('Y-m-d H:i:s', $result->gb_day);
        }
        return $results;
    }

    function getHDayTeamAmount($user_id, $type, $search_day)
    {
        $num = 16;
        $step = 1;

        $sql = 'SELECT ';
        $dayKeys = [];
        $now = time();
        $today = date('Y-m-d 00:00:00', $now);

        if ($search_day == 0) {
            $target_day = strtotime($today);
        } else {
            $target_day = strtotime($search_day);
        }


        for ($i = $num; $i > 0; $i = $i - $step) {
//            $start_day = date('Y/m/d',strtotime('-'.($i).' days'));
            $start_day = date('Y/m/d', $target_day - $i * 24 * 60 * 60);
            $start = strtotime($start_day);

            if ($i - $step >= 0) {
//                $end_day = date('Y/m/d',strtotime('-'.($i-$step).' days'));
                $end_day = date('Y/m/d', $target_day - ($i - $step) * 24 * 60 * 60);
                $end = strtotime($end_day);
                $sql .= 'SUM(CASE WHEN  tp_gmt_create BETWEEN ' . $start . ' AND ' . $end . ' THEN tp_num else 0 END) AS tp' . ($i - $step) . ',';
                $sday_start = date('m/d', strtotime($start_day));
                $sday_end = date('m/d', strtotime($end_day));
//                $dayKeys[] = $sday_start.'-'.$sday_end;
                $dayKeys[] = $sday_end;
            } else {
                break;
            }

        }
        $sql = rtrim($sql, ',');
        $sql .= ' FROM xm_trade_performance FORCE INDEX (u_t) WHERE FIND_IN_SET(' . $user_id . ',tp_top_user_ids)';

        if ($type == 1) {
            $children = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->pluck('user_id')->toArray();
            $childrenStr = implode(',', (array)$children);
            if ($childrenStr) {
                $sql .= " AND user_id IN (" . $childrenStr . ")";
            } else {
                $sql .= " AND user_id != " . $user_id;
            }
        }
        $lists = DB::select($sql);

        $num = count($dayKeys);

        $listArr = [];
        if (count($lists) == 0) {
            for ($i = 0; $i < $num; $i++) {
                $listArr[] = '0.00';
            }
        } else {
            $listArr = (array)$lists[0];
        }
        $res = [];
        $listValues = array_values($listArr);
        for ($i = 0; $i < $num; $i++) {
            $res[] = [
                'gb_day' => $dayKeys[$i],
                'tp_num' => $listValues[$i]
            ];
        }

        return $res;

    }

    /******************团队业绩(购买H单) END**********************/


    /**
     * description:用户签到
     * @author libaowei
     * @date 2019/7/9
     */
    function Qd(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $is_debug = $request->input('is_debug', 0);

        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $user = DB::table('users')->where('user_id', $user_id)->first();
        if ($user->is_new == 1) {
            $this->signNew($user_id);
            exit();
        }

        //初始化总共的金额
        $sum = 0;
        //初始化释放的总金额
        $sum_balance = 0;
        //修改is_debug逻辑
        if ($is_debug == 1) {
            $time = $request->input('begin_time', 0);
            $end_time = strtotime(date('Ymd', $time));
        } else {

            $time = time();
            $end_time = strtotime(date('Ymd', $time));
        }
        //获取释放记录
        $flow_log = DB::table('flow_log')->where([['user_id', $user_id], ['target_type', 4]])->orderBy('sign_time', 'desc')->first();
        if (isset($flow_log)) {
            //计算今天是否已经签到
            $log_time = $time - $flow_log->sign_time;
        } else {
            $log_time = 86401;
        }
        //如果已经签到
        if ($is_debug == 1) {
            $log_time = abs($log_time);
        }
        if ($log_time < 86400) {
            return error('20004', '今天已经签到过了');
        }
        //获取用户待释放的优惠券
        $customs_order = DB::table('customs_order')->where([['user_id', $user_id], ['status', 1]])->get();
        //如果发现有待释放的优惠券才会继续进行
        if (count($customs_order)) {
//
//            //获取释放记录
//            $flow_log = DB::table('flow_log')->where([['user_id', $user_id], ['target_type', 4]])->orderBy('sign_time', 'desc')->first();
//            if (isset($flow_log)) {
//                //计算今天是否已经签到
//                $log_time = $time - $flow_log->sign_time;
//            } else {
//                $log_time = 86401;
//            }
//            //如果已经签到
//            if ($is_debug == 1) {
//                $log_time = abs($log_time);
//            }
//            if ($log_time < 86400) {
//                return error('20004', '今天已经签到过了');
//            }

            //查出后台设置的每天释放比例
            $day_release_ratio = DB::table('master_config')->where('code', 'day_release_ratio')->pluck('value')->first();
            //查询用户的配置
            $person_release_ratio = DB::table('mq_users_limit')->where('user_id', $user_id)->first();

            //查询用户待释放优惠券
            $user_release_balance = DB::table('user_account')->where('user_id', $user_id)->value('release_balance');

            if ($user_release_balance <= 0) {

                DB::table('customs_order')->where('user_id', $user_id)->update(['status' => 2]);
                return error('00000', '暂无待释放优惠券');
            }
            //新增记录
            DB::beginTransaction();
            try {

                addDrawcont(2, $user_id);

                //如果用户配置的每日释放率有效就用配置的，无效就用后台配置的
                if ($person_release_ratio->day_release_ratio > 0) {
                    $lv = $person_release_ratio->day_release_ratio;
                } else {
                    $lv = $day_release_ratio;
                }

                foreach ($customs_order as $k => $v) {
                    //需要减少的金额
                    $money = $v->release_balance * $lv / 100;
                    //如果待释放金额不能够减释放金额的比例
                    if ($v->surplus_release_balance - $money <= 0) {
                        //直接减去待释放的优惠券
                        $money = $v->surplus_release_balance;
                        //证明优惠券已经释放完
                        DB::table('customs_order')->where('co_id', $v->co_id)->update(['status' => 2]);
                    }

                    //一共要减少的金额
                    $sum += $money;

                    //更新报单待释放金额
                    if ($user_release_balance >= $sum) {
                        DB::update('UPDATE xm_customs_order SET surplus_release_balance = surplus_release_balance - ?,update_at = ? WHERE co_id = ?', [$money, $time, $v->co_id]);
                    } else {
                        //证明优惠券已经释放完
                        DB::table('customs_order')->where('co_id', $v->co_id)->update(['status' => 2]);
                    }
                }

                //查询后台增加的待释放优惠券
                $release_balance1 = DB::table('flow_log')->where([['user_id', $user_id], ['target_type', 5], ['status', 1]])->sum('amount');
                //查询后台减少的待释放优惠券
                $release_balance2 = DB::table('flow_log')->where([['user_id', $user_id], ['target_type', 5], ['status', 2]])->sum('amount');
                //计算一共增加的优惠券 = 增加的优惠券 - 减少的优惠券
                $release_balance = $release_balance1 - $release_balance2;

                //判断优惠券是否还能释放，用户总的优惠券 小于 增加的优惠券
                if ($user_release_balance < $release_balance) {
                    return error('10000', '没有待释放优惠券了');
                }

                //计算出实际的待释放优惠券金额
                $reality_balance = $user_release_balance - $release_balance;
                //如果用户的实际待释放优惠券 小于 当前签到获得的优惠券
                if ($user_release_balance < $sum) {
                    //为了提示和方便更改
                    $sum = $user_release_balance;
                    //更新待释放优惠券
                    DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$sum, $sum, $time, $user_id]);
                    //签到的优惠券全部释放完更新报单的状态
                    DB::table('customs_order')->where('user_id', $user_id)->update(['status' => 2]);
                } else {
                    //更新待释放优惠券
                    DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$sum, $sum, $time, $user_id]);
                }

                if ($sum <= 0) {
                    return error('00000', '暂无待释放优惠券');
                }

                //查询出更新后的数据，方便记录
                $user_account = DB::table('user_account')->where('user_id', $user_id)->first();

                //余额流水
                $flow_data = [
                    'user_id' => $user_id,
                    'type' => 2,
                    'status' => 1,
                    'amount' => $sum,
                    'surplus' => $user_account->balance,
                    'notes' => '签到收入',
                    'create_at' => $time,
                    'sign_time' => $end_time,
                    'target_type' => 4,
                ];
                DB::table('flow_log')->insertGetId($flow_data, 'foid');

                //待释放余额流水
                $flow_data = [
                    'user_id' => $user_id,
                    'type' => 3,
                    'status' => 2,
                    'amount' => $sum,
                    'surplus' => $user_account->release_balance,
                    'notes' => '签到支出',
                    'create_at' => $time,
                    'sign_time' => $end_time,
                    'target_type' => 4,
                ];
                DB::table('flow_log')->insertGetId($flow_data, 'foid');
                $sum = round($sum, 2);
                $date['money'] = $sum;
                success($date);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                return error('10000', '签到失败');
            }
        } else {
            return error('00000', '暂无待释放优惠券');
        }
    }

    public function signInNew(Request $request)
    {
        $user_id = $request->input('user_id');
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }

        $num = 0;
        $flow_log = DB::table('flow_log')->where([['user_id', $user_id], ['target_type', 4]])->orderBy('sign_time', 'desc')->first();
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');

        $betweenTime = $distriBution['sign_max_time']->value;
        if (isset($flow_log)) {
            //计算今天是否已经签到
            if (date('Ymd', time()) - date('Ymd', $flow_log->sign_time) < $betweenTime) {
                return error('20004', $betweenTime . '前已经签到过了');
            }
        }
        //获取用户待释放的优惠券
        $customs_order = DB::table('customs_order')->where([['user_id', $user_id], ['status', 1]])->get();
        //如果发现有待释放的优惠券才会继续进行
        if (count($customs_order) <= 0) {
            return error('00000', '暂无待释放优惠券');
        }
        //查询用户待释放优惠券
        $user_release_balance = DB::table('user_account')->where('user_id', $user_id)->first();
        if ($user_release_balance->release_balance <= 0) {
            DB::table('customs_order')->where('user_id', $user_id)->update(['status' => 2]);
            return error('00000', '暂无待释放优惠券');
        }
        $user = DB::table('users')->where('user_id', $user_id)->first();
        $tpMoney = $user->user_tp_count;
        $signCode = explode(",", $distriBution['sign_num']->value);
        $beginSiginCode = explode("/", $distriBution[$signCode[0]]->value)[0];
        $beginProportion = explode("/", $distriBution[$signCode[0]]->value)[1];
        $endSignCode = explode("/", $distriBution[$signCode[count($signCode) - 1]]->value)[0];
        $endProportion = explode("/", $distriBution[$signCode[count($signCode) - 1]]->value)[1];
        unset($signCode[0]);
        unset($signCode[count($signCode) - 1]);
        if ($tpMoney < $beginSiginCode) {
            $num = $tpMoney * ($beginProportion * 0.01);
        } elseif ($tpMoney > $endSignCode) {
            $num = $tpMoney * ($endProportion * 0.01);
        } else {
            foreach ($signCode as $k => $v) {
                $betweenArr = explode("/", $distriBution[$v]->value);
                $duibiArr = explode("-", $betweenArr[0]);
                if ($tpMoney > $duibiArr[0] && $tpMoney < $duibiArr[1]) {
                    $num = $tpMoney * ($betweenArr[1] * 0.01);
                    break;
                }
            }
        }

        if ($user_release_balance->release_balance < $num) {
            list($customNum, $updateData) = update_custom($user_id, $user_release_balance->release_balance);
        } else {

            list($customNum, $updateData) = update_custom($user_id, $num);
        }

        DB::beginTransaction();
        try {
            if (empty($updateData)) {
                return error('10000', '签到失败');
            }
            addDrawcont(2, $user_id);
            //更新待释放优惠券
            DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$customNum, $customNum, time(), $user_id]);
            //签到的优惠券全部释放完更新报单的状态
            foreach ($updateData as $k => $updateDatas) {
                DB::table('customs_order')->where('co_id', $k)->update($updateDatas);
            }
            //余额流水
            $flow_data = [
                'user_id' => $user_id,
                'type' => 2,
                'status' => 1,
                'amount' => $customNum,
                'surplus' => $user_release_balance->balance + $customNum,
                'notes' => '签到收入',
                'create_at' => time(),
                'sign_time' => time(),
                'target_type' => 4,
            ];
            DB::table('flow_log')->insert($flow_data, 'foid');

            //待释放余额流水
            $flow_data = [
                'user_id' => $user_id,
                'type' => 3,
                'status' => 2,
                'amount' => $customNum,
                'surplus' => $user_release_balance->release_balance - $customNum,
                'notes' => '签到支出',
                'create_at' => time(),
                'sign_time' => time(),
                'target_type' => 4,
            ];
            DB::table('flow_log')->insert($flow_data, 'foid');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return error('10000', '签到失败');
        }
        success(['money' => $customNum]);
    }

    public function signNew($user_id)
    {
//        $user_id = $request->input('user_id');
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }

        $redis = app('redis.connection');
        $signNum = $redis->hgetall("sign:prize");
        $num = isset($signNum[$user_id]) ? $signNum[$user_id] : 0;
        $flow_log = DB::table('flow_log')->where([['user_id', $user_id], ['target_type', 4]])->orderBy('sign_time', 'desc')->first();
        if (isset($flow_log)) {
            //计算今天是否已经签到
            if (date('Ymd', time()) == date('Ymd', $flow_log->sign_time)) {
                return error('20004', '今天已经签到过了');
            }
        }
        //获取用户待释放的优惠券
        $customs_order = DB::table('customs_order')->where([['user_id', $user_id], ['status', 1]])->get();
        //如果发现有待释放的优惠券才会继续进行
        if (count($customs_order) <= 0) {
            return error('00000', '暂无待释放优惠券');
        }
        //查询用户待释放优惠券
        $user_release_balance = DB::table('user_account')->where('user_id', $user_id)->first();
        if ($user_release_balance->release_balance <= 0) {
            DB::table('customs_order')->where('user_id', $user_id)->update(['status' => 2]);
            return error('00000', '暂无待释放优惠券');
        }
        if ($num == 0) {
            $user = DB::table('users')->where('user_id', $user_id)->first();
            $wigth = $user->cut_wigth;
            $distriButions = DB::table('master_config')->get()->toArray();
            $distriBution = array_column($distriButions, null, 'code');
            $distr = explode("/", $distriBution[$wigth]->value);
            $signArr['section'] = explode("-", $distr[2]);
//            $timeCheck = date("Ymd", strtotime("-1 day"));
            //临时用有数据的时间做判断
//            $timeCheck = '20190906';
            $status = 1;
            $sql = "SELECT user_id,SUM(release_balance) AS num FROM xm_customs_order WHERE status=? GROUP BY user_id";
            $cusResults = DB::select($sql, [$status]);
            $cusResult = array_column($cusResults, null, 'user_id');
            $roundNum = rand(($signArr['section'][0] * 100), (($signArr['section'][1]) * 100)) * 0.01;
            $insertRedis[$user_id] = sprintf("%.2f", $cusResult[$user_id]->num * ($roundNum * 0.01));
            $num = $insertRedis[$user_id];
        }


        if ($user_release_balance->release_balance < $num) {
            list($customNum, $updateData) = update_custom($user_id, $user_release_balance->release_balance);
        } else {

            list($customNum, $updateData) = update_custom($user_id, $num);
        }

        DB::beginTransaction();
        try {
            if (empty($updateData)) {
                return error('10000', '签到失败');
            }
            addDrawcont(2, $user_id);
            //更新待释放优惠券
            DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$customNum, $customNum, time(), $user_id]);
            //签到的优惠券全部释放完更新报单的状态
            foreach ($updateData as $k => $updateDatas) {
                DB::table('customs_order')->where('co_id', $k)->update($updateDatas);
            }
            //余额流水
            $flow_data = [
                'user_id' => $user_id,
                'type' => 2,
                'status' => 1,
                'amount' => $customNum,
                'surplus' => $user_release_balance->balance + $customNum,
                'notes' => '签到收入',
                'create_at' => time(),
                'sign_time' => time(),
                'target_type' => 4,
            ];
            DB::table('flow_log')->insert($flow_data, 'foid');

            //待释放余额流水
            $flow_data = [
                'user_id' => $user_id,
                'type' => 3,
                'status' => 2,
                'amount' => $customNum,
                'surplus' => $user_release_balance->release_balance - $customNum,
                'notes' => '签到支出',
                'create_at' => time(),
                'sign_time' => time(),
                'target_type' => 4,
            ];
            DB::table('flow_log')->insert($flow_data, 'foid');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return error('10000', '签到失败');
        }
        success(['money' => $customNum]);
    }

    /**
     * description:查询好友消费
     * @author libaowei
     * @date 2019/7/25
     */
    public function expense(Request $request)
    {
        //初始化
        $users = [];
        //用户本人ID
        $user_id = $request->user_id;
        //页数
        $page = $request->page;

        $limit = 40;

        $offset = $page * $limit;

        if (!isset($user_id) || !isset($page)) {
            return error('00000', '参数不全');
        }
        //查询当前用户直推人的ID
        $zt = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->offset($offset)->limit($limit)->pluck('user_id')->toArray();

        foreach ($zt as $v) {
            //获取用户昵称和手机号
            $user = DB::table('users')->select('user_id', 'nickname', 'mobile_phone')->where('user_id', $v)->first();
            //获取粉丝消费金额
            $money = DB::table('trade_performance')->select(DB::raw('SUM(tp_num) as money'))->whereRaw(' FIND_IN_SET(?,tp_top_user_ids)', [$v])->first();
            //如果没有消费金额
            if ($money->money == null) {
                $money->money = '0.00';
            }
            //统一存放在一个数组中
            $users[] = array('user_id' => $user->user_id, 'nickname' => $user->nickname, 'mobile_phone' => $user->mobile_phone, 'money' => $money->money);
        }

        success($users);
    }

    /**
     * 查询我的二维码图片
     */
    public function share_img()
    {
        //查询出信息
        $shares = DB::table('share_img')->selectRaw('concat(?,img_src) as img_src', [IMAGE_DOMAIN])->where('status', 1)->orderBy('p_sort', 'desc')->limit(3)->get();

        success($shares);

    }

}
