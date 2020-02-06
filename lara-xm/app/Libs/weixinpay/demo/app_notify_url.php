<?php
/**
 * 通用通知接口demo
 * ====================================================
 * 支付完成后，微信会把相关支付和用户信息发送到商户设定的通知URL，
 * 商户接收回调信息后，根据需要设定相应的处理流程。
 *
 * 这里举例使用log文件形式记录回调信息。
 */
include_once("./log_.php");
include_once("./db_mysql.php");
include_once("../WxPayPubHelper/WxPayPubHelper.php");
//    使用通用通知接口
$notify = new Notify_pub();

// 存储微信的回调
//$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
$xml = file_get_contents('php://input');
$notify->saveData($xml);

//验证签名，并回应微信。
//对后台通知交互时，如果微信收到商户的应答不是成功或超时，微信认为通知失败，
//微信会通过一定的策略（如30分钟共8次）定期重新发起通知，
//尽可能提高通知的成功率，但微信不保证通知最终能成功。
if($notify->checkSign() == FALSE){
    $notify->setReturnParameter("return_code","FAIL");//返回状态码
    $notify->setReturnParameter("return_msg","签名失败");//返回信息
}else{
    $notify->setReturnParameter("return_code","SUCCESS");//设置返回码
}
$returnXml = $notify->returnXml();
//echo $returnXml;
// print_r(123);
//==商户根据实际情况设置相应的处理流程，此处仅作举例=======

//以log文件形式记录回调信息
$log_ = new Log_();
$log_name="./notify_url.log";//log文件路径
//$log_->log_result($log_name,"【接收到的notify通知】:\n".$xml."\n");
// print_r(123);
if($notify->checkSign() == TRUE)
{
    if ($notify->data["return_code"] == "FAIL") {
        //此处应该更新一下订单状态，商户自行增删操作
        $log_->log_result($log_name,"【通信出错】:\n".$xml."\n");

    }
    elseif($notify->data["result_code"] == "FAIL"){
        //此处应该更新一下订单状态，商户自行增删操作
        $log_->log_result($log_name,"【业务出错】:\n".$xml."\n");

    }
    else{
        //此处应该更新一下订单状态，商户自行增删操作
        $log_->log_result($log_name,"【支付成功】:\n".$xml."\n");
        $postObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        //商户自定义订单
        $out_trade_no= $postObj->out_trade_no;
        //微信交易订单，查询接口最好调用这个
        $transaction_id = $postObj->transaction_id;

        //使用订单查询接口
        $orderQuery = new OrderQuery_pub();


        $orderQuery->setParameter("transaction_id",$transaction_id);//商户订单号
        //获取订单查询结果
        $orderQueryResult = $orderQuery->getResult();

        //商户根据实际情况设置相应的处理流程,此处仅作举例
        if ($orderQueryResult["return_code"] == "FAIL") {
            echo "通信出错：".$orderQueryResult['return_msg']."<br>";
        }
        elseif($orderQueryResult["result_code"] == "FAIL"){
            echo "错误代码：".$orderQueryResult['err_code']."<br>";
            echo "错误代码描述：".$orderQueryResult['err_code_des']."<br>";
        }
        else{
            if($orderQueryResult['trade_state'] == 'SUCCESS'){
                header('Location: http://tool.aiyaole.cn/callback/changestate?sn='.$out_trade_no.'&trade_state='.$orderQueryResult['trade_state']);
                exit();
            }




                //签名验证通过并更新订单状态后
//            $log_->log_result($log_name,$orderQueryResult['trade_state']);
//                $log_->changestate($out_trade_no,$transaction_id,$orderQueryResult['trade_state']);
//            echo "交易状态：".$orderQueryResult['trade_state']."<br>";
//            echo "设备号：".$orderQueryResult['device_info']."<br>";
//            echo "用户标识：".$orderQueryResult['openid']."<br>";
//            echo "是否关注公众账号：".$orderQueryResult['is_subscribe']."<br>";
//            echo "交易类型：".$orderQueryResult['trade_type']."<br>";
//            echo "付款银行：".$orderQueryResult['bank_type']."<br>";
//            echo "总金额：".$orderQueryResult['total_fee']."<br>";
//            echo "现金券金额：".$orderQueryResult['coupon_fee']."<br>";
//            echo "货币种类：".$orderQueryResult['fee_type']."<br>";
//            echo "微信支付订单号：".$orderQueryResult['transaction_id']."<br>";
//            echo "商户订单号：".$orderQueryResult['out_trade_no']."<br>";
//            echo "商家数据包：".$orderQueryResult['attach']."<br>";
//            echo "支付完成时间：".$orderQueryResult['time_end']."<br>";
        }





    }

    //商户自行增加处理流程,
    //例如：更新订单状态
    //例如：数据库操作
    //例如：推送支付完成信息
}



?>