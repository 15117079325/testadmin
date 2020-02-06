<?php
/**
 * ECSHOP 报单统计
 * $Author: ji
 * 2018年1月15日12:55:10
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/admin/statistic.php');
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/admin/order.php');
require_once(ROOT_PATH . 'includes/lib_order.php');

// act操作项的初始化
if (empty($_REQUEST['act'])) {

    $_REQUEST['act'] = 'list';

} else {

    $_REQUEST['act'] = trim($_REQUEST['act']);
}

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';

/* 路由 */
$function_name = 'action_' . $action;
if(! function_exists($function_name)) {

    $function_name = "action_list";
}

call_user_func($function_name);
// 20180510 1518

function act_release_points() {
     $db = $GLOBALS['db'];
     $sql = "SELECT value FROM `xm_master_config` WHERE `tip` = 'c' AND `code`='precharge_rule'";
     $value = $db->getOne($sql);
     $value = empty($value) ? 10 : $value;
     $current = time();
     $time = $current - ($value * 60 * 60 * 24);

    $sql = "SELECT * FROM %s WHERE  `surpro` > 0 AND (update_at < {$time})";
    $sql = sprintf($sql, 'xm_customs_apply');
    $ret = $db->getAll($sql);

    if (!$ret) {
        die;
    }
    //获取

    $new = [];
    $notes = '待用积分释放';
    include_once ROOT_PATH . 'includes/lib_tran.php';

    foreach ($ret as $k => $v) {

        $points = get_user_points($v['to_user_id']);

        switch ($v['surpro']) {

            case  7:

                $sql = "UPDATE `xm_customs_apply` SET  `surplus` = `surplus` * 5/7, `surpro` = 5, update_at = $current WHERE `id` = ".$v['id'];
                $db->query($sql);
                $new = [
                    'user_id' => $v['to_user_id'],
                    'type' => 3, // 消费积分
                    'status' => 1, // 收入
                    'amount' => $v['surplus'] * 2/7,
                    'surplus' => $points['tps']['shopp']+$v['surplus'] * 2/7,
                    'notes' => $notes,
                    'create_at' => $current
                ];
                new_flow($new);
                // 20180530 1404 V
                $new = [

                    'user_id' => $v['to_user_id'],
                    'type' => 6, // 待用积分
                    'status' => 2, // 支出
                    'amount' => $v['surplus'] * 2/7,
                    'surplus' => $points['tps']['surplus'] - $v['surplus'] * 2/7,
                    'notes' => $notes,
                    'create_at' => $current
                ];
                new_flow($new);
                update_tps($v['surplus'] * 2/7, $v['to_user_id']);
                break;
            case  5:

                $sql = "UPDATE `xm_customs_apply` SET  `surplus` = `surplus` * 3/5, `surpro` = 3, update_at = $current WHERE `id` = ".$v['id'];
                $db->query($sql);

                $new = [

                    'user_id' => $v['to_user_id'],
                    'type' => 3, // 消费积分
                    'status' => 1, // 收入
                    'amount' => $v['surplus'] * 2/5,
                    'surplus' => $points['tps']['shopp']+$v['surplus'] * 2/5,
                    'notes' => $notes,
                    'create_at' => $current
                ];
                new_flow($new);
                $new = [

                    'user_id' => $v['to_user_id'],
                    'type' => 6, // 待用积分
                    'status' => 2, // 支出
                    'amount' => $v['surplus'] * 2/5,
                    'surplus' => $points['tps']['surplus'] - $v['surplus'] * 2/5,
                    'notes' => $notes,
                    'create_at' => $current
                ];
                new_flow($new);
                update_tps($v['surplus'] * 2/5, $v['to_user_id']);
                break;
            case  3:
                $sql = "UPDATE `xm_customs_apply` SET  `surplus` = 0, `surpro` = 0, update_at = $current WHERE `id` = ".$v['id'];
                $db->query($sql);
                $new = [
                    'user_id' => $v['to_user_id'],
                    'type' => 3, // 消费积分
                    'status' => 1, // 收入
                    'amount' => $v['surplus'],
                    'surplus' => $points['tps']['shopp']+$v['surplus'],
                    'notes' => $notes,
                    'create_at' => $current
                ];
                new_flow($new);
                $new = [

                    'user_id' => $v['to_user_id'],
                    'type' => 6, // 待用积分
                    'status' => 2, // 支出
                    'amount' => $v['surplus'],
                    'surplus' => $points['tps']['surplus'] - $v['surplus'],
                    'notes' => $notes,
                    'create_at' => $current
                ];
                new_flow($new);
                update_tps($v['surplus'], $v['to_user_id']);
                break;

       }
    }
    custom_log();
}

function new_flow($data) {

    if (!$data) { return false;}

    $db  = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $res = $db->autoExecute($ecs->table('flow_log'), $data, 'INSERT');

}

function update_tps($points, $uid) {

    if (!$points || !$uid) { return; }

    $sql = "UPDATE `xm_tps` SET  `surplus` = `surplus` - $points, `shopp` = `shopp` + $points WHERE `user_id` = " . $uid;
    $GLOBALS['db']->query($sql);

}

function custom_log() {
    $new = [
        'admin_id' => 1,
        'create_at' => time()
    ];

    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('customs_logs'), $new, 'INSERT');
}







