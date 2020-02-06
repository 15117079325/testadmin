<?php

/**
 * 积分api接口调用
*/

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}


function linkApi($uri,$data){

    $hb_query = http_build_query($data);

    $ch = curl_init ();
    curl_setopt ( $ch, CURLOPT_URL, $uri );
    curl_setopt ( $ch, CURLOPT_POST, 1 );
    curl_setopt ( $ch, CURLOPT_HEADER, 0 );
    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt ( $ch, CURLOPT_POSTFIELDS, $hb_query);

    \maqu\Log::debug('$uri:'.$uri);
    \maqu\Log::debug('$hb_query:'.$hb_query);
    \maqu\Log::debug('$data:'.json_encode($data));
    $return = curl_exec ( $ch );
    \maqu\Log::debug('$return:'.$return);
    curl_close ( $ch );
    return json_decode($return,true);
}

/**
 * 用户资金账户
 * @param int $user_id 用户id
 * @return  返回结果例子
  [
    "status"=>1,
    "code"=?"",
    "message"=>"成功",
    "auth_failure"=>0,
    "data"=>[
      "money_shopping"=>"5456465.00",//购物券
      "money_consume"=>"10000.00",//消费积分
      "money_useable"=>"23123.00",//可用积分
      "money_invest"=>"10000.00",//待用、投资积分
      "money_share"=>"10623.00",//分享积分
      "money_cash"=>"10000.00",//现金
      "money_register"=>"12000.00"//注册积分
    ]
  ]

 */
function api_get_user_account($user_id = 0){
    /*
     * 参数判断
     */
    if(!$user_id){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/account_info';
    $data = [
        'user_id' => $user_id,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 用户积分明细
 * @param int $user_id 用户id
 * @param string $type 积分类型
 * @param int $page_no 页码
 * @param int $page_size 每页数量
 * @param int $from_time 查询开始时间（未启用
 * @param int $to_time 查询开始时间（未启用
 * @return array|bool|mix|mixed|string
 */
function api_get_user_account_logs($user_id = 0, $type = 'default', $page_no = 0, $page_size = 5, $from_time = 0, $to_time = 0){
    /*
     * 参数判断
     */
    if(!$user_id){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/balance/'.$type;
    $data = [
        'user_id' => $user_id,
        'page_no' => $page_no,
        'page_size' => $page_size,
        'from_time' => $from_time,
        'to_time' => $to_time
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}
/**
 * 提现申请
 * @param int $user_id 用户id
 * @param string $bank_name 积分类型
 */
function api_get_user_account_out($user_id=0,$amount=0,$bank_name='',$owner_name='',$branch='',$account=0,$saveme=0){
    /*
     * 参数判断
     */
    if(!$user_id){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }
    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/mysys/usercenter/'.$user_id.'/action/apply_takecash';
    //var_dump($apiurl);die();
    $data = [
        'user_id' => $user_id,
        'amount' => $amount,
        'bank_name' => $bank_name,
        'owner_name' => $owner_name,
        'branch' => $branch,
        'account'=>$account,
        'saveme' => $saveme
    ];

    $return = linkApi($apiurl,$data);
    return $return;
}

/**
 * 兑换购物券
 * @param int $user_id 操作者用户id
 * @param string $user_name2 被兑换的用户账号
 * @param int $cash_credit 需要消耗操作者的V积分
 * @param int $consume_credit 需要消耗操作者的消费积分
 * @param int $register_credit 需要消耗操作者的注册积分
 * @param string $pay_password 操作者的支付密码
 * @return array|bool|mix|mixed|string
 */
function api_exchange_shopping($user_id=0,$user_name2='',$cash_credit=0,$consume_credit=0,$register_credit=0,$pay_password=''){

    /*
     * 规则
     *  1.各个字段不可为空
     *  2.cash_credit+consumer_credit+register_credit = 10000
     *  3.cash_credit 占比最低40%
     */
    if(!$user_id || !$user_name2 || !$cash_credit || !$consume_credit || !$pay_password){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/pre_recharge';
    $data = [
        'user_id' => $user_id,
        'user_name2' => $user_name2,
        'cash_money' => $cash_credit,
        'consume_money' => $consume_credit,
        'register_money' => $register_credit,
        'pay_password' => $pay_password
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}



/**
 * 充值服务中心(更名服务中心)
 * @param int $user_id 操作者用户id
 * @param string $user_name2 被充值的用户账号
 * @param int $cash_credit 需要消耗操作者的新美积分
 * @param int $consume_credit 需要消耗操作者的消费积分
 * @param int $register_credit 需要消耗操作者的注册积分
 * @param string $pay_password 操作者的支付密码
 * @return array|bool|mix|mixed|string
 */
function api_wechat_business_recharge($user_id=0,$user_name2='',$cash_credit=0,$consume_credit=0,$register_credit=0,$pay_password='',$recharge_amount=0){

    /*
     * 规则
     *  1.各个字段不可为空
     *  2.cash_credit+consumer_credit+register_credit = 30000
     *  3.cash_credit 占比最低40%
     */
    if(!$user_id || !$user_name2 || !$cash_credit || !$consume_credit || !$pay_password){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/tobe_weishang';
    $data = [
        'user_id' => $user_id,
        'user_name2' => $user_name2,
        'cash_money' => $cash_credit,
        'consume_money' => $consume_credit,
        'register_money' => $register_credit,
        'pay_password' => $pay_password,
        'recharge_amount'=> $recharge_amount,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 现金转账
 * @param int $user_id
 * @param string $account_type
 * @param string $user_name2
 * @param int $amount
 * @return array|bool|mix|mixed|string
 */
function api_account_transfer($user_id=0,$account_type='',$user_name2='',$amount=0){
    /*
     * 规则
     *  1.各个字段不可为空
     */
    if(!$user_id || !$user_name2 || !$account_type || !$amount){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/transfer/'.$account_type;
    $data = [
        'user_id' => $user_id,
        'user_name2' => $user_name2,
        'account_type' => $account_type,
        'amount' => $amount
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 积分转账
 * @param int $user_id 用户id
 * @param string $user_name2 对方用户名
 * @param int $amount 转账数量
 * @param string $account_type 转账积分类型
 * @return array|bool|mix|mixed|string
 */
function api_transfer_credit($user_id=0,$user_name2='',$amount=0,$account_type=''){

    /*
     * 规则
     *  1.各个字段不可为空
     */
    if(!$user_id || !$user_name2 || !$amount || !$account_type ){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/transfer/'.$account_type;
    $data = [
        'user_name2' => $user_name2,
        'amount' => $amount,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 账户充值
 * @param int $user_id 用户id
 * @param string $account_type 充值类型
 * @param int $amount 充值金额
 * @return array|bool|mix|mixed|string
 */
function api_recharge($user_id=0,$account_type='',$amount=0){

    /*
     * 规则
     *  1.各个字段不可为空
     */
    if(!$user_id || !$account_type || !$amount ){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/recharge/'.$account_type;
    $data = [
        'amount' => $amount,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 账户提现
 * @param int $user_id 用户id
 * @param string $apply_id 提现id
 * @return array|bool|mix|mixed|string
 */
function api_take_cash_approval($user_id='0',$apply_id='',$audit='',$app_result=''){

    /*
     * 规则
     *  1.各个字段不可为空
     */

    if(!$user_id || !$apply_id || !$app_result){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/mysys/usercenter/'.$user_id.'/action/approval_takecash/'.$apply_id;
    // echo $apiurl;die();
    $data = [
        'button' => strval($audit),
        'desc' => $app_result,
    ];
    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 倍增列表
 * @param int $user_id 用户id
 * @param int $invest_status 投资状态
 * @param int $page_no 页码
 * @param int $page_size 每页数量
 * @return array|bool|mix|mixed|string
 */
function api_get_invest_lists($user_id = 0, $invest_status = 0, $page_no = 0, $page_size = 5){
    /*
     * 参数判断
     */
    if(!$user_id){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/mysys/usercenter/'.$user_id.'/action/list_invest';
    $data = [
        'user_id' => $user_id,
        'invest_status' => $invest_status,
        'page_no' => $page_no,
        'page_size' => $page_size,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 积分复投
 * @param int $user_id 用户id
 * @param int $invest_id 投资id
 * @param int $amount 投资额度
 * @return array|bool|mix|mixed|string
 */
function api_re_invest($user_id = 0, $invest_id = 0, $amount = 0 ){
    /*
     * 参数判断
     */
    if(!$user_id || !$invest_id || !$amount){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
        $apiurl = API_SITE.'/mysys/usercenter/'.$user_id.'/action/re_invest/'.$invest_id;
    $data = [
        'user_id' => $user_id,
        'invest_id' => $invest_id,
        'amount' => $amount,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

function api_release_invest($user_id = 0, $release_at = 0){
    /*
         * 参数判断
         */
    if(!$user_id || !$release_at){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/mysys/usercenter/'.$user_id.'/action/release_invest';
    $data = [
        'user_id' => $user_id,
        'release_at' => $release_at
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 积分兑换
 * @param int $user_id 用户id
 * @param int $amount 额度
 * @param string $account_type_from 积分来源
 * @param string $account_type_to 积分转到
 * @return array|bool|mix|mixed|string
 */
function api_exchange_credit($user_id = 0, $amount = 0,$account_type_from = '', $account_type_to = '' ){
    /*
     * 参数判断
     */
    if(!$user_id || !$amount || !$account_type_from || !$account_type_to){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/exchange';
    $data = [
        'user_id' => $user_id,
        'amount' => $amount,
        'account_type' => $account_type_from,
        'account_type2' => $account_type_to,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 清楚api中的config缓存文件
 */
function api_clean_api_config(){
    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/forgetConfig';
    $data = [
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * H单暂停切换
 */
function api_swith_hdan_pause($user_id,$new_status){

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/hdan/pause/swith';
    $data = [
        'user_id'=>$user_id,
        'new_status'=>$new_status
    ];

    $return = linkApi($apiurl,$data);

    return $return;

}

/**
 * 终止复投 不去复投了 要积分了
 * @param int $user_id 用户id
 * @param int $invest_id 投资id
 * @param int $amount 投资额度
 * @return array|bool|mix|mixed|string
 */
function api_stop_invest($user_id = 0, $invest_id = 0 ){
    /*
     * 参数判断
     */
    if(!$user_id || !$invest_id){
        return [
            'status' => 2,
            'message' => '提交信息有误，请填写符合的数据',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/mysys/usercenter/'.$user_id.'/action/stop_invest/'.$invest_id;
    $data = [
        'user_id' => $user_id,
        'invest_id' => $invest_id,
    ];

    $return = linkApi($apiurl,$data);

    return $return;
}

/**
 * 用户充值申请
 * @param $user_id 用户id
 * @param $amount 金额
 * @return array|bool|mix|mixed|string
 */
function api_apply_recharge($user_id,$amount){

    /*
    * 参数判断
    */
    if(!$user_id || !$amount){
        return [
            'status' => 0,
            'message' => '参数不正确',
        ];
    }

    /*
     * 接口调用
     */
    $apiurl = API_SITE.'/msys/usercenter/'.$user_id.'/action/apply_recharge/cash';
    $data = [
        'user_id' => $user_id,
        'amount' => $amount,
    ];

    $return = linkApi($apiurl,$data);

    return $return;

}

/**
 * 身份证识别
 * @param $pic 身份证图片
 * @param $type 1 正面 2反面
 * @return array
 */
function api_check_card($input,$type){


    /*
    * 参数判断
    */
    if(!$input || !$type){
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
    array_push($headers, "Content-Type".":"."application/json; charset=UTF-8");
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

function base64EncodeImage ($image_file) {
    $base64_image = '';
    $image_info = getimagesize($image_file);
    $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
//    $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
    $base64_image = chunk_split(base64_encode($image_data));
    return $base64_image;
}
