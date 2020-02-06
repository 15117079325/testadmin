<?php

/**
 *订单迁移
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/../includes/init.php');
ini_set('memory_limit', '3072M');
set_time_limit(0);

$db = $GLOBALS['db'];
$count = $db->getOne("SELECT count(*) FROM xm_order_goods");

$total = ceil($count / 1000);

for ($i = 0; $i < $total; $i++) {
    $page = $i * 1000;
    $value = $db->getAll("SELECT g.*,xg.goods_img FROM  xm_order_goods g  LEFT JOIN xm_goods xg ON g.goods_id=xg.goods_id  LIMIT {$page},1000");

    if($value){
    $sql1 = "INSERT INTO xm_order_detail (`od_id`,`order_id`,`p_id`,`p_title`,`p_img`,`size_id`,`size_title`,`od_num`,
            `od_price`,`od_discount`,`is_comment`) VALUES ";
        foreach ($value as $k => $v) {

        if ($v['barter_type'] == 1) {

              $sql1 .= "('{$v['rec_id']}','{$v['order_id']}','{$v['goods_id']}','{$v['goods_name']}','{$v['goods_img']}','0','{$v['goods_attr']}','{$v['goods_number']}','{$v['shopping_money_all']}',0,1),";
        } elseif ($v['barter_type'] == 2) {
            $money = $v['consume_money_all'] + $v['cash_money_all'];

            $sql1 .= "('{$v['rec_id']}','{$v['order_id']}','{$v['goods_id']}','{$v['goods_name']}','{$v['goods_img']}','0','{$v['goods_attr']}','{$v['goods_number']}','$money',0,1),";
        }
    }
    }



    $sql1 = rtrim($sql1, ',');

    $db->query($sql1);
}











