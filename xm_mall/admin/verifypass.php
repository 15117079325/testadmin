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
    $smarty->display('examine/verify_pass_list.html');
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
    make_json_result($smarty->fetch('examine/verify_pass.html'), 'ok', array('filter' => $verify_list['filter'], 'page_count' => $verify_list['page_count']));
}

function verify_list()
{
    $filter = array();
    /* 过滤信息 */
    $filter['status'] = $_REQUEST['status'] == '' ? 3 : trim($_REQUEST['status']);
    if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1) {
        $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);
    }
    $admin_id = $_SESSION['admin_id'];
    $checkadminsql = "SELECT * FROM " . $GLOBALS['ecs']->table('examines_user') . " WHERE user_id = " . $admin_id;
    $check = $GLOBALS['db']->getRow($checkadminsql);
    if (empty($check)) {
        return false;
    }

    if ($filter['status'] && $filter['status'] == 3) {
        $where = ' WHERE ei.status != -1 AND (ei.status=0 OR ei.status=-2)';
    } else {
        $where = ' WHERE ei.status != -1';
    }
    if ($filter['status'] && $filter['status'] != 3) {
        $where .= " AND ei.status={$filter['status']}";
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