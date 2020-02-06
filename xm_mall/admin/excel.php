<?php
define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
//require_once ROOT_PATH . 'includes/phpexcel/Classes/PHPExcel.php';
//require_once ROOT_PATH . 'includes/phpexcel/Classes/PHPExcel/Writer/Excel2007.php';
//require_once ROOT_PATH . 'includes/phpexcel/Classes/PHPExcel/IOFactory.php';

if ($_REQUEST['act'] == 'order_excel')
{
    //var_dump(1111111);die();
    // 载入供货商
    $sql_suppliers = "SELECT suppliers_id, suppliers_name FROM " . $GLOBALS['ecs']->table("suppliers");
    $res_suppliers =$db->getAll($sql_suppliers);
    $smarty->assign('res_suppliers',  $res_suppliers);
    $smarty->display('excel.htm');
}
elseif($_REQUEST['act'] == 'excel')
{
    //var_dump(123);die();
    header('Content-type:text/html;charset=utf-8');
    $PHPExcel = new PHPExcel();
    $express_company = get_express_company();
    //var_dump($express_company);die();
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

    //表格宽度
//    $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(22);//订单号
//    $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);//发货单号
//    $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);//订单状态
//    //$PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);//订单来源(1：易业商城 2：精品商城)
//    $PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);//购货人
//    $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(22);//下单时间
//    $PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(40);//商品名称
//    $PHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);//货号
//    $PHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);//数量
//    // $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(18);//单价
//    // $PHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(18);//总金额
//    $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);//收货人
//    $PHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(20);//手机/电话
//    $PHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(35);//地址
//    $PHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(30);//客户留言
//    $PHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(22);//发货时间
//    $PHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(18);//快递公司
//    $PHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(20);//快递单号
//EDIT by jixf 2017年8月29日12:19:29
    $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(22);//订单号
    $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);//发货单号
    $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);//订单状态
    $PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);//订单来源(1：易业商城 2：精品商城)
    $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);//购货人
    $PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(22);//下单时间
    $PHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(40);//商品名称
    $PHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(50);//商品规格属性
    $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);//货号
    $PHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);//数量
    // $PHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(18);//单价
    // $PHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(18);//总金额
    $PHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(15);//收货人
    $PHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(20);//手机/电话
    $PHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(35);//地址
    $PHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(30);//客户留言
    $PHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(22);//发货时间
    $PHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(18);//快递公司
    $PHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(20);//快递单号
    $PHPExcel->getActiveSheet()->getColumnDimension('R')->setWidth(20);//供货商名称
//edit end



//    $PHPExcel->getActiveSheet()->setCellValue('A1', $_LANG['orderout']['order_sn']);//加个空格，防止时间戳被转换
//    $PHPExcel->getActiveSheet()->setCellValue('B1', $_LANG['orderout']['delivery_sn']);
//    $PHPExcel->getActiveSheet()->setCellValue('C1', $_LANG['orderout']['order_status']);
//   // $PHPExcel->getActiveSheet()->setCellValue('C1', $_LANG['orderout']['barter_type']);
//    $PHPExcel->getActiveSheet()->setCellValue('D1', $_LANG['orderout']['buyer']);
//    $PHPExcel->getActiveSheet()->setCellValue('E1', $_LANG['orderout']['order_time']);
//    $PHPExcel->getActiveSheet()->setCellValue('F1', $_LANG['orderout']['goods_name']);
//    $PHPExcel->getActiveSheet()->setCellValue('G1', $_LANG['orderout']['goods_sn']);
//    $PHPExcel->getActiveSheet()->setCellValue('H1', $_LANG['orderout']['goods_number']);
//    // $PHPExcel->getActiveSheet()->setCellValue('I1', $_LANG['orderout']['goods_price']);
//    // $PHPExcel->getActiveSheet()->setCellValue('J1', $_LANG['orderout']['total_fee']);
//    $PHPExcel->getActiveSheet()->setCellValue('I1', $_LANG['orderout']['consignee']);
//    $PHPExcel->getActiveSheet()->setCellValue('J1', $_LANG['orderout']['mobile']);
//    $PHPExcel->getActiveSheet()->setCellValue('K1', $_LANG['orderout']['region_address']);
//    $PHPExcel->getActiveSheet()->setCellValue('L1', $_LANG['orderout']['postscript']);
//    $PHPExcel->getActiveSheet()->setCellValue('M1', $_LANG['orderout']['shipping_time']);
//    $PHPExcel->getActiveSheet()->setCellValue('N1', $_LANG['orderout']['shipping_name']);
//    $PHPExcel->getActiveSheet()->setCellValue('O1', $_LANG['orderout']['invoice_no']);
//    $PHPExcel->getActiveSheet()->setCellValue('P1', $_LANG['orderout']['suppliers_name']);
//edit by jixf2017年8月29日12:17:19
    $PHPExcel->getActiveSheet()->setCellValue('A1', $_LANG['orderout']['order_sn']);//加个空格，防止时间戳被转换
    $PHPExcel->getActiveSheet()->setCellValue('B1', $_LANG['orderout']['delivery_sn']);
    $PHPExcel->getActiveSheet()->setCellValue('C1', $_LANG['orderout']['order_status']);
    $PHPExcel->getActiveSheet()->setCellValue('D1', $_LANG['orderout']['barter_type']);
    $PHPExcel->getActiveSheet()->setCellValue('E1', $_LANG['orderout']['buyer']);
    $PHPExcel->getActiveSheet()->setCellValue('F1', $_LANG['orderout']['order_time']);
    $PHPExcel->getActiveSheet()->setCellValue('G1', $_LANG['orderout']['goods_name']);
    $PHPExcel->getActiveSheet()->setCellValue('H1', $_LANG['orderout']['goods_attr']);
    $PHPExcel->getActiveSheet()->setCellValue('I1', $_LANG['orderout']['goods_sn']);
    $PHPExcel->getActiveSheet()->setCellValue('J1', $_LANG['orderout']['goods_number']);
    // $PHPExcel->getActiveSheet()->setCellValue('I1', $_LANG['orderout']['goods_price']);
    // $PHPExcel->getActiveSheet()->setCellValue('J1', $_LANG['orderout']['total_fee']);
    $PHPExcel->getActiveSheet()->setCellValue('K1', $_LANG['orderout']['consignee']);
    $PHPExcel->getActiveSheet()->setCellValue('L1', $_LANG['orderout']['mobile']);
    $PHPExcel->getActiveSheet()->setCellValue('M1', $_LANG['orderout']['region_address']);
    $PHPExcel->getActiveSheet()->setCellValue('N1', $_LANG['orderout']['postscript']);
    $PHPExcel->getActiveSheet()->setCellValue('O1', $_LANG['orderout']['shipping_time']);
    $PHPExcel->getActiveSheet()->setCellValue('P1', $_LANG['orderout']['shipping_name']);
    $PHPExcel->getActiveSheet()->setCellValue('Q1', $_LANG['orderout']['invoice_no']);
    $PHPExcel->getActiveSheet()->setCellValue('R1', $_LANG['orderout']['suppliers_name']);
 // end edit
    $hang = 1;


    /* 查询 */
        // 订单状态
    $order_status = intval($_REQUEST['order_status']);
    // 下单开始时间
    $start_time = empty($_REQUEST['start_time']) ? '' : (strpos($_REQUEST['start_time'], '-') > 0 ?  local_strtotime($_REQUEST['start_time']) : $_REQUEST['start_time']);
    // 下单结束时间
    $end_time = empty($_REQUEST['end_time']) ? '' : (strpos($_REQUEST['end_time'], '-') > 0 ?  local_strtotime($_REQUEST['end_time']) : $_REQUEST['end_time']);
    // 供货商
    $suppliers_id = $_REQUEST['suppliers_id'];
    $where = 'WHERE 1 ';


    if($order_status > 0)
    {   
        $where .= " AND z.order_status = '$order_status' ";
                       
    } 
    if($order_status == '1'){

        $where .= " AND z.order_status = '$order_status' "
                  ."AND a.status <> 0 "
                  ."AND z.shipping_status <> 1 ";
    }


    if($start_time != '' && $end_time != '')
    {
        $where .= " AND a.add_time >= '$start_time' AND a.add_time <= '$end_time' ";
    }else if($start_time!= '' && empty($end_time)){
        $where .= " AND a.add_time >= '$start_time' ";

    }else if(empty($start_time)  &&  $end_time!=''){
        $where .= " AND a.add_time <= '$end_time' ";
    }

    if ($suppliers_id < 0)
    {
        // 所有供货商
        $where .= 'AND a.suppliers_id >= 0 ';
    }
    else
    {
        // 选择供货商
        $where .= 'AND a.suppliers_id = ' . $suppliers_id;
    }
 
//var_dump( $suppliers_id);die();
    $sql = "UPDATE " . $GLOBALS['ecs']->table('delivery_order')  .
           " AS a LEFT JOIN " . $GLOBALS['ecs']->table('delivery_goods') .
           " AS b ON a.delivery_id = b.delivery_id ".
           " LEFT JOIN " . $GLOBALS['ecs']->table('goods') .
           " AS d ON b.goods_id = d.goods_id ".
           " SET a.suppliers_id = d.suppliers_id ";
    //var_dump($sql);die();     
    $GLOBALS['db']->query($sql);

    $sql = "UPDATE " . $GLOBALS['ecs']->table('delivery_goods')  .
           " AS a LEFT JOIN " . $GLOBALS['ecs']->table('goods') .
           " AS d ON a.goods_id = d.goods_id ".
           " LEFT JOIN " . $GLOBALS['ecs']->table('brand') .
           " AS b ON b.brand_id = d.brand_id ".
           " SET a.brand_name = b.brand_name" ;
    //var_dump($sql);die();     
    $GLOBALS['db']->query($sql);

    $sql = "SELECT "
        . "a.*,"
        ."b.*,"
        ."d.*,"
        ."e.user_name," //购买人
        ."z.order_status,"
        ."c.suppliers_name "   //供货商
        . "FROM " . $GLOBALS['ecs']->table('delivery_order')
        . " AS a LEFT JOIN " . $GLOBALS['ecs']->table('delivery_goods')
        . " AS b ON a.delivery_id = b.delivery_id "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('suppliers')
        . " AS c ON c.suppliers_id = a.suppliers_id "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('mq_goods_extra')
        . " AS d ON b.goods_id = d.goods_id "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('users')
        . " AS e ON a.user_id = e.user_id "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('order_info')
        . " AS z ON a.order_id = z.order_id "
        . $where ;
     //var_dump($sql);die();
    $res = $db->getAll($sql);
    $list = array();
    //var_dump($res);die();
    foreach($res as $key => $rows)
    {
        // 订单状态
        if ($rows['order_status'] == 0)
        {
            $list[$key]['order_status'] = '未确认';
        }
        else if ($rows['order_status'] == 1)
        {
           //var_dump($rows['order_status']);die();
            $list[$key]['order_status'] = '已确认';
        }
        else if ($rows['order_status'] == 3)
        {
            $list[$key]['order_status'] = '无效';
        }
        else if ($rows['order_status'] == 4)
        {
            $list[$key]['order_status'] = '退货';
        }
        else if ($rows['order_status'] == 5)
        {
            $list[$key]['order_status'] = '已发货';
        }
        else if ($rows['order_status'] == 101)
        {
            $list[$key]['order_status'] = '待发货';
        }
        else if ($rows['order_status'] == 102)
        {
            $list[$key]['order_status'] = '已完成';
        }
        else
        {
            $list[$key]['order_status'] = '';
        }
        if($rows['suppliers_id'] == '0')
        {
            $list[$key]['suppliers_name'] = '平台自营';
        }else{
            $list[$key]['suppliers_name'] = $rows['suppliers_name'];
        }


         if($rows['belongto'] == 1){

             $list[$key]['barter_type'] = '易业商城';
             $list[$key]['goods_price'] = '购物券'.$rows['shopping_money']; //易业商城商品单价
             $list[$key]['total_fee'] = '购物券'.$rows['shopping_money_all']; //商品总价

         }else if($rows['belongto'] == 2){
             $list[$key]['goods_price'] = '新美积分：'.$rows['cash_money'].'+'.'消费积分：'.$rows['consume_money'];//精品商城商品单价
             $list[$key]['barter_type'] = '精品商城';
             $list[$key]['total_fee'] = '新美积分：'.$rows['cash_money_all'].'+'.'消费积分：'.$rows['consume_money_all']; //商品总价
         }else{

             $list[$key]['barter_type'] = '小易物';
         }

        /* 取得区域名 */
        $sql = "SELECT concat('', '', IFNULL(p.region_name, ''), " .
            "'', IFNULL(t.region_name, ''), '', IFNULL(d.region_name, '')) AS region " .
            "FROM " . $ecs->table('order_info') . " AS o " .
            "LEFT JOIN " . $ecs->table('region') . " AS c ON o.country = c.region_id " .
            "LEFT JOIN " . $ecs->table('region') . " AS p ON o.province = p.region_id " .
            "LEFT JOIN " . $ecs->table('region') . " AS t ON o.city = t.region_id " .
            "LEFT JOIN " . $ecs->table('region') . " AS d ON o.district = d.region_id " .
            "WHERE o.order_sn = {$rows['order_sn']}";
        $address = $db->getOne($sql) . ' ' . $rows['address'];

        $list[$key]['order_sn'] = $rows['order_sn'];
        $list[$key]['delivery_sn'] = $rows['delivery_sn'];
        $list[$key]['formated_order_time'] = local_date('y-m-d H:i', $rows['add_time']);
        $list[$key]['froms'] = $rows['froms'];
        $list[$key]['consignee'] = $rows['consignee'];
        $list[$key]['address'] = $address;
        $list[$key]['mobile'] = empty($rows['mobile']) ? $rows['tel'] : $rows['mobile'];
        $list[$key]['goods_name'] = $rows['goods_name'];
        $list[$key]['goods_sn'] = $rows['goods_sn'];
        $list[$key]['goods_number'] = $rows['send_number'];
        $list[$key]['goods_attr'] = $rows['goods_attr'];
        $list[$key]['money'] = $rows['money'];
        $list[$key]['buyer'] = $rows['user_name'];
        $list[$key]['brand_name'] = $rows['brand_name'];

        $list[$key]['postscript'] = $rows['postscript'];
        $list[$key]['shipping_id'] = $rows['shipping_id'];
    }
    //var_dump($list);die();

    if($list!=NULL)
    {
        foreach($list as $k=>$row){
            $hang ++;
            $PHPExcel->getActiveSheet()->setCellValue('A' . ($hang), $row['order_sn']." "); //加个空格，防止时间戳被转换
            $PHPExcel->getActiveSheet()->setCellValue('B' . ($hang), $row['delivery_sn']." ");
            $PHPExcel->getActiveSheet()->setCellValue('C' . ($hang), $row['order_status']." ");
            $PHPExcel->getActiveSheet()->setCellValue('D' . ($hang), $row['barter_type']);
            $PHPExcel->getActiveSheet()->setCellValue('E' . ($hang), $row['buyer']." ");
            $PHPExcel->getActiveSheet()->setCellValue('F' . ($hang), $row['formated_order_time']);
            $PHPExcel->getActiveSheet()->setCellValue('G' . ($hang), $row['goods_name']);
            $PHPExcel->getActiveSheet()->setCellValue('H' . ($hang), $row['goods_attr']);
            $PHPExcel->getActiveSheet()->setCellValue('I' . ($hang), $row['goods_sn']." ");
            $PHPExcel->getActiveSheet()->setCellValue('J' . ($hang), $row['goods_number']." ");
            // $PHPExcel->getActiveSheet()->setCellValue('I' . ($hang), $row['goods_price']." ");
            // $PHPExcel->getActiveSheet()->setCellValue('J' . ($hang), $row['total_fee']." ");
            $PHPExcel->getActiveSheet()->setCellValue('K' . ($hang), $row['consignee']);
            $PHPExcel->getActiveSheet()->setCellValue('L' . ($hang), $row['mobile']." ");
            $PHPExcel->getActiveSheet()->setCellValue('M' . ($hang), str_replace(" ","",$row['region']).$row['address']);
            $PHPExcel->getActiveSheet()->setCellValue('N' . ($hang), $row['postscript']);
            $PHPExcel->getActiveSheet()->setCellValue('O' . ($hang), $row['formated_shipping_time']);
           // $PHPExcel->getActiveSheet()->setCellValue('N' . ($hang), $row['shipping_name']);
            $objValidation = $PHPExcel->getActiveSheet()->getCell('P' . ($hang))->getDataValidation(); //这一句为要设置数据有效性的单元格
            $objValidation -> setType(PHPExcel_Cell_DataValidation::TYPE_LIST)
                -> setErrorStyle(PHPExcel_Cell_DataValidation::STYLE_INFORMATION)
                -> setAllowBlank(false)
                -> setShowInputMessage(true)
                -> setShowErrorMessage(true)
                -> setShowDropDown(true)
                -> setErrorTitle('输入的值有误')
                -> setError('您输入的值不在下拉框列表内.')
                -> setPromptTitle('快递公司')
                -> setFormula1('data!$A$1:$A$'.count($express_company));
            $PHPExcel->getActiveSheet()->setCellValue('P' . ($hang), $companyList[$row['shipping_id']]);//快递公司
            $PHPExcel->getActiveSheet()->setCellValue('Q' . ($hang), $row['invoice_no']." ");
            $PHPExcel->getActiveSheet()->setCellValue('R' . ($hang), $row['suppliers_name']);
        }
        //var_dump($companyList[$row['shipping_id']]);die();
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

    $smarty->display('load_excel.htm');
}
elseif($_REQUEST['act'] == 'upload_excel'){
    $PHPExcel = PHPExcel_IOFactory::load($_FILES['file']['tmp_name']);
    $currentSheet = $PHPExcel->getSheet(0);  
    $allColumn = "T";   /**取得一共有多少列*/     
    $allRow = $currentSheet->getHighestRow();  /**取得一共有多少行*/ 
    $field_list = array_keys($_LANG['orderout']); // 字段列表
    if($allRow<1)
    {
        sys_msg(sprintf($_LANG['delivery_xlsx_model_error']), 1, array(), false);
    }
    
    if($allRow==1)
    {
        sys_msg(sprintf($_LANG['delivery_order_is_null']), 1, array(), false);
    }
    
    $i = 1 ;   
    $col = array();  
    for($currentColumn='A'; ord($currentColumn) <= ord($allColumn) ; $currentColumn++){        
        $address = $currentColumn.$i;                
        $string = $currentSheet->getCell($address)->getValue();  
       // var_dump($_LANG['orderout'][$field_list[ord($currentColumn)-65]],trim($string));die();
        if($_LANG['orderout'][$field_list[ord($currentColumn)-65]] != trim($string)) 
        {
            sys_msg(sprintf($_LANG['delivery_xlsx_model_error']), 1, array(), false);
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
    
   //var_dump( $delivery_list);die();
    //移除非待发货发货单，减少服务器处理时间
    //移除发货单号 订单号不匹配的数据
     foreach($delivery_list as $k=>$item)
     {
         if($item['order_status']!= '已确认')
         {
             unset($delivery_list[$k]);
             continue;
         }
        
         // if(empty($item['delivery_sn'])||empty($item['order_sn']))
         // {
         //     unset($delivery_list[$k]);
         //     continue;
         // }
        
         $delivery_list[$k]['shipping_id']= express_company_shipping($item['shipping_name']);
     }
    //var_dump($delivery_list);die();
     
    // if(count($delivery_list)==0)
    // {
    //     sys_msg("订单条件不符合", 1, array(), false);
    // }


    $express_company_list_name = getshipping_name();
    //var_dump($express_company_list_name);die();
    $smarty->assign('express_company_list_name', $express_company_list_name);
    
    $smarty->assign('goods_class', $_LANG['g_class']);
    $smarty->assign('delivery_list', array_values($delivery_list));

    // 字段名称列表
    $smarty->assign('title_list', $_LANG['orderout']);

    /* 快递公司名 */
    //$express_company_list_name = express_company_list_name();
    // $smarty->assign('express_company_list_name', 888888);
    
    // 显示的字段列表
    $smarty->assign('field_show', array(
        'delivery_sn' => true, 'order_sn' => true, 'suppliers_name'=>true,'goods_name' => true,'send_number' => true, 'consignee' => true, 'region_address' => true,'invoice_no' => true));

    /* 参数赋值 */
    $smarty->assign('ur_here', $_LANG['delivery_upload_confirm']);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('upload_excel.htm');
}
elseif ($_REQUEST['act'] == 'update_delivery')
{
     /* 检查权限 */
    //admin_priv('delivery_view');
    define('GMTIME_UTC', time()); // 获取 UTC 时间戳
   // var_dump($_POST);die();
    if (isset($_POST['checked']))
    {
        /* 字段列表 */
        $field_list = array_keys($_LANG['orderout']);
        $error_msg = '';
        //二次验证
        foreach ($_POST['checked'] AS $key => $value)
        {
            if(empty($_POST['delivery_sn'][$value])||empty($_POST['order_sn'][$value]))
            {
                sys_msg($_LANG['delivery_order_has_error'], 1, array(), false);
            }else{
                $delivery_sn = trim($_POST['delivery_sn'][$value]);
                $order_sn = trim($_POST['order_sn'][$value]);
                $delivery_order = delivery_order_info(-1,$delivery_sn,$order_sn);
                if(empty($delivery_order))
                {
                    $error_msg .= sprintf($_LANG['delivery_order_not_exit'],$delivery_sn,$order_sn)."<br/>";
                }
            }
            
            //var_dump($delivery_order);die();
            if(empty($_POST['invoice_no'][$value]) && $_POST['express_company_id'][$value] != -1)
            {
                $error_msg .= sprintf($_LANG['invoice_no_is_null'],$_POST['delivery_sn'][$value])."<br/>";
            }

            if(!empty($_POST['invoice_no'][$value]) && $_POST['express_company_id'][$value] == -1)
            {
                $error_msg .= sprintf($_LANG['express_company_is_null'],$_POST['delivery_sn'][$value])."<br/>";
            }

            
        }
        if($error_msg != '')
        {
            sys_msg($error_msg, 1, array(), false);
        }

        /* 循环查询可发货的发货单 */
        foreach ($_POST['checked'] AS $key => $value)
        {
            foreach ($field_list AS $field)
            {
                // 转换编码
                $field_value = isset($_POST[$field][$value]) ? $_POST[$field][$value] : '';
            }
            $delivery   = array();
            $order_sn   = trim($_POST['order_sn'][$value]);        // 订单sn
            $delivery_sn   = trim($_POST['delivery_sn'][$value]);        // 发货单sn
            $delivery['invoice_no'] = isset($_POST['invoice_no'][$value]) ? trim($_POST['invoice_no'][$value]) : '';
            $delivery['express_company_id'] = ($_POST['express_company_id'][$value]== -1 )? 0 : $_POST['express_company_id'][$value];
            $delivery['shipping_name'] = (express_company_shipping('',$_POST['express_company_id'][$value]) == -1) ? 0 : express_company_shipping('',$_POST['express_company_id'][$value]);

            //var_dump( $delivery['express_company_name']);die();
            if(empty($_POST['invoice_no'][$value]) && $_POST['express_company_id'][$value] == -1)
            {
                continue;
            }

            $delivery_order = delivery_order_info(-1,$delivery_sn,$order_sn);
             //var_dump($delivery_order);die();

                if ($delivery_order['status'] == 2) {
                    //var_dump($delivery_order['status']);die();
                    /* 查询订单信息 */
                    //echo 111;exit;
                    $sql = "SELECT a.* FROM " . $GLOBALS['ecs']->table('order_info') . " as a WHERE  a.order_sn = '$order_sn' ";
                    //var_dump($sql);die();
                    $order = $GLOBALS['db']->getRow($sql);
                    $order_id = $order['order_id'];
                    //var_dump($order_id);die();
                    if (empty($order_id)) {
                        continue;
                    }
                    //var_dump(123);die();
                    /* 检查此单发货商品库存缺货情况 */
                    $virtual_goods = array();
                    $delivery_stock_sql = "SELECT DG.goods_id, DG.is_real, DG.product_id, SUM(DG.send_number) AS sums, IF(DG.product_id > 0, P.product_number, G.goods_number) AS storage, G.goods_name, DG.send_number
                    FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " . $GLOBALS['ecs']->table('goods') . " AS G, " . $GLOBALS['ecs']->table('products') . " AS P
                    WHERE DG.goods_id = G.goods_id
                    AND DG.delivery_id = '$delivery_order[delivery_id]'
                    AND DG.product_id = P.product_id
                    GROUP BY DG.product_id ";

                    $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);

                    /* 如果商品存在规格就查询规格，如果不存在规格按商品库存查询 */
                    if (!empty($delivery_stock_result)) {
                        foreach ($delivery_stock_result as $val) {
                            if (($val['sums'] > $val['storage'] || $val['storage'] <= 0) && (($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $val['is_real'] == 0))) {
                                //                      /* 操作失败 */
                                //                      $links[] = array('text' => $_LANG['order_info'], 'href' => 'suppliers_delivery.php?act=delivery_info&delivery_id=' . $delivery_id);
                                //                      sys_msg(sprintf($_LANG['act_good_vacancy'], $val['goods_name']), 1, $links);
                                //                      break;
                                continue;
                            }


                        }
                    } else {
                        $delivery_stock_sql = "SELECT DG.goods_id, DG.is_real, SUM(DG.send_number) AS sums, G.goods_number, G.goods_name, DG.send_number
                    FROM " . $GLOBALS['ecs']->table('delivery_goods') . " AS DG, " . $GLOBALS['ecs']->table('goods') . " AS G
                    WHERE DG.goods_id = G.goods_id
                    AND DG.delivery_id = '$delivery_order[delivery_id]'
                    GROUP BY DG.goods_id ";
                        $delivery_stock_result = $GLOBALS['db']->getAll($delivery_stock_sql);
                        foreach ($delivery_stock_result as $val) {
                            if (($val['sums'] > $val['goods_number'] || $val['goods_number'] <= 0) && (($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) || ($_CFG['use_storage'] == '0' && $val['is_real'] == 0))) {
                                //                      /* 操作失败 */
                                //                      $links[] = array('text' => $_LANG['order_info'], 'href' => 'suppliers_delivery.php?act=delivery_info&delivery_id=' . $delivery_id);
                                //                      sys_msg(sprintf($_LANG['act_good_vacancy'], $val['goods_name']), 1, $links);
                                //                      break;
                                continue;
                            }


                        }
                    }


                    /* 如果使用库存，且发货时减库存，则修改库存 */
                    if ($_CFG['use_storage'] == '1' && $_CFG['stock_dec_time'] == SDT_SHIP) {
                        foreach ($delivery_stock_result as $val) {

                            /* 商品（实货）、超级礼包（实货） */
                            if ($val['is_real'] != 0) {
                                //（货品）
                                if (!empty($val['product_id'])) {
                                    $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('products') . "
                                                    SET product_number = product_number - " . $val['sums'] . "
                                                    WHERE product_id = " . $val['product_id'];
                                    $GLOBALS['db']->query($minus_stock_sql, 'SILENT');
                                }

                                $minus_stock_sql = "UPDATE " . $GLOBALS['ecs']->table('goods') . "
                                                SET goods_number = goods_number - " . $val['sums'] . "
                                                WHERE goods_id = " . $val['goods_id'];

                                $GLOBALS['db']->query($minus_stock_sql, 'SILENT');
                            }
                        }
                    }

                    /* 修改发货单信息 */
                    $invoice_no = str_replace(',', '<br>', $delivery['invoice_no']);
                    $invoice_no = trim($invoice_no, '<br>');
                    $_delivery['invoice_no'] = $invoice_no;
                    $_delivery['update_time'] = GMTIME_UTC;
                    $_delivery['shipping_name'] = $delivery['shipping_name'];
                    $_delivery['shipping_id'] = $delivery['express_company_id'];
                    $_delivery['status'] = 0; // 0，为已发货
                    $query = $GLOBALS['db']->autoExecute($ecs->table('delivery_order'), $_delivery, 'UPDATE', "delivery_sn = '$delivery_sn'", 'SILENT');

                    if (!$query) {
                        /* 操作失败 */
                        //$links[] = array('text' => $_LANG['delivery_sn'] . $_LANG['detail'], 'href' => 'suppliers_delivery.php?act=delivery_info&delivery_id=' . $delivery_order['delivery_id']);
                        continue;
                    }

                    /* 标记订单为已确认 “已发货” */
                    /* 更新发货时间 */

                    // $order_finish = get_all_delivery_finish($delivery_order['order_id']);
                    $shipping_status = 1;
                    // $arr['shipping_status']     = $shipping_status;
                    $shipping_time = GMTIME_UTC; // 发货时间
                    $shipping_name = $delivery['shipping_name'];
                    $shipping_id = $delivery['express_company_id'];
                    //var_dump($express_company_name);die();
                    // $arr['invoice_no']          = trim($order['invoice_no'] . '<br>' . $invoice_no, '<br>');
                    $sql = "UPDATE " . $GLOBALS['ecs']->table('order_info') .
                        " SET shipping_status = 1, shipping_time = '$shipping_time', shipping_name = '$shipping_name', shipping_id = '$shipping_id', order_status = 5 " .
                        " WHERE order_sn = " . $order['order_sn'];
                    $GLOBALS['db']->query($sql);
                    //var_dump($sql);die();
                    // include_once(ROOT_PATH . '/languages/' .$GLOBALS['_CFG']['lang']. '/shopping_flow.php');
                    // $sms = sprintf($_LANG['sms_code4'],$order_sn,$delivery['express_company_name'],$delivery['invoice_no']);
                    // $error =send_ztsms(get_buyer_mobile($order_sn),$sms);
                    // if($error == '0'){
                    //     $sql = 'UPDATE ' . $GLOBALS['ecs']->table('delivery_order') .
                    //         " SET is_send_sms = '1', " .
                    //         " sms_code = '$sms' " .
                    //         "WHERE delivery_sn = '$delivery_sn'";
                    //     $GLOBALS['db']->query($sql);
                    // }else{
                    //     $sql = 'UPDATE ' . $GLOBALS['ecs']->table('delivery_order') .
                    //         " SET is_send_sms = '$error', " .
                    //         " sms_code = '$error' " .
                    //         "WHERE delivery_sn = '$delivery_sn'";
                    //     $GLOBALS['db']->query($sql);
                    // }
                    //添加日志
                    $deliveryinfo = "[" . $_LANG['op_ship'] . "]" . $_LANG['label_order_sn'] . $order_sn . "," .
                        $_LANG['label_invoice_no'] . $_delivery['invoice_no'] . "," .
                        $_LANG['shipping_name'] . $_delivery['shipping_name'];
                    admin_log($deliveryinfo, 'edit', 'delivery_order');   // 记录管理员操作

                    /* 发货单发货记录log */
                    order_action($order['order_sn'], OS_CONFIRMED, $shipping_status, $order['pay_status'], $action_note, null, 1, $_LANG['op_update_delivery']);

                    /* 如果当前订单已经全部发货 */
                    if ($order_finish) {

                        /* 更新商品销量 */
                        $sql = 'SELECT goods_id,goods_number FROM ' . $GLOBALS['ecs']->table('order_goods') . ' WHERE order_id =' . $order_id;
                        $order_res = $GLOBALS['db']->getAll($sql);
                        foreach ($order_res as $idx => $val) {
                            $sql = 'SELECT SUM(og.goods_number) as goods_number ' .
                                'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g, ' .
                                $GLOBALS['ecs']->table('order_info') . ' AS o, ' .
                                $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
                                "WHERE g.is_alone_sale = 1 AND g.is_delete = 0 AND og.order_id = o.order_id AND og.goods_id = g.goods_id " .
                                "AND (o.order_status = '" . OS_CONFIRMED . "' OR o.order_status = '" . OS_SPLITED . "') " .
                                "AND (o.pay_status = '" . PS_PAYED . "' OR o.pay_status = '" . PS_PAYING . "') " .
                                "AND (o.shipping_status = '" . SS_SHIPPED . "' OR o.shipping_status = '" . SS_RECEIVED . "') AND g.goods_id=" . $val['goods_id'];

                            $sales_volume = $GLOBALS['db']->getOne($sql);

                            $sql = "update " . $ecs->table('goods') . " set sales_volume=$sales_volume WHERE goods_id =" . $val['goods_id'];

                            $GLOBALS['db']->query($sql);
                        }
                    }
                }
            }


    }

    /* 清除缓存 */
    clear_cache_files();

    /* 显示提示信息，返回商品列表 */
    $link[] = array('href' => 'order.php?act=delivery_list', 'text' => $_LANG['09_delivery_order']);
    sys_msg($_LANG['order_delivery_upload_ok'], 0, $link);
}




/**
 * 取得发货单信息
 * @param   int     $delivery_order   发货单id（如果delivery_order > 0 就按id查，否则按sn查）
 * @param   string  $delivery_sn      发货单号
 * @return  array   发货单信息（金额都有相应格式化的字段，前缀是formated_）
 */
function delivery_order_info($delivery_id, $delivery_sn = '',$order_sn='')
{
    $return_order = '';
    if (empty($delivery_id) || !is_numeric($delivery_id))
    {
        return $return_order;
    }

    $where = ' WHERE 1=1 ';
    /* 获取管理员信息 */
    $admin_info = admin_info();
    
    $sql = "SELECT do.*, o.order_sn FROM " . $GLOBALS['ecs']->table('delivery_order') . " as do LEFT JOIN " . $GLOBALS['ecs']->table("order_info") . " as o on o.order_id = do.order_id ";

    if ($delivery_id > 0)
    {
        $where .= " AND do.delivery_id = '$delivery_id' ";
    }
    else
    {
        if(!empty($delivery_sn)&&!empty($order_sn))
        {
            $where .= " AND do.delivery_sn = '$delivery_sn' AND o.order_sn = '$order_sn' "; 
        }
        elseif(!empty($delivery_sn)&&empty($order_sn))
        {
            $where .= " AND do.delivery_sn = '$delivery_sn' ";  
        }
        elseif(!empty($order_sn)&&empty($delivery_sn))
        {
            $where .= " AND o.order_sn = '$order_sn'" ;
        }
        
    }

    $sql .= $where;
    $sql .= " LIMIT 0, 1";
    $delivery = $GLOBALS['db']->getRow($sql);
    //var_dump( $sql);die();
    if ($delivery)
    {
        $delivery['formated_add_time']       = local_date($GLOBALS['_CFG']['time_format'], $delivery['add_time']);
        $delivery['formated_update_time']    = local_date($GLOBALS['_CFG']['time_format'], $delivery['update_time']);

        $return_order = $delivery;
    }


    return $return_order;
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
        "FROM " . $GLOBALS['ecs']->table('express_company');

    //var_dump($sql);die();

    return  $GLOBALS['db']->getAll($sql);
    //var_dump($arr);die();

 }

function express_company()
{
    $sql   =  "SELECT  name ".
        "FROM " . $GLOBALS['ecs']->table('express_company');

    //var_dump($sql);die();

    return  $GLOBALS['db']->getAll($sql);
    //var_dump($arr);die();

}
function getshipping_name()
{
    $express_company_list = get_express_company();
    //var_dump($express_company_list);die();
    $express_company_name = array();
    if (count($express_company_list) > 0) {
        foreach ($express_company_list as $company) {
            $express_company_name[$company['id']] = $company['name'];
        }
    }
    return $express_company_name;
}
function express_company_shipping($express_company_name='',$express_company_id=-1)
{
    if($express_company_id==-1&& empty($express_company_name))
    {
        return -1;
    }
    if($express_company_id==-1){
        $express_company_name = trim($express_company_name);
        $sql = "SELECT id FROM ".$GLOBALS['ecs']->table('express_company')." WHERE name = '" . $express_company_name . "'";
        return $GLOBALS['db']->getOne($sql);
    }
    if(empty($express_company_name)){
        $sql = "SELECT name FROM ".$GLOBALS['ecs']->table('express_company')." WHERE id = '$express_company_id'";
        return $GLOBALS['db']->getOne($sql);
    }
}

?>
