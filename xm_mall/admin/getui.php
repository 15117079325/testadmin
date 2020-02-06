<?php

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
require_once(dirname(__FILE__) . '/includes/Getui.php');
if ($_REQUEST['act'] == 'send_list') {
    //检查权限
    admin_priv('getui_send');

    //查询所有推送消息
//    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('getui');
//    $getui = $GLOBALS['db']->getAll($sql);
    $getui_list = getui_list();

    $smarty->assign('getui_list',$getui_list['getui']);
    $smarty->assign('filter', $getui_list['filter']);
    $smarty->assign('record_count', $getui_list['record_count']);
    $smarty->assign('page_count', $getui_list['page_count']);
    $smarty->assign('is_status', $getui_list['is_status']);
    $smarty->assign('full_page',1);
    $smarty->display('getui/send_list.html');

}
elseif ($_REQUEST['act'] == 'query') {

    $smarty = $GLOBALS['smarty'];
    $getui_list = getui_list();
    $smarty->assign('getui_list', $getui_list['getui']);
    $smarty->assign('filter', $getui_list['filter']);
    $smarty->assign('record_count', $getui_list['record_count']);
    $smarty->assign('page_count', $getui_list['page_count']);
    make_json_result($smarty->fetch('getui/send_list.html'), '', array('filter' => $getui_list['filter'], 'page_count' => $getui_list['page_count']));

}
else if($_REQUEST['act'] == 'create') {

    $goods = $GLOBALS['db']->getAll("SELECT p_id,p_title FROM xm_product WHERE p_delete=1 AND p_putaway=1");
    $smarty->assign('form_act','insert');
    $smarty->assign('is_add', true);
    $smarty->assign('goods',$goods);
    $smarty->display('getui/send_info.html');

} else if($_REQUEST['act'] == 'insert') {
    //当前时间
    $now = date('Y-m-d h:i:s');
    //标题
    $title = $_POST['title'];
    //内容
    $content = $_POST['content'];
    //类型
    $type = $_POST['type'];
    //跳转链接
    $jump_targets = $_POST['jump_targets'];
    //商品ID
    $goods_id =$_POST['goods_id'];

    //判断是否会存在空值
    if (empty($title)) {
        sys_msg("标题不能为空");
    }
    if (empty($content)) {
        sys_msg("内容不能为空");
    }
    if (empty($type)) {
        sys_msg("类型不能为空");
    }
    if($type == 1) {
        $jump_targets = $goods_id;
    }else if ($type == 2) {
        $jump_targets = $jump_targets;
    } else {
        $jump_targets = '';
    }

    $sql = "INSERT INTO `xm_getui`(`title`, `content`, `jump_targets`, `type`, `status`, `create_time`, `update_time`) VALUE('{$title}','{$content}','{$jump_targets}','{$type}',1,'$now','$now')";
    $db->query($sql);

    $href[] = array('text' => '创建成功', 'href' => 'getui.php?act=send_list');
    sys_msg('创建成功', 0, $href);

} else if($_REQUEST['act'] == 'edit') {
    //得到要编辑的ID
    $id = $_GET['id'];

    if(empty($id)) {
        sys_msg("非法操作");
    }

    //查询出信息
    $sql = "SELECT * FROM xm_getui WHERE id = {$id}";
    $goods = $GLOBALS['db']->getAll("SELECT p_id,p_title FROM xm_product WHERE p_delete=1 AND p_putaway=1");
    $getui = $db->getRow($sql);

    $smarty->assign('getui',$getui);
    $smarty->assign('form_act','update');
    $smarty->assign('goods',$goods);
    $smarty->assign('is_add', false);
    $smarty->display('getui/send_info.html');
} else if($_REQUEST['act'] == 'update') {
    //当前时间
    $now = date('Y-m-d h:i:s');
    //id
    $id = $_POST['id'];
    //标题
    $title = $_POST['title'];
    //内容
    $content = $_POST['content'];
    //类型
    $type = $_POST['type'];
    //跳转链接
    $jump_targets = $_POST['jump_targets'];
    //商品ID
    $goods_id =$_POST['goods_id'];

    //判断是否会存在空值
    if (empty($title)) {
        sys_msg("标题不能为空");
    }
    if (empty($content)) {
        sys_msg("内容不能为空");
    }
    if (empty($type)) {
        sys_msg("类型不能为空");
    }
    if($type == 1) {
        $jump_targets = $goods_id;
    } else if ($type == 2) {
        $jump_targets = $jump_targets;
    } else {
        $jump_targets = '';
    }

    $sql = "UPDATE `xm_getui` SET `title`='{$title}',`content`='{$content}',`jump_targets`='{$jump_targets}',`type`='{$type}',`status`=1,`update_time`=now() WHERE id = '{$id}'";
    $db->query($sql);

    $href[] = array('text' => '更新成功', 'href' => 'getui.php?act=send_list');
    sys_msg('更新成功', 0, $href);
} else if($_REQUEST['act'] == 'audit_list') {
    //检查权限
    admin_priv('getui_audit');

    //查询所有推送消息
    $getui_list = getui_list();

    $smarty->assign('getui_list',$getui_list['getui']);
    $smarty->assign('filter', $getui_list['filter']);
    $smarty->assign('record_count', $getui_list['record_count']);
    $smarty->assign('page_count', $getui_list['page_count']);
    $smarty->assign('full_page',1);
    $smarty->assign('is_status', $getui_list['is_status']);
    $smarty->display('getui/audit_list.html');
} else if ($_REQUEST['act'] == 'send_msg') {

    $Getui = new Getui();

    $id = $_POST['id'];

    if(empty($id)) {
        sys_msg("非法操作");
    }

    $sql = "SELECT * FROM xm_getui WHERE id = {$id}";
    $getui = $db->getRow($sql);

    if($getui['status'] != 1) {
        echo json_encode(['status' => '2', 'msg' => '操作失败']);
        die;
    }

    //发送通知
//    $Getui->pushMessageToSingle('d642b89654b9843ddbfff02770c6664d',$getui);
//
//    $Getui->pushMessageToSingle('698cae70aa1f6a6a5e76ac8362897602',$getui);
//
//    $Getui->pushMessageToSingle('87bd5040aec0671fc78c6c7fc5c5b0e0',$getui);

    //发送群推消息
    $Getui->pushMessageToApp($getui);

    admin_log('','1','','通过验证，进行推送 '.$getui['title']);

    $sql = "UPDATE `xm_getui` SET `status` = 2 WHERE id = '{$id}'";
    $db->query($sql);

    echo json_encode(['status' => '1', 'msg' => '审核通过']);
    die;
} else if($_REQUEST['act'] == 'no_send_msg') {
    $id = $_POST['id'];

    if(empty($id)) {
        sys_msg("非法操作");
    }

    $sql = "SELECT * FROM xm_getui WHERE id = {$id}";
    $getui = $db->getRow($sql);

    if($getui['status'] == 2) {
        echo json_encode(['status' => '2', 'msg' => '操作失败']);
        die;
    }

    $sql = "UPDATE `xm_getui` SET `status` = 3 WHERE id = '{$id}'";
    $db->query($sql);

    echo json_encode(['status' => '3', 'msg' => '已经拒绝']);
    die;
}

function getui_list() {

    //状态 1 待审核 2 已审核  3 已拒绝
    $is_status = $_POST['is_status'];

    if(empty($is_status)) {
        $is_status = 1;
    }

    $where = ' WHERE status = '.$is_status;

    /* 分页大小 */
    $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

    if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
        $filter['page_size'] = intval($_REQUEST['page_size']);
    } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
        $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
    } else {
        $filter['page_size'] = 15;
    }

    $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('getui').$where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

    $sql = "SELECT *" .
        " FROM " . $GLOBALS['ecs']->table('getui') .
        $where .
        " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    set_filter($filter, $sql);

    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row AS $key => $value ) {

        switch ($value['status']) {
            case 1:
                $status_back = '待审核';
                break;
            case 2:
                $status_back = '已审核';
                break;
            case 3:
                $status_back = '已拒绝';
                break;
            case 4:
                $status_back = '已删除';
            default :
                break;
        }
        $row[$key]['status'] = $status_back;

        switch ($value['type']) {
            case 1:
                $type_back = '商品';
                break;
            case 2:
                $type_back = 'web页面';
                break;
            case 3:
                $type_back = '火粉社区';
                break;
            case 4:
                $type_back = '普通消息';
            default :
                break;
        }
        $row[$key]['type'] = $type_back;
    }
    $arr = array('getui' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'], 'is_status' => $is_status);

    return $arr;

}
?>