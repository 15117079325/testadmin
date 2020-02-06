<?php

/**
 * 积分排行榜
 *
 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- 充值申请列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{


    /* 查询 */
    $result = integral_ranking_list();
    /* 模板赋值 */
    $smarty->assign('ur_here', "积分排行榜 "); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('result',    $result['result']);
    $smarty->assign('rank_type',    empty($_REQUEST['rank_type']) ? 1 : trim($_REQUEST['rank_type']));
    $smarty->assign('top_user_id',    empty($_REQUEST['top_user_id']) ? 0 : trim($_REQUEST['top_user_id']));
    $smarty->assign('rank_level',    empty($_REQUEST['rank_level']) ? 4 : trim($_REQUEST['rank_level']));
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('integral_ranking_other_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{


    $result = integral_ranking_list();
    $smarty->assign('result',    $result['result']);
     $smarty->assign('rank_type',    empty($_REQUEST['rank_type']) ? 1 : trim($_REQUEST['rank_type']));
    $smarty->assign('top_user_id',    empty($_REQUEST['top_user_id']) ? 0 : trim($_REQUEST['top_user_id']));
    $smarty->assign('rank_level',    empty($_REQUEST['rank_level']) ? 4 : trim($_REQUEST['rank_level']));
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);

    make_json_result($smarty->fetch('integral_ranking_new_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}elseif($_REQUEST['act'] == 'cash_list'){

}elseif($_REQUEST['act'] == 'rechange_list'){

}






/**
 *  获取提现申请列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function integral_ranking_list()
{

    $result = get_filter();
    if ($result === false)
    {
        $filter = array();

        if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }

//        /* 过滤信息 */
        $where = 'WHERE 1 ';
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $top_user_id = empty($_REQUEST['top_user_id']) ? 0 : trim($_REQUEST['top_user_id']);
        $rank_level = empty($_REQUEST['rank_level']) ? 4 : trim($_REQUEST['rank_level']);
        $rank_type = empty($_REQUEST['rank_type']) ? 1 : trim($_REQUEST['rank_type']);
        $account_type = empty($_REQUEST['account_type']) ? 1 : trim($_REQUEST['account_type']);
        //用户名
        if($filter['keywords'])
        {
            $where .= " AND ( u.user_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' or u.email like  '%" . mysql_like_quote($filter['keywords']) . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['keywords']) . "%' ) ";
        }
        $time = strtotime(date('Y-m-d',time()));
        if($rank_level==3){
            //查出她下面的所有10万服务商
            if($account_type==2){
                $sql = "SELECT user_id FROM xm_other_rank WHERE top_user_id={$top_user_id} AND create_time>{$time}";
            $user_ids = $GLOBALS['db']->getAll($sql);
            $str = "(";
            foreach($user_ids as $k=>$v){
                $str .= "{$v['user_id']},";
            }
            $str .="{$top_user_id})";
            $where .= " AND r.user_cx_rank=2 AND r.top_user_id IN {$str} AND r.type={$rank_type} AND r.create_time>{$time} ";
            }else{
                $where .= " AND r.user_cx_rank={$rank_level} AND r.top_user_id={$top_user_id} AND r.type={$rank_type} AND r.create_time>{$time} ";
            }
        }else{
            $where .= " AND r.user_cx_rank={$rank_level} AND r.top_user_id={$top_user_id} AND r.type={$rank_type} AND r.create_time>{$time} ";
        }


        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = 50;
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = 50;
        }
        else
        {
            $filter['page_size'] = 50;
        }
        $table = $GLOBALS['ecs']->table('other_rank') ." AS r ON u.user_id=r.user_id ";
        /* 记录总数 */
        $sql = " SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('users') ." AS u ". "LEFT JOIN " . $table.$where;
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;
        /* 查询 */
        $sql = "SELECT r.money AS money,r.user_cx_rank,u.user_id, u.user_name FROM " . $GLOBALS['ecs']->table("users") . " AS u ".
            "LEFT JOIN " . $table.$where." ORDER BY money DESC".
            " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";
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
        if($row['user_cx_rank']==4){
            $row['rank_name'] = "30w";
        }elseif($row['user_cx_rank']==3){
            $row['rank_name'] = "10w";
        }else{
            $row['rank_name'] = "3w";
        }
        if($rank_type==1){
            $row['other_money'] = $GLOBALS['db']->getOne("SELECT money FROM xm_other_rank WHERE user_id={$row['user_id']} AND `type`=2 AND create_time>{$time}");
        }elseif($rank_type==2){
            $row['other_money'] = $GLOBALS['db']->getOne("SELECT money FROM xm_other_rank WHERE user_id={$row['user_id']} AND `type`=1 AND create_time>{$time}");
        }else{
            $row['other_money'] = 0;
        }
        $row['user_cx_rank'] = $row['user_cx_rank']-1;
        $arr[] = $row;
    }
    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}
?>
