<?php
define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');


if ($_REQUEST['act'] == 'order_excel')
{
    $smarty->display('home/app_excel.htm');
}
elseif($_REQUEST['act'] == 'excel')
{
    //var_dump(123);die();
    header('Content-type:text/html;charset=utf-8');
    $PHPExcel = new PHPExcel();
    $express_company = get_express_company();

    //设置excel属性基本信息
    $PHPExcel->getProperties()->setCreator("Neo")
        ->setLastModifiedBy("Neo")
        ->setTitle("")
        ->setSubject("订单列表")
        ->setDescription("")
        ->setKeywords("订单列表")
        ->setCategory("");
    //存储Excel数据源到其他工作薄
    $PHPExcel->createSheet();
    $subObject = $PHPExcel->getSheet(1);
    $subObject->setTitle('data');
    //var_dump($express_company);die();
    foreach ($express_company as $key => $value){
        $subObject->setCellValue('A'.($key+1),$value['name']);
        $companyList[$value['id']] = $value['name'];
    }
    //var_dump($companyList[$row['id']]);die();
    $subObject->getColumnDimension('A')->setWidth(30);

    //保护数据源
    $subObject->getProtection()->setSheet(true);
    $subObject->protectCells('A1:A1000',time());

    $PHPExcel->setActiveSheetIndex(0);
    $PHPExcel->getActiveSheet()->setTitle("订单列表");


    $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(22);//订单号
    $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);//发货单号
    $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);//订单状态
    $PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);//购货人
    $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(22);//下单时间
    $PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(40);//商品名称
    $PHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(50);//商品规格属性
    $PHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);//数量
    $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);//收货人
    $PHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(20);//手机/电话
    $PHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(35);//地址
    $PHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(30);//客户留言
    $PHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(22);//发货时间
    $PHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(18);//快递公司
    $PHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(20);//快递单号
//edit end



//edit by jixf2017年8月29日12:17:19
    $PHPExcel->getActiveSheet()->setCellValue('A1', '订单编号');//加个空格，防止时间戳被转换
    $PHPExcel->getActiveSheet()->setCellValue('B1', '快递单号');
    $PHPExcel->getActiveSheet()->setCellValue('C1', '订单状态');
    $PHPExcel->getActiveSheet()->setCellValue('D1', '购买人');
    $PHPExcel->getActiveSheet()->setCellValue('E1', '下单时间');
    $PHPExcel->getActiveSheet()->setCellValue('F1', '商品名字');
    $PHPExcel->getActiveSheet()->setCellValue('G1', '商品规格');
    $PHPExcel->getActiveSheet()->setCellValue('H1', '商品数量');
    $PHPExcel->getActiveSheet()->setCellValue('I1', '收货人');
    $PHPExcel->getActiveSheet()->setCellValue('J1', '手机号码');
    $PHPExcel->getActiveSheet()->setCellValue('K1', '收货地址');
    $PHPExcel->getActiveSheet()->setCellValue('L1', '客户留言');
    $PHPExcel->getActiveSheet()->setCellValue('M1', '发货时间');
    $PHPExcel->getActiveSheet()->setCellValue('N1', '快递名');
    // end edit
    $hang = 1;


    /* 查询 */
    // 订单状态
    $order_status = intval($_REQUEST['order_status']);
    // 下单开始时间
    $start_time = empty($_REQUEST['start_time']) ? '' : (strpos($_REQUEST['start_time'], '-') > 0 ?  local_strtotime($_REQUEST['start_time']) : $_REQUEST['start_time']);
    // 下单结束时间
    $end_time = empty($_REQUEST['end_time']) ? '' : (strpos($_REQUEST['end_time'], '-') > 0 ?  local_strtotime($_REQUEST['end_time']) : $_REQUEST['end_time']);
    $where = 'WHERE o.order_cancel=1  ';


    if($order_status > 0)
    {
        $where .= " AND o.order_status = '$order_status' ";

    }



    if($start_time != '' && $end_time != '')
    {
        $where .= " AND o.order_gmt_create >= '$start_time' AND o.order_gmt_create <= '$end_time' ";
    }else if($start_time!= '' && empty($end_time)){
        $where .= " AND o.order_gmt_create >= '$start_time' ";

    }else if(empty($start_time)  &&  $end_time!=''){
        $where .= " AND o.order_gmt_create <= '$end_time' ";
    }


    $sql = "SELECT o.*,e.user_name,od.* FROM " . $GLOBALS['ecs']->table('orders')
        . " o LEFT JOIN " . $GLOBALS['ecs']->table('order_detail')
        . " AS od ON o.order_id = od.order_id "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('users')
        . " AS e ON o.user_id = e.user_id "
        . $where ;
    $res = $db->getAll($sql);
    $list = array();

    foreach($res as $key => $rows)
    {
        if ($rows['order_status'] == 2)
        {
            $list[$key]['order_status'] = '待发货';
        }
        else if ($rows['order_status'] == 3)
        {
            $list[$key]['order_status'] = '待收货';
        }
        else if ($rows['order_status'] == 4)
        {
            $list[$key]['order_status'] = '待评价';
        }
        else if ($rows['order_status'] == 5)
        {
            $list[$key]['order_status'] = '已完成';
        }

        $list[$key]['order_sn'] = $rows['order_sn'];
        $list[$key]['delivery_sn'] = $rows['shipping_number'];
        $list[$key]['formated_order_time'] = date('y-m-d H:i:s', $rows['order_gmt_create']);
        $list[$key]['buyer'] = $rows['user_name'];
        $list[$key]['goods_name'] = $rows['p_title'];
        $list[$key]['goods_attr'] = $rows['size_title'];
        $list[$key]['goods_number'] = $rows['od_num'];
        $list[$key]['consignee'] = $rows['consignee'];
        $list[$key]['mobile'] = $rows['mobile'];
        $list[$key]['region_address'] = $rows['area'].$rows['address'];
        $list[$key]['postscript'] = $rows['order_remarks'];
        $list[$key]['shipping_time'] =empty($rows['order_gmt_send']) ? '未发货' : date('y-m-d H:i:s', $rows['order_gmt_send']);
        $list[$key]['shipping_name'] = $rows['shipping_name'];

    }

    if($list!=NULL)
    {
        foreach($list as $k=>$row){
            $hang ++;
            $PHPExcel->getActiveSheet()->setCellValue('A' . ($hang), $row['order_sn']." "); //加个空格，防止时间戳被转换
            $PHPExcel->getActiveSheet()->setCellValue('B' . ($hang), $row['delivery_sn']." ");
            $PHPExcel->getActiveSheet()->setCellValue('C' . ($hang), $row['order_status']." ");
            $PHPExcel->getActiveSheet()->setCellValue('D' . ($hang), $row['buyer']." ");
            $PHPExcel->getActiveSheet()->setCellValue('E' . ($hang), $row['formated_order_time']);
            $PHPExcel->getActiveSheet()->setCellValue('F' . ($hang), $row['goods_name']);
            $PHPExcel->getActiveSheet()->setCellValue('G' . ($hang), $row['goods_attr']." ");
            $PHPExcel->getActiveSheet()->setCellValue('H' . ($hang), $row['goods_number']);
            $PHPExcel->getActiveSheet()->setCellValue('I' . ($hang), $row['consignee']." ");
            $PHPExcel->getActiveSheet()->setCellValue('J' . ($hang), $row['mobile']);
            $PHPExcel->getActiveSheet()->setCellValue('K' . ($hang), $row['region_address']);
            $PHPExcel->getActiveSheet()->setCellValue('L' . ($hang), $row['postscript']);
            $PHPExcel->getActiveSheet()->setCellValue('M' . ($hang), $row['shipping_time']);
            $PHPExcel->getActiveSheet()->setCellValue('N' . ($hang), $row['shipping_name']);

        }
        //设置自动换行
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getAlignment()->setWrapText(true);
        //设置字体大小
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getFont()->setSize(12);
    }
    //垂直居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    //水平居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $filename = date("Y-m-d",time()).'导出订单';
    $encoded_filename = urlencode($filename);
    $encoded_filename = str_replace("+", "%20", $encoded_filename);

    $ua = $_SERVER["HTTP_USER_AGENT"];
    if (preg_match("/MSIE/", $ua)) {
        header('Content-Disposition: attachment; filename="' . $encoded_filename . '.xlsx"');
    } else if (preg_match("/Firefox/", $ua)) {
        header('Content-Disposition: attachment; filename*="' . $filename . '.xlsx"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    }

    header('Cache-Control: max-age=0');
    $Writer = PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel2007');

    $Writer->save('php://output');

    exit;
}
elseif($_REQUEST['act'] == 'load_excel'){
    $smarty->display('home/app_load_excel.htm');
}elseif($_REQUEST['act'] == 'mid_load_excel') {
    $smarty->display('home/mid_load_excel.html');
}

elseif($_REQUEST['act'] == 'upload_excel'){
    $PHPExcel = PHPExcel_IOFactory::load($_FILES['file']['tmp_name']);
    $currentSheet = $PHPExcel->getSheet(0);
    $allColumn = "N";   /**取得一共有多少列*/
    $allRow = $currentSheet->getHighestRow();  /**取得一共有多少行*/
    $orderout['orderout']['order_sn'] = '订单编号';
    $orderout['orderout']['delivery_sn'] = '快递单号';
    $orderout['orderout']['order_status'] = '订单状态';
    $orderout['orderout']['buyer'] = '购买人';
    $orderout['orderout']['formated_order_time'] = '下单时间';
    $orderout['orderout']['goods_name'] = '商品名字';
    $orderout['orderout']['goods_attr'] = '商品规格';
    $orderout['orderout']['goods_number'] = '商品数量';
    $orderout['orderout']['consignee'] = '收货人';
    $orderout['orderout']['mobile'] = '手机号码';
    $orderout['orderout']['region_address'] = '收货地址';
    $orderout['orderout']['postscript'] = '客户留言';
    $orderout['orderout']['shipping_time'] = '发货时间';
    $orderout['orderout']['shipping_name'] = '快递名';
    $field_list = array_keys($orderout['orderout']); // 字段列表

    if($allRow<1)
    {
        sys_msg('发货单导入的表格格式不正确，请不要修改模版', 1, array(), false);
    }

    if($allRow==1)
    {
        sys_msg('上传的文件中没有符合的发货单信息', 1, array(), false);
    }

    $i = 1 ;
    $col = array();
    for($currentColumn='A'; ord($currentColumn) <= ord($allColumn) ; $currentColumn++){
        $address = $currentColumn.$i;
        $string = $currentSheet->getCell($address)->getValue();
        if($orderout['orderout'][$field_list[ord($currentColumn)-65]] != trim($string))
        {
            sys_msg('发货单导入的表格格式不正确，请不要修改模版', 1, array(), false);
        }
    }

    $delivery_list[] = $col;
    $delivery_list = array();
    for( $currentRow = 2 ; $currentRow <= $allRow ; $currentRow++){
        $col = array();
        for($currentColumn='A'; ord($currentColumn) <= ord($allColumn) ; $currentColumn++){
            $address = $currentColumn.$currentRow;
            $string = $currentSheet->getCell($address)->getValue();
            $col[$field_list[ord($currentColumn)-65]] = trim($string);
        }

        $delivery_list[] = $col;
    }

    //移除非待发货发货单，减少服务器处理时间
    //移除发货单号 订单号不匹配的数据
    foreach($delivery_list as $k=>$item)
    {
        if($item['order_status']!= '待发货')
        {
            unset($delivery_list[$k]);
            continue;
        }

        $delivery_list[$k]['shipping_id']= express_company_shipping($item['shipping_name']);
    }

    $express_company_list_name = getshipping_name();
    $smarty->assign('express_company_list_name', $express_company_list_name);

    $smarty->assign('goods_class', '实体商品');
    $smarty->assign('delivery_list', array_values($delivery_list));


    /* 参数赋值 */
    $smarty->assign('ur_here', '发货单导入信息确认');

    /* 显示模板 */
    assign_query_info();
    $smarty->display('home/app_upload_excel.htm');
}
elseif ($_REQUEST['act'] == 'update_delivery')
{
    if (isset($_POST['checked']))
    {
        //二次验证

        foreach ($_POST['checked'] AS $key => $value)
        {
            if(empty($_POST['delivery_sn'][$value]))
            {
                sys_msg('快递单号不能为空', 1, array(), false);
            }
            if($_POST['express_company'][$value]==-1)
            {
                sys_msg('请选择快递名', 1, array(), false);
            }
        }


        $express_company_list_name = getshipping_name();

        /* 循环查询可发货的发货单 */
        foreach ($_POST['checked'] AS $key => $value)
        {
            $order_sn   = trim($_POST['order_sn'][$value]);        // 订单sn
            $delivery_sn   = trim($_POST['delivery_sn'][$value]);        // 发货单sn
            $shipping_id = intval($_POST['express_company'][$value]);

            //查出订单信息

            $sql = "SELECT order_status,order_id FROM " . $GLOBALS['ecs']->table('orders') . " as a WHERE  a.order_sn = '$order_sn' ";
            $order = $GLOBALS['db']->getRow($sql);
            $order_id = $order['order_id'];
            $order_status = $order['order_status'];
            if (empty($order_id)) {
                continue;
            }
            if ($order_status >2) {
                continue;
            }
            $shipping_time = time(); // 发货时间
            $shipping_name = $express_company_list_name[$shipping_id];
            $sql = "UPDATE " . $GLOBALS['ecs']->table('orders') .
                " SET order_status = 3, order_gmt_send = '$shipping_time', shipping_name = '$shipping_name', shipping_id = '$shipping_id', shipping_number = '$delivery_sn' " .
                " WHERE order_id = " .$order_id;
            $GLOBALS['db']->query($sql);
        }


    }

    /* 清除缓存 */
    clear_cache_files();

    /* 显示提示信息，返回商品列表 */
    $link[] = array('href' => 'app_order.php?act=app_list', 'text' => '发货成功');
    sys_msg('发货成功', 0, $link);
} else if($_REQUEST['act'] == 'trade_detail') {

    //var_dump(123);die();
    header('Content-type:text/html;charset=utf-8');
    $PHPExcel = new PHPExcel();

    //设置excel属性基本信息
    $PHPExcel->getProperties()->setCreator("Neo")
        ->setLastModifiedBy("Neo")
        ->setTitle("")
        ->setSubject("火粉社区列表")
        ->setDescription("")
        ->setKeywords("火粉社区列表")
        ->setCategory("");
    //存储Excel数据源到其他工作薄
    $PHPExcel->createSheet();
    $subObject = $PHPExcel->getSheet(1);
    $subObject->setTitle('data');

    $subObject->getColumnDimension('A')->setWidth(30);

    //保护数据源
    $subObject->getProtection()->setSheet(true);
    $subObject->protectCells('A1:A1000',time());

    $PHPExcel->setActiveSheetIndex(0);
    $PHPExcel->getActiveSheet()->setTitle("火粉社区列表");

    $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(22);//编号
    $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);//出售人
    $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(22);//出售人手机号
    $PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);//购买人
    $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);//购买人手机号
    $PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(22);//出售状态
    $PHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(40);//出售个数
    $PHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(50);//发布时间
    $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);//确认时间
    $PHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);//银行卡号
    $PHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(20);//开户行
    $PHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(35);//支付宝账号
    $PHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(30);//支付宝收款人
    $PHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(100);//凭证
//edit end



//edit by jixf2017年8月29日12:17:19
    $PHPExcel->getActiveSheet()->setCellValue('A1', '编号');
    $PHPExcel->getActiveSheet()->setCellValue('B1', '出售人');//加个空格，防止时间戳被转换
    $PHPExcel->getActiveSheet()->setCellValue('C1', '出售人手机号');
    $PHPExcel->getActiveSheet()->setCellValue('D1', '购买人');
    $PHPExcel->getActiveSheet()->setCellValue('E1', '购买人手机号');
    $PHPExcel->getActiveSheet()->setCellValue('F1', '出售状态');
    $PHPExcel->getActiveSheet()->setCellValue('G1', '出售个数');
    $PHPExcel->getActiveSheet()->setCellValue('H1', '发布时间');
    $PHPExcel->getActiveSheet()->setCellValue('I1', '确认时间');
    $PHPExcel->getActiveSheet()->setCellValue('J1', '银行卡号');
    $PHPExcel->getActiveSheet()->setCellValue('K1', '开户行');
    $PHPExcel->getActiveSheet()->setCellValue('L1', '支付宝账号');
    $PHPExcel->getActiveSheet()->setCellValue('M1', '支付宝收款人');
    $PHPExcel->getActiveSheet()->setCellValue('N1', '凭证');
    // end edit
    $hang = 1;


    $time = empty($_REQUEST['time']) ? 0 : strtotime($_REQUEST['time']);
    $status = empty($_REQUEST['status']) ? 1 : intval($_REQUEST['status']);
    $end_time = empty($_REQUEST['endtime']) ? 9999999999 : strtotime($_REQUEST['endtime']);

    $where = "WHERE t.td_status = $status";

    if ($time) {
        $where .= " AND t.create_at BETWEEN $time AND $end_time ";
    }

    $sql = "SELECT t.*,u.user_name,u.mobile_phone,xt.* FROM xm_trade_detail t LEFT JOIN xm_users u ON t.seller_id=u.user_id LEFT JOIN xm_trade xt ON xt.trade_id=t.t_id ".$where;
    $row = $db->getAll($sql);

    $list = array();



    foreach($row as $key => $rows)
    {
        $rows['user_name'] = addslashes($rows['user_name']);
        if ($rows['td_status'] == 1)
        {
            $list[$key]['td_status'] = '待确认';
        }
        else if ($rows['td_status'] == 2)
        {
            $list[$key]['td_status'] = '完成';
        }
        else if ($rows['td_status'] == 3)
        {
            $list[$key]['td_status'] = '有误';
        }
        else if ($rows['td_status'] == 4)
        {
            $list[$key]['td_status'] = '确认有误';
        }

        if ($rows['buyer_id']) {
            $user_info = $db->getRow("SELECT user_name,mobile_phone FROM xm_users WHERE user_id={$rows['buyer_id']}");
            $list[$key]['buy_name'] = $user_info['user_name'];
            $list[$key]['buy_mobile_phone'] = $user_info['mobile_phone'];
        } else {
            $list[$key]['buy_name'] = 0;
            $list[$key]['buy_mobile_phone'] = 0;
        }
        $list[$key]['user_name'] = $rows['user_name'];
        $list[$key]['mobile_phone'] = $rows['mobile_phone'];


        $list[$key]['td_id'] = $rows['td_id'];
        $list[$key]['td_num'] = $rows['td_num'];
        $list[$key]['trade_gmt_create'] = date("Y-m-d", $rows['create_at']);
        $list[$key]['trade_gmt_sure'] = empty($rows['complete_at']) ? '' : date("Y-m-d", $rows['complete_at']);
        $list[$key]['bank_account'] = empty($rows['bank_account']) ? '' : $rows['bank_account'];
        $list[$key]['bank_name'] = empty($rows['bank_name']) ? '' : $rows['bank_name'];
        $list[$key]['ali_account'] = empty($rows['ali_account']) ? '' : $rows['ali_account'];
        $list[$key]['ali_owner'] = empty($rows['ali_owner']) ? '' : $rows['ali_owner'];
        $list[$key]['trade_voucher'] = empty($rows['td_voucher']) ? '' : \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath('data/' . $rows['td_voucher']);
        $list[$key]['trade_status'] = $rows['trade_status'];

    }


    if($list!=NULL)
    {
        foreach($list as $k=>$row){
            $hang ++;
            $PHPExcel->getActiveSheet()->setCellValue('A' . ($hang), $row['td_id']." "); //加个空格，防止时间戳被转换
            $PHPExcel->getActiveSheet()->setCellValue('B' . ($hang), $row['user_name']." ");
            $PHPExcel->getActiveSheet()->setCellValue('C' . ($hang), $row['mobile_phone']." ");
            $PHPExcel->getActiveSheet()->setCellValue('D' . ($hang), $row['buy_name']." ");
            $PHPExcel->getActiveSheet()->setCellValue('E' . ($hang), $row['buy_mobile_phone']." ");
            $PHPExcel->getActiveSheet()->setCellValue('F' . ($hang), $row['td_status']);
            $PHPExcel->getActiveSheet()->setCellValue('G' . ($hang), $row['td_num']);
            $PHPExcel->getActiveSheet()->setCellValue('H' . ($hang), $row['trade_gmt_create']." ");
            $PHPExcel->getActiveSheet()->setCellValue('I' . ($hang), $row['trade_gmt_sure']);
            $PHPExcel->getActiveSheet()->setCellValue('J' . ($hang), $row['bank_account']." ");
            $PHPExcel->getActiveSheet()->setCellValue('K' . ($hang), $row['bank_name']);
            $PHPExcel->getActiveSheet()->setCellValue('L' . ($hang), $row['ali_account']);
            $PHPExcel->getActiveSheet()->setCellValue('M' . ($hang), $row['ali_owner']);
            $PHPExcel->getActiveSheet()->setCellValue('N' . ($hang), $row['trade_voucher']);

        }
        //设置自动换行
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getAlignment()->setWrapText(true);
        //设置字体大小
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getFont()->setSize(12);
    }
    //垂直居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    //水平居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $filename = date("Y-m-d",time()).'导出火粉社区列表';
    $encoded_filename = urlencode($filename);
    $encoded_filename = str_replace("+", "%20", $encoded_filename);

    $ua = $_SERVER["HTTP_USER_AGENT"];
    if (preg_match("/MSIE/", $ua)) {
        header('Content-Disposition: attachment; filename="' . $encoded_filename . '.xlsx"');
    } else if (preg_match("/Firefox/", $ua)) {
        header('Content-Disposition: attachment; filename*="' . $filename . '.xlsx"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    }

    header('Cache-Control: max-age=0');
    $Writer = PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel2007');

    $Writer->save('php://output');

    exit;

} else if($_REQUEST['act'] == 'mid_award') {

    header('Content-type:text/html;charset=utf-8');
    $PHPExcel = new PHPExcel();

    //设置excel属性基本信息
    $PHPExcel->getProperties()->setCreator("Neo")
        ->setLastModifiedBy("Neo")
        ->setTitle("")
        ->setSubject("中秋奖品获取记录")
        ->setDescription("")
        ->setKeywords("中秋奖品获取记录")
        ->setCategory("");
    //存储Excel数据源到其他工作薄
    $PHPExcel->createSheet();
    $subObject = $PHPExcel->getSheet(1);
    $subObject->setTitle('data');

    $subObject->getColumnDimension('A')->setWidth(30);

    //保护数据源
    $subObject->getProtection()->setSheet(true);
    $subObject->protectCells('A1:A1000',time());

    $PHPExcel->setActiveSheetIndex(0);
    $PHPExcel->getActiveSheet()->setTitle("中秋奖品获取记录");

    $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(22);//编号
    $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);//用户名
    $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(22);//用户电话
    $PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);//商品名
    $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);//发货状态
    $PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(22);//收货人姓名
    $PHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(40);//收货人手机号
    $PHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(50);//收获地址
    $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);//运单号
//edit end



//edit by jixf2017年8月29日12:17:19
    $PHPExcel->getActiveSheet()->setCellValue('A1', '编号');
    $PHPExcel->getActiveSheet()->setCellValue('B1', '用户名');//加个空格，防止时间戳被转换
    $PHPExcel->getActiveSheet()->setCellValue('C1', '用户电话');
    $PHPExcel->getActiveSheet()->setCellValue('D1', '商品名');
    $PHPExcel->getActiveSheet()->setCellValue('E1', '发货状态');
    $PHPExcel->getActiveSheet()->setCellValue('F1', '收货人姓名');
    $PHPExcel->getActiveSheet()->setCellValue('G1', '收货人手机号');
    $PHPExcel->getActiveSheet()->setCellValue('H1', '收获地址');
    $PHPExcel->getActiveSheet()->setCellValue('I1', '运单号');
    // end edit
    $hang = 1;


    /* 过滤信息 */
    $filter['phone'] = empty($_REQUEST['phone']) ? '' : trim($_REQUEST['phone']);

    /* 过滤信息 */
    $filter['is_deliver'] = $_REQUEST['is_deliver'];

    $where = ' WHERE o.is_prize != 1 ';

    if ($filter['phone']) {
        $where .= " AND d_u.phone='{$filter['phone']}'";
    } else if ($filter['is_deliver']) {
        $where .= " AND o.is_deliver={$filter['is_deliver']}";
    }

    $sql = "SELECT u.nickname,u.mobile_phone,o.id,o.is_deliver,g.goods_name,g.goods_img,o.order_no,d_u.address,d_u.phone,d_u.name " .
        " FROM " . $GLOBALS['ecs']->table('luck_draw_log') . " AS o " .
        " LEFT JOIN xm_luck_goods_draw AS g  ON o.goods_id=g.id" .
        " LEFT JOIN xm_users AS u ON o.user_id=u.user_id" .
        " LEFT JOIN xm_luck_draw_user AS d_u ON u.user_id = d_u.user_id" .
        $where;

    $row = $db->getAll($sql);

    $list = array();

    foreach($row as $key => $rows)
    {
        if ($rows['is_deliver'] == 0)
        {
            $list[$key]['is_deliver'] = '未发';
        }
        else if ($rows['is_deliver'] == 1)
        {
            $list[$key]['is_deliver'] = '已发';
        }

        $list[$key]['id'] = $rows['id'];
        $list[$key]['nickname'] = $rows['nickname'];
        $list[$key]['mobile_phone'] = $rows['mobile_phone'];
        $list[$key]['goods_name'] = $rows['goods_name'];
        $list[$key]['name'] = $rows['name'];
        $list[$key]['phone'] = $rows['phone'];
        $list[$key]['address'] = $rows['address'];
        $list[$key]['order_no'] = $rows['order_no'];

    }


    if($list!=NULL)
    {
        foreach($list as $k=>$row){
            $hang ++;
            $PHPExcel->getActiveSheet()->setCellValue('A' . ($hang), $row['id']." "); //加个空格，防止时间戳被转换
            $PHPExcel->getActiveSheet()->setCellValue('B' . ($hang), $row['nickname']." ");
            $PHPExcel->getActiveSheet()->setCellValue('C' . ($hang), $row['mobile_phone']." ");
            $PHPExcel->getActiveSheet()->setCellValue('D' . ($hang), $row['goods_name']." ");
            $PHPExcel->getActiveSheet()->setCellValue('E' . ($hang), $row['is_deliver']." ");
            $PHPExcel->getActiveSheet()->setCellValue('F' . ($hang), $row['name']);
            $PHPExcel->getActiveSheet()->setCellValue('G' . ($hang), $row['phone']);
            $PHPExcel->getActiveSheet()->setCellValue('H' . ($hang), $row['address']." ");
            $PHPExcel->getActiveSheet()->setCellValue('I' . ($hang), $row['order_no']);
        }
        //设置自动换行
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getAlignment()->setWrapText(true);
        //设置字体大小
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getFont()->setSize(12);
    }
    //垂直居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    //水平居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $filename = date("Y-m-d",time()).'中秋奖品获取记录';
    $encoded_filename = urlencode($filename);
    $encoded_filename = str_replace("+", "%20", $encoded_filename);

    $ua = $_SERVER["HTTP_USER_AGENT"];
    if (preg_match("/MSIE/", $ua)) {
        header('Content-Disposition: attachment; filename="' . $encoded_filename . '.xlsx"');
    } else if (preg_match("/Firefox/", $ua)) {
        header('Content-Disposition: attachment; filename*="' . $filename . '.xlsx"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    }

    header('Cache-Control: max-age=0');
    $Writer = PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel2007');

    $Writer->save('php://output');

    exit;

} else if($_REQUEST['act'] == 'team_order') {

    header('Content-type:text/html;charset=utf-8');
    $PHPExcel = new PHPExcel();

    //设置excel属性基本信息
    $PHPExcel->getProperties()->setCreator("Neo")
        ->setLastModifiedBy("Neo")
        ->setTitle("")
        ->setSubject("团队业绩导出")
        ->setDescription("")
        ->setKeywords("团队业绩导出")
        ->setCategory("");
    //存储Excel数据源到其他工作薄
    $PHPExcel->createSheet();
    $subObject = $PHPExcel->getSheet(1);
    $subObject->setTitle('data');

    $subObject->getColumnDimension('A')->setWidth(30);

    //保护数据源
    $subObject->getProtection()->setSheet(true);
    $subObject->protectCells('A1:A1000',time());

    $PHPExcel->setActiveSheetIndex(0);
    $PHPExcel->getActiveSheet()->setTitle("团队业绩导出");

    $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(22);//手机号
    $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);//姓名
    $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(22);//业绩
//edit end



//edit by jixf2017年8月29日12:17:19
    $PHPExcel->getActiveSheet()->setCellValue('A1', '手机号');
    $PHPExcel->getActiveSheet()->setCellValue('B1', '姓名');//加个空格，防止时间戳被转换
    $PHPExcel->getActiveSheet()->setCellValue('C1', '业绩');
    // end edit
    $hang = 1;


//    /* 过滤信息 */
//    $filter['phone'] = empty($_REQUEST['phone']) ? '' : trim($_REQUEST['phone']);
//
//    /* 过滤信息 */
//    $filter['is_deliver'] = $_REQUEST['is_deliver'];
//
//    $where = ' WHERE o.is_prize != 1 ';
//
//    if ($filter['phone']) {
//        $where .= " AND d_u.phone='{$filter['phone']}'";
//    } else if ($filter['is_deliver']) {
//        $where .= " AND o.is_deliver={$filter['is_deliver']}";
//    }

    //查询用户业绩
    $sql = "SELECT user.mobile_phone,extra.real_name,per.money".
        " FROM ". $GLOBALS['ecs']->table('users') ." AS user ".
        " LEFT JOIN ".$GLOBALS['ecs']->table('top_users') ." AS per ON user.user_id = per.user_id ".
        " LEFT JOIN ".$GLOBALS['ecs']->table('mq_users_extra') ." AS extra ON per.user_id = extra.user_id ".
//        $where.
        " ORDER BY per.money DESC".
        " LIMIT 200";
    $data = $GLOBALS['db']->getAll($sql);

    $list = array();

    foreach($data as $key => $rows)
    {
//        if ($rows['is_deliver'] == 0)
//        {
//            $list[$key]['is_deliver'] = '未发';
//        }
//        else if ($rows['is_deliver'] == 1)
//        {
//            $list[$key]['is_deliver'] = '已发';
//        }

        $list[$key]['mobile_phone'] = $rows['mobile_phone'];
        $list[$key]['real_name'] = $rows['real_name'];
        $list[$key]['money'] = $rows['money'];

    }


    if($list!=NULL)
    {
        foreach($list as $k=>$row){
            $hang ++;
            $PHPExcel->getActiveSheet()->setCellValue('A' . ($hang), $row['mobile_phone']." "); //加个空格，防止时间戳被转换
            $PHPExcel->getActiveSheet()->setCellValue('B' . ($hang), $row['real_name']." ");
            $PHPExcel->getActiveSheet()->setCellValue('C' . ($hang), $row['money']." ");
        }
        //设置自动换行
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getAlignment()->setWrapText(true);
        //设置字体大小
        $PHPExcel->getActiveSheet()->getStyle('A2:AD'.$hang)->getFont()->setSize(12);
    }
    //垂直居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    //水平居中
    $PHPExcel->getActiveSheet()->getStyle('A1:AD'.$hang)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $filename = date("Y-m-d",time()).'团队业绩导出';
    $encoded_filename = urlencode($filename);
    $encoded_filename = str_replace("+", "%20", $encoded_filename);

    $ua = $_SERVER["HTTP_USER_AGENT"];
    if (preg_match("/MSIE/", $ua)) {
        header('Content-Disposition: attachment; filename="' . $encoded_filename . '.xlsx"');
    } else if (preg_match("/Firefox/", $ua)) {
        header('Content-Disposition: attachment; filename*="' . $filename . '.xlsx"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    }

    header('Cache-Control: max-age=0');
    $Writer = PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel2007');

    $Writer->save('php://output');

    exit;
}


/**
 * 判断订单的发货单是否全部发货
 * @param   int     $order_id  订单 id
 * @return  int     1，全部发货；0，未全部发货；-1，部分发货；-2，完全没发货；
 */

function get_all_delivery_finish($order_id)
{
    $return_res = 0;

    if (empty($order_id))
    {
        return $return_res;
    }

    /* 未全部分单 */
    if (!get_order_finish($order_id))
    {
        return $return_res;
    }
    /* 已全部分单 */
    else
    {
        // 是否全部发货
        $sql = "SELECT COUNT(delivery_id)
                FROM " . $GLOBALS['ecs']->table('delivery_order') . "
                WHERE order_id = '$order_id'
                AND status = 2 ";
        $sum = $GLOBALS['db']->getOne($sql);
        // 全部发货
        if (empty($sum))
        {
            $return_res = 1;
        }
        // 未全部发货
        else
        {
            /* 订单全部发货中时：当前发货单总数 */
            $sql = "SELECT COUNT(delivery_id)
            FROM " . $GLOBALS['ecs']->table('delivery_order') . "
            WHERE order_id = '$order_id'
            AND status <> 1 ";
            $_sum = $GLOBALS['db']->getOne($sql);
            if ($_sum == $sum)
            {
                $return_res = -2; // 完全没发货
            }
            else
            {
                $return_res = -1; // 部分发货
            }
        }
    }

    return $return_res;
}

//快递公司查询

function get_express_company()
{
    $sql   =  "SELECT id, name ".
        "FROM " . $GLOBALS['ecs']->table('express_code');

    return  $GLOBALS['db']->getAll($sql);

}

function express_company()
{
    $sql   =  "SELECT  name ".
        "FROM " . $GLOBALS['ecs']->table('express_code');

    return  $GLOBALS['db']->getAll($sql);

}
function getshipping_name()
{
    $express_company_list = get_express_company();

    $express_company_name = array();
    if (count($express_company_list) > 0) {
        foreach ($express_company_list as $company) {
            $express_company_name[$company['id']] = $company['name'];
        }
    }
    return $express_company_name;
}
function express_company_shipping($express_company_name='')
{
    $express_company_name = trim($express_company_name);
    if( empty($express_company_name))
    {
        return -1;
    }
    $sql = "SELECT id FROM ".$GLOBALS['ecs']->table('express_code')." WHERE name LIKE  '%" . $express_company_name . "%' LIMIT 1";
    return $GLOBALS['db']->getOne($sql);
}


