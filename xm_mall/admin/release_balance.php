<?php
/**
 * ECSHOP 报单统计
 * $Author: ji
 * 2018年1月15日12:55:10
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/admin/statistic.php');
require_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/admin/order.php');
require_once(ROOT_PATH . 'includes/lib_order.php');

// act操作项的初始化
if (empty($_REQUEST['act'])) {

    $_REQUEST['act'] = 'list';

} else {

    $_REQUEST['act'] = trim($_REQUEST['act']);
}

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';

/* 路由 */
$function_name = 'action_' . $action;
if (!function_exists($function_name)) {

    $function_name = "action_list";
}

call_user_func($function_name);


// 20180510 14 V 报单统计
function action_list()
{

    admin_priv('release_balance');

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $time = date('Y-m-d');
    $smarty->assign('lang', $_LANG);

    if ($_POST['start_date']) {
        $start_date = strtotime(date('Ymd', strtotime($_POST['start_date'])));
        $end_date = $start_date+24*3600-1;
    }else{
        $start_date = strtotime(date('Ymd',time()));
        $end_date = $start_date+24*3600-1;;
    }

    $sql = "SELECT SUM(release_balance) FROM `xm_user_account` ";

    $total = $GLOBALS['db']->getOne($sql);

    $sql = "SELECT COUNT(foid) FROM `xm_flow_log` WHERE create_at >= $start_date AND create_at <= $end_date AND type=3 AND status=2";
    $day_num = $GLOBALS['db']->getOne($sql);

    $sql = "SELECT SUM(amount) FROM `xm_flow_log` WHERE create_at >= $start_date AND create_at <= $end_date AND type=3 AND status=2";
    $day_money = $GLOBALS['db']->getOne($sql); 

    if(empty($day_money)) {
        $day_money = 0;
    } 

    /* 查询 */
    $result = get_result_list($start_date, $end_date);


    /* 模板赋值 */
    $smarty->assign('ur_here', "优惠券统计"); // 当前导航

    $smarty->assign('full_page', 1); // 翻页参数
    $smarty->assign('total', $total);
    $smarty->assign('day_num',$day_num);
    $smarty->assign('day_money',$day_money);
    $smarty->assign('time',$time);

    $smarty->assign('recharge_info', $result['item']);
    $smarty->assign('filter', $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count', $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('release_balance.htm');
}


function action_query()
{
    admin_priv('release_balance');

    $smarty = $GLOBALS['smarty'];

   if ($_POST['start_date']) {
        $start_date = strtotime(date('Ymd', strtotime($_POST['start_date'])));
        $end_date = $start_date+24*3600-1;
    }else{
        $start_date = strtotime(date('Ymd',time()));
        $end_date = $start_date+24*3600-1;;
    }

    $sql = "SELECT SUM(release_balance) FROM `xm_user_account` ";

    $total = $GLOBALS['db']->getOne($sql);


    $sql = "SELECT COUNT(foid) FROM `xm_flow_log` WHERE create_at >= $start_date AND create_at <= $end_date AND type=3 AND status=2 ";
    $day_num = $GLOBALS['db']->getOne($sql);

    $sql = "SELECT SUM(amount) FROM `xm_flow_log` WHERE create_at >= $start_date AND create_at <= $end_date AND type=3 AND status=2 ";
    $day_money = $GLOBALS['db']->getOne($sql);

    if(empty($day_money)) {
        $day_money = 0;
    }

    $smarty->assign('total', $total);

    $result = get_result_list($start_date, $end_date);

    // 开始时间
    $smarty->assign('start_date', $_POST['start_date']);
    // 终了时间
    $smarty->assign('end_date', $_POST['end_date']);
    $smarty->assign('day_num',$day_num);
    $smarty->assign('day_money',$day_money);
    $smarty->assign('recharge_info', $result['item']);
    $smarty->assign('filter', $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count', $result['page_count']);

    make_json_result($smarty->fetch('release_balance.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}


/**
 * 获取订单信息列表
 *
 * @return array
 */
function get_result_list($start_date, $end_date)
{

    $filter = array();
    $filter['start_date'] = $start_date;
    $filter['end_date'] = $end_date;
    $filter['page_size'] = 15;
    $filter['page'] = $_POST['page'] ?: 1;
    $filter['record_count'] = $_POST['record_count'] ?: 0;
     $where = " WHERE type=3 AND status=2";
    // 查询条件
    if($start_date){
        $where .= ' AND create_at >=' . $filter['start_date'] . ' AND create_at <=' . $filter['end_date'];
    }else{
         $where .= ' AND 1 = 1';
    }

    $sql = "SELECT COUNT(*) FROM `xm_flow_log` " . $where;

    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;


    /* 分页大小 */
    // $filter = page_and_size($filter);

    $limit = 'LIMIT ' . ($filter['page'] - 1) * $filter['page_size'] . ', ' . $filter['page_size'];

    $sql = "SELECT * FROM `xm_flow_log` $where ORDER BY create_at DESC $limit";

    $list = $GLOBALS['db']->getAll($sql);

    foreach ($list AS $k => $v) {

        $list[$k]['from_user'] = get_user_name($v['user_id']);

        $list[$k]['create_at'] = date('Y-m-d H:i:s', $v['create_at']);
        $list[$k]['update_at'] = $v['update_at'] == 0 ? '' : date('Y-m-d H:i:s', $v['update_at']);
    }

    $filter['start_date'] = date('Y-m-d H:i:s', $start_date);
    $filter['end_date'] = date('Y-m-d H:i:s', $end_date);

    $arr = array(
        'item' => $list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']
    );

    return $arr;
}

// 20180509 1523 V
function get_user_name($uid)
{

    if (!$uid) {
        return '';
    }

    $ret = $GLOBALS['db']->getRow("SELECT user_name FROM `xm_users` WHERE user_id = " . $uid);

    if (!$ret) {
        return '';
    }

    return $ret['user_name'];
}


function new_flow($data)
{

    if (!$data) {
        return false;
    }

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $res = $db->autoExecute($ecs->table('flow_log'), $data, 'INSERT');

}

function update_tps($points, $uid)
{

    if (!$points || !$uid) {
        return;
    }

    $sql = "UPDATE `xm_tps` SET  `surplus` = `surplus` - $points, `shopp` = `shopp` + $points WHERE `user_id` = " . $uid;
    $GLOBALS['db']->query($sql);

}

/*
|- 20180515 1426 V 报单的管理端操作记录
*/
function custom_log($start, $end)
{

    if (!$start || !$end) {
        return false;
    }

    $new = [

        'admin_id' => $_SESSION['admin_id'],
        'start_time' => $start,
        'end_time' => $end,
        'create_at' => time()
    ];

    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('customs_logs'), $new, 'INSERT');
}






function get_admin($mid)
{

    if (!$mid) {
        return '';
    }

    $ret = $GLOBALS['db']->getRow("SELECT user_name FROM `xm_admin_user` WHERE user_id = " . $mid);

    if (!$ret) {
        return '';
    }

    return $ret['user_name'];
}
