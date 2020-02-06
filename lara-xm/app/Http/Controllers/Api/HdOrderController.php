<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class HdOrderController extends Controller
{

    const PAYWAY_ALIPAY = '2';
    const PAYWAY_WXPAY = '3';
    const PAYWAY_WALLETPAY = '4';

    /**
     * description:进行支付
     * @author libaowei
     * @date 2019/8/17
     */
    public function onlinePay(Request $request)
    {
        //订单号
        $order_id = $request->input('order_id', 0);
        //支付类型
        $type = $request->input('type');//1、支付宝2、微信
        //用户ID
        //$user_id = $request->input('user_id');
        //表示支付的半永久报名
        $bm = 1;
        if (empty($order_id) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');
        }

        $order = DB::table('hd_orders')->where('id', $order_id)->first();
        if (empty($order) || $order->order_status != 1 || $order->order_cancel != 1) {
            return error('99998', '非法操作');
        }

        $sn = $order->order_sn;
        $need_money = $order->order_money;
        $body = '半永久活动费用';
        if ($type == '1') {
            $alipay = new \Alipay();
            $subject = '火单';
            $expire = time() + 60 * 5;
            // $need_money = '0.01';
            $res = $alipay->unifiedorder($sn, $subject, $body, $need_money, $expire, $bm);
            $data['sign'] = $res;

        } elseif ($type == '2') {
            $wxpay = new \Wxpay();
            // $need_money = '0.01';
            $data = $wxpay->unifiedorder($sn, $need_money, $body, $bm);
        }
        $data['order_id'] = $order_id;

        success($data);

    }

    /**
     * description:进行支付
     * @author libaowei
     * @date 2019/8/17
     */
    public function walletPay(Request $request)
    {
        //订单号
        $order_id = $request->input('order_id', 0);
        //支付类型
        $type = $request->input('type');//1、支付宝2、微信、3钱包
        //密码
        $password = $request->input('password');

        if (empty($order_id) || !in_array($type, [1, 2, 3])) {
            return error('00000', '参数不全');
        }

        $order = DB::table('orders')->where('order_id', $order_id)->first();
        if (empty($order) || $order->order_status != 1 || $order->order_cancel != 1) {
            return error('99998', '非法操作');
        }
        $user_extra = DB::table('users')->select('mq_users_extra.pay_password', 'users.clientid', 'users.device')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('users.user_id', $order->user_id)->first();
        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }
        if (strcmp($user_extra->pay_password, $password) !== 0) {
            return error('40005', '支付密码不正确');
        }
        $user = DB::table('users')->where('user_id', $order->user_id)->first();
        $money_num = $user->wallet_balance - $order->order_cash;
        if ($money_num < 0) {
            return error('99998', '余额不足');
        }
        DB::table('users')->where('user_id', $order->user_id)->update(['wallet_balance' => $money_num]);
        $sn = $order->order_sn;
        $need_money = $order->order_money;
        if ($type == '3') {
            $this->wallet($sn, $need_money);
        }
        $data['order_id'] = $order_id;

        success($data);

    }

    private function wallet($order_no, $payway)
    {
        $order = DB::table('orders')->select('order_sn', 'order_id', 'user_id', 'order_money', 'order_discount', 'order_cash', 'order_balance', 'order_type', 'order_status')->where('order_sn', $order_no)->first();
        $rrcustom = DB::table('customs_order')->where('user_id', $order->user_id)->get();

        if ($rrcustom->isEmpty()) {
            addDrawcont(4, $order->user_id);
        }
        if ($order->order_status == 2) {
            return true;
        }
        $redis = app('redis.connection');
        $redis->rpush('orderPay', $order->order_sn);
        if ($order && $order->order_type == PRODUCT_TYPE_PINPAI) {
            $this->walletOrder($order, $payway);
        } elseif ($order && $order->order_type == PRODUCT_TYPE_BAODAN) {
            $update_data = [
                'order_status' => '2',
                'order_gmt_pay' => time(),
                'order_payway' => $payway
            ];
            DB::table('orders')->where('order_id', $order->order_id)->update($update_data);
        } elseif ($order && $order->order_type == PRODUCT_TYPE_BAODAN_MONEY) {
            $update_data = [
                'order_status' => '2',
                'order_gmt_pay' => time(),
                'order_payway' => $payway
            ];
            DB::table('orders')->where('order_id', $order->order_id)->update($update_data);
        }
    }

    private function walletOrder($order, $payway)
    {
        $now = time();
        $notes = '';

        if ($payway == self::PAYWAY_WALLETPAY) {
            $notes = '购买品牌商品,钱包支付';
        }
        $update_data = [
            'order_status' => '2',
            'order_gmt_pay' => $now,
            'order_payway' => $payway
        ];
        DB::beginTransaction();
        DB::table('orders')->where('order_id', $order->order_id)->update($update_data);

        $base_data = [
            'user_id' => $order->user_id,
            'status' => 2,
            'create_at' => $now,
            'type' => FLOW_LOG_TYPE_CASH,
            'amount' => $order->order_cash,
            'notes' => $notes,
            'target_id' => $order->order_id,
            'target_type' => 1
        ];
        $foid1 = DB::table('flow_log')->insertGetId($base_data, 'foid');
        $account = DB::table('user_account')->where('user_id', $order->user_id)->first();
        if ($account) {
            DB::table('user_account')->where('user_id', $order->user_id)->decrement('pending_balance', $order->order_balance);
            $base_data['type'] = FLOW_LOG_TYPE_BALANCE;
            $base_data['status'] = 2;
            $base_data['amount'] = $order->order_balance;
            $base_data['surplus'] = $account->balance;
            $base_data['notes'] = '购买品牌商品,消耗余额';
            $foid2 = DB::table('flow_log')->insertGetId($base_data, 'foid');
            if (empty($foid2)) {
                DB::rollBack();
            }
        }
        if (empty($foid1)) {
            DB::rollBack();
        } else {
            DB::commit();
        }

    }


    /**
     * description:检查订单是否已完成支付
     * @author libaowei
     * @date 2019/8/17
     */
    public function checkOrderPay(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);

        if (empty($user_id) || empty($order_id)) {
            return error('00000', '参数不全');
        }
        $order = DB::table('hd_orders')->where('id', $order_id)->first();
        if (empty($order) || $user_id != $order->user_id || $order->order_cancel != 1) {
            return error('99998', '非法操作');
        }

        if ($order->order_status != 2) {
            $msg = '支付失败';
        } else {
            $msg = '支付成功';

            if ($order->type == 2) {
                //查询投票信息
                $vote = DB::table('hd_vote')->where('join_order', $order->id)->first();
                //更新用户表的总投票数
                $user_votes_num = DB::table('hd_users')->where('user_id', $vote->user_id)->increment('poll_sum', $vote->votes_num);
            }

            $title = '订单成功通知';

            $content = '订单已支付成功';

            $mtype = '5';
            $custom_content = ['id' => $order_id, 'type' => $mtype, 'content' => $content, 'title' => $title];

            $push_data = array(
                'user_id' => $user_id,
                'm_type' => $mtype,
                'o_id' => $order_id,
                'm_title' => $title,
                'm_read' => '1',
                'm_content' => $content,
                'm_gmt_create' => time()
            );
            $message_id = DB::table('message')->insertGetId($push_data, 'm_id');
            if ($message_id) {
//                $bol = $toUser->device=='android'?true:false;
                $bol = false;
                GeTui::push($toUser->clientid, $custom_content, $bol);
            }
        }

        success([], $msg);
    }
}
