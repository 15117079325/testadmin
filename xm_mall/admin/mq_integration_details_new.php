<?php


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
    $smarty->display('integration_details_new.htm');
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
    make_json_result($smarty->fetch('integration_details_new.htm'), '',
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
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;
        /* 过滤信息 */
        $filter['sort_by'] = 'mal.change_time';
        $filter['sort_order'] =  'DESC';

        $where = " WHERE 1 ";

        $filter['start_time'] = isset($_REQUEST['start_time']) ? trim($_REQUEST['start_time']) : '';

        $filter['keyword'] = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);

        if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }
         if ($filter['keyword']) {
            $where .= " AND ( u.user_name LIKE '%" . mysql_like_quote($filter['keyword']) . "%' or u.email like  '%" . mysql_like_quote($filter['keyword']) . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['keyword']) . "%' ) ";
        }
        if(intval($_REQUEST['user_id'])){
            $where .= " AND u.user_id={$_REQUEST['user_id']} ";
        }
        if(empty($filter['start_time'] )){
            $start_time=strtotime(local_date('Y-m-d',gmtime()).'00:00:00');

            $where .= " AND mal.create_at >= '$start_time'";
        }else{
            if ($filter['start_time'])
            {
                $start_time=strtotime($filter['start_time']);
                $end_time = $start_time + 86399;
                $where .= " AND mal.create_at between {$start_time} AND {$end_time} ";
            }
        }

         /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        } else {
            $filter['page_size'] = 15;
        }

          /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('flow_log') ." AS mal  INNER JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mal.user_id = u.user_id $where";

        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT mal.notes,mal.type,mal.status,mal.amount,mal.create_at, u.user_name FROM " . $GLOBALS['ecs']->table("flow_log") . " AS mal ".
                "INNER JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mal.user_id = u.user_id
                $where
                ORDER BY mal.create_at DESC
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
        switch ($row['type']){
            case 1:
                if($row['status']==1){
                    $row['money'] = '现金 + '.$row['amount'];
                }else{
                    $row['money'] = '现金 - '.$row['amount'];
                }
                break;
            case 2:
                if($row['status']==1){
                    $row['money'] = '优惠券 + '.$row['amount'];
                }else{
                    $row['money'] = '优惠券 - '.$row['amount'];
                }
                break;
            case 3:
                if($row['status']==1){
                    $row['money'] = '待释放优惠券 + '.$row['amount'];
                }else{
                    $row['money'] = '待释放优惠券 - '.$row['amount'];
                }
                break;
        }
        $row['change_time'] = date('Y-m-d H:i:s',$row['create_at']);
        $arr[] = $row;
    }

    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'],'count_money'=>  $filter['count_money']);

    return $arr;
}

?>
