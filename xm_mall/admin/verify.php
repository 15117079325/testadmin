<?php
define('IN_ECS', true);
define('IMAGE_IMG', 'http://huodan-test.oss-cn-hangzhou.aliyuncs.com/');

use Illuminate\Database\Capsule\Manager as DB;//如果你不喜欢这个名称，as DB;就好
define('VERIFY_MAX_CODE', 'verify_max');
require(dirname(__FILE__) . '/includes/init.php');
include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$smarty = $GLOBALS['smarty'];

if ($_REQUEST['act'] == 'list') {
    $admin_id = $_SESSION['admin_id'];
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $verify_list = verify_list();
    $smarty->assign('verify_list', $verify_list['verify']);
    $smarty->assign('filter', $verify_list['filter']);
    $smarty->assign('record_count', $verify_list['record_count']);
    $smarty->assign('page_count', $verify_list['page_count']);
    $smarty->assign('full_page', 1);
    $smarty->display('examine/verify_list.html');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {
    $verify_list = verify_list();//精品商城订单类型
    $smarty->assign('verify_list', $verify_list['verify']);
    $smarty->assign('filter', $verify_list['filter']);
    $smarty->assign('record_count', $verify_list['record_count']);
    $smarty->assign('page_count', $verify_list['page_count']);
    $smarty->assign('full_page', 1);
    make_json_result($smarty->fetch('examine/verify.html'), 'ok', array('filter' => $verify_list['filter'], 'page_count' => $verify_list['page_count']));
}


/*------------------------------------------------------ */
//-- 删除银行卡信息
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'remove') {
    $id = intval($_POST['id']);
    $sql = "UPDATE  " . $GLOBALS['ecs']->table('examines_info') . " SET status=-1  WHERE id=$id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }

}

/*------------------------------------------------------ */
//-- 用户绑定审核权限
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'useroption') {
    //判断是查找还是绑定
    $admin_id = $_REQUEST['id'];
    $verify_list = verify_max();
    $admin_info = get_user_verify($admin_id);
    $verify_info = array();
    $verify_info['user_id'] = $admin_id;
    if (empty($admin_info)) {
        $verify_info['examines'] = 0;
        $verify_info['id'] = 0;
    } else {
        $verify_info['examines'] = $admin_info['examines'];
        $verify_info['id'] = $admin_info['id'];
    }
    $verify = [];
    for ($i = 1; $i <= $verify_list['value']; $i++) {
        $verify[] = $i;
    }
    $smarty->assign('verify_list', $verify);
    $smarty->assign('verify_info', $verify_info);
    $smarty->assign('form_act', 'user_add');
    $smarty->display('examine/verify_user.html');
}

/*------------------------------------------------------ */
//-- 用户绑定审核权限
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'user_add') {
    $user_id = $_REQUEST['user_id'];
    $id = $_REQUEST['id'];
    $examines = $_REQUEST['examines'];
    $is_add = $id == 0 ? true : false;
    $time = time();
    if ($is_add) {
        $sql = "INSERT INTO  " . $GLOBALS['ecs']->table('examines_user') . " (`user_id`,`examines`,`update_time`,`create_time`) VALUES ('{$user_id}','{$examines}','{$time}','{$time}')";
        $db->query($sql);
    } else {
        $sql = "UPDATE " . $GLOBALS['ecs']->table('examines_user') . " SET user_id='{$user_id}',examines='{$examines}',update_time='{$time}' WHERE user_id='{$user_id}'";
        $db->query($sql);
    }
    $href[] = array('text' => '用户绑定审核权限成功', 'href' => 'privilege.php?act=list');
    sys_msg('用户绑定审核权限成功', 0, $href);
}

/*------------------------------------------------------ */
//-- 审核通过不通过
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'passfex') {
    $id = intval($_REQUEST['id']);
    $status = intval($_REQUEST['status']);
    $userGradeArr = get_user_verify($_SESSION['admin_id']);
    $maxGradeArr = verify_max();
    $maxGrade = $maxGradeArr['value'];
    $userGrade = $userGradeArr['examines'];
    $infoArr = get_id_verify($id);
    if ($infoArr['status'] != $userGrade) {
        echo json_encode(['status' => -1, 'msg' => '请不要越级审核'], 256);
        die;
    }
    $updateArr = [];
    $updateArr['id'] = $id;
    $is_ok = '';
    if ($maxGrade == $userGrade) {
        $updateArr['status'] = 0;
        $is_ok = true;
    } else {
        $updateArr['status'] = $infoArr['status'] + 1;
        $is_ok = false;
    }
    $db = $GLOBALS['db'];
    if ($is_ok) {
        $getUserArr = "SELECT * FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id= " . $infoArr['user_id'];
        $userArr = $GLOBALS['db']->getRow($getUserArr);
        $time = time();
        DB::beginTransaction();
        try {
            $wallet = $userArr['wallet_balance'] + $infoArr['money'];
            $userSql = "UPDATE " . $GLOBALS['ecs']->table('users') . " SET wallet_balance='{$wallet}',wallet_time='{$time}' WHERE user_id='{$infoArr['user_id']}'";
            $db->query($userSql);
            $sql = "UPDATE  " . $GLOBALS['ecs']->table('examines_info') . " SET status='{$updateArr['status']}'  WHERE id='{$updateArr['id']}'";
            $db->query($sql);
            DB::commit();
            clear_cache_files();
            echo json_encode(['status' => 1, 'msg' => '审核成功'], 256);
            die;
        } catch (\Exception $e) {
            DB::rollback();
            echo json_encode(['status' => -1, 'msg' => '审核失败'], 256);
            die;
        }
    } else {
        DB::beginTransaction();
        try {
            $sql = "UPDATE  " . $GLOBALS['ecs']->table('examines_info') . " SET status='{$updateArr['status']}'  WHERE id='{$updateArr['id']}'";
            $db->query($sql);

            DB::commit();
            clear_cache_files();
            echo json_encode(['status' => 1, 'msg' => '审核成功'], 256);
            die;
        } catch (\Exception $e) {
            DB::rollback();
            echo json_encode(['status' => -1, 'msg' => '审核失败'], 256);
            die;
        }
    }


}

function get_id_verify($id = 0)
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('examines_info') . " WHERE id= " . $id . " AND status != -1";
    return $GLOBALS['db']->getRow($sql);
}

function get_user_verify($user_id = 0)
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('examines_user') . " WHERE user_id= " . $user_id . " AND status != -1";
    return $GLOBALS['db']->getRow($sql);
}

function verify_max()
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('master_config') . " WHERE code=" . "'" . VERIFY_MAX_CODE . "'";
    return $GLOBALS['db']->getRow($sql);
}

function verify_list()
{
    $filter = array();
    if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1) {
        $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);
    }
    $admin_id = $_SESSION['admin_id'];
    $checkadminsql = "SELECT * FROM " . $GLOBALS['ecs']->table('examines_user') . " WHERE user_id = " . $admin_id;
    $check = $GLOBALS['db']->getRow($checkadminsql);
    if (empty($check)) {
        return false;
    }

    $where = ' WHERE ei.status != -1 AND ei.status=' . $check['examines'];
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
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('examines_info') . " AS ei LEFT JOIN " . $GLOBALS['ecs']->table('users') . " AS u ON ei.user_id=u.user_id" . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;


    $sql = "SELECT ei.id,u.user_name,ei.user_id,ei.card_no,ei.money,ei.image_info,ei.status,ei.exam_no,ei.create_time,ei.update_time" .
        " FROM " . $GLOBALS['ecs']->table('examines_info') . " AS ei LEFT JOIN " .
        $GLOBALS['ecs']->table('users') .
        "  AS u ON ei.user_id=u.user_id " .
        $where . " ORDER BY id DESC " .
        " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    $row = $GLOBALS['db']->getAll($sql);
    /* 格式话数据 */
    foreach ($row AS $key => $value) {
        $row[$key]['update_time'] = date('Y-m-d H:i', $value['update_time']);
        $row[$key]['create_time'] = date('Y-m-d H:i', $value['create_time']);
        $row[$key]['image_info'] = IMAGE_IMG . $value['image_info'];
        switch ($value['status']) {
            case 0 :
                $status = "审核通过";
                break;
            case -2 :
                $status = "审核未通过";
                break;
            default :
                $status = "审核中";
                break;
        }
        $row[$key]['type'] = $status;
    }

    $arr = array('verify' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}