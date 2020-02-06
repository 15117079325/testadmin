<?php

/**
 * 积分排行榜
 *
 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- 积分列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list') {
    /* 查询 */
    $result = integral_ranking_list();
    $amount = $GLOBALS['db']->getOne("SELECT amount FROM xm_master_config WHERE code='xm_t_all'");
    /* 模板赋值 */
    $time = empty($_REQUEST['thedate']) ? strtotime(date("Y-m-d"), time()) : strtotime($_REQUEST['thedate']);
    $end_time = $time + 24 * 3600 - 1;
    //每天的支出.每天的收入
    $income = $GLOBALS['db']->getOne("SELECT sum(amount) FROM xm_flow_log WHERE isall=1 AND status=1 AND create_at BETWEEN {$time} AND {$end_time}");
    $expenditure = $GLOBALS['db']->getOne("SELECT sum(amount) FROM xm_flow_log WHERE isall=1 AND status=2 AND create_at BETWEEN {$time} AND {$end_time}");
    $income = empty($income) ? 0 : $income;
    $expenditure = empty($expenditure) ? 0 : $expenditure;
    $time = date("Y-m-d",$time);
    $smarty->assign('thedate', $time);
    $smarty->assign('income', $income);
    $smarty->assign('expenditure', $expenditure);
    $smarty->assign('ur_here', "平台数据流水 "); // 当前导航
    $status = empty($_REQUEST['status']) ? 1 : intval($_REQUEST['status']);
    $smarty->assign('status', $status);
    $smarty->assign('full_page', 1); // 翻页参数
    $smarty->assign('amount', $amount);
    $smarty->assign('result', $result['result']);
    $smarty->assign('filter', $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count', $result['page_count']);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('platform_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {


    $result = integral_ranking_list();
    $smarty->assign('result', $result['result']);
    $smarty->assign('filter', $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count', $result['page_count']);
    /* 排序标记 */
    $sort_flag = sort_flag($result['filter']);
    make_json_result($smarty->fetch('platform_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/**
 *  获取积分列表
 */
function integral_ranking_list()
{

    $result = get_filter();
    if ($result === false) {
        $filter = array();

        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1) {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }
        $status = empty($_REQUEST['status']) ? 1 : intval($_REQUEST['status']);
        $time = empty($_REQUEST['thedate']) ? strtotime(date("Y-m-d"), time()) : strtotime($_REQUEST['thedate']);
        $end_time = $time + 24 * 3600 - 1;
        $where = " WHERE isall=1 AND user_id=0 AND status ={$status} ";
        if ($time) {
            $where .= " AND create_at BETWEEN $time AND $end_time ";
        }
        $filter['status']=$status;
        $filter['thedate']=date('Y-m-d',$time);
        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
            $filter['page_size'] = 20;
        } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
            $filter['page_size'] = 20;
        } else {
            $filter['page_size'] = 20;
        }

        /* 记录总数 */
        $sql = " SELECT COUNT(*) FROM xm_flow_log ".$where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;
        /* 查询 */
        $sql = "SELECT * FROM xm_flow_log {$where} ORDER BY foid DESC" .
            " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";
        set_filter($filter, $sql);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->query($sql);

    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $row['status'] = $row['status'] == 1 ? '收入' : '支出';
        $row['create_at'] = date('Y-m-d H:i:s', $row['create_at']);
        $arr[] = $row;
    }
    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

?>
