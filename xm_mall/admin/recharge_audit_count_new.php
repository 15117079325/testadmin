<?php

/**
 * 充值排行榜
 *
 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- 充值申请列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
     /* 检查权限 */
     admin_priv('recharge_audit_count');

    /* 查询 */
    $result = recharge_apply_list();
    $smarty->assign('todays',  local_date('Y-m-d',gmtime()));//今日
    $smarty->assign('this_week',  local_date('Y-m-d',gmtime()));//本周
    $smarty->assign('this_month',  local_date('Y-m-d',gmtime()));//本周
    $smarty->assign('this_year',  local_date('Y-m-d',gmtime()));//本周

    /* 模板赋值 */
    $smarty->assign('ur_here', "充值排行榜 "); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('recharge_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('recharge_audit_count_list_new.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    admin_priv('recharge_audit_count');

    $result = recharge_apply_list();
    $smarty->assign('todays',  local_date('Y-m-d',gmtime()));//今日
    $smarty->assign('this_week',  local_date('Y-m-d',gmtime()));//本周
    $smarty->assign('this_month',  local_date('Y-m-d',gmtime()));//本周
    $smarty->assign('this_year',  local_date('Y-m-d',gmtime()));//本周
    $smarty->assign('recharge_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);

    make_json_result($smarty->fetch('recharge_audit_count_list_new.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}






/**
 *  获取充值申请列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function recharge_apply_list()
{
    admin_priv('recharge_audit_count');
    $result = get_filter();
    if ($result === false)
    {
        $filter = array();

        if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }


        $where = "WHERE mra.notes='充值' ";
        $filter['start_time'] = isset($_REQUEST['start_time']) ? trim($_REQUEST['start_time']) : '';
        $filter['end_time'] = isset($_REQUEST['end_time']) ? trim($_REQUEST['end_time']) : '';
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $this_week = $filter['this_week'] = empty($_REQUEST['this_week']) ? '' : trim($_REQUEST['this_week']);
        $todays = $filter['todays'] = empty($_REQUEST['todays']) ? '' : trim($_REQUEST['todays']);
        $this_month = $filter['this_month'] = empty($_REQUEST['this_month']) ? '' : trim($_REQUEST['this_month']);
        $this_year = $filter['this_year']= empty($_REQUEST['this_year']) ? '' : trim($_REQUEST['this_year']);
        if($filter['start_time'] || $filter['end_time']){ //为了防止冲突如果有时间条件则清除快捷查询里的时间条件
            $this_week = $filter['this_week'] = '';
            $todays = $filter['todays'] = '';
            $this_month = $filter['this_month']= '';
            $this_year = $filter['this_year'] = '';
        }
        //用户名
        if($filter['keywords'])
        {
            $where .= " AND ( u.user_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' or u.email like  '%" . mysql_like_quote($filter['keywords']) . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['keywords']) . "%' ) ";
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
        //今日
        if($todays){
            $filter['start_time'] = $todays;
            $filter['end_time'] = $todays;
            $start_time = local_strtotime($filter['start_time'] . ' 00:00:00');
            $end_time = local_strtotime($filter['end_time'] . ' 00:00:00') + 86399;
            $where .= " AND mra.create_at >= '$start_time' AND  mra.create_at <= '$end_time' ";
//            $where .= " AND DATE_FORMAT(FROM_UNIXTIME(mra.create_time),'%Y-%m-%d') = DATE_FORMAT('$todays','%Y-%m-%d') ";
        }
        //本周
        if($this_week ){
            $filter['start_time'] = local_date('Y-m-d', local_strtotime('this week'));
            $filter['end_time'] = local_date('Y-m-d', local_strtotime('last day next week'));
            $start_time = local_strtotime($filter['start_time'] . ' 00:00:00');
            $end_time = local_strtotime($filter['end_time'] . ' 00:00:00') + 86399;
            $where .= " AND mra.create_at >= '$start_time' AND  mra.create_at <= '$end_time' ";

        }
        //本月
        if($this_month){
            $filter['start_time'] = local_date('Y-m-01', local_strtotime(date("Y-m-d")));
            $filter['end_time'] = local_date('Y-m-d', local_strtotime("$filter[start_time] +1 month -1 day"));
            $start_time = local_strtotime($filter['start_time'] . ' 00:00:00');
            $end_time = local_strtotime($filter['end_time'] . ' 00:00:00') + 86399;
            $where .= " AND mra.create_at >= '$start_time' AND  mra.create_at <= '$end_time' ";

        }
        //本年
        if($this_year){
            $filter['start_time'] = $todays;
            $year=local_date("Y",time());
            $filter['start_time'] = $year."-01-01";
            $filter['end_time'] = $year."-12-31";
            $start_time = local_strtotime($filter['start_time'] . ' 00:00:00');
            $end_time = local_strtotime($filter['end_time'] . ' 00:00:00') + 86399;
            $where .= " AND mra.create_at >= '$start_time' AND  mra.create_at <= '$end_time' ";

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
        $sql = "SELECT COUNT(*) FROM ( SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('flow_log') ." AS mra  INNER JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mra.user_id = u.user_id $where  GROUP BY mra.user_id )tmp";
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT SUM(mra.amount) as cash_money ,mra.user_id, u.user_name FROM " . $GLOBALS['ecs']->table("flow_log") . " AS mra ".
                "INNER JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mra.user_id = u.user_id
                $where GROUP BY mra.user_id
                ORDER BY cash_money DESC
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
        $arr[] = $row;
    }
    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}
?>
