<?php
use maqu\Models\User;
use maqu\Models\AdminUser;

/**
 * ECSHOP 用户意见反馈程序
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: suggestions_manage.php 17217 2018-01-28 fuhuaquan
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/* act操作项的初始化 */
if (empty($_REQUEST['act'])) {
    $_REQUEST['act'] = 'list';
} else {
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

/*------------------------------------------------------ */
//-- 获取没有回复的意见列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list') {
    /* 检查权限 */
    admin_priv('suggestions_manage');
    $smarty->assign('ur_here', '意见反馈');

    $smarty->assign('full_page', 1);

    $list = get_suggestions_list();

    $smarty->assign('suggestions_list', $list['item']);
    $smarty->assign('filter', $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count', $list['page_count']);

    assign_query_info();
    $smarty->display('suggestions_list.htm');
}

/*------------------------------------------------------ */
//-- 翻页、搜索、排序
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'query') {
    $list = get_suggestions_list();
    $smarty->assign('suggestions_list', $list['item']);
    $smarty->assign('filter', $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count', $list['page_count']);
    make_json_result($smarty->fetch('suggestions_list.htm'), '',
        array('filter' => $list['filter'], 'page_count' => $list['page_count']));
}

/* 处理反馈的意见 */
if ($_REQUEST['act'] == 'reply') {
    /* 检查权限 */
    admin_priv('suggestions_manage');

    $sugg_info = array();
    /* 获取IP地址 */
    $ip = real_ip();

    /* 获取评论详细信息并进行字符处理 */
    $sugg_info = Suggestions::where('id', $_REQUEST['id'])->first()->toArray();
    $sugg_info['ip_address'] = $ip;

    $info = User::where('user_id', $sugg_info['user_id'])->first();
    $sugg_info['user_name'] = $info->user_name;

    $sugg_info['content'] = str_replace('\r\n', '<br />', htmlspecialchars($sugg_info['content']));
    $sugg_info['content'] = nl2br(str_replace('\n', '<br />', $sugg_info['content']));
    $sugg_info['add_time'] = local_date($_CFG['time_format'], $sugg_info['add_time']);
    $sugg_info['update_time'] = local_date($_CFG['time_format'], $sugg_info['update_time']);

    /* 获取管理员的用户名和Email地址 */
    if ($sugg_info['admin_id']) {
        $admin_info = AdminUser::where('user_id', $sugg_info['admin_id'])->select('user_name', 'email')->first()->toArray();
    } else {
        $admin_info = "";
    }
    /* 模板赋值 */
    $smarty->assign('msg', $sugg_info); //评论信息

    $smarty->assign('admin_info', $admin_info);   //管理员信息

    $smarty->assign('send_fail', !empty($_REQUEST['send_ok']));

    $smarty->assign('ur_here', '意见反馈');
    $smarty->assign('action_link', array('text' => '意见反馈',
        'href' => 'suggestions_manage.php?act=list'));

    /* 页面显示 */
    assign_query_info();
    $smarty->display('suggestions_info.htm');
}

if ($_REQUEST['act'] == 'action') {
    admin_priv('suggestions_manage');
    //备注
    $remarks = $_REQUEST['remarks'];
    $status = $_REQUEST['status'];
    $id = $_REQUEST['id'];
    $admin_id = $_SESSION['admin_id'];
    if ($status) {
        $info = Suggestions::where('id', $id)->first();
        $info->status = $status;
        $info->update_time = time();
        $info->remarks = $remarks;
        $info->admin_id = $admin_id;
        $res = $info->save();
    } else {
        $info = Suggestions::where('id', $id)->first();
        $info->status = $status;
        $info->update_time = time();
        $info->remarks = $remarks;
        $info->admin_id = $admin_id;
        $res = $info->save();
    }
    $links[] = array('text' => '返回', 'href' => 'suggestions_manage.php?act=list');
    if ($res) {
        /* 记录管理员操作 */
        admin_log(addslashes('意见处理'), 'edit', 'suggestions_manage');
        sys_msg('意见处理成功！', 0, $links);
    } else {
        sys_msg('操作失败，请稍后再试！', 1, $links);
    }


}

//-- 删除某一条意见
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove') {
    check_authz_json('suggestions_manage');

    $id = intval($_GET['id']);

    $sql = "DELETE FROM " . $ecs->table('feedbacks') . " WHERE fb_id = '$id'";
    $res = $db->query($sql);
    if ($res) {
        echo "{'status':'success','msg':'删除成功！'}";
    } else {
        echo "{'status':'failure','msg':'删除失败，请稍后再试！'}";
    }
}

//-- 批量删除用户评论
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'batch') {
    admin_priv('suggestions_manage');
    // $action = isset($_POST['sel_action']) ? trim($_POST['sel_action']) : 'deny';
    $ids = $_GET['ids'];
    $array = explode(",", $ids);
    if (empty($array)) {
        echo "{'status':'failure','msg':'请选择要删除的编号'}";
    }
    $res = \maqu\Models\Feedback::whereIn('fb_id', $array)->delete();
    if ($res) {
        echo "{'status':'success','msg':'删除成功！'}";
    } else {
        echo "{'status':'failure','msg':'删除失败！'}";
    }
}

if($_REQUEST['act'] == 'update_status'){


    $fb_id = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $status = intval($_POST['status']);
    $status = $status==1 ? 2 : 1;
    $sql = "UPDATE xm_feedbacks SET $key=$status WHERE fb_id=$fb_id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => $status]);
        die;
    }
}

/**
 * 获取意见列表
 * @access  public
 * @return  array
 */
function get_suggestions_list()
{
    /* 查询条件 */
    $filter['keywords'] = empty($_REQUEST['keywords']) ? 0 : trim($_REQUEST['keywords']);
    $filter['status'] = empty($_REQUEST['status']) ? 0 : trim($_REQUEST['status']);
    if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1) {
        $filter['keywords'] = json_str_iconv($filter['keywords']);
    }
    $where = " WHERE 1 ";
    if($filter['keywords']){
        $where .= " AND f.fb_content LIKE '%{$filter['keywords']}%'";
    }
    if($filter['status']){
        $where .= " AND f.fb_status={$filter['status']}";
    }

        /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('feedbacks') . ' f '.$where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);


    /* 获得广告数据 */
    $arr = array();
    $sql = "SELECT f.*,u.user_name,u.mobile_phone FROM xm_feedbacks f LEFT JOIN xm_users u ON f.user_id=u.user_id {$where} ORDER BY f.fb_id DESC ";

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);
    while ($rows = $GLOBALS['db']->fetchRow($res)) {
        $rows['fb_content'] = base64_decode($rows['fb_content']);
        $rows['fb_gmt_create'] = date("Y-m-d H:i:s",$rows['fb_gmt_create']);
        if($rows['fb_imgs']){
            $imgs = explode('|',$rows['fb_imgs']);
            foreach($imgs as $k=>$v){
               $rows['image'][]=\maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/'.$v);
            }
        }
        $arr[] = $rows;
    }

    return array('item' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}


