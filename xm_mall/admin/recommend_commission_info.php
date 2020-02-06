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
     admin_priv('recommend_commission_lists');

    /* 查询 */
    /*新增查询 by ymm 2017-2-24 20:29*/
    $result = re_invest_list();
    $last_release_date = get_last_release_date();
/*    echo "<pre>";
    print_r($result);*/
    foreach ($result['result'] as $k=>$v){
        $result['result'][$k]['add_time'] = date('Y-m-d',$v['add_time']);
        $result['result'][$k]['shipping_time'] = date('Y-m-d',$v['add_time']);
    }
    /* 模板赋值 */
    $smarty->assign('ur_here', "商品推荐提成列表"); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('last_release_date',$last_release_date);
    $smarty->assign('bonus_List',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_re_invest_lists.htm');


}
/*-----------------------------------------------------*/
/*==推荐人详细页*/
/*-----------------------------------------------------*/
elseif($_REQUEST['act'] == 'info')
{
    $user_id = $_GET['user_id'];
    $result = re_invest_list_info($user_id);
    $last_release_date = get_last_release_date();
    foreach ($result['result'] as $k=>$v){
        $result['result'][$k]['add_time'] = date('Y-m-d',$v['add_time']);
        $result['result'][$k]['shipping_time'] = date('Y-m-d',$v['add_time']);
    }
    /* 模板赋值 */
    $smarty->assign('ur_here', "商品推荐提成明细列表"); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('last_release_date',$last_release_date);
    $smarty->assign('bonus_List',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('user_id', $user_id);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_re_invest_lists_info.htm');

}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('recommend_commission_lists');


    $result = re_invest_list_info();
    $smarty->assign('bonus_List',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
/*    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);*/

    make_json_result($smarty->fetch('mq_re_invest_lists_info.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}


/*------------------------------------------------------ */
//-- 释放选择日期之前的投资
/*------------------------------------------------------ */
elseif ($_REQUEST['act']=='release_invest')
{
    /* 检查权限 */
    admin_priv('xinmei_manage');
    $links[] = array('href' => 'mq_re_invest.php?act=list', 'text' => '返回积分投资列表');
    $now = time();
    $release_time_format = !empty($_POST['release_time']) ? trim($_POST['release_time']) : sys_msg('参数错误', 1, $links, false);
    $release_time = strtotime($release_time_format)-8*3600;//0点

    $now_date = local_date('Y-m-d', $now);
    $today_time = strtotime($now_date);

    if($release_time > $today_time){
        sys_msg('请选择今天之前（包括今天）的日期', 1, $links, false);
    }
    $release_time = $release_time + 86399;
    //如果选择的是今天之后的日子 返回错误

    if($release_time > time()){
        $release_time = time();
    }

    include_once (ROOT_PATH . 'includes/lib_creditapi.php');

    $result = api_release_invest($_SESSION['admin_id'], $release_time);

    switch($result['status']){
        case 0:
            sys_msg('释放失败，请稍后重试'.$result['message'], 1, $links, false);
            break;
        case 1:
            /* 提示信息 */
            sys_msg($release_time_format.'之前的投资已释放完毕', 0, $links);
            break;
        case 2:
            sys_msg('释放失败，请稍后重试'.$result['message'], 1, $links, false);
            break;
        case 3:
            sys_msg('释放失败，请稍后重试'.$result['message'], 1, $links, false);
            break;
    }

}

/**
 *  获取充值申请列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */

function get_last_release_date(){
    $sql = "SELECT last_time FROM " . $GLOBALS['ecs']->table('mq_credit_invest_timeline') ." ORDER BY id DESC LIMIT 1  ";
    $last_release_date   = $GLOBALS['db']->getOne($sql);
    return local_date('Y-m-d', $last_release_date);
}

function re_invest_list_info($user_id)
{
    $user_ids = $user_id;
    $result = get_filter();
    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        /*        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'update_at' : trim($_REQUEST['sort_by']);
                $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);*/

        $where = 'WHERE b.user_id = '.$_REQUEST['user_id'] ;
        //当月数据
        $thismonth = date('m');
        $thisyear = date('Y');
        $startDay = $thisyear . '-' . $thismonth . '-1';
        $endDay = $thisyear . '-' . $thismonth . '-' . date('t', strtotime($startDay));
        $b_time  = strtotime($startDay);//当前月的月初时间戳
        $e_time  = strtotime($endDay);//当前月的月末时间戳
        if($_REQUEST['preparation'] == 1){
            $where .= "b.add_time between ".$b_time." AND ".$e_time;
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
            $where .= " AND b.add_time between ".$b_time." AND ".$e_time;
        }
        //echo $where;die;
        if ($filter['user_name'])
        {
            $where .= " AND u.user_name LIKE '%" . mysql_like_quote($filter['user_name']) . "%'";
        }
        if($filter['type'] != -1 && $filter['type'] != 0 ){
            $where .= " AND i.barter_type =".$filter['type'];
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
               $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table("mq_referee_bonus") . " AS b ".
                    "LEFT JOIN " . $GLOBALS['ecs']->table("order_goods") ." AS i ON b.order_goods_id = i.rec_id
                     LEFT JOIN  ". $GLOBALS['ecs']->table("users") ." AS u ON b.user_id = u.user_id
                        $where";
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;


        /* 查询 */
               $sql = "SELECT b.bonus_amount,b.add_time,b.percent,b.shopping_amount,u.user_name,i.goods_name,i.goods_id,i.barter_type FROM " . $GLOBALS['ecs']->table("mq_referee_bonus") . " AS b ".
                        "LEFT JOIN " . $GLOBALS['ecs']->table("order_goods") ." AS i ON b.order_goods_id = i.rec_id
                        LEFT JOIN  ". $GLOBALS['ecs']->table("users") ." AS u ON b.user_id = u.user_id
                        $where ORDER BY b.id DESC  LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";
        // echo $sql;
        /*记录总数*/
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