<?php

/**
 * ECSHOP 自动修改订单状态
 * ============================================================================
 * 版权所有 2013-2017 杭州码趣科技有限公司，并保留所有权利。
 * 网站地址: http://www.68ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: derek $
 * $Id: okgoods.php 17217 2015-03-24 06:29:08Z derek $
 */


define('IN_ECS', true);
require('../includes/init.php');

// 自动确认收货
$okg = $GLOBALS['db']->getAll("select order_id, shipping_time,order_sn from " . $GLOBALS['ecs']->table('order_info') . " where shipping_status = ".SS_SHIPPED." and order_status = ".OS_SPLITED." ");
$okgoods_time = $GLOBALS['db']->getOne("select value from " . $GLOBALS['ecs']->table('shop_config') . " where code='okgoods_time'");
//var_dump($okgoods_time);die();
foreach($okg as $okg_id)
{
	$okg_time =  $okgoods_time - (time()/86400- $okg_id['shipping_time']/86400);
	//var_dump($okg_time);die();
	$is_back_now = 0;
	$is_back_now = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $ecs->table('back_order') . " WHERE order_id = " . $okg_id['order_id'] . " AND status_back < 6 AND status_back != 3");
	//var_dump(("SELECT COUNT(*) FROM " . $ecs->table('back_order') . " WHERE order_id = " . $okg_id['order_id'] . " AND status_back < 6 AND status_back != 3"));die();
	if ($okg_time <= 0 && $is_back_now == 0)
	{
		$db->query("update " . $ecs->table('order_info') . " set shipping_status = 2, shipping_time_end = " . time() . "  where order_id = " . $okg_id['order_id']);
		/*增加 start by ymm 2017-2-24 17:14*/
		/*-----------执行推荐人操作-----------*/
		//如果是精品商城查询推荐人id
		$sql_referee_id = "SELECT rec_id,referee_id ,goods_id,cash_money,consume_money,shopping_money,referee_percent,barter_type FROM " . $GLOBALS['ecs']->table('order_goods')
				. " WHERE order_id =" . $okg_id['order_id'];

		if (count($GLOBALS['db']->GetRow($sql_referee_id)) == count($GLOBALS['db']->GetRow($sql_referee_id), 1)) {
			$referee_id_order_goods[0] = $GLOBALS['db']->GetRow($sql_referee_id);
		} else {
			$referee_id_order_goods = $GLOBALS['db']->GetRow($sql_referee_id);
		}
		//插入推荐人提成表
		foreach ($referee_id_order_goods as $v){
			$sql_jz = "INSERT INTO " . $GLOBALS['ecs']->table('mq_referee_bonus') .
					" (user_id,order_goods_id,bonus_amount,shopping_amount,add_time,percent) VALUES
            ('" .$v['referee_id'].  "','" .$v['rec_id']. "','" . $v['cash_money'] * ($v['referee_percent']/100) . "','"  . $v['shopping_money'] * ($v['referee_percent']/100) .
					"','" . time() . "','"  .$v['referee_percent']. "') ";
			//插入数据库执行
			$GLOBALS['db']->query($sql_jz);
			//查询账户优惠券
			$sql_wherer = "b.user_id='". $v['referee_id'] ."' ";

			$user_money = "SELECT IFNULL(ma1.money,0) as cash_credit, IFNULL(ma2.money,0) as consume_credit, IFNULL(ma3.money,0) as shopping_credit ".
					" FROM " . $GLOBALS['ecs']->table('mq_users_extra') ." AS b ".
					" LEFT JOIN " . $GLOBALS['ecs']->table('mq_account')." AS ma1 ON b.account_id = ma1.account_id and ma1.account_type = 'cash' ".
					" LEFT JOIN " . $GLOBALS['ecs']->table('mq_account')." AS ma2 ON b.account_id = ma2.account_id and ma2.account_type = 'consume' ".
					" LEFT JOIN " . $GLOBALS['ecs']->table('mq_account')." AS ma3 ON b.account_id = ma3.account_id and ma3.account_type = 'shopping' ".
					"WHERE  $sql_wherer ";
			$casha =  $GLOBALS['db']->getRow($user_money);
			if($v['barter_type']==1){
				$bonus_amount = $v['shopping_money'] * $v['referee_percent']/100;
				if($bonus_amount>0){
					$new_shopping_money = $casha['shopping_credit'] + $bonus_amount;
					$new_shopping_money_sql = " UPDATE " . $GLOBALS['ecs']->table('mq_users_extra') ." AS b " .
							" LEFT JOIN ". $GLOBALS['ecs']->table('mq_account')." AS a ON b.account_id = a.account_id AND a.account_type = 'shopping'".
							" set `money` = '$new_shopping_money' WHERE  $sql_wherer ";
					$update_smoney = $db->query($new_shopping_money_sql);
					if($update_smoney){
						$user_account_sql = "SELECT IFNULL(ma1.account_id,0) as ma1_account_id ".
								" FROM " . $GLOBALS['ecs']->table('mq_users_extra') ." AS b ".
								" LEFT JOIN " . $GLOBALS['ecs']->table('mq_account')." AS ma1 ON b.account_id = ma1.account_id and ma1.account_type = 'shopping' ".
								"WHERE  $sql_wherer ";
						$user_account_id = $GLOBALS['db']->getOne($user_account_sql);
						$account_id = $user_account_id['ma1_account_id'];
						//var_dump( $account_id);die();
						$time = time();
						$account_log = 'INSERT INTO ' . $ecs->table('mq_account_log') . "(`account_id`, `account_type`, `money`, `change_money`, `change_desc`, `change_time`, `change_type`,`income_type`) VALUES ('$user_account_id', 'cash', '$new_shopping_money' , '$bonus_amount', '易业商城商品推荐提成：订单号".$okg_id['order_sn']."', '$time', '8','+')";
						$db->query($account_log);
					}
				}
			}elseif($v['barter_type']==2){
				$bonus_amount = $v['cash_money'] * $v['referee_percent']/100;
				if($bonus_amount>0){
					$new_cash_money = $casha['cash_credit'] + $bonus_amount;
					$new_cash_money_sql = " UPDATE " . $GLOBALS['ecs']->table('mq_users_extra') ." AS b " .
							" LEFT JOIN ". $GLOBALS['ecs']->table('mq_account')." AS a ON b.account_id = a.account_id AND a.account_type = 'cash'".
							" set `money` = '$new_cash_money' WHERE  $sql_wherer ";
					$update_cmoney = $db->query($new_cash_money_sql);
					if($update_cmoney){
						$user_account_sql = "SELECT IFNULL(ma1.account_id,0) as ma1_account_id ".
								" FROM " . $GLOBALS['ecs']->table('mq_users_extra') ." AS b ".
								" LEFT JOIN " . $GLOBALS['ecs']->table('mq_account')." AS ma1 ON b.account_id = ma1.account_id and ma1.account_type = 'cash' ".
								"WHERE  $sql_wherer ";
						$user_account_id = $GLOBALS['db']->getOne($user_account_sql);
						$account_id = $user_account_id['ma1_account_id'];
						//var_dump( $account_id);die();
						$time = time();
						$account_log = 'INSERT INTO ' . $ecs->table('mq_account_log') . "(`account_id`, `account_type`, `money`, `change_money`, `change_desc`, `change_time`, `change_type`,`income_type`) VALUES ('$user_account_id', 'cash', '$new_cash_money' , '$bonus_amount', '精品商城商品推荐提成：订单号".$okg_id['order_sn']."', '$time', '8','+')";
						$db->query($account_log);
					}
				}
			}

		}
		/*---- end -----*/
	}
}

// 自动通过审核
$okb = $GLOBALS['db']->getAll("select back_id, add_time, back_type from " . $GLOBALS['ecs']->table('back_order') . " where status_back = 5");
$okback_time = $GLOBALS['db']->getOne("select value from " . $GLOBALS['ecs']->table('shop_config') . " where code='okback_time'");

foreach($okb as $okb_id)
{
	$okb_time = $okback_time - (local_date('d',time()) - local_date('d',$okb_id['add_time']));
	if ($okb_time <= 0)
	{
		$status_back_c = ($okb_id['back_type'] == 4) ? 4 : 0;
		$GLOBALS['db']->query("update " . $GLOBALS['ecs']->table('back_order') . " set status_back = " . $status_back_c . " where back_id = " . $okb_id['back_id']);
		$GLOBALS['db']->query("update " . $GLOBALS['ecs']->table('back_goods') . " set status_back = " . $status_back_c . " where back_id = " . $okb_id['back_id']);
	}
}

// 自动取消退货/维修（退货/维修买家发货期限）
$delback_time = $GLOBALS['db']->getOne("select value from " . $GLOBALS['ecs']->table('shop_config') . " where code='delback_time'");
$back_goods = $GLOBALS['db']->getAll("select back_id, add_time, invoice_no, shipping_id from " . $GLOBALS['ecs']->table('back_order') . " where status_back < 5");

foreach ($back_goods as $bgoods_list)
{
	if ($bgoods_list['invoice_no'] == NULL or $bgoods_list['shipping_id'] == 0)
	{
		$delb_time = $delback_time - (local_date('d',time()) - local_date('d',$bgoods_list['add_time']));
		if ($delb_time <= 0)
		{
			$GLOBALS['db']->query("update " . $GLOBALS['ecs']->table('back_order') . " set status_back = 7 where back_id = '" . $bgoods_list['back_id'] . "'");
			$GLOBALS['db']->query("update " . $GLOBALS['ecs']->table('back_goods') . " set status_back = 7 where back_id = '" . $bgoods_list['back_id'] . "'");
		}
	}
}

// 虚拟商品自动下架
$virtual_goods = $GLOBALS['db']->getAll("select valid_date,goods_id from ". $GLOBALS['ecs']->table('goods') ." where is_virtual=1" );
foreach($virtual_goods as $v){
	
	if($v['valid_date']<time()){
		 $GLOBALS['db']->query("update ". $GLOBALS['ecs']->table('goods') ." set is_on_sale = 0 where goods_id=".$v['goods_id']);
	}
}

?>
