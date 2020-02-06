<?php

/**
 *每天跑积分团队数据
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/../includes/init.php');
ini_set('memory_limit', '3072M');
set_time_limit(0);


/*
 * 查出30w,10W,3W的服务中心名单
 */
if (find_rank_list(4)) {
    if (find_rank_list(3)) {
        find_rank_list(2);
    }
}
function find_rank_list($rank)
{
    $db = $GLOBALS['db'];
    $sql = "SELECT user_id FROM xm_mq_users_extra WHERE user_cx_rank={$rank}";
    $ret = $db->getAll($sql);

    //查出上一个服务商的user_id;
    if ($rank == 4) {
        foreach ($ret as $k => $v) {
            $top_user_id[$v['user_id']] = 0;
        }
    }
    if ($rank == 3) {
        foreach ($ret as $k => $v) {
            $sql1 = "select invite_user_id from xm_mq_users_extra where user_id={$v['user_id']}";
            $user_extra2 = $db->getRow($sql1);
            if ($user_extra2['invite_user_id']) {
                $invite_user_id = get_top_user($user_extra2, 4);
                $top_user_id[$v['user_id']] = $invite_user_id;
            } else {
                $top_user_id[$v['user_id']] = 0;
            }
        }
    }

    if ($rank == 2) {
        foreach ($ret as $k => $v) {
            $sql1 = "select invite_user_id from xm_mq_users_extra where user_id={$v['user_id']}";
            $user_extra2 = $db->getRow($sql1);
            if ($user_extra2['invite_user_id']) {
                $invite_user_id = get_top_user($user_extra2, 3);
                $top_user_id[$v['user_id']] = $invite_user_id;
            } else {
                $top_user_id[$v['user_id']] = 0;
            }
        }
    }
    for ($i = 1; $i < 7; $i++) {
        switch ($i) {
            case 1:
                //充值排行
                $table = $GLOBALS['ecs']->table('flow_log');
                $filed = " x.amount";
                $where = " AND x.notes='充值' ";
                $condition = " x.user_id=e.user_id ";
                foreach ($ret as $key => $value) {
                    $money = get_lower($value['user_id'], 0, $table, $filed, $where, $rank,$condition);
                    $self_sql = "SELECT SUM({$filed}) FROM {$table} x  WHERE user_id={$value['user_id']} AND x.notes='充值'";
                    $self_money = $GLOBALS['db']->getOne($self_sql);
                    $money_arr1[$value['user_id']] = $money + $self_money;
                    $GLOBALS['u_invi_money'] = 0;
                };
                break;
            case 2:
                //提现
                $table = $GLOBALS['ecs']->table('wd');
                $filed = " x.amount";
                $where = " AND x.type=2 AND x.status=1 ";
                $condition = " x.user_id=e.user_id ";
                foreach ($ret as $key => $value) {
                    $money = get_lower($value['user_id'], 0, $table, $filed,$where, $rank,$condition);
                    $self_sql = "SELECT SUM({$filed}) FROM {$table} x  WHERE user_id={$value['user_id']} {$where}";
                    $self_money = $GLOBALS['db']->getOne($self_sql);
                    $money_arr2[$value['user_id']] = $money + $self_money;
                    $GLOBALS['u_invi_money'] = 0;
                };
                break;
            case 3:
                //报单
                $table = $GLOBALS['ecs']->table('customs_apply');
                $filed = " x.xpoints";
                $where = " ";
                $condition = " x.from_user_id=e.user_id ";
                foreach ($ret as $key => $value) {
                    $money = get_lower($value['user_id'], 0, $table, $filed,$where, $rank,$condition);
                    $self_sql = "SELECT SUM({$filed}) FROM {$table} x  WHERE from_user_id={$value['user_id']} {$where}";
                    $self_money = $GLOBALS['db']->getOne($self_sql);
                    $money_arr3[$value['user_id']] = $money + $self_money;
                    $GLOBALS['u_invi_money'] = 0;
                };
                break;
            case 4:
                //H单购买
                $table = $GLOBALS['ecs']->table('flow_log');
                $filed = " x.amount";
                $where = " AND x.notes='购买 H 单' ";
                $condition = " x.user_id=e.user_id ";
                foreach ($ret as $key => $value) {
                    $money = get_lower($value['user_id'], 0, $table, $filed,$where, $rank,$condition);
                    $self_sql = "SELECT SUM({$filed}) FROM {$table} x  WHERE user_id={$value['user_id']} AND x.notes='购买 H 单'";
                    $self_money = $GLOBALS['db']->getOne($self_sql);
                    $money_arr4[$value['user_id']] = $money + $self_money;
                    $GLOBALS['u_invi_money'] = 0;
                };
                break;
            case 5:
                $table = $GLOBALS['ecs']->table('flow_log');
                $filed = " x.amount";
                $where = " AND x.notes='回购结算' ";
                $condition = " x.user_id=e.user_id ";
                foreach ($ret as $key => $value) {
                    $money = get_lower($value['user_id'], 0, $table, $filed,$where, $rank,$condition);
                    $self_sql = "SELECT SUM({$filed}) FROM {$table} x  WHERE user_id={$value['user_id']} AND x.notes='回购结算'";
                    $self_money = $GLOBALS['db']->getOne($self_sql);
                    $money_arr5[$value['user_id']] = $money + $self_money;
                    $GLOBALS['u_invi_money'] = 0;
                };
                break;
            case 6:
                $table = $GLOBALS['ecs']->table('flow_log');
                $filed = " x.amount";
                $where = " AND x.notes='购买精品' ";
                $condition = " x.user_id=e.user_id ";
                foreach ($ret as $key => $value) {
                    $money = get_lower($value['user_id'], 0, $table, $filed,$where, $rank,$condition);
                    $self_sql = "SELECT SUM({$filed}) FROM {$table} x  WHERE user_id={$value['user_id']} AND x.notes='购买精品'";
                    $self_money = $GLOBALS['db']->getOne($self_sql);
                    $money_arr6[$value['user_id']] = $money + $self_money;
                    $GLOBALS['u_invi_money'] = 0;
                };
                break;
        }
    }
    $time = time();
    $sql = "INSERT INTO `xm_other_rank` (`user_id`,`user_cx_rank`,`top_user_id`,`type`,`money`,`create_time`) VALUES ";
    foreach ($money_arr1 as $k => $v) {
        $sql .= "({$k},{$rank},{$top_user_id[$k]},1,$v,{$time}),";
    }
    foreach ($money_arr2 as $k => $v) {
        $sql .= "({$k},{$rank},{$top_user_id[$k]},2,$v,{$time}),";
    }
    foreach ($money_arr3 as $k => $v) {
        $sql .= "({$k},{$rank},{$top_user_id[$k]},3,$v,{$time}),";
    }
    foreach ($money_arr4 as $k => $v) {
        $sql .= "({$k},{$rank},{$top_user_id[$k]},4,$v,{$time}),";
    }
    foreach ($money_arr5 as $k => $v) {
        $sql .= "({$k},{$rank},{$top_user_id[$k]},5,$v,{$time}),";
    }
    foreach ($money_arr6 as $k => $v) {
        $sql .= "({$k},{$rank},{$top_user_id[$k]},6,$v,{$time}),";
    }

    $sql = rtrim($sql, ',');
    $db->query($sql);
    return true;
}

function get_lower($str, $money = 0, $table, $filed, $where,$rank,$condition)
{

    if (!$str) {
        return false;
    }

    $GLOBALS['u_invi_money'] += $money;
    $sql = "select user_id,user_cx_rank from xm_mq_users_extra where invite_user_id in ($str) AND user_cx_rank<{$rank}";
    $sql1 = "SELECT SUM({$filed}) FROM {$table} x LEFT JOIN xm_mq_users_extra e ON {$condition} WHERE e.invite_user_id in ($str) AND e.user_cx_rank<{$rank} {$where}";
    $money = $GLOBALS['db']->getOne($sql1);
    $list = $GLOBALS['db']->getAll($sql);
    if (count($list) != 0) {

        foreach ($list as $key => $value) {
            if ($value['user_cx_rank'] < $rank) {
                $ll[] = $value['user_id'];
            }
        }
        if ($ll) {
            $str = implode(',', $ll);
            $inv_c = empty($money) ? 0 : $money;
            get_lower($str, $inv_c, $table, $filed,$where, $rank,$condition);
        }

    }
    return $GLOBALS['u_invi_money'];
}


function get_top_user($user_extra2, $rank)
{
    $db = $GLOBALS['db'];
    while (true) {
        $sql = "select invite_user_id,user_cx_rank,user_id from xm_mq_users_extra where user_id={$user_extra2['invite_user_id']}";
        $user_extra2 = $db->getRow($sql);
        if (!$user_extra2) {
            break;
        }

        if ($user_extra2['user_cx_rank'] >= $rank) {
            return $user_extra2['user_id'];
            break;
        }
        //已达到顶级永和则退出
        if (!$user_extra2['invite_user_id']) {
            break;
        }
    }
    return 0;
}





