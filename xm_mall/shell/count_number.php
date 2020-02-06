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
$count = $db->getOne("SELECT count(*) FROM xm_mq_users_extra");
$total = ceil($count/1000);

for($i=0;$i<$total;$i++){
    $page = $i*1000;
    $sql = "SELECT user_id FROM xm_mq_users_extra LIMIT {$page},1000";
    $users = $db->getAll($sql);
    foreach($users as $k=>$v){
        //查出直推人员
        $user_num = $db->getOne("SELECT count(*) FROM xm_mq_users_extra WHERE  invite_user_id={$v['user_id']} ");
        //查出间推人员
        if($user_num){
           $i_num = get_lower($v['user_id']);
        }else{
            $i_num = 0;
        }
        $total_num = intval($user_num) + intval($i_num)+1;
        $GLOBALS['u_invi_c'] = 0 ;
        //查出该会员现在的等级
        $rank = $db->getOne("SELECT user_cx_rank  FROM xm_mq_users_extra WHERE user_id={$v['user_id']} ");
        $rank = judge_rank($total_num,$rank);
        $db->query("UPDATE xm_mq_users_extra SET team_number={$total_num},user_cx_rank={$rank} WHERE user_id={$v['user_id']}");
    }
}

function judge_rank($num,$rank){
    if($rank==0 || $rank==5){
        return 0;
    }
    if($num<10){
        return $rank > 1 ? $rank : 1;

    }elseif($num>=10 && $num<50){
        return $rank > 2 ? $rank : 2;
    }elseif ($num>=50 && $num<200){
        return $rank > 3 ? $rank : 3;
    }elseif($num>=200){
        return $rank > 4 ? $rank : 4;
    }
}

function get_lower($str, $count = -1) {
    if (!$str) { return false; }
    $GLOBALS['u_invi_c'] += $count;
    $sql = "select user_id from xm_mq_users_extra where invite_user_id in ($str)";
    $list = $GLOBALS['db']->getAll($sql);
    if (count($list) != 0) {
        foreach ($list as $key => $value) {
            $ll[] = $value['user_id'];
        }
        $str = implode(',', $ll);

        // 间推的人数统计 | 当 $count == -1, 说明上面的 SQL 查询的直推的结果，这里的人数要过滤掉
        $inv_c = ($count == -1) ? 0 : count($list) ;

        get_lower($str, $inv_c);
    }
    // 从 -1 算起，少了一个人，要加上
    return $GLOBALS['u_invi_c'] + 1;
}







