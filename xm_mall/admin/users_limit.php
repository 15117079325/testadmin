<?php

use Illuminate\Database\Capsule\Manager as DB;

/**
 * 会员限制功能
 */

define('IN_ECS', true);

require (dirname(__FILE__) . '/includes/init.php');

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'restriction_list';

/* 路由 */

$function_name = 'action_' . $action;
if(! function_exists($function_name))
{
	$function_name = "action_restriction_list";
}
call_user_func($function_name);

/* 路由 */

/* ------------------------------------------------------ */
// -- 用户帐号列表
/* ------------------------------------------------------ */
function action_restriction_list ()
{
	// 全局变量
	$smarty = $GLOBALS['smarty'];

	/* 检查权限 */
	admin_priv('users_manage');

	$user_list = user_restriction_list();

	/* 模板赋值 */
	$smarty->assign('ur_here', "限制会员列表 "); // 当前导航

	$smarty->assign('user_list', $user_list['user_list']);
	$smarty->assign('filter', $user_list['filter']);
	$smarty->assign('record_count', $user_list['record_count']);
	$smarty->assign('page_count', $user_list['page_count']);
	$smarty->assign('full_page', 1);
	$smarty->assign('sort_start_time', '<img src="images/sort_desc.gif">');
	$smarty->assign('limit_types', [
		'0'=>'全部',
		'1'=>'限制会员',
		'2'=>'H单限制',
		'3'=>'新美转账限制',
		'4'=>'消费转账限制',
		'5'=>'提现限制'
	]);

	assign_query_info();

	$smarty->display('user_limit/users_restriction_list.htm');
}

/* ------------------------------------------------------ */
// -- ajax返回用户列表
/* ------------------------------------------------------ */
function action_query ()
{
	// 全局变量
	$smarty = $GLOBALS['smarty'];

	$user_list = user_restriction_list();

	$smarty->assign('user_list', $user_list['user_list']);
	$smarty->assign('filter', $user_list['filter']);
	$smarty->assign('record_count', $user_list['record_count']);
	$smarty->assign('page_count', $user_list['page_count']);
	$smarty->assign('sort_start_time', '<img src="images/sort_desc.gif">');
	$smarty->assign('limit_types', [
			'0'=>'全部',
			'1'=>'限制会员',
			'2'=>'H单限制',
			'3'=>'新美转账限制',
			'4'=>'消费转账限制',
			'5'=>'提现限制'
	]);

	$sort_flag = sort_flag($user_list['filter']);
	$smarty->assign($sort_flag['tag'], $sort_flag['img']);

	make_json_result($smarty->fetch('user_limit/users_restriction_list.htm'), '', array(
			'filter' => $user_list['filter'],'page_count' => $user_list['page_count']
	));
}
/* ------------------------------------------------------ */
// -- 删除会员限制
/* ------------------------------------------------------ */
function action_remove ()
{
	// 全局变量
	$_LANG = $GLOBALS['_LANG'];
	$db = $GLOBALS['db'];
	$ecs = $GLOBALS['ecs'];

	/* 检查权限 */
	$sql = "DELETE  FROM " . $ecs->table('mq_users_limit') . " WHERE user_id = '" . $_GET['id'] . "'";
	$db->query($sql);
	$sql = "DELETE  FROM " . $ecs->table('mq_users_limit_goods') . " WHERE user_id = '" . $_GET['id'] . "'";
	$db->query($sql);
	/* 提示信息 */
	$link[] = array(
			'text' => $_LANG['go_back'], 'href' => 'users_limit.php?act=restriction_list'
	);
	sys_msg('删除成功', 0, $link);
}

/* ------------------------------------------------------ */
// -- 批量删除会员限制
/* ------------------------------------------------------ */
function action_batch_remove ()
{
	// 全局变量
	$_LANG = $GLOBALS['_LANG'];
	$db = $GLOBALS['db'];
	$ecs = $GLOBALS['ecs'];

	/* 检查权限 */

	if(isset($_POST['checkboxes']))
	{
		$sql = "DELETE  FROM " . $ecs->table('mq_users_limit') . " WHERE user_id " . db_create_in($_POST['checkboxes']);
		$db->query($sql);
		$sql = "DELETE  FROM " . $ecs->table('mq_users_limit_goods') . " WHERE user_id " . db_create_in($_POST['checkboxes']);
		$db->query($sql);

		$lnk[] = array(
				'text' => $_LANG['go_back'], 'href' => 'users_limit.php?act=restriction_list'
		);
		sys_msg('删除成功', 0, $lnk);
	}
	else
	{
		$lnk[] = array(
				'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
		);
		sys_msg('没有数据需要删除', 0, $lnk);
	}
}

/**
 * 返回用户列表数据
 *
 * @access public
 * @param
 *
 * @return void
 */
function user_restriction_list ()
{
	$result = get_filter();
	if($result === false)
	{
		/* 过滤条件 */

		$filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
		$filter['limit_type'] = empty($_REQUEST['limit_type']) ? '' : trim($_REQUEST['limit_type']);

		if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
		{
			$filter['keywords'] = json_str_iconv($filter['keywords']);
		}

		$filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'u.user_id' : trim($_REQUEST['sort_by']);
		$filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

		$ex_where = ' WHERE 1 ';
		if($filter['keywords'])
		{
			$ex_where .= " AND u.user_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' or u.email like  '%" . mysql_like_quote($filter['keywords']) . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['keywords']) . "%' ";
		}

		if($filter['limit_type']){
//			'0'=>'全部',
//			'1'=>'限制会员',
//			'2'=>'H单限制',
//			'3'=>'新美转账限制',
//			'4'=>'消费转账限制',
//			'5'=>'提现限制'
			switch($filter['limit_type']){
				case '1'://限制会员
					$ex_where .= " AND mul.user_limited = 1 ";
					break;
				case '2'://H单限制
					$ex_where .= " AND (mul.hdan_limited = 1 OR mul.hdan_zbuy_limited = 1)";
					break;
				case '3'://新美转账限制
					$ex_where .= " AND mul.cash_limited = 1 ";
					break;
				case '4'://消费转账限制
					$ex_where .= " AND mul.consume_limited = 1 ";
					break;
				case '5'://提现限制
					$ex_where .= " AND mul.cash_take_limited = 1 ";
					break;
			}
		}

		$ex_where .=" AND mul.start_time is not null ";

		$sql1 = "SELECT COUNT(*) FROM " . $GLOBALS['ecs'] ->table('users') . " AS u" .
				" INNER JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON u.user_id = mue.user_id " .
				" INNER JOIN " . $GLOBALS['ecs']->table('mq_users_limit') . " AS mul ON mul.user_id = u.user_id ".
				$ex_where;

		$filter['record_count'] = $GLOBALS['db']->getOne($sql1);

		// end

		/* 分页大小 */
		$filter = page_and_size($filter);

		$sql = "SELECT u.user_id,u.user_name, u.email, u.mobile_phone,mul.* ".
				/* 代码增加2014-12-23 by www.68ecshop.com  _end  */
				" FROM " . $GLOBALS['ecs']->table('users'). ' AS u ' .
				" INNER JOIN " . $GLOBALS['ecs']->table('mq_users_limit') . " AS mul ON mul.user_id = u.user_id ".
				$ex_where . " ORDER by " . $filter['sort_by'] . ' ' . $filter['sort_order'] . " LIMIT " . $filter['start'] . ',' . $filter['page_size'];

		$filter['keywords'] = stripslashes($filter['keywords']);

		set_filter($filter, $sql);
	}
	else
	{
		$sql = $result['sql'];
		$filter = $result['filter'];
	}

	$user_list = $GLOBALS['db']->getAll($sql);
	foreach($user_list as $k=>$value){
		$user_list[$k]['start_time'] = local_date('Y-m-d', $value['start_time']);
		$user_list[$k]['end_time'] = $value['end_time'] ? local_date('Y-m-d', $value['end_time']):'';
	}

	//获取H单限制的明细
	if(count($user_list)>0){
		mergeHdanLimitDetail($user_list);
	}

	$arr = array(
		'user_list' => $user_list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']
	);

	return $arr;
}

function mergeHdanLimitDetail(&$user_list){

	$user_ids = array_pluck($user_list,'user_id');

	$goods = DB::table('mq_users_limit_goods')
		->whereIn('user_id',$user_ids)
		->select('user_id','goods_id','daily_hdan_buy_max_num','daily_hdan_zbuy_max_num')
		->get();

	if(!$goods){
		return;
	}

	$result = [];

	foreach($goods as $good){
		$result[$good->user_id][]= sprintf('%u-%u-%u',$good->goods_id,$good->daily_hdan_buy_max_num,$good->daily_hdan_zbuy_max_num);
	}

	foreach($user_list as $k=>$value){

		$temp =  $result[$value['user_id']];
		$str = '';
		if($temp){
			foreach($temp as $item){
				$str .= '<br/>' . $item ;
			}
		}

		$user_list[$k]['details'] =$str;
	}
}
