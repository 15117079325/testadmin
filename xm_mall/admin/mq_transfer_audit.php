<?php

/**
 * 转账审核管理
 * Author: ji
 * CreatedTime: 2018年1月14日13:02:03
 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');


$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';
/* 路由 */
$function_name = 'action_' . $action;
if (!function_exists($function_name)) {
    $function_name = "action_list";
}
call_user_func($function_name);

/*------------------------------------------------------ */
//-- 转账申请列表
/*------------------------------------------------------ */
function action_list()
{
    $smarty = $GLOBALS['smarty'];

     /* 检查权限 */
     admin_priv('transfer_audit_lists');

    /* 查询 */
    $result = transfer_apply_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', "转账申请记录"); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('transfer_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_transfer_audit/mq_transfer_audit_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
function action_query()
{
    $smarty = $GLOBALS['smarty'];

    check_authz_json('transfer_audit_lists');

    $result = transfer_apply_list();

    $smarty->assign('transfer_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('mq_transfer_audit/mq_transfer_audit_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/*------------------------------------------------------ */
//-- 审核转账申请记录
/*------------------------------------------------------ */
function action_apply_audit()
{
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];

    /* 检查权限 */
    admin_priv('transfer_audit_lists');

    /* 取得商品推荐人信息 */
    $id = $_REQUEST['id'];

    if (!$id) {

        sys_msg('未找到对应的转账申请记录', 1, array(), false);
    }

    $sql = "SELECT a.*,u.user_name AS from_name, u2.user_name AS to_name FROM xm_transfer_apply a LEFT JOIN xm_users u ON a.from_user = u.user_id 
            LEFT JOIN xm_users u2 ON a.to_user = u2.user_id WHERE a.trid = $id";
    $apply = $db->getRow($sql);

    if ($apply === false) {

        sys_msg('未找到对应的转账申请记录', 1, array(), false);
    }

    // 查询当前用户的 新美积分 消费积分
    $sql = "SELECT x.unlimit, t.shopp,t.unlimit as tunlimit FROM xm_xps x LEFT JOIN xm_tps t on x.user_id = t.user_id WHERE x.user_id = ".$apply['from_user'];
    $money = $db->getRow($sql);

    $apply['create_at_format'] = date($GLOBALS['_CFG']['time_format'], $apply['create_at']);

    $smarty->assign('ur_here', '转账申请审核');
    $smarty->assign('action_link', array('href' => 'mq_transfer_audit.php?act=list', 'text' => '转账申请记录'));
    $smarty->assign('form_action', 'act_audit');
    $smarty->assign('apply', $apply);
	$smarty->assign('money',$money);
    assign_query_info();

    $smarty->display('mq_transfer_audit/mq_transfer_audit_info.htm');

}

/*------------------------------------------------------ */
//-- 提交转账审核
/*------------------------------------------------------ */
function action_act_audit()
{
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];

    /* 检查权限 */
    admin_priv('transfer_audit_lists');

    $links[] = array('href' => 'mq_transfer_audit.php?act=list', 'text' => '返回转账申请列表');
    $id=$_POST['id'];

    $apply_id = !empty($_POST['id']) ? trim($_POST['id']) : sys_msg('申请记录不存在', 1, $links, false);
    $audit = !empty($_POST['audit']) ? intval($_POST['audit']) : sys_msg('请勾选审核结果', 1, $links, false);//审核结果
    $app_result = trim($_POST['app_result']);

    if ($audit != 1 && $audit != 2) {

        sys_msg('请勾选审核结果', 1, $links, false);

    }

    if (!$app_result) {

        sys_msg('请填写审核原因', 1, $links, false);
    }

    $sql = "SELECT * FROM xm_transfer_apply where trid = $apply_id";

    $apply_audit = $db->getRow($sql);

    if (!$apply_audit) {

        sys_msg('未找到对应申请记录', 1, $links, false);
    }

    $current = time();
    // 查询当前用户的 新美积分 消费积分
    $sql = "SELECT x.unlimit, t.shopp,t.unlimit as tunlimit FROM xm_xps x LEFT JOIN xm_tps t on x.user_id = t.user_id WHERE x.user_id = ".$apply_audit['from_user'];
    $money = $db->getRow($sql);
    if($apply_audit['type']==0){
        if(($apply_audit['amount']+$apply_audit['trfee'])>$money['unlimit']){
            sys_msg('新美优惠券不足', 1, $links, false);
        }
    }elseif($apply_audit['type']==1){
        if(($apply_audit['amount']+$apply_audit['trfee'])>$money['shopp']){
            sys_msg('消费优惠券不足', 1, $links, false);
        }
    }elseif($apply_audit['type']==2){
        if(($apply_audit['amount']+$apply_audit['trfee'])>$money['tunlimit']){
            sys_msg('T积分优惠券不足', 1, $links, false);
        }
    }

    $sql = "UPDATE xm_transfer_apply SET status = %d, done_at = %d, admin_id = %d, admin_msg = '%s' WHERE trid = %d";
    $sql = sprintf($sql, $audit, $current, $_SESSION['admin_id'], $app_result, $apply_id);
    $ret = $db->query($sql);

    if ($audit == 1) {
        // 同意转账
        include_once ROOT_PATH . 'includes/lib_tran.php';
        if ($apply_audit['type'] == 0) {
            trans_ok($apply_audit['from_user'], $apply_audit['to_user'], $apply_audit['amount'], 'xinmei',$apply_id,$apply_audit['trfee']);
        }elseif($apply_audit['type'] == 1){
            trans_ok($apply_audit['from_user'], $apply_audit['to_user'], $apply_audit['amount'], 'consume',$apply_id,$apply_audit['trfee']);
        }else{
            trans_ok($apply_audit['from_user'], $apply_audit['to_user'], $apply_audit['amount'], 'tjifen',$apply_id,$apply_audit['trfee']);
        }
        
    }

    sys_msg('操作成功', 1, $links, false);
}

/**
 *  获取大金额转账申请列表信息
 */
function transfer_apply_list()
{
    admin_priv('transfer_audit_lists');
    $result = get_filter();
    if ($result === false)
    {
        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'create_at' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $where = 'WHERE 1 ';

        $filter['user_name'] = isset($_REQUEST['user_name']) ? trim($_REQUEST['user_name']) : '';
        $filter['status'] = isset($_REQUEST['status']) ? trim($_REQUEST['status']) : 0;

        if ($filter['user_name'])
        {
            $where .= " AND ( u.user_name LIKE '%" . mysql_like_quote($filter['user_name']) 
            
            . "%' or u.email like  '%" . mysql_like_quote($filter['user_name'])
            . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['user_name']) . "%' ) ";
        }

        $where .= " AND mata.status = '$filter[status]'";

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
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('transfer_apply') ." AS mata ".
                "LEFT JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mata.from_user = u.user_id $where";
        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */
        $sql = "SELECT mata.*, u.user_name,u2.user_name as to_user_name FROM " . $GLOBALS['ecs']->table("transfer_apply") . " AS mata ".
            "LEFT JOIN " . $GLOBALS['ecs']->table("users") ." AS u ON mata.from_user = u.user_id  ".
            "LEFT JOIN " . $GLOBALS['ecs']->table("users") ." AS u2 ON mata.to_user = u2.user_id $where
            
            ORDER BY " . $filter['sort_by'] . " " . $filter['sort_order']. "
            
            LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ", " . $filter['page_size'] . " ";

        set_filter($filter, $sql);

    } else {

        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->query($sql);

    while ($row = $GLOBALS['db']->fetchRow($res)) {

        switch($row['status']){
            case 2 :
                $row['app_status_text'] = '已拒绝';
                break;
            case 1 :
                $row['app_status_text'] = '已审核';
                break;
            default:
                $row['app_status_text'] = '待审核';
        }
        switch($row['type']){
            case '0':
                $row['transfer_type'] = '新美积分';
                break;
            case '1' :
                $row['transfer_type'] = '消费积分';
                break;
            case '2' :
                $row['transfer_type'] = 'T积分';
                break;
        }
        $row['create_at_format'] = date($GLOBALS['_CFG']['time_format'], $row['create_at']);
        $row['update_at_format'] = $row['done_at'] == 0 ? '' : date($GLOBALS['_CFG']['time_format'], $row['done_at']);
        $arr[] = $row;
    }

    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}
