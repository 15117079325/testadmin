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
$exc   = new exchange($ecs->table("ad"), $db, 'ad_id', 'ad_name');

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

/*------------------------------------------------------ */
//-- 广告列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    $pid = !empty($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0;


    $smarty->assign('action_link', array('text' => '添加客服', 'href' => 'home_service.php?act=add'));

    $smarty->assign('full_page',  1);
    $sql = "SELECT u.user_name,s.service_id,s.type FROM xm_service_users s LEFT JOIN xm_users u ON u.user_id=s.user_id";
    $ads_list = $db->query($sql);

    $smarty->assign('nav_list',     $ads_list);


    assign_query_info();
    $smarty->display('home/home_service.htm');
}


/*------------------------------------------------------ */
//-- 添加新广告页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add')
{

    $smarty->assign('action_link',   array('href' => 'home_service.php?act=list', 'text' => '返回列表'));

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action',   'add');
    $smarty->assign('cfg_lang', $_CFG['lang']);

    assign_query_info();
    $smarty->display('home/home_service_info.htm');
}

/*------------------------------------------------------ */
//-- 新广告的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert')
{


    /* 初始化变量 */
    $name    = $_POST['name'];
    $type = $_POST['type'];
    if(empty($name)){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('客服账号不能为空', 0, $link);
    }
    $user_id = $db->getOne("SELECT user_id FROM xm_users WHERE user_name='{$name}'");
    if(empty($user_id)){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('客服账号不存在,请重新填写', 0, $link);
    }
    /* 插入数据 */
    $sql = "INSERT INTO ".$ecs->table('service_users'). " (user_id,type)
    VALUES ('$user_id',$type)";
    $db->query($sql);
    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'home_hot_search.php?act=list';
    sys_msg($_LANG['add'] . "&nbsp;" .$_POST['name'] . "&nbsp;" . $_LANG['attradd_succed'],0, $link);
}


/*------------------------------------------------------ */
//-- 广告编辑页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit')
{


    /* 获取广告数据 */
    $sql = "SELECT s.*,u.user_name FROM " .$ecs->table('service_users'). " s LEFT JOIN xm_users u ON u.user_id=s.user_id WHERE service_id='".intval($_REQUEST['id'])."'";
    $ads_arr = $db->getRow($sql);

    $smarty->assign('action_link',   array('href' => 'home_service.php?act=list', 'text' => '返回列表页'));
    $smarty->assign('form_act',      'update');
    $smarty->assign('action',        'edit');
    $smarty->assign('nav',           $ads_arr);

    assign_query_info();
    $smarty->display('home/home_service_info.htm');
}

/*------------------------------------------------------ */
//-- 广告编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update')
{

    /* 初始化变量 */
    $id   = !empty($_POST['id'])   ? intval($_POST['id'])   : 0;
    $name    = $_POST['name'];
    $type = $_POST['type'];
    $user_id = $db->getOne("SELECT user_id FROM xm_users WHERE user_name='{$name}'");
    if(empty($user_id)){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('客服账号不存在,请重新填写', 0, $link);
    }
    /* 更新信息 */
    $sql = "UPDATE " .$ecs->table('service_users'). " SET ".
        "user_id     = '$user_id' ,".
        "type     = '$type' ".
        "WHERE service_id = '$id'";
    $db->query($sql);

    /* 提示信息 */
    $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'home_service.php?act=list');
    sys_msg($_LANG['edit'] .' '.$_POST['ad_name'].' '. $_LANG['attradd_succed'], 0, $href);

}

/*------------------------------------------------------ */
//-- 删除广告位置
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('ad_manage');

    $id = intval($_POST['id']);
    $sql = 'DELETE FROM ' . $ecs->table('service_users') . " WHERE service_id={$id}";
    $id = $db->query($sql);

    if($id){
        echo json_encode(['status'=>1]);
    }
    exit;
} else if($_REQUEST['act'] == 'share_list') {

    $smarty->assign('full_page',  1);
    $sql = "SELECT * FROM xm_share_img order by p_sort desc ";
    $share_list = $GLOBALS['db']->getAll($sql);

    /* 格式化数据 */
    foreach ($share_list AS $key => $value) {
        $share_list[$key]['img_src'] = "https://" . STATIC_RESOURCE_URL . '/data/' . $value['img_src'];
    }

    $smarty->assign('share_list',$share_list);

    $smarty->display('home/share_img.html');
} elseif ($_REQUEST['act'] == 'add_share') {

    $smarty->assign('action_link',array('href' => 'home_service.php?act=share_list', 'text' => '返回列表'));
    $smarty->assign('form_act','insert_share');

    assign_query_info();
    $smarty->display('home/share_img_info.html');

} else if ($_REQUEST['act'] == 'insert_share') {

    $p_sort = $_POST['share_p_sort'];

    // 如果上传了商品图片，相应处理
    if (($_FILES['share_img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('share_img') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'share/' . basename($image->upload_image($_FILES['share_img'], 'share')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $share_img = $original_img;   // 商品图片

    } else {
        $share_img = $_POST['share_img_hidden'];
    }

    $sql = "INSERT INTO `xm_share_img`(`id`, `img_src`, `status`,`p_sort`) VALUES (null,'{$share_img}',1,'{$p_sort}')";
    $db->query($sql);

    $href[] = array('text' => '创建成功', 'href' => 'home_service.php?act=share_list');
    sys_msg('创建成功', 0, $href);
} else if($_REQUEST['act'] == 'edit_share') {

    $sql = "SELECT * FROM " .$ecs->table('share_img'). " WHERE id='".intval($_REQUEST['id'])."'";
    $ads_arr = $db->getRow($sql);

    $smarty->assign('action_link',   array('href' => 'home_service.php?act=share_list', 'text' => '返回列表'));
    $smarty->assign('form_act',      'update_share');
    $smarty->assign('share',           $ads_arr);

    assign_query_info();
    $smarty->display('home/share_img_info.html');

} else if($_REQUEST['act'] == 'update_share') {

    $id = $_POST['id'];

    $p_sort = $_POST['share_p_sort'];

    // 如果上传了商品图片，相应处理
    if (($_FILES['share_img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('share_img') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'share/' . basename($image->upload_image($_FILES['share_img'], 'share')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $share_img = $original_img;   // 商品图片

    } else {
        $share_img = $_POST['share_img_hidden'];
    }

    $sql = "UPDATE `xm_share_img` SET `img_src`='{$share_img}',`p_sort`='{$p_sort}' WHERE id = '{$id}'";
    $db->query($sql);

    $href[] = array('text' => '更新成功', 'href' => 'home_service.php?act=share_list');
    sys_msg('更新成功', 0, $href);
} else if($_REQUEST['act'] == 'remove_share') {

    $id = intval($_POST['id']);
    $sql = 'DELETE FROM ' . $ecs->table('share_img') . " WHERE id={$id}";
    $id = $db->query($sql);

    if($id){
        echo json_encode(['status'=>1]);
    }
    exit;
} else if($_REQUEST['act'] == 'share_list') {

    $smarty->assign('full_page',  1);
    $sql = "SELECT * FROM xm_share_img";
    $share_list = $GLOBALS['db']->getAll($sql);

    /* 格式化数据 */
    foreach ($share_list AS $key => $value) {
        $share_list[$key]['img_src'] = "https://" . STATIC_RESOURCE_URL . '/data/' . $value['img_src'];
    }

    $smarty->assign('share_list',$share_list);

    $smarty->display('home/share_img.html');
} elseif ($_REQUEST['act'] == 'add_share') {

    $smarty->assign('action_link',array('href' => 'home_service.php?act=share_list', 'text' => '返回列表'));
    $smarty->assign('form_act','insert_share');

    assign_query_info();
    $smarty->display('home/share_img_info.html');

} else if ($_REQUEST['act'] == 'insert_share') {
    // 如果上传了商品图片，相应处理
    if (($_FILES['share_img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('share_img') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'share/' . basename($image->upload_image($_FILES['share_img'], 'share')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $share_img = $original_img;   // 商品图片

    } else {
        $share_img = $_POST['share_img_hidden'];
    }

    $sql = "INSERT INTO `xm_share_img`(`id`, `img_src`, `status`) VALUES (null,'{$share_img}',1)";
    $db->query($sql);

    $href[] = array('text' => '创建成功', 'href' => 'home_service.php?act=share_list');
    sys_msg('创建成功', 0, $href);
} else if($_REQUEST['act'] == 'edit_share') {

    $sql = "SELECT * FROM " .$ecs->table('share_img'). " WHERE id='".intval($_REQUEST['id'])."'";
    $ads_arr = $db->getRow($sql);

    $smarty->assign('action_link',   array('href' => 'home_service.php?act=share_list', 'text' => '返回列表'));
    $smarty->assign('form_act',      'update_share');
    $smarty->assign('share',           $ads_arr);

    assign_query_info();
    $smarty->display('home/share_img_info.html');

} else if($_REQUEST['act'] == 'update_share') {

    $id = $_POST['id'];
    // 如果上传了商品图片，相应处理
    if (($_FILES['share_img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('share_img') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'share/' . basename($image->upload_image($_FILES['share_img'], 'share')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $share_img = $original_img;   // 商品图片

    } else {
        $share_img = $_POST['share_img_hidden'];
    }

    $sql = "UPDATE `xm_share_img` SET `img_src`='{$share_img}' WHERE id = '{$id}'";
    $db->query($sql);

    $href[] = array('text' => '更新成功', 'href' => 'home_service.php?act=share_list');
    sys_msg('更新成功', 0, $href);
} else if($_REQUEST['act'] == 'remove_share') {

    $id = intval($_POST['id']);
    $sql = 'DELETE FROM ' . $ecs->table('share_img') . " WHERE id={$id}";
    $id = $db->query($sql);

    if($id){
        echo json_encode(['status'=>1]);
    }
    exit;
} else if($_REQUEST['act'] == 'alert_list') {
    $smarty->assign('full_page',  1);

    $alert_list = alert_list();

    $smarty->assign('filter', $alert_list['filter']);
    $smarty->assign('record_count', $alert_list['record_count']);
    $smarty->assign('page_count', $alert_list['page_count']);
    $smarty->assign('is_status', $alert_list['is_status']);
    $smarty->assign('alert_list',$alert_list['alert_img']);

    $smarty->display('home/alert_img.html');
} else if($_REQUEST['act'] == 'edit_alert') {
    $id = $_GET['id'];

    if(empty($id)) {
        sys_msg('非法操作');
    }

    $sql = "SELECT * FROM " .$ecs->table('alert_img'). " WHERE id='".intval($id)."'";
    $ads_arr = $db->getRow($sql);

    $smarty->assign('action_link',   array('href' => 'home_service.php?act=alert_list', 'text' => '返回列表'));
    $smarty->assign('form_act',      'update_alert');
    $smarty->assign('alert_img',           $ads_arr);

    assign_query_info();
    $smarty->display('home/alert_img_info.html');
} else if($_REQUEST['act'] == 'update_alert') {
    $id = $_POST['id'];
    //标题
    $title = $_POST['title'];
    //类型
    $type = $_POST['type'];
    //状态
    $status = $_POST['status'];
    //跳转目标
    $target = $_POST['target'];
    if(empty(id)) {
        sys_msg('非法操作');
    }

    // 如果上传了商品图片，相应处理
    if (($_FILES['alert_img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('alert_img') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'alert_img/' . basename($image->upload_image($_FILES['alert_img'], 'alert_img')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $alert_img = $original_img;   // 商品图片

    } else {
        $alert_img = $_POST['alert_img_hidden'];
    }

    $sql = "UPDATE `xm_alert_img` SET `img_src`='{$alert_img}',`title`='{$title}',`target`='{$target}',`type`='{$type}',`status`='{$status}' WHERE id = '{$id}'";
    $db->query($sql);

    $href[] = array('text' => '更新成功', 'href' => 'home_service.php?act=alert_list');
    sys_msg('更新成功', 0, $href);
} else if($_REQUEST['act'] == 'add_alert') {
    $smarty->assign('action_link',array('href' => 'home_service.php?act=alert_list', 'text' => '返回列表'));
    $smarty->assign('form_act','insert_alert');

    assign_query_info();
    $smarty->display('home/alert_img_info.html');
} else if($_REQUEST['act'] == 'insert_alert') {
    $id = $_POST['id'];
    //标题
    $title = $_POST['title'];
    //类型
    $type = $_POST['type'];
    //状态
    $status = $_POST['status'];
    //跳转目标
    $target = $_POST['target'];
    if(empty(id)) {
        sys_msg('非法操作');
    }

    // 如果上传了商品图片，相应处理
    if (($_FILES['alert_img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('alert_img') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'alert_img/' . basename($image->upload_image($_FILES['alert_img'], 'alert_img')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $alert_img = $original_img;   // 商品图片

    } else {
        $alert_img = $_POST['alert_img_hidden'];
    }


    $sql = "INSERT INTO `xm_alert_img`(`id`, `img_src`, `status`,`title`,`target`,`type`) VALUES (null,'{$alert_img}','{$status}','{$title}','{$target}','{$type}')";
    $db->query($sql);

    $href[] = array('text' => '创建成功', 'href' => 'home_service.php?act=alert_list');
    sys_msg('创建成功', 0, $href);
} else if($_REQUEST['act'] == 'remove_alert') {
    $id = intval($_POST['id']);
    $sql = 'DELETE FROM ' . $ecs->table('alert_img') . " WHERE id={$id}";
    $id = $db->query($sql);

    if($id){
        echo json_encode(['status'=>1]);
    }
    exit;
}

function alert_list () {

    $where = '';

    /* 分页大小 */
    $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

    if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
        $filter['page_size'] = intval($_REQUEST['page_size']);
    } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
        $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
    } else {
        $filter['page_size'] = 15;
    }

    $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('alert_img').$where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

    $sql = "SELECT *" .
        " FROM " . $GLOBALS['ecs']->table('alert_img') .
        $where .
        " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    set_filter($filter, $sql);

    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row AS $key => $value ) {

//        switch ($value['status']) {
//            case 1:
//                $status_back = '待审核';
//                break;
//            case 2:
//                $status_back = '已审核';
//                break;
//            case 3:
//                $status_back = '已拒绝';
//                break;
//            case 4:
//                $status_back = '已删除';
//            default :
//                break;
//        }
//        $row[$key]['status'] = $status_back;
//
//        switch ($value['type']) {
//            case 1:
//                $type_back = '商品';
//                break;
//            case 2:
//                $type_back = 'web页面';
//                break;
//            case 3:
//                $type_back = '火粉社区';
//                break;
//            case 4:
//                $type_back = '普通消息';
//            default :
//                break;
//        }
//        $row[$key]['type'] = $type_back;
        $img_src = "https://" . STATIC_RESOURCE_URL . '/data/' . $value['img_src'];
        $row[$key]['img_src'] = $img_src;
    }
    $arr = array('alert_img' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;

}


