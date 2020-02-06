<?php

/**
 * H单直购排名榜明细
 * @author yang
 * @company maqu
 * @create_at 2017-11-4
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require(ROOT_PATH . 'languages/' .$_CFG['lang']. '/admin/order.php');

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';
/* 路由 */
$function_name = 'action_' . $action;
if(! function_exists($function_name))
{
    $function_name = "action_list";
}
call_user_func($function_name);

/**
 * 查看积分转账排名榜明细
 */
function action_list(){

    $smarty = $GLOBALS['smarty'];

    /* 检查权限 */
    admin_priv('mq_account_transfer_stats');

    /* 查询 */
    $result = getDetail();

    /* 模板赋值 */
    $smarty->assign('ur_here', '<a href = "mq_account_transfer_stats.php?act=list">积分转账排行榜</a>-转账明细'); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('datalist',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('lang',  $GLOBALS['_LANG']);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_account_transfer_stats/mq_account_transfer_stats_view_detail.htm');

}

/**
 * 积分转账排名榜查询
 */
function action_query()
{

    $smarty = $GLOBALS['smarty'];

    admin_priv('mq_account_transfer_stats');

    $result = getDetail();

    $smarty->assign('datalist',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('lang',  $GLOBALS['_LANG']);

    /* 排序标记 */
//    $sort_flag  = sort_flag($result['filter']);

    make_json_result($smarty->fetch('mq_account_transfer_stats/mq_account_transfer_stats_view_detail.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/**
 *  H单直购排名榜明细
 *
 * @return array
 */
function getDetail(){

    $result = get_filter();
    if ($result === false)
    {
        $start_time = $_REQUEST['start_time'];
        $end_time =  $_REQUEST['end_time'];
        $user_id =  intval($_REQUEST['user_id']);
        $inout_type = empty($_REQUEST['inout_type']) ? 'out' : trim($_REQUEST['inout_type']);
        $transfer_type = empty($_REQUEST['transfer_type']) ? 'cash' : trim($_REQUEST['transfer_type']);
        if(!in_array($transfer_type,['cash','consume'])){
            $transfer_type = 'cash';
        }

        $filter = array();

        $filter['start_time'] = $start_time;
        $filter['end_time'] = $end_time;
        $filter['user_id'] = $user_id;
        $filter['inout_type'] = $inout_type;
        $filter['transfer_type'] = $transfer_type;

        $start_date = local_strtotime($start_time);
        $end_date = local_strtotime($end_time)+86399;

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        } else {
            $filter['page_size'] = 15;
        }

        if($inout_type =='out'){
            /* 记录总数 */
            $sql = <<<EOF
            SELECT
                COUNT(*)
            FROM
                %s tl
            WHERE
                tl.transfer_type = '%s'
            AND tl.user_id = %u
            AND tl.create_at >= %u
            AND tl.create_at <= %u
EOF;
        } else {
            /* 记录总数 */
            $sql = <<<EOF
            SELECT
                COUNT(*)
            FROM
                %s tl
            WHERE
                tl.transfer_type = '%s'
            AND tl.user_id2 = %u
            AND tl.create_at >= %u
            AND tl.create_at <= %u
EOF;
        }

        $sql = sprintf($sql,$GLOBALS['ecs']->table("mq_transfer_log"),
            $transfer_type,
            $user_id,
            $start_date,
            $end_date);

        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        if($inout_type =='out'){
            $sql = <<<EOF
            SELECT
                tl.user_id,
                u1.user_name,
                tl.create_at,
                tl.user_id2,
                u2.user_name as user_name2,
                tl.money
            FROM
                %s tl
            INNER JOIN %s u1 ON tl.user_id = u1.user_id
            INNER JOIN %s u2 ON tl.user_id2 = u2.user_id
            WHERE
                tl.transfer_type = '%s'
            AND tl.user_id = %u
            AND tl.create_at >= %u
            AND tl.create_at <= %u
            ORDER BY tl.create_at DESC
            LIMIT %u,%u
EOF;
        } else {
            $sql = <<<EOF
            SELECT
                tl.user_id,
                u1.user_name,
                tl.create_at,
                tl.user_id2,
                u2.user_name as user_name2,
                tl.money
            FROM
                %s tl
            INNER JOIN %s u1 ON tl.user_id = u1.user_id
            INNER JOIN %s u2 ON tl.user_id2 = u2.user_id
            WHERE
                tl.transfer_type = '%s'
            AND tl.user_id2 = %u
            AND tl.create_at >= %u
            AND tl.create_at <= %u
            ORDER BY tl.create_at DESC
            LIMIT %u,%u
EOF;
        }

        $sql = sprintf($sql,$GLOBALS['ecs']->table("mq_transfer_log"),
            $GLOBALS['ecs']->table("users"),
            $GLOBALS['ecs']->table("users"),
            $transfer_type,
            $user_id,
            $start_date,
            $end_date,
            ($filter['page'] - 1) * $filter['page_size'],
            $filter['page_size']);

        set_filter($filter, $sql,"?act=list&user_id=$user_id&start_time=$start_time&end_time=$end_time");
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->query($sql);
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $row['create_at_format'] = local_date($GLOBALS['_CFG']['time_format'], $row['create_at']);
        $arr[] = $row;
    }

    $arr = array('result' => $arr, 'filter' => $filter,
        'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;

}