<?php

use maqu\Services\UserCenterService;

/**
 * ECSHOP 会员管理程序
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: users.php 17217 2011-01-19 06:29:08Z liubo $
 */
define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
/* 代码增加2014-12-23 by www.68ecshop.com _star */
include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
/* 代码增加2014-12-23 by www.68ecshop.com _end */

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';

/* 路由 */

$function_name = 'action_' . $action;

if (!function_exists($function_name)) {
    $function_name = "action_list";
}

call_user_func($function_name);

/* 路由 */

/*
|- 20180425 1730 团队权限设置页
*/
function action_group_limit()
{

    admin_priv('users_manage');

    if (!$_GET['id']) {

        exit("<script>alert('出错了啦!');history.back(-1);</script>");
    }

    $smarty = $GLOBALS['smarty'];

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('group_limit'), $_GET['id']);
    $res = $db->getRow($sql);

    if (!$res) {

        exit("<script>alert('出错了啦!');history.back(-1);</script>");
    }
    //查看用户等级
    $rank = $db->getOne("SELECT user_cx_rank FROM xm_mq_users_extra WHERE user_id={$_GET['id']}");

    $xmps = explode('-', $res['xmps']);
    $xmcs = explode('-', $res['xmcs']);
    $xmts = explode(',', $res['xmts']);
    $smarty->assign('rank', $rank);
    $smarty->assign('xmps', $xmps);
    $smarty->assign('xmcs', $xmcs);
    $smarty->assign('xmts', $xmts);
    $smarty->assign('tstime', $res['tstime']);
    $smarty->assign('daily_deal_sell_max_money', $res['daily_deal_sell_max_money']);
    $smarty->assign('daily_deal_buy_max_money', $res['daily_deal_buy_max_money']);
    $smarty->assign('transfer_t_proportion', $res['transfer_t_proportion']);
    $smarty->assign('transfer_h_proportion', $res['transfer_h_proportion']);
    $smarty->assign('userid', $_GET['id']);
    $smarty->display('group_limit/limit.htm');

}

function action_group_transfer()
{

    admin_priv('users_manage');
    $check_ids = $_POST['data'];
    if (empty($check_ids)) {
        exit(json_encode(['sus' => 2, 'msg' => '未选择用户进行操作']));
    }
    $db = $GLOBALS['db'];
    $str_ids = implode(',', $check_ids);
    //查出会员等级
    $admin_name = $_SESSION['user_name'];
    $user_ids = $db->getAll("SELECT user_id,user_cx_rank FROM xm_mq_users_extra WHERE user_id IN ($str_ids)");
    foreach ($user_ids as $k => $v) {
        $ids[$v['user_id']] = $v['user_cx_rank'];
    }
    $time = time();
    $start_time = strtotime(date("Y-m-d", $time));
    $end_time = $start_time + 24 * 3600 - 1;
    $sql = "INSERT INTO xm_transfer_list (`op_name`,`user_id`,`rank`,`create_time`,`status`) VALUES ";
    foreach ($ids as $k => $v) {
        //一天设置一条最新名单
        $flag = $db->getRow("SELECT user_id FROM xm_transfer_list WHERE user_id={$k} AND status=1 AND create_time BETWEEN {$start_time} AND {$end_time}");
        if ($flag) {
            unset($ids[$k]);
            continue;
        }
        if ($v < 2) {
            unset($ids[$k]);
            continue;
        }
        $sql .= "('{$admin_name}','{$k}','{$v}',{$time},1),";
    }
    if ($ids) {
        $sql = rtrim($sql, ',');
        if ($db->query($sql)) {
            exit(json_encode(['sus' => 1, 'msg' => '设置成功']));
        } else {
            exit(json_encode(['sus' => 2, 'msg' => '设置失败,请重新设置']));
        }
    }
}


/*
|- 20180425 1813 V 团队权限设置操作
|- 20180503 0933 V 去除 购物积分 / 回购利润 = 回购金额 - 新美积分
*/
function action_set_group_limit()
{

    admin_priv('users_manage');

    $uid = intval($_POST['id']);
    $xmps = $_POST['xmps']; // 新美积分设置组 xinmei points
    $xmcs = $_POST['xmcs']; // 回购利润设置组 xinmei cousume points

    if (!$uid || !$xmps || !$xmcs) {

        exit(json_encode(['ret' => 2, 'msg' => '参数错误']));
    }

    $xmps_arr = explode('-', $xmps);
    $xmcs_arr = explode('-', $xmcs);

    foreach ($xmps_arr as $v) {

        if ($v > 100) {

            exit(json_encode(['ret' => 2, 'msg' => '参数错误']));
        }
    }

    $key = 0;

    foreach ($xmcs_arr as $v) {

        $key += $v;

        if ($v > 100) {

            exit(json_encode(['ret' => 2, 'msg' => '参数错误']));
        }
    }

    if ($key > 100) {

        exit(json_encode(['ret' => 2, 'msg' => '回购分配比例设置错误']));
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $rank_arr = [2, 3, 4];
    $rank_rules = [

        2 => '1, 5',
        3 => '2, 1, 5',
        4 => '3, 2, 1, 5'
    ];

    $sql = "SELECT user_cx_rank FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('mq_users_extra'), $uid);
    $check = $db->getRow($sql);

    if (!$check || !in_array($check['user_cx_rank'], $rank_arr)) {

        exit(json_encode(['ret' => 2, 'msg' => '用户不存在或非服务商']));
    }

    // 20180426 V 修改服务商权限
    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('group_limit'), $uid);
    $res = $db->getRow($sql);

    $new = [];

    if ($res['xmps'] != $xmps) {

        $new['xmps'] = $xmps;
    }

    if ($res['xmcs'] != $xmcs) {

        $new['xmcs'] = $xmcs;
    }

    if ($new == []) {

        exit(json_encode(['ret' => 2, 'msg' => '未更新设置']));
    }

    $new['update_at'] = time();

    $ret = $db->autoExecute($ecs->table('group_limit'), $new, 'UPDATE', "user_id = '$uid'");

    if (!$ret) {

        exit(json_encode(['ret' => 2, 'msg' => '操作失败！']));
    }

    // 冻结当前被操作用户的新美积分

    $sql = "SELECT * FROM %s WHERE user_id = %d";
    $sql = sprintf($sql, $ecs->table('xps'), $uid);
    $res = $db->getRow($sql);

    $the_money = $res['frozen'] + $res['unlimit'];

    if ($the_money == 0) {

        // 修改底层权限
        group_low_limit($uid, $xmps, $xmcs, $rank_rules[$check['user_cx_rank']]);
        exit(json_encode(['ret' => 1, 'msg' => '操作成功！']));
    }

    $new_xps = [

        'frozen' => $the_money * $xmps_arr[0] / 100,
        'unlimit' => $the_money * (1 - $xmps_arr[0] / 100),
        'update_at' => time()
    ];

    $result = $db->autoExecute($ecs->table('xps'), $new_xps, 'UPDATE', "user_id = '$uid'");

    // 修改流水记录
    $amount = $the_money * $xmps_arr[0] / 100;
    $notes = '团队新美积分冻结';

    if ($xmps_arr[0] == 0) {

        $amount = $res['frozen'];
        $notes = '团队新美积分解除冻结';
    }

    $flow_data = [
        'user_id' => $uid,
        'type' => 1,
        'amount' => $amount,
        'surplus' => $res['amount'],
        'notes' => $notes,
        'create_at' => time()
    ];

    $f_res = $db->autoExecute($ecs->table('flow_log'), $flow_data, 'INSERT');

    // 修改底层权限
    group_low_limit($uid, $xmps, $xmcs, $rank_rules[$check['user_cx_rank']]);

    echo json_encode(['ret' => 1, 'msg' => '操作成功！']);

}


/*
|- 20180426 V 递归修改团队底层用户的权限和冻结金额
*/
function group_low_limit($uids, $xmps, $xmcs, $urank)
{

    if (!$uids || !$xmps || !$xmcs || !$urank) {
        return false;
    }

    $current = time();

    // 查询当前层合适的用户
    $sql = "SELECT `user_id` FROM `xm_mq_users_extra` WHERE `invite_user_id` IN ($uids) AND `user_cx_rank` IN ($urank)";
    $res = $GLOBALS['db']->getAll($sql);

    if (!$res) {
        return;
    }

    $uids_arr = [];

    foreach ($res as $k => $v) {

        $uids_arr[] = $v['user_id'];
    }

    $uids_str = implode(',', $uids_arr);

    // 修改用户的 group_limit
    $new = [
        'xmps' => $xmps,
        'xmcs' => $xmcs,
        'update_at' => time()
    ];

    $GLOBALS['db']->autoExecute('xm_group_limit', $new, 'UPDATE', "user_id IN ($uids_str)");

    $sql = "SELECT * FROM %s WHERE user_id IN (%s)";
    $sql = sprintf($sql, $GLOBALS['ecs']->table('xps'), $uids_str);
    $res = $GLOBALS['db']->getAll($sql);

    $xmps_arr = explode('-', $xmps);

    foreach ($res as $k => $v) {

        // 分配新美积分
        $xmps_all = $v['frozen'] + $v['unlimit'];

        if ($xmps_all == 0) {
            continue;
        }

        $new_xps = [

            'frozen' => $xmps_all * $xmps_arr[0] / 100,
            'unlimit' => $xmps_all * (1 - $xmps_arr[0] / 100),
            'update_at' => $current
        ];

        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('xps'), $new_xps, 'UPDATE', "user_id = '" . $v['user_id'] . "'");

        // 写入流水记录
        $amount = $xmps_all * $xmps_arr[0] / 100;
        $notes = '冻结团队新美积分';

        if ($xmps_arr[0] == 0) {

            $amount = $v['frozen'];
            $notes = '解除团队新美积分冻结';
        }

        $flow_data = [

            'user_id' => $v['user_id'],
            'type' => 1,
            'amount' => $amount,
            'surplus' => $v['amount'],
            'notes' => $notes,
            'create_at' => $current
        ];

        $f_res = $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('flow_log'), $flow_data, 'INSERT');
    }

    group_low_limit($uids_str, $xmps, $xmcs, $urank);
}


// 20180621 1053
function action_set_group_tscore()
{

    $userid = $_POST['userid'];
    $lower = $_POST['lower'];

    // 20180628 1309 团队提现时间
    $tstime = $_POST['tstime'];

    if (!$userid || !isset($lower) || !$tstime) {

        exit(json_encode(['ret' => 2, 'msg' => '参数错误']));
    }

    // check tstime
    $tstime_arr = explode('-', $tstime);
    $week = [1, 2, 3, 4, 5];

    if (!in_array($tstime_arr[0], $week) || !in_array($tstime_arr[1], $week) || $tstime_arr[0] > $tstime_arr[1]) {

        exit(json_encode(['ret' => 2, 'msg' => '周期设置错误']));
    }

    if ($tstime_arr[2] > $tstime_arr[3] || count($tstime_arr) != 4) {

        exit(json_encode(['ret' => 2, 'msg' => '时间设置错误']));
    }

    $sql = "SELECT user_cx_rank FROM xm_mq_users_extra WHERE user_id = %d";
    $sql = sprintf($sql, $userid);
    $user = $GLOBALS['db']->getRow($sql);

    if ($user['user_cx_rank'] != 4) {

        exit(json_encode(['ret' => 2, 'msg' => '当前用户不是30万服务商']));
    }

    $sql = "SELECT xmts,tstime FROM xm_group_limit WHERE user_id = %d";
    $sql = sprintf($sql, $userid);
    $limit = $GLOBALS['db']->getRow($sql);

    $str = implode(',', $lower);

    if ($limit['xmts'] == $str && $tstime == $limit['tstime']) {

        exit(json_encode(['ret' => 2, 'msg' => '未更新设置']));
    }

    // 修改用户的提现限制
    $ret = $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('group_limit'), ['xmts' => $str, 'tstime' => $tstime], 'UPDATE', "user_id = $userid");

    if (!$ret) {

        exit(json_encode(['ret' => 1, 'msg' => '修改失败']));
    }

    $rank_rules = [

        2 => '1, 5, 0',
        3 => '2, 1, 5, 0',
        4 => '3, 2, 1, 5, 0'
    ];

    // 修改下级用户
    group_tscore_low($userid, $str, $tstime, $rank_rules[4]);

    echo json_encode(['ret' => 1, 'msg' => '修改成功']);
}


function action_set_group_bscore()
{

    $userid = $_POST['userid'];
    $buy_max_money = $_POST['buy_max_money'];
    $sell_max_money = $_POST['sell_max_money'];


    if (!$userid || !$buy_max_money || !$sell_max_money) {

        exit(json_encode(['ret' => 2, 'msg' => '参数错误']));
    }


    $sql = "SELECT user_cx_rank FROM xm_mq_users_extra WHERE user_id = %d";
    $sql = sprintf($sql, $userid);
    $user = $GLOBALS['db']->getRow($sql);

    if ($user['user_cx_rank'] != 4) {

        exit(json_encode(['ret' => 2, 'msg' => '当前用户不是30万服务商']));
    }

    $sql = "SELECT daily_deal_sell_max_money,daily_deal_buy_max_money FROM xm_group_limit WHERE user_id = %d";
    $sql = sprintf($sql, $userid);
    $limit = $GLOBALS['db']->getRow($sql);

    if ($sell_max_money == $limit['daily_deal_sell_max_money'] && $buy_max_money == $limit['daily_deal_buy_max_money']) {

        exit(json_encode(['ret' => 2, 'msg' => '未更新设置']));
    }

    // 修改用户的提现限制
    $ret = $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('group_limit'), ['daily_deal_sell_max_money' => $sell_max_money, 'daily_deal_buy_max_money' => $buy_max_money], 'UPDATE', "user_id = $userid");
    //修改个人的
    if (!$ret) {

        exit(json_encode(['ret' => 1, 'msg' => '修改失败']));
    }

    $rank_rules = [
        2 => '1, 5, 0',
        3 => '2, 1, 5, 0',
        4 => '3, 2, 1, 5, 0'
    ];

    // 修改下级用户
    group_bscore_low($userid, $buy_max_money, $sell_max_money, $rank_rules[4]);

    echo json_encode(['ret' => 1, 'msg' => '修改成功']);
}

function action_set_group_transfer()
{

    $userid = $_POST['userid'];
    $transfer_t_proportion = $_POST['transfer_t_proportion'];
    $transfer_h_proportion = $_POST['transfer_h_proportion'];


    if (!$userid) {

        exit(json_encode(['ret' => 2, 'msg' => '参数错误']));
    }


    $sql = "SELECT user_cx_rank FROM xm_mq_users_extra WHERE user_id = %d";
    $sql = sprintf($sql, $userid);
    $user = $GLOBALS['db']->getRow($sql);

    if ($user['user_cx_rank'] < 2) {

        exit(json_encode(['ret' => 2, 'msg' => '当前用户不是服务商']));
    }

    $sql = "SELECT transfer_t_proportion,transfer_h_proportion FROM xm_group_limit WHERE user_id = %d";
    $sql = sprintf($sql, $userid);
    $limit = $GLOBALS['db']->getRow($sql);

    if ($transfer_t_proportion == $limit['transfer_t_proportion'] && $transfer_h_proportion == $limit['transfer_h_proportion']) {

        exit(json_encode(['ret' => 2, 'msg' => '未更新设置']));
    }

    // 修改用户的提现限制
    $ret = $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('group_limit'), ['transfer_t_proportion' => $transfer_t_proportion, 'transfer_h_proportion' => $transfer_h_proportion], 'UPDATE', "user_id = $userid");
    //修改个人的
    if (!$ret) {
        exit(json_encode(['ret' => 1, 'msg' => '修改失败']));
    }

    $rank_rules = [
        2 => '1, 5, 0',
        3 => '2, 1, 5, 0',
        4 => '3, 2, 1, 5, 0'
    ];
    // 修改下级用户

    group_transfer_low($userid, $transfer_t_proportion, $transfer_h_proportion, $rank_rules[$user['user_cx_rank']]);

    echo json_encode(['ret' => 1, 'msg' => '修改成功']);
}

function group_transfer_low($uids, $transfer_t_proportion, $transfer_h_proportion, $urank)
{

    if (!$uids || !$urank) {
        return false;
    }
    $current = time();

    // 查询当前层合适的用户
    $sql = "SELECT `user_id` FROM `xm_mq_users_extra` WHERE `invite_user_id` IN ($uids) AND `user_cx_rank` IN ($urank)";
    $res = $GLOBALS['db']->getAll($sql);

    if (!$res) {
        return;
    }

    $uids_arr = [];

    foreach ($res as $k => $v) {

        $uids_arr[] = $v['user_id'];
    }

    $uids_str = implode(',', $uids_arr);

    // 修改用户的 group_limit
    $new = [
        'transfer_t_proportion' => $transfer_t_proportion,
        'transfer_h_proportion' => $transfer_h_proportion,
        'update_at' => time()
    ];

    $GLOBALS['db']->autoExecute('xm_group_limit', $new, 'UPDATE', "user_id IN ($uids_str)");


    group_transfer_low($uids_str, $transfer_t_proportion, $transfer_h_proportion, $urank);
}

function group_bscore_low($uids, $bscore, $score, $urank)
{

    if (!$uids || !$bscore || !$score || !$urank) {
        return false;
    }

    $current = time();

    // 查询当前层合适的用户
    $sql = "SELECT `user_id` FROM `xm_mq_users_extra` WHERE `invite_user_id` IN ($uids) AND `user_cx_rank` IN ($urank)";
    $res = $GLOBALS['db']->getAll($sql);

    if (!$res) {
        return;
    }

    $uids_arr = [];

    foreach ($res as $k => $v) {

        $uids_arr[] = $v['user_id'];
    }

    $uids_str = implode(',', $uids_arr);

    // 修改用户的 group_limit
    $new = [
        'daily_deal_buy_max_money' => $bscore,
        'daily_deal_sell_max_money' => $score,
        'update_at' => time()
    ];

    $GLOBALS['db']->autoExecute('xm_group_limit', $new, 'UPDATE', "user_id IN ($uids_str)");


    group_bscore_low($uids_str, $bscore, $score, $urank);
}

function group_tscore_low($uids, $xmts, $tstime, $urank)
{

    if (!$uids || !$xmts || !$tstime || !$urank) {
        return false;
    }

    $current = time();

    // 查询当前层合适的用户
    $sql = "SELECT `user_id` FROM `xm_mq_users_extra` WHERE `invite_user_id` IN ($uids) AND `user_cx_rank` IN ($urank)";
    $res = $GLOBALS['db']->getAll($sql);

    if (!$res) {
        return;
    }

    $uids_arr = [];

    foreach ($res as $k => $v) {

        $uids_arr[] = $v['user_id'];
    }

    $uids_str = implode(',', $uids_arr);

    // 修改用户的 group_limit
    $new = [

        'xmts' => $xmts,
        'tstime' => $tstime,
        'update_at' => time()
    ];

    $GLOBALS['db']->autoExecute('xm_group_limit', $new, 'UPDATE', "user_id IN ($uids_str)");

    group_tscore_low($uids_str, $xmts, $tstime, $urank);
}

/* ------------------------------------------------------ */
// -- 我的团队
/* ------------------------------------------------------ */

function action_my_team($user_id)
{
// 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];
    $smarty->display('.htm');

    /* 检查权限 */
    admin_priv('users_manage');
}

/* ------------------------------------------------------ */
// -- 用户帐号列表
/* ------------------------------------------------------ */
function action_list()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];
    /* 检查权限 */
    admin_priv('users_manage');


    $smarty->assign('ur_here', $_LANG['03_users_list']);


    $user_list = user_list();

    $smarty->assign('user_list', $user_list['user_list']);
    $smarty->assign('filter', $user_list['filter']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('page_count', $user_list['page_count']);
    $smarty->assign('full_page', 1);
    $smarty->assign('sort_user_id', '<img src="images/sort_desc.gif">');

    assign_query_info();
    $smarty->display('users_list.htm');
}

/* ------------------------------------------------------ */
// --
/* ------------------------------------------------------ */
function action_member_excel()
{
    $smarty = $GLOBALS['smarty'];
    $smarty->display('home/member_excel.htm');
}

/* ------------------------------------------------------ */
// -- ajax返回用户列表
/* ------------------------------------------------------ */
function action_query()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    $user_list = user_list();

    $smarty->assign('user_list', $user_list['user_list']);
    $smarty->assign('filter', $user_list['filter']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('page_count', $user_list['page_count']);

    $sort_flag = sort_flag($user_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('users_list.htm'), '', array(
        'filter' => $user_list['filter'], 'page_count' => $user_list['page_count']
    ));
}


/* ------------------------------------------------------ */
// -- 编辑用户帐号
/* ------------------------------------------------------ */
function action_edit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_GET['id'];


    /* 检查权限 */
    admin_priv('users_manage');
    $sql = "SELECT u.user_id,u.user_name,u.mobile_phone,u.sex,u.birthday,e.user_cx_rank as rank_id,e.card,e.face_card,e.back_card,e.real_name,ua.balance,ua.release_balance FROM xm_users u 
          LEFT JOIN xm_mq_users_extra e ON u.user_id=e.user_id 
           LEFT JOIN xm_user_account ua ON u.user_id=ua.user_id 
            WHERE u.user_id={$user_id} ";
    $user = $db->getRow($sql);

    if (empty($user)) {
        $link[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg($_LANG['username_invalid'], 0, $link);
    }
    $user['face'] = $user['face_card'];
    $user['back'] = $user['back_card'];
    if (strstr($user['face_card'], 'appImage')) {
        $user['face_card'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/' . $user['face_card']);
    } else {
        $user['face_card'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath($user['face_card']);
    }
    if (strstr($user['back_card'], 'appImage')) {
        $user['back_card'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/' . $user['back_card']);
    } else {
        $user['back_card'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath($user['back_card']);
    }


//    if ($user['rank_id'] > 1) {
//        $address = $db->getRow("SELECT * FROM xm_team_leaders WHERE user_id={$user_id}");
//        $user['area'] = empty($address['tl_area']) ? '' : $address['tl_area'];
//        $user['address'] = empty($address['tl_detail']) ? '' : $address['tl_detail'];
//    }
    assign_query_info();
    $smarty->assign('ur_here', $_LANG['users_edit']);
    $smarty->assign('action_link', array(
        'text' => $_LANG['03_users_list'], 'href' => 'users.php?act=list&' . list_link_postfix()
    ));

    $smarty->assign('lang', $_LANG);
    $smarty->assign('user', $user);
    $smarty->assign('form_action', 'update');
    $smarty->display('user_info.htm');
}

function action_show_image()
{

    $smarty = $GLOBALS['smarty'];
    $img_url = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath('/' . $_GET['img_url']);


    $smarty->assign('img_url', $img_url);
    $smarty->display('goods_show_image.htm');
}

/* ------------------------------------------------------ */
// -- 更新用户帐号
/* ------------------------------------------------------ */
function action_update()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    include_once(ROOT_PATH . '/includes/cls_image.php');

    /* 检查权限 */
    admin_priv('users_manage');

    $rank_id = $_POST['rank_id'];//用户等级ID
    $user_id = $_POST['id'];//用户ID
    //查出该会员现在的等级
    $rank = $db->getOne("SELECT user_cx_rank FROM xm_mq_users_extra WHERE user_id={$user_id}");

    if ($rank != $rank_id) {

        if ($user_id) {
            if ($rank == 4 && $rank_id < 4) {

                $db->query("UPDATE xm_mq_users_extra SET user_cx_rank={$rank_id} WHERE user_id={$user_id}");
                update_new_status($user_id, $rank_id);
                //上级加上团队人数
                $user = $db->getRow("SELECT team_number,invite_user_id,new_status FROM xm_mq_users_extra WHERE user_id={$user_id}");
                //上级减去团队人数不包括他自己
//                $team_num = $user['team_number']-1;
//                $db->query("UPDATE xm_mq_users_extra SET team_number=team_number+$team_num WHERE user_id={$user['invite_user_id']}");
//                //查出上级的上级
//                $inv_id =$db->getOne("SELECT invite_user_id FROM xm_mq_users_extra WHERE user_id={$user['invite_user_id']}");
//                $in_user = $db->getRow("SELECT invite_user_id,user_cx_rank,user_id FROM xm_mq_users_extra WHERE user_id={$inv_id}");
//                if ($in_user['user_cx_rank'] == 4) {
//                    add_num($in_user, $user['team_number'],true);
//                }else{
//                     add_num($in_user, $user['team_number']);
//                }
                //更改下级new_status

                group_new_status($user_id, $user['new_status'], '3, 2, 1, 5,0');
            }
            if ($rank < 4 && $rank_id == 4) {
                $str = $user_id . '-0-0-0-0';
                $db->query("UPDATE xm_mq_users_extra SET user_cx_rank={$rank_id} , new_status = '{$str}'  WHERE user_id={$user_id}");
//                //剔除这个团队人数
//                $user = $db->getRow("SELECT team_number,invite_user_id FROM xm_mq_users_extra WHERE user_id={$user_id}");
//                //上级减去团队人数不包括他自己
//                $team_num = $user['team_number']-1;
//                $db->query("UPDATE xm_mq_users_extra SET team_number=team_number-$team_num WHERE user_id={$user['invite_user_id']}");
//                //查出上级的上级
//                $inv_id =$db->getOne("SELECT invite_user_id FROM xm_mq_users_extra WHERE user_id={$user['invite_user_id']}");
//                $in_user = $db->getRow("SELECT invite_user_id,user_cx_rank,user_id FROM xm_mq_users_extra WHERE user_id={$inv_id}");
//                if($in_user['user_cx_rank']==4){
//                   delete_num($in_user, $user['team_number'],true);
//                }else{
//                    delete_num($in_user, $user['team_number']);
//                }

                //更改下级new_status
                group_new_status($user_id, $user_id . '-0-0-0-0', '3, 2, 1, 5,0');
            }
            if ($rank < 4 && $rank_id < 4) {
                $db->query("UPDATE xm_mq_users_extra SET user_cx_rank={$rank_id} WHERE user_id={$user_id}");
            }
        }
    }


    /* 记录管理员操作 */
    admin_log($_SESSION['user_name'], 'edit', 'users');

    /* 提示信息 */
    $links[0]['text'] = $_LANG['goto_list'];
    $links[0]['href'] = 'users.php?act=list&' . list_link_postfix();
    $links[1]['text'] = $_LANG['go_back'];
    $links[1]['href'] = 'javascript:history.back()';

    sys_msg($_LANG['update_success'], 0, $links);
}

function add_num($in_user, $team_number, $flag = false)
{
    $db = $GLOBALS['db'];
    while (true) {
        //判断是否是第一个30万的
        if ($flag) {
            break;
        }
        $db->query("UPDATE xm_mq_users_extra SET team_number=team_number+{$team_number} WHERE user_id={$in_user['user_id']}");
        if (empty($in_user['invite_user_id'])) {
            break;
        }
        $in_user = $db->getRow("SELECT invite_user_id,user_cx_rank,user_id FROM xm_mq_users_extra WHERE user_id={$in_user['invite_user_id']}");
        if (empty($in_user)) {
            break;
        }
        if ($in_user['user_cx_rank'] == 4) {
            $flag = true;
            $db->query("UPDATE xm_mq_users_extra SET team_number=team_number+{$team_number} WHERE user_id={$in_user['user_id']}");
        } else {
            $flag = false;
        }
        add_num($in_user, $team_number, $flag);
    }
}

function delete_num($in_user, $team_number, $flag = false)
{
    $db = $GLOBALS['db'];
    while (true) {
        //判断是否是第一个30万的
        if ($flag) {
            break;
        }
        $db->query("UPDATE xm_mq_users_extra SET team_number=team_number-{$team_number} WHERE user_id={$in_user['user_id']}");
        if (empty($in_user['invite_user_id'])) {
            break;
        }
        $in_user = $db->getRow("SELECT invite_user_id,user_cx_rank,user_id FROM xm_mq_users_extra WHERE user_id={$in_user['invite_user_id']}");
        if (empty($in_user)) {
            break;
        }
        if ($in_user['user_cx_rank'] == 4) {
            $flag = true;
            $db->query("UPDATE xm_mq_users_extra SET team_number=team_number-{$team_number} WHERE user_id={$in_user['user_id']}");
        } else {
            $flag = false;
        }
        delete_num($in_user, $team_number, $flag);
    }
}

// 20180518 1456 V 变更服务商身份，变更相关的 new_status
function update_new_status($uid, $rank)
{

//    if (!$uid || !$rank) {
//        return;
//    }

    $db = $GLOBALS['db'];

    if ($rank == 4) {

        // 30 万
        $sql = "UPDATE `xm_mq_users_extra` SET `new_status` = '$uid-0-0-0-0' WHERE user_id = $uid";

    } else {

        // 查询上面的第一个 30 服务商的 new_status
        $wh_user_id = $uid;
        $new_str = '';

        while (true) {

            $res = $db->getRow("SELECT user_id, user_cx_rank, invite_user_id, new_status FROM `xm_mq_users_extra` WHERE user_id = $wh_user_id");

            if ($res['user_cx_rank'] == 4) {

                $new_str = $res['new_status'];

                break;
            }

            $wh_user_id = $res['invite_user_id'];
        }

        $sql = "UPDATE `xm_mq_users_extra` SET `new_status` = '$new_str' WHERE user_id = $uid";

    }

    $ret = $db->query($sql);

    if (!$ret) {
        return false;
    }

    return true;
}

/* ------------------------------------------------------ */
// -- 批量删除会员帐号
/* ------------------------------------------------------ */
function action_batch_remove()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');

    if (isset($_POST['checkboxes'])) {
        $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id " . db_create_in($_POST['checkboxes']);
        $col = $db->getCol($sql);
        $usernames = implode(',', addslashes_deep($col));
        $count = count($col);
        /* 通过插件来删除用户 */
        $users = &init_users();
        $users->remove_user($col);

        admin_log($usernames, 'batch_remove', 'users');

        $lnk[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg(sprintf($_LANG['batch_remove_success'], $count), 0, $lnk);
    } else {
        $lnk[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg($_LANG['no_select_user'], 0, $lnk);
    }
}

/* 编辑用户名 */
function action_edit_username()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $username = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);

    if ($id == 0) {
        make_json_error('NO USER ID');
        return;
    }

    if ($username == '') {
        make_json_error($GLOBALS['_LANG']['username_empty']);
        return;
    }

    $users = &init_users();

    if ($users->edit_user($id, $username)) {
        if ($_CFG['integrate_code'] != 'ecshop') {
            /* 更新商城会员表 */
            $db->query('UPDATE ' . $ecs->table('users') . " SET user_name = '$username' WHERE user_id = '$id'");
        }

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result(stripcslashes($username));
    } else {
        $msg = ($users->error == ERR_USERNAME_EXISTS) ? $GLOBALS['_LANG']['username_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
        make_json_error($msg);
    }
}

/* ------------------------------------------------------ */
// -- 编辑email
/* ------------------------------------------------------ */
function action_edit_email()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $email = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_email($email)) {
        if ($users->edit_user(array(
            'username' => $username, 'email' => $email
        ))
        ) {
            admin_log(addslashes($username), 'edit', 'users');

            make_json_result(stripcslashes($email));
        } else {
            $msg = ($users->error == ERR_EMAIL_EXISTS) ? $GLOBALS['_LANG']['email_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
            make_json_error($msg);
        }
    } else {
        make_json_error($GLOBALS['_LANG']['invalid_email']);
    }
}

// 商品推荐人认证
function action_edit_is_referee()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    // 实名认证验证
    $sql = "SELECT real_name FROM " . $ecs->table('users') . " WHERE user_id = " . $id;
    if (!$db->getOne($sql)) {
        make_json_error('该用户没有实名认证，不能成为商品推荐人');
    }


    $is_referee = $_REQUEST['val'];

    $sql = "UPDATE " . $ecs->table('mq_users_extra') . " SET `is_referee` = '" . $is_referee . "' WHERE user_id = " . $id;
    $db->query($sql);
    $sql = "select is_referee from " . $ecs->table('mq_users_extra') . " where user_id = " . $id;
    $check = $db->getOne($sql);
    make_json_result(($check == 1) ? 1 : 0);

}

//消费积分
function action_act_edit_consume_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    // $consume_credit = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));
    $consume_credit = $_REQUEST['val'];
    // var_dump($_REQUEST['val']);exit();

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($consume_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$consume_credit' where account_type = 'consume' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);
        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($consume_credit);
    } else {
        make_json_error('您输入的消费积分不是一个合法的积分。');
    }
}

//待用积分
function action_edit_invest_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $invest_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($invest_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$invest_credit' where account_type = 'invest' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($invest_credit);

    } else {
        make_json_error('您输入的待用积分不是一个合法的积分。');
    }
}

//注册积分
function action_edit_register_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $register_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($register_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$register_credit' where account_type = 'register' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($register_credit);

    } else {
        make_json_error('您输入的注册积分不是一个合法的积分。');
    }
}

//分享积分
function action_edit_share_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $share_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($share_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$share_credit' where account_type = 'share' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($share_credit);

    } else {
        make_json_error('您输入的分享积分不是一个合法的积分。');
    }
}

//分享积分
function action_edit_shopping_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $shopping_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($shopping_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$shopping_credit' where account_type = 'shopping' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($shopping_credit);

    } else {
        make_json_error('您输入的购物券不是一个合法的数字。');
    }
}

//可用积分
function action_edit_useable_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $useable_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($useable_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$useable_credit' where account_type = 'useable' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($useable_credit);

    } else {
        make_json_error('您输入的可用积分不是一个合法的积分。');
    }
}

/*获得用户account_id*/
function get_account_id($username)
{
    $sql = "select account_id" . " FROM " . $GLOBALS['ecs']->table('mq_users_extra') . ' AS mue ' .
        ' LEFT JOIN ' . $GLOBALS['ecs']->table('users') . " AS u ON u.user_id = mue.user_id " .
        " where u.user_name = '" . $username . "'";
    $account_id = $GLOBALS['db']->getOne($sql);
    return $account_id;
}

/*end*/

function action_edit_mobile_phone()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $mobile_phone = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if ($users->edit_user(array(
        'username' => $username, 'mobile_phone' => $mobile_phone
    ))
    ) {
        admin_log(addslashes($username), 'edit', 'users');

        make_json_result(stripcslashes($mobile_phone));
    } else {
        $msg = ($users->error == ERR_MOBILE_PHONE_EXISTS) ? $GLOBALS['_LANG']['mobile_phone_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
        make_json_error($msg);
    }

}


function action_edit_cash_points()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $mobile_phone = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_mobile_phone($mobile_phone)) {
        if ($users->edit_user(array(
            'username' => $username, 'mobile_phone' => $mobile_phone
        ))
        ) {
            admin_log(addslashes($username), 'edit', 'users');

            make_json_result(stripcslashes($mobile_phone));
        } else {
            $msg = ($users->error == ERR_MOBILE_PHONE_EXISTS) ? $GLOBALS['_LANG']['mobile_phone_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
            make_json_error($msg);
        }
    } else {
        make_json_error($GLOBALS['_LANG']['invalid_mobile_phone']);
    }
}

/* ------------------------------------------------------ */
// -- 删除会员帐号
/* ------------------------------------------------------ */
function action_remove()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');
    /* 如果会员已申请或正在申请入驻商家，不能删除会员 */
    $sql = " SELECT COUNT(*) FROM " . $ecs->table('supplier') . " WHERE user_id='" . $_GET['id'] . "'";
    $issupplier = $db->getOne($sql);
    if ($issupplier > 0) {
        /* 提示信息 */
        $link[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg(sprintf('该会员已申请或正在申请入驻商，不能删除！'), 0, $link);
    } else {
        $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
        $username = $db->getOne($sql);
        /* 通过插件来删除用户 */
        $users = &init_users();
        $users->remove_user($username); // 已经删除用户所有数据
        /* 记录管理员操作 */
        admin_log(addslashes($username), 'remove', 'users');

        /* 提示信息 */
        $link[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg(sprintf($_LANG['remove_success'], $username), 0, $link);
    }
}

/* ------------------------------------------------------ */
// -- 重置密码
/* ------------------------------------------------------ */
function action_rest_password()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];
    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
    );
    /* 检查权限 */
    admin_priv('users_manage');

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
    $user_name = $db->getOne($sql);

    if (!$user_name) {
        sys_msg(sprintf('用户数据异常！', $user_name), 0, $link);
    }
    $sql = "UPDATE " . $ecs->table('users') . "SET `ec_salt`='0',`edit_password`='0',`password`='" . md5(123456) . "' WHERE user_id= '" . $_GET['id'] . "'";
    $db->query($sql);
    /* 记录管理员操作 */
    admin_log(addslashes($user_name), 'rest_password', 'users');
    sys_msg(sprintf('密码重置成功！', $user_name), 0, $link);

}

/* ------------------------------------------------------ */
// -- 禁用会员帐号 2017年3月1日 by fzp
/* ------------------------------------------------------ */
function action_forbiden()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');
    $sql = "UPDATE " . $ecs->table('mq_users_extra') . " SET user_status = 2 WHERE user_id= '" . $_GET['id'] . "'";
    $db->query($sql);

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
    $username = $db->getOne($sql);

    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
    );
    sys_msg(sprintf($_LANG['forbiden_success'], $username), 0, $link);
}

/* ------------------------------------------------------ */
// -- 启用会员帐号 2017年3月2日 by fzp
/* ------------------------------------------------------ */
function action_recover()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');
    $sql = "UPDATE " . $ecs->table('mq_users_extra') . " SET user_status = 1 WHERE user_id= '" . $_GET['id'] . "'";
    $db->query($sql);

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
    $username = $db->getOne($sql);

    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
    );
    sys_msg(sprintf($_LANG['recover_success'], $username), 0, $link);
}

/* ------------------------------------------------------ */
// -- 收货地址查看
/* ------------------------------------------------------ */
function action_address_list()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $sql = "SELECT a.*, c.region_name AS country_name, p.region_name AS province, ct.region_name AS city_name, d.region_name AS district_name " . " FROM " . $ecs->table('user_address') . " as a " . " LEFT JOIN " . $ecs->table('region') . " AS c ON c.region_id = a.country " . " LEFT JOIN " . $ecs->table('region') . " AS p ON p.region_id = a.province " . " LEFT JOIN " . $ecs->table('region') . " AS ct ON ct.region_id = a.city " . " LEFT JOIN " . $ecs->table('region') . " AS d ON d.region_id = a.district " . " WHERE user_id='$id'";
    $address = $db->getAll($sql);
    $smarty->assign('address', $address);
    assign_query_info();
    $smarty->assign('ur_here', $_LANG['address_list']);
    $smarty->assign('action_link', array(
        'text' => $_LANG['03_users_list'], 'href' => 'users.php?act=list&' . list_link_postfix()
    ));
    $smarty->display('user_address_list.htm');
}

/* ------------------------------------------------------ */
// -- 脱离推荐关系
/* ------------------------------------------------------ */
function action_remove_parent()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_manage');

    $sql = "UPDATE " . $ecs->table('users') . " SET parent_id = 0 WHERE user_id = '" . $_GET['id'] . "'";
    $db->query($sql);

    /* 记录管理员操作 */
    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
    $username = $db->getOne($sql);
    admin_log(addslashes($username), 'edit', 'users');

    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
    );
    sys_msg(sprintf($_LANG['update_success'], $username), 0, $link);
}

/* ------------------------------------------------------ */
// -- 查看用户推荐会员列表
/* ------------------------------------------------------ */
function action_aff_list()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_manage');
    $smarty->assign('ur_here', $_LANG['03_users_list']);

    $auid = $_GET['auid'];
    $user_list['user_list'] = array();

    $affiliate = unserialize($GLOBALS['_CFG']['affiliate']);
    $smarty->assign('affiliate', $affiliate);

    empty($affiliate) && $affiliate = array();

    $num = count($affiliate['item']);
    $up_uid = "'$auid'";
    $all_count = 0;
    for ($i = 1; $i <= $num; $i++) {
        $count = 0;
        if ($up_uid) {
            $sql = "SELECT user_id FROM " . $ecs->table('users') . " WHERE parent_id IN($up_uid)";
            $query = $db->query($sql);
            $up_uid = '';
            while ($rt = $db->fetch_array($query)) {
                $up_uid .= $up_uid ? ",'$rt[user_id]'" : "'$rt[user_id]'";
                $count++;
            }
        }
        $all_count += $count;

        if ($count) {
            $sql = "SELECT user_id, user_name, '$i' AS level, email, is_validated, user_money, frozen_money, rank_points, pay_points, reg_time " . " FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id IN($up_uid)" . " ORDER by level, user_id";
            $user_list['user_list'] = array_merge($user_list['user_list'], $db->getAll($sql));
        }
    }

    $temp_count = count($user_list['user_list']);
    for ($i = 0; $i < $temp_count; $i++) {
        $user_list['user_list'][$i]['reg_time'] = local_date($_CFG['date_format'], $user_list['user_list'][$i]['reg_time']);
    }

    $user_list['record_count'] = $all_count;

    $smarty->assign('user_list', $user_list['user_list']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('full_page', 1);
    $smarty->assign('action_link', array(
        'text' => $_LANG['back_note'], 'href' => "users.php?act=edit&id=$auid"
    ));

    assign_query_info();
    $smarty->display('affiliate_list.htm');
}

/* ------------------------------------------------------ */
// -- 用户帐号限购 addby ji 2017年9月12日14:33:31
/* ------------------------------------------------------ */
function action_user_restriction()
{
    // 全局变量
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];

    /* 检查权限 */
    admin_priv('users_manage');

    $id = intval($_GET['id']);

    //从会员限制表获取数据
    $sql = <<<EOF
    SELECT
        xmql.*,
       u.user_name,
       u.user_id
    FROM
        %s xmql
    RIGHT JOIN %s u ON u.user_id = xmql.user_id
    WHERE
        u.user_id = %u
EOF;
    $sql = sprintf($sql, $GLOBALS['ecs']->table('mq_users_limit'), $GLOBALS['ecs']->table('users'), $id);
    $xmul_row = $db->GetRow($sql);

    assign_query_info();

    $smarty->assign('ur_here', '账号限购');
    $smarty->assign('user', $xmul_row);
    $smarty->assign('lang', $_LANG);

    $smarty->assign('form_action', 'update_restriction');
    $smarty->display('user_limit.htm');
}

/* ------------------------------------------------------ */
// -- 更新帐号限购 addby ji 2017年9月12日14:33:31
/* ------------------------------------------------------ */
function action_update_restriction()
{
    // 全局变量
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $_LANG = $GLOBALS['_LANG'];

    /* 检查权限 */
    admin_priv('users_manage');

    //获取参数
    $data = array();

    $data['day_release_ratio'] = trim($_POST['day_release_ratio']);
    $data['daily_deal_buy_max_money'] = trim($_POST['daily_deal_buy_max_money']);
    $data['daily_deal_sell_max_money'] = trim($_POST['daily_deal_sell_max_money']);

    $id = intval($_POST['id']);


    if ($_POST['type'] == 'personal') {
        //更新xm_mq_users_limit
        $sql = "SELECT COUNT(*) FROM %s WHERE user_id = %u ";


        $sql = sprintf($sql, $ecs->table('mq_users_limit'), $id);
        $result = $db->GetOne($sql);
        if ($result) {    //存在则更新
            $data['update_at'] = gmtime();
            $db->autoExecute($ecs->table('mq_users_limit'), $data, 'UPDATE', "user_id = '$id'");
        } else { //不存在则插入
            $data['user_id'] = $id;
            $data['create_at'] = gmtime();
            $data['update_at'] = gmtime();
            $db->autoExecute($ecs->table('mq_users_limit'), $data, 'INSERT');
        }
        //更新admin_log
        admin_log($id, 'user_restriction', 'users');

    } elseif ($_POST['type'] == 'group') {

        // 20180420 1453 V 设置服务商新规则权限（封闭）
        //------------------------------------------------------------------------------
        $rank_arr = [2, 3, 4];

        $sql = "SELECT user_cx_rank, new_status FROM %s WHERE user_id = %d";
        $sql = sprintf($sql, $GLOBALS['ecs']->table('mq_users_extra'), $id);
        $res = $GLOBALS['db']->getRow($sql);

        $urank = $res['user_cx_rank'];

        // 如果是服务商
        if (in_array($urank, $rank_arr)) {

            $pre_str = $_POST['shop'] . '-' . $_POST['horder'] . '-' . $_POST['xmp'] . '-' . $_POST['consume'];

            $new_str = $id . '-' . $pre_str;

            // 取消限制
            if ($pre_str == '0-0-0-0') {

                $new_str = '0-' . $pre_str;
            }

            // 规则若有改动，更新原有规则
            if ($new_str != $res['new_status']) {

                $change['new_status'] = $new_str;
                $ret = $db->autoExecute($ecs->table('mq_users_extra'), $change, 'UPDATE', "user_id = '$id'");

                if ($ret) {

                    // 20180614 1119 添加日志
                    @file_put_contents('../error_log/' . date('Ymd') . '_new_status.txt', date('H:i:s')
                        . ' - admin-' . $_SESSION['admin_id'] . ' - uid-' . $id . ' - status-' . $new_str . "\n\r", FILE_APPEND);

                    // 1 激活会员，2 3万服务商，3 10万服务商，4 30万服务商，5 注册会员
                    // 等级低于他的团队也将被限制
                    $rank_rules = [

                        2 => '1, 5,0',
                        3 => '2, 1, 5,0',
                        4 => '3, 2, 1, 5,0'
                    ];

                    group_new_status($id, $new_str, $rank_rules[$urank]);
                }
            }

        }
        //---------------------------------------------------------------------------------

    }

    /* 提示信息 */
    $links[0]['text'] = "返回限制会员列表";
    $links[0]['href'] = 'users.php?act=list';
    $links[1]['text'] = $_LANG['go_back'];
    $links[1]['href'] = 'javascript:history.back(-2)';

    clear_cache_files(); // 清除模版缓存

    sys_msg("操作成功！", 0, $links);
}


/*
|- 20180423 1406 V 限制团队（精品商城/H 单）购买，限制新美和消费积分的转账
*/
function group_new_status($uids, $new_str, $urank)
{

    if (!$uids || !$new_str || !$urank) {
        return false;
    }

    // 查询当前层合适的用户
    $sql = "SELECT `user_id` FROM `xm_mq_users_extra` WHERE `invite_user_id` IN ($uids) AND `user_cx_rank` IN ($urank)";
    $res = $GLOBALS['db']->getAll($sql);

    if (!$res) {
        return;
    }

    $uids_arr = [];

    foreach ($res as $k => $v) {

        $uids_arr[] = $v['user_id'];
    }

    $uids_str = implode(',', $uids_arr);

    @file_put_contents('../error_log/' . date('Ymd') . '_new_status.txt', date('H:i:s') . ' - uids-' . $uids_str . "\n\r", FILE_APPEND);

    // 修改用户的 new_status
    $new = ['new_status' => $new_str];
    $res = $GLOBALS['db']->autoExecute('xm_mq_users_extra', $new, 'UPDATE', "user_id IN ($uids_str)");

    group_new_status($uids_str, $new_str, $urank);
}

/**
 * 返回用户列表数据
 *
 * @access public
 * @param
 *
 * @return void
 */
function user_list()
{

    $result = get_filter();
    if ($result === false) {
        /* 过滤条件 */

        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $filter['real_name'] = empty($_REQUEST['real_name']) ? '' : trim($_REQUEST['real_name']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1) {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
            $filter['real_name'] = json_str_iconv($filter['real_name']);
        }

        $filter['rank'] =intval($_REQUEST['rank']);

        $filter['pay_points_gt'] = empty($_REQUEST['pay_points_gt']) ? 0 : intval($_REQUEST['pay_points_gt']);
        $filter['pay_points_lt'] = empty($_REQUEST['pay_points_lt']) ? 0 : intval($_REQUEST['pay_points_lt']);
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'u.user_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $ex_where = ' WHERE 1 ';
        if ($filter['keywords']) {
            $ex_where .= " AND u.user_name='{$filter['keywords']}'";
        }
        if ($filter['real_name']) {
            $ex_where .= " AND mue.real_name like  '%" . mysql_like_quote($filter['real_name']) . "%'";
        }
        if ($filter['rank']) {
            $ex_where .= " AND mue.user_cx_rank  = {$filter['rank']}";
        }
       //var_dump($filter['rank']);die;
        $sql1 = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('users') . " AS u" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON u.user_id = mue.user_id " .
            $ex_where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql1);

        /* 分页大小 */
        $filter = page_and_size($filter);

        $belong_sys = BELONG_SYS_UCENTER; //会员中心
        $sql = "SELECT u.user_id, u.user_name, u.mobile_phone,mue.user_status, ua.balance,ua.release_balance,mue.invite_user_id,mue.real_name,mue.status, mue.invite_code, 
        u.rank_points, u.pay_points, u.reg_time, u.froms " .
            " FROM " . $GLOBALS['ecs']->table('users') . ' AS u ' .
            ' LEFT JOIN ' . $GLOBALS['ecs']->table('mq_users_extra') . ' AS mue ON u.user_id = mue.user_id ' .
            ' LEFT JOIN ' . $GLOBALS['ecs']->table('user_account') . " AS ua ON ua.user_id = u.user_id  " .
            $ex_where . " ORDER by " . $filter['sort_by'] . ' ' . $filter['sort_order'] . " LIMIT " . $filter['start'] . ',' . $filter['page_size'];

        $filter['keywords'] = stripslashes($filter['keywords']);

    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $user_list = $GLOBALS['db']->getAll($sql);


    // end
    foreach ($user_list as $k => $v) {
        switch ($v['user_cx_rank']) {
            case 1 :
                $user_list[$k]['rank_name'] = '普通';
                break;
            case 2 :
                $user_list[$k]['rank_name'] = '初级';
                break;
            case 3 :
                $user_list[$k]['rank_name'] = '中级';
                break;
            case 4 :
                $user_list[$k]['rank_name'] = '高级';
                break;
        }
	if(is_null($v['invite_user_id'])){
		$v['invite_user_id']=1;
	}
        $user_list[$k]['reg_time'] = date("Y-m-d", $v['reg_time']);
        //查出推荐人信息
        $sql11 = "SELECT u.real_name FROM " . $GLOBALS['ecs']->table('mq_users_extra') . " AS u WHERE u.user_id= " . $v['invite_user_id'];
        $user_list[$k]['invited_username'] = $GLOBALS['db']->getOne($sql11);
    }

    $arr = array(
        'user_list' => $user_list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']
    );

    return $arr;
}

function action_up_info()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    $sql = "UPDATE " . $ecs->table('mq_users_extra') . "SET `user_cx_rank`='" . $_POST['lv'] . "' WHERE user_id= '" . $_POST['id'] . "'";
    $db->query($sql);

}
