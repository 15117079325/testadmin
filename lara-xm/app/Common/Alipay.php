<?php
class Alipay{

    /**
     * 应用ID
     */
    const APPID = '2019062265613787';
    /**
     *请填写开发者私钥去头去尾去回车，一行字符串
     */
    const RSA_PRIVATE_KEY = 'MIIEowIBAAKCAQEAuC+tCBVOAqw+aCdIIQ9HVBWundPcifLBbbUqakjrPF0lierO
9cDQ334aVRLG5tiuAw4prmArZ0H6u5Z+SE3gGQVObh3t7uEeTY6fRZTl5/HKWx/E
66YhLea7EwqAOouU52W2se6T4KiDhYcoXqG4ZjcV0UiUOMW9hGkpfH3drTBVhZ4Q
uYl74XhrGOhuOBfXcZPMEiVy/pk8q6RAlibdIYlI9sE5FZYem/4kn5uiE7hDYXuI
mzKV3CV3OtUv/OOQFjHtjkTb9+5AkU9xLHJfOGXv3A7ERRYUeyOrI7aP/hhr2R6E
4J6+pYCj6CPB5KdPmXfpw/CWKn6M/XmTo62NSwIDAQABAoIBAH3eBs8JUCA/eP5Q
Kdh9ym7JymSMzZ9vx4OjVHMBlc/Qj8CqN/h1Zcf1MyWECWzkEjaATTee/Mo5qpDb
DT14CnbOy4Qw69JdAQpbNrikQmC5OWIAWd/3zaDCloEyoeJgVMe1GJ6LvX6/afGs
JYhV19/yMPSuNqx9ZT/BZdpvYTfiMKXnoxVLb/gUNGLVaytmTQfRksF7EJ4n32nk
DrruXVJdVnlL0SgXWetq8dljIYOzJPzWjb8FIIz75IjjPK4b8MiHxjr7BD8+gywA
/dL9HpD3rOai+Eq+86QgTMn2+brBXr5ARdE3/2TR/KdntvSlJArjCXZYHwRmrQ9H
RV441kECgYEA5ilF+lLkdVmQAg+9e7Ez6lQXM5N/ziRsewGanN7rOT0e7KEr710Z
Qsch8Dv1XvuaGIbQMODXJojkm/ovyZ/hLg/RkFJlW1Iwld90TJRTxP1jYryDjGIh
FBUmh/UPMbJCOSZuFEAcFmtEt9mzHxNNtM4Di0Ncf0qcZPqwWm7jVNsCgYEAzN0a
Soa1dSyHnJa+mtxNi5wYIHCTOeizyLUv4GFn1d6R42PZS5K8YxebhpiNRCoBYJjG
7NQTEmSGWAHODhaJvAEu29FUbGk56LA1eCPzvvM29+i6ONNbQob1nwxKREc5KFTK
jPKDowVrzYwoO/MEYrJyQJCknMmmT+XcXl0TXFECgYBbTLIKm4kul8mNV8sVXvS2
FodhmTgQgNhbbwZzBeaPPRSgT0rLV8XmfHGVB2PNOsckxY2eZgJSsejlirgcJgTA
Ldw2gMjeEdteCFbs7cXRFaawCxGvxVlTyxQOyIIvd4PXgcwW0luR9Rk8SOpKAHFJ
sJMtUhpGEEW7tMnyBZy+EwKBgCRiwOCrvF7rYcq2G3R13HAHcWGRnRST+BqV08MO
idq6hT7V5So/Daar8rudLLoGm+gEOpCluh1yLUpER8zIw/3YV/JC47O9nMNvSI/m
Esy/deviMfEV2Qef4NA25pnp7IT1SmRuTmMN+2+ujRbYutasyw4coqAWUKuwL8uy
zFWBAoGBAMteX/qSjjNpwbMkOp1hPh/GPxHWXsZqF+OlXDTv8gNaIaFT7YxuW5Wx
L0BmrJI39LoFCEduwZjh1ffmmCSGFFFjfKcCyw5WQ5/CEWXtP5Trb5Pqx6Cxqcto
5IZrjV/1STWOW5Bq8MTsyvAo76gQPwsFkufuxFrhcw/dn2vZaKBW';
    /*
     * 支付宝公钥
     */
    const ALIPAY_RSA_PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAm85PsY21qnntxVKGsoqOI2jKpaa3JKFt32BlQRwMbfegLI6zrZS2lTDP3OQt3+MFgcxcXEY85mWu2TLPwzgVE6E+PbygaKTHg2K+h3MxijkdaH2utb1/YKUp6cFBQ1yzAY6YFPbrj2wqbzeKWVIXF74howS4zS5mmkPydSDyl/cWMejU9AK6kecOO5eAnKG6XZDfgqV3Ys1Y4/WivfQCZ7WnH87/PF+IZUBSe6+cbSiXky7xJRle5Ajym1rsMDq3FvoEOonF13vX1Eb/x7gOwfLLUw1CotX2Lz7FcYIom8McKkZroKxK3e09mygUQuM8PMvp1hY2w6N/2QJ+ElOqPQIDAQAB';
    /**
     * 支付宝服务器主动通知商户服务器里指定的页面
     * @var string
     */
//    private $callback = url("/callback/alipayNotify");

    /**
     *生成APP支付订单信息
     * @param string $orderId   商品订单ID
     * @param string $subject   支付商品的标题
     * @param string $body      支付商品描述
     * @param float $pre_price  商品总支付金额
     * @param int $expire       支付交易时间
     * @return bool|string  返回支付宝签名后订单信息，否则返回false
     */
    function unifiedorder($orderId, $subject,$body,$pre_price,$expire,$bm = 0){
        require_once (app_path('Libs/alipay/AopSdk.php'));
        try{
            $aop = new \AopClient();
            $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
            $aop->appId = self::APPID;
            $aop->rsaPrivateKey = self::RSA_PRIVATE_KEY;
            $aop->format = "json";
            $aop->charset = "UTF-8";
            $aop->signType = "RSA2";
            $aop->alipayrsaPublicKey = self::ALIPAY_RSA_PUBLIC_KEY;
            //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
            $request = new \AlipayTradeAppPayRequest();
            //SDK已经封装掉了公共参数，这里只需要传入业务参数
            $bizcontent = "{\"body\":\"{$body}\","      //支付商品描述
                . "\"subject\":\"{$subject}\","        //支付商品的标题
                . "\"out_trade_no\":\"{$orderId}\","   //商户网站唯一订单号
                . "\"timeout_express\":\"{$expire}m\","       //该笔订单允许的最晚付款时间，逾期将关闭交易
                . "\"total_amount\":\"{$pre_price}\"," //订单总金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]
                . "\"product_code\":\"QUICK_MSECURITY_PAY\""
                . "}";
            if($bm == 1) {
                $request->setNotifyUrl(url('api/Invitational/alipayNotify'));
            } else {
                $request->setNotifyUrl(url('api/callback/alipayNotify'));//$this->callback)
            }
            $request->setBizContent($bizcontent);
            //这里和普通的接口调用不同，使用的是sdkExecute
            $response = $aop->sdkExecute($request);
            //htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
            return htmlspecialchars($response);//就是orderString 可以直接给客户端请求，无需再做处理。
        }catch (\Exception $e){
            return false;
        }

    }

    /**
     * @param $out_biz_no 商户转账唯一订单号。发起转账来源方定义的转账单据ID，用于将转账回执通知给来源方。
    不同来源方给出的ID可以重复，同一个来源方必须保证其ID的唯一性。
    只支持半角英文、数字，及“-”、“_”。
     * @param $payee_type 收款方账户类型。可取值：
    1、ALIPAY_USERID：支付宝账号对应的支付宝唯一用户号。以2088开头的16位纯数字组成。
    2、ALIPAY_LOGONID：支付宝登录号，支持邮箱和手机号格式。
     * @param $payee_account 收款方账户。与payee_type配合使用。付款方和收款方不能是同一个账户。
     * @param $amount 单位元12.23
     * @param $payer_show_name 可选 （付款方姓名，默认支付宝登记的认证姓名或者单位名）
     * @param $payee_real_name 可选（收款方真实姓名，如果本参数不为空，则会校验该账户在支付宝登记的实名是否与收款方真实姓名一致。）
     * @param $remark  可选 （转账备注（支持200个英文/100个汉字）。
    当付款方为企业账户，且转账金额达到（大于等于）50000元，remark不能为空。收款方可见，会展示在收款用户的收支详情中。）
     * description:转账到支付宝用户
     * @author Harcourt
     * @date xxx
     */
     function transToAccount($out_biz_no,$payee_type="ALIPAY_LOGONID",$payee_account,$amount,$payer_show_name,$remark = '余额提现')
    {
        require_once (app_path('Libs/alipay/AopSdk.php'));
        $aop = new AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = self::APPID;
        $aop->rsaPrivateKey = self::RSA_PRIVATE_KEY;
        $aop->alipayrsaPublicKey=self::ALIPAY_RSA_PUBLIC_KEY;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';//GBK
        $aop->format='json';
        $request = new AlipayFundTransToaccountTransferRequest ();
        $request->setBizContent("{" .
            "\"out_biz_no\":\"{$out_biz_no}\"," .
            "\"payee_type\":\"{$payee_type}\"," .
            "\"payee_account\":\"{$payee_account}\"," .
            "\"amount\":\"{$amount}\"," .
            "\"payer_show_name\":\"{$payer_show_name}\"," .
            "\"remark\":\"{$remark}\"" .
            "}");
        $result = $aop->execute ( $request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
//        return $result->$responseNode;
        if(!empty($resultCode)&&$resultCode == 10000){
//            echo "成功";
            return 'SUCCESS';
        } else {
//            echo "失败";
            return 'FAIL';
        }
    }

    /**
     * @param $out_biz_no 商户自己平台的订单号
     * @param $order_id   支付宝转账单据号
     * description: 查询转账订单接口，两个参数二选一，如果都填，则忽略$out_biz_no
     * @author Harcourt
     * @date 2018/4/3
     */
     function transFundOrderQuery($out_biz_no)
    {
        require_once (app_path('Libs/alipay/AopSdk.php'));
        $aop = new AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = self::APPID;
        $aop->rsaPrivateKey = self::RSA_PRIVATE_KEY;
        $aop->alipayrsaPublicKey = self::ALIPAY_RSA_PUBLIC_KEY;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';//GBK
        $aop->format='json';
        $request = new AlipayFundTransOrderQueryRequest ();
        $request->setBizContent("{" .
            "\"out_biz_no\":\"{$out_biz_no}\"," .
            "  }");
        $result = $aop->execute ( $request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        return $result->$responseNode;
        if(!empty($resultCode)&&$resultCode == 10000){
            echo "成功";
        } else {
            echo "失败";
        }

//        {
//            "alipay_fund_trans_order_query_response": {
//            "code": "10000",
//        "msg": "Success",
//        "order_id": "2912381923",
//        "status": "SUCCESS",
//        "pay_date": "2013-01-01 08:08:08",
//        "arrival_time_end": "2013-01-01 08:08:08",
//        "order_fee": "0.02",
//        "fail_reason": "单笔额度超限",
//        "out_biz_no": "3142321423432",
//        "error_code": "ORDER_NOT_EXIST"
//    },
//    "sign": "ERITJKEIJKJHKKKKKKKHJEREEEEEEEEEEE"
//}


    }

     function tradeOrderQuery($out_biz_no,$trade_no=''){
         require_once (app_path('Libs/alipay/AopSdk.php'));
        $aop = new AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = self::APPID;
        $aop->rsaPrivateKey = self::RSA_PRIVATE_KEY;
        $aop->alipayrsaPublicKey= self::ALIPAY_RSA_PUBLIC_KEY;
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';//GBK
        $aop->format='json';
        $request = new AlipayTradeQueryRequest ();
        $request->setBizContent("{" .
            "\"out_trade_no\":\"{$out_biz_no}\"," .
            "\"trade_no\":\"{$trade_no}\"" .
            "  }");
        $result = $aop->execute ( $request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        return $result->$responseNode;
        if(!empty($resultCode)&&$resultCode == 10000){
            echo "成功";
        } else {
            echo "失败";
        }
    }

}