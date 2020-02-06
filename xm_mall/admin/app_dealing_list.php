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

// -----------------------------------------------------------------------------


function action_list()
{
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty->assign('lang', $_LANG);
    $ads_list = get_deallist();
    $smarty->assign('full_page', 1);
    $keywords = empty($_REQUEST['keywords']) ? '' : $_REQUEST['keywords'];
    $smarty->assign('keywords', $keywords);
    //交易中总额
    $total_money = $GLOBALS['db']->getOne("SELECT SUM(trade_num) FROM xm_trade WHERE trade_status=1 AND trade_type=1");
    $smarty->assign('total_money', $total_money);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    assign_query_info();
    $smarty->display('home/dealing_list.htm');
}

function action_query()
{
    $smarty = $GLOBALS['smarty'];
    $ads_list = get_deallist();
    $keywords = empty($_REQUEST['keywords']) ? '' : $_REQUEST['keywords'];
    $smarty->assign('keywords', $keywords);
     //交易中总额
    $total_money = $GLOBALS['db']->getOne("SELECT SUM(trade_num) FROM xm_trade WHERE trade_status=1 AND trade_type=1");
    $smarty->assign('total_money', $total_money);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);


    make_json_result($smarty->fetch('home/dealing_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}


function get_deallist()
{
    $filter = array();

    $keywords = empty($_REQUEST['keywords']) ? '' : rtrim($_REQUEST['keywords']);
    $filter['keywords'] = $keywords;
    $where = " WHERE ad.trade_status = 1 AND trade_type=1 ";
    if ($keywords) {
        $user_id = $GLOBALS['db']->getOne("SELECT user_id FROM xm_users WHERE user_name='{$keywords}'");
        $user_id =empty($user_id) ? 0 : $user_id;
        $where .= " AND ad.user_id={$user_id} ";
    }

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM xm_trade as ad ' . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.*,u.user_name ' .
        'FROM ' . $GLOBALS['ecs']->table('trade') . 'AS ad LEFT JOIN xm_users u ON ad.user_id=u.user_id ' . $where .
        'ORDER by ad.trade_id DESC ';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        $rows['trade_gmt_create'] = date("Y-m-d H:i:s", $rows['trade_gmt_create']);
        switch ($rows['trade_status']) {
            case 0 :
                $rows['status'] = '出售中';
                break;
            case 1:
                $rows['status'] = '交易中';
                break;
            case 2:
                $row['status'] = '待确认';
                break;
            case 3:
                $row['status'] = '完成交易';
                break;
            case 4:
                $row['status'] = '取消';
                break;
            case 5:
                $row['status'] = '有误';
                break;
            case 6:
                $row['status'] = '确认有误';
                break;
        }

        $arr[] = $rows;
    }
    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}











