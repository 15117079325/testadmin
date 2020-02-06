<?php

/**
 * ECSHOP 管理中心商店设置
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: shop_config.php 17217 2011-01-19 06:29:08Z liubo $
 */


define('IN_ECS', true);
define('XINMEI_SETTING', '新美配置');
/* 代码 */
require(dirname(__FILE__) . '/includes/init.php');

if ($GLOBALS['_CFG']['certificate_id'] == '') {
    $certi_id = 'error';
} else {
    $certi_id = $GLOBALS['_CFG']['certificate_id'];
}

$sess_id = $GLOBALS['sess']->get_session_id();

$auth = local_mktime(); //代码修改  By www.68ecshop.com
$ac = md5($certi_id . 'SHOPEX_SMS' . $auth);
$url = 'http://service.shopex.cn/sms/index.php?certificate_id=' . $certi_id . '&sess_id=' . $sess_id . '&auth=' . $auth . '&ac=' . $ac;

/*------------------------------------------------------ */
//-- 列表编辑 ?act=list_edit
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list_edit') {
    /* 检查权限 */
    admin_priv('shop_config');

    /* 可选语言 */
    $dir = opendir('../languages');
    $lang_list = array();
    while (@$file = readdir($dir)) {
        if ($file != '.' && $file != '..' && $file != '.svn' && $file != '_svn' && is_dir('../languages/' . $file)) {
            $lang_list[] = $file;
        }
    }
    @closedir($dir);

    $smarty->assign('lang_list', $lang_list);
    $smarty->assign('ur_here', $_LANG['01_shop_config']);
    $smarty->assign('group_list', get_settings(null, array('5', '50', '51', '52', '53', '54'), array('chat')));
    $smarty->assign('countries', get_regions());

    if (strpos(strtolower($_SERVER['SERVER_SOFTWARE']), 'iis') !== false) {
        $rewrite_confirm = $_LANG['rewrite_confirm_iis'];
    } else {
        $rewrite_confirm = $_LANG['rewrite_confirm_apache'];
    }
    $smarty->assign('rewrite_confirm', $rewrite_confirm);

    if ($_CFG['shop_country'] > 0) {
        $smarty->assign('provinces', get_regions(1, $_CFG['shop_country']));
        if ($_CFG['shop_province']) {
            $smarty->assign('cities', get_regions(2, $_CFG['shop_province']));
        }
    }

    $smarty->assign('cfg', $_CFG);

    assign_query_info();
    $smarty->display('shop_config.htm');
} /*
|-交易大厅配置
*/
elseif ($_REQUEST['act'] == 'trade_settings') {
    /* 检查权限 */

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $sql = "SELECT value,notes FROM `xm_master_config` WHERE `code` = 'xm_trade_switch'";
    $trade = $db->getRow($sql);

    $smarty->assign('trade', $trade);

    $smarty->display('configs/trade_switch.htm');
} elseif ($_REQUEST['act'] == 'set_trade_settings') {

    /* 检查权限 */


    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $is_open = $_POST['is_open'];
    $reason = $_POST['reason'];
    if ($is_open) {
        if (empty($reason)) {
            exit(json_encode(['sus' => 2, 'msg' => '关闭时候，请填写原因']));
        }
    }
    $sql = "UPDATE xm_master_config SET value={$is_open},notes='{$reason}' WHERE code='xm_trade_switch'";
    $db->query($sql);
    echo json_encode(['sus' => 1, 'msg' => '设置成功']);

} elseif ($_REQUEST['act'] == 'config_settings') {
    /* 检查权限 */
    admin_priv('shop_config');
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $ret = $db->getAll("SELECT * FROM `xm_master_config`");
    //查询报单范围限制
    $ret1 = $db->getAll("SELECT * FROM `xm_release_config`");

    $retSign = $db->getAll("SELECT * FROM `xm_master_config` WHERE `tip`='s'");

    $retLuck = $db->getAll("SELECT * FROM `xm_master_config` WHERE `tip`='l'");

    $retSignCode = array_column($retSign, null, 'code');
    $signNumCode = explode(",", $retSignCode['sign_num']['value']);

    $sortSign = [];
    foreach ($signNumCode as $k => $v) {
        if (isset($retSignCode[$v])) {
            $sortSign[] = $retSignCode[$v];
            unset($retSignCode[$v]);
        }
    }

    foreach ($ret as $k => $v) {
        $list[$v['code']] = $v['value'];
    }

    foreach ($ret1 as $k => $v) {
        $list1[$v['code'] . '_num'] = $v['num'];
        $list1[$v['code'] . '_money'] = $v['money'];
    }
    $smarty->assign('list', $list);
    $smarty->assign('luckList', $retLuck);
    $smarty->assign('signList', array_values($retSignCode));
    $smarty->assign('signListSort', array_values($sortSign));
    $smarty->assign('list1', $list1);
    $smarty->display('configs/config_index.htm');
} elseif ($_REQUEST['act'] == 'customs') {
    //报单配置
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $customs_min = $_POST['customs_min'];
    $customs_max = $_POST['customs_max'];
    $cash_balance = $_POST['cash_balance'];
    $give_ratio = $_POST['give_ratio'];
    $day_release_ratio = $_POST['day_release_ratio'];
    $release_max = $_POST['release_max'];
    $direct_ratio = $_POST['direct_ratio'];
    $superior_ratio = $_POST['superior_ratio'];
    $direct_assess_num = $_POST['direct_assess_num'];
    //$customs_goods_max_num = $_POST['customs_goods_max_num'];
    $customs_goods_max_money = $_POST['customs_goods_max_money'];
    $direct_assess_day = $_POST['direct_assess_day'];
    if (!$customs_min || !$customs_max || !$cash_balance) {

        exit(json_encode(['sus' => 2, 'msg' => '参数错误']));
    }

    if ($customs_min >= $customs_max) {

        exit(json_encode(['sus' => 2, 'msg' => '下限金额需小于上限金额']));
    }

    if ($customs_min % 100 != 0 || $customs_max % 100 != 0) {

        exit(json_encode(['sus' => 2, 'msg' => '上下限金额需是 100 的整数倍']));
    }

    $cash = explode(':', $give_ratio);

    if (count($cash) == 1) {

        exit(json_encode(['sus' => 2, 'msg' => '请在英文状态下输入冒号比例']));
    }

    $sql = "SELECT * FROM `xm_master_config` WHERE `tip` = 'c'";
    $ret = $db->getAll($sql);

    $code = [
        'customs_min' => $customs_min,
        'customs_max' => $customs_max,
        'cash_balance' => $cash_balance,
        'give_ratio' => $give_ratio,
        'day_release_ratio' => $day_release_ratio,
        'release_max' => $release_max,
        'direct_ratio' => $direct_ratio,
        'superior_ratio' => $superior_ratio,
        'direct_assess_num' => $direct_assess_num,
        //'customs_goods_max_num' => $customs_goods_max_num,
        'customs_goods_max_money' => $customs_goods_max_money,
        'direct_assess_day' => $direct_assess_day
    ];

    $list = [];

    foreach ($ret as $k => $v) {

        if (isset($code[$v['code']]) && $code[$v['code']] != $v['value']) {

            $res = new_setting($v['code'], $code[$v['code']]);

            if ($res == false) {

                exit(json_encode(['sus' => 2, 'msg' => '设置失败']));

            }
        }
    }

    echo json_encode(['sus' => 1, 'msg' => '设置成功']);

} elseif ($_REQUEST['act'] == 'customs_sign') {

    $sql = "SELECT * FROM `xm_master_config` WHERE `tip` = 's'";
    $rets = $db->getAll($sql);
    $signCode = array_column($rets, 'code');
    $updatePost = $_POST;
    $updateDataSql = [];
    foreach ($updatePost as $k => $item) {
        $db->query("UPDATE `xm_master_config` SET `value`='{$item}' WHERE `code`='{$k}'");
    }
    echo json_encode(['sus' => 1, 'msg' => '设置成功']);
    exit();

} elseif ($_REQUEST['act'] == 'customs_luck') {
    $updatePost = $_POST;
    $updateDataSql = [];
    foreach ($updatePost as $k => $item) {
        $db->query("UPDATE `xm_master_config` SET `value`='{$item}' WHERE `code`='{$k}'");
    }
    echo json_encode(['sus' => 1, 'msg' => '设置成功']);
    exit();
} elseif ($_REQUEST['act'] == 'customs_sign_insert') {
    $postData = $_POST;
    if (empty($postData['sign_name']) || empty($postData['sign_code']) || empty($postData['sign_money_up']) || empty($postData['sign_money_down']) || empty($postData['sign_section_min']) || empty($postData['sign_section_max'])) {
        echo json_encode(['sus' => 0, 'msg' => '请填写正确的配置信息']);
        exit();
    }
    $sql = "SELECT * FROM `xm_master_config` WHERE `code` = '{$postData['sign_code']}' OR `notes` = '{$postData['sign_name']}'";
    $rets = $db->getAll($sql);
    if (!empty($rets)) {
        echo json_encode(['sus' => 0, 'msg' => '请误重复添加信息']);
        exit();
    }
    $signNumSql = "SELECT * FROM `xm_master_config` WHERE `code` = 'sign_num'";
    $signNum = $db->getRow($signNumSql);
    $zongSign = explode(",", $signNum['value']);
    array_push($zongSign, $postData['sign_code']);
    $updateSign = implode(",", $zongSign);

    $insertData = [];
    $insertData['tip'] = 's';
    $insertData['code'] = $postData['sign_code'];
    $insertData['value'] = $postData['sign_money_up'] . '/' . $postData['sign_money_down'] . '/' . $postData['sign_section_min'] . '-' . $postData['sign_section_max'];
    $insertData['notes'] = $postData['sign_name'];
    //要新增的sql
    $insertSql = "INSERT INTO `xm_master_config` SET `tip`='{$insertData['tip']}',`code`='{$insertData['code']}',`value`='{$insertData['value']}',`notes`='{$insertData['notes']}'";
    $db->query($insertSql);

    //要修改的sql
    $updateSignsql = "UPDATE `xm_master_config` SET value='{$updateSign}' WHERE `code` = 'sign_num'";
    $db->query($updateSignsql);
    echo json_encode(['sus' => 1, 'msg' => '设置成功']);
    exit();

} elseif ($_REQUEST['act'] == 'team') {
    //团队奖配置
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $primary_performance_limit = $_POST['primary_performance_limit'];
    $primary_direct_num = $_POST['primary_direct_num'];
    $primary_team_ratio = $_POST['primary_team_ratio'];
    $equative_team_ratio = $_POST['equative_team_ratio'];
    $middle_team_ratio = $_POST['middle_team_ratio'];
    $high_team_ratio = $_POST['high_team_ratio'];

    if (!$primary_performance_limit || !$primary_direct_num || !$primary_team_ratio) {
        exit(json_encode(['sus' => 2, 'msg' => '参数错误']));
    }

    $sql = "SELECT * FROM `xm_master_config` WHERE `tip` = 't'";
    $ret = $db->getAll($sql);

    $code = [
        'primary_performance_limit' => $primary_performance_limit,
        'primary_direct_num' => $primary_direct_num,
        'primary_team_ratio' => $primary_team_ratio,
        'equative_team_ratio' => $equative_team_ratio,
        'middle_team_ratio' => $middle_team_ratio,
        'high_team_ratio' => $high_team_ratio,
    ];

    $list = [];

    foreach ($ret as $k => $v) {

        if (isset($code[$v['code']]) && $code[$v['code']] != $v['value']) {

            $res = new_setting($v['code'], $code[$v['code']]);

            if ($res == false) {

                exit(json_encode(['sus' => 2, 'msg' => '设置失败']));

            }
        }
    }

    echo json_encode(['sus' => 1, 'msg' => '设置成功']);

} elseif ($_REQUEST['act'] == 'deal') {
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $holidays = $_POST['holidays'];
    $deal_open_close_time = $_POST['deal_open_close_time'];
    $daily_deal_buy_max_money = $_POST['daily_deal_buy_max_money'];
    $daily_deal_sell_max_money = $_POST['daily_deal_sell_max_money'];
    $platform_service_charge = $_POST['platform_service_charge'];
    $total_service_charge = $_POST['total_service_charge'];
    $seller_service_charge = $_POST['seller_service_charge'];
    if (!$deal_open_close_time) {
        exit(json_encode(['sus' => 2, 'msg' => '参数错误']));
    }

    if ($total_service_charge < $platform_service_charge) {
        exit(json_encode(['sus' => 2, 'msg' => '请设置正确手续费比列']));
    }

    if (($seller_service_charge + $platform_service_charge) != $total_service_charge) {
        exit(json_encode(['sus' => 2, 'msg' => '请设置正确手续费比列']));
    }

    $sql = "SELECT * FROM `xm_master_config` WHERE `tip` = 'd'";
    $ret = $db->getAll($sql);

    $code = [
        'holidays' => $holidays,
        'deal_open_close_time' => $deal_open_close_time,
        'daily_deal_buy_max_money' => $daily_deal_buy_max_money,
        'daily_deal_sell_max_money' => $daily_deal_sell_max_money,
        'platform_service_charge' => $platform_service_charge,
        'total_service_charge' => $total_service_charge,
        'seller_service_charge' => $seller_service_charge
    ];

    $list = [];

    foreach ($ret as $k => $v) {

        if (isset($code[$v['code']]) && $code[$v['code']] != $v['value']) {

            $res = new_setting($v['code'], $code[$v['code']]);

            if ($res == false) {
                exit(json_encode(['sus' => 2, 'msg' => '设置失败']));
            }
        }
    }

    echo json_encode(['sus' => 1, 'msg' => '设置成功']);
} else if ($_REQUEST['act'] == 'customs_scope') {
    //设置报单范围
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $num1 = $_POST['num1'];
    $money1 = $_POST['money1'];

    $num2 = $_POST['num2'];
    $money2 = $_POST['money2'];

    $num3 = $_POST['num3'];
    $money3 = $_POST['money3'];

    $num4 = $_POST['num4'];
    $money4 = $_POST['money4'];

    for ($i = 1; $i <= 4; $i++) {
        $num = 'num';
        $money = 'money';

        $sql = "UPDATE `xm_release_config` SET `num`='{${$num . $i}}',`money`='{${$money . $i}}' WHERE `code`= 'release_max{$i}' ";
        $db->query($sql);
    }

    echo json_encode(['sus' => 1, 'msg' => '设置成功']);
} //else if($_REQUEST['transfer']) {
//    $db = $GLOBALS['db'];
//    $ecs = $GLOBALS['ecs'];
//
//
//    $xm_transfer_rate_cash_fee = $_POST['transfer'];
//
//
//    $sql = "SELECT * FROM `xm_master_config` WHERE `tip` = 'z'";
//    $ret = $db->getAll($sql);
//
//    $code = [
//        'xm_transfer_rate_cash_fee' => $xm_transfer_rate_cash_fee
//    ];
//
//    $list = [];
//
//    foreach ($ret as $k => $v) {
//
//        if (isset($code[$v['code']]) && $code[$v['code']] != $v['value']) {
//
//            $res = new_setting($v['code'], $code[$v['code']]);
//
//            if ($res == false) {
//                exit(json_encode(['sus' => 2, 'msg' => '设置失败']));
//            }
//        }
//    }
//
//    echo json_encode(['sus' => 1, 'msg' => '设置成功']);
//
//}
/*
|- 20180507 1612 V 新设置
*/
function new_setting($code, $value)
{

    if (!isset($code) || !isset($value)) {
        return false;
    }

    $new = [
        'value' => $value
    ];

    $ret = $GLOBALS['db']->autoExecute('xm_master_config', $new, 'UPDATE', "code = '$code'");

    if (!$ret) {
        return false;
    }

    return true;
}
