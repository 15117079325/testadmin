<?php
class Wxpay{

    /**
     * @param $out_trade_no 商户订单编号
     * @param $total_fee 订单价格
     * @param $body  主题内容
     * @return bool|mix|mixed|string
     * @author Harcourt
     * @date 2018/8/16
     */
     function unifiedorder($out_trade_no,$total_fee,$body,$bm = 0)
    {
        require_once (app_path('Libs/weixinpay/WxPayPubHelper/WxPayPubHelper.php'));

        //使用统一支付接口
        $unifiedOrder = new UnifiedOrder_pub();

//	$plat = $_GET['plat'];
//        $out_trade_no = $_GET['out_trade_no'];
//        $total_fee = (float)$_GET['total_fee'];
//        $body = $_GET['body'];


        //设置统一支付接口参数
        //设置必填参数
        //appid已填,商户无需重复填写
        //mch_id已填,商户无需重复填写
        //noncestr已填,商户无需重复填写
        //spbill_create_ip已填,商户无需重复填写
        //sign已填,商户无需重复填写

        $unifiedOrder->setParameter("body",$body);//商品描述
        //自定义订单号，此处仅作举例
        //$timeStamp = time();
        //$out_trade_no = WxPayConf_pub::APPID."$timeStamp";
        $unifiedOrder->setParameter("out_trade_no",$out_trade_no);//商户订单号
        $unifiedOrder->setParameter("total_fee",$total_fee*100);//总金额分
        if($bm == 1) {
            $unifiedOrder->setParameter("notify_url",url('api//Invitational/wxpayNotify'));
        } else {
            $unifiedOrder->setParameter("notify_url",url('api/callback/wxpayNotify'));//通知地址WxPayConf_pub::NOTIFY_URL
        }
        $unifiedOrder->setParameter("trade_type","APP");//交易类型
        //非必填参数，商户可根据实际情况选填
        //$unifiedOrder->setParameter("sub_mch_id","XXXX");//子商户号
        //$unifiedOrder->setParameter("device_info","XXXX");//设备号
        //$unifiedOrder->setParameter("attach","XXXX");//附加数据
        //$unifiedOrder->setParameter("time_start","XXXX");//交易起始时间
        //$unifiedOrder->setParameter("time_expire","XXXX");//交易结束时间
        //$unifiedOrder->setParameter("goods_tag","XXXX");//商品标记
        //$unifiedOrder->setParameter("openid","XXXX");//用户标识
        //$unifiedOrder->setParameter("product_id","XXXX");//商品ID
//	$prepay_id = $unifiedOrder->getPrepayId();

//         $log_ = new \Log_();
//         $log_name = base_path('public/notify_url.log');
//         $log_->log_result($log_name, "【统一下单回调url】:\n" . url('api/callback/wxpayNotify') . "\n");
        $res = $unifiedOrder->getResult();
        if($res['return_code']=='SUCCESS' && $res['result_code'] == 'SUCCESS'){
            //二次签名
            $data['appid'] = $res['appid'];
            $data['partnerid'] = $res['mch_id'];
            $data['noncestr'] = $res['nonce_str'];
            $data['prepayid'] = $res['prepay_id'];
            $data['timestamp'] = time();
            $data['package'] = 'Sign=WXPay';
//            $sign = strtolower($unifiedOrder->getSign($data));
            $sign = $unifiedOrder->getSign($data);
            $res['sign'] = $sign;
            $res['timestamp'] = time();
        }
//{"return_code":"SUCCESS","return_msg":"OK","appid":"wxa1cc8f61c8be12bd","mch_id":"1421370402","nonce_str":"qNUGD6bQyCsoYLiN","sign":"331A01E2EEE59CF73B05F0FF8C20E7F5","result_code":"SUCCESS","prepay_id":"wx20180322152957724d4548080624350513","trade_type":"APP"}

        return $res;
    }

}