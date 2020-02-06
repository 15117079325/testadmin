<?php

namespace App\Http\Controllers\Api;

use App\Events\CustomsMoneyEvent;
use App\Events\NewOrderEvent;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\CustomsController;

class CallbackController extends Controller
{
    const PAYWAY_ALIPAY = '2';
    const PAYWAY_WXPAY = '3';

    /**
     * description:alipay
     * @author Harcourt
     * @date 2018/8/9
     */
    public function alipayNotify()
    {
        require_once(app_path('Libs/alipay/AopSdk.php'));
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAm85PsY21qnntxVKGsoqOI2jKpaa3JKFt32BlQRwMbfegLI6zrZS2lTDP3OQt3+MFgcxcXEY85mWu2TLPwzgVE6E+PbygaKTHg2K+h3MxijkdaH2utb1/YKUp6cFBQ1yzAY6YFPbrj2wqbzeKWVIXF74howS4zS5mmkPydSDyl/cWMejU9AK6kecOO5eAnKG6XZDfgqV3Ys1Y4/WivfQCZ7WnH87/PF+IZUBSe6+cbSiXky7xJRle5Ajym1rsMDq3FvoEOonF13vX1Eb/x7gOwfLLUw1CotX2Lz7FcYIom8McKkZroKxK3e09mygUQuM8PMvp1hY2w6N/2QJ+ElOqPQIDAQAB';
        //此处验签方式必须与下单时的签名方式一致


        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        //验签通过后再实现业务逻辑，比如修改订单表中的支付状态。
        /**
         * ①验签通过后核实如下参数trade_status、out_trade_no、total_amount、seller_id
         * ②修改订单表
         **/
        //打印success，应答支付宝。必须保证本界面无错误。只打印了success，否则支付宝将重复请求回调地址。
        if ($flag) {
            if ($_POST['trade_status'] == 'TRADE_SUCCESS'
                || $_POST['trade_status'] == 'TRADE_FINISHED') {
                //处理交易完成或者支付成功的通知
                $order_no = $_POST['out_trade_no'];
                $this->dealOrder($order_no, self::PAYWAY_ALIPAY);
                $redis = app('redis.connection');
                $redis->rpush('orderPay', $order_no);
                die('success');

            } else {
                die('fail');
            }
        }


    }

    /**
     * description:wxpay
     * @author Harcourt
     * @date 2018/8/9
     */
    public function wxpayNotify()
    {
        $redis = app('redis.connection');
//        $redis = app('redis.connection');
        include_once(app_path('Libs/weixinpay/WxPayPubHelper/WxPayPubHelper.php'));
        //使用通用通知接口
        $notify = new \Notify_pub();


        // 存储微信的回调
        $xml = $_REQUEST;
        if ($xml == null) {
            $xml = file_get_contents("php://input");
        }

        if ($xml == null) {
            $xml = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        }

        //$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
//        $xml = file_get_contents('php://input');
        $log_ = new \Log_();
        $log_name = base_path('public/notify_url.log');
        if ($xml == null) {
            $log_->log_result($log_name, "【接收到的信息是空的】:\n" . $xml . "\n");
        }
        try {
            $notify->saveData($xml);
        } catch (\Exception $e) {
            $log_->log_result($log_name, "【接收到的xml信息】:\n" . $xml . "\n");
        }

        //验证签名，并回应微信。
        //对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
        //微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
        //尽可能提高通知的成功率，但微信不保证通知最终能成功。
        if ($notify->checkSign() == FALSE) {
            $notify->setReturnParameter("return_code", "FAIL");//返回状态码
            $notify->setReturnParameter("return_msg", "签名失败");//返回信息
        } else {
            $notify->setReturnParameter("return_code", "SUCCESS");//设置返回码
        }
        $returnXml = $notify->returnXml();
        //echo $returnXml;
        //==商户根据实际情况设置相应的处理流程，此处仅作举例=======

        //以log文件形式记录回调信息

        $log_->log_result($log_name, "【接收到的notify通知】:\n" . $xml . "\n");
        if ($notify->checkSign() == TRUE) {
            if ($notify->data["return_code"] == "FAIL") {
                //此处应该更新一下订单状态，商户自行增删操作
                $log_->log_result($log_name, "【通信出错】:\n" . $xml . "\n");

            } elseif ($notify->data["result_code"] == "FAIL") {
                //此处应该更新一下订单状态，商户自行增删操作
                $log_->log_result($log_name, "【业务出错】:\n" . $xml . "\n");

            } else {
                //此处应该更新一下订单状态，商户自行增删操作
                $log_->log_result($log_name, "【支付成功】:\n" . $xml . "\n");
                $postObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

                //商户自定义订单
                $out_trade_no = $postObj->out_trade_no;
                //插入redis
//                $redis->rpush('orderPay', $out_trade_no);
                //微信交易订单，查询接口最好调用这个
                $transaction_id = $postObj->transaction_id;

                $log_->log_result($log_name, "【postObjcet-out_trade_no-transaction_id】:\n" . $out_trade_no . '-' . $transaction_id . "\n");

                //使用订单查询接口
                $orderQuery = new \OrderQuery_pub();

                $orderQuery->setParameter("out_trade_no", $out_trade_no);//商户订单号
                $orderQuery->setParameter("transaction_id", $transaction_id);//微信订单号
                //获取订单查询结果
                $orderQueryResult = $orderQuery->getResult();

                $log_->log_result($log_name, "【return_code】:\n" . $orderQueryResult['return_code'] . "\n");
                //商户根据实际情况设置相应的处理流程,此处仅作举例
                if ($orderQueryResult["return_code"] == "FAIL") {
                    $log_->log_result($log_name, "通信出错：" . $orderQueryResult['return_msg'] . "\n");
//                    echo "通信出错：" . $orderQueryResult['return_msg'] . "<br>";
                } elseif ($orderQueryResult["result_code"] == "FAIL") {
                    $log_->log_result($log_name, "错误代码：" . $orderQueryResult['err_code'] . "\n");
                    $log_->log_result($log_name, "错误代码描述：" . $orderQueryResult['err_code_des'] . "\n");

//                    echo "错误代码：" . $orderQueryResult['err_code'] . "<br>";
//                    echo "错误代码描述：" . $orderQueryResult['err_code_des'] . "<br>";
                } else {
                    $log_->log_result($log_name, "【trade_state】:\n" . $orderQueryResult['trade_state'] . "\n");

                    if ($orderQueryResult['trade_state'] == 'SUCCESS') {
                        //业务修改
                        $redis = app('redis.connection');
                        $redis->rpush('orderPay', $out_trade_no);
                        $this->dealOrder($out_trade_no, self::PAYWAY_WXPAY);
                    }

                    //签名验证通过并更新订单状态后


                }


            }
        }
    }

    private function dealOrder($order_no, $payway)
    {

        $order = DB::table('orders')->select('order_id', 'user_id', 'order_money', 'order_discount', 'order_cash', 'order_balance', 'order_type', 'order_status')->where('order_sn', $order_no)->first();

        $rrcustom = DB::table('customs_order')->where('user_id', $order->user_id)->get();


        if ($rrcustom->isEmpty()) {
            addDrawcont(4, $order->user_id);
        }

        if ($order->order_status == 2) {
            return true;
        }

        if ($order && $order->order_type == PRODUCT_TYPE_PINPAI) {
            $this->brandOrder($order, $payway);
        } elseif ($order && $order->order_type == PRODUCT_TYPE_BAODAN) {
            $update_data = [
                'order_status' => '2',
                'order_gmt_pay' => time(),
                'order_payway' => $payway
            ];
            DB::table('orders')->where('order_id', $order->order_id)->update($update_data);
//            event(new CustomsMoneyEvent($order));
        } elseif ($order && $order->order_type == PRODUCT_TYPE_BAODAN_MONEY) {
            $update_data = [
                'order_status' => '2',
                'order_gmt_pay' => time(),
                'order_payway' => $payway
            ];
            DB::table('orders')->where('order_id', $order->order_id)->update($update_data);
//            event(new NewOrderEvent($order));
        }

    }

    private function brandOrder($order, $payway)
    {
        $now = time();
        if ($payway == self::PAYWAY_ALIPAY) {
            $notes = '购买品牌商品,支付宝支付';
        } elseif ($payway == self::PAYWAY_WXPAY) {
            $notes = '购买品牌商品,微信支付';
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

    private function moneyOrder($order, $payway)
    {
        $now = time();
        if ($payway == self::PAYWAY_ALIPAY) {
            $notes = '购买新人赠优惠券商品,支付宝支付';
        } elseif ($payway == self::PAYWAY_WXPAY) {
            $notes = '购买新人赠优惠券商品,微信支付';
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
        //赠送余额
        $customs_config = DB::table('master_config')->where('tip', 'c')->get()->toArray();
        $customs_config = array_column($customs_config, 'value', 'code');
        $array = explode(':', $customs_config['give_ratio']);
        $surplus_release_balance = ($order->order_cash * $array[1]) / $array[0];
        $account = DB::table('user_account')->where('user_id', $order->user_id)->first();
        if ($account) {
            DB::update('UPDATE xm_user_account SET release_balance = release_balance + ?,update_at = ? WHERE user_id = ?', [$surplus_release_balance, $now, $order->user_id]);
            $base_data['type'] = FLOW_LOG_TYPE_RELEASE_BALANCE;
            $base_data['status'] = 1;
            $base_data['amount'] = $surplus_release_balance;
            $base_data['surplus'] = $account->release_balance;
            $base_data['notes'] = '购买商品，赠送待释放优惠券';
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
}
