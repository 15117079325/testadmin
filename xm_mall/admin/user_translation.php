<?php

/**
 * ECSHOP 会员平移程序
 * $Author: jixf $
 * $Id: user_translation.php 2017年9月28日11:18:47 $
 */
define('IN_ECS', true);

require (dirname(__FILE__) . '/includes/init.php');

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';

/* 路由 */

$function_name = 'action_' . $action;

if(! function_exists($function_name))
{
	$function_name = "action_list";
}

call_user_func($function_name);

/* ------------------------------------------------------ */
// -- 平移日志
/* ------------------------------------------------------ */
function action_list ()
{
	// 全局变量
	$user = $GLOBALS['user'];
	$_CFG = $GLOBALS['_CFG'];
	$_LANG = $GLOBALS['_LANG'];
	$smarty = $GLOBALS['smarty'];
	$db = $GLOBALS['db'];
	$ecs = $GLOBALS['ecs'];
	$user_id = $_SESSION['user_id'];

	/* 检查权限 */
	admin_priv('user_translation_log');

	$smarty->assign('ur_here', '平移日志');
	$user_list = user_translation_list();
	$smarty->assign('user_list', $user_list['user_list']);
	$smarty->assign('filter', $user_list['filter']);
	$smarty->assign('record_count', $user_list['record_count']);
	$smarty->assign('page_count', $user_list['page_count']);
	$smarty->assign('full_page', 1);
	$smarty->assign('sort_user_id', '<img src="images/sort_desc.gif">');

	assign_query_info();
	$smarty->display('user_translation_log.htm');
}

/* ------------------------------------------------------ */
// -- ajax返回平移日志
/* ------------------------------------------------------ */
function action_query ()
{
	// 全局变量
	admin_priv('user_translation_log');
	$user = $GLOBALS['user'];
	$_CFG = $GLOBALS['_CFG'];
	$_LANG = $GLOBALS['_LANG'];
	$smarty = $GLOBALS['smarty'];
	$db = $GLOBALS['db'];
	$ecs = $GLOBALS['ecs'];
	$user_id = $_SESSION['user_id'];

	$user_list = user_translation_list();

	$smarty->assign('user_list', $user_list['user_list']);

	$smarty->assign('filter', $user_list['filter']);
	$smarty->assign('record_count', $user_list['record_count']);
	$smarty->assign('page_count', $user_list['page_count']);

	$sort_flag = sort_flag($user_list['filter']);
	$smarty->assign($sort_flag['tag'], $sort_flag['img']);

	make_json_result($smarty->fetch('user_translation_log.htm'), '', array(
			'filter' => $user_list['filter'],'page_count' => $user_list['page_count']
	));
}
/* ------------------------------------------------------ */
// -- 会员平移
/* ------------------------------------------------------ */
function action_add ()
{
	// 全局变量
	$smarty = $GLOBALS['smarty'];
	/* 检查权限 */
	admin_priv('user_translation');
	$smarty->assign('ur_here','会员平移');
	$smarty->assign('form_action', 'insert');
	assign_query_info();
	$smarty->display('user_translation.htm');
}


/* ------------------------------------------------------ */
// -- 会员平移操作
/* ------------------------------------------------------ */
function action_insert ()
{
	// 全局变量
	$user = $GLOBALS['user'];
	$_CFG = $GLOBALS['_CFG'];
	$_LANG = $GLOBALS['_LANG'];
	$smarty = $GLOBALS['smarty'];
	$db = $GLOBALS['db'];
	$ecs = $GLOBALS['ecs'];

	/* 检查权限 */
	admin_priv('user_translation');
	/* 获取数据 */
	$f_username = empty($_POST['f_username']) ? '' : trim($_POST['f_username']);
	$t_username = empty($_POST['t_username']) ? '' : trim($_POST['t_username']);
	$password = empty($_POST['admin_password']) ? '' : trim($_POST['admin_password']);
	$reason = empty($_POST['reason']) ? '' : trim($_POST['reason']);

	/* 验证数据 */
	if(empty($f_username)){
		sys_msg("平移账号不能为空！", 1);
	}
	if(empty($password)){
		sys_msg("管理员密码不能为空！", 1);
	}
	if(empty($t_username)){
		sys_msg("接管账号不能为空！", 1);
	}
	if($f_username == $t_username){
		sys_msg("平移账号不能和接管账号相同！", 1);
	}
	//验证平移账号是否存在
	$f_users = $db->getrow("select u.user_id, mue.layer from xm_users as u LEFT JOIN xm_mq_users_extra as mue ON u.user_id = mue.user_id  WHERE user_name='$f_username'");
	$f_user_layer = $f_users['layer'];
	$f_user_id = $f_users['user_id'];

	if(!$f_users){
		sys_msg("平移账号不存在！", 1);
	}

	//验证接管账号是否存在
	$t_users = $db->getrow("select u.user_id, mue.layer, mue.new_status from xm_users as u LEFT JOIN xm_mq_users_extra as mue ON u.user_id = mue.user_id  WHERE user_name='$t_username'");
	$t_user_layer = $t_users['layer'];
	$t_user_id = $t_users['user_id'];

	// 20180424 1357 V 平移操作中要更新平移团队的 new_status, 限制规则
	$new_status = $t_users['new_status'];

	if(!$t_users){
		sys_msg("接管账号不存在！", 1);
	}

	//验证管理员密码是否正确
	$sql="SELECT `ec_salt` FROM ". $ecs->table('admin_user') ."WHERE user_name = '" . $_SESSION['admin_name']."'";
	$ec_salt =$db->getOne($sql);

	if(!empty($ec_salt))
	{
		/* 检查密码是否正确 */
		$sql = "SELECT user_id ".
				" FROM " . $ecs->table('admin_user') .
				" WHERE user_name = '" .$_SESSION['admin_name']. "' AND password = '" . md5(md5($password).$ec_salt) . "'";
	}
	else
	{
		/* 检查密码是否正确 */
		$sql = "SELECT user_id ".
				" FROM " . $ecs->table('admin_user') .
				" WHERE user_name = '" . $_SESSION['admin_name']. "' AND password = '" . md5($password) . "'";
	}

	$admin = $db->getOne($sql);

	if(!$admin){
		sys_msg("管理员密码不正确！", 1);
	}

//	//验证平移账号与接管账号是否同一团队(上级不能转给下级)

	$f_team = $db->getall("select user_id from xm_mq_users_extra  WHERE layer LIKE concat('$f_user_layer', '_%')");
	$f_team_users = array_column($f_team,'user_id');
	if(in_array($t_user_id,$f_team_users)){
		sys_msg("该功能只能平移给自己团队以外的账号！", 1);
	}

	/*更换推荐人 接管平移账号*/
//	//查询出平移账号直推人数
//	$f_layer_first = $db->getall("select user_id,layer from xm_mq_users_extra WHERE  invite_user_id=".$f_user_id);
//	$f_layer_count = count($f_layer_first);
	//把自己包括队伍平移到接管账号
	$f_layer_count = 1;
	//查询接管账号直推人数最大值
	$t_max_layer = $db->getone("select layer from xm_mq_users_extra WHERE  invite_user_id='$t_user_id' ORDER BY layer DESC LIMIT 1 " );

	//如果新账号有下级
	if($t_max_layer){
		//取出下级的最后3位
		$t_layer_str = substr ($t_max_layer, -3,3);
	}else{
		//取出下级的最后3位
		$t_layer_str = '000';
	}

	if($t_layer_str + $f_layer_count > 999){
		sys_msg("无法平移，该团队等级已达到限制！", 1);
	}

//	$new_layer_value = number_format(intval($t_layer_str)  + 1, 0, '', '');
	$new_layer_value = intval($t_layer_str)  + 1;
//	$length = strlen($new_layer_value);
	//格式化layer 并且生成新layer
//	$new_layer = $t_user_layer . sprintf_layer($new_layer_value,$length);
	$new_layer = $t_user_layer . sprintf('%03d',$new_layer_value);
	$old_layer = $f_user_layer;
	$old_layer_length = strlen($old_layer)+1; //因为平移时原团队的会员添加顺序可不变，此参数用于截取最后面3位编码
	if(strlen($new_layer)>6000){
		sys_msg("无法平移，该团队等级已达到限制！", 1);
	}

	//开始平移
	//开启事务
	$db->query('BEGIN');

	try{

		//更新自己的layer
		$first_update = $db->query(" update xm_mq_users_extra  set invite_user_id = '$t_user_id', layer = '$new_layer', new_status = '$new_status' WHERE user_id=".$f_user_id);
		if($first_update===false){
			//回滚事务
			$db->query('ROLLBACK');
			sys_msg("直推平移失败！", 1);
		}

		// 更新属于自己的队伍layer
		$seconed_update = $db->query(" update xm_mq_users_extra  set new_status = '$new_status', layer = concat('$new_layer',substring(layer, '$old_layer_length'))  WHERE layer LIKE concat('$old_layer','_%')") ;
		if($seconed_update===false){
			//回滚事务
			$db->query('ROLLBACK');
			sys_msg("团队平移失败！", 1);
		}

		/* 记录管理员操作 */
		admin_log($_SESSION['admin_name'], 'add', 'user_translation');

		/* 记录平移操作日志 */
		$data = array(
				'f_use_id'=> $f_user_id,
				'f_user_name'=> $f_username,
				't_use_id'=> $t_user_id,
				't_user_name'=> $t_username,
				'admin_name'=> $_SESSION['admin_name'],
				'admin_id'=> $_SESSION['admin_id'],
				'add_time'=> gmtime(),
				'reason'=> $reason
		);
		$db->autoExecute($ecs->table('user_translation_log'), $data, 'INSERT');

		//提交事务
		$db->query('COMMIT');
	} catch(\Exception $e){
		//回滚事务
		$db->query('ROLLBACK');
		throw $e;
	}

	/* 提示信息 */
	$link[] = array(
		'text' => $_LANG['go_back'],'href' => 'user_translation.php?act=list'
	);
	sys_msg('平移成功', 0, $link);
}

/**
 * 返回平移日志列表数据
 *
 * @access public
 * @param
 *
 * @return void
 */
function user_translation_list ()
{
	$result = get_filter();
	if($result === false)
	{
		/* 过滤条件 */

		$filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
		if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
		{
			$filter['keywords'] = json_str_iconv($filter['keywords']);
		}

		$ex_where = ' WHERE 1 ';
		if($filter['keywords'])
		{
			$ex_where .= " AND f_user_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' or t_user_name like  '%" . mysql_like_quote($filter['keywords']) . "%'";
		}

		$sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs'] ->table('user_translation_log').$ex_where;
		$filter['record_count'] = $GLOBALS['db']->getOne($sql);


		// end

		/* 分页大小 */
		$filter = page_and_size($filter);

		$sql = "SELECT *  FROM " . $GLOBALS['ecs']->table('user_translation_log') .
				$ex_where . " ORDER by  add_time  desc  LIMIT " . $filter['start'] . ',' . $filter['page_size'];

		$filter['keywords'] = stripslashes($filter['keywords']);
	}
	else
	{
		$sql = $result['sql'];
		$filter = $result['filter'];
	}
	// echo $sql;exit();

	$user_list = $GLOBALS['db']->getAll($sql);
	foreach($user_list as $k=>$value){
		$user_list[$k]['add_time'] = $value['add_time'] ? local_date('Y-m-d', $value['add_time']):'';
	}

	$arr = array(
			'user_list' => $user_list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']
	);

	return $arr;
}

/**
 * 返回格式化后的数据
 *
 * @access public
 * @param
 *
 * @return void
 */
function sprintf_layer($str,$length){
	//根据取数据库字段长度 最大值6000
	for ($i=1; $i<=2000;$i++ ){
		$length_new = $i*3;//最大值6000
		if( $length < $length_new){

			$str = str_pad($str,$length_new,"0",STR_PAD_LEFT);
			return $str;
		}
	}
}
/**
 * 循环修改下级layer
 *
 * @access public
 * @param
 *
 * @return void
 */
function update_layer($user_id,$user_layer){

	$db = $GLOBALS['db'];
	if($user_id) {
		//更新把上级layer 拼接到最后3为前面 修改layer
		$db->query(" update xm_mq_users_extra  set layer = concat('$user_layer',right(layer,3))  WHERE user_id=".$user_id);
		//查询上级layer 已及本级用户id
		$layer_subordinate = $db->getAll("SELECT ma.user_id,mb.layer FROM xm_mq_users_extra AS ma LEFT JOIN xm_mq_users_extra AS mb ON ma.invite_user_id = mb.user_id WHERE mb.user_id = ".$user_id);
		if(count($layer_subordinate)>0){
			foreach($layer_subordinate as  $subordinate){
				//无限循环修改layer 直到结束
				update_layer($subordinate['user_id'], $subordinate['layer']);
			}
		}
	}
}
?>
