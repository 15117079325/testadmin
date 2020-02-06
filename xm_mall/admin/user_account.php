<?php

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
require_once(dirname(__FILE__) . '/includes/Getui.php');
if ($_REQUEST['act'] == 'list') {
    //检查权限
    admin_priv('deal_data');

    $user = top_account();

    $smarty->assign('users_list',$user['user']);
    $smarty->assign('filter', $user['filter']);
    $smarty->assign('record_count', $user['record_count']);
    $smarty->assign('page_count', $user['page_count']);
    $smarty->assign('is_status', $user['is_status']);
    $smarty->assign('full_page',1);
    $smarty->assign('type',$user['type']);
    $smarty->display('user/top_account.html');

}
elseif ($_REQUEST['act'] == 'query') {

    $smarty = $GLOBALS['smarty'];
    $user = top_account();
    $smarty->assign('users_list', $user['user']);
    $smarty->assign('filter', $user['filter']);
    $smarty->assign('record_count', $user['record_count']);
    $smarty->assign('page_count', $user['page_count']);
    make_json_result($smarty->fetch('user/top_account.html'), '', array('filter' => $user['filter'], 'page_count' => $user['page_count']));

}


/**
 * 用户优惠券排行
 **/

function top_account () {

    $filter = array();

    //获取类型
    $type = empty($_REQUEST['type']) ? 1 : $_REQUEST['type'];

    $filter['type'] = $type;

    /* 分页大小 */
    $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);

    if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
        $filter['page_size'] = intval($_REQUEST['page_size']);
    } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
        $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
    } else {
        $filter['page_size'] = 15;
    }

    $sql = "SELECT COUNT(*) FROM ".$GLOBALS['ecs']->table('user_account');
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter['page_count'] = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

    if($type == 1) {
        $sql = "SELECT u.user_id,u.mobile_phone,e.real_name,a.balance FROM ".
            $GLOBALS['ecs']->table('user_account'). ' AS a LEFT JOIN'.
            $GLOBALS['ecs']->table('users') .' AS u ON a.user_id = u.user_id LEFT JOIN '.
            $GLOBALS['ecs']->table('mq_users_extra') ." AS e ON u.user_id = e.user_id" .
            " ORDER BY balance DESC".
            " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    } else {
        $sql = "SELECT u.user_id,u.mobile_phone,e.real_name,a.release_balance AS balance  FROM ".
            $GLOBALS['ecs']->table('user_account'). ' AS a LEFT JOIN'.
            $GLOBALS['ecs']->table('users') .' AS u ON a.user_id = u.user_id LEFT JOIN '.
            $GLOBALS['ecs']->table('mq_users_extra') ." AS e ON u.user_id = e.user_id" .
            " ORDER BY release_balance DESC".
            " LIMIT " . ($filter['page'] - 1) * $filter['page_size'] . ",$filter[page_size]";
    }
    set_filter($filter, $sql);

    $row = $GLOBALS['db']->getAll($sql);

//    foreach ($row AS $key => $value ) {
//        switch ($value['status']) {
//            case 1:
//                $status_back = '正常';
//                break;
//            case 2:
//                $status_back = '不显示';
//                break;
//            default :
//                break;
//        }
//        $row[$key]['status'] = $status_back;
//
//        $row[$key]['img_src'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/' .$row[$key]['img_src']);
//    }

    $arr = array('user' => $row, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'], 'type' => $type);

    return $arr;
}

?>