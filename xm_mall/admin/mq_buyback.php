<?php

/**
 * H单管理
 *
 * @author yang
 * @version 1.0.0
 * @history
 * 1. 2017/8/24 yang created
 */
define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'includes/lib_order.php');
require_once(ROOT_PATH . 'includes/lib_goods.php');
use Illuminate\Database\Capsule\Manager as DB;//如果你不喜欢这个名称，as DB;就好
$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'buyback_list';
$function_name = 'action_' . $action;
if(! function_exists($function_name)) {
    $function_name = "action_buyback_list";
}

call_user_func($function_name);

/**
 * 页面默认载入动作
 */
function action_buyback_list(){

    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];

    /* 检查权限 */
    admin_priv('buyback_list');

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['04_buyback_list']);
    //action_link 页面右上角的按钮
    //$smarty->assign('action_link', array('href' => 'order.php?act=order_query&order_type=2', 'text' => $_LANG['mid_order_query']));

    $smarty->assign('status_list', [1=>'待回购',3=>'已回购', 4=>'已暂停', 5 => '新美排序']);   // 状态
    $smarty->assign('full_page',        1);

    $order_list = buyback_list();//H单列表

    $smarty->assign('order_list',   $order_list['orders']);
    $smarty->assign('filter',       $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count',   $order_list['page_count']);
    //$smarty->assign('sort_order_time', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();

    $smarty->display('mq_buyback_list.htm');

}

/**
 * 暂停H单
 */
function action_stop_usersByH()
{

    admin_priv('mq_hdan_pause');

    $admin_id = $_SESSION['admin_id']; // 管理员 ID
    $case   = $_REQUEST['type']; // 规定：0 正常 / 1 暂停
    $horders  = $_REQUEST['order_ids'];

    if (!$admin_id) {

        exit(json_encode(array('flag'=>0)));
    }

    switch ($case) {

        case 0:
            # 恢复
            $res = recovery($admin_id, $horders);
            break;
        case 1:
            # 暂停
            $res = pause($admin_id, $horders);
            break;

        default:
            return 'failure';
            break;
    }

    $flag = 1;

    if ($res == 'failure') {

        $flag = 0;
    }

    exit(json_encode(array('flag'=>$flag)));
}

/**
 * 搜索按钮动作
 */
function action_query(){

    $smarty = $GLOBALS['smarty'];

    /* 检查权限 */
    admin_priv('buyback_list');

    $order_list = buyback_list();//H单列表

    $smarty->assign('order_list',   $order_list['orders']);
    $smarty->assign('filter',       $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count',   $order_list['page_count']);

    make_json_result($smarty->fetch('mq_buyback_list.htm'), '', array('filter' => $order_list['filter'], 'page_count' => $order_list['page_count']));

}

/**
 *  获取订单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function buyback_list()
{
    $result = get_filter();
    if ($result === false)
    {
        /* 过滤信息 */
        $filter['bb_sn'] = empty($_REQUEST['bb_sn']) ? '' : trim($_REQUEST['bb_sn']);
        if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1)
        {
            $_REQUEST['contact'] = json_str_iconv($_REQUEST['contact']);
        }
        $filter['contact'] = empty($_REQUEST['contact']) ? '' : trim($_REQUEST['contact']);
        $filter['user_name'] = empty($_REQUEST['user_name']) ? '' : trim($_REQUEST['user_name']);
        $filter['bb_status'] = isset($_REQUEST['bb_status']) ? intval($_REQUEST['bb_status']) : -1;
        $filter['pause'] = empty($_REQUEST['pause']) ? '' : trim($_REQUEST['pause']);

        $where = 'WHERE 1=1 ';
        $orderby = ' o.create_at DESC';

        if ($filter['bb_sn'])
        {
            $where .= " AND o.bb_sn LIKE '%" . mysql_like_quote($filter['bb_sn']) . "%'";
        }
        if ($filter['contact'])
        {
            $where .= " AND o.contact LIKE '%" . mysql_like_quote($filter['contact']) . "%'";
        }

        $group_time = group_time($_REQUEST['start_time'], $_REQUEST['end_time']);

        $where .= $group_time ? : '';

        switch ($filter['bb_status']) {

            case 1:
            case 3:

                // 待回购 / 已回购
                $where .= " AND o.bb_status  = '$filter[bb_status]'";
            
                break;

            case 4:

                // 暂停中
                $where .= " AND o.is_stop <> 0";
                break;

            case 5:

                // 按照用户的新美积分排序
                // H 单状态是 待回购
                $orderby = ' c.money DESC';
                $where  .= ' AND o.bb_status  = 1';

                break;

            default:
                # code...
                break;
        }


        if ($filter['user_name'])
        {
            $where .= " AND ( u.user_name LIKE '%" . mysql_like_quote($filter['user_name'])
                   . "%' or u.email like  '%" . mysql_like_quote($filter['user_name'])
                   . "%' or a.address like  '%" . mysql_like_quote($filter['user_name'])
                   . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['user_name']) . "%' ) ";
        }

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0)
        {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        }
        elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0)
        {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        }
        else
        {
            $filter['page_size'] = 50;
        }

        /* 记录总数 */
        $sql = "SELECT COUNT(*) FROM %s AS o LEFT JOIN %s as u on o.user_id = u.user_id LEFT JOIN %s as a on a.user_id = u.user_id %s ";
        $sql = sprintf($sql,$GLOBALS['ecs']->table('mq_buy_back'),$GLOBALS['ecs']->table('users'),$GLOBALS['ecs']->table('user_address'),$where);

        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        foreach (array('bb_sn', 'contact') AS $val)
        {
            $filter[$val] = stripslashes($filter[$val]);
        }

        $sql = 'SELECT o.*,u.user_name,c.unlimit as money'. //,a.address '.
            ' FROM %s as o '.
            ' LEFT JOIN %s as u on o.user_id = u.user_id '.
            ' LEFT JOIN %s as a on a.user_id = u.user_id '.
            ' LEFT JOIN %s as x on x.user_id = u.user_id '.
            ' LEFT JOIN %s as c on c.user_id = x.user_id %s'.
            ' ORDER BY %s' .
            ' LIMIT %d,%d';

        $sql = sprintf(
                $sql,$GLOBALS['ecs']->table('mq_buy_back'),
                $GLOBALS['ecs']->table('users'),
                $GLOBALS['ecs']->table('user_address'),
                $GLOBALS['ecs']->table('mq_users_extra'),
                $GLOBALS['ecs']->table('xps'),
                $where,
                $orderby,
                ($filter['page'] - 1)*$filter['page_size'],
                $filter['page_size']);
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $rows = $GLOBALS['db']->getAll($sql);

    $arr1 = array();

    /* 格式化数据 */
    foreach ($rows AS $key => $row)
    {
        //为了精确到分，挖掉秒
        $expire_at = gmstr2time(date('y-m-d H:i',$row['expire_at']));

        $arr1[$row['bb_id']] = array(
            'bb_id'       => $row['bb_id'],
            'bb_sn'       => $row['bb_sn'],
            'user_id'       => $row['user_id'],
            'user_name'       => $row['user_name'],
            'goods_id'       => $row['goods_id'],
            'goods_name'       => $row['goods_name'],
            'goods_sn'       => $row['goods_sn'],
            'product_id'       => $row['product_id'],
            'goods_number'       => $row['goods_number'],
            'consume_money'       => $row['consume_money']/$row['goods_number'],
            'cash_money'       => $row['cash_money']/$row['goods_number'],
            'consume_money_all'       => $row['consume_money'],
            'cash_money_all'       => $row['cash_money'],
            'bb_status'       => $row['bb_status'],
            'create_at'       => $row['create_at'],
            'pay_at' => $row['pay_at'],
            'pay_at_fmt' => date('y-m-d H:i', $row['pay_at']),
            'url' =>build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']),
            'thumb' =>get_image_path($row['goods_id'], $row['goods_thumb'],true),

            'bb_status_name'=>$row['bb_status']==1?'待回购':'已回购',
            'expire_at' => $row['expire_at'],
            'expire_at_fmt' => date('y-m-d H:i', $row['expire_at']),
            'goods_attr'=>$row['goods_attr'],
            'rest_time'=>$expire_at-time(),
            'rest_time_fmt'=>timediff(time(),$expire_at) ,
            'cash_money_bb' => $row['cash_money_bb'],
            'contact' => $row['contact'],
            'contact_phone' => $row['contact_phone'],
            'is_stop' => $row['is_stop'],
            'money' => $row['money']
        );

        if($row['is_stop'] != 0){
            $arr1[$row['bb_id']]['rest_time_fmt'] =  '暂停中';
        }
    }

    $arr = array('orders' => $arr1, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
    return $arr;
}

function timediff($begin_time,$end_time)
{
//    if($begin_time < $end_time){
//        $starttime = $begin_time;
//        $endtime = $end_time;
//    }else{
//        $starttime = $end_time;
//        $endtime = $begin_time;
//    }

    //计算天数
    $timediff = $end_time-$begin_time;
    if($timediff<0){
        $timediff =0;
    }
    $days = intval($timediff/86400);
    //计算小时数
    $remain = $timediff%86400;
    $hours = intval($remain/3600);
    //计算分钟数
    $remain = $remain%3600;
    $mins = intval($remain/60);
    //计算秒数
    $secs = $remain%60;
    //$res = array("day" => $days,"hour" => $hours,"min" => $mins,"sec" => $secs);

    $result = '';
//    if($days>0){
//        $result = sprintf('%d天%d小时%d分%d秒',$days,$hours,$mins,$secs);
//    } else if($hours>0){
//        $result = sprintf('%d小时%d分%d秒',$hours,$mins,$secs);
//    } else if($mins>0){
//        $result = sprintf('%d分%d秒',$mins,$secs);
//    } else {
//        $result = sprintf('%d秒',$secs);
//    }
    if($days>0){
        $result = sprintf('%d天%d小时%d分',$days,$hours,$mins);
    } else if($hours>0){
        $result = sprintf('%d小时%d分',$hours,$mins);
    } else if($mins>0){
        $result = sprintf('%d分',$mins);
    } else {
        $result = sprintf('%d分',$mins);
    }
    return $result;
}


// ================================================================= //

    /*
    |- 20180414 1517 V 恢复
    */
    function recovery($admin_id, $order_ids) {

        $current = time();
        $values = [

            'start_user_id' => $admin_id,
            'end_time' => $current,
            'status' => 2
        ];

        DB::beginTransaction();

        if (!$order_ids) {

            // 恢复所有
            $order_res = DB::update("UPDATE `xm_mq_buy_back` SET `expire_at` = `expire_at` + ($current - `is_stop`),
                        `update_at` = $current, `is_stop` = 0 WHERE `bb_status` IN (1,2) AND `is_stop` <> 0");

            // 更新暂停/恢复记录
            $res = DB::table('hdan_pause') -> where('end_user_id', '=', 0) -> update($values);

        } else {

            // 恢复指定记录
            $oid_str = implode(',', $order_ids);
            $order_res = $order_res = DB::update("UPDATE `xm_mq_buy_back` SET `expire_at` = `expire_at` + ($current - `is_stop`),
                        `update_at` = $current, `is_stop` = 0 WHERE `bb_id` IN ($oid_str) AND bb_status IN (1,2)");

            $res = DB::table('hdan_pause') -> where('end_user_id', 'in', $oid_str) -> update($values);
        }

        if (!$order_res) {

            DB::rollback();
            return 'failure';
        }

        DB::commit();
        return 'success';
    }


    /*
    |- 20180414 1535 V 暂停
    */
    function pause($admin_id, $order_ids) {

        $current = time();
        $values = [

            'start_user_id' => $admin_id,
            'start_time' => $current,
            'status' => 1
        ];

        DB::beginTransaction();

        if (!$order_ids) {

            // 暂停所有
            $order_res = DB::table('mq_buy_back')
                            -> whereIn('bb_status', [1, 2])
                            -> update(['is_stop'=>$current,'update_at'=>$current]);

            // 更新暂停/恢复记录
            $values['end_user_id'] = 0; // 标记当前为所有
            $res = DB::table('hdan_pause')->insert($values);

        } else {

            // 暂停指定记录

            $order_res = DB::table('mq_buy_back')
                            -> whereIn('bb_id', $order_ids)
                            -> whereIn('bb_status', [1, 2])
                            -> update(['is_stop'=>$current,'update_at'=>$current]);

            foreach ($order_ids as $k => $v) {

                $values['end_user_id'] = $v; // 标记一个订单
                DB::table('hdan_pause')->insert($values);
            }
        }

        if (!$order_res) {

            DB::rollback();
            return 'failure';
        }

        DB::commit();
        return 'success';
    }

    /*
    |- 20180417 V 组织时间
    */
    function group_time($start, $end) {

        if (!$start && !$end) { return false; }

        $start = strtotime($start);
        $end   = strtotime($end);

        if ($start) {

            $sql_str = " AND o.pay_at > $start ";
        }

        if ($end) {

            $sql_str = " AND o.pay_at < $end ";
        }

        if ($start && $end) {

            $sql_str = " AND (o.pay_at BETWEEN $start AND $end) ";
        }

        return $sql_str;
    }
