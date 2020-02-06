<?php

/*
|- 20180521 1554 V 数据统计
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'languages/' .$_CFG['lang']. '/admin/statistic.php');

// act操作项的初始化
if (empty($_REQUEST['act'])) {

    $_REQUEST['act'] = 'main';

} else {

    $_REQUEST['act'] = trim($_REQUEST['act']);
}

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'main';

/* 路由 */
$function_name = 'action_' . $action;

if(!function_exists($function_name)) {

    $function_name = "action_main";
}

call_user_func($function_name);

// -----------------------------------------------------------------------------

function action_main() {
    
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];

    $smarty->assign('lang', $_LANG);

    /* 模板赋值 */
    $smarty->assign('ur_here', "App首页管理"); // 当前导航

    /* 显示模板 */
    assign_query_info();
    $smarty->display('home/main.htm');
}

function action_rec() {

	$smarty = $GLOBALS['smarty'];
    $_LANG  = $GLOBALS['_LANG'];

    $smarty->assign('lang', $_LANG);

    /* 模板赋值 */
    $smarty->assign('ur_here', " 充值-提现-报单统计图表"); // 当前导航

    $ret = get_rec_chart($_REQUEST['month']);

    /* 显示模板 */
    $month = $_REQUEST['month'] ? : date('Ym');
    assign_query_info();
    $smarty->assign('month', $month);
    $smarty->assign('ret', $ret);
    $smarty->display('charts/rec.htm');
}

/*
|- 20180524 1529 V 查询 充值-提现-报单 的图表
*/
function get_rec_chart($month) {

    if ($month) {

        $start = $month.'01';
        $end = $month.'31';

        $condi = " WHERE create_date BETWEEN $start AND $end";

    } else {

        $condi = " WHERE create_date <= ".date('Ymd');
    }

    $ret = $GLOBALS['db']->getAll("SELECT * FROM xm_chart_rec $condi ORDER BY create_date LIMIT ".get_month_days($month));

    if (!$ret) { return ''; }

    $desc = [];
    $rec_data = [];
    $wd_data = [];
    $baod_data =[];

    foreach ($ret as $k => $v) {
        
        $desc[] = "'". $v['create_date'] ."'";
        $rec_data[] = "'". $v['recharge'] ."'";
        $wd_data[]  = "'". $v['withdraw'] ."'";
        $baod_data[] = "'". $v['baodan'] ."'";
    }

    $res = [

        'date_str' => implode(',', $desc),
        'rec_str' => implode(',', $rec_data),
        'wd_str' => implode(',', $wd_data),
        'bd_str' => implode(',', $baod_data)
    ];

    return $res;
}

// H 单图表页面
function action_horder() {

    $smarty = $GLOBALS['smarty'];
    $_LANG  = $GLOBALS['_LANG'];

    $smarty->assign('lang', $_LANG);

    /* 模板赋值 */
    $smarty->assign('ur_here', " H 单购买/回购 统计图表"); // 当前导航

    $date = date('Ymd');
    
    if ($_REQUEST['thedate']) {
    
        $date = date('Ymd', strtotime($_REQUEST['thedate']));
    }
    $ret = get_horder_chart($date);
    $date2 = strtotime($date) - 60*60*24*16;
    $time = [
        date('Y-m-d', $date2),
        date('Y-m-d', strtotime($date))
    ];

    // 20180622 1028 V 获取累计数据
    $addup = get_horder_addup($time);

    /* 显示模板 */
    $thedate = $_REQUEST['thedate'] ? : date('Y-m-d');
    assign_query_info();
    $smarty->assign('thedate', $thedate);
    $smarty->assign('ret', $ret);
    $smarty->assign('time', $time); // 日期区间
    $smarty->assign('bar_data', $addup);
    $smarty->display('charts/horder.htm');
}

/*
|- 20180528 1512 V 查询  H 单 购买 / 回购 的数据
*/
function get_horder_chart($date) {

    if (!$date || $data > date('Ymd')) {

        $date = date('Ymd');
    }

    $start = $date - 16;
    $condi = " WHERE type < 3 AND create_date BETWEEN $start AND $date";

    // 两条数据算一组，so limit 32
    $ret = $GLOBALS['db']->getAll("SELECT * FROM xm_chart_buy $condi ORDER BY create_date LIMIT 32");

    if (!$ret) { return ''; }
    
    // * 昨天 是数据里面日期的昨天

    $hnum  = []; // 昨天的购买的 H 单数量
    $hnum_ = []; // 昨天回购的 H 单数量
    $hxps  = []; // 昨天购买 H 单 花费的新美积分
    $hxps_ = []; // 昨天回购的 H 单新美积分
    $hcps  = []; // 昨天购买 H 单 花费的消费积分    
    $date  = [];

    foreach ($ret as $k => $v) {
        
        if ($v['type'] == 1) {

            $hnum[] = "'". $v['nums'] ."'";
            $hxps[] = "'". $v['amount_0'] ."'";
            $hcps[] = "'". $v['amount_1'] ."'";

        } else {

            $hnum_[] = "'". $v['nums'] ."'";
            $hxps_[] = "'". $v['amount_0'] ."'";
        }
        
        $date[] = "'". $v['create_date'] ."'";
    }

    $res = [

        'date_str' => implode(',', array_unique($date)),
        'hnum'  => implode(',', $hnum),
        'hnum_'  => implode(',', $hnum_),
        'hxps'  => implode(',', $hxps),
        'hxps_'  => implode(',', $hxps_),
        'hcps'  => implode(',', $hcps),

    ];

    return $res;
}

function get_horder_addup($time) {

    if (!$time) { return '';}

    $start = strtotime($time[0]);
    $end = strtotime($time[1].'23:59:59');

    $condi = "type = 1 AND notes = '购买 H 单' AND (create_at BETWEEN $start AND $end)";

    // 累计购买 H 单数量
    $hnum = $GLOBALS['db']->getRow("SELECT COUNT(*) AS num FROM xm_flow_log WHERE $condi");

    // 购买 H 单累计花费新美积分
    $hxps = $GLOBALS['db']->getRow("SELECT SUM(amount) AS amount FROM xm_flow_log WHERE $condi");    

    $condi = "type = 1 AND notes = '回购结算' AND (create_at BETWEEN $start AND $end)";

    // 累计回购 H 单数量
    $num_ = $GLOBALS['db']->getRow("SELECT COUNT(*) AS num FROM xm_flow_log WHERE $condi");
    // 累计回购新美积分
    $xps_ = $GLOBALS['db']->getRow("SELECT SUM(amount) AS amount FROM xm_flow_log WHERE $condi");


    return [

        'num' => $hnum['num'],
        'xps' => $hxps['amount'],
        'num_' => $num_['num'],
        'xps_' => $xps_['amount']
    ];    

}


// 精品商城购买 图表页面
function action_jorder() {

    $smarty = $GLOBALS['smarty'];
    $_LANG  = $GLOBALS['_LANG'];

    $smarty->assign('lang', $_LANG);

    /* 模板赋值 */
    $smarty->assign('ur_here', " 精品商城购买 统计图表"); // 当前导航

    $ret = get_jorder_chart($_REQUEST['month']);

    /* 显示模板 */
    $month = $_REQUEST['month'] ? : date('Ym');
    assign_query_info();
    $smarty->assign('month', $month);
    $smarty->assign('ret', $ret);
    $smarty->display('charts/jorder.htm');
}

/*
|- 20180528 1513 V 查询 精品商城购买的数据
*/
function get_jorder_chart($month) {

    if ($month) {

        $start = $month.'01';
        $end = $month.'31';

        $condi = " WHERE type = 3 AND create_date BETWEEN $start AND $end";

    } else {

        $condi = " WHERE type = 3 AND create_date <= ".date('Ymd');
    }

    $ret = $GLOBALS['db']->getAll("SELECT * FROM xm_chart_buy $condi ORDER BY create_date LIMIT ".get_month_days($month));

    if (!$ret) { return ''; }

    $num = [];
    $tps = [];
    $cps = [];
    $date = [];

    foreach ($ret as $k => $v) {
        
        $num[] = "'". $v['nums'] ."'";;
        $tps[] = "'". $v['amount_0'] ."'";;
        $cps[] = "'". $v['amount_1'] ."'";;
        $date[] = "'". $v['create_date'] ."'";
    }

    $res = [

        'num' => implode(',', $num),
        'tps' => implode(',', $tps),
        'cps' => implode(',', $cps),
        'date_str' => implode(',', $date)
    ];

    return $res;
}

function get_month_days($month) {

    if (!$month) { return 30; }

    $year  = substr($month, 0, 4);
    $month = substr($month, 4);

    $month_2 = 28;

    if ($year % 4 == 0 || $year % 400 == 0) {

        $month_2 = 29;
    }

    $month_arr = [

        '01' => 31,
        '02' => $month_2,
        '03' => 31,
        '04' => 30,
        '05' => 31,
        '06' => 30,
        '07' => 31,
        '08' => 31,
        '09' => 30,
        '10' => 31,
        '11' => 30,
        '12' => 31,
    ];

    return $month_arr[$month];
}
