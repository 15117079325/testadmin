<?php

/*
|- 20180521 1554 V 数据统计
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/admin/statistic.php');

// act操作项的初始化
if (empty($_REQUEST['act'])) {

    $_REQUEST['act'] = 'main';

} else {

    $_REQUEST['act'] = trim($_REQUEST['act']);
}

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'main';
/* 路由 */
$function_name = 'action_' . $action;

if (!function_exists($function_name)) {

    $function_name = "action_main";
}

call_user_func($function_name);

// -----------------------------------------------------------------------------

function action_attr_list()
{
    admin_priv('attr_manage');
    $smarty = $GLOBALS['smarty'];

    $ads_list = get_attrlist();


    $smarty->assign('action_link', array('text' => '添加属性值', 'href' => 'app_attr.php?act=add_attr'));

    $smarty->assign('full_page', 1);


    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    $sort_flag = sort_flag($ads_list['filter']);

    assign_query_info();
    $smarty->display('home/attr_list.htm');
}

function action_add_attr()
{
    admin_priv('attr_manage');
    $smarty = $GLOBALS['smarty'];
    $smarty->assign('goods',
        array('status' => 1));

    $smarty->assign('action_link', array('href' => 'app_attr.php?act=attr_list', 'text' => '返回列表'));

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action', 'add_attr');

    assign_query_info();
    $smarty->display('home/attr_info.htm');
}

function action_insert()
{
    admin_priv('attr_manage');
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    /* 初始化变量 */
    $attr_name = trim($_REQUEST['attr_name']);
    if (empty($attr_name)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('属性值不能为空', 0, $link);
    }
    $attr_name = explode('<br />', nl2br($attr_name));
    $attr_name = array_unique($attr_name);
    $str = "";
    $all_attr = $db->getAll("SELECT * FROM xm_attributes");
    $all_attr_name = array_column($all_attr, 'attr_title');
    foreach ($attr_name as $k => $v) {
        if ($v) {
            if (in_array($v, $all_attr_name)) {
                continue;
            }
            $str .= "(null,'{$v}'),";
        }
    }

    if ($str) {
        $str = 'INSERT INTO xm_attributes (attr_id,attr_title) VALUES ' . rtrim($str, ',');
        $db->query($str);
    }
    $href[] = array('text' => '添加成功', 'href' => 'app_attr.php?act=attr_list');
    sys_msg('添加成功', 0, $href);
}

function action_query()
{
    admin_priv('attr_manage');
    $smarty = $GLOBALS['smarty'];

    $ads_list = get_attrlist();

    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    $sort_flag = sort_flag($ads_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('home/attr_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

function action_remove()
{
    admin_priv('attr_manage');
    $ecs = $GLOBALS['ecs'];
    $db = $GLOBALS['db'];
    $id = intval($_POST['id']);
    $sql = 'DELETE FROM ' . $ecs->table('attributes') . " WHERE attr_id={$id}";
    $val_sql = 'DELETE FROM ' . $ecs->table('attr_val') . " WHERE attr_id={$id}";
    $db->query($val_sql);
    $id = $db->query($sql);

    if ($id) {
        echo json_encode(['status' => 1]);
    }
    exit;
}

function action_update_name()
{
    admin_priv('attr_manage');
    $db = $GLOBALS['db'];
    $sk_id = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $title = json_str_iconv(trim($_POST['title']));
    $sql = "UPDATE xm_attributes SET $key='$title' WHERE attr_id=$sk_id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }
}

function get_attrlist()
{
    $filter = array();

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('attributes') . ' AS ad ';
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.* ' .
        'FROM ' . $GLOBALS['ecs']->table('attributes') . 'AS ad ' .
        'ORDER by ad.attr_id';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {

        $arr[] = $rows;
    }

    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}


function action_attr_val_list()
{
    admin_priv('attr_val_manage');
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $attr_ids = $db->getAll("SELECT * FROM xm_attributes ORDER BY attr_id");
    $attr_id = isset($_GET['attrid']) ? intval($_GET['attrid']) : $attr_ids[0]['attr_id'];
    $ads_list = get_attrvallist($attr_id);

    $smarty->assign('action_link', array('text' => '添加属性值', 'href' => 'app_attr.php?act=add_attr_val'));
    $smarty->assign('attr_ids', $attr_ids);
    $smarty->assign('attrid', $attr_id);
    $smarty->assign('full_page', 1);

    $ads_list['filter']['attrid'] = $attr_id;
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    $sort_flag = sort_flag($ads_list['filter']);

    assign_query_info();
    $smarty->display('home/attr_val_list.htm');
}

function action_add_attr_val()
{
    admin_priv('attr_val_manage');
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    //查出所有的属性值
    $all_attr = $db->getAll("SELECT * FROM xm_attributes");
    $smarty->assign('all_attr', $all_attr);
    $smarty->assign('action_link', array('href' => 'app_attr.php?act=attr_val_list', 'text' => '返回列表'));

    $smarty->assign('form_act', 'insert_val');
    $smarty->assign('action', 'add_attr_val');

    assign_query_info();
    $smarty->display('home/attr_val_info.htm');
}

function action_insert_val()
{
    admin_priv('attr_val_manage');
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    /* 初始化变量 */

    $attr_id = intval($_REQUEST['attr_val']);
    $attr_val_name = trim($_REQUEST['attr_val_name']);
    if (empty($attr_val_name)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('属性值不能为空', 0, $link);
    }
    if (empty($attr_id)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('参数出错', 0, $link);
    }
    //查出该属性下面的属性值
    $attr_vals = $db->getAll("SELECT * FROM xm_attr_val WHERE attr_id={$attr_id}");
    $attr_vals = array_column($attr_vals, 'attr_val_name');
    $attr_val_name = explode('<br />', nl2br($attr_val_name));
    $attr_val_name = array_unique($attr_val_name);
    $str = "";

    foreach ($attr_val_name as $k => $v) {
        if ($v) {
            if (in_array($v, $attr_vals)) {
                continue;
            }
            $str .= "({$attr_id},'{$v}'),";
        }
    }

    if (!empty($str)) {
        $str = rtrim($str, ',');
        $str = " INSERT INTO xm_attr_val (attr_id,attr_val_name) VALUES " . $str;
        $db->query($str);
    }
    $href[] = array('text' => '添加成功', 'href' => 'app_attr.php?act=attr_val_list');
    sys_msg('添加成功', 0, $href);
}

function action_query_val()
{
    admin_priv('attr_val_manage');
    $smarty = $GLOBALS['smarty'];
    $attr_id = intval($_REQUEST['attrid']);
    $ads_list = get_attrvallist($attr_id);
    $ads_list['filter']['attrid'] = $attr_id;
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    $sort_flag = sort_flag($ads_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('home/attr_val_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

function action_remove_val()
{
    admin_priv('attr_val_manage');
    $ecs = $GLOBALS['ecs'];
    $db = $GLOBALS['db'];
    $id = intval($_POST['id']);
    $sql = 'DELETE FROM ' . $ecs->table('attr_val') . " WHERE attr_val_id={$id}";
    $id = $db->query($sql);

    if ($id) {
        echo json_encode(['status' => 1]);
    }
    exit;
}

function action_update_name_val()
{
    admin_priv('attr_val_manage');
    $db = $GLOBALS['db'];
    $sk_id = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $title = json_str_iconv(trim($_POST['title']));
    $sql = "UPDATE xm_attr_val SET $key='$title' WHERE attr_val_id=$sk_id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }
}

function get_attrvallist($attr_id)
{
    $filter = array();

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('attr_val') . "AS ad WHERE ad.attr_id={$attr_id}";
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.* ' .
        'FROM ' . $GLOBALS['ecs']->table('attr_val') . 'AS ad ' .
        " WHERE ad.attr_id={$attr_id} ORDER by ad.attr_val_id";

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {

        $arr[] = $rows;
    }

    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}








