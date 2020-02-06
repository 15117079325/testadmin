<?php
function success($data = array(), $msg = '操作成功')
{
    $res['code'] = '1';
    $res['errorCode'] = '0';
    $res['errorMsg'] = $msg;
    $res['data'] = $data;
    echo json_encode($res);
}

function successCode($data = array(), $msg = '操作成功')
{
    $res['code'] = '1';
    $res['errorCode'] = '0';
    $res['errorMsg'] = $msg;
    $res['data'] = $data;
    echo json_encode($res,256);
}

function signRound($total, $num, $min = 0.01)
{
    $overPlus = $total - $num * $min; // 剩余待发钱数
    $base = 0; // 总基数
    // 存放所有人数据
    $container = array();
    // 每个人保底
    for ($i = 0; $i < $num; $i++) {
        // 计算比重
        $weight = round(lcg_value() * 1000);
        $container[$i]['weight'] = $weight; // 权重
        $container[$i]['money'] = $min; // 最小值都塞进去
        $base += $weight; // 总基数
    }
    $len = $num - 1; // 下面要计算总人数-1的数据,
    for ($i = 0; $i < $len; $i++) {
        $money = floor($container[$i]['weight'] / $base * $overPlus * 100) / 100; // 向下取整,否则会超出
        $container[$i]['money'] += $money;
    }
    // 弹出最后一个元素
    array_pop($container);
    $result = array_column($container, 'money');
    $last_one = round($total - array_sum($result), 2);
    array_push($result, $last_one);
    return $result;
}

function errorcode($errNum = '', $msg = '操作失败', $data = array())
{
    $res['code'] = '0';
    $res['errorCode'] = $errNum;
    $res['errorMsg'] = $msg;
    $res['data'] = $data;
    echo json_encode($res,256);
}

function error($errNum = '', $msg = '操作失败', $data = array())
{
    $res['code'] = '0';
    $res['errorCode'] = $errNum;
    $res['errorMsg'] = $msg;
    $res['data'] = $data;
    echo json_encode($res);
}

function update_custom($user_id, $num)
{
    $customs_order = DB::table('customs_order')->where([['user_id', $user_id], ['status', 1]])->get()->toArray();
    $updateData = [];
    $customNum = 0;
    foreach ($customs_order as $k => $v) {
        if ($num <= 0) {
            break;
        }
        if ($v->surplus_release_balance > $num) {
            $customNum = $customNum + $num;
            $updateData[$v->co_id]['surplus_release_balance'] = $v->surplus_release_balance - $num;
            $num = 0;
        } else {
            $updateData[$v->co_id]['surplus_release_balance'] = 0;
            $updateData[$v->co_id]['status'] = 2;
            $num = $num - $v->surplus_release_balance;
            $customNum = $customNum + $v->surplus_release_balance;
        }
    }
    return [$customNum, $updateData];

}

function get_device()
{
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    dd($agent);
    $device = '';
    if (strpos($agent, 'ios')) {
        $device = 'ios';
    } elseif (strpos($agent, 'android')) {
        $device = 'android';
    }

    return $device;

}

function addDrawUser($cannel, $user_id, $num)
{
    $distriButions = DB::table('master_config')->get()->toArray();
    $distriBution = array_column($distriButions, null, 'code');
    $endTime = $distriBution['luck_end_time']->value;
    $startTime = $distriBution['luck_begin_time']->value;
    $cut_time = time();
    if ($cut_time < strtotime($endTime) && $cut_time >= strtotime($startTime)) {
        $luckdrawtime = time();
        //插入获取抽奖记录表
        $insert_luckuser = [];
        $insert_luckuser['user_id'] = $user_id;
        $insert_luckuser['frequency'] = $num;
        $insert_luckuser['channel'] = $cannel;
        $insert_luckuser['freetime'] = date("Ymd", $luckdrawtime);
        $insert_luckuser['update_time'] = $luckdrawtime;
        $insert_luckuser['create_time'] = $luckdrawtime;
        DB::table('luck_userdraw_log')->insert($insert_luckuser);
        $resultLuckArr = DB::table('luck_draw_user')->where('user_id', $user_id)->first();
        if (empty($resultLuckArr)) {
            $insert_luck_data = [];
            $insert_luck_data['user_id'] = $user_id;
            $insert_luck_data['num'] = $num;
            $insert_luck_data['create_time'] = $luckdrawtime;
            $insert_luck_data['update_time'] = $luckdrawtime;
            DB::table('luck_draw_user')->insert($insert_luck_data);
        } else {
            DB::table('luck_draw_user')->where('user_id', $user_id)->update(['num' => $resultLuckArr->num + 1, 'update_time' => $luckdrawtime]);
        }
        if ($cannel == 1) {
            $distriButions = DB::table('master_config')->get()->toArray();
            $distriBution = array_column($distriButions, null, 'code');
            $update_code = explode("/", $distriBution['luck_opportunity']->value);
            foreach ($update_code as $k => $v) {
                $update_code[$k] = explode(",", $v);
            }
            $updateUser = [];
            $updateUser['user_id'] = $user_id;
            $goods_code_luck = [];
            foreach ($update_code as $item) {
                if (0 >= $item[1]) {
                    $goods_code_luck[] = $item[0];
                }

            }
            $goodsArr = DB::table('luck_goods_draw')->whereIn('goods_code_luck', $goods_code_luck)->get()->toArray();
            $updateGoodsId = array_column($goodsArr, 'id');
            $userGoodsId = DB::table('luck_draw_user')->where(['user_id' => $user_id])->value('goods_id_luck');

            if ($userGoodsId == 0) {
                $updateUser['goods_id_luck'] = implode(",", $updateGoodsId);
            } else {
                $updateUser['goods_id_luck'] = $userGoodsId . ',' . implode(",", $updateGoodsId);
            }
            DB::table('luck_draw_user')->where(['user_id' => $user_id])->update($updateUser);
        }
    }

}

function addDrawcont($cannel, $user_id)
{
    //结束时间
    $endTime = date("Ymd", strtotime('2019-09-16'));
    //开始时间
    $startTime = date("Ymd", strtotime('2019-09-09'));
    //当前时间
    $beginTime = date("Ymd", time());
    if ($beginTime < $endTime && $beginTime >= $startTime) {
        //新增记录
        $luckdrawtime = time();
        $sqlUserDraw = "SELECT user_id,SUM(frequency) AS frequencynum FROM xm_luck_userdraw_log WHERE freetime>=? AND user_id=? AND channel=?";
        $UserDrawData = DB::select($sqlUserDraw, [date("Ymd", $luckdrawtime), $user_id, $cannel]);
        $UserDrawDataArr = json_decode(json_encode($UserDrawData), true);
        $resultLuckArr = DB::table('luck_draw_user')->where('user_id', $user_id)->first();
        if ($cannel == 2) {
            if (!isset($UserDrawDataArr[0]['frequencynum'])) {
                //插入获取抽奖记录表
                $insert_luckuser = [];
                $insert_luckuser['user_id'] = $user_id;
                $insert_luckuser['frequency'] = 1;
                $insert_luckuser['channel'] = $cannel;
                $insert_luckuser['freetime'] = date("Ymd", $luckdrawtime);
                $insert_luckuser['update_time'] = $luckdrawtime;
                $insert_luckuser['create_time'] = $luckdrawtime;
                DB::table('luck_userdraw_log')->insert($insert_luckuser);
                if (empty($resultLuckArr)) {
                    $insert_luck_data = [];
                    $insert_luck_data['user_id'] = $user_id;
                    $insert_luck_data['num'] = 1;
                    $insert_luck_data['create_time'] = $luckdrawtime;
                    $insert_luck_data['update_time'] = $luckdrawtime;
                    DB::table('luck_draw_user')->insert($insert_luck_data);
                } else {
                    DB::table('luck_draw_user')->where('user_id', $user_id)->update(['num' => $resultLuckArr->num + 1, 'update_time' => $luckdrawtime]);
                }

            }
        } else {

            if (!isset($UserDrawDataArr[0]['frequencynum']) || $UserDrawDataArr[0]['frequencynum'] < 3) {
                //插入获取抽奖记录表
                $insert_luckuser = [];
                $insert_luckuser['user_id'] = $user_id;
                $insert_luckuser['frequency'] = 1;
                $insert_luckuser['channel'] = $cannel;
                $insert_luckuser['freetime'] = date("Ymd", $luckdrawtime);
                $insert_luckuser['update_time'] = $luckdrawtime;
                $insert_luckuser['create_time'] = $luckdrawtime;
                DB::table('luck_userdraw_log')->insert($insert_luckuser);
                if (empty($resultLuckArr)) {
                    $insert_luck_data = [];
                    $insert_luck_data['user_id'] = $user_id;
                    $insert_luck_data['num'] = 1;
                    $insert_luck_data['create_time'] = $luckdrawtime;
                    $insert_luck_data['update_time'] = $luckdrawtime;
                    DB::table('luck_draw_user')->insert($insert_luck_data);
                } else {
                    DB::table('luck_draw_user')->where('user_id', $user_id)->update(['num' => $resultLuckArr->num + 1, 'update_time' => $luckdrawtime]);
                }

            }
        }
    }


}

function get_token($user_id = '0')
{
    $str = md5(uniqid(md5(microtime(true), true)) . $user_id);
    $token = sha1($str);
    return $token;
}

function getRandGroup($groups)
{
    if (!is_array($groups) || empty($groups)) {
        return false;
    }
    $k = array_rand($groups, 1);
    if (array_key_exists($k, $groups)) {
        $group = $groups[$k];
        $gc_uid = json_decode($group->gc_uid);
        if (count($gc_uid) >= 1000) {
            getRandGroup($groups);
        } else {
            return $group;
        }
    } else {
        return false;
    }
}

function getDateFormate($time)
{
    if (empty($time)) {
        return '无';
    }
    return date('Y-m-d H:i:s', $time);
}

function strpos_domain($str)
{
    if (strpos($str, 'http') !== false || empty($str)) {
        return $str;
    } else {
        return IMAGE_DOMAIN . $str;
    }
}

/**
 * 生成红包数组
 * @param [type] $total [description]
 * @param [type] $num   [description]
 * @param float $min [description]
 */
function set_red_packet($total, $num, $min = 0.01)
{
    $arr = [];
    if ($total - $num * $min == 0) {
        for ($i = 0; $i < $num; $i++) {
            $arr[] = $min;
        }
        return $arr;
    }
    for ($i = 1; $i < $num; $i++) {
        $safe_total = ($total - ($num - $i) * $min) / ($num - $min);
        $money = mt_rand($min * 100, $safe_total * 100) / 100;
        $arr[] = $money;
        $total = bcsub($total, $money, 2);
    }
    $arr[] = $total;
    shuffle($arr);
    return $arr;
}

function wxpay($sn = '', $body = '', $money = '')
{
    $url = 'http://tool.aiyaole.cn/weixinpay/demo/get_app_sign.php?out_trade_no=' . $sn . '&body=' . $body . '&total_fee=' . $money;
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, FALSE);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//         curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);
    return $data;
}

function curlRequest($url = '', $param_data = '', $method = 'GET')
{
    if (empty($url)) {
        return false;
    }
    $queryString = '';
    if (is_array($param_data)) {
        foreach ($param_data as $key => $value) {
            $queryString .= $key . '=' . urlencode($value) . '&';
        }
        $queryString = substr($queryString, 0, -1);
        //等价于$o = http_build_query($param_data);
    } else {
        $queryString = $param_data;
    }
    if ($method == 'GET') {
        $url .= '?' . $queryString;
    }
    $ch = curl_init();//初始化curl
    curl_setopt($ch, CURLOPT_URL, $url);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);//也可以直接用数组，使用时需要将请求头header的Content-Type:multipart/form-data
    }
    $data = curl_exec($ch);//运行curl,发生错误会产生FALSE
    curl_close($ch);//释放句柄
    if ($data === FALSE) {
        return false;
    } else {
        return $data;
    }

}

if (!function_exists('get_client_ip')) {
    /**
     * 获取客户端IP地址
     * @param int $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param bool $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    function get_client_ip($type = 0, $adv = true)
    {
        $type = $type ? 1 : 0;
        static $ip = NULL;
        if ($ip !== NULL) return $ip[$type];
        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) unset($arr[$pos]);
                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }
}

/**
 * description:关系链更改
 * @author libaowei
 */
function user_like($reUser, $insert_id)
{
    //得到关系链
    $like = $reUser->user_like;
    //把注册的用户插入到推荐人的关系链中
    $like = $like . ',' . $insert_id;
    //注册用户自己的关系链
    DB::table('users')->where('user_id', $insert_id)->update(['user_like' => $like]);
}


if (!function_exists('getBetweenTime')) {
    /**
     * 获取指定日期结点的时间
     */
    function getBetweenTime($time = '', $format = 'Y-m-d', $day = 7)
    {
        $time = $time != '' ? $time : time();
        //组合数据
        $date = [];
        for ($i = 1; $i <= $day; $i++) {
            $date[$i] = date($format, strtotime('+' . $i - $day . ' days', $time));
        }
        return $date;
    }
}
