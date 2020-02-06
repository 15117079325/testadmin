<?php

/**
 * ECSHOP 管理中心帐户变动记录
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: account_log.php 17217 2011-01-19 06:29:08Z liubo $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
include_once(ROOT_PATH . 'includes/lib_order.php');

/*------------------------------------------------------ */
//-- 办事处列表
/*------------------------------------------------------ */   
if ($_REQUEST['act'] == 'list') {

    /* 检查参数 */
    $user_id = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
    $type = empty($_REQUEST['type']) ? 0 : intval($_REQUEST['type']);

    if ($user_id == 0) {

        sys_msg('invalid param');
    }
    
    $user = user_money($user_id);

    $smarty->assign('type', $type);
    $smarty->assign('user', $user);

    $smarty->assign('ur_here', $_LANG['account_list']);
    if($_SESSION[admin_id] == 1) {
        $smarty->assign('action_link',  array('text' => $_LANG['add_account'], 'href' => 'account_log.php?act=add&user_id='.$user_id));
    }
    $smarty->assign('full_page', 1);

    $list = money_list($user_id, $type);

    $smarty->assign('list', $list['list']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    assign_query_info();
    $smarty->display('account_list.htm');
}elseif ($_REQUEST['act'] == 'old_list')
{
    /* 检查参数 */

    $user_id = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
    $account_id = empty($_REQUEST['account_id']) ? '' : $_REQUEST['account_id'];
    if ($user_id <= 0 || empty($account_id))
    {
        sys_msg('invalid param');
    }

    /*edit 修改用户积分详细记录user_info_jifen() 2017年2月26日 19:47:07 by fzp*/
    // $user = user_info($user_id);
    $user = user_money($user_id);

    if (empty($user))
    {
        sys_msg($_LANG['user_not_exist']);
    }
    $smarty->assign('user', $user);

    if (empty($_REQUEST['account_type']) || !in_array($_REQUEST['account_type'],
        array('cash', 'consume', 'invest', 'register', 'share', 'shopping', 'useable')))
    {
        $account_type = '';
    }
    else
    {
        $account_type = $_REQUEST['account_type'];
    }

    /*end*/
    $smarty->assign('account_type', $account_type);
    $smarty->assign('ur_here',      $_LANG['account_list']);
    $smarty->assign('action_link',  array('text' => $_LANG['add_account'], 'href' => 'account_log.php?act=add&user_id='.$user_id.'&account_id='.$account_id));
    $smarty->assign('full_page',    1);

    $account_list = get_accountlist($user_id, $account_type,$account_id);
    $smarty->assign('query', 'old_query');
    $smarty->assign('account_list', $account_list['account']);
    $smarty->assign('filter',       $account_list['filter']);
    $smarty->assign('record_count', $account_list['record_count']);
    $smarty->assign('page_count',   $account_list['page_count']);
    assign_query_info();
    $smarty->display('account_old_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'old_query')
{
    /* 检查参数 */
    $user_id = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
    $account_id = empty($_REQUEST['account_id']) ? '' : $_REQUEST['account_id'];

    if ($user_id <= 0 || empty($account_id))
    {
        sys_msg('invalid param');
    }
    $user = user_info($user_id);
    if (empty($user))
    {
        sys_msg($_LANG['user_not_exist']);
    }
    $smarty->assign('user', $user);
    if (empty($_REQUEST['account_type']) || !in_array($_REQUEST['account_type'],
            array('cash', 'consume', 'invest', 'register', 'share', 'shopping', 'useable')))
    {
        $account_type = '';
    }
    else
    {
        $account_type = $_REQUEST['account_type'];
    }
    $smarty->assign('account_type', $account_type);

    $account_list = get_accountlist($user_id, $account_type,$account_id);
    $smarty->assign('account_list', $account_list['account']);
    $smarty->assign('filter',       $account_list['filter']);
    $smarty->assign('record_count', $account_list['record_count']);
    $smarty->assign('page_count',   $account_list['page_count']);

    make_json_result($smarty->fetch('account_old_list.htm'), '',
        array('filter' => $account_list['filter'], 'page_count' => $account_list['page_count']));
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {

    /* 检查参数 */
    $user_id = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
    $type = empty($_REQUEST['type']) ? 0 : intval($_REQUEST['type']);

    if ($user_id == 0 ) {

        sys_msg('invalid param');
    }

    $smarty->assign('type', $type);
    $smarty->assign('user', $user);

    $list = money_list($user_id, $type);

    $smarty->assign('list', $list['list']);
    $smarty->assign('filter',       $list['filter']);
    $smarty->assign('record_count', $list['record_count']);
    $smarty->assign('page_count',   $list['page_count']);

    make_json_result($smarty->fetch('account_list.htm'), '',
        array('filter' => $list['filter'], 'page_count' => $list['page_count']));
}

/*------------------------------------------------------ */
//-- 调节帐户
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add') {

    /* 检查权限 */
    admin_priv('xinmei_manage');

    /* 检查参数 */
    $user_id = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
    $account_id = empty($_REQUEST['account_id']) ? '' : $_REQUEST['account_id'];
    if ($user_id <= 0)
    {
        sys_msg('invalid param');
    }
    /*edit 修改 会员账户变动明细 页面 2017年2月27日 00:03:08 by fzp*/
    //$user = user_info_jifen($user_id);

    // $user = user_info_jifen($user_id);
    /*end*/
     include_once ROOT_PATH . 'includes/lib_tran.php';
     include_once ROOT_PATH . 'includes/lib_main.php';
     $user = $GLOBALS['db']->getRow("SELECT * FROM xm_user_account WHERE user_id={$user_id}");
     //查出用户信息

     $user_info = get_user_info($user_id);
     $user['user_name'] = $user_info['user_name'];
     $user['user_id'] = $user_info['user_id'];
    if (empty($user))
    {
        sys_msg($_LANG['user_not_exist']);
    }

    $smarty->assign('user', $user);
    /* 显示模板 */
    $smarty->assign('ur_here', $_LANG['add_account']);
    $smarty->assign('action_link', array('href' => 'account_log.php?act=list&user_id='.$user_id.'&account_id='.$account_id, 'text' => $_LANG['account_list']));
    assign_query_info();
    $smarty->display('account_info.htm');
}

/*------------------------------------------------------ */
//-- 提交添加、编辑办事处
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update') {

    /* 检查权限 */
    admin_priv('account_manage');

    /* 检查参数 */
    $user_id = empty($_REQUEST['user_id']) ? 0 : intval($_REQUEST['user_id']);
    $account_id = empty($_REQUEST['account_id']) ? '' : $_REQUEST['account_id'];
    if ($user_id <= 0)
    {
        sys_msg('invalid param');
    }
    $user = user_info($user_id);
    if (empty($user))
    {
        sys_msg($_LANG['user_not_exist']);
    }

    // edit 7个积分修改 2017年2月27日 13:48:42 by fzp
    /* 提交值 */
    $change_desc = $_POST['change_desc'];
   include_once ROOT_PATH . 'includes/lib_tran.php';
    $user_info = $GLOBALS['db']->getRow("SELECT * FROM xm_user_account WHERE user_id={$user_id}");
    $money = [
        'balance' => $user_info['balance'], //优惠券
        'release_balance' => $user_info['release_balance'],//待释放优惠券
    ];
    $arr=['balance','release_balance'];

    for ($i=0; $i<2; $i++) {
        if(abs(intval($_POST[$arr[$i]])) != 0) {
            $credit[$i]['income_type'] = $_POST['add_sub_'.$arr[$i]];
            $sign = ($credit[$i]['income_type'] == '+') ? 1 : -1; //符号
            $credit[$i]['account_type'] = $arr[$i];
            $credit[$i]['change_money'] = abs($_POST[$arr[$i]]);
            $credit[$i]['money'] = $money[$arr[$i]]+$sign*$credit[$i]['change_money']; //优惠券
            if($credit[$i]['money']<0){
                sys_msg('优惠券不能为负数！');
            }
        }
    }

    if ($credit == 0)
    {
        sys_msg($_LANG['no_account_change']);
    }
    foreach($credit as $k=>$v){
        switch($v['account_type']){
            case 'balance':
                if($v['income_type']=='+'){
                 spend_balance_unlimit($user_id,$v['change_money'],'inc-2',$change_desc);
                }else{
                    spend_balance_unlimit($user_id,$v['change_money'],'dec-2',$change_desc);
                }
                break;
            case 'release_balance':
                if($v['income_type']=='+'){
                    spend_release_balance($user_id,$v['change_money'],'inc-3',$change_desc);
                }else{
                    spend_release_balance($user_id,$v['change_money'],'dec-3',$change_desc);
                }
                break;
        }
    }
    // end
    /* 保存 */
    //log_account_change_mq($user_id, $credit, $change_desc, ACT_ADJUSTING);
	//是否开启优惠券变动给客户发短信-管理员调节
//	if($_CFG['sms_user_money_change'] == 1)
//	{
//		if(abs($user_money) > 0)
//		{
//			if($_POST['add_sub_user_money'] > 0)
//			{
//				$user_money = '+'.$user_money;
//			}
//			else
//			{
//				$user_money = '-'.$user_money;
//			}
//			$sql = "SELECT user_money,mobile_phone FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id = '$user_id'";
//			$users = $GLOBALS['db']->getRow($sql);
//			$content = sprintf($_CFG['sms_admin_operation_tpl'],date("Y-m-d H:i:s",time()),$user_money,$users['user_money'],$_CFG['sms_sign']);
//			if($users['mobile_phone'])
//			{
//				include_once('../send.php');
//				sendSMS($users['mobile_phone'],$content);
//			}
//		}
//	}
    /* 提示信息 */
    $links = array(
        array('href' => 'account_log.php?act=list&user_id='.$user_id.'&account_id='.$account_id, 'text' => $_LANG['account_list'])
    );
    sys_msg($_LANG['log_account_change_ok'], 0, $links);
}

/*
|- 20180516 1331 获取用户的账户明细
*/

function money_list($user_id, $type = 0) {

    if (!$user_id) { return ''; }

    $cond = " WHERE user_id = $user_id";

    if ($type != 0) {

        $cond .= " AND type = $type";
    }

    /* 初始化分页参数 */
    $filter = [
        'user_id' => $user_id,
        'type'    => $type
    ];


    $sql = "SELECT COUNT(*) FROM xm_flow_log $cond";

    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);


    $sql = "SELECT * FROM xm_flow_log $cond ORDER BY create_at DESC LIMIT " .$filter['start'].", ".$filter['page_size'];

    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $k => $v) {

        $res[$k]['create_at'] = date('Y-m-d H:i:s', $v['create_at']);

        switch ($v['status']) {

            case 1:
                $res[$k]['amount'] = '+'.$v['amount'];
                break;
            
            case 2:

                $res[$k]['amount'] = '-'.$v['amount'];
                break;

            default:
                
                break;
        }

        $res[$k]['surplus'] = $v['surplus'] == 0 ? '' : $v['surplus'];
        
    }


    return array('list' => $res, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

/*
|- 20180516 1451 V 账户信息
*/
function user_money($uid) {

    if (!$uid) { return ''; } 
    
    $sql = "SELECT x.*, u.user_name FROM xm_user_account AS x  LEFT JOIN xm_users as u on x.user_id = u.user_id WHERE x.user_id = $uid";

    $ret = $GLOBALS['db']->getRow($sql);

    return $ret;
}
/**
 * 取得帐户明细
 * @param   int     $user_id    用户id
 * @param   string  $account_type   帐户类型：空表示所有帐户，user_money表示可用资金，
 *                  frozen_money表示冻结资金，rank_points表示等级积分，pay_points表示消费积分
 * @return  array
 */
function get_accountlist($user_id, $account_type,$account_id)
{
    /* 检查参数 */
//    $where = " WHERE u.user_id = '$user_id' ";
    $where = " WHERE account_id = '$account_id' ";
    /*edit 修改用户积分详细记录 2017年2月26日 19:48:25 by fzp*/
    if (in_array($account_type, array('cash', 'consume', 'invest', 'register', 'share', 'shopping', 'useable')))
    {
        // $where .= " AND $account_type <> 0 ";
        $where .= " AND mal.account_type = '$account_type' ";
    }

    /* 初始化分页参数 */
    $filter = array(
        'user_id'       => $user_id,
        'account_type'  => $account_type,
        'account_id'  => $account_id,
    );

    /* 查询记录总数，计算分页数 */ //查询优化
//    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " .
//            " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON mal.account_id = mue.account_id " .
//            " LEFT JOIN " . $GLOBALS['ecs']->table('users') . " AS u ON mue.user_id = u.user_id " . $where;
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter = page_and_size($filter);

    /* 查询记录 以时间降序排列*/
    /*$sql = "SELECT * FROM " . $GLOBALS['ecs']->table('account_log') . $where .
            " ORDER BY log_id DESC";*/
    //查询优化
//    $sql = "SELECT mal.change_time, mal.change_desc, mal.income_type, mal.change_money, mal.money, mal.account_type".
//            " FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " .
//            " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON mal.account_id = mue.account_id " .
//            " LEFT JOIN " . $GLOBALS['ecs']->table('users') . " AS u ON mue.user_id = u.user_id " . $where. "ORDER BY mal.change_time DESC";
    $sql = "SELECT mal.change_time, mal.change_desc, mal.income_type, mal.change_money, mal.money, mal.account_type,mal.account_id".
        " FROM " . $GLOBALS['ecs']->table('mq_account_log') . " AS mal " .
        $where. "ORDER BY mal.change_time DESC, mal.log_id DESC ";
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);
    /*end*/
    $arr = array();
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $row['change_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['change_time']);
        if($row['income_type'] == '+'){
            $row['rest_money'] = $row['money'] - $row['change_money'];
        } else {
            $row['rest_money'] = $row['money'] + $row['change_money'];
        }
        $arr[] = $row;
    }

    return array('account' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}
