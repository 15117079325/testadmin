<?php

header("Content-Type: text/html; charset=utf-8");
/*
|- 20180507 1122 V 各种金额操作
|- use: include_once ROOT_PATH . 'includes/lib_tran.php';
*/

/*
|- 20180525 0938 V 获取当前用户是否可提现
*/
function get_wdstatus($uid)
{

    if (!$uid) {
        return false;
    }

    $db = $GLOBALS['db'];

    // 查询后台配置
    $list = get_master_config();

    $hint = ['sus' => 1, 'msm' => []];

    $_SESSION['wd_status'] = $hint;

    $cur_day = date('w');
    $cur_hour = date('H');

    $time = explode('-', $list['ttime']);

    $week_arr = [

        1 => '一',
        2 => '二',
        3 => '三',
        4 => '四',
        5 => '五',
        6 => '六',
        7 => '日'
    ];
    if ($time[1] == 7 && $cur_day == 0) {
        $cur_day = $time[1];
    }

    if ($cur_day < $time[0] || $cur_day > $time[1]) {

        $hint['sus'] = 2;
        $hint['msm']['day'] = '兑换的日期在周' . $week_arr[$time[0]] . '到周' . $week_arr[$time[1]] . '。';

        $_SESSION['wd_status'] = $hint;

    }

    if ($cur_day != $time[0] && $time[0] == $time[1]) {

        $hint['sus'] = 2;
        $hint['msm']['day'] = '兑换的日期在每周' . $week_arr[$time[0]];

        $_SESSION['wd_status'] = $hint;

    }

    if ($cur_hour < $time[2] || $cur_hour >= $time[3]) {

        $hint['sus'] = 2;
        $hint['msm']['time'] = '兑换的时间在' . $time[2] . '点 - ' . $time[3] . '点。';

        // 20180525 1917 V 写死
        $hint['msm']['day'] = '';
        $hint['msm']['time'] = '兑换时间周一至周五 09：00 到 14：00 节假日除外';

        $_SESSION['wd_status'] = $hint;
    }

    // 如果有未审核的，不能多次申请
    if ($list['tmore'] == 0) {

        $wd = $db->getRow("SELECT * FROM `xm_wd` WHERE `user_id` = $uid AND `type` = 2 AND  `status` = 0");

        if ($wd) {

            $hint['sus'] = 2;
            $hint['msm']['have'] = "您尚有未审核的兑换申请。";

            $_SESSION['wd_status'] = $hint;
        }
    }

    $hint['msm']['okmoney']['tip'] = '';
    $hint['msm']['okmoney']['num'] = 0;

    // 如果本周提现 超出限制

    if ($list['addup'] > 0) {

        $okmoney = $list['addup'];

        $themoney = get_week_tscore($uid);

        if ($themoney) {

            $okmoney = $list['addup'] - $themoney;
        }

        $hint['sus'] = 2;
        $hint['msm']['okmoney']['tip'] = "本周可兑换额度： " . $okmoney;
        $hint['msm']['okmoney']['num'] = $okmoney;

        if ($okmoney >= 0 && count($hint['msm']) == 1) {

            $hint['sus'] = 1;
        }

        $_SESSION['wd_status'] = $hint;
    }

    return $hint;
}


/*
|- V 查询提现配置
*/
function get_master_config($type = 't')
{

    // 查询后台配置
    $sql = "SELECT * FROM `xm_master_config` WHERE `tip` = '$type'";
    $conf = $GLOBALS['db']->getAll($sql);

    $list = [];

    foreach ($conf as $k => $v) {

        $list[$v['code']] = $v['value'];
    }

    return $list;
}


/*
|- 20180621 1659 V 当前周 提现的 T 积分 / 购买精品花费的 T 积分总和
*/
function get_week_tscore($uid)
{

    if (!$uid) {
        return false;
    }

    $beginWeek = mktime(0, 0, 0, date("m"), date("d") - date("w") + 1, date("Y"));
    $endWeek = mktime(23, 59, 59, date("m"), date("d") - date("w") + 7, date("Y"));

    // 这一周内 申请中/已通过 的提现申请
    $wd = $GLOBALS['db']->getRow("SELECT SUM(amount) AS amount FROM `xm_wd` WHERE `user_id` = $uid AND `type` = 2 
          AND `status` IN (0,1) AND (`create_at` BETWEEN $beginWeek AND $endWeek)");

    // 这一周内 购买精品花费的 T 积分总和
    $buy = $GLOBALS['db']->getRow("SELECT SUM(amount) AS weekbuy FROM xm_flow_log WHERE user_id = $uid AND type = 2
           AND notes ='购买精品' AND (create_at BETWEEN $beginWeek AND $endWeek)");

    return $wd['amount'] + $buy['weekbuy'];
}


/*
|- 20180621 1327 V 团队提现限制
|- money - 提现/购买精品的钱数
|- type - 类型| out-提现/buy-购买精品
*/
function group_cashout_limit($uid, $money, $type)
{

//    if (!$uid || !$money || !$type) { return false; }
//
//    // 团队的每日提现设置
//    $limit = $GLOBALS['db']->getRow("SELECT xmts,tstime FROM xm_group_limit WHERE user_id = $uid");
//
//    // 20180628 团队提现时间限制
//    if ($type == 'out') {
//
//        $tstime = explode('-', $limit['tstime']);
//        $cur_day = date('w');
//        $cur_hour = date('H');
//        $week_arr = [
//
//            1 => '一',
//            2 => '二',
//            3 => '三',
//            4 => '四',
//            5 => '五',
//        ];
//
//        $hint = ['code'=>2, 'msg'=>'您所在的团队兑换时间在 周'.$week_arr[$tstime[0]].' —— 周'.$week_arr[$tstime[1]]];
//
//        if (!array_key_exists($cur_day, $week_arr)) {
//
//            return $hint;
//        }
//
//        if ($cur_day != $tstime[0] && $tstime[0] == $tstime[1]) {
//
//            return ['code'=>2, 'msg'=>'您所在的团队兑换时间在 周'.$week_arr[$tstime[0]]];
//        }
//
//        if ($cur_day < $tstime[0] || $cur_day > $tstime[1]) {
//
//            return $hint;
//        }
//
//        if ($cur_hour != $tstime[2] && $tstime[2] == $tstime[3]) {
//
//            return ['code'=>2, 'msg'=>'您所在的团队兑换时间在 '.$tstime[2].'点'];
//
//        }
//
//        if ($cur_hour < $tstime[2] || $cur_hour >= $tstime[3]) {
//
//            return ['code'=>2, 'msg'=>'您所在的团队兑换时间在 '.$tstime[2].'点 —— '.$tstime[3].'点'];
//        }
//
//    }
//
//    if ($limit['xmts'] == '') { return ['code'=>1]; }
//
//    $limit = explode(',', $limit['xmts']);
//
//    $value = [];
//    $person_num = [];
//    $amount = [];
//    $amount_arr = [];
//
//    foreach ($limit as $k => $v) {
//
//        $value = explode('-', $v);
//        $person_num[] = $value[0];
//        $amount_arr[]= $value[1];
//        $amount[$value[0]] = $value[1];
//    }
//
//    // 单日申请提现总额（包括已成功提现的）
//    $today_start = strtotime(date("Y-m-d", time()).'00:00:00');
//    $today_end = $today_start + 3600 * 24;
//
//    $sql = "SELECT SUM(amount) AS amo FROM xm_wd WHERE user_id = $uid AND status < 2 AND notes = '兑换 T 积分' AND (create_at BETWEEN $today_start AND $today_end)";
//    $apply = $GLOBALS['db']->getRow($sql);
//
//    // 当日购买精品花费总额
//    $sql = "SELECT SUM(amount) AS daybuy FROM xm_flow_log WHERE user_id = $uid AND type = 2 AND notes ='购买精品' AND (create_at BETWEEN $today_start AND $today_end)";
//    $buy = $GLOBALS['db']->getRow($sql);
//
//    // 该用户的直推
//    $invite = $GLOBALS['db']->getAll("SELECT user_id FROM xm_mq_users_extra WHERE invite_user_id = $uid");
//
//    if (!$invite) {
//
//        $person = 0;
//
//    } else {
//
//        $id_arr = [];
//
//        foreach ($invite as $k => $v) {
//
//            $id_arr[] = $v['user_id'];
//        }
//
//        $id_str = implode(',', $id_arr);
//
//        // 有未回购的订单的直推人数
//        $buyback = $GLOBALS['db']->getRow("SELECT COUNT(DISTINCT(user_id)) AS low_num FROM xm_mq_buy_back WHERE user_id IN ($id_str) AND bb_status IN (1,2) GROUP BY user_id");
//
//        $person = isset($buyback['low_num']) ? $buyback['low_num'] : 0;
//    }
//
//
//    if ($person == 0 && $person<$person_num[0]) {
//
//        return ['code'=>2, 'msg'=>'您最低需要 '.$person_num[0].' 个 H 单正在回购的直推用户才可继续操作'];
//    }
//
//    $day_money_ok = 0; // 单日可使用的额度
//    $day_money_ed = $apply['amo'] + $buy['daybuy']; // 已使用额度
//
//
//    //判断该会员需要哪一档提现
//    if($person_num[0]<=$person && $person<$person_num[1]){
//        $min_amount = $amount[$person_num[0]];
//    }elseif($person_num[1]<=$person && $person<$person_num[2]){
//        $min_amount = $amount[$person_num[1]];
//    }elseif($person_num[2]<=$person && $person<$person_num[3]){
//        $min_amount = $amount[$person_num[2]];
//    }elseif($person_num[3]<=$person && $person<$person_num[4]){
//        $min_amount = $amount[$person_num[3]];
//    }elseif($person_num[4]<=$person){
//        $min_amount = $amount[$person_num[4]];
//    }
//
//    //判断体现money在哪一档
//    if($amount_arr[0]>=$money){
//        $max_amount = $amount_arr[0];
//        $person_key = $person_num[0];
//    }elseif($amount_arr[0]<$money && $money<=$amount_arr[1]){
//        $max_amount = $amount_arr[1];
//        $person_key = $person_num[1];
//    }elseif($amount_arr[1]<$money && $money<=$amount_arr[2]){
//        $max_amount = $amount_arr[2];
//        $person_key = $person_num[2];
//    }elseif($amount_arr[2]<$money && $money<=$amount_arr[3]){
//        $max_amount = $amount_arr[3];
//        $person_key = $person_num[3];
//    }elseif($amount_arr[3]<$money && $money<=$amount_arr[4]){
//        $max_amount = $amount_arr[4];
//        $person_key = $person_num[4];
//    }elseif($money>$amount_arr[4]){
//        $max_amount = $amount_arr[4];
//        $person_key = $person_num[4];
//    }
//    if ($money > $min_amount) {
//        $day_money_ok = $min_amount-$day_money_ed;
//        return ['code'=>2, 'msg'=>"对不起！您的H单回购中的直推人数未满{$person_key}人，当前只有{$person}人,当日最多可兑换额度{$day_money_ok}"];
//    }
    return ['code' => 1];
}


// 20180429 1307 V 
// 用户充值新美积分
// 新增用户的 新美总额/可用新美积分 | [添加流水记录] | 释放上级的冻结新美积分
// ---------------------------------------------------------------------
// 20180517 1036 V 充值审核之后，金额流入 T 积分
function operate_user($userid, $money)
{

    if (!$userid || !$money) {
        return false;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('tps'), $userid);
    $tps = $db->getRow($sql);

    if (!$tps) {
        return false;
    }

    $current = time();

    $ret = $db->query("UPDATE `xm_tps` SET unlimit = unlimit + $money, update_at = $current WHERE user_id = $userid");

    if (!$ret) {

        $hint = '审核充值出现错误';
        error_log_('rechar.txt', $new, $hint);

        return ['sus' => false, 'msg' => $hint];
    }

    // 添加流水记录
    $new = [

        'user_id' => $userid,
        'type' => 2,
        'status' => 1,
        'amount' => $money,
        'surplus' => $tps['unlimit'] + $money,
        'notes' => '充值',
        'create_at' => $current
    ];

    $ret = record_flow($new);

    if (!$ret) {

        error_log_('tran.txt', $new, '更新流水数据错误');
    }

    return ['sus' => true, 'msg' => ''];

}


/*
|- 20180517 1058 V 转 T 积分 到 新美积分 / 释放上级冻结的新美积分
*/
function tps_to_xps($uid, $money)
{

    $hint = ['sus' => 0, 'msm' => '参数错误'];

    if (!$uid || !$money) {

        return $hint;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('tps'), $uid);
    $tps = $db->getRow($sql);

    if (!$tps) {
        return $hint;
    }

    if ($money > $tps['unlimit']) {

        $hint['msm'] = '转换金额大于现有金额';
        return $hint;
    }

    $current = time();

    // 修改新美积分
    $ret = $db->query("UPDATE `xm_xps` SET amount = amount + $money, unlimit = unlimit + $money, update_at = $current WHERE user_id = $uid");

    if (!$ret) {

        $hint['msm'] = '修改新美积分失败';
        return $hint;
    }

    // 修改 T 积分
    $ret = $db->query("UPDATE `xm_tps` SET unlimit = unlimit - $money, update_at = $current WHERE user_id = $uid");

    if (!$ret) {

        $hint['msm'] = '修改 T 积分失败';
        return $hint;
    }
    $points = get_user_points($uid);
    // 添加流水
    $new = [

        'user_id' => $uid,
        'type' => 1,
        'status' => 1,
        'amount' => $money,
        'surplus' => $points['xps']['amount'],
        'notes' => 'T 积分转入新美积分',
        'create_at' => $current
    ];

    $ret = record_flow($new);

    $new = [

        'user_id' => $uid,
        'type' => 2,
        'status' => 2,
        'amount' => $money,
        'surplus' => $points['tps']['unlimit'],
        'notes' => 'T 积分转入新美积分',
        'create_at' => $current
    ];

    $ret = record_flow($new);


    // 解冻上级冻结的新美积分

    $sql = "SELECT m.invite_user_id as uid, x.* FROM %s AS m LEFT JOIN %s AS x 
            ON m.invite_user_id = x.user_id WHERE m.user_id = %d";
    $sql = sprintf($sql, $ecs->table('mq_users_extra'), $ecs->table('xps'), $uid);
    $top_xps = $db->getRow($sql);

    // 没有新美积分被冻结
    if ($top_xps['frozen'] == 0) {

        return ['sus' => 1, 'msm' => '转入成功'];
    }

    $frozen = $top_xps['frozen'] - $money;
    $unlimit = $top_xps['unlimit'] + $money;

    if ($money > $top_xps['frozen']) {

        $frozen = 0; // 全部释放
        $unlimit = $top_xps['unlimit'] + $top_xps['frozen'];
    }

    $new = [
        'frozen' => $frozen,
        'unlimit' => $unlimit,
        'update_at' => $current
    ];

    $ret = $db->autoExecute($ecs->table('xps'), $new, 'UPDATE', 'user_id = ' . $top_xps['uid']);

    if (!$ret) {

        error_log_('tran.txt', $new, '解冻上级新美积分出错 - user_id -' . $top_xps['uid']);
    }

    // 20180524 0906 V 添加冻结释放流水
    $new = [

        'user_id' => $top_xps,
        'type' => 1,
        'status' => 0,
        'amount' => $money,
        'surplus' => $top_xps['amount'],
        'notes' => '下级T积分转新美积分，释放冻结新美积分',
        'create_at' => $current
    ];
    record_flow($new);
    return ['sus' => 1, 'msm' => '转入成功'];
}


// 20180507 1050 V 是否开启了新美转账
function transfer_status()
{

    $db = $GLOBALS['db'];
    $sql = "SELECT `value` FROM `xm_shop_config` WHERE `code` = 'xm_transfer_cash_close' OR `code` = 'xm_transfer_cash_close_reason'";

    $res = $db->getAll($sql);

    $ret = [

        'sus' => $res[0]['value'],
        'msg' => $res[1]['value'],
    ];

    return $ret;
}

// 20180615 1130 V 是否存在个人限制
function per_limit($uid, $type)
{

    if (!$uid || !$type) {
        return false;
    }

    $type_arr = [

        'xps' => 'cash_limited, daily_cash_transfer_sum_limit', // xinmei points 是否限制单日转账 / 限制额度
        'horder' => 'hdan_limited',
        'tps' => 'cash_take_limited, daily_cash_take_sum_limit', // t points 是否限制单日提现 / 提现额度
    ];

    $filed = isset($type_arr[$type]) ? $type_arr[$type] : '';

    if ($filed == '') {
        return false;
    }

    $current = time();

    $sql = "SELECT $filed FROM %s WHERE `user_id` = %d AND ((%d > `start_time` AND %d < `end_time`) OR (%d > `start_time` AND `end_time` = 0))";
    $sql = sprintf($sql, $GLOBALS['ecs']->table('mq_users_limit'), $uid, $current, $current, $current);
    $res = $GLOBALS['db']->getRow($sql);

    if (!$res) {
        return false;
    }

    return $res;

}

// 20180420 1730 找出用户的服务商
function check_user_group($uid)
{

    if (!$uid) {
        return false;
    }

    $sql = "SELECT `new_status` FROM %s WHERE `user_id` = %d";
    $sql = sprintf($sql, $GLOBALS['ecs']->table('mq_users_extra'), $uid);
    $res = $GLOBALS['db']->getRow($sql);

    $ret = explode('-', $res['new_status']);

    return $ret[0];

}

// 20180703 1400 V 查询用户限制
function get_user_newstatus($uid)
{

    if (!$uid) {
        return false;
    }

    $sql = "SELECT `new_status` FROM %s WHERE `user_id` = %d";
    $sql = sprintf($sql, $GLOBALS['ecs']->table('mq_users_extra'), $uid);
    $res = $GLOBALS['db']->getRow($sql);

    $ret = explode('-', $res['new_status']);

    return $ret;
}

// 当前用户当日的转账限额
function tran_day_limit()
{

}


// 20180507 1332 V 获取转账手续费
function get_tran_fee($type)
{

    if (!isset($type)) {
        return false;
    }

    $type_c = [

        'xm_transfer_rate_cash_fee', // 新美积分
        'xm_transfer_rate_consume_fee', // 消费积分
    ];

    $sql = "SELECT `value` FROM `xm_shop_config` WHERE `code` = '" . $type_c[$type] . "'";
    $conf = $GLOBALS['db']->getRow($sql);

    if (!$conf) {
        return 0;
    }

    return $conf['value'] / 100;
}


// 大金额转账申请
function transfer_apply($data)
{

    $hint = [

        'sus' => false,
        'msg' => '参数错误'
    ];

    if (!$data) {
        return $hint;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $trfee = get_tran_fee($data['type']);
    $current = time();

    $new = [

        'from_user' => $data['from'],
        'to_user' => $data['to'],
        'type' => $data['type'],
        'status' => 0,
        'amount' => $data['money'],
        'trfee' => $data['money'] * $trfee,
        'notes' => '转给-' . $to,
        'create_at' => $current
    ];

    $ret = $db->autoExecute($ecs->table('transfer_apply'), $new, 'INSERT');

    if (!$ret) {

        $hint['msg'] = '更新数据错误';

        return $hint;
    }

    $hint = [

        'sus' => true,
        'msg' => '申请提交成功'
    ];

    return $hint;
}

// 大金额转账申请
function transfer_apply_shopp($data)
{

    $hint = [

        'sus' => false,
        'msg' => '参数错误'
    ];

    if (!$data) {
        return $hint;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $current = time();
    //查配置
    $sql = "SELECT value FROM %s WHERE code = 'xm_transfer_rate_consume_fee'";
    $sql = sprintf($sql, $ecs->table('shop_config'));
    $conf = $db->getRow($sql);
    //判断是是否存在上下级关系
    $from_invite_user_id = $GLOBALS['db']->getOne("SELECT invite_user_id FROM xm_mq_users_extra WHERE user_id = {$data['from']}");
    $to_invite_user_id = $GLOBALS['db']->getOne("SELECT invite_user_id FROM xm_mq_users_extra WHERE user_id = {$data['to']}");
    if ($from_invite_user_id == $data['to'] || $to_invite_user_id == $data['from']) {
        $fee = 0;
    } else {
        $fee = $conf['value'] * $data['money'] / 100;
    }
    $new = [
        'from_user' => $data['from'],
        'to_user' => $data['to'],
        'type' => $data['type'],
        'status' => 0,
        'amount' => $data['money'],
        'trfee' => $fee,
        'notes' => '转给-' . $data['to_user_name'],
        'create_at' => $current
    ];

    $ret = $db->autoExecute($ecs->table('transfer_apply'), $new, 'INSERT');

    if (!$ret) {

        $hint['msg'] = '更新数据错误';

        return $hint;
    }

    $hint = [

        'sus' => true,
        'msg' => '申请提交成功'
    ];

    return $hint;
}


/*
|- 20180430 1446 V [转账]操作
|- 修改金额，添加流水
*/
function trans_ok($from, $to, $money, $type, $target_id = 0, $fee = 0)
{

    if (!$from || !$to || !$money || !$type) {
        return false;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $new = [];
    $current = time();

    $from_name = get_user_info_('user_id', $from, 'user_name');
    $to_name = get_user_info_('user_id', $to, 'user_name');

    switch ($type) {

        case 'xinmei':

            // 新美积分
            // 查询配置信息

            $the_money = $money + $fee;

            $ret = $db->query("UPDATE xm_xps SET amount = amount - $the_money, unlimit = unlimit - $the_money, update_at = $current WHERE user_id = $from");

            // to
            // 实际金额


            $ret = $db->query("UPDATE xm_xps SET amount = amount + $money, unlimit = unlimit + $money, update_at = $current WHERE user_id = $to");


            $from_money = get_user_points($from);
            $to_money = get_user_points($to);

            // 流水记录
            // from
            $new = [

                'user_id' => $from,
                'type' => 1,
                'status' => 2, // 支出
                'amount' => $money,
                'surplus' => $from_money['xps']['amount'] + $fee,
                'notes' => '转账给：' . $to_name['user_name'],
                'create_at' => $current,
                'target_id' => $target_id,
                'target_type' => 8

            ];
            $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');

            // to
            $new = [
                'user_id' => $to,
                'type' => 1,
                'status' => 1, // 收入
                'amount' => $money,
                'surplus' => $to_money['xps']['amount'],
                'notes' => $from_name['user_name'] . ' 转账给我',
                'create_at' => $current,
                'target_id' => $target_id,
                'target_type' => 8
            ];
            $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');

            if ($fee > 0) {

                // fee
                $new = [

                    'user_id' => $from,
                    'type' => 1,
                    'status' => 2, // 支出
                    'amount' => $fee,
                    'surplus' => $from_money['xps']['amount'],
                    'notes' => '转账手续费',
                    'create_at' => $current,
                    'target_id' => $target_id,
                    'target_type' => 8
                ];
                $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');
            }

            break;

        case 'consume':
            //查配置

            $the_money = $money + $fee;
            $ret = $db->query("UPDATE xm_tps SET shopp = shopp - $the_money, update_at = $current WHERE user_id = $from");

            // to
            // 实际金额


            $ret = $db->query("UPDATE xm_tps SET shopp = shopp + $money, update_at = $current WHERE user_id = $to");


            $from_money = get_user_points($from);
            $to_money = get_user_points($to);

            // 流水记录
            // from
            $new = [

                'user_id' => $from,
                'type' => 3,
                'status' => 2, // 支出
                'amount' => $money,
                'surplus' => $from_money['tps']['shopp'] + $fee,
                'notes' => '转账给：' . $to_name['user_name'],
                'create_at' => $current,
                'target_id' => $target_id,
                'target_type' => 8
            ];
            $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');

            // to
            $new = [

                'user_id' => $to,
                'type' => 3,
                'status' => 1, // 收入
                'amount' => $money,
                'surplus' => $to_money['tps']['shopp'],
                'notes' => $from_name['user_name'] . ' 转账给我',
                'create_at' => $current,
                'target_id' => $target_id,
                'target_type' => 8
            ];
            $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');

            if ($fee > 0) {

                // fee
                $new = [
                    'user_id' => $from,
                    'type' => 3,
                    'status' => 2, // 支出
                    'amount' => $fee,
                    'surplus' => $from_money['tps']['shopp'],
                    'notes' => '转账手续费',
                    'create_at' => $current,
                    'target_id' => $target_id,
                    'target_type' => 8
                ];
                $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');
            }

            break;

        case 'tjifen':

            // 新美积分
            // 查询配置信息


            $the_money = $money + $fee;
            $ret = $db->query("UPDATE xm_tps SET  unlimit = unlimit - $the_money, update_at = $current WHERE user_id = $from");

            // to
            // 实际金额

            $ret = $db->query("UPDATE xm_tps SET  unlimit = unlimit + $money, update_at = $current WHERE user_id = $to");


            $from_money = get_user_points($from);
            $to_money = get_user_points($to);

            // 流水记录
            // from
            $new = [

                'user_id' => $from,
                'type' => 2,
                'status' => 2, // 支出
                'amount' => $money,
                'surplus' => $from_money['tps']['unlimit'] + $fee,
                'notes' => '转账给：' . $to_name['user_name'],
                'create_at' => $current,
                'target_id' => $target_id,
                'target_type' => 8
            ];
            $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');

            // to
            $new = [

                'user_id' => $to,
                'type' => 2,
                'status' => 1, // 收入
                'amount' => $money,
                'surplus' => $to_money['tps']['unlimit'],
                'notes' => $from_name['user_name'] . ' 转账给我',
                'create_at' => $current,
                'target_id' => $target_id,
                'target_type' => 8
            ];
            $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');

            if ($fee > 0) {
                // fee
                $new = [

                    'user_id' => $from,
                    'type' => 2,
                    'status' => 2, // 支出
                    'amount' => $fee,
                    'surplus' => $from_money['tps']['unlimit'],
                    'notes' => '转账手续费',
                    'create_at' => $current,
                    'target_id' => $target_id,
                    'target_type' => 8
                ];
                $ret = $db->autoExecute($ecs->table('flow_log'), $new, 'INSERT');
            }

            break;

        default:
            # code...
            break;
    }
}


/*
|- 20180430 1457 获取用户积分
*/
function get_upoints($uid, $type)
{

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $sql = "SELECT * FROM %s WHERE user_id = %d";

    switch ($type) {

        case 'xinmei':


            $sql = sprintf($sql, $ecs->table('xps'), $uid);
            $res = $db->getRow($sql);
            break;

        case 'cousume':

            // 消费积分 ...

            return;
            $sql = sprintf($sql, $ecs->table('xps'), $uid);
            $res = $db->getRow($sql);
            break;

        default:
            return [];
            break;
    }

    return $res;

}

// 添加流水记录
function record_flow($data)
{

    if (!$data) {
        return false;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $ret = $db->autoExecute($ecs->table('flow_log'), $data, 'INSERT');

    if (!$ret) {

        return false;
    }

    return true;

}


/*
|- 20180503 V 获取用户 ID
|- 作为条件的字段名，字段值，要获取的字段
*/
function get_user_info_($con, $cons, $filed)
{

    if (!$con || !$cons || !$filed) {
        return false;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $sql = "SELECT %s FROM %s WHERE %s = '%s'";
    $sql = sprintf($sql, $filed, $ecs->table('users'), $con, $cons);
    $res = $db->getRow($sql);

    if (!$res) {

        return [];
    }

    return $res;
}

/*
|- 20180503 V 获取用户 ID
|- 作为条件的字段名，字段值，要获取的字段
*/
function get_user_extra_info_($con, $cons, $filed)
{

    if (!$con || !$cons || !$filed) {
        return false;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $sql = "SELECT %s FROM %s WHERE %s = '%s'";

    $sql = sprintf($sql, $filed, $ecs->table('mq_users_extra'), $con, $cons);
    $res = $db->getRow($sql);

    if (!$res) {

        return [];
    }

    return $res;
}

/*
|- 20180430 0942 V 获取用户的积分
|- 新美积分总额 - T 积分 - 购物积分 - 奖金池
*/
function get_user_points($uid)
{

    $data = [];

    if (!$uid) {
        return $data;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    // 查询用户的新美积分详情
    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('xps'), $uid);
    $data['xps'] = $db->getRow($sql);

    // 查询用户的 T 积分组详情
    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('tps'), $uid);
    $data['tps'] = $db->getRow($sql);

    return $data;
}



/*
|- 20180502 1402 V 冻结新美积分 (获得/花费新美积分)
|- userid - 数额 - 得失类型 - 备注
*/
function spend_release_balance($uid, $money, $type, $notes)
{

       if (!$uid || !$money || !$type) {
        return false;
    }

    $db = $GLOBALS['db'];

    $data = $GLOBALS['db']->getRow("SELECT * FROM xm_user_account WHERE user_id={$uid}");

    if ($data == []) {
        return false;
    }


    // inc / dec
    if ($type == 'inc-3') {
        $sql = " UPDATE xm_user_account SET release_balance=release_balance+{$money},customs_money=customs_money+{$money} WHERE user_id={$uid}";
       $db->query($sql);
       $total_money = $data['release_balance'] + $money;
    } elseif ($type == 'dec-3') {
        $sql = " UPDATE xm_user_account SET release_balance=release_balance-{$money},customs_money=customs_money-{$money} WHERE user_id={$uid}";
         $db->query($sql);
         $total_money = $data['release_balance'] - $money;
    }

    // 写入流水记录
    insert_flow($uid, $money, $type, $notes, $total_money);

}



/*
|- 20180503 1325 V t积分
*/
function spend_balance_unlimit($uid, $money, $type, $notes)
{

    if (!$uid || !$money || !$type) {
        return false;
    }

    $db = $GLOBALS['db'];

    $data = $GLOBALS['db']->getRow("SELECT * FROM xm_user_account WHERE user_id={$uid}");

    if ($data == []) {
        return false;
    }

    // inc / dec
    if ($type == 'inc-2') {
        $sql = " UPDATE xm_user_account SET balance=balance+{$money} WHERE user_id={$uid}";
       $db->query($sql);
       $total_money = $data['balance'] + $money;
    } elseif ($type == 'dec-2') {
        $sql = " UPDATE xm_user_account SET balance=balance-{$money} WHERE user_id={$uid}";
         $db->query($sql);
         $total_money = $data['balance'] - $money;
    }

    // 写入流水记录
    insert_flow($uid, $money, $type, $notes, $total_money);

}




/*
|- 20180502 1422 V 写入流水记录
*/
function insert_flow($uid, $money, $type, $notes, $surplus = 0)
{

    if (!$uid || !$money || !$type) {
        return false;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $config = [

        // 优惠券收入
        'inc-2' => [
            'type' => 2,
            'status' => 1,
        ],

        // 优惠券支出
        'dec-2' => [
            'type' => 2,
            'status' => 2,
        ],

        // 待释放收入
        'inc-3' => [

            'type' => 3,
            'status' => 1,
            'target_type' => 5,
        ],
        // 待释放支出
        'dec-3' => [

            'type' => 3,
            'status' => 2,
            'target_type' => 5,
        ],
    ];

    $part = $config[$type] ?: '';

    if ($part == '') {
        return false;
    }

    $part['user_id'] = $uid;
    $part['amount'] = $money;

    if ($notes) {
        $part['notes'] = $notes;
    }

    $part['create_at'] = time();
    $part['surplus'] = $surplus;
    $res = $db->autoExecute($ecs->table('flow_log'), $part, 'INSERT');

}

// 添加错误日志
function error_log_($file_name, $content, $msg)
{

    if (!$file_name || !$content) {
        return;
    }

    @file_put_contents('../error_log/' . date('Ymd') . $file_name, date('His') . ' -- ' . json_encode($content) . "\n\r" . 'msg -- ' . $msg, FILE_APPEND);

}

//账号验证
function judge_account_user($user_id, $user_name2, $pay_password)
{
    if (!$user_name2 || !$pay_password) {
        return ['status' => 0, 'msg' => '缺少参数请重试尝试'];
    }
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    // 查询报单人信息
    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('mq_users_extra'), $user_id);
    $user = $db->getRow($sql);
    if (!$user) {
        return ['status' => 0, 'msg' => '该账号不存在'];
    }
    if ($user['pay_password'] != md5($pay_password)) {
        return ['status' => 0, 'msg' => '交易密码不正确'];
    }
    // 查询被报单人信息
    $user2 = get_user_info_('user_name', $user_name2, 'user_id');
    if (!$user2) {
        return ['status' => 0, 'msg' => '消费预充值对象账号不存在'];
    }
    return ['status' => 1, 'msg' => '验证通过'];
}

/**
 * 消费预充值
 * @param $user_id 操作用户id
 * @param $exchange_for_user 对方账号
 * @param $cash_money 新美
 * @param $consume_money 消费
 * @return array
 */
function new_pre_recharge($user_id, $exchange_for_user, $cash_credit, $consume_credit, $master_config)
{
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    if (!$user_id || !$exchange_for_user || !$cash_credit) {
        return ['status' => 0, 'msg' => '参数错误'];
    }
    $sum = $cash_credit + $consume_credit;
    //首次获得积分
    $tmp = explode(':', $master_config['surplus_propo']);
    $first_shopp = ($tmp['0']) * $sum;

    //剩余积分
    $surplus_shopp = (array_sum($tmp) - $tmp['0']) * $sum;
    //获得购物券
    $tmp1 = explode(':', $master_config['coupon_propo']);
    $coupon_propo = ($tmp1['1'] * $sum) / $tmp1['0'];
    // 更新新美积分
    spend_xps($user_id, $cash_credit, 'dec-1', '消费激活-' . $exchange_for_user);
    // 更新消费积分
    spend_tps($user_id, $consume_credit, 'dec-3', '消费激活-' . $exchange_for_user);
    $user_info = get_user_info_('user_id', $user_id, 'user_name');
    // 被报单人
    $for_user = get_user_info_('user_name', $exchange_for_user, 'user_id');

    //更新被报单人的剩余总积分，消费积分，购物券
    $sql = " UPDATE xm_tps SET shopp=shopp+{$first_shopp},surplus=surplus+{$surplus_shopp},coupon=coupon+{$coupon_propo} WHERE user_id={$for_user['user_id']}";
    $ret = $db->query($sql);
    $for_user_money = get_user_points($for_user['user_id']);
    //写入流水
    if ($ret) {
        insert_flow($for_user['user_id'], $first_shopp, 'inc-3', '被消费激活-' . $user_info['user_name'], $for_user_money['tps']['shopp']);
        insert_flow($for_user['user_id'], $coupon_propo, 'inc-5', '被消费激活赠送购物券-' . $user_info['user_name'], $for_user_money['tps']['coupon']);
        insert_flow($for_user['user_id'], $surplus_shopp, 'inc-6', '被消费激活剩余积分-' . $user_info['user_name'], $for_user_money['tps']['surplus']);
        //获取被报单人的信息
        $for_user1 = get_user_extra_info_('user_id', $for_user['user_id'], 'user_cx_rank,invite_user_id');
        if ($for_user1['user_cx_rank'] == 0 || $for_user1['user_cx_rank'] == 5) {
            $db->autoExecute($ecs->table('mq_users_extra'), ['user_cx_rank' => 1], 'UPDATE', 'user_id = ' . $for_user['user_id']);
        }
        //对服务商进行分钱
        if ($for_user1) {
            //被报单人的邀请人信息
            //自己对自己报单
            if ($for_user['user_id'] == $user_id && $for_user1['user_cx_rank'] > 1 && $for_user1['user_cx_rank'] < 5) {
                $top1 = [
                    'user_cx_rank' => $for_user1['user_cx_rank'],
                    'invite_user_id' => $for_user1['invite_user_id'],
                    'user_id' => $user_id
                ];
            } else {
                $top1 = get_user_extra_info_('user_id', $for_user1['invite_user_id'], 'user_cx_rank,invite_user_id,user_id');
            }
        }
        //服务中心
        if ($top1) {
            $result = bonusWeishang($top1, $cash_credit, $user_info['user_name']);
        }
    }
    //插入报单信息表
    $time = time();
    $customs_apply = array(
        'from_user_id' => $user_id,
        'to_user_id' => $for_user['user_id'],
        'xpoints' => $cash_credit,
        'cpoints' => $consume_credit,
        'surplus' => $surplus_shopp,
        'points' => ($first_shopp + $surplus_shopp),
        'surpro' => (array_sum($tmp) - $tmp['0']),
        'create_at' => $time,
        'update_at' => $time,
    );
    $res = $db->autoExecute($ecs->table('customs_apply'), $customs_apply, 'INSERT');
    if (!$res) {
        return ['status' => 0, 'msg' => '消费激活失败，请稍后再试'];
    } else {
        return ['status' => 1, 'msg' => '消费激活成功'];
    }

}

/**
 * @param $user_extra2 被报单人用户信息
 * @param $cash_money 报单使用新美积分部分
 */
function bonusWeishang($user_extra2, $cash_money, $user_name)
{
    $db = $GLOBALS['db'];
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
    while (true) {
        //送完则退出处理
        if ($percent_rest == 0) {
            while (true) {
                //奖励分完，在找一个30w分1%的奖励
                if ($user_extra2['invite_user_id']) {
                    $user_extra2 = get_user_extra_info_('user_id', $user_extra2['invite_user_id'], 'user_cx_rank,invite_user_id,user_id');
                } else {
                    $user_extra2 = get_user_extra_info_('user_id', $user_extra2['user_id'], 'user_cx_rank,invite_user_id,user_id');
                }
                if (!$user_extra2) {
                    break;
                }
                //更新到奖金池
                if ($user_extra2['user_cx_rank'] == 4) {
                    $amount1 = 0.01 * $cash_money;
                    $sql = " UPDATE xm_tps SET gold_pool=gold_pool+{$amount1} WHERE user_id={$user_extra2['user_id']}";
                    $ret = $db->query($sql);
                    $users_gold = get_user_points($user_extra2['user_id']);
                    if ($ret) {
                        insert_flow($user_extra2['user_id'], $amount1, 'inc-4', $user_name . '消费激活服务商获得平级奖励', $users_gold['tps']['gold_pool']);
                    }
                    break;
                }
                //已达到顶级永和则退出
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
                $sql = " UPDATE xm_tps SET gold_pool=gold_pool+{$amount} WHERE user_id={$user_extra2['user_id']}";
                $ret = $db->query($sql);
                $userss_gold = get_user_points($user_extra2['user_id']);
                if ($ret) {
                    insert_flow($user_extra2['user_id'], $amount, 'inc-4', $user_name . '消费激活服务商获得奖励', $userss_gold['tps']['gold_pool']);
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
        $user_extra2 = get_user_extra_info_('user_id', $user_extra2['invite_user_id'], 'user_cx_rank,invite_user_id,user_id');

        if (!$user_extra2) {
            break;
        }
    }
    return true;
}


/*
 * 个推推送
 */

function getuiPush()
{
//消息推送Demo
    require_once(ROOT_PATH . 'vendor/getui/IGt.Push.php');
//采用"PHP SDK 快速入门"， "第二步 获取访问凭证 "中获得的应用配置
    define('APPKEY', 'hqLXNBYJpQ893j0lTTNR2A');
    define('APPID', 'n3P90Rlyen9dCrYPlCpLd4');
    define('MASTERSECRET', '1bgfoS83br5fIWPgnlIEn');
    define('HOST', 'http://sdk.open.api.igexin.com/apiex.htm');
    define('CID', '484c0958bbd2724d1f8b68028e97c3e7');
//define('CID2','7a0574dbe2d680e8e8570f862c17df14');
    pushMessageToSingle();
}

function pushMessageToSingle()
{
    $igt = new IGeTui(HOST, APPKEY, MASTERSECRET);

    //消息模版：
    // 4.NotyPopLoadTemplate：通知弹框下载功能模板
    $template = IGtNotyPopLoadTemplateDemo();

    //定义"SingleMessage"
    $message = new IGtSingleMessage();
    $message->set_isOffline(true);//是否离线
    $message->set_offlineExpireTime(3600 * 12 * 1000);//离线时间
    $message->set_data($template);//设置推送消息类型

    //接收方
    $target = new IGtTarget();
    $target->set_appId(APPID);
    $target->set_clientId(CID);


    try {
        $rep = $igt->pushMessageToSingle($message, $target);
        var_dump($rep);
        echo("<br><br>");

    } catch (RequestException $e) {
        $requstId = e . getRequestId();
        //失败时重发
        $rep = $igt->pushMessageToSingle($message, $target, $requstId);
        var_dump($rep);
        echo("<br><br>");
    }
}

function IGtNotyPopLoadTemplateDemo()
{
    $template = new IGtNotyPopLoadTemplate();
    $template->set_appId(APPID);                      //应用appid
    $template->set_appkey(APPKEY);                    //应用appkey
    //通知栏
    $template->set_notyTitle("请填写通知标题");                 //通知栏标题
    $template->set_notyContent("请填写通知内容"); //通知栏内容
    $template->set_notyIcon("");                      //通知栏logo
    $template->set_isBelled(true);                    //是否响铃
    $template->set_isVibrationed(true);               //是否震动
    $template->set_isCleared(true);                   //通知栏是否可清除
    //弹框
    $template->set_popTitle("弹框标题");              //弹框标题
    $template->set_popContent("弹框内容");            //弹框内容
    $template->set_popImage("");                      //弹框图片
    $template->set_popButton1("下载");                //左键
    $template->set_popButton2("取消");                //右键
    //下载
    $template->set_loadIcon("");                      //弹框图片
    $template->set_loadTitle("请填写下载标题");
    $template->set_loadUrl("请填写下载地址");
    $template->set_isAutoInstall(false);
    $template->set_isActived(true);

    //设置通知定时展示时间，结束时间与开始时间相差需大于6分钟，消息推送后，客户端将在指定时间差内展示消息（误差6分钟）
    $begin = "2015-02-28 15:26:22";
    $end = "2015-02-28 15:31:24";
    $template->set_duration($begin, $end);
    return $template;
}



