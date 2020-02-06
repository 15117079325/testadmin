<?php

/**
 * 我的团队业绩
 * Author: ji
 * CreatedTime: 2018年1月22日09:11:03
 */


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*-----------------------------------------------------*/
/*-----------------------------------------------------*/
/*我的团队业绩详情*/
/*-----------------------------------------------------*/
if($_REQUEST['act'] == 'info')
{
	/*获取参数*/
	$layer = $_GET['layer'];
	$user_name = $_GET['user_name'];
	/*获取数据*/
    $server = new \maqu\Services\MyTeamService();
    $result = $server->myTeamCount(0,$layer);
	if($result['result']==false){
		show_message("访问失败！");
		return;
	}
	/*返回数据*/
	$return['cx_rank_3w'] = $result['data']['user_cx_rank'][2]->num?$result['data']['user_cx_rank'][2]->num:0;
	$return['cx_rank_10w'] = $result['data']['user_cx_rank'][3]->num?$result['data']['user_cx_rank'][3]->num:0;
	$return['cx_rank_30w'] = $result['data']['user_cx_rank'][4]->num?$result['data']['user_cx_rank'][4]->num:0;
	$return['recharge_count_num'] = $result['data']['recharge_count']->num?$result['data']['recharge_count']->num:0;
	$return['recharge_count_money'] = $result['data']['recharge_count']->money?$result['data']['recharge_count']->money:0;
	$return['hbuy_back_num'] = $result['data']['hbuy_back']->num;
	$return['hbuy_back_money'] = $result['data']['hbuy_back']->cash_money + $result['data']['hbuy_back']->consume_money;
	$return['hz_buy_back_num'] = $result['data']['hz_buy_back']->num;
	$return['hz_buy_back_money'] = $result['data']['hz_buy_back']->cash_money + $result['data']['hz_buy_back']->consume_money ;
	$return['mid_order_num'] = $result['data']['mid_order']->num;
	$return['mid_order_money'] = $result['data']['mid_order']->cash_money + $result['data']['mid_order']->consume_money;
	/* 模板赋值 */
	$smarty->assign('ur_here', '团队业绩'); // 当前导航
	$smarty->assign('result',$return);
	$smarty->assign('user_name',$user_name);
	/* 显示模板 */
	assign_query_info();
	$smarty->display('my_team_count/my_team_count_info.htm');

}

?>