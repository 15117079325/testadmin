<?php
define('IN_ECS', true);
define('IMAGE_IMG', 'http://huodan-test.oss-cn-hangzhou.aliyuncs.com');
require(dirname(__FILE__) . '/includes/init.php');
include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$smarty = $GLOBALS['smarty'];
if ($_REQUEST['act'] == 'option') {
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $examines_list = exam_list();
    $smarty->assign('examines_list', $examines_list['examines']);
    $smarty->assign('filter', $examines_list['filter']);
    $smarty->assign('action_link', array('text' => '添加银行卡', 'href' => 'examine.php?act=add'));
    $smarty->assign('record_count', $examines_list['record_count']);
    $smarty->assign('page_count', $examines_list['page_count']);
    $smarty->assign('full_page', 1);
    $smarty->display('examine/examine_option_list.html');
}
/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {
    $examines_list = exam_list();//精品商城订单类型
    $smarty->assign('examines_list', $examines_list['examines']);
    $smarty->assign('filter', $examines_list['filter']);
    $smarty->assign('record_count', $examines_list['record_count']);
    $smarty->assign('page_count', $examines_list['page_count']);
    $smarty->assign('full_page', 1);
    make_json_result($smarty->fetch('examine/examine_option.html'), 'ok', array('filter' => $examines_list['filter'], 'page_count' => $examines_list['page_count']));
}

/*------------------------------------------------------ */
//-- 修改、新增
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add' || $_REQUEST['act'] == 'edit') {
    $is_add = $_REQUEST['act'] == 'add' ? true : false; // 添加还是编辑的标识
    if ($is_add) {
        //添加
        $examines = [];
    } else {
        $id = intval($_GET['id']);
        //修改
        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('examines_card') . " WHERE id={$id}";
        $examines = $db->getRow($sql);
        $examines['card_img'] = IMAGE_IMG . $examines['card_img'];
        $examines['logo_img'] = IMAGE_IMG . $examines['logo_img'];
    }
    $smarty->assign('examines_list', $examines);
    $smarty->assign('form_act', $is_add ? 'insert' : ($_REQUEST['act'] == 'edit' ? 'update' : 'insert'));
    $smarty->display('examine/examine_add.html');
}

/*------------------------------------------------------ */
//-- 删除银行卡信息
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'remove') {
    $id = intval($_POST['id']);
    $sql = "UPDATE  " . $GLOBALS['ecs']->table('examines_card') . " SET status=-1  WHERE id=$id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }

}

/*------------------------------------------------------ */
//-- 插入银行卡 更新银行卡
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update') {
    $card_no = trim($_POST['card_no']);
    $truename = $_POST['truename'];
    $money = $_POST['money'];
    $balance = $_POST['balance'];
    $phone = $_POST['phone'];
    $card_name = $_POST['card_name'];
    $is_emergency = $_POST['is_emergency'];
    //判断是添加还是更新
    $is_add = $_REQUEST['act'] == 'insert' ? true : false;
    //添加时图片上传判断
    if ($is_add) {
        if (empty($_FILES['logo_img'])) {
            sys_msg("请上传银行卡logo");
        }
        if (empty($_FILES['card_img'])) {
            sys_msg("请上传银行卡图片");
        }
    }
    // 如果上传了图片，相应处理
    $sql = "SELECT logo_img,card_img " .
        " FROM " . $GLOBALS['ecs']->table('examines_card') .
        " WHERE id = '{$_REQUEST['examines_id']}'";
    $rowExamines = $db->getRow($sql);
    if (($_FILES['card_img'] != '')) {
        if ($_REQUEST['examines_id'] > 0) {
            /* 删除原来的图片文件 */
            if ($rowExamines['card_img'] != '' && is_file('../' . $rowExamines['card_img'])) {
                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($rowExamines['card_img']);
            }
        }
        $original_img = $image->upload_image($_FILES['card_img'], 'examine'); // 原始图片
        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $card_img = $original_img;   // 商品图片

    } else {
        $card_img = $_POST['card_img'];
    }
    if (($_FILES['logo_img'] != '')) {
        if ($_REQUEST['examines_id'] > 0) {
            /* 删除原来的图片文件 */
            if ($rowExamines['logo_img'] != '' && is_file('../' . $rowExamines['logo_img'])) {
                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($rowExamines['logo_img']);
            }

        }
        $original_img = $image->upload_image($_FILES['card_img'], 'examine'); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $logo_img = $original_img;   // 商品图片

    } else {
        $logo_img = $_POST['card_img'];
    }

    $time = time();
    if ($is_add) {
        $sql = "INSERT INTO " . $GLOBALS['ecs']->table('examines_card') . " (`card_no`,`truename`,`money`,`balance`,`phone`,`update_time`,`create_time`,`card_name`,`card_img`,`is_emergency`,`logo_img`) VALUE ('{$card_no}','{$truename}','{$money}','{$balance}','{$phone}','{$time}','{$time}','{$card_name}','{$card_img}','{$is_emergency}','{$logo_img}')";
        $db->query($sql);
    } else {
        //商品表更新
        $goods_sql = "UPDATE " . $GLOBALS['ecs']->table('examines_card') . " SET card_no='{$card_no}',truename='{$truename}',money = '{$money}',balance='{$balance}',phone='{$phone}',update_time='{$time}',card_name='{$card_name}',is_emergency='{$is_emergency}',card_img='{$card_img}',logo_img='{$logo_img}' WHERE id={$_REQUEST['examines_id']}";
        $db->query($goods_sql);
    }
    $href[] = array('text' => '银行卡更新成功', 'href' => 'examine.php?act=option');
    sys_msg('银行卡更新成功', 0, $href);
}

/**
 *  获取银行卡信息
 *
 * @access  public
 * @param
 *
 * @return void
 */
function exam_list()
{
    $filter = array();
    /* 过滤信息 */
    $filter['card_no'] = empty($_REQUEST['card_no']) ? '' : trim($_REQUEST['card_no']);
    /* 过滤信息 */
    $filter['phone'] = empty($_REQUEST['phone']) ? '' : trim($_REQUEST['phone']);
    /* 过滤信息 */
    $filter['status'] = empty($_REQUEST['status']) ? 3 : trim($_REQUEST['status']);
    if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1) {
        $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);
    }

    $where = ' WHERE status != -1 ';

    if ($filter['card_no']) {
        $where .= " AND card_no='{$filter['card_no']}'";
    }
    if ($filter['phone']) {
        $where .= " AND phone={$filter['phone']}";
    }
    if ($filter['status'] && $filter['status'] != 3) {
        $where .= " AND status={$filter['status']}";
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
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('examines_card') . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;


    $sql = "SELECT id,card_no,truename,money,balance,count,phone,update_time,create_time,card_img,card_name,is_emergency,logo_img" .
        " FROM " . $GLOBALS['ecs']->table('examines_card') . $where .
        " ORDER BY id DESC " .
        " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    $row = $GLOBALS['db']->getAll($sql);
    /* 格式话数据 */
    foreach ($row AS $key => $value) {
        $row[$key]['update_time'] = date('Y-m-d H:i', $value['update_time']);
        $row[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
        $row[$key]['card_img'] = IMAGE_IMG . $value['card_img'];
        $row[$key]['logo_img'] = IMAGE_IMG . $value['logo_img'];
        switch ($value['status']) {
            case 1 :
                $status = "冻结";
                break;
            case 0 :
                $status = "正常";
                break;
            default :
                break;
        }
        switch ($value['is_emergency']) {
            case 1 :
                $emergency = "是";
                break;
            case 0 :
                $emergency = "否";
                break;
            default :
                break;
        }
        $row[$key]['emergency'] = $emergency;
        $row[$key]['type'] = $status;
    }

    $arr = array('examines' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}