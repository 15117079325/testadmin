<?php

/**
 * ECSHOP用户积分明细
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: account_log.php 17217 2011-01-19 06:29:08Z liubo $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
include_once(ROOT_PATH . 'includes/lib_order.php');

/*------------------------------------------------------ */
//-- 积分明细列表
/*------------------------------------------------------ */   
if ($_REQUEST['act'] == 'list')
{

    /*end*/
    if($_REQUEST['start_time']){
        $start_time = $_REQUEST['start_time'];
    }else{
       $start_time =  local_date('Y-m-d',gmtime());
    }
    if($_REQUEST['end_time']) {
        $end_time = $_REQUEST['end_time'];
    }else{
        $end_time =  local_date('Y-m-d',gmtime());
    }
    $account_list = get_accountlist();
    $smarty->assign('start_time',$start_time );
    $smarty->assign('end_time',$end_time );
    $smarty->assign('account_type',trim($_REQUEST['account_type']) );
    $smarty->assign('ur_here', '积分明细');
    $smarty->assign('full_page',    1);
    $smarty->assign('account_list',  $account_list['result']);
    $smarty->assign('filter',       $account_list['filter']);
    $smarty->assign('record_count', $account_list['record_count']);
    $smarty->assign('page_count',   $account_list['page_count']);
    $smarty->assign('count_money',   $account_list['count_money']);

    assign_query_info();
    $smarty->display('integration_details.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    /* 检查参数 */
    $account_list = get_accountlist();

    $smarty->assign('account_list',  $account_list['result']);
    $smarty->assign('filter',       $account_list['filter']);
    $smarty->assign('record_count', $account_list['record_count']);
    $smarty->assign('page_count',   $account_list['page_count']);
    $smarty->assign('count_money',   $account_list['count_money']);
    //var_dump($account_list['count_money']);die();
    make_json_result($smarty->fetch('integration_details.htm'), '',
        array('filter' => $account_list['filter'], 'page_count' => $account_list['page_count']));
}
/**
 * 取得帐户明细
 * @param   int     $user_id    用户id
 * @param   string  $account_type   帐户类型：空表示所有帐户，user_money表示可用资金，
 *                  frozen_money表示冻结资金，rank_points表示等级积分，pay_points表示消费积分
 * @return  array
 */
function get_accountlist()
{
    /* 检查参数 */
    $result = get_filter();
    if ($result === false)
    {

//        $json = new JSON;
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;
//        $result = array('error' => 0, 'content' => 'dddd');
//        die($json->encode($result));
        /* 过滤信息 */
        $filter['sort_by'] = 'mal.change_time';
        $filter['sort_order'] =  'DESC';

//        $where = " WHERE 1 ";
        $where = " WHERE account_id !='D6CA2D5A-33F4-5163-B04C-38E52975194C' ";

        $filter['start_time'] = isset($_REQUEST['start_time']) ? trim($_REQUEST['start_time']) : '';
        $filter['end_time'] = isset($_REQUEST['end_time']) ? trim($_REQUEST['end_time']) : '';
        $filter['account_type'] = isset($_REQUEST['account_type']) ? trim($_REQUEST['account_type']) : '';
        $filter['income_type'] = isset($_REQUEST['income_type']) ? trim($_REQUEST['income_type']) : '';
        $filter['change_type'] = isset($_REQUEST['change_type']) ? trim($_REQUEST['change_type']) : '';
        $filter['keyword'] = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }

        if(empty($filter['start_time'] ) && empty($filter['end_time'])){
            $start_time=strtotime(local_date('Y-m-d',gmtime()).'00:00:00');
            $where .= " AND mal.change_time >= '$start_time'";
            $end_time=strtotime(local_date('Y-m-d',gmtime()))+86399;
            $where .= " AND mal.change_time <= '$end_time'";
        }else{
            if($filter['start_time'] && empty($filter['end_time']) ){
                $check_month = strtotime("+1 months", strtotime($filter['start_time']));
                if($check_month<time()){
                    make_json_error('您指定的日期范围不能超过一个月，请填写结束时间！');
                }
            }
            if($filter['end_time'] && empty($filter['start_time']) ){
                $check_month = strtotime("-1 months", strtotime($filter['end_time']));
                if($check_month<time()){
                    make_json_error('您指定的日期范围不能超过一个月，请填写开始时间！');
                }
            }
            if($filter['end_time'] && $filter['start_time'] ){
                if($filter['start_time']>$filter['end_time']){
                    make_json_error('结束时间必须大于开始时间！');
                }
                $check_emonth = local_date('Y-m-d',strtotime("-1 months", strtotime($filter['end_time'])));
                $check_smonth = local_date('Y-m-d',strtotime("-1 months", strtotime($filter['start_time'])));
                if($check_emonth>$filter['start_time'] || $check_smonth>$filter['end_time']){
                    make_json_error('您指定的日期范围不能超过一个月！');
                }
            }
            if ($filter['start_time'])
            {
                $start_time=local_strtotime($filter['start_time'].'00:00:00');
                $where .= " AND mal.change_time >= '$start_time'";
            }
            if ($filter['end_time'])
            {
                $end_time=local_strtotime($filter['end_time'].'00:00:00')+86399;
                $where .= " AND mal.change_time <= '$end_time'";
            }
        }
        if ($filter['account_type']!='')
        {
            $account_type=$filter['account_type'];
            $where .= " AND mal.account_type = '$account_type' ";
        }
        if ($filter['income_type']!='')
        {
            $income_type=$filter['income_type'];
            $where .= " AND mal.income_type = '$income_type' ";
        }
        if ($filter['change_type']!='')
        {
            $change_type=$filter['change_type'];
            $where .= " AND mal.change_type = '$change_type' ";
        }
//        if($filter['keyword'])
//        {
//            $where .= " AND ( u.user_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%' or u.email like  '%" . mysql_like_quote($filter['keyword']) . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['keyword']) . "%' ) ";
//        }

        /* 记录总数 */
//        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " .
//               " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON mal.account_id = mue.account_id " .
//               " LEFT JOIN " . $GLOBALS['ecs']->table('users') . " AS u ON mue.user_id = u.user_id " . $where;
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " . $where;
        $list_where = $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);
        $filter = page_and_size($filter);
        if($filter['change_type']!='' && $filter['change_type'] == '12'){
            $where .=" AND change_type !=13";
        }else{
            $where .=" AND change_type!= 12 AND change_type !=13";
        }
//        $count_sql = "SELECT ifnull(SUM(case income_type when '+' then change_money end),0) as income_money,ifnull(SUM(case income_type when '-' then change_money end),0) as decome_money  FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " .
//            " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON mal.account_id = mue.account_id " .
//            " LEFT JOIN " . $GLOBALS['ecs']->table('users') . " AS u ON mue.user_id = u.user_id " . $where;

        $count_sql = <<<EOF
SELECT
	ifnull(
		SUM(
			CASE income_type
			WHEN '+' THEN
				change_money
			END
		),
		0
	) AS income_money,
	ifnull(
		SUM(
			CASE income_type
			WHEN '-' THEN
				change_money
			END
		),
		0
	) AS decome_money
FROM
	%s AS mal
	%s
EOF;
        $count_sql = sprintf($count_sql,$GLOBALS['ecs']->table('mq_account_log'),$where);

        $count = $GLOBALS['db']->getrow($count_sql);
        $filter['count_money'] = $count['income_money'] - $count['decome_money'];

        /* 查询 */
//        $sql =  " SELECT  u.user_name, mal.account_id,mal.change_time, mal.change_desc, mal.income_type, mal.change_money, mal.money, mal.account_type " .
//                " FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " .
//                " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON mal.account_id = mue.account_id " .
//                " LEFT JOIN " . $GLOBALS['ecs']->table('users') . " AS u ON mue.user_id = u.user_id " . $where.
//                " ORDER BY mal.change_time desc LIMIT " . $filter['start'] . "," . $filter['page_size']." " ;

        $sql = <<<EOF
    SELECT
        u.user_name,
        sub.*
    FROM
        (
        SELECT
            mal.account_id,
            mal.change_time,
            mal.change_desc,
            mal.income_type,
            mal.change_money,
            mal.money,
            mal.account_type,
            mal.log_id
        FROM
            %s AS mal
        %s
        ORDER BY mal.change_time desc ,mal.log_id desc LIMIT %u,%u
        ) sub
        INNER JOIN %s AS mue ON sub.account_id = mue.account_id
        INNER JOIN %s AS u ON mue.user_id = u.user_id
        ORDER BY sub.change_time desc ,sub.log_id desc
EOF;

        $sql = sprintf($sql,$GLOBALS['ecs']->table('mq_account_log'),$list_where, $filter['start'],$filter['page_size'],
            $GLOBALS['ecs']->table('mq_users_extra'),$GLOBALS['ecs']->table('users'));

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
        $row['change_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['change_time']);
        $arr[] = $row;
    }

    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'],'count_money'=>  $filter['count_money']);

    return $arr;
}

?>
