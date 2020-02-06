<?php

/**
 *团队人数
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/../includes/init.php');
ini_set('memory_limit', '3072M');
set_time_limit(0);

//查出所有会员的数量

    $sql = "SELECT user_id FROM xm_users";
    $users = $db->getAll($sql);

$sql = "INSERT INTO xm_mq_users_limit (user_id) VALUES ";
    foreach($users as $k=>$v){
        //查出直推人员
        $user_num = $db->getOne("SELECT count(*) FROM xm_mq_users_limit WHERE  user_id={$v['user_id']} ");
        //查出间推人员
        if($user_num){
            continue;
        }
        $sql .="({$v['user_id']}),";
    }
$sql = rtrim($sql, ',');
$db->query($sql);









