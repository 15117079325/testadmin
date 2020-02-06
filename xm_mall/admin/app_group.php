<?php

/**
 * ECSHOP 广告管理程序
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: ads.php 17217 2011-01-19 06:29:08Z liubo $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
include_once(ROOT_PATH . 'includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$exc = new exchange($ecs->table("ad"), $db, 'ad_id', 'ad_name');

/* act操作项的初始化 */
if (empty($_REQUEST['act'])) {
    $_REQUEST['act'] = 'list';
} else {
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

/*------------------------------------------------------ */
//-- 广告列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list') {
    $pid = !empty($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0;


    $smarty->assign('action_link', array('text' => '添加群组', 'href' => 'app_group.php?act=add'));

    $smarty->assign('full_page', 1);

    $ads_list = get_adslist();

    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    $sort_flag = sort_flag($ads_list['filter']);

    assign_query_info();
    $smarty->display('home/app_group.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {
    $ads_list = get_adslist();

    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    $sort_flag = sort_flag($ads_list['filter']);


    make_json_result($smarty->fetch('home/app_group.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加新广告页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add') {



    $smarty->assign('action_link', array('href' => 'app_group.php?act=list', 'text' => '返回列表'));

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action', 'add');
    $smarty->assign('cfg_lang', $_CFG['lang']);

    assign_query_info();
    $smarty->display('home/app_group_info.htm');
}

/*------------------------------------------------------ */
//-- 新广告的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert') {


    /* 初始化变量 */
    $group_name = $_POST['group_name'];
    $group_user_name = trim($_POST['group_user_name']);

    if (empty($group_name)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('群名称不能为空', 0, $link);
    }
    if (empty($group_user_name)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('群主不能为空', 0, $link);
    }
    //查出是否有该用户
    $user_info = $db->getRow("SELECT user_id,nickname FROM xm_users WHERE user_name='{$group_user_name}'");
    if (empty($user_info['user_id'])) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('该用户不存在，请重新填写', 0, $link);
    }

    if ($_FILES['group_img']['tmp_name'] == '') {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('群头像不能为空', 0, $link);
    }
    $original_img = 'group/' . basename($image->upload_image($_FILES['group_img'], 'group'));
    $time = time();
    include_once(ROOT_PATH . 'admin/includes/ServerAPI.php');
    $json_uid = json_encode([$user_info['user_id']]);
    /* 插入数据 */
    $sql = "INSERT INTO " . $ecs->table('group_chat') . " (user_id,gc_title,gc_uid,gc_pic,gc_gmt_create)
    VALUES ('$user_info[user_id]',
            '$group_name',
            '$json_uid',
             '$original_img',
             '$time')";
    $db->query($sql);
    $id = $db->insert_id();
    if ($id) {
        $ServerAPI = New ServerAPI();
        $ret = $ServerAPI->groupCreate([$user_info['user_id']], $id, $group_name);
        $ret = json_decode($ret, true);
        if ($ret['code'] != 200) {
            $db->query("DELETE FROM xm_group_chat WHERE gc_id = {$id}");
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg('创群失败，请重试', 0, $link);
        } else {
            $content = array(
                'operatorUserId' => '1',
                'operation' => 'Create',
                'data' => array(
                    'operatorNickname' => '后台管理员',
                    'targetGroupName' => $group_name,
                ),
                'message' => '后台管理员' . '创建群组,大家一起快乐的聊天吧@TNT@',
                //"message"=>"创建群组",
                'extra' => ''
            );
            $content = json_encode($content);
            $ret = $ServerAPI->messageGroupPublish('1', $id, 'RC:GrpNtf', $content);
        }
    }
    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'app_group.php?act=list';
    sys_msg($_LANG['add'] . "&nbsp;" . $_POST['ad_name'] . "&nbsp;" . $_LANG['attradd_succed'], 0, $link);
}


/*------------------------------------------------------ */
//-- 广告编辑页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit') {


    /* 获取广告数据 */
    $sql = "SELECT ad.gc_id,ad.gc_title,ad.gc_pic,u.user_name 
            FROM xm_group_chat ad LEFT JOIN xm_users u ON ad.user_id=u.user_id WHERE ad.gc_id=" . intval($_REQUEST['id']);
    $ads_arr = $db->getRow($sql);

    $smarty->assign('action_link', array('href' => 'app_group.php?act=list', 'text' => '返回列表页'));
    $smarty->assign('form_act', 'update');
    $smarty->assign('action', 'edit');
    $smarty->assign('nav', $ads_arr);

    assign_query_info();
    $smarty->display('home/app_group_info.htm');
}

/*------------------------------------------------------ */
//-- 广告编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update') {



    /* 初始化变量 */
    $group_name = $_POST['group_name'];
    $group_user_name = trim($_POST['group_user_name']);
    $id = $_POST['id'];
    if (empty($group_name)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('群名称不能为空', 0, $link);
    }
    if (empty($group_user_name)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('群主不能为空', 0, $link);
    }
    //查出是否有该用户
    $user_id = $db->getOne("SELECT user_id FROM xm_users WHERE user_name='{$group_user_name}'");
    if (empty($user_id)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('该用户不存在，请重新填写', 0, $link);
    }

    if ($_FILES['group_img']['tmp_name'] == '') {
        $original_img = $_POST['goods_img_hidden'];
    } else {
        $original_img = 'group/' . basename($image->upload_image($_FILES['group_img'], 'group'));
    }

    /* 更新信息 */
    $sql = "UPDATE " . $ecs->table('group_chat') . " SET " .
        "gc_title     = '$group_name' ," .
        "user_id     = '$user_id' ," .
        "gc_pic     = '$original_img' " .
        "WHERE gc_id = '$id'";
    $db->query($sql);

    /* 提示信息 */
    $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'app_group.php?act=list');
    sys_msg($_LANG['edit'] . ' ' . $_POST['ad_name'] . ' ' . $_LANG['attradd_succed'], 0, $href);

} elseif ($_REQUEST['act'] == 'update_name') {

    $sk_id = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $title = json_str_iconv(trim($_POST['title']));

    $sql = "UPDATE xm_group_chat SET $key='$title' WHERE gc_id=$sk_id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }

}elseif ($_REQUEST['act'] == 'delete_group') {
    $group_list = $GLOBALS['db']->getAll("SELECT * FROM xm_group_chat WHERE gc_delete=1");
    include_once(ROOT_PATH . 'admin/includes/ServerAPI.php');
    $ServerAPI = New ServerAPI();
    foreach($group_list as $k=>$v){
        $ret = $ServerAPI->groupDismiss($v['user_id'], $v['gc_id']);
        $sql = "UPDATE xm_group_chat SET gc_delete=2 WHERE gc_id={$v['gc_id']}";
        $db->query($sql);
    }
}

/* 获取广告数据列表 */
function get_adslist()
{

    $filter = array();

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('group_chat') . ' AS ad WHERE ad.gc_delete=1';
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.user_id,ad.gc_id,ad.gc_title,ad.gc_pic,ad.gc_gmt_create,u.user_name FROM xm_group_chat ad LEFT JOIN xm_users u ON ad.user_id=u.user_id WHERE ad.gc_delete=1';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        $rows['gc_gmt_create'] = date('Y-m-d', $rows['gc_gmt_create']);
        $rows['gc_pic'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/' . $rows['gc_pic']);
        $arr[] = $rows;
    }

    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

?>
