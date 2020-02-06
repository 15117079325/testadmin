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
    $ads_list = trade_data();
    $time = empty($_REQUEST['thedate']) ? date("Y-m-d",time()) : $_REQUEST['thedate'];
    $rank = empty($_REQUEST['rank']) ? 4 : intval($_REQUEST['rank']);
    $type = empty($_REQUEST['type']) ? 1 : intval($_REQUEST['type']);
    $smarty->assign('type', $type);
    $smarty->assign('rank', $rank);
    $smarty->assign('thedate', $time);
    $smarty->assign('full_page', 1);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    assign_query_info();
    $smarty->display('home/trade_data_list.htm');
}

function action_query()
{
    $smarty = $GLOBALS['smarty'];
    $ads_list = trade_data();
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);


    make_json_result($smarty->fetch('home/trade_data_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}




function trade_data()
{
    $filter = array();
    $time = empty($_REQUEST['thedate']) ? strtotime(date("Y-m-d"), time()) : strtotime($_REQUEST['thedate']);
    $rank = empty($_REQUEST['rank']) ? 4 : intval($_REQUEST['rank']);
    $type = empty($_REQUEST['type']) ? 1 : intval($_REQUEST['type']);
    $filter['rank'] = $rank;
    $filter['type'] = $type;
    $end_time = $time + 24 * 3600 - 1;
    $where = " WHERE tr.user_cx_rank={$rank} AND tr.type={$type} ";
    if ($time) {
        $where .= " AND tr.create_time BETWEEN $time AND $end_time ";
    }
    $filter['thedate'] = date('Y-m-d',$time);
    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM xm_trade_team_rank as tr ' . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT tr.*,u.user_name ' .
        'FROM ' . $GLOBALS['ecs']->table('trade_team_rank') . 'AS tr LEFT JOIN xm_users u ON tr.user_id=u.user_id ' . $where .
        'ORDER by tr.money DESC ';
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);
    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        switch ($rows['user_cx_rank']) {
            case 4 :
                $rows['rank'] = '30w';
                break;
            case 3:
                $rows['rank'] = '10w';
                break;
            case 2:
                $row['rank'] = '3w';
                break;
        }

        $arr[] = $rows;
    }
    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}












