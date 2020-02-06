<?php

/**
 * ECSHOP 订单管理
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: yehuaixiao $
 * $Id: order.php 17219 2011-01-27 10:49:19Z yehuaixiao $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
//require_once(ROOT_PATH . 'includes/lib_order.php');
//require_once(ROOT_PATH . 'includes/lib_goods.php');

/*------------------------------------------------------ */
//-- 订单查询
/*------------------------------------------------------ */

if ($_REQUEST['act'] == 'app_list') {
    /* 检查权限 */
    admin_priv('winning_record');
    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['03_order_list']);
    $smarty->assign('action_link', '');

    $order_list = draw_log();//精品商城订单类型
    $smarty->assign('order_list', $order_list['orders']);
    $smarty->assign('filter', $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count', $order_list['page_count']);
    $smarty->assign('full_page', 1);
    $smarty->assign('is_deliver', $order_list['is_deliver']);

    /* 显示模板 */
    $smarty->display('home/mid_award_list.html');
}
/*end fzp*/


/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {
    $order_list = draw_log();
    $smarty->assign('order_list', $order_list['orders']);
    $smarty->assign('filter', $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count', $order_list['page_count']);
    $smarty->assign('full_page', 1);
    $sort_flag = sort_flag($order_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);
    make_json_result($smarty->fetch('home/mid_award.html'), 'ok', array('filter' => $order_list['filter'], 'page_count' => $order_list['page_count']));
}

/*------------------------------------------------------ */
//-- 发货
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'update_order') {

    $goods_id = intval($_POST['id']);
    $order = $_POST['order_no'];

    $sql = "UPDATE xm_luck_draw_log SET is_deliver='1',order_no='{$order}'  WHERE id='{$goods_id}'";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }

}

/*------------------------------------------------------ */
//-- 订单详情页面
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'info') {
    $order_id = intval($_REQUEST['order_id']);
    if (empty($order_id)) {
        die('订单错误，请稍后再试');
    }
    //查出订单信息
    $sql = "SELECT * FROM xm_luck_draw_log o  WHERE o.order_id={$order_id} ";
    $order_info = $db->getRow($sql);

    switch ($order_info['is_deliver']) {
        case 0 :
            $status_back = "未发";
            break;
        case 1 :
            $status_back = "已发货";
            break;
        default :
            break;
    }

    $order_info['order_status_name'] = $status_back;

    $smarty->assign('order', $order_info);
    $smarty->assign('order_id', $order_id);

    assign_query_info();
    $smarty->display('home/mid_award.htm');
} elseif ($_REQUEST['act'] == 'send_goods') {
    $order_id = intval($_REQUEST['order_id']);

    $order_no = empty(intval($_REQUEST['order_no'])) ? 0 : intval($_REQUEST['order_no']);
    if (empty($order_id)) {
        echo json_encode(['status' => '0', 'msg' => '订单号错误,请刷新重试']);
        die;
    }

    if ($order_no == 0) {
        echo json_encode(['status' => '0', 'msg' => '出现错误']);
    }

    //查出订单信息
    $sql = "SELECT o.status FROM luck_draw_log o  WHERE o.order_id={$order_id} ";
    $order_status = $db->getOne($sql);
    if ($order_status != 0) {
        echo json_encode(['status' => '0', 'msg' => '订单已经发货']);
        die;
    }
    $time = time();

    $sql = "UPDATE xm_luck_draw_log SET order_no = '{$order_no}',is_deliver = 1 WHERE id = '{$order_id}'";

    if ($db->query($sql)) {
        //操作记录表
        $inst_sql = "INSERT INTO xm_orders_log (`order_id`,`log_user`,`log_note`,`log_time`) VALUES ('{$order_id}','{$_SESSION['admin_name']}','发货',{$time})";
        $db->query($inst_sql);
        echo json_encode(['status' => '1', 'msg' => '中秋活动奖品发货']);
        die;
    } else {
        echo json_encode(['status' => '0', 'msg' => '发生错误，请刷新重试']);
        die;
    }
}


/**
 *  获取订单列表信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function draw_log()
{

    /* 过滤信息 */
    $filter['phone'] = empty($_REQUEST['phone']) ? '' : trim($_REQUEST['phone']);

    /* 过滤信息 */
    $filter['mobile_phone'] = empty($_REQUEST['mobile_phone']) ? '' : trim($_REQUEST['mobile_phone']);

    /* 过滤信息 */
    $filter['is_deliver'] =empty($_REQUEST['is_deliver']) ? 0 : $_REQUEST['is_deliver'];

    if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1) {
        $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);

    }

    $time = strtotime('2019-09-27 00:00:00');
    $where = ' WHERE o.is_prize != 1 AND o.channel = 1';

    if ($filter['phone']) {
        $where .= " AND d_u.phone='{$filter['phone']}'";
    } else if ($filter['is_deliver'] >= 0) {
        $where .= " AND o.is_deliver={$filter['is_deliver']}";
    } else if ($filter['mobile_phone']) {
        $where .= " AND u.mobile_phone='{$filter['mobile_phone']}'";
    }

    /* 分页大小 */
    $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

    if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
        $filter['page_size'] = intval($_REQUEST['page_size']);
    } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
        $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
    } else {
        $filter['page_size'] = 15;
    }

    /* 记录总数 */

    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('luck_draw_log') . " AS o " .
        " LEFT JOIN xm_luck_goods_draw AS g  ON o.goods_id=g.id" .
        " LEFT JOIN xm_users AS u ON o.user_id=u.user_id" .
        " LEFT JOIN xm_luck_draw_user AS d_u ON u.user_id = d_u.user_id" .
        $where;

    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;


    $sql = "SELECT u.nickname,u.mobile_phone,o.id,o.is_deliver,g.goods_name,g.goods_img,o.order_no,d_u.address,d_u.phone,d_u.name " .
        " FROM " . $GLOBALS['ecs']->table('luck_draw_log') . " AS o " .
        " LEFT JOIN xm_luck_goods_draw AS g  ON o.goods_id=g.id" .
        " LEFT JOIN xm_users AS u ON o.user_id=u.user_id" .
        " LEFT JOIN xm_luck_draw_user AS d_u ON u.user_id = d_u.user_id" .
        $where .
        " ORDER BY o.id DESC " .
        " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    set_filter($filter, $sql);

    $row = $GLOBALS['db']->getAll($sql);

    /* 格式话数据 */
    foreach ($row AS $key => $value) {
        $row[$key]['goods_img'] = "https://" . STATIC_RESOURCE_URL . '/data/' . $value['goods_img'];

        switch ($value['is_deliver']) {
            case 0 :
                $status_back = "未发";
                break;
            case 1 :
                $status_back = "已发";
                break;
            default :
                break;
        }
        $row[$key]['order_status_name'] = $status_back;
    }

    $arr = array('orders' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'], 'is_deliver' => $filter['is_deliver']);

    return $arr;
}