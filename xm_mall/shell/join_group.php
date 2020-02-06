<?php

/**
 *加入群组
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/../includes/init.php');
ini_set('memory_limit', '3072M');
set_time_limit(0);

$db = $GLOBALS['db'];
$count = $db->getAll("SELECT count(*) FROM xm_order_info");
$total = ceil($count / 1000);

for ($i = 0; $i < $total; $i++) {
    $page = $i * 1000;
    $value = $db->getAll("SELECT * FROM xm_order_info LIMIT {$page},1000");
    $sql = "INSERT INTO xm_orders (`order_id`,`order_sn`,`user_id`,`shop_id`,`order_money`,`order_discount`,`consignee`,
        `mobile`,`area`,`address`,`order_remarks`,`order_status`,`order_cancel`,`order_gmt_create`,`order_gmt_pay`,`order_gmt_send`,`order_gmt_sure`,`shipping_id`,`shipping_name`,
        `shipping_fee`,`shipping_number`,`market_price`,`order_payway`,`order_delete`,`order_gmt_expire`,`order_type`) VALUES ";
    foreach ($value as $k => $v) {
        //查询地址
        $area_sql = "SELECT CONCAT((SELECT region_name FROM xm_region WHERE region_id = {$v['province']}),'-',(SELECT region_name FROM xm_region WHERE region_id = {$v['city']}),'-',(SELECT region_name FROM xm_region WHERE region_id = {$v['district']})) as a";
        $area = $db->getOne($area_sql);
        if ($v['barter_type'] == 1) {
            $sql .= "('{$v['order_id']}','{$v['order_sn']}','{$v['user_id']}',0,'{$v['shopping_money_all']}',0,'{$v['consignee']}','{$v['mobile']}'
        ,'$area','{$v['address']}','{$v['postscript']}','{$v['order_status']}',1,'{$v['add_time']}','{$v['pay_time']}','{$v['shipping_time']}','{$v['confirm_time']}','{$v['shipping_id']}','{$v['shipping_name']}',
        0,'',0,1,1,0,'{$v['barter_type']}'),";
        } elseif ($v['barter_type'] == 2) {
            $money = $v['consume_money_all'] + $v['cash_money_all'];
            $sql .= "('{$v['order_id']}','{$v['order_sn']}','{$v['user_id']}',0,'{$money}',0,'{$v['consignee']}','{$v['mobile']}'
        ,'$area','{$v['address']}','{$v['postscript']}','{$v['order_status']}',1,'{$v['add_time']}','{$v['pay_time']}','{$v['shipping_time']}','{$v['confirm_time']}','{$v['shipping_id']}','{$v['shipping_name']}',
        0,'',0,1,1,0,'{$v['barter_type']}'),";
        }
    }
    $sql = rtrim($sql, ',');
    $db->query($sql);
}











