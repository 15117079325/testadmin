<?php

/**
 *团队人数
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/../includes/init.php');
ini_set('memory_limit', '3072M');
set_time_limit(0);

//查出所有会员的数量
$db = $GLOBALS['db'];
$count = $db->getOne("SELECT count(*) FROM xm_wd WHERE status=1 AND type=1 AND percent!=0");
$total = ceil($count/1000);
$n=0;
$time =time();
for($i=0;$i<$total;$i++){
    $page = $i*100;
    $sql = "SELECT wid,amount,user_id,percent FROM xm_wd WHERE status=1 AND type=1 AND percent!=0  LIMIT {$page},1000";
    $users = $db->getAll($sql);
    foreach($users as $k=>$v){
          $flag = $db->query("UPDATE xm_wd SET status=3 ,done_at={$time} WHERE wid={$v['wid']}") ;
          if($flag){
              $money = round($v['amount'] * ($v['percent'] / 100), 2);
              $reling = $db->getOne("SELECT reling FROM xm_xps  WHERE user_id={$v['user_id']}");
              $money = $money>$reling ? $reling : $money;
              $flag2 = $db->query("UPDATE xm_xps SET unlimit=unlimit+{$money},reling=reling-{$money},update_at = {$time} WHERE user_id={$v['user_id']}") ;
              if($flag2){
                  $n++;
              }
          }
    }
}
echo $n;







