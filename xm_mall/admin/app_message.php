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
    $smarty = $GLOBALS['smarty'];
    $ads_list = get_attrlist();
    $smarty->assign('action_link', array('text' => '添加系统消息', 'href' => 'app_message.php?act=add'));
    $smarty->assign('full_page', 1);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    $sort_flag = sort_flag($ads_list['filter']);
    assign_query_info();
    $smarty->display('home/message_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {
    $ads_list = get_attrlist();
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    make_json_result($smarty->fetch('home/message_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
} elseif ($_REQUEST['act'] == 'add') {
    $smarty = $GLOBALS['smarty'];
    create_html_editor('detail', htmlspecialchars(''));
    $smarty->assign('action_link', array('href' => 'app_message.php?act=message_list', 'text' => '返回列表'));
    $smarty->assign('form_act', 'insert');
    $smarty->assign('action', 'add_message');

    assign_query_info();
    $smarty->display('home/message_info.htm');
} elseif ($_REQUEST['act'] == 'insert') {
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    /* 初始化变量 */
    $title = trim($_REQUEST['title']);
    $content = trim($_REQUEST['content']);
    $detail = empty($_POST['detail']) ? '' : $_POST['detail'];


    if (empty($title)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('标题不能为空', 0, $link);
    }
    if (empty($content)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('内容不能为空', 0, $link);
    }


    // 如果上传了商品图片，相应处理
    if (($_FILES['thumb']['tmp_name'] != '')) {
        $original_img = 'message/' . basename($image->upload_image($_FILES['thumb'], 'message')); // 原始图片
    } else {
        $original_img = '';
    }
    $time = time();
    $sql = "INSERT INTO xm_system_message (am_title,am_content,am_detail,am_img,am_gmt_create) VALUES ('{$title}','{$content}','{$detail}','{$original_img}',$time)";
    $db->query($sql);
    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'app_attr.php?act=attr_list';
    sys_msg("添加成功" . $_LANG['attradd_succed'], 0, $link);

} elseif ($_REQUEST['act'] == 'remove') {
    check_authz_json('ad_manage');

    $id = intval($_POST['id']);
    $sql = "UPDATE xm_system_message SET am_delete=1 WHERE am_id=$id";
    $id = $db->query($sql);
    if ($id) {
        echo json_encode(['status' => 1]);
    }
    exit;
} elseif ($_REQUEST['act'] == 'update_name') {

    $id = intval($_POST['id']);
    $status = json_str_iconv(trim($_POST['status']));
    if ($status==0) {
        include_once(ROOT_PATH . 'admin/includes/Getui.php');
        $getui = new Getui();
        $message = $db->getRow("SELECT * FROM xm_system_message WHERE am_id=$id");
        if ($message) {
            $title = $message['am_title'];
            $content = $message['am_content'];
            $isWeb = '1';
            if ($message['am_detail']) {
                $isWeb = '2';
            }
            $mtype = '2';

            $custom_content = [
                'id' => $message['am_id'],
                'type' => $mtype,
                'content' => $content,
                'title' => $title,
                'isWeb' => $isWeb
            ];
            $custom_content = json_encode($custom_content);
            $getui->pushMessageToApp($custom_content);
            $sql = "UPDATE xm_system_message SET am_status='2' WHERE am_id=$id";
            if ($db->query($sql)) {
                die;
                clear_cache_files();
                echo json_encode(['status' => 1]);
                die;
            }
        }
    }
} elseif ($_REQUEST['act'] == 'edit') {
    $id = intval($_GET['am_id']);
    $smarty = $GLOBALS['smarty'];
    $message = $GLOBALS['db']->getRow("SELECT * FROM xm_system_message WHERE am_id={$id}");
    create_html_editor('detail', htmlspecialchars($message['am_detail']));
    $smarty->assign('action_link', array('href' => 'app_message.php?act=message_list', 'text' => '返回列表'));
    $smarty->assign('form_act', 'update');
    $smarty->assign('message', $message);
    $smarty->assign('action', 'update_message');

    assign_query_info();
    $smarty->display('home/message_info.htm');

} elseif ($_REQUEST['act'] == 'update') {
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    /* 初始化变量 */
    $title = trim($_REQUEST['title']);
    $content = trim($_REQUEST['content']);
    $detail = empty($_POST['detail']) ? '' : $_POST['detail'];
    $am_id = intval($_POST['am_id']);
    if (empty($am_id)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('参数出错', 0, $link);
    }
    if (empty($title)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('标题不能为空', 0, $link);
    }
    if (empty($content)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('内容不能为空', 0, $link);
    }

    if ((isset($_FILES['thumb']['error']) && $_FILES['thumb']['error'] == 0) || (!isset($_FILES['thumb']['error']) && isset($_FILES['thumb']['tmp_name']) && $_FILES['thumb']['tmp_name'] != 'none')) {
        $original_img = "am_img ='" . 'message/' . basename($image->upload_image($_FILES['thumb'], 'message')) . "',"; // 原始图片
    } else {
        if (!empty($_POST['thumb_hidden'])) {
            $original_img = "am_img = '$_POST[thumb_hidden]', ";
        } else {
            $original_img = '';
        }
    }

    /* 更新信息 */
    $sql = "UPDATE " . $ecs->table('system_message') . " SET " .
        "am_title = '$title', " .
        "am_content     = '$content', " .
        $original_img .
        "am_detail     = '$detail' " .
        "WHERE am_id = '$am_id'";
    $db->query($sql);


    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'app_attr.php?act=attr_list';
    sys_msg("添加成功" . $_LANG['attradd_succed'], 0, $link);

}


function get_attrlist()
{
    $filter = array();

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('system_message') . '  WHERE am_delete=0';
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT * ' .
        'FROM ' . $GLOBALS['ecs']->table('system_message') . ' WHERE am_delete=0 ' .
        'ORDER by am_id';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        $rows['am_gmt_create'] = date("Y-m-d H:i:s", $rows['am_gmt_create']);
        $arr[] = $rows;
    }

    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}








