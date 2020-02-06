<?php

/**
 * 商品推荐人管理
 * Author: Shenglin
 * E-mail: shengl@maqu.im
 * CreatedTime: 2017/2/7 11:05
 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- 商品推荐人列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
     /* 检查权限 */
     admin_priv('referee_manage');

    /* 查询 */
    $result = referee_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['referee_list']); // 当前导航
    $smarty->assign('action_link', array('href' => 'mq_referee.php?act=add', 'text' => $_LANG['add_referee']));

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('referee_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_referee_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_referee_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('referee_manage');

    $result = referee_list();

    $smarty->assign('referee_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('mq_referee_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/*------------------------------------------------------ */
//-- 列表页编辑名称
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_referee_name')
{
    check_authz_json('referee_manage');

    $id     = intval($_POST['id']);
    $name   = json_str_iconv(trim($_POST['val']));

    /* 判断名称是否重复 */
    $sql = "SELECT referee_id
            FROM " . $ecs->table('mq_referee') . "
            WHERE referee_name = '$name'
            AND referee_id <> '$id' ";
    if ($db->getOne($sql))
    {
        make_json_error(sprintf($_LANG['referee_name_exist'], $name));
    }
    else
    {
        /* 保存商品推荐人信息 */
        $sql = "UPDATE " . $ecs->table('mq_referee') . "
                SET referee_name = '$name'
                WHERE referee_id = '$id'";
        if ($result = $db->query($sql))
        {
            /* 记日志 */
            admin_log($name, 'edit', 'referee');

            clear_cache_files();

            make_json_result(stripslashes($name));
        }
        else
        {
            make_json_result(sprintf($_LANG['agency_edit_fail'], $name));
        }
    }
}

/*------------------------------------------------------ */
//-- 删除商品推荐人
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('referee_manage');

    $id = intval($_REQUEST['id']);
    $sql = "SELECT *
            FROM " . $ecs->table('mq_referee') . "
            WHERE referee_id = '$id'";
    $referee = $db->getRow($sql, TRUE);

    if ($referee['referee_id'])
    {
        /* 判断商品推荐人是否存在提成记录 */
        $sql = "SELECT COUNT(*)
                FROM " . $ecs->table('mq_referee_bonus') . "
                WHERE referee_id = '$id'";
        $bonus_exists = $db->getOne($sql, TRUE);
        if ($bonus_exists > 0)
        {
            $url = 'mq_referee.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
            ecs_header("Location: $url\n");
            exit;
        }

        /* 判断商品推荐人是否存在商品 */
        $sql = "SELECT COUNT(*)
                FROM " . $ecs->table('mq_goods_extra') . "
                WHERE referee_id = '$id'";
        $goods_exists = $db->getOne($sql, TRUE);
        if ($goods_exists > 0)
        {
            $url = 'mq_referee.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
            ecs_header("Location: $url\n");
            exit;
        }

        $sql = "DELETE FROM " . $ecs->table('mq_referee') . "
            WHERE referee_id = '$id'";
        $db->query($sql);

        /* 记日志 */
        admin_log($referee['referee_name'], 'remove', 'referee');

        /* 清除缓存 */
        clear_cache_files();
    }

    $url = 'mq_referee.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
    ecs_header("Location: $url\n");

    exit;
}

/*------------------------------------------------------ */
//-- 批量操作
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'batch')
{
    /* 取得要操作的记录编号 */
    if (empty($_POST['checkboxes']))
    {
        sys_msg($_LANG['no_record_selected']);
    }
    else
    {
        /* 检查权限 */
        admin_priv('referee_manage');

        $ids = $_POST['checkboxes'];

        if (isset($_POST['remove']))
        {
            $sql = "SELECT *
                    FROM " . $ecs->table('mq_referee') . "
                    WHERE referee_id " . db_create_in($ids);
            $referee = $db->getAll($sql);

            foreach ($referee as $key => $value)
            {

                $sql = "SELECT COUNT(*)
                FROM " . $ecs->table('mq_referee_bonus') . "
                WHERE referee_id = '$id'";
                $bonus_exists = $db->getOne($sql, TRUE);
                if ($bonus_exists > 0)
                {
                    unset($referee[$key]);
                }

                /* 判断商品推荐人是否存在商品 */
                $sql = "SELECT COUNT(*)
                FROM " . $ecs->table('mq_goods_extra') . "
                WHERE referee_id = '$id'";
                $goods_exists = $db->getOne($sql, TRUE);
                if ($goods_exists > 0)
                {
                    unset($referee[$key]);
                }

            }
            if (empty($referee))
            {
                sys_msg($_LANG['batch_drop_no']);
            }

            $referee_names = '';
            foreach ($referee as $value)
            {
                $referee_names .= $value['referee_name'] . '|';
            }

            $sql = "DELETE FROM " . $ecs->table('mq_referee') . "
                WHERE referee_id " . db_create_in($ids);
            $db->query($sql);

            /* 记日志 */
            foreach ($referee as $value)
            {
                $referee_names .= $value['referee_name'] . '|';
            }
            admin_log($referee_names, 'remove', 'referee');

            /* 清除缓存 */
            clear_cache_files();

            sys_msg($_LANG['batch_drop_ok']);
        }
    }
}

/*------------------------------------------------------ */
//-- 添加、编辑商品推荐人
/*------------------------------------------------------ */
elseif (in_array($_REQUEST['act'], array('add', 'edit')))
{
    /* 检查权限 */
    admin_priv('referee_manage');

    if ($_REQUEST['act'] == 'add')
    {
        $smarty->assign('ur_here', $_LANG['add_referee']);
        $smarty->assign('action_link', array('href' => 'mq_referee.php?act=list', 'text' => $_LANG['referee_list']));

        $smarty->assign('form_action', 'insert');

        assign_query_info();

        $smarty->display('mq_referee_info.htm');

    }
    elseif ($_REQUEST['act'] == 'edit')
    {
        $referee = array();

        /* 取得商品推荐人信息 */
        $id = $_REQUEST['id'];
        $sql = "SELECT * FROM " . $ecs->table('mq_referee') . " WHERE referee_id = '$id'";
        $referee = $db->getRow($sql);
        if (count($referee) <= 0)
        {
            sys_msg('referee does not exist');
        }

        $smarty->assign('ur_here', $_LANG['edit_referee']);
        $smarty->assign('action_link', array('href' => 'mq_referee.php?act=list', 'text' => $_LANG['referee_list']));

        $smarty->assign('form_action', 'update');
        $smarty->assign('referee', $referee);

        assign_query_info();

        $smarty->display('mq_referee_info.htm');
    }

}

/*------------------------------------------------------ */
//-- 提交添加、编辑商品推荐人
/*------------------------------------------------------ */
elseif (in_array($_REQUEST['act'], array('insert', 'update')))
{
    /* 检查权限 */
    admin_priv('referee_manage');

    $referee_phone = trim($_POST['referee_phone']);
    if(!is_mobile_phone($referee_phone)){
        sys_msg($_LANG['referee_phone_error']);
    }

    $referee_percent = floatval($_POST['referee_percent']);
    if(!is_numeric($referee_percent)){
        sys_msg($_LANG['referee_percent_error']);
    }
    if($referee_percent<0||$referee_percent>100){
        sys_msg($_LANG['referee_percent_error2']);
    }

    if ($_REQUEST['act'] == 'insert')
    {

        /* 提交值 */
        $referee = array('referee_name'   => trim($_POST['referee_name']),
                            'referee_phone' => $referee_phone,
                            'percent'       => $referee_percent,
                           'referee_desc'   => trim($_POST['referee_desc'])
                           );

        /* 判断名称是否重复 */
        $sql = "SELECT referee_id
                FROM " . $ecs->table('mq_referee') . "
                WHERE referee_name = '" . $referee['referee_name'] . "' ";
        if ($db->getOne($sql))
        {
            sys_msg($_LANG['referee_name_exist']);
        }

        $db->autoExecute($ecs->table('mq_referee'), $referee, 'INSERT');
        $referee['referee_id'] = $db->insert_id();

        /* 记日志 */
        admin_log($referee['referee_name'], 'add', 'referee');

        /* 清除缓存 */
        clear_cache_files();

        /* 提示信息 */
        $links = array(array('href' => 'mq_referee.php?act=add',  'text' => $_LANG['continue_add_referee']),
                       array('href' => 'mq_referee.php?act=list', 'text' => $_LANG['back_referee_list'])
                       );
        sys_msg($_LANG['add_referee_ok'], 0, $links);

    }

    if ($_REQUEST['act'] == 'update')
    {
        /* 提交值 */
        $referee = array('id'   => trim($_POST['id']));

        $referee['new'] = array('referee_name'   => trim($_POST['referee_name']),
                                'referee_phone' => $referee_phone,
                                'percent'       => $referee_percent,
                                'referee_desc'   => trim($_POST['referee_desc'])
                            );

        /* 取得商品推荐人信息 */
        $sql = "SELECT * FROM " . $ecs->table('mq_referee') . " WHERE referee_id = '" . $referee['id'] . "'";
        $referee['old'] = $db->getRow($sql);
        if (empty($referee['old']['referee_id']))
        {
            sys_msg('referee does not exist');
        }

        /* 判断名称是否重复 */
        $sql = "SELECT referee_id
                FROM " . $ecs->table('mq_referee') . "
                WHERE referee_name = '" . $referee['new']['referee_name'] . "'
                AND referee_id <> '" . $referee['id'] . "'";
        if ($db->getOne($sql))
        {
            sys_msg($_LANG['referee_name_exist']);
        }

        /* 保存商品推荐人信息 */
        $db->autoExecute($ecs->table('mq_referee'), $referee['new'], 'UPDATE', "referee_id = '" . $referee['id'] . "'");

        /* 记日志 */
        admin_log($referee['old']['referee_name'], 'edit', 'referee');

        /* 清除缓存 */
        clear_cache_files();

        /* 提示信息 */
        $links[] = array('href' => 'mq_referee.php?act=list', 'text' => $_LANG['back_referee_list']);
        sys_msg($_LANG['edit_referee_ok'], 0, $links);
    }

}

/**
 *  获取商品推荐人列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function referee_list()
{
    $result = get_filter();
    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'referee_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'ASC' : trim($_REQUEST['sort_order']);

        $where = 'WHERE 1 ';

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
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('mq_referee') . $where;
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT referee_id, referee_name, referee_phone, referee_desc, percent
                FROM " . $GLOBALS['ecs']->table("mq_referee") . "
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

    $row = $GLOBALS['db']->getAll($sql);

    $arr = array('result' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}
?>