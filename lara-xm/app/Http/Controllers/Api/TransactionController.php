<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

class TransactionController extends Controller
{
    private static $user_ids = '';
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    /**
     * description:明细记录
     * @author douhao
     * @date 2018/8/21
     */
    public function detailLog(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 2);
        $page = $request->input('page', 0);
        if (empty($user_id) || empty($type)) {
            return error('00000', '参数不全');
        }
        $limit = 20;
        $offset = $limit * $page;
        switch ($type) {
            case 1:
                $tip = 1;
                break;
            case 2:
                $tip = 2;
                break;
            case 3:
                $tip = 3;
                break;
            default:
                $tip = 2;
                break;
        }
        $where = [
            ['type', '=', $tip],
            ['isall', '=', 0],
            ['user_id', '=', $user_id]
        ];

        $data = DB::table('flow_log')->select('foid', 'status', 'amount', 'notes', 'create_at')->where($where)->orderBy('foid', 'desc')->offset($offset)->limit($limit)->get();
        $case = [
            '0' => 'x',
            '1' => '收入',
            '2' => '支出'
        ];
        foreach ($data as $k => $v) {
            $v->create_at = date('Y-m-d H:i:s', $v->create_at);
            $v->cases = $case[$v->status];
        }
        success($data);
    }

    /**
     * description:新美转T积分
     * @author douhao
     * @date 2018/8/24
     */
    public function tranT(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $money = $request->input('money', 0);
        if (empty($user_id) || empty($money)) {
            return error('00000', '参数不全');
        }
        if ($money % 100 != 0) {
            return error('70004', '转换金额必须是100的整数倍');
        }
        //查出用户当前的新美积分
        $ret = DB::table('xps')->select('unlimit')->where('user_id', $user_id)->first();

        if ($ret->unlimit == 0) {
            return error('70000', '当前没有可用H积分');
        }
        if ($money > $ret->unlimit) {
            return error('70006', '流转金额大于所剩额度');
        }
        // 查询当前用户，可用余额转入 T 积分的比例
        $res = DB::table('group_limit')->select('xmps')->where('user_id', $user_id)->first();
        if (empty($res)) {
            return error('70001', '管理端流转限制错误，请联系客服处理');
        }
        $res = explode('-', $res->xmps);

        $reling = $money * $res[1] / 100;


        if ($reling == 0) {
            return error('70001', '管理端流转限制错误，请联系客服处理');
        }
        DB::beginTransaction();
        $num = DB::update('update xm_xps set unlimit = unlimit - ?,reling = reling + ? WHERE user_id = ?', [$reling, $reling, $user_id]);
        // 添加到新美积分到兑换记录
        $current = time();
        $log_data = [
            'user_id' => $user_id,
            'type' => 1,
            'status' => 1,
            'amount' => $reling,
            'notes' => 'H积分转T积分',
            'create_at' => $current,
        ];
        $wid = DB::table('wd')->insertGetId($log_data, 'wid');
        if (empty($num) || empty($wid)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            return success();
        }
    }

    /**
     * description:取消新美流转
     * @author douhao
     * @date 2018/8/24
     */
    public function quitTran(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $wid = $request->input('wid', 0);
        if (empty($user_id) || empty($wid)) {
            return error('00000', '参数不全');
        }
        $where = [
            ['wid', '=', $wid],
            ['type', '=', 1],
            ['status', '=', 1],
        ];
        $ret = DB::table('wd')->where($where)->first();
        if (!$ret) {
            return error('70002', '数据错误');
        }
        $current = time();
        // 剩余额度
        $surplus = round($ret->amount * ($ret->percent / 100), 2);
        // 将当前记录修改为取消状态
        DB::beginTransaction();
        $new = [
            'status' => 3,
            'done_at' => $current
        ];
        $num = DB::table('wd')->where('wid', $wid)->update($new);
        $num1 = DB::update('update xm_xps set unlimit = unlimit + ?,reling = reling - ?,update_at = ? WHERE user_id = ?', [$surplus, $surplus, $current, $user_id]);
        if (empty($num) || empty($num1)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            return success();
        }
    }

    /**
     * description:每日新美流转
     * @author douhao
     * @date 2018/8/24
     */
    public function dailyTran(Request $request)
    {
        $user_id = $request->input('user_id', 0);

        if (empty($user_id)) {
            return error('00000', '参数不全');
        }

        // 查询新美积分的兑换记录
        $where = [
            ['user_id', '=', $user_id],
            ['type', '=', 1],
            ['status', '=', 1],
            ['percent', '>', 0],

        ];
        $ret = DB::table('wd')->where($where)->get();
        if (empty($ret)) {
            return error('70003', '无流转记录');
        }

        // 查询当前用户，可用余额转入 T 积分的比例
        $lim = DB::table('group_limit')->select('xmps')->where('user_id', $user_id)->first();

        $xmps = explode('-', $lim->xmps);

        $current = time();
        $day_num = 0;
        $tran_num = 0;
        $percent = 0;
        $new = [];
        $flag = 0;
        $notes = 'H积分流转T积分';

        // 这是兑换记录
        foreach ($ret as $k => $v) {
            // 假定流转之后的第一次登录
            $day_num = intval(($current - $v->create_at) / (24 * 3600));

            if ($day_num == 0) {
                continue;
            }
            // 流转百分比
            $percent = $day_num * ($xmps[2] / 100);

            $percent = bcsub($percent ,(100 - $v->percent) / 100,2);

            if ($percent == 0) {
                continue;
            }

            if ($percent > $v->percent / 100) {

                $percent = $v->percent / 100;
            }

            // 需要流转的新美积分数额
            // 兑换数额 * (兑换单截至目前的天数 * 配置每日流转百分比)
            $tran_num = $v->amount * $percent;

            // 修改剩余百分比
            $new = [

                'percent' => $v->percent - $percent * 100,
                'done_at' => $current,
            ];

            DB::beginTransaction();
            $num = DB::table('wd')->where('wid', $v->wid)->update($new);

            // 修改流转新美余额
            $num2 = DB::update('update xm_xps set amount = amount - ?,reling = reling - ?,update_at = ? WHERE user_id = ?', [$tran_num, $tran_num, $current, $user_id]);

            // 修改 T 积分可用余额
            $num3 = DB::update('update xm_tps set unlimit = unlimit + ?,update_at = ? WHERE user_id = ?', [$tran_num, $current, $user_id]);

            $amount = DB::table('xps')->where('user_id', $user_id)->pluck('amount')->first();

            $unlimit = DB::table('tps')->where('user_id', $user_id)->pluck('unlimit')->first();

            // 写入流水记录
            // 减去新美积分
            $new = [

                'user_id' => $user_id,
                'type' => 1,
                'status' => 2,
                'amount' => $tran_num,
                'surplus' => $amount,
                'notes' => $notes,
                'create_at' => $current
            ];
            $foid = DB::table('flow_log')->insertGetId($new, 'foid');

            // 累计 T 积分
            $new = [

                'user_id' => $user_id,
                'type' => 2,
                'status' => 1,
                'amount' => $tran_num,
                'surplus' => $unlimit,
                'notes' => $notes,
                'create_at' => $current
            ];
            $foid2 = DB::table('flow_log')->insertGetId($new, 'foid');

            if (empty($num) || empty($num2) || empty($num3) || empty($foid) || empty($foid2)) {
                DB::rollBack();
                $flag = 0;
                continue;
            } else {
                DB::commit();
                $flag = 1;
                continue;
            }
        }
        if ($flag) {
            return success();
        } else {
            return error('99999', '操作失败');
        }
    }

    /**
     * description:T积分转新美
     * @author douhao
     * @date 2018/8/24
     */
    public function tranX(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $money = $request->input('money', 0);
        if (empty($user_id) || empty($money)) {
            return error('00000', '参数不全');
        }
        if ($money % 100 != 0) {
            return error('70004', '转换金额必须是100的整数倍');
        }
        //查询用户T积分
        $tps = DB::table('tps')->where('user_id', $user_id)->first();
        if (!$tps) {
            return error('70005', '账户出错，请稍后再试');
        }
        if ($money > $tps->unlimit) {
            return error('70006', '流转金额大于所用额度');
        }

        $current = time();
        DB::beginTransaction();
        // 修改流转新美余额
        $num = DB::update('update xm_xps set amount = amount + ?,unlimit = unlimit + ?,update_at = ? WHERE user_id = ?', [$money, $money, $current, $user_id]);
        // 修改 T 积分可用余额
        $num2 = DB::update('update xm_tps set unlimit = unlimit - ?,update_at = ? WHERE user_id = ?', [$money, $current, $user_id]);
        $amount = DB::table('xps')->where('user_id', $user_id)->pluck('amount')->first();
        $unlimit = DB::table('tps')->where('user_id', $user_id)->pluck('unlimit')->first();
        // 添加流水
        $new = [
            'user_id' => $user_id,
            'type' => 1,
            'status' => 1,
            'amount' => $money,
            'surplus' => $amount,
            'notes' => 'T积分转入H积分',
            'create_at' => $current,
            'target_type' =>12
        ];
        $foid = DB::table('flow_log')->insertGetId($new, 'foid');
        $new = [
            'user_id' => $user_id,
            'type' => 2,
            'status' => 2,
            'amount' => $money,
            'surplus' => $unlimit,
            'notes' => 'T积分转入H积分',
            'create_at' => $current,
            'target_type'=>12,
        ];
        $foid2 = DB::table('flow_log')->insertGetId($new, 'foid');
        //插入业绩表,用于业绩统计
        //找出所有的上级
        $this->get_up($user_id);
        $user_str = self::$user_ids;
        $user_str .= $user_id;
        $user_name = DB::table('users')->where('user_id', $user_id)->pluck('user_name')->first();
        $performance = [
            'user_id' => $user_id,
            'tp_num'=>$money,
            'tp_gmt_create'=>$current,
            'user_name'=> $user_name,
            'tp_top_user_ids'=>$user_str
        ];
        self::$user_ids = '';
        $tp_id = DB::table('trade_performance')->insertGetId($performance, 'tp_id');
        // 解冻上级冻结的新美积分
        $invite_user_id = DB::table('mq_users_extra')->where('user_id', $user_id)->pluck('invite_user_id')->first();
        $top_xps = DB::table('xps')->where('user_id', $invite_user_id)->first();

        // 没有新美积分被冻结
        if ($top_xps && $top_xps->frozen == 0) {
            if (empty($num) || empty($num2) || empty($foid) || empty($foid2)) {
                DB::rollBack();
                return error('99999', '操作失败');
            } else {
                DB::commit();
                return success();
            }
        }
        //每次释放10%
        $money = $money*0.1;
        if ($money > $top_xps->frozen) {
            $money = $top_xps->frozen; // 全部释放
        }
        $num3 = DB::update('update xm_xps set unlimit = unlimit + ?,frozen = frozen - ?,update_at = ? WHERE user_id = ?', [$money, $money, $current, $top_xps->user_id]);

        // 20180524 0906 V 添加冻结释放流水
        $new = [

            'user_id' => $top_xps->user_id,
            'type' => 1,
            'status' => 0,
            'amount' => $money,
            'surplus' => $top_xps->amount,
            'notes' => '下级T积分转H积分，释放冻结H积分',
            'create_at' => $current
        ];
        $foid3 = DB::table('flow_log')->insertGetId($new, 'foid');
        if (empty($num) || empty($num2) || empty($num3) || empty($foid) || empty($foid2) || empty($foid3) || empty($tp_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            return success();
        }
    }

    /**
     * description:消费转账
     * @author douhao
     * @date 2018/8/24
     */
    public function tranC(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        // 查询可用消费积分
        $consume = DB::table('tps')->where('user_id', $user_id)->pluck('shopp')->first();
        //查询转账手续费
        $transfer_consume_fee = DB::table('shop_config')->where('code', 'xm_transfer_rate_consume_fee')->pluck('value')->first();
        // 个人转账限制
        $today_start = strtotime(date("Y-m-d", time()) . '00:00:00');
        $today_end = $today_start + 3600 * 24;
        $where = [
            ['user_id', '=', $user_id],
            ['type', '=', 3],
            ['notes', 'LIKE', '转账%'],
            ['create_at', '>=', $today_start],
            ['create_at', '<=', $today_end],
        ];
        $apply = DB::table('flow_log')->where($where)->sum('amount');
        $apply = empty($apply) ? 0 : $apply;
        //获取用户是否被限制
        $user_limit = DB::table('mq_users_limit')->where('user_id', $user_id)->first();
        if (($user_limit->end_time >= time() && $user_limit->start_time <= time()) || ($user_limit->start_time <= time() && empty($user_limit->end_time))) {
            //如果时间有效 或者未设置即永久有效
            if ($user_limit->user_limited == 1) {
                $user_limited_consume = '1';
            } else {
                $user_limited_consume = '0';
            }
            if ($user_limit->consume_limited > 0) {
                $daily_consume_transfer_sum_limit = $user_limit->daily_consume_transfer_sum_limit - $apply >= 0 ? $user_limit->daily_consume_transfer_sum_limit - $apply : '0';
                $daily_consume_sum_limit = '' . $daily_consume_transfer_sum_limit . '';
            } else {
                $daily_consume_sum_limit = '0';
            }
        } else {
            $user_limited_consume = '0';
            $daily_consume_sum_limit = '0';
        }

        $data = [
            'consume' => $consume,
            'user_limited_consume' => $user_limited_consume,
            'daily_consume_sum_limit' => $daily_consume_sum_limit,
            'tips' => "说明：\n1.消费积分可到商城消费\n2.消费积分可用激活团队，积分倍增\n3.转账消费积分必须是100的整数倍\n4.请保证账户额度大于您需要转账的和手续费之和。\n5.消费积分转账时，上下级转账免费，其余将会收取您{$transfer_consume_fee}%的手续费。",
        ];
        success($data);
    }

    /**
     * description:消费转账提交
     * @author douhao
     * @date 2018/8/24
     */
    public function dotranC(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $password = $request->input('password', 0);
        $money = $request->input('money', 0);
        $to_user = $request->input('to_user', '');
        $msg = $request->input('msg', 0);
        $needMsg = $request->input('needMsg', '0');
        if (empty($user_id) || empty($money) || empty($password) || empty($to_user)) {
            return error('00000', '参数不全');
        }
        if ($money % 100 != 0) {
            return error('70004', '转换金额必须是100的整数倍');
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
        $mobile_phone = DB::table('users')->where('user_id', $user_id)->pluck('mobile_phone')->first();

        if ($needMsg && $msg) {
            $where = [
                ['veri_mobile', $mobile_phone],
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
//            return error('10008','需重新认证身份');
                    $update_insert_data = [
                        'ip_address' => $ip,
                        'check_time' => date('Y-m-d H:i:s', $now),
                        'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)
                    ];
                    DB::table('ip_safecheck_log')->where('log_id', $safeCheck->log_id)->update($update_insert_data);
                }
            }
        }

        /*检查支付密码*/
        $where = [
            ['user_id', '=', $user_id],
            ['pay_password', '=', $password],
        ];
        $res = DB::table('mq_users_extra', 'new_status', 'invite_user_id')->where($where)->first();
        if (empty($res)) {
            return error('40005', '支付密码错误');
        }

        // 检查账户信息
        $to_user_info = DB::table('users')->select('users.user_id', 'mq_users_extra.new_status', 'mq_users_extra.invite_user_id')->where('users.user_name', $to_user)->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->first();

        if (!$to_user_info) {
            return error('40024', '转账对象不存在');

        }
        if ($to_user_info->user_id == $user_id) {
            return error('40025', '无法给自己转账');
        }
        // 个人转账限制
        $today_start = strtotime(date("Y-m-d", time()) . '00:00:00');
        $today_end = $today_start + 3600 * 24;
        $where = [
            ['user_id', '=', $user_id],
            ['type', '=', 3],
            ['notes', 'LIKE', '转账%'],
            ['create_at', '>=', $today_start],
            ['create_at', '<=', $today_end],
        ];
        $apply = DB::table('flow_log')->where($where)->sum('amount');
        $apply = empty($apply) ? 0 : $apply;
        //获取用户是否被限制
        $user_limit = DB::table('mq_users_limit')->where('user_id', $user_id)->first();
        if (($user_limit->end_time >= time() && $user_limit->start_time <= time()) || ($user_limit->start_time <= time() && empty($user_limit->end_time))) {
            //如果时间有效 或者未设置即永久有效
            if ($user_limit->user_limited == 1) {
                return error('40023', '转账功能暂时关闭');
            }
            if ($user_limit->consume_limited > 0) {
                $daily_consume_transfer_sum_limit = $user_limit->daily_consume_transfer_sum_limit - $apply >= 0 ? $user_limit->daily_consume_transfer_sum_limit - $apply : '0';
                if ($daily_consume_transfer_sum_limit < $money) {
                    return error('40021', '超出转账额度,今天无法进行转账');
                }
            }
        }
        $my_ret = explode('-', $res->new_status); // 我的服务商是谁
        $you_ret = explode('-', $to_user_info->new_status); // 对方的服务商是谁

        if ($my_ret[0] != $you_ret[0]) {
            return error('40026', '不同团队之间无法进行转账');
        }
        //判断是否有团队限制
        if ($my_ret[4] ==1) {
            return error('40023', '转账功能暂时关闭');
        }
        if ($you_ret[4] ==1) {
            return error('40023', '对方转账功能暂时关闭');
        }
        $xm_consume_large_transfer = DB::table('shop_config')->where('code', 'xm_consume_large_transfer')->value('value');
        $xm_consume_large_amount_value = DB::table('shop_config')->where('code', 'xm_consume_large_amount_value')->value('value');
        $xm_transfer_rate_consume_fee = DB::table('shop_config')->where('code', 'xm_transfer_rate_consume_fee')->value('value');
         //判断是是否存在上下级关系
            if ($res->invite_user_id == $to_user_info->user_id || $to_user_info->invite_user_id == $user_id) {
                $fee = 0;
            } else {
                $fee = $xm_transfer_rate_consume_fee * $money / 100;
            }
           //判断金额够不够
        $tps = DB::table('tps')->where('user_id', $user_id)->first();
        if ($money > ($tps->shopp+$fee)) {
            return error('40014', '消费积分额度不足');
        }

        // 是否有未审核的大金额转账记录
        $where = [
            ['from_user', '=', $user_id],
            ['type', '=', 1],
            ['status', '=', 0],
        ];
        $apply = DB::table('transfer_apply')->where($where)->first();
        if ($apply) {
            return error('40022', '转账失败,您还有未审核的转账申请！');
        }

        if ($xm_consume_large_transfer == 1 && $money >= $xm_consume_large_amount_value) {

            $new = [
                'from_user' => $user_id,
                'to_user' => $to_user_info->user_id,
                'type' => 1,
                'status' => 0,
                'amount' => $money,
                'trfee' => $fee,
                'create_at' => time(),
            ];
            $trid = DB::table('transfer_apply')->insertGetId($new, 'trid');
            if (!$trid) {
                return error('99995', '转账失败,请稍后再试！');
            } else {
                $data['msg'] = '为了您的账户安全本次转账需经过管理员审核才能到账，请耐心等待！';
                return success($data);
            }
        } else {
            $redis_name = 'dotranC-' . $user_id;
            if (Redis::exists($redis_name)) {
                return error('99994', '处理中...');
            } else {
                Redis::set($redis_name, '1');
            }
            DB::beginTransaction();

            // 实际金额
            $flag = DB::update('update xm_tps set shopp = shopp - ?,update_at = ? WHERE user_id = ?', [($money+$fee), time(), $user_id]);
            $flag2 = DB::update('update xm_tps set shopp = shopp + ?,update_at = ? WHERE user_id = ?', [$money, time(), $to_user_info->user_id]);
            // 流水记录
            // from
            $new = [
                'user_id' => $user_id,
                'type' => 3,
                'status' => 2, // 支出
                'amount' => $money,
                'surplus' => $tps->shopp - $money,
                'notes' => '转账给：' . $to_user,
                'create_at' => time()
            ];
            $foid = DB::table('flow_log')->insertGetId($new, 'foid');
            // to
            $user_name = DB::table('users')->where('user_id', $user_id)->pluck('user_name')->first();
            $to_shopp = DB::table('tps')->where('user_id', $to_user_info->user_id)->pluck('shopp')->first();
            $new = [
                'user_id' => $to_user_info->user_id,
                'type' => 3,
                'status' => 1, // 收入
                'amount' => $money,
                'surplus' => $to_shopp,
                'notes' => $user_name . ' 转账给我',
                'create_at' => time(),
            ];
            $foid2 = DB::table('flow_log')->insertGetId($new, 'foid');
            if ($fee > 0) {
                // fee
                $new = [

                    'user_id' => $user_id,
                    'type' => 3,
                    'status' => 2, // 支出
                    'amount' => $fee,
                    'surplus' => $tps->shopp - ($money+$fee),
                    'notes' => '转账手续费',
                    'create_at' => time(),
                ];
                $foid3 = DB::table('flow_log')->insertGetId($new, 'foid');
            } else {
                $foid3 = 1;
            }
            if (empty($flag) || empty($flag2) || empty($foid) || empty($foid2) || empty($foid3)) {
                DB::rollBack();
                Redis::del($redis_name);
                return error('99999', '操作失败');
            } else {
                DB::commit();
                Redis::del($redis_name);
                $data['msg'] = '转账成功';
                return success($data);
            }
        }
    }

     /**
     * description:找出所有上级
     * @author douhao
     * @date 2018/8/24
     */
    private function get_up($user_id)
    {
        $user_info = DB::table('mq_users_extra')->select('invite_user_id','user_id')->where('user_id', '=', $user_id)->first();
        if ($user_info->invite_user_id != 0) {
            self::$user_ids = self::$user_ids . $user_info->invite_user_id . ',';
        }else{
           return true;
        }
        $this->get_up($user_info->invite_user_id);
    }

}
