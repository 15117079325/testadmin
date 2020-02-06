<?php

/*
|- 20180521 1554 V 数据统计
*/


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/admin/statistic.php');

// act操作项的初始化
if (empty($_REQUEST['act'])) {

    $_REQUEST['act'] = 'main';

} else {

    $_REQUEST['act'] = trim($_REQUEST['act']);
}

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'main';
/* 路由 */
$function_name = 'action_' . $action;

if (!function_exists($function_name)) {

    $function_name = "action_main";
}

call_user_func($function_name);

function action_list()
{
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty->assign('lang', $_LANG);
    $ads_list = performance_data();
    $time = empty($_REQUEST['thedate']) ? date("Y-m-d",time()) : $_REQUEST['thedate'];
    $user_name = empty($_REQUEST['user_name']) ? '' : $_REQUEST['user_name'];
    $smarty->assign('user_name', $user_name);
    $smarty->assign('thedate', $time);
    $smarty->assign('full_page', 1);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    assign_query_info();
    $smarty->display('home/person_performance_list.htm');
}

function action_query()
{
    $smarty = $GLOBALS['smarty'];
    $ads_list = performance_data();
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);


    make_json_result($smarty->fetch('home/person_performance_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}


function performance_data()
{
    $filter = array();
    $time = empty($_REQUEST['thedate']) ? strtotime(date("Y-m-d"), time()) : strtotime($_REQUEST['thedate']);
    $user_name = empty($_REQUEST['user_name']) ? '' : $_REQUEST['user_name'];
    $end_time = $time + 24 * 3600 - 1;
    $where = " WHERE pd_gmt_create BETWEEN $time AND  $end_time ";
    if($user_name){
        $user_id = $GLOBALS['db']->getOne("SELECT user_id FROM xm_users WHERE user_name='{$user_name}'");
        $user_id = empty($user_id) ? 0 : $user_id;
        $where .= " AND tr.user_id={$user_id} ";
    }
    $filter['thedate'] = date('Y-m-d',$time);
    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM  xm_trade_performance_data as tr ' . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    $arr = array();
    $sql = 'SELECT tr.user_id,tr.pd_money as money ,tr.pd_gmt_create,u.user_name ' .
        'FROM ' . $GLOBALS['ecs']->table('trade_performance_data') . 'AS tr LEFT JOIN xm_users u ON tr.user_id=u.user_id ' . $where .
        ' ORDER by money DESC ';
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);
    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        $rows['time'] = date("Y-m-d H:i:s",$rows['pd_gmt_create']);
        $arr[] = $rows;
    }
    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}

function action_detail()
{
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty->assign('lang', $_LANG);
    $ads_list = detail_data();

    $time = empty($_REQUEST['thedate']) ? date("Y-m-d",time()) : $_REQUEST['thedate'];
    $user_id = empty($_REQUEST['user_id']) ? '' : $_REQUEST['user_id'];
    $smarty->assign('user_id', $user_id);
    $smarty->assign('thedate', $time);
    $smarty->assign('full_page', 1);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    assign_query_info();
    $smarty->display('home/person_performance_detail.htm');
}

function action_detail_query()
{
    $smarty = $GLOBALS['smarty'];
    $ads_list = detail_data();
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    make_json_result($smarty->fetch('home/person_performance_detail.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

function detail_data()
{
    $filter = array();
    $time = empty($_REQUEST['thedate']) ? strtotime(date("Y-m-d"), time()) : strtotime($_REQUEST['thedate']);
    $user_id = empty($_REQUEST['user_id']) ? '' : $_REQUEST['user_id'];
    $end_time = $time + 24 * 3600 - 1;
    $where = " WHERE tr.tp_gmt_create BETWEEN $time AND  $end_time AND FIND_IN_SET($user_id, tp_top_user_ids) ";
    $filter['thedate'] = date('Y-m-d',$time);
    $filter['user_id'] = $user_id;
    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM  xm_trade_performance as tr ' . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    $arr = array();
    $sql = 'SELECT tr.user_id,tr.tp_num as money ,tr.tp_gmt_create,tr.user_name ' .
        'FROM ' . $GLOBALS['ecs']->table('trade_performance') . 'AS tr' . $where .
        ' ORDER by money DESC ';
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);
    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        $rows['time'] = date("Y-m-d H:i:s",$rows['tp_gmt_create']);
        $arr[] = $rows;
    }
    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}












