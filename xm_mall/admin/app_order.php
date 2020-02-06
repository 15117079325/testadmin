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
require_once(ROOT_PATH . 'includes/lib_order.php');
require_once(ROOT_PATH . 'includes/lib_goods.php');

/*------------------------------------------------------ */
//-- 订单查询
/*------------------------------------------------------ */

if ($_REQUEST['act'] == 'app_list')
{
    /* 检查权限 */
    admin_priv('app_order');
    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['03_order_list']);
    $smarty->assign('action_link', '');



    $smarty->assign('full_page',        1);

    $order_list = order_list();//精品商城订单类型

    $smarty->assign('order_list',   $order_list['orders']);
    $smarty->assign('filter',       $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count',   $order_list['page_count']);
    $smarty->assign('sort_order_time', '<img src="images/sort_desc.gif">');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('home/app_order_list.htm');
}
/*end fzp*/


/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{

    $order_list = order_list();
    $smarty->assign('order_list',   $order_list['orders']);
    $smarty->assign('filter',       $order_list['filter']);
    $smarty->assign('record_count', $order_list['record_count']);
    $smarty->assign('page_count',   $order_list['page_count']);
    $sort_flag  = sort_flag($order_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);
    make_json_result($smarty->fetch('home/app_order_list.htm'), '', array('filter' => $order_list['filter'], 'page_count' => $order_list['page_count']));
}
/*------------------------------------------------------ */
//-- 订单详情页面
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'info')
{
    $order_id = intval($_REQUEST['order_id']);
    if(empty($order_id)){
        die('订单错误，请稍后再试');
    }

    //查出订单信息
    $sql = "SELECT o.*,u.user_name FROM xm_orders o LEFT JOIN xm_users u ON o.user_id=u.user_id WHERE o.order_id={$order_id} ";
    $order_info = $db->getRow($sql);
    $detail_sql = "SELECT od.p_title,od.size_title,od.size_id,od.od_num,od.p_img,od.od_cash,od.od_balance,od.od_discount FROM xm_order_detail od WHERE od.order_id={$order_id}";
    $detail_info = $db->getAll($detail_sql);

    $order_info['address'] = $order_info['area'].$order_info['address'];
    switch ($order_info['order_status'])
        {
            case 1 :
                $status_back = "待支付";
                break;
            case 2 :
                $status_back = "待发货";
                break;
            case 3 :
                $status_back = "待收货";
                break;
            case 4 :
                $status_back = "待评价";
                break;
            case 5 :
                $status_back = "已完成";
                break;
            default :
                break;
        }
        switch ($order_info['order_payway'])
        {
            case 1 :
                $order_info['order_payway'] = "优惠券支付";
                break;
            case 2 :
                $order_info['order_payway'] = "支付宝";
                break;
            case 3 :
                $order_info['order_payway'] = "微信";
                break;
            default :
                break;
        }
        $order_info['order_gmt_create'] = date('Y-m-d H:i',$order_info['order_gmt_create']);
        $order_info['order_gmt_pay'] = date('Y-m-d H:i',$order_info['order_gmt_pay']);
        $order_info['order_gmt_send'] = empty($order_info['order_gmt_send']) ? '未发货' : date('Y-m-d H:i',$order_info['order_gmt_send']);
      $order_info['order_status_name'] = $status_back;

      foreach($detail_info as $k=>$v){
          $detail_info[$k]['p_img'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/'.$v['p_img']);
      }
      //获取所有的快递信息
    $shipping_list = $GLOBALS['db']->getAll('SELECT id,name FROM ' . $GLOBALS['ecs']->table('express_code'));
    $shipping_list = array_column($shipping_list,'name','id');
    //操作纪律
     //获取所有的快递信息
    $order_log = $GLOBALS['db']->getAll('SELECT * FROM ' . $GLOBALS['ecs']->table('orders_log') . " WHERE order_id = {$order_id}");
    $smarty->assign('shipping_list', $shipping_list);
    $smarty->assign('order_log', $order_log);
    $smarty->assign('order',  $order_info);
    $smarty->assign('order_id',  $order_id);
    $smarty->assign('order_info',  $detail_info);
    assign_query_info();
    $smarty->display('home/app_order_info.htm');
}

elseif ($_REQUEST['act'] == 'send_goods')
{
    $order_id = intval($_REQUEST['order_id']);
    $shipping_number = empty(trim($_REQUEST['shipping_number'])) ? '' : trim($_REQUEST['shipping_number']);
    $shipping_name = empty(trim($_REQUEST['shipping_name'])) ? '' : trim($_REQUEST['shipping_name']);
    $shipping_id = empty(intval($_REQUEST['shipping_id'])) ? 0 : intval($_REQUEST['shipping_id']);
    if(empty($order_id)){
        echo json_encode(['status'=>'0','msg'=>'订单号错误,请刷新重试']);
        die;
    }
    //查出订单信息
    $sql = "SELECT o.order_status FROM xm_orders o  WHERE o.order_id={$order_id} ";
    $order_status = $db->getOne($sql);
    if($order_status!=2){
        echo json_encode(['status'=>'0','msg'=>'订单状态错误，请刷新重试']);
        die;
    }
    $time = time();
    $sql = "UPDATE xm_orders SET shipping_number='{$shipping_number}',shipping_name='{$shipping_name}',shipping_id='{$shipping_id}',order_status=3,order_gmt_send={$time} WHERE order_id={$order_id}";
    $goods_ids = $db->getAll("SELECT p_id FROM xm_order_detail WHERE order_id={$order_id}");
    $p_ids ='(';
    foreach($goods_ids as $k=>$v){
        $p_ids .= $v['p_id'].',';
    }
    $p_ids = rtrim($p_ids,',').')';
    $db->query("UPDATE xm_product SET p_sold_num=p_sold_num+1 WHERE p_id IN $p_ids ");
    if($db->query($sql)){
        //操作记录表
        $inst_sql ="INSERT INTO xm_orders_log (`order_id`,`log_user`,`log_note`,`log_time`) VALUES ('{$order_id}','{$_SESSION['admin_name']}','发货',{$time})";
        $db->query($inst_sql);
        echo json_encode(['status'=>'1','msg'=>'订单发货']);
        die;
    }else{
        echo json_encode(['status'=>'0','msg'=>'发生错误，请刷新重试']);
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
function order_list()
{

        /* 过滤信息 */
        $filter['order_sn'] = empty($_REQUEST['order_sn']) ? '' : trim($_REQUEST['order_sn']);
        /* 过滤信息 */
        $filter['order_name'] = empty($_REQUEST['order_name']) ? '' : trim($_REQUEST['order_name']);
        /* 过滤信息 */
        $filter['order_status'] = empty($_REQUEST['order_status']) ? '' : trim($_REQUEST['order_status']);
        if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1)
        {
            $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);

        }

		$where = 'WHERE o.order_status>1 AND o.order_cancel=1 ';

        if($filter['order_sn']){
            $where .= " AND o.order_sn='{$filter['order_sn']}'";
        }
        if($filter['order_name']){
            $user_id = $GLOBALS['db']->getOne("SELECT user_id FROM xm_users WHERE user_name='{$filter['order_name']}'");
            $user_id = empty($user_id) ? 0 : $user_id;
            $where .= " AND o.user_id={$user_id}";
        }
        if($filter['order_status']){
            $where .= " AND o.order_status={$filter['order_status']}";
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
            $filter['page_size'] = 15;
        }

        /* 记录总数 */
        if ($filter['user_name'])
        {
            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('orders') . " AS o ,".
                   $GLOBALS['ecs']->table('users') . " AS u " . $where;
        }
        else
        {
            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('orders') . " AS o ". $where;
        }

        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;


        	$sql = "SELECT o.order_id,o.order_sn,o.order_money,o.order_discount,o.consignee,o.mobile,o.area,o.address,o.order_status,o.order_cancel,o.order_gmt_create,o.order_gmt_pay,u.user_name" .
				" FROM " . $GLOBALS['ecs']->table('orders') . " AS o " .
                " LEFT JOIN " .$GLOBALS['ecs']->table('users'). " AS u ON u.user_id=o.user_id ". $where .
                " ORDER BY o.order_id DESC ".
                " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";

        set_filter($filter, $sql);

    $row = $GLOBALS['db']->getAll($sql);

    /* 格式话数据 */
    foreach ($row AS $key => $value)
    {
        //$row[$key]['order_money'] = price_format($value['order_money']);
        //$row[$key]['order_discount'] = price_format($value['order_discount']);
        $row[$key]['order_money'] = $value['order_money'];
        $row[$key]['order_gmt_create'] = date('Y-m-d H:i',$value['order_gmt_create']);
        $row[$key]['order_gmt_pay'] = date('Y-m-d H:i',$value['order_gmt_pay']);
        switch ($value['order_status'])
        {
            case 1 :
                $status_back = "待支付";
                break;
            case 2 :
                $status_back = "待发货";
                break;
            case 3 :
                $status_back = "待收货";
                break;
            case 4 :
                $status_back = "待评价";
                break;
            case 5 :
                $status_back = "已完成";
                break;
            default :
                break;
        }
        $row[$key]['order_status_name'] = $status_back;
    }

    $arr = array('orders' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}


