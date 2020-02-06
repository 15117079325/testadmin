<?php

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
require_once(dirname(__FILE__) . '/includes/Getui.php');
include_once(ROOT_PATH . 'includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
if ($_REQUEST['act'] == 'list') {
    //检查权限
    admin_priv('community_content');


    $community = community_list();

    $smarty->assign('community_list',$community['community']);
    $smarty->assign('filter', $community['filter']);
    $smarty->assign('record_count', $community['record_count']);
    $smarty->assign('page_count', $community['page_count']);
    $smarty->assign('is_status', $community['is_status']);
    $smarty->assign('full_page',1);
    $smarty->display('community_list.html');

}
elseif ($_REQUEST['act'] == 'query') {

    $smarty = $GLOBALS['smarty'];
    $community = community_list();
    $smarty->assign('community_list', $community['community']);
    $smarty->assign('filter', $community['filter']);
    $smarty->assign('record_count', $community['record_count']);
    $smarty->assign('page_count', $community['page_count']);
    make_json_result($smarty->fetch('community_list.html'), '', array('filter' => $community['filter'], 'page_count' => $community['page_count']));

}
else if($_REQUEST['act'] == 'create') {

    $community = community_list();

    $smarty->assign('form_act','insert');
    $smarty->assign('is_add', true);
    create_html_editor('content', '');
    $smarty->display('community_info.html');

} else if($_REQUEST['act'] == 'insert') {
    //当前时间
    $now = date('Y-m-d h:i:s');
    //id
    $id = $_POST['id'];
    //标题
    $title = $_POST['title'];
    //描述
    $comm_describe = $_POST['comm_describe'];
    //内容
    $content = $_POST['content'];
    //状态
    $status = $_POST['status'];
    //排序
    $sort = $_POST['sort'];


    //判断是否会存在空值
    if (empty($title)) {
        sys_msg("标题不能为空");
    }
    if (empty($content)) {
        sys_msg("内容不能为空");
    }
    if (empty($status)) {
        sys_msg("状态不能为空");
    }
    if(empty($comm_describe)) {
        sys_msg('描述不能为空');
    }

    // 如果上传了商品图片，相应处理
    if (($_FILES['img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('community') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'community/' . basename($image->upload_image($_FILES['img'], 'community')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $xm_community_img = $original_img;   // 商品图片

    } else {
        $xm_community_img = $_POST['img_hidden'];
        if(empty($xm_community_img)) {
            sys_msg('图片不能为空');
        }
    }

    $sql = "INSERT INTO `xm_community`(`title`, `content`, `img_src`, `class_id`, `create_time`, `update_time`, `status`,`comm_describe`,`sort`) VALUE('{$title}','{$content}','{$xm_community_img}',0,'$now','$now','{$status}','{$comm_describe}','{$sort}')";
    $db->query($sql);

    $href[] = array('text' => '创建成功', 'href' => 'community.php?act=list');
    sys_msg('创建成功', 0, $href);

} else if($_REQUEST['act'] == 'edit') {
    //得到要编辑的ID
    $id = $_GET['id'];

    if(empty($id)) {
        sys_msg("非法操作");
    }

    //查询出信息
    $sql = "SELECT * FROM xm_community WHERE id = {$id}";
    $community = $db->getRow($sql);

    $smarty->assign('community',$community);
    $smarty->assign('form_act','update');
    $smarty->assign('is_add', false);
    create_html_editor('content', htmlspecialchars($community['content']));
    $smarty->display('community_info.html');

} else if($_REQUEST['act'] == 'update') {
    //当前时间
    $now = date('Y-m-d h:i:s');
    //id
    $id = $_POST['id'];
    //标题
    $title = $_POST['title'];
    //描述
    $comm_describe = $_POST['comm_describe'];
    //内容
    $content = $_POST['content'];
    //状态
    $status = $_POST['status'];
    //排序
    $sort = $_POST['sort'];
    //阅读数
    $show_num = $_POST['show_num'];


    //判断是否会存在空值
    if (empty($title)) {
        sys_msg("标题不能为空");
    }
    if (empty($content)) {
        sys_msg("内容不能为空");
    }
    if (empty($status)) {
        sys_msg("状态不能为空");
    }
    if(empty($comm_describe)) {
        sys_msg('描述不能为空');
    }

    // 如果上传了商品图片，相应处理
    if (($_FILES['img']['tmp_name'] != '')) {
        if ($_REQUEST['id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT * " .
                " FROM " . $ecs->table('community') .
                " WHERE id = '$_REQUEST[id]'";
            $row = $db->getRow($sql);
            if ($row['img_src'] != '' && is_file('../' . $row['img_src'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['img_src']);
            }

        }
        $original_img = 'community/' . basename($image->upload_image($_FILES['img'], 'community')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $xm_community_img = $original_img;   // 商品图片

    } else {
        $xm_community_img = $_POST['img_hidden'];
    }

    $sql = "UPDATE `xm_community` SET `title`='{$title}',`content`='{$content}',`status`='{$status}',`update_time`=now(),`img_src`='{$xm_community_img}',`comm_describe`='{$comm_describe}',`sort`='{$sort}',`show_num`='{$show_num}' WHERE id = '{$id}'";
    $db->query($sql);

    $href[] = array('text' => '更新成功', 'href' => 'community.php?act=list');
    sys_msg('更新成功', 0, $href);
} else if($_REQUEST['act'] == 'remove') {

    $id = intval($_POST['id']);
    $status = intval($_POST['status']);
    if(empty($id)) {
        sys_msg('非法操作');
    }

    if ( $status == 2) {
        $status = 1;
    } elseif ( $status == 1) {
        $status = 2;
    } else {
        $status = empty($status) ? 1 : 0;
    }

    $sql = "UPDATE  xm_community SET status=$status  WHERE id=$id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => $status]);
        die;
    }
} else if($_REQUEST['act'] == 'remove_community') {

    $id = intval($_POST['id']);

    if(empty($id)) {
        sys_msg('非法操作');
    }

    $sql = "DELETE FROM `xm_community` WHERE  id=$id";
    if ($db->query($sql)) {

        echo json_encode(['status' => 1]);
        die;
    }

}

function community_list() {

    /* 分页大小 */
    $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

    if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
        $filter['page_size'] = intval($_REQUEST['page_size']);
    } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
        $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
    } else {
        $filter['page_size'] = 15;
    }

    $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('community');
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

    $sql = "SELECT *" .
        " FROM " . $GLOBALS['ecs']->table('community') .
        " ORDER BY sort DESC".
        " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    set_filter($filter, $sql);

    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row AS $key => $value ) {

//        switch ($value['status']) {
//            case 1:
//                $status_back = '正常';
//                break;
//            case 2:
//                $status_back = '不显示';
//                break;
//            default :
//                break;
//        }
//        $row[$key]['status'] = $status_back;

        $row[$key]['img_src'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/' .$row[$key]['img_src']);
    }

    $arr = array('community' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;

}
?>