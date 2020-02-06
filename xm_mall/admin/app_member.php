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


    //设置excel属性基本信息
    $PHPExcel->getProperties()->setCreator("Neo")
        ->setLastModifiedBy("Neo")
        ->setTitle("")
        ->setSubject("会员列表")
        ->setDescription("")
        ->setKeywords("会员列表")
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
    $PHPExcel->getActiveSheet()->setTitle("会员列表");


    $PHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(22);//会员名称
    $PHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(22);//优惠券
    $PHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);//待释放优惠券
    $PHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(50);//注册日期
    $PHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);//实名认证
    $PHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);//手机
//edit end



//edit by jixf2017年8月29日12:17:19
    $PHPExcel->getActiveSheet()->setCellValue('A1', '会员名称');//加个空格，防止时间戳被转换
    $PHPExcel->getActiveSheet()->setCellValue('B1', '优惠券');
    $PHPExcel->getActiveSheet()->setCellValue('C1', '待释放优惠券');
    $PHPExcel->getActiveSheet()->setCellValue('D1', '注册日期');
    $PHPExcel->getActiveSheet()->setCellValue('E1', '实名认证');
    $PHPExcel->getActiveSheet()->setCellValue('F1', '手机');
 // end edit
    $hang = 1;



    // 下单开始时间
    $start_time = empty($_REQUEST['start_time']) ? '' : (strpos($_REQUEST['start_time'], '-') > 0 ?  strtotime($_REQUEST['start_time']) : $_REQUEST['start_time']);
    // 下单结束时间
    $end_time = empty($_REQUEST['end_time']) ? '' : (strpos($_REQUEST['end_time'], '-') > 0 ?  strtotime($_REQUEST['end_time']) : $_REQUEST['end_time']);

    $where = " WHERE 1 ";
    if($start_time != '' && $end_time != '')
    {
        $where .= " AND u.reg_time >= '$start_time' AND u.reg_time <= '$end_time' ";
    }else if($start_time!= '' && empty($end_time)){
        $end_time = $start_time + 30*3600*24;
        $where .= " AND u.reg_time >= '$start_time' AND u.reg_time <= '$end_time' ";

    }else if(empty($start_time)  &&  $end_time!=''){
        $start_time = $end_time - 30*3600*24;
        $where .= " AND u.reg_time >= '$start_time' AND u.reg_time <= '$end_time' ";
    }

    $sql = "SELECT u.user_name,u.mobile_phone,x.balance,x.release_balance,e.status FROM " . $GLOBALS['ecs']->table('users')
        . " u LEFT JOIN " . $GLOBALS['ecs']->table('user_account')
        . " AS x ON u.user_id = x.user_id "
        . " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra')
        . " AS e ON u.user_id = e.user_id "
        . $where ;
    $res = $db->getAll($sql);
    $list = array();

    foreach($res as $key => $rows)
    {
        $list[$key]['user_name'] = $rows['user_name'];
        $list[$key]['reg_time'] = date('y-m-d H:i:s', $rows['reg_time']);
        $list[$key]['balance'] = $rows['balance'];
        $list[$key]['release_balance'] = $rows['release_balance'];
        $list[$key]['mobile_phone'] = $rows['mobile_phone'];
       if($row['stutas']){
           $list[$key]['status'] = '已实名';
       }else{
            $list[$key]['status'] = '未实名';
       }
    }

    if($list!=NULL)
    {
        foreach($list as $k=>$row){
            $hang ++;
            $PHPExcel->getActiveSheet()->setCellValue('A' . ($hang), $row['user_name']." "); //加个空格，防止时间戳被转换
            $PHPExcel->getActiveSheet()->setCellValue('B' . ($hang), $row['balance']." ");
            $PHPExcel->getActiveSheet()->setCellValue('C' . ($hang), $row['release_balance']." ");
            $PHPExcel->getActiveSheet()->setCellValue('D' . ($hang), $row['reg_time']." ");
            $PHPExcel->getActiveSheet()->setCellValue('E' . ($hang), $row['status']);
            $PHPExcel->getActiveSheet()->setCellValue('F' . ($hang), $row['mobile_phone']." ");
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







