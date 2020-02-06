<?php

/**
 * 复投记录管理
 * Author: Shenglin
 * E-mail: shengl@maqu.im
 * CreatedTime: 2017/2/7 11:05
 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- 投资记录
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
     /* 检查权限 */
     admin_priv('xinmei_manage');

    /* 查询 */
    /*新增查询 by ymm 2017-2-24 20:29*/
    $result = re_invest_list();
    $last_release_date = get_last_release_date();
   foreach($result['result'] as $k=>$v){
        $sql_sum = $GLOBALS['db']->getRow("SELECT sum(total_spend) as total_spend_all_s FROM ".$GLOBALS['ecs']->table('mq_users_extra')." WHERE layer LIKE '" . $v['layer'].'%%' ."' order by invite_user_id ");
        foreach ($sql_sum as $kk=>$vv) {
            if ($vv == '') {
            $result['result'][$k]['total_spend_all_s'] = 0;
            } else {
            }
              $result['result'][$k]['total_spend_all_s'] = $vv;
        }
    }

    /* 模板赋值 */
    $smarty->assign('ur_here', "我的团队列表"); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('last_release_date',$last_release_date);
    $smarty->assign('bonus_List',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('my_team_list.htm');


}
/*-----------------------------------------------------*/
/*==业绩搜索页面*/
elseif($_REQUEST['act'] == 'search'){
    $smarty->assign('ur_here', "我的团队搜索"); // 当前导航
    $smarty->assign('search_date',1);
    $smarty->assign('user_id', $_GET['user_id']);
    $smarty->display('my_team_list.htm');
}
/*-----------------------------------------------------*/
/*-----------------------------------------------------*/
/*==推荐人详细页*/
/*-----------------------------------------------------*/
elseif($_REQUEST['act'] == 'info')
{
//    $server = new \maqu\Services\MyTeamService();
//    $server->myTeamCount(0,0);
    $user_id = $_GET['user_id'];
    $result = re_invest_list_info($user_id);
    $last_release_date = get_last_release_date();
    $sum=$result['sum'];
    foreach ($result['result'] as $k=>$v){
        $result['result'][$k]['reg_time'] = date('Y-m-d',$v['reg_time']);
        $result['result'][$k]['sum_zhitui_rate']=sprintf("%.2f", $result['result'][$k]['sum_zhitui']/$sum)*100;
    }
    /* 模板赋值 */

    $user_name = $GLOBALS['db']->getOne("select user_name from xm_users where user_id=$user_id");

    $smarty->assign('ur_here', $user_name."的团队 直推总业绩： ". $result['sum']." 团队总业绩： ". $result['layer_sum']); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('user_id',$user_id);
    $smarty->assign('stop',$result['stop']);
    $smarty->assign('last_release_date',$last_release_date);
    $smarty->assign('bonus_List',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');
    $smarty->assign('sum', $result['sum']);
    $smarty->assign('layer_sum', $result['layer_sum']);
    /* 显示模板 */
    assign_query_info();
    $smarty->display('my_team_list.htm');

}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('xinmei_manage');
    $user_id=$_REQUEST['user_id'];

    $result = re_invest_list_info($user_id);
    $sum=$result['sum'];
    foreach ($result['result'] as $k=>$v){
        $result['result'][$k]['reg_time'] = date('Y-m-d',$v['reg_time']);
        $result['result'][$k]['sum_zhitui_rate']=sprintf("%.2f", $result['result'][$k]['sum_zhitui']/$sum)*100;
    }
    $user_name = $GLOBALS['db']->getOne("select user_name from xm_users where user_id=$user_id");

    $smarty->assign('ur_here', $user_name."的团队 直推总业绩： ". $result['sum']." 团队总业绩： ". $result['layer_sum']); // 当前导航
    $smarty->assign('user_id',  $_REQUEST['user_id']);
    $smarty->assign('bonus_List',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sum', $result['sum']);
    $smarty->assign('layer_sum', $result['layer_sum']);
   /* echo "<pre>";
    print_r($result['result']);*/

    /* 排序标记 */
/*    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);*/

    make_json_result($smarty->fetch('my_team_list.htm'), '',
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
function re_invest_list($user_id)
{
    $result = get_filter();
    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
/*      $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'update_at' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);*/
        $where = empty($user_id)? 'WHERE 1 ':'where ue.user_id = '.$user_id;
        //当月数据
        $thismonth = date('m');
        $thisyear = date('Y');
        $startDay = $thisyear . '-' . $thismonth . '-1';
        $endDay = $thisyear . '-' . $thismonth . '-' . date('t', strtotime($startDay));
        $b_time  = strtotime($startDay);//当前月的月初时间戳
        $e_time  = strtotime($endDay);//当前月的月末时间戳
        if($_REQUEST['preparation'] == 1){
            $where .= "AND ii.shipping_time between ".$b_time." AND ".$e_time;
        }
        $filter['user_name'] = isset($_REQUEST['user_name']) ? trim($_REQUEST['user_name']) : '';
        $filter['type'] = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : 0;
        /*add 日期搜索 ymm 2017-3-1 13:41 */
        $filter['add_time1'] = isset($_REQUEST['add_time1']) ? strtotime(trim($_REQUEST['add_time1'])) : '';
        $filter['add_time2'] = isset($_REQUEST['add_time2']) ? strtotime(trim($_REQUEST['add_time2'])) : '';
       /*endsa*/
/*        $filter['invest_status'] = isset($_REQUEST['invest_status']) ? intval($_REQUEST['invest_status']) : 0;*/
      if($filter['add_time1'] != '' && $filter['add_time2'] != '' )
        {
            $where .= " AND ii.shipping_time >= '".mysql_like_quote($filter['add_time1'])."' AND ii.shipping_time <=  '".mysql_like_quote($filter['add_time2'])."' ";
        }

        if ($filter['user_name'])
        {
            $where .= " AND u.user_name LIKE '%" . mysql_like_quote($filter['user_name']) . "%'";
        }
        if($filter['type'] != -1 && $filter['type'] != 0 ){
            $where .= " AND ii.barter_type =".$filter['type'];
        }
      /*  if ($filter['invest_status'])
        {
            $where .= " AND mci.invest_status = '$filter[invest_status]'";
        }*/

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
        $sql =  "SELECT COUNT(DISTINCT u.user_id ) from xm_mq_users_extra as ue LEFT JOIN xm_users as u ON ue.user_id = u.user_id  ".$where;

        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;
        /*查询*/
        $sql = "SELECT  ue.layer ,ma.*, ue.*, sum(ue.total_spend)  ,  u.* from xm_mq_users_extra as ue LEFT JOIN xm_users as u ON ue.invite_user_id = u.user_id
         LEFT JOIN xm_mq_account AS ma ON ue.account_id = ma.account_id AND ma.account_type ='cash' 
         ". $where  ."  group by ue.invite_user_id LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . "";
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

function get_last_release_date(){
    $sql = "SELECT last_time FROM " . $GLOBALS['ecs']->table('mq_credit_invest_timeline') ." ORDER BY id DESC LIMIT 1  ";
    $last_release_date   = $GLOBALS['db']->getOne($sql);
    return local_date('Y-m-d', $last_release_date);
}

function re_invest_list_info($user_id=1)
{
    $result = get_filter();

    if ($result === false) {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;
        //当月数据
//        $thismonth = date('m');
//        $thisyear = date('Y');
//        $startDay = $thisyear . '-' . $thismonth . '-1';
//        $endDay = $thisyear . '-' . $thismonth . '-' . date('t', strtotime($startDay));
//        $b_time = strtotime($startDay);//当前月的月初时间戳
//        $e_time = strtotime($endDay);//当前月的月末时间戳
        $where_time = '  ';
        //var_dump($_REQUEST['add_time1']);
        $filter['user_id']=$user_id;
        $filter['add_time1'] = isset($_REQUEST['add_time1']) ? strtotime(trim($_REQUEST['add_time1'])) : '';
        $filter['add_time2'] = isset($_REQUEST['add_time2']) ? strtotime(trim($_REQUEST['add_time2'])) : '';
//        var_dump( $filter['add_time1']);
//        var_dump( $filter['add_time2']);
        if ($filter['add_time1'] != '' && $filter['add_time2'] != '') {
            $where_time .= " AND l.create_at >= '" . mysql_like_quote($filter['add_time1']) . "' AND l.create_at <=  '" . mysql_like_quote($filter['add_time2']) . "' ";
        }
        else  if ($filter['add_time1'] != ''){
            $where_time .= " AND l.create_at >= '" . mysql_like_quote($filter['add_time1']) ;
        }
        else  if ($filter['add_time2'] != ''){
            $where_time .= " AND l.create_at <= '" . mysql_like_quote($filter['add_time2']) ;
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
        /*查询*/

        $sql_count ='SELECT u.user_id,u.user_name,u.reg_time,(select ifnull(sum(sum),0) from xm_mq_pre_recharge_log l where l.user_name2  = u.user_name '.$where_time.'  ) as '.
            ' sum_zhitui,'.
//            '(select ifnull(sum(sum),0) from xm_mq_pre_recharge_log l where l.user_name2 in '.
//            ' ( '.
//            ' select user_name from xm_users '.
//            ' where user_id in '.
//            ' (SELECT user_id from xm_mq_users_extra where invite_user_id=u.user_id ) '.
//            ' ) '. $where_time.
//            ' ) as sum_tuandui  '.
            '(select ifnull(sum(sum),0) AS sum_tuandui '.
            ' from xm_mq_pre_recharge_log l '.
            ' where   l.user_name2  '.
            ' IN( '.
            '		select  user_name from xm_users a LEFT JOIN xm_mq_users_extra b on a.user_id=b.user_id '.
            "    where layer like concat((select layer from xm_mq_users_extra where user_id =u.user_id),'%')  and a.user_id<>u.user_id".
            ')' .$where_time.
            ' ) as sum_tuandui  '.
            'FROM xm_users AS u '.
            ' LEFT JOIN xm_mq_users_extra AS mue ON u.user_id = mue.user_id '.
            " where u.user_id in(SELECT user_id from xm_mq_users_extra where invite_user_id =$user_id)  ";

            $sql =$sql_count .' LIMIT '. ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";


            $sql_sum ='SELECT ifnull(sum((select ifnull(sum(sum),0) from xm_mq_pre_recharge_log l where l.user_name2  = u.user_name  '.$where_time.' )),0) as '.
                ' sum_zhitui '.
                'FROM xm_users AS u '.
                ' LEFT JOIN xm_mq_users_extra AS mue ON u.user_id = mue.user_id '.
                " where u.user_id in(SELECT user_id from xm_mq_users_extra where invite_user_id =$user_id) ";

        $sql_layer_sum ='select ifnull(sum(sum),0) AS sum_tuandui '.
            ' from xm_mq_pre_recharge_log l '.
            ' where   l.user_name2  '.
            ' IN( '.
            '		select  user_name from xm_users a LEFT JOIN xm_mq_users_extra b on a.user_id=b.user_id '.
            "    where layer like concat((select layer from xm_mq_users_extra where user_id =$user_id),'%')  and a.user_id<>$user_id".
            ')' .$where_time;

        $sql_temp='select count(*) FROM xm_users AS u '.
            ' LEFT JOIN xm_mq_users_extra AS mue ON u.user_id = mue.user_id '.
            " where u.user_id in(SELECT user_id from xm_mq_users_extra where invite_user_id =$user_id)  ";
            $filter['record_count'] =  $GLOBALS['db']->getOne($sql_temp);
            $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;
            if ($filter['record_count'] == 0) {
                $stop = 1;
            }

            $record_sum = $GLOBALS['db']->getOne($sql_sum);

        $record_layer_sum = $GLOBALS['db']->getOne($sql_layer_sum);

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

    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'],
//        'total_spends'=>$total_spends,
        'sum'=>$record_sum,
        'layer_sum'=>$record_layer_sum,
        'stop'=>$stop);

    return $arr;
}
function in_ids($sql)
{
    $user_arr = $GLOBALS['db']->getAll($sql);
    $user_ids = '';
    foreach ($user_arr as $K => $v) {
        $user_ids .= $v['user_id'] . ',';
    }
    return substr($user_ids,0,-1);
}

?>