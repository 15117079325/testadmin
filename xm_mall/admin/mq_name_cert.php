<?php

/**
 * ECSHOP 会员管理程序
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: users.php 17217 2011-01-19 06:29:08Z liubo $
 */
set_time_limit(0);
define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';

/* 路由 */

$function_name = 'action_' . $action;

if (!function_exists($function_name)) {
    $function_name = "action_list";
}

call_user_func($function_name);

/* 路由 */

/* ------------------------------------------------------ */
// -- 我的团队
/* ------------------------------------------------------ */

function action_my_team($user_id)
{
// 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];
    $smarty->display('.htm');

    /* 检查权限 */
    admin_priv('name_cert_lists');
}

/* ------------------------------------------------------ */
// -- 用户帐号列表
/* ------------------------------------------------------ */
function action_list()
{
    // 全局变量
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 检查权限 */
    admin_priv('name_cert_lists');

    $smarty->assign('ur_here', $_LANG['name_cert_lists']);

    $user_list = user_list();

    $smarty->assign('user_list', $user_list['user_list']);
    $smarty->assign('filter', $user_list['filter']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('page_count', $user_list['page_count']);
    $smarty->assign('full_page', 1);
    $smarty->assign('sort_user_id', '<img src="images/sort_desc.gif">');
    $smarty->assign('lang', $_LANG);
    assign_query_info();
    $smarty->display('user_cert_list.htm');
}

/* ------------------------------------------------------ */
// -- ajax返回用户列表
/* ------------------------------------------------------ */
function action_query()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    $user_list = user_list();

    $smarty->assign('user_list', $user_list['user_list']);
    $smarty->assign('filter', $user_list['filter']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('page_count', $user_list['page_count']);

    $sort_flag = sort_flag($user_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('user_cert_list.htm'), '', array(
        'filter' => $user_list['filter'], 'page_count' => $user_list['page_count']
    ));
}

/* ------------------------------------------------------ */
// -- 用户实名认证审核
/* ------------------------------------------------------ */
function action_edit()
{
    // 全局变量

    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 检查权限 */
    admin_priv('name_cert_lists');

    $sql = "SELECT u.user_name,u.user_id, u.sex,mue.real_name,mue.status,mue.card,mue.face_card,mue.back_card,mue.user_cx_rank" .
        " FROM " . $ecs->table('users') . " u LEFT JOIN " . $ecs->table('mq_users_extra') . " mue ON u.user_id = mue.user_id WHERE u.user_id='$_GET[id]'";

    $row = $db->GetRow($sql);
    $row['user_name'] = addslashes($row['user_name']);
    if ($row) {
        $user['user_id'] = $row['user_id'];
        $user['user_name'] = $row['user_name'];
        $user['sex'] = $row['sex'];
        $user['user_rank'] = $row['user_rank'];
        $user['real_name'] = $row['real_name'];
        $user['refusal_reason'] = $row['refusal_reason'];
        $user['card'] = $row['card'];
        $user['face_card'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath('data/' . $row['face_card']);
        $user['back_card'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath('data/' . $row['back_card']);
        $user['status'] = $row['status'];

    } else {
        $link[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg($_LANG['username_invalid'], 0, $link);

    }


    /* 代码增加2014-12-23 by www.68ecshop.com _star */
    $smarty->assign('lang', $_LANG);
    /* 代码增加2014-12-23 by www.68ecshop.com _end */

    assign_query_info();
    $smarty->assign('ur_here', $_LANG['users_edit']);
    $smarty->assign('action_link', array(
        'text' => $_LANG['name_cert_lists'], 'href' => 'mq_name_cert.php?act=list&' . list_link_postfix()
    ));
    $smarty->assign('user', $user);
    $smarty->assign('form_action', 'update');
    $smarty->display('mq_cert_info.htm');
}

/* ------------------------------------------------------ */
// -- 更新用户帐号
/* ------------------------------------------------------ */
function action_update()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    /* 检查权限 */
    admin_priv('name_cert_lists');
    $status = $_POST['status'];
    $user_id = $_POST['id'];
    if (!$user_id) {
        make_json_error('参数出错，请重新尝试。');
    }
    $sql = "update " . $ecs->table('mq_users_extra') . "set `status`='$status' where user_id = '" . $user_id . "'";
    $db->query($sql);
    $clientid = $db->getOne("SELECT clientid FROM xm_users WHERE user_id={$user_id}");
    if ($clientid) {
        include_once(ROOT_PATH . 'admin/includes/Getui.php');
        $getui = new Getui();
        $custom_content['title'] = '审核通知';
        if($status){
            $custom_content['content'] = '实名认证成功!';
        }else{
            $custom_content['content'] = '实名认证失败,请上传正确的身份信息!';
        }
        $custom_content = json_encode($custom_content);
        $getui->pushMessageToSingle($clientid,$custom_content);
    }
    /* 提示信息 */
    $links[0]['text'] = $_LANG['goto_list'];
    $links[0]['href'] = 'mq_name_cert.php?act=list&' . list_link_postfix();
    $links[1]['text'] = $_LANG['go_back'];
    $links[1]['href'] = 'javascript:history.back()';

    sys_msg($_LANG['update_success'], 0, $links);
}

/* ------------------------------------------------------ */
// -- 批量删除会员帐号
/* ------------------------------------------------------ */
function action_batch_remove()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');

    if (isset($_POST['checkboxes'])) {
        $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id " . db_create_in($_POST['checkboxes']);
        $col = $db->getCol($sql);
        $usernames = implode(',', addslashes_deep($col));
        $count = count($col);
        /* 通过插件来删除用户 */
        $users = &init_users();
        $users->remove_user($col);

        admin_log($usernames, 'batch_remove', 'users');

        $lnk[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg(sprintf($_LANG['batch_remove_success'], $count), 0, $lnk);
    } else {
        $lnk[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg($_LANG['no_select_user'], 0, $lnk);
    }
}

/* 编辑用户名 */
function action_edit_username()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $username = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);

    if ($id == 0) {
        make_json_error('NO USER ID');
        return;
    }

    if ($username == '') {
        make_json_error($GLOBALS['_LANG']['username_empty']);
        return;
    }

    $users = &init_users();

    if ($users->edit_user($id, $username)) {
        if ($_CFG['integrate_code'] != 'ecshop') {
            /* 更新商城会员表 */
            $db->query('UPDATE ' . $ecs->table('users') . " SET user_name = '$username' WHERE user_id = '$id'");
        }

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result(stripcslashes($username));
    } else {
        $msg = ($users->error == ERR_USERNAME_EXISTS) ? $GLOBALS['_LANG']['username_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
        make_json_error($msg);
    }
}

/* ------------------------------------------------------ */
// -- 编辑email
/* ------------------------------------------------------ */
function action_edit_email()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $email = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_email($email)) {
        if ($users->edit_user(array(
            'username' => $username, 'email' => $email
        ))
        ) {
            admin_log(addslashes($username), 'edit', 'users');

            make_json_result(stripcslashes($email));
        } else {
            $msg = ($users->error == ERR_EMAIL_EXISTS) ? $GLOBALS['_LANG']['email_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
            make_json_error($msg);
        }
    } else {
        make_json_error($GLOBALS['_LANG']['invalid_email']);
    }
}

// 商品推荐人认证
function action_edit_is_referee()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    // 实名认证验证
    $sql = "SELECT real_name FROM " . $ecs->table('users') . " WHERE user_id = " . $id;
    if (!$db->getOne($sql)) {
        make_json_error('该用户没有实名认证，不能成为商品推荐人');
    }


    $is_referee = $_REQUEST['val'];

    $sql = "UPDATE " . $ecs->table('mq_users_extra') . " SET `is_referee` = '" . $is_referee . "' WHERE user_id = " . $id;
    $db->query($sql);
    $sql = "select is_referee from " . $ecs->table('mq_users_extra') . " where user_id = " . $id;
    $check = $db->getOne($sql);
    make_json_result(($check == 1) ? 1 : 0);

}

//消费积分
function action_act_edit_consume_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    // $consume_credit = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));
    $consume_credit = $_REQUEST['val'];
    // var_dump($_REQUEST['val']);exit();

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($consume_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$consume_credit' where account_type = 'consume' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);
        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($consume_credit);
    } else {
        make_json_error('您输入的消费积分不是一个合法的积分。');
    }
}

//待用积分
function action_edit_invest_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $invest_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($invest_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$invest_credit' where account_type = 'invest' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($invest_credit);

    } else {
        make_json_error('您输入的待用积分不是一个合法的积分。');
    }
}

//注册积分
function action_edit_register_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $register_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($register_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$register_credit' where account_type = 'register' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($register_credit);

    } else {
        make_json_error('您输入的注册积分不是一个合法的积分。');
    }
}

//分享积分
function action_edit_share_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $share_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($share_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$share_credit' where account_type = 'share' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($share_credit);

    } else {
        make_json_error('您输入的分享积分不是一个合法的积分。');
    }
}

//分享积分
function action_edit_shopping_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $shopping_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($shopping_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$shopping_credit' where account_type = 'shopping' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($shopping_credit);

    } else {
        make_json_error('您输入的购物券不是一个合法的数字。');
    }
}

//可用积分
function action_edit_useable_credit()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $useable_credit = $_REQUEST['val'];

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_num($useable_credit)) {
        $sql = "UPDATE " . $ecs->table('mq_account') . " set `money`='$useable_credit' where account_type = 'useable' and account_id = '" .
            get_account_id($username) . "'";
        $result = $db->query($sql);

        admin_log(addslashes($username), 'edit', 'users');
        make_json_result($useable_credit);

    } else {
        make_json_error('您输入的可用积分不是一个合法的积分。');
    }
}

/*获得用户account_id*/
function get_account_id($username)
{
    $sql = "select account_id" . " FROM " . $GLOBALS['ecs']->table('mq_users_extra') . ' AS mue ' .
        ' LEFT JOIN ' . $GLOBALS['ecs']->table('users') . " AS u ON u.user_id = mue.user_id " .
        " where u.user_name = '" . $username . "'";
    $account_id = $GLOBALS['db']->getOne($sql);
    return $account_id;
}

/*end*/

function action_edit_mobile_phone()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');

    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $mobile_phone = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if ($users->edit_user(array(
        'username' => $username, 'mobile_phone' => $mobile_phone
    ))
    ) {
        admin_log(addslashes($username), 'edit', 'users');

        make_json_result(stripcslashes($mobile_phone));
    } else {
        $msg = ($users->error == ERR_MOBILE_PHONE_EXISTS) ? $GLOBALS['_LANG']['mobile_phone_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
        make_json_error($msg);
    }

}


function action_edit_cash_points()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    check_authz_json('users_manage');
    $id = empty($_REQUEST['id']) ? 0 : intval($_REQUEST['id']);
    $mobile_phone = empty($_REQUEST['val']) ? '' : json_str_iconv(trim($_REQUEST['val']));

    $users = &init_users();

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '$id'";
    $username = $db->getOne($sql);

    if (is_mobile_phone($mobile_phone)) {
        if ($users->edit_user(array(
            'username' => $username, 'mobile_phone' => $mobile_phone
        ))
        ) {
            admin_log(addslashes($username), 'edit', 'users');

            make_json_result(stripcslashes($mobile_phone));
        } else {
            $msg = ($users->error == ERR_MOBILE_PHONE_EXISTS) ? $GLOBALS['_LANG']['mobile_phone_exists'] : $GLOBALS['_LANG']['edit_user_failed'];
            make_json_error($msg);
        }
    } else {
        make_json_error($GLOBALS['_LANG']['invalid_mobile_phone']);
    }
}

/* ------------------------------------------------------ */
// -- 删除会员帐号
/* ------------------------------------------------------ */
function action_remove()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');
    /* 如果会员已申请或正在申请入驻商家，不能删除会员 */
    $sql = " SELECT COUNT(*) FROM " . $ecs->table('supplier') . " WHERE user_id='" . $_GET['id'] . "'";
    $issupplier = $db->getOne($sql);
    if ($issupplier > 0) {
        /* 提示信息 */
        $link[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg(sprintf('该会员已申请或正在申请入驻商，不能删除！'), 0, $link);
    } else {
        $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
        $username = $db->getOne($sql);
        /* 通过插件来删除用户 */
        $users = &init_users();
        $users->remove_user($username); // 已经删除用户所有数据
        /* 记录管理员操作 */
        admin_log(addslashes($username), 'remove', 'users');

        /* 提示信息 */
        $link[] = array(
            'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
        );
        sys_msg(sprintf($_LANG['remove_success'], $username), 0, $link);
    }
}

/* ------------------------------------------------------ */
// -- 禁用会员帐号 2017年3月1日 by fzp
/* ------------------------------------------------------ */
function action_forbiden()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');
    $sql = "UPDATE " . $ecs->table('mq_users_extra') . " SET user_status = 2 WHERE user_id= '" . $_GET['id'] . "'";
    $db->query($sql);

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
    $username = $db->getOne($sql);

    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
    );
    sys_msg(sprintf($_LANG['forbiden_success'], $username), 0, $link);
}

/* ------------------------------------------------------ */
// -- 启用会员帐号 2017年3月2日 by fzp
/* ------------------------------------------------------ */
function action_recover()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_drop');
    $sql = "UPDATE " . $ecs->table('mq_users_extra') . " SET user_status = 1 WHERE user_id= '" . $_GET['id'] . "'";
    $db->query($sql);

    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
    $username = $db->getOne($sql);

    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
    );
    sys_msg(sprintf($_LANG['recover_success'], $username), 0, $link);
}

/* ------------------------------------------------------ */
// -- 收货地址查看
/* ------------------------------------------------------ */
function action_address_list()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $sql = "SELECT a.*, c.region_name AS country_name, p.region_name AS province, ct.region_name AS city_name, d.region_name AS district_name " . " FROM " . $ecs->table('user_address') . " as a " . " LEFT JOIN " . $ecs->table('region') . " AS c ON c.region_id = a.country " . " LEFT JOIN " . $ecs->table('region') . " AS p ON p.region_id = a.province " . " LEFT JOIN " . $ecs->table('region') . " AS ct ON ct.region_id = a.city " . " LEFT JOIN " . $ecs->table('region') . " AS d ON d.region_id = a.district " . " WHERE user_id='$id'";
    $address = $db->getAll($sql);
    $smarty->assign('address', $address);
    assign_query_info();
    $smarty->assign('ur_here', $_LANG['address_list']);
    $smarty->assign('action_link', array(
        'text' => $_LANG['03_users_list'], 'href' => 'users.php?act=list&' . list_link_postfix()
    ));
    $smarty->display('user_address_list.htm');
}

/* ------------------------------------------------------ */
// -- 脱离推荐关系
/* ------------------------------------------------------ */
function action_remove_parent()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_manage');

    $sql = "UPDATE " . $ecs->table('users') . " SET parent_id = 0 WHERE user_id = '" . $_GET['id'] . "'";
    $db->query($sql);

    /* 记录管理员操作 */
    $sql = "SELECT user_name FROM " . $ecs->table('users') . " WHERE user_id = '" . $_GET['id'] . "'";
    $username = $db->getOne($sql);
    admin_log(addslashes($username), 'edit', 'users');

    /* 提示信息 */
    $link[] = array(
        'text' => $_LANG['go_back'], 'href' => 'users.php?act=list'
    );
    sys_msg(sprintf($_LANG['update_success'], $username), 0, $link);
}

/* ------------------------------------------------------ */
// -- 查看用户推荐会员列表
/* ------------------------------------------------------ */
function action_aff_list()
{
    // 全局变量
    $user = $GLOBALS['user'];
    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty = $GLOBALS['smarty'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $user_id = $_SESSION['user_id'];

    /* 检查权限 */
    admin_priv('users_manage');
    $smarty->assign('ur_here', $_LANG['03_users_list']);

    $auid = $_GET['auid'];
    $user_list['user_list'] = array();

    $affiliate = unserialize($GLOBALS['_CFG']['affiliate']);
    $smarty->assign('affiliate', $affiliate);

    empty($affiliate) && $affiliate = array();

    $num = count($affiliate['item']);
    $up_uid = "'$auid'";
    $all_count = 0;
    for ($i = 1; $i <= $num; $i++) {
        $count = 0;
        if ($up_uid) {
            $sql = "SELECT user_id FROM " . $ecs->table('users') . " WHERE parent_id IN($up_uid)";
            $query = $db->query($sql);
            $up_uid = '';
            while ($rt = $db->fetch_array($query)) {
                $up_uid .= $up_uid ? ",'$rt[user_id]'" : "'$rt[user_id]'";
                $count++;
            }
        }
        $all_count += $count;

        if ($count) {
            $sql = "SELECT user_id, user_name, '$i' AS level, email, is_validated, user_money, frozen_money, rank_points, pay_points, reg_time " . " FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id IN($up_uid)" . " ORDER by level, user_id";
            $user_list['user_list'] = array_merge($user_list['user_list'], $db->getAll($sql));
        }
    }

    $temp_count = count($user_list['user_list']);
    for ($i = 0; $i < $temp_count; $i++) {
        $user_list['user_list'][$i]['reg_time'] = local_date($_CFG['date_format'], $user_list['user_list'][$i]['reg_time']);
    }

    $user_list['record_count'] = $all_count;

    $smarty->assign('user_list', $user_list['user_list']);
    $smarty->assign('record_count', $user_list['record_count']);
    $smarty->assign('full_page', 1);
    $smarty->assign('action_link', array(
        'text' => $_LANG['back_note'], 'href' => "users.php?act=edit&id=$auid"
    ));

    assign_query_info();
    $smarty->display('affiliate_list.htm');
}

/**
 * 返回用户列表数据
 *
 * @access public
 * @param
 *
 * @return void
 */
function user_list()
{
    $result = get_filter();
    if ($result === false) {
        /* 过滤条件 */

        $filter['keywords'] = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1) {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }
        $filter['status'] = !is_numeric($_REQUEST['status']) ? 2 : $_REQUEST['status'];


        $filter['sort_by'] = empty($_REQUEST['sort_by']) ? 'u.user_id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $ex_where = ' WHERE 1 ';
        if ($filter['keywords']) {
            $ex_where .= " AND (u.user_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' or u.email like  '%" . mysql_like_quote($filter['keywords']) . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['keywords']) . "%') ";
        }

        if ($filter['status'] != "") {
            $filter_status = $filter['status'];
            $ex_where .= " AND mue.status = '$filter_status' ";
        } else {
            $ex_where .= " AND mue.status = 0 ";
        }

        $sql1 = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('users') . " AS u" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('mq_users_extra') . " AS mue ON u.user_id = mue.user_id " .
            $ex_where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql1);

        /* 分页大小 */
        $filter = page_and_size($filter);

        $sql = "SELECT u.user_id, u.user_name, u2.user_name as invited_username, mue.status, FROM_UNIXTIME(u.reg_time) as reg_time " .
            " FROM " . $GLOBALS['ecs']->table('users') . ' AS u ' .
            ' LEFT JOIN ' . $GLOBALS['ecs']->table('mq_users_extra') . ' AS mue ON u.user_id = mue.user_id ' .
            ' LEFT JOIN ' . $GLOBALS['ecs']->table('users') . " AS u2 ON mue.invite_user_id = u2.user_id " .

            $ex_where . " ORDER by " . $filter['sort_by'] . ' ' . $filter['sort_order'] . " LIMIT " . $filter['start'] . ',' . $filter['page_size'];
        $filter['keywords'] = stripslashes($filter['keywords']);
    } else {
        $sql = $result['sql'];
        $filter = $result['filter'];
    }

    $user_list = $GLOBALS['db']->getAll($sql);

    $arr = array(
        'user_list' => $user_list, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']
    );

    return $arr;
}
