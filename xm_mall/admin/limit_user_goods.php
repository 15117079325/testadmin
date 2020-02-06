<?php

/**
 * ECSHOP 会员管理程序
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author:ji $
 * 2017年11月4日16:33:32
 */
define('IN_ECS', true);

require (dirname(__FILE__) . '/includes/init.php');

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';

/* 路由 */

$function_name = 'action_' . $action;

if(! function_exists($function_name))
{
    $function_name = "action_list";
}

call_user_func($function_name);

/* 路由 */
/* ------------------------------------------------------ */
// -- 商品列表
/* ------------------------------------------------------ */
function action_list ()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    /* 检查权限 */
    $user_list = limit_user_goods_list();
    if($_REQUEST['type']=='add'){
        $smarty->assign('ur_here', '指定限制商品'); // 当前导航
    }else{
        $smarty->assign('ur_here', '查看限制商品'); // 当前导航
    }

    $smarty->assign('goods_list', $user_list['goods_list']);
    $smarty->assign('filter', $user_list['filter']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('page_count', $user_list['page_count']);
    $smarty->assign('full_page', 1);
    $smarty->assign('sort_user_id', '<img src="images/sort_desc.gif">');

    assign_query_info();
    $smarty->display('limit_user_goods/limit_user_goods_list.htm');
}

/* ------------------------------------------------------ */
// -- ajax返回商品列表
/* ------------------------------------------------------ */
function action_query ()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_list = limit_user_goods_list();

    $smarty->assign('goods_list', $user_list['goods_list']);
    $smarty->assign('filter', $user_list['filter']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('page_count', $user_list['page_count']);

    $sort_flag = sort_flag($user_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('limit_user_goods/limit_user_goods_list.htm'), '', array(
        'filter' => $user_list['filter'],'page_count' => $user_list['page_count']
    ));
}

/**
 * 返回商品列表
 *
 * @access public
 * @param
 *
 * @return void
 */
function limit_user_goods_list ()
{
    $result = get_filter();
    if($result === false)
    {
        /* 过滤条件 */
        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        $filter['type'] = empty($_REQUEST['type']) ? '' : trim($_REQUEST['type']);
        $filter['user_id'] = empty($_REQUEST['user_id']) ? '' : trim($_REQUEST['user_id']);

        if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }

        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'g.goods_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $ex_where = " WHERE mge.belongto=2 AND mge.allow_bb=1 " ;
        $goods_idsql = "SELECT goods_id from " . $GLOBALS['ecs']->table('mq_users_limit_goods')." where user_id =".$filter['user_id'];
        $goods_ids = $GLOBALS['db']->getAll($goods_idsql);

        if($filter['keywords'])
        {
            $ex_where .= " AND g.goods_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' ";
        }
        switch ($filter['type'])
        {
            case 'add':   //用户已限制商品不再出现
                if(count($goods_ids)>0){
                    $goods_id_arr = array_column($goods_ids,'goods_id');
                    $ex_where .= " AND g.goods_id ".db_create_not_in($goods_id_arr);
                }
                $sql1 = "SELECT COUNT(*) FROM " . $GLOBALS['ecs'] ->table('goods') . " AS g " .
                    " INNER JOIN " . $GLOBALS['ecs']->table('mq_goods_extra') . " AS mge ON g.goods_id = mge.goods_id " .
                    $ex_where;
                $filter['record_count'] = $GLOBALS['db']->getOne($sql1);
                /* 分页大小 */
                $filter = page_and_size($filter);
                /* 代码增加2014-12-23 by www.68ecshop.com _star */
                $sql = "SELECT g.goods_name ,g.goods_id".
                    /* 代码增加2014-12-23 by www.68ecshop.com  _end  */
                    " FROM " . $GLOBALS['ecs']->table('goods'). ' AS g' .
                    " INNER JOIN " . $GLOBALS['ecs']->table('mq_goods_extra') . " AS mge ON  g.goods_id = mge.goods_id ".
                    $ex_where . " ORDER by " . $filter['sort_by'] . ' ' . $filter['sort_order'] . " LIMIT " . $filter['start'] . ',' . $filter['page_size'];
                /*end fuzp*/
                $filter['keywords'] = stripslashes($filter['keywords']);
                break;
            case 'update';  //用户限制商品
                $ex_where.= " AND lug.user_id=". $filter['user_id'];
                if(count($goods_ids)>0){
                    $goods_id_arr = array_column($goods_ids,'goods_id');
                    $ex_where .= " AND g.goods_id ".db_create_in($goods_id_arr);
                }else{
                    $ex_where .= " AND g.goods_id ".db_create_in([]);
                }
                $sql1 = "SELECT COUNT(*) FROM " . $GLOBALS['ecs'] ->table('mq_users_limit_goods') . " AS lug " .
                    " INNER JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON g.goods_id = lug.goods_id " .
                    " INNER JOIN " . $GLOBALS['ecs']->table('mq_goods_extra') . " AS mge ON g.goods_id = mge.goods_id " .
                    $ex_where;
                $filter['record_count'] = $GLOBALS['db']->getOne($sql1);
                /* 分页大小 */
                $filter = page_and_size($filter);
                /* 代码增加2014-12-23 by www.68ecshop.com _star */
                $sql = "SELECT g.goods_name ,g.goods_id, lug.limit_num".
                    /* 代码增加2014-12-23 by www.68ecshop.com  _end  */
                    " FROM " . $GLOBALS['ecs']->table('mq_users_limit_goods'). ' AS lug' .
                    " INNER JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON  g.goods_id = lug.goods_id ".
                    " INNER JOIN " . $GLOBALS['ecs']->table('mq_goods_extra') . " AS mge ON  g.goods_id = mge.goods_id ".
                    $ex_where . " ORDER by " . $filter['sort_by'] . ' ' . $filter['sort_order'] . " LIMIT " . $filter['start'] . ',' . $filter['page_size'];
                /*end fuzp*/
                $filter['keywords'] = stripslashes($filter['keywords']);
                break;
        }
        //set_filter($filter, $sql);
    }
    else
    {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }
    // echo $sql;exit();

    $goods_list = $GLOBALS['db']->getAll($sql);

    $arr = array(
        'goods_list' => $goods_list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']
    );

    return $arr;
}

/* ------------------------------------------------------ */
// -- 用户帐号限购 addby ji 2017年11月6日10:52:55
/* ------------------------------------------------------ */
function action_add ()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_GET['user_id'];
    $sql = "SELECT xmql.*,u.user_name,u.user_id " . " FROM " . $ecs->table('mq_users_limit') . " xmql 	RIGHT JOIN " . $ecs->table('users') . " u ON u.user_id = xmql.user_id WHERE u.user_id='$user_id'";
    $row = $db->GetRow($sql);
    /* 检查权限 */

    if($_POST['type']=='add'){
        $sql = "SELECT goods_name,goods_id  " . " FROM " . $ecs->table('goods') . " WHERE goods_id ".db_create_in($_POST['checkboxes']);
    }else{
        $sql = "SELECT g.goods_name,lug.goods_id,lug.limit_num " . " FROM " . $ecs->table('mq_users_limit_goods') . " lug LEFT JOIN " . $ecs->table('goods') . " g ON g.goods_id = lug.goods_id WHERE lug.goods_id ".db_create_in($_POST['checkboxes'])." AND user_id =".$user_id;
    }

    $goods = $db->GetAll($sql);
    $row['user_name'] = addslashes($row['user_name']);
    if($row)
    {
        $user['user_id'] = $row['user_id'];
        $user['user_name'] = $row['user_name'];
        $user['goods'] = $goods;
    }
    assign_query_info();
    $smarty->assign('ur_here', '按商品限制');
    $smarty->assign('user', $user);
    $smarty->assign('form_action', 'insert');
    $smarty->display('limit_user_goods/limit_user_goods_from.htm');
}

/* ------------------------------------------------------ */
// -- 更新帐号限购 2017年11月6日10:52:55
/* ------------------------------------------------------ */
function action_insert ()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    /* 检查权限 */
    $data=array();
    $goods_ids = $_POST['goods_id'];
    $limit_num = $_POST['limit_num'];
    if(count($goods_ids) != count($limit_num)){
        show_message("数据异常！");
    };
    $add_data=[];
    for ($x=0; $x<count($goods_ids); $x++) {
        $add_data[$x]['goods_id'] = $goods_ids[$x];
        $add_data[$x]['limit_num'] = $limit_num[$x];
    }
    foreach($add_data as $item){
        $sql = "SELECT * " . " FROM " . $ecs->table('mq_users_limit_goods') . " WHERE user_id='$_POST[user_id]' AND goods_id=".$item['goods_id'];
        $result = $db->GetRow($sql);
        /*如果存在就更新 不存在就插入*/
        if($result){
            $data['limit_num'] = $item['limit_num'];
            $data['update_at'] = gmtime();
            $data['update_user_id'] = $_SESSION['admin_id'];
            $db->autoExecute($ecs->table('mq_users_limit_goods'), $data, 'UPDATE', "user_id = '$_POST[user_id]' AND goods_id='$item[goods_id]'");
        }else{
            $data['user_id'] = $_POST['user_id'];
            $data['limit_num'] = $item['limit_num'];
            $data['goods_id'] = $item['goods_id'];
            $data['create_at'] = gmtime();
            $data['create_user_id'] = $_SESSION['admin_id'];
            $db->autoExecute($ecs->table('mq_users_limit_goods'), $data, 'INSERT');
        }
    }

    /* 记录管理员操作 */
    admin_log($result['user_name'], '指定商品限制用户', 'users');

    /* 提示信息 */
    $links[0]['text'] = "返回限制会员列表";
    $links[0]['href'] = 'users_limit.php?act=restriction_list';
    $links[1]['text'] = $_LANG['go_back'];
    $links[1]['href'] = 'javascript:history.back()';
    clear_cache_files(); // 清除模版缓存
    sys_msg("操作成功！", 0, $links);
}

/* ------------------------------------------------------ */
// -- 删除会员限制
/* ------------------------------------------------------ */
function action_remove ()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];
    /* 检查权限 */
    $sql = "DELETE  FROM " . $ecs->table('mq_users_limit_goods') . " WHERE user_id = '" . $_GET['user_id'] . "' and goods_id = ".$_GET['goods_id'];

    $db->query($sql);
    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'limit_user_goods.php?act=list&type=update&user_id='.$_GET['user_id']
    );
    sys_msg('删除成功', 0, $link);
}

?>
