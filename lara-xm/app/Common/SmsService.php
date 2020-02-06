<?php


function sendSms($mobile, $code, $type = '1')
{
        switch ($type){

            case 1 :
                $templateCode = 'SMS_165107208';//用户注册验证码 SMS_141895253
                break;
            case 2 :
                $templateCode = 'SMS_167051843';//登录确认验证码 SMS_141920219
                break;
            case 3 :
                $templateCode = 'SMS_141940234';//手机号改绑 SMS_141940234

                break;
            case 4 :
                $templateCode = 'SMS_167041832';//身份验证用户密码修改 SMS_142000251
                break;

            default:
                $templateCode = 'SMS_167041832';//通用模板 SMS_86720064
                break;
        }
//    $templateCode = 'SMS_86720064';
    require_once(dirname(dirname(__DIR__)) . '/app/Libs/msg/api_demo/SmsDemo.php');

    $demo = new SmsDemo();
    $response = $demo->sendSms(
        "火单", // 短信签名  创新美
        $templateCode, // 短信模板编号
        $mobile, // 短信接收者
        Array(  // 短信模板中字段的值
            "code" => $code,
            "product" => "dsd"
        ),
        "123"
    );
    return (array)$response;
}

