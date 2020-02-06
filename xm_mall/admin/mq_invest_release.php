<?php

/**
 * 复投释放记录管理
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
    admin_priv('release_invest_lists');

    /* 查询 */
    $result = invest_release_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', "积分投资释放记录列表"); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('invest_release_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_invest_release_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('release_invest_lists');

    $result = invest_release_list();

    $smarty->assign('invest_release_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('mq_invest_release_list.htm'), '',
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
function invest_release_list()
{
    admin_priv('release_invest_lists');
    $result = get_filter();
    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'create_at' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $where = 'WHERE 1 ';

        $filter['user_name'] = isset($_REQUEST['user_name']) ? trim($_REQUEST['user_name']) : '';
        $filter['release_status'] = isset($_REQUEST['release_status']) ? intval($_REQUEST['release_status']) : '';
        $filter['release_date'] = isset($_REQUEST['release_date']) ? trim($_REQUEST['release_date']) : '';

        if ($filter['user_name'])
        {
            $where .= " AND au.user_name LIKE '%" . mysql_like_quote($filter['user_name']) . "%' ";
        }
        if($filter['release_date']){
            $release_date = strtotime($filter['release_date']);
            $where .= " AND mcirl.release_end < ".$release_date;
        }
        if ($filter['release_status']!='')
        {
            $where .= " AND mcirl.status = '$filter[release_status]'";
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
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('mq_credit_invest_release_log') ." AS mcirl ".
                "INNER JOIN " . $GLOBALS['ecs']->table("admin_user") ." AS au ON mcirl.user_id = au.user_id $where ";
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT mcirl.*, au.user_name FROM " . $GLOBALS['ecs']->table("mq_credit_invest_release_log") . " AS mcirl ".
                "INNER JOIN " . $GLOBALS['ecs']->table("admin_user") ." AS au ON mcirl.user_id = au.user_id
                $where
                ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order']. "
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
        switch($row['status']){
            case 1 :
                $row['release_status_text'] = '释放中';
                break;
            case 2 :
                $row['release_status_text'] = '已释放';
                break;
            default:
                $row['release_status_text'] = '等待释放';
        }
        $row['release_start_format'] = local_date($GLOBALS['_CFG']['time_format'], $row['release_start']);
        $row['release_end_format'] = local_date($GLOBALS['_CFG']['time_format'], $row['release_end']);
        $row['create_at_format'] = local_date($GLOBALS['_CFG']['time_format'], $row['create_at']);
        $arr[] = $row;
    }

    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}

?>