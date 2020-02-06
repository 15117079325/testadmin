<?php

/*
|- 20180521 1554 V 数据统计
*/


define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/admin/statistic.php');

// act操作项的初始化
if (empty($_REQUEST['act'])) {

    $_REQUEST['act'] = 'main';

} else {

    $_REQUEST['act'] = trim($_REQUEST['act']);
}

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'main';
/* 路由 */
$function_name = 'action_' . $action;

if (!function_exists($function_name)) {

    $function_name = "action_main";
}

call_user_func($function_name);

// -----------------------------------------------------------------------------
function action_main()
{

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];

    $smarty->assign('lang', $_LANG);

    /* 模板赋值 */
    $smarty->assign('ur_here', "App首页管理"); // 当前导航

    /* 显示模板 */
    assign_query_info();
    $smarty->display('home/deal_main.htm');
}

function action_trading_list()
{
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ads_list = $db->getAll("SELECT * FROM xm_trading_hall_explain");
    $smarty->assign('action_link', array('text' => '添加说明', 'href' => 'app_deal.php?act=add_trading'));
    $smarty->assign('full_page', 1);
    $smarty->assign('nav_list', $ads_list);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    $sort_flag = sort_flag($ads_list['filter']);
    assign_query_info();
    $smarty->display('home/trading_list.htm');
}

function action_add_trading()
{
    $smarty = $GLOBALS['smarty'];
    create_html_editor('content', htmlspecialchars(''));
    $smarty->assign('action_link', array('href' => 'app_deal.php?act=trading_list', 'text' => '返回列表'));
    $smarty->assign('form_act', 'insert');
    $smarty->assign('action', 'add_trading');
    assign_query_info();
    $smarty->display('home/trading_info.htm');
}

function action_insert()
{
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    /* 初始化变量 */
    $type = trim($_REQUEST['type']);
    $content = $_POST['content'];
    $res = $db->getRow("SELECT * FROM xm_trading_hall_explain WHERE type={$type}");
    if ($res) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('存在该类型说明，请前往编辑', 0, $link);
    }
    if (empty($content)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('内容不能为空', 0, $link);
    }
    $sql = "INSERT INTO xm_trading_hall_explain (type,content) VALUES ($type,'{$content}')";
    $db->query($sql);
    $link[0]['text'] = $_LANG['back_list'];
    $link[0]['href'] = 'app_deal.php?act=trading_list';
    sys_msg('添加成功', 0, $link);
}

function action_edit_trading()
{
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $tid = $_REQUEST['tid'];
    $nav = $db->getRow("SELECT * FROM xm_trading_hall_explain WHERE tid={$tid}");
    create_html_editor('content', htmlspecialchars($nav['content']));
    $smarty->assign('action_link', array('href' => 'app_deal.php?act=trading_list', 'text' => '返回列表'));
    $smarty->assign('nav', $nav);
    $smarty->assign('form_act', 'update');
    $smarty->assign('action', 'add_trading');
    assign_query_info();
    $smarty->display('home/trading_info.htm');
}

function action_update()
{
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    /* 初始化变量 */
    $type = trim($_REQUEST['type']);
    $content = $_POST['content'];
    $tid = $_POST['id'];
    $res = $db->getRow("SELECT * FROM xm_trading_hall_explain WHERE tid={$tid}");
    if ($res['type'] != $type) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('说明类型不一致，请重新编辑', 0, $link);
    }
    if (empty($content)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('内容不能为空', 0, $link);
    }
    $sql = "UPDATE xm_trading_hall_explain SET content='$content' WHERE tid={$tid}";
    $db->query($sql);
     $link[0]['text'] = $_LANG['back_list'];
    $link[0]['href'] = 'app_deal.php?act=trading_list';
    sys_msg('编辑成功', 0, $link);
}

function action_deal_list()
{
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty->assign('lang', $_LANG);
    $ads_list = get_deallist();
    $smarty->assign('full_page', 1);
    $thedate = $_REQUEST['thedate'] ?: date('Y-m-d');
    $status = empty($_REQUEST['status']) ? 0 : intval($_REQUEST['status']);
    $smarty->assign('thedate', $thedate);
    $smarty->assign('status', $status);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    assign_query_info();
    $smarty->display('home/deal_list.htm');
}

function action_query()
{
    $smarty = $GLOBALS['smarty'];
    $ads_list = get_deallist();
    $thedate = $_REQUEST['thedate'] ?: date('Y-m-d');
    $status = empty($_REQUEST['status']) ? 0 : intval($_REQUEST['status']);
    $smarty->assign('thedate', $thedate);
    $smarty->assign('status', $status);
    $smarty->assign('nav_list', $ads_list['nav']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);


    make_json_result($smarty->fetch('home/deal_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

function action_edit_deal()
{
    // 全局变量

    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    $sql = "SELECT t.*,u.user_name,u.mobile_phone,xt.* FROM xm_trade_detail t LEFT JOIN xm_users u ON t.seller_id=u.user_id LEFT JOIN xm_trade xt ON xt.trade_id=t.t_id WHERE t.td_id={$_GET['tid']}";
    $row = $db->GetRow($sql);
    $row['user_name'] = addslashes($row['user_name']);
    if ($row) {
        switch ($row['td_status']) {
            case 1:
                $row['tip'] = '待确认';
                break;
            case 2:
                $row['tip'] = '完成';
                break;
            case 3:
                $row['tip'] = '有误';
                break;
            case 4:
                $row['tip'] = '确认有误';
                break;
            case 5:
                $row['tip'] = '已取消';
                break;
        }

        $user['user_name'] = $row['user_name'];
        $user['mobile_phone'] = $row['mobile_phone'];
        if ($row['buyer_id']) {
            $user_info = $db->getRow("SELECT user_name,mobile_phone FROM xm_users WHERE user_id={$row['buyer_id']}");
            $user['buy_name'] = $user_info['user_name'];
            $user['buy_mobile_phone'] = $user_info['mobile_phone'];
        } else {
            $user['buy_name'] = 0;
            $user['buy_mobile_phone'] = 0;
        }
        $user['tip'] = $row['tip'];
        $user['td_num'] = $row['td_num'];
        $user['trade_gmt_create'] = date("Y-m-d", $row['create_at']);
        $user['trade_gmt_sure'] = empty($row['complete_at']) ? '' : date("Y-m-d", $row['complete_at']);
        $user['bank_account'] = empty($row['bank_account']) ? '' : $row['bank_account'];
        $user['bank_name'] = empty($row['bank_name']) ? '' : $row['bank_name'];
        $user['ali_account'] = empty($row['ali_account']) ? '' : $row['ali_account'];
        $user['ali_owner'] = empty($row['ali_owner']) ? '' : $row['ali_owner'];
//        $user['trade_gmt_commit'] = empty($row['trade_gmt_commit']) ? '' : date("Y-m-d", $row['trade_gmt_commit']);
        $user['trade_voucher'] = empty($row['td_voucher']) ? '' : \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath('data/' . $row['td_voucher']);
        $user['trade_status'] = $row['trade_status'];
    } else {
        $link[] = array(
            'text' => $_LANG['go_back'], 'href' => 'app_deal.php?act=deal_list'
        );
        sys_msg($_LANG['username_invalid'], 0, $link);

    }

    $smarty->assign('lang', $_LANG);
    assign_query_info();
    $smarty->assign('ur_here', '返回列表');
    $smarty->assign('action_link', array(
        'text' => '返回列表', 'href' => 'app_deal.php?act=deal_list&' . list_link_postfix()
    ));
    $smarty->assign('tid', $_GET['tid']);
    $smarty->assign('user', $user);
    $smarty->assign('form_action', 'update');
    $smarty->display('home/deal_info.htm');
}


function get_deallist()
{
    $filter = array();
    $time = empty($_REQUEST['thedate']) ? strtotime(date("Y-m-d"), time()) : strtotime($_REQUEST['thedate']);
    $status = empty($_REQUEST['status']) ? 1 : intval($_REQUEST['status']);
    $filter['status'] = $status;

    //出售中
    if($status == 6) {

        $end_time = $time + 24 * 3600 - 1;
        $where = " WHERE trade_status = 1 AND trade_num > 0";
        if ($time) {
            // $where .= " AND trade_gmt_create BETWEEN $time AND $end_time ";
            $where .= " AND trade_gmt_create < $time ";
        }
        /* 获得总记录数据 */
        $sql = 'SELECT COUNT(*) FROM xm_trade' . $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        //查询出售信息
        $arr = array();
        $sql = "SELECT trade_id as td_id,user_name,trade_status as td_status,trade_num as td_num, trade_gmt_create as trade_gmt_create FROM `xm_trade` ".$where.'ORDER by td_id DESC';

        $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

        while ($rows = $GLOBALS['db']->fetchRow($res)) {
            $rows['trade_gmt_create'] = date("Y-m-d H:i:s", $rows['trade_gmt_create']);
            switch ($rows['td_status']) {
                case 1:
                    $rows['td_status'] = '出售中';
                    break;
            }

            $arr[] = $rows;
        }

    }
    //求购中
    if($status == 7) {

        $end_time = $time + 24 * 3600 - 1;
        $where = " WHERE status = 1 AND buy_num > 0";
        if ($time) {
            // $where .= " AND tb_gmt_create BETWEEN $time AND $end_time ";
            $where .= " AND tb_gmt_create < $time ";
        }
        /* 获得总记录数据 */
        $sql = 'SELECT COUNT(*) FROM xm_trade_buy' . $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        //查询出售信息
        $arr = array();
        $sql = "SELECT tb_id as td_id,user_name,status as td_status,buy_num as td_num, tb_gmt_create as trade_gmt_create FROM `xm_trade_buy` ".$where.'ORDER by td_id DESC';

        $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

        while ($rows = $GLOBALS['db']->fetchRow($res)) {
            $rows['trade_gmt_create'] = date("Y-m-d H:i:s", $rows['trade_gmt_create']);
            switch ($rows['td_status']) {
                case 1:
                    $rows['td_status'] = '求购中';
                    break;
            }

            $arr[] = $rows;
        }
    }

    //交易中
    if($status <= 5) {
    
        $end_time = $time + 24 * 3600 - 1;
        $where = " WHERE ad.td_status = $status ";
        if ($time) {
            // $where .= " AND ad.create_at BETWEEN $time AND $end_time ";
            $where .= " AND ad.create_at < $time ";
        }

        /* 获得总记录数据 */
        $sql = 'SELECT COUNT(*) FROM xm_trade_detail as ad ' . $where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        /* 获得广告数据 */
        $arr = array();
        $sql = 'SELECT ad.*,u.user_name ' .
            'FROM ' . $GLOBALS['ecs']->table('trade_detail') . 'AS ad LEFT JOIN xm_users u ON ad.seller_id=u.user_id ' . $where .
            'ORDER by ad.td_id DESC ';

        $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

        while ($rows = $GLOBALS['db']->fetchRow($res)) {
            $rows['trade_gmt_create'] = date("Y-m-d H:i:s", $rows['create_at']);
            switch ($rows['td_status']) {
                case 1:
                    $rows['td_status'] = '待确认';
                    break;
                case 2:
                    $rows['td_status'] = '完成交易';
                    break;
                case 3:
                    $rows['td_status'] = '有误';
                    break;
                case 4:
                    $rows['td_status'] = '确认有误';
                    break;
                case 5:
                    $rows['td_status'] = '已取消';
                    break;
            }

            $arr[] = $rows;
        }
    }

    $filter['thedate'] = date('Y-m-d', $time);
    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

}

function action_check_status()
{
    $db = $GLOBALS['db'];
    $tid = intval($_POST['tid']);
    $status = intval($_POST['status']);
    $res = $db->getRow("SELECT * FROM xm_trade WHERE trade_id={$tid}");
    if ($res) {
        if ($res['trade_status'] == 5) {
            //代表确实有误
            if ($status == 1) {
                $sql = "UPDATE xm_trade SET trade_status = 6 WHERE trade_id={$tid}";
                $ret = $db->query($sql);
                if ($ret) {
                    $amount = $res['trade_num'] + $res['trade_num'] * $res['cost_rate'] / 100;
                    $sql = "UPDATE xm_tps SET unlimit=unlimit + {$amount},freeze=freeze-{$amount} WHERE user_id={$res['user_id']}";
                    $ret1 = $db->query($sql);
                    if ($ret1) {
                        echo json_encode(['status' => 1]);
                        die;
                    } else {
                        echo json_encode(['status' => 0]);
                        die;
                    }
                } else {
                    echo json_encode(['status' => 0]);
                    die;
                }
            } elseif ($status == 2) {
                $sql = "UPDATE xm_trade SET trade_status = 3 WHERE trade_id={$tid}";
                $ret = $db->query($sql);
                if ($ret) {
                    $amount = $res['trade_num'] + $res['trade_num'] * $res['cost_rate'] / 100;
                    $sql = "UPDATE xm_tps SET freeze=freeze-{$amount} WHERE user_id={$res['user_id']}";
                    $ret1 = $db->query($sql);
                    if ($ret1) {
                        $db->query("UPDATE xm_tps SET unlimit=unlimit+{$res['trade_num']} WHERE user_id={$res['buy_user_id']}");
                        echo json_encode(['status' => 1]);
                        die;
                    } else {
                        echo json_encode(['status' => 0]);
                        die;
                    }
                } else {
                    echo json_encode(['status' => 0]);
                    die;
                }
            }
        } else {
            echo json_encode(['status' => 0]);
            die;
        }
    } else {
        echo json_encode(['status' => 0]);
        die;
    }
}

function action_rec()
{

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];

    $smarty->assign('lang', $_LANG);

    /* 模板赋值 */
    $smarty->assign('ur_here', " 交易大厅日出售，购买T积分曲线图"); // 当前导航

    $ret = get_rec_chart($_REQUEST['month']);

    /* 显示模板 */
    $month = $_REQUEST['month'] ?: date('Ym');
    assign_query_info();
    $smarty->assign('month', $month);
    $smarty->assign('ret', $ret);
    $smarty->display('home/deal_rec.htm');
}

/*
|- 交易日曲线图 的图表
*/
function get_rec_chart($month)
{

    if ($month) {

        $start = $month . '01';
        $end = $month . '31';

        $condi = " WHERE td_create_at BETWEEN $start AND $end";

    } else {

        $condi = " WHERE td_create_at <= " . date('Ymd');
    }

    $ret = $GLOBALS['db']->getAll("SELECT * FROM xm_trade_data $condi ORDER BY td_create_at LIMIT " . get_month_days($month));

    if (!$ret) {
        return '';
    }

    $desc = [];
    foreach ($ret as $k => $v) {

        $desc[] = "'" . $v['td_create_at'] . "'";
        $success_money_data[] = "'" . $v['td_success_money'] . "'";
        $success_num_data[] = "'" . $v['td_success_num'] . "'";
        $sell_money_data[] = "'" . $v['td_sell_money'] . "'";
        $sell_num_data[] = "'" . $v['td_sell_num'] . "'";
        $buy_money_data[] = "'" . $v['td_buy_money'] . "'";
        $buy_num_data[] = "'" . $v['td_buy_num'] . "'";
        $service_money_data[] = "'" . $v['td_service_money'] . "'";
    }

    $res = [
        'date_str' => implode(',', $desc),
        'success_money_str' => implode(',', $success_money_data),
        'success_num_str' => implode(',', $success_num_data),
        'sell_money_str' => implode(',', $sell_money_data),
        'sell_num_str' => implode(',', $sell_num_data),
        'buy_money_str' => implode(',', $buy_money_data),
        'buy_num_str' => implode(',', $buy_num_data),
        'service_money_str' => implode(',', $service_money_data)
    ];

    return $res;
}

function get_month_days($month)
{

    if (!$month) {
        return 30;
    }

    $year = substr($month, 0, 4);
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







