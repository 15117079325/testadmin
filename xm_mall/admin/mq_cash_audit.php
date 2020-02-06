<?php

/**
 * 兑换审核管理
 * Author: fzp
 * E-mail: fuzp@maqu.im
 * CreatedTime: 2017年3月30日
 */


define('IN_ECS', true);
define('CASH_AUDIT_ID', '推荐人id');

require(dirname(__FILE__) . '/includes/init.php');

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';
/* 路由 */
$function_name = 'action_' . $action;
if (!function_exists($function_name)) {
    $function_name = "action_list";
}
call_user_func($function_name);

/*------------------------------------------------------ */
//-- 兑换申请列表页面
/*------------------------------------------------------ */
function action_list()
{
    /* 检查权限 */
    admin_priv('cash_audit_lists');

    $smarty = $GLOBALS['smarty'];

    /* 查询 */
    $result = take_apply_list();

    /* 模板赋值 */
    $smarty->assign('ur_here', "兑换申请记录"); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数
    $smarty->assign('take_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);
    $smarty->assign('sort_id', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_cash_audit_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
function action_query()
{
    $smarty = $GLOBALS['smarty'];

    check_authz_json('cash_audit_lists');

    $result = take_apply_list();

    $smarty->assign('take_apply_list',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('mq_cash_audit_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/*------------------------------------------------------ */
//-- 审核兑换 页面
/*------------------------------------------------------ */
function action_apply_audit()
{
    /* 检查权限 */
    admin_priv('cash_audit_lists');

    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 取得商品推荐人信息 */
    $id = $_REQUEST['id'];

    if (!$id) {

        sys_msg('非法操作', 1, array(), false);
    }

    $sql = "SELECT w.*, b.* FROM %s AS w LEFT JOIN %s AS b ON w.user_id = b.user_id 
            WHERE w.wid = %d AND w.type = 2 AND w.status = 0";
    $sql = sprintf($sql, $ecs->table('wd'), $ecs->table('user_bankinfo'), $id);

    $apply = $db->getRow($sql);

    if ($apply === false) {
        sys_msg('未找到对应的兑换申请记录', 1, array(), false);
    }

    $apply['create_at_format'] = date($GLOBALS['_CFG']['time_format'], $apply['create_at']);

    $smarty->assign('ur_here', '兑换申请审核');
    $smarty->assign('action_link', array('href' => 'mq_cash_audit.php?act=list', 'text' => '兑换申请记录'));
    $smarty->assign('form_action', 'act_audit');
    $smarty->assign('apply', $apply);
    $smarty->assign('money', $apply['amount'] - $apply['fee']);

    $takeapi_flg = 0;

    if(in_array($apply['bank_name'], KQ_SUPPORT_BANK_LIST)) {
        $takeapi_flg = 1;
    }

    $smarty->assign('takeapi_flg',$takeapi_flg);

    assign_query_info();

    $smarty->display('mq_cash_audit_info.htm');

}

/*------------------------------------------------------ */
//-- 审核
/*------------------------------------------------------ */
function action_act_audit() {

    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 检查权限 */
    admin_priv('cash_audit_lists');

    $links[] = array('href' => 'mq_cash_audit.php?act=list', 'text' => '返回兑换申请列表');

    $id = !empty($_POST['id']) ? trim($_POST['id']) : sys_msg('申请记录不存在', 1, $links, false);

    // 2 人工打款 3 拒绝
    $audit = !empty($_POST['audit']) ? intval($_POST['audit']) : sys_msg('请勾选审核结果', 1, $links, false);//审核结果
    $app_result = trim($_POST['app_result']);

    if (!$id) {

        sys_msg('数据错误', 1, array(), false);
    }

    if (!in_array($audit, [2, 3])) {

        sys_msg('数据错误', 1, array(), false);
    }

    $sql = "SELECT * FROM %s WHERE wid = %d AND type = 2 AND status = 0";
    $sql = sprintf($sql, $ecs->table('wd'), $id);

    $apply = $db->getRow($sql);

    if ($apply === false) {
        sys_msg('未找到对应的兑换申请记录', 1, $links, false);
    }

    $current = time();

    // 拒绝
    if ($audit == 3) {

        /// 修改兑换状态
        $change = [

            'status' => 2, //拒绝
            'admin_id' => $_SESSION['admin_id'],
            'status_msg' => $app_result,
            'done_at' => $current
        ];

        $res = $db->autoExecute($ecs->table('wd'), $change, 'UPDATE', 'wid = ' . $id);

        /// 增加账户 T 积分
        $ret = $db->query("UPDATE xm_tps SET unlimit = unlimit + ".$apply['amount'].", update_at = $current WHERE user_id = ".$apply['user_id']);

        if (!$ret) {

            sys_msg('更新账户优惠券错误', 1, array(), false);
        }
        //查出金额
        include_once ROOT_PATH . 'includes/lib_tran.php';
        $points = get_user_points($apply['user_id']);

        /// 添加流水记录
        $new_flow = [

            'user_id' => $apply['user_id'],
            'type' => 2,
            'status' => 1,
            'amount' => $apply['amount'],
            'surplus' => $points['tps']['unlimit'],
            'notes' => '拒绝 T 积分兑换',
            'create_at' => $current
        ];

        $flow = $db->autoExecute($ecs->table('flow_log'), $new_flow, 'INSERT');

        sys_msg('操作成功', 1, $links, false);

    } else if ($audit == 2) {

        /// 已手动打款
        /// 修改兑换状态
        $change = [

            'status' => 1, // 通过
            'admin_id' => $_SESSION['admin_id'],
            'status_msg' => $app_result,
            'done_at' => $current
        ];

        $res = $db->autoExecute($ecs->table('wd'), $change, 'UPDATE', 'wid = ' . $id);

        sys_msg('操作成功', 1, $links, false);
    }
    
}

/*
|- 20180428 16 V 修改优化兑换列表
*/
function take_apply_list()
{

    admin_priv('cash_audit_lists');

    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $result = get_filter();

    if ($result === false) {

        $aiax = isset($_GET['is_ajax']) ? $_GET['is_ajax'] : 0;

        /* 过滤信息 */
        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'create_at' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);
        $filter['user_name'] = isset($_REQUEST['user_name']) ? trim($_REQUEST['user_name']) : '';
        $filter['app_status'] = isset($_REQUEST['app_status']) ? trim($_REQUEST['app_status']) : 0;

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        $filter['page_size'] = 15;
        $limit = 'LIMIT ' . ($filter['page'] - 1) * $filter['page_size'] . ', ' . $filter['page_size'];
        $where = ' WHERE w.type = 2 AND w.status = ' . $filter['app_status'];

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);

        } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {

            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }

        if($filter['user_name']) {

            $where .= " AND (u.mobile_phone LIKE '%" . mysql_like_quote($filter['user_name']) . "%' OR".
                    " u.user_name LIKE '%" . mysql_like_quote($filter['user_name']) . "%' OR".
                    " u.email LIKE '%" . mysql_like_quote($filter['user_name']) . "%')";
        }

        $sql = "SELECT count(*) AS cou FROM %s AS w LEFT JOIN %s AS u ON w.user_id = u.user_id %s";
        $sql = sprintf($sql, $ecs->table('wd'), $ecs->table('users'), $where);

        $filter['record_count'] = $db->getOne($sql);
        $filter['page_count']   = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /// 记录
        $sql = "SELECT w.*, u.user_name FROM %s AS w LEFT JOIN %s AS u ON w.user_id = u.user_id %s ORDER BY w.wid DESC %s";
        $sql = sprintf($sql, $ecs->table('wd'), $ecs->table('users'), $where, $limit);

        set_filter($filter, $sql);

    } else {

        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $db->getAll($sql);

    foreach ($res as $k => $v) {
        
        $res[$k]['create_at'] = date($GLOBALS['_CFG']['time_format'], $v['create_at']);
        $res[$k]['done_at']   = $v['done_at'] == 0 ? '' : date($GLOBALS['_CFG']['time_format'], $v['done_at']);
    }

    $arr = array('result' => $res, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}
