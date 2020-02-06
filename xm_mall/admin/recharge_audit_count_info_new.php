<?php

/**
 * 用户充值记录列表

 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- 充值申请列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    /* 查询 */
    $result = recharge_apply_list();
    if($_REQUEST['user_id']){
        $smarty->assign('user_id',$_REQUEST['user_id']);
    }
    /* 模板赋值 */
    $smarty->assign('ur_here', "用户充值记录"); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('recharge_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    /* 显示模板 */
    assign_query_info();
    $smarty->display('recharge_audit_count_info_new.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    $result = recharge_apply_list();
    if($_REQUEST['user_id']){
        $smarty->assign('user_id',$_REQUEST['user_id']);
    }
    $smarty->assign('recharge_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    make_json_result($smarty->fetch('recharge_audit_count_info_new.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}


/**
 *  获取用户充值记录列表
 *
 * @access  public
 * @param
 *
 * @return void
 */
function recharge_apply_list()
{
    admin_priv('recharge_audit_lists');
    $result = get_filter();
    if ($result === false)
    {
        $filter = array();
        $filter['start_time'] = isset($_REQUEST['start_time']) ? trim($_REQUEST['start_time']) : '';
        $filter['end_time'] = isset($_REQUEST['end_time']) ? trim($_REQUEST['end_time']) : '';
        $where = "WHERE mra.notes='充值' ";
        if($_REQUEST['user_id']){
            $filter['user_id'] = $_REQUEST['user_id'];
            $where .= " AND mra.user_id = ".$_REQUEST['user_id'] ;
        }

        if($filter['end_time'] && $filter['start_time'] ){
            if($filter['start_time']>$filter['end_time']){
                make_json_error('结束时间必须大于开始时间！');
            }
        }
        if ($filter['start_time'])
        {
            $start_time=local_strtotime($filter['start_time'].' 00:00:00');
            $where .= " AND mra.create_at >= '$start_time'";
        }
        if ($filter['end_time'])
        {
            $end_time = local_strtotime($filter['end_time'].' 00:00:00')+86399;
            $where .= " AND mra.create_at <= '$end_time'";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 15;
        }

        /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('flow_log') ." AS mra ".
                "INNER JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mra.user_id = u.user_id $where ";
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT mra.*, u.user_name FROM " . $GLOBALS['ecs']->table("flow_log") . " AS mra ".
                "INNER JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mra.user_id = u.user_id
                $where
                ORDER BY mra.create_at  desc
                LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->query($sql);

    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $row['create_time_format'] = local_date($GLOBALS['_CFG']['time_format'], $row['create_at']);
        $row['update_time_format'] = local_date($GLOBALS['_CFG']['time_format'], $row['create_at']);
        $arr[] = $row;
    }

    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}
?>
