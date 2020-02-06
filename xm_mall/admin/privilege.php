<?php

use maqu\Services\UserCenterService;
use Symfony\Component\HttpFoundation\Request;
use \maqu\Models\IpSafeCheckLog;
use \maqu\Models\UserSafe;
use \Symfony\Component\HttpFoundation\JsonResponse;

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');


$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'login';
/* 路由 */
$function_name = 'action_' . $action;
if (!function_exists($function_name)) {
    $function_name = "action_list";
}
call_user_func($function_name);


//验证用户登录
function action_check_safe()
{
    $service = new UserCenterService();

    $res = $service->checkLoginSafeOnPC(BELONG_SYS_ADMIN);

    if ($res['result'] == true) {
        $jsonRepsone = new JsonResponse(
            array(
                'status' => RESPONSE_SUCCESS,
                'code' => '',
                'message' => '成功',
                'auth_failure' => AUTH_FAILURE_NO,
                'data' => $res['data']
            ));
    } else {
        $jsonRepsone = new JsonResponse(
            array(
                'status' => RESPONSE_FAILURE,
                'code' => $res['code'],
                'message' => $res['message'],
                'auth_failure' => AUTH_FAILURE_NO,
                'data' => ['phone' => $res['phone']]
            ));
    }

    exit($jsonRepsone->getContent());
}


//ip变化验证手机号登陆验
function action_phone_safe()
{
    $service = new UserCenterService();

    $res = $service->checkPhoneLoginSafe();
    exit($res->getContent());

}

//ip变化验证手机号登陆验
function action_sendcode()
{

    $service = new UserCenterService();

    $res = $service->sendMobileCode(VT_MOBILE_LOGIN_ADMIN);

    if ($res['result'] == true) {
        $jsonRepsone = new JsonResponse(
            array(
                'status' => RESPONSE_SUCCESS,
                'code' => '',
                'message' => '成功',
                'auth_failure' => AUTH_FAILURE_NO,
                'data' => $res['data']
            ));
    } else {
        $jsonRepsone = new JsonResponse(
            array(
                'status' => RESPONSE_FAILURE,
                'code' => '',
                'message' => $res['message'],
                'auth_failure' => AUTH_FAILURE_NO,
                'data' => []
            ));
    }

    exit($jsonRepsone->getContent());

}

/**
 * 退出登录
 */
function action_logout()
{

    $sess = $GLOBALS['sess'];

    /* 清除cookie */
    setcookie('ECSCP[admin_id]', '', 1);
    setcookie('ECSCP[admin_pass]', '', 1);

    $sess->destroy_session();

    action_login();

}

/**
 * 登陆界面
 */
function action_login()
{

    $smarty = $GLOBALS['smarty'];
    $_CFG = $GLOBALS['_CFG'];

    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");

    $smarty->assign('gd_version', gd_version());
    $smarty->assign('random', mt_rand());


    $smarty->display('login.htm');

}

/**
 * 验证登陆信息
 */
function action_signin()
{

    $_CFG = $GLOBALS['_CFG'];
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    if (!empty($_SESSION['captcha_word']) && (intval($_CFG['captcha']) & CAPTCHA_ADMIN)) {
        include_once(ROOT_PATH . 'includes/cls_captcha.php');

        /* 检查验证码是否正确 */
        $validator = new captcha();
        if (!empty($_POST['captcha']) && !$validator->check_word($_POST['captcha'])) {
            sys_msg($_LANG['captcha_error'], 1);
        }
    }

    $_POST['username'] = isset($_POST['username']) ? trim($_POST['username']) : '';
    $_POST['password'] = isset($_POST['password']) ? trim($_POST['password']) : '';

    $sql = "SELECT `ec_salt` FROM " . $ecs->table('admin_user') . "WHERE user_name = '" . $_POST['username'] . "'";
    $ec_salt = $db->getOne($sql);
    if (!empty($ec_salt)) {
        /* 检查密码是否正确 */
        $sql = "SELECT user_id, user_name, password, last_login, action_list, last_login,suppliers_id,ec_salt" .
            " FROM " . $ecs->table('admin_user') .
            " WHERE user_name = '" . $_POST['username'] . "' AND password = '" . md5(md5($_POST['password']) . $ec_salt) . "'";
    } else {
        /* 检查密码是否正确 */
        $sql = "SELECT user_id, user_name, password, last_login, action_list, last_login,suppliers_id,ec_salt" .
            " FROM " . $ecs->table('admin_user') .
            " WHERE user_name = '" . $_POST['username'] . "' AND password = '" . md5($_POST['password']) . "'";
    }
    $row = $db->getRow($sql);
    if ($row) {


        // 登录成功
        set_admin_session($row['user_id'], $row['user_name'], $row['action_list'], $row['last_login']);
        $_SESSION['suppliers_id'] = $row['suppliers_id'];
        //dqy add start 2012-12-10
        $_SESSION['user_name'] = $row['user_name'];
        //dqy add end 2012-12-10
        if (empty($row['ec_salt'])) {
            $ec_salt = rand(1, 9999);
            $new_possword = md5(md5($_POST['password']) . $ec_salt);
            $db->query("UPDATE " . $ecs->table('admin_user') .
                " SET ec_salt='" . $ec_salt . "', password='" . $new_possword . "'" .
                " WHERE user_id='$_SESSION[admin_id]'");
        }

        if ($row['action_list'] == 'all' && empty($row['last_login'])) {
            $_SESSION['shop_guide'] = true;
        }

        // 更新最后登录时间和IP
        $db->query("UPDATE " . $ecs->table('admin_user') .
            " SET last_login='" . time() . "', last_ip='" . real_ip() . "'" .
            " WHERE user_id='$_SESSION[admin_id]'");
        //保存最后一次登录时间用于后台判断是否过期
        $_SESSION['last_login_time'] = time();
        if (isset($_POST['remember'])) {
            $time = time() + 3600 * 24 * 365;
            setcookie('ECSCP[admin_id]', $row['user_id'], $time);
            setcookie('ECSCP[admin_pass]', md5($row['password'] . $_CFG['hash_code']), $time);
        }


        ecs_header("Location: ./index.php\n");

        exit;
    } else {
        sys_msg($_LANG['login_faild'], 1);
    }

}

/**
 *  验证手机号 登录
 * @param String captcha
 * @param String phoneNum
 * @param String mobile_code
 * @return null
 */
function action_phone_signin()
{
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    \maqu\Log::debug('action_phone_signin start');
    $request = Request::createFromGlobals();
    $captcha_phone = $request->get('captcha_phone', '');    //验证码
    $mobile = $request->get('mobile', '');                   //手机号
    $mobile_code = $request->get('mobile_code', '');        //手机验证码

    if (!$captcha_phone || !$mobile || !$mobile_code) {
        sys_msg('invalid parameter', 1);
        return;
    }

    /* 检查验证码是否正确 */
    include_once(ROOT_PATH . 'includes/cls_captcha.php');
    $validator = new captcha();
    if (!$validator->check_word($captcha_phone)) {
        sys_msg($_LANG['captcha_error'], 1);
        return;
    }

    /* 验证手机验证码是否正确 ，用手机号去查 验证码 */
    $sql = "SELECT * FROM " . $ecs->table('validate_record') . " WHERE record_key = " .
        $mobile . " AND record_type= 'mobile_login_admin' AND expired_time " . ">" . time();

    $row = $db->getRow($sql); //在 手机号验证表里有着数据,否则 sys_msg错误页面

    if (!$row || $row['record_code'] != $mobile_code) {  //验证码一致,去取得user_id
        /* 提示 验证码 错误 */
        sys_msg($_LANG['cpatch_faild'], 1);
        return;
    }

    unset($row);

    $sql = "SELECT * FROM " . $ecs->table('admin_user') . " WHERE phone = " . $mobile;
    $row = $db->getRow($sql);
    if (!$row) {
        //手机号码对应 用户不存在
        sys_msg($_LANG['unknown_err'], 1);
        return;
    }

    if ($row['locked'] == 1) {
        sys_msg('该账号已被锁定，请联系管理员解锁。', 1);
        return;
    }
//
//    // 检查是否为供货商的管理员 所属供货商是否有效
//    if (!empty($row['suppliers_id']))
//    {
//        $supplier_is_check = suppliers_list_info(' is_check = 1 AND suppliers_id = ' . $row['suppliers_id']);
//        if (empty($supplier_is_check))
//        {
//            sys_msg($_LANG['login_disable'], 1);
//            return;
//        }
//    }
//\maqu\Log::debug('phone logined!');
    // 登录成功
    set_admin_session($row['user_id'], $row['user_name'], $row['action_list'], $row['last_login']);
    $_SESSION['suppliers_id'] = $row['suppliers_id'];
    $_SESSION['user_name'] = $row['user_name'];

    if (empty($row['ec_salt'])) {
        $ec_salt = rand(1, 9999);
        $new_possword = md5(md5($_POST['password']) . $ec_salt);
        $db->query("UPDATE " . $ecs->table('admin_user') .
            " SET ec_salt='" . $ec_salt . "', password='" . $new_possword . "'" .
            " WHERE user_id='$_SESSION[admin_id]'");
    }

    if ($row['action_list'] == 'all' && empty($row['last_login'])) {
        $_SESSION['shop_guide'] = true;
    }

    // 更新最后登录时间和IP
    $db->query("UPDATE " . $ecs->table('admin_user') .
        " SET last_login='" . time() . "', last_ip='" . real_ip() . "'" .
        " WHERE user_id='$_SESSION[admin_id]'");

//    if (isset($_POST['remember']))
//    {
//        $time = time() + 3600 * 24 * 365;
//        setcookie('ECSCP[admin_id]',   $row['user_id'],                            $time);
//        setcookie('ECSCP[admin_pass]', md5($row['password'] . $_CFG['hash_code']), $time);
//    }

    /* 获取客户端ip地址 */
    $ip = get_client_ip(1);

    /* 更新ipsafe表的ip_address */
    $log = IpSafeCheckLog::where('user_id', $row['user_id'])
        ->where('belong_sys', BELONG_SYS_ADMIN)
        ->where('ip_address', $ip)->first();
    if (!$log) {
        $log = new IpSafeCheckLog();
        $log->user_id = $row['user_id'];
        $log->belong_sys = BELONG_SYS_ADMIN;
        $log->ip_address = $ip;
    }
    $log->check_time = local_date("Y-m-d H:i:s", time());
    $log->expire_time = local_date("Y-m-d H:i:s", time() + 7 * 24 * 60 * 60);
    $log->save();


    ecs_header("Location: ./index.php\n");

}

/**
 * 管理员列表页面
 */
function action_list()
{

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['admin_list']);
    $smarty->assign('action_link', array('href' => 'privilege.php?act=add', 'text' => $_LANG['admin_add']));
    $smarty->assign('full_page', 1);
    $smarty->assign('admin_list', get_admin_userlist());

    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_list.htm');

}

/**
 * 查询
 */
function action_query()
{

    $smarty = $GLOBALS['smarty'];

    $smarty->assign('admin_list', get_admin_userlist());

    make_json_result($smarty->fetch('privilege_list.htm'));
}

/**
 * 添加管理员页面
 */
function action_add()
{

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];

    /* 检查权限 */
    admin_priv('admin_manage');

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['admin_add']);
    $smarty->assign('action_link', array('href' => 'privilege.php?act=list', 'text' => $_LANG['admin_list']));
    $smarty->assign('form_act', 'insert');
    $smarty->assign('action', 'add');
    $smarty->assign('select_role', get_role_list());

    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_info.htm');
}

/**
 * 添加管理员的处理
 */
function action_insert()
{

    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 初始化 $exc 对象 */
    $exc = new exchange($ecs->table("admin_user"), $db, 'user_id', 'user_name');

    admin_priv('admin_manage');

    /* 判断管理员是否已经存在 */
    if (!empty($_POST['user_name'])) {
        $is_only = $exc->is_only('user_name', stripslashes($_POST['user_name']));

        if (!$is_only) {
            sys_msg(sprintf($_LANG['user_name_exist'], stripslashes($_POST['user_name'])), 1);
        }
    }

    /* Email地址是否有重复 */
    if (!empty($_POST['email'])) {
        $is_only = $exc->is_only('email', stripslashes($_POST['email']));

        if (!$is_only) {
            sys_msg(sprintf($_LANG['email_exist'], stripslashes($_POST['email'])), 1);
        }
    }
    /* 短信发送手机号码是否有重复 */
    if (!empty($_POST['phone'])) {
        $is_only = $exc->is_only('phone', stripslashes($_POST['phone']));

        if (!$is_only) {
            sys_msg(sprintf($_LANG['mobile_exists'], stripslashes($_POST['phone'])), 1);
        }
    }


    /* 获取添加日期及密码 */
    $add_time = time();

    $password = md5($_POST['password']);
    $role_id = '';
    $action_list = '';
    if (!empty($_POST['select_role'])) {
        $sql = "SELECT action_list FROM " . $ecs->table('role') . " WHERE role_id = '" . $_POST['select_role'] . "'";
        $row = $db->getRow($sql);
        $action_list = $row['action_list'];
        $role_id = $_POST['select_role'];
    }

    $sql = "SELECT nav_list FROM " . $ecs->table('admin_user') . " WHERE action_list = 'all'";
    $row = $db->getRow($sql);

    $phone = $_POST['phone'];
    $sql = "INSERT INTO " . $ecs->table('admin_user') . " (user_name, email, password, add_time, nav_list, action_list, role_id ,phone) " .
        " VALUES ('" . trim($_POST['user_name']) . "', '" . trim($_POST['email']) . "', '$password', '$add_time', '$row[nav_list]', '$action_list', '$role_id','$phone')";

    $db->query($sql);
    /* 转入权限分配列表 */
    $new_id = $db->Insert_ID();
    /* 变量初始化 */
//    $locked = !empty($_REQUEST['locked']) ? trim($_REQUEST['locked']) : '';  //是否锁定
//    $service = new UserCenterService();
//    /* 更新usersafe表 */
//    $res=$service->isLockedUser($new_id,BELONG_SYS_ADMIN,$locked);
//    if(!$res){
//        sys_msg('操作失败，请稍后再试！', 1);
//    }


    /*添加链接*/
    $link[0]['text'] = $_LANG['go_allot_priv'];
    $link[0]['href'] = 'privilege.php?act=allot&id=' . $new_id . '&user=' . $_POST['user_name'] . '';

    $link[1]['text'] = $_LANG['continue_add'];
    $link[1]['href'] = 'privilege.php?act=add';

    sys_msg($_LANG['add'] . "&nbsp;" . $_POST['user_name'] . "&nbsp;" . $_LANG['action_succeed'], 0, $link);

    /* 记录管理员操作 */
    admin_log($_POST['user_name'], 'add', 'privilege');
}

/**
 * 编辑管理员信息
 */
/**
 * 编辑管理员信息
 */
function action_edit()
{

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 不能编辑demo这个管理员 */
    if ($_SESSION['admin_name'] == 'demo') {
        $link[] = array('text' => $_LANG['back_list'], 'href' => 'privilege.php?act=list');
        sys_msg($_LANG['edit_admininfo_cannot'], 0, $link);
    }

    $_REQUEST['id'] = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

    /* 查看是否有权限编辑其他管理员的信息 */
    if ($_SESSION['admin_id'] != $_REQUEST['id']) {
        admin_priv('admin_manage');
    }
    /* 获取管理员信息 */
    $sql = "SELECT user_id, user_name, email, password, agency_id, role_id ,phone FROM " . $ecs->table('admin_user') .
        " WHERE user_id = '" . $_REQUEST['id'] . "'";
    $user_info = $db->getRow($sql);
    /* 获取 locked */


    /* 取得该管理员负责的办事处名称 */
    if ($user_info['agency_id'] > 0) {
        $sql = "SELECT agency_name FROM " . $ecs->table('agency') . " WHERE agency_id = '$user_info[agency_id]'";
        $user_info['agency_name'] = $db->getOne($sql);
    }

    /* 模板赋值 */
    $smarty->assign('ur_here', $_LANG['admin_edit']);
    $smarty->assign('action_link', array('text' => $_LANG['admin_list'], 'href' => 'privilege.php?act=list'));
    $smarty->assign('user', $user_info);

    /* 获得该管理员的权限 */
    $priv_str = $db->getOne("SELECT action_list FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_GET[id]'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str != 'all') {
        $smarty->assign('select_role', get_role_list());
    }
    $smarty->assign('form_act', 'update');
    $smarty->assign('action', 'edit');

    assign_query_info();
    $smarty->display('privilege_info.htm');
}

/*------------------------------------------------------ */
//-- 更新管理员信息
/*------------------------------------------------------ */

function action_update()
{
    //  $smarty = $GLOBALS['smarty'];
    $sess = $GLOBALS['sess'];
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 初始化 $exc 对象 */
    $exc = new exchange($ecs->table("admin_user"), $db, 'user_id', 'user_name');

    /* 变量初始化 */
    $admin_id = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    $admin_name = !empty($_REQUEST['user_name']) ? trim($_REQUEST['user_name']) : '';
    $admin_email = !empty($_REQUEST['email']) ? trim($_REQUEST['email']) : '';


    $ec_salt = rand(1, 9999);
    $password = !empty($_POST['new_password']) ? ", password = '" . md5(md5($_POST['new_password']) . $ec_salt) . "'" : '';
    if ($_REQUEST['act'] == 'update') {
        /* 查看是否有权限编辑其他管理员的信息 */
        if ($_SESSION['admin_id'] != $_REQUEST['id']) {
            admin_priv('admin_manage');
        }
        $g_link = 'privilege.php?act=list';
        $nav_list = '';
    } else {
        $nav_list = !empty($_POST['nav_list']) ? ", nav_list = '" . @join(",", $_POST['nav_list']) . "'" : '';
        $admin_id = $_SESSION['admin_id'];
        $g_link = 'privilege.php?act=modif';
    }
    /* 判断管理员是否已经存在 */
    if (!empty($admin_name)) {
        $is_only = $exc->num('user_name', $admin_name, $admin_id);
        if ($is_only == 1) {
            sys_msg(sprintf($_LANG['user_name_exist'], stripslashes($admin_name)), 1);
        }
    }

    /* 短信发送手机号码是否有重复 */
    if (!empty($_POST['phone'])) {
        $is_only = $exc->is_only('phone', stripslashes($_POST['phone']), $admin_id);

        if (!$is_only) {
            sys_msg(sprintf($_LANG['mobile_exists'], stripslashes($_POST['phone'])), 1);
        }
    }

    /* 更新 验证手机号 */
    $phone = $_POST['phone'];
    $sql = "UPDATE " . $ecs->table('admin_user') . " SET " .
        "phone = '$phone'" .
        "WHERE user_id = '$admin_id'";
    $db->query($sql);

    $eamil = $_POST['email'];
    $sql = "UPDATE " . $ecs->table('admin_user') . " SET " .
        "email = '$eamil'" .
        "WHERE user_id = '$admin_id'";
    $db->query($sql);

    $user_name = $_POST['user_name'];
    $sql = "UPDATE " . $ecs->table('admin_user') . " SET " .
        "user_name = '$user_name'" .
        "WHERE user_id = '$admin_id'";
    $db->query($sql);


    //如果要修改密码
    $pwd_modified = false;
    if (!empty($_POST['new_password'])) {
        /* 查询旧密码并与输入的旧密码比较是否相同 */
        $sql = "SELECT password FROM " . $ecs->table('admin_user') . " WHERE user_id = '$admin_id'";
        $old_password = $db->getOne($sql);
        $sql = "SELECT ec_salt FROM " . $ecs->table('admin_user') . " WHERE user_id = '$admin_id'";
        $old_ec_salt = $db->getOne($sql);
        if (empty($old_ec_salt)) {
            $old_ec_password = md5($_POST['old_password']);
        } else {
            $old_ec_password = md5(md5($_POST['old_password']) . $old_ec_salt);
        }
        if ($old_password <> $old_ec_password) {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['pwd_error'], 0, $link);
        }

        /* 比较新密码和确认密码是否相同 */
        if ($_POST['new_password'] <> $_POST['pwd_confirm']) {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg($_LANG['js_languages']['password_error'], 0, $link);
        } else {
            $pwd_modified = true;
        }
    }
    $role_id = '';
    $action_list = '';
    if (!empty($_POST['select_role'])) {
        $sql = "SELECT action_list FROM " . $ecs->table('role') . " WHERE role_id = '" . $_POST['select_role'] . "'";
        $row = $db->getRow($sql);
        $action_list = ', action_list = \'' . $row['action_list'] . '\'';
        $role_id = ', role_id = ' . $_POST['select_role'] . ' ';
    }
    //更新管理员信息
    if ($pwd_modified) {
        $sql = "UPDATE " . $ecs->table('admin_user') . " SET " .
            "ec_salt = '$ec_salt' " .
            $action_list .
            $role_id .
            $password .
            $nav_list .
            "WHERE user_id = '$admin_id'";
    } else {
        $sql = "UPDATE " . $ecs->table('admin_user') . " SET " .
            $action_list .
            $role_id .
            $nav_list .
            "WHERE user_id = '$admin_id'";
    }
$db->query($sql);
    /* 记录管理员操作 */
    admin_log($_POST['user_name'], 'edit', 'privilege');

    /* 如果修改了密码，则需要将session中该管理员的数据清空 */
    if ($pwd_modified && $_REQUEST['act'] == 'update_self') {
        $sess->delete_spec_admin_session($_SESSION['admin_id']);
        $msg = $_LANG['edit_password_succeed'];
    } else {
        $msg = $_LANG['edit_profile_succeed'];
    }

    /* 提示信息 */
    $link[] = array('text' => strpos($g_link, 'list') ? $_LANG['back_admin_list'] : $_LANG['modif_info'], 'href' => $g_link);
    sys_msg("$msg<script>parent.document.getElementById('header-frame').contentWindow.document.location.reload();</script>", 0, $link);

}

function action_update_self()
{

    action_update();
}

/**
 * 编辑个人资料
 */
function action_modif()
{

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $_CFG = $GLOBALS['_CFG'];

    /* 不能编辑demo这个管理员 */
    if ($_SESSION['admin_name'] == 'demo') {
        $link[] = array('text' => $_LANG['back_admin_list'], 'href' => 'privilege.php?act=list');
        sys_msg($_LANG['edit_admininfo_cannot'], 0, $link);
    }

    include_once('includes/inc_menu.php');
    include_once('includes/inc_priv.php');

//    /* 包含插件菜单语言项 */
//    $sql = "SELECT code FROM ".$ecs->table('plugins');
//    $rs = $db->query($sql);
//    while ($row = $db->FetchRow($rs))
//    {
//        /* 取得语言项 */
//        if (file_exists(ROOT_PATH.'plugins/'.$row['code'].'/languages/common_'.$_CFG['lang'].'.php'))
//        {
//            include_once(ROOT_PATH.'plugins/'.$row['code'].'/languages/common_'.$_CFG['lang'].'.php');
//        }
//
//        /* 插件的菜单项 */
//        if (file_exists(ROOT_PATH.'plugins/'.$row['code'].'/languages/inc_menu.php'))
//        {
//            include_once(ROOT_PATH.'plugins/'.$row['code'].'/languages/inc_menu.php');
//        }
//    }

    foreach ($modules AS $key => $value) {
        ksort($modules[$key]);
    }
    ksort($modules);

    foreach ($modules AS $key => $val) {
        if (is_array($val)) {
            foreach ($val AS $k => $v) {
                if (is_array($purview[$k])) {
                    $boole = false;
                    foreach ($purview[$k] as $action) {
                        $boole = $boole || admin_priv($action, '', false);
                    }
                    if (!$boole) {
                        unset($modules[$key][$k]);
                    }
                } elseif (!admin_priv($purview[$k], '', false)) {
                    unset($modules[$key][$k]);
                }
            }
        }
    }

    /* 获得当前管理员数据信息 */
    $sql = "SELECT user_id, user_name, email, nav_list " .
        "FROM " . $ecs->table('admin_user') . " WHERE user_id = '" . $_SESSION['admin_id'] . "'";
    $user_info = $db->getRow($sql);

    /* 获取导航条 */
    $nav_arr = (trim($user_info['nav_list']) == '') ? array() : explode(",", $user_info['nav_list']);
    $nav_lst = array();
    foreach ($nav_arr AS $val) {
        $arr = explode('|', $val);
        $nav_lst[$arr[1]] = $arr[0];
    }

    /* 模板赋值 */
    $smarty->assign('lang', $_LANG);
    $smarty->assign('ur_here', $_LANG['modif_info']);
    $smarty->assign('action_link', array('text' => $_LANG['admin_list'], 'href' => 'privilege.php?act=list'));
    $smarty->assign('user', $user_info);
    $smarty->assign('menus', $modules);
    $smarty->assign('nav_arr', $nav_lst);

    $smarty->assign('form_act', 'update_self');
    $smarty->assign('action', 'modif');

    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_info.htm');
}

/**
 * 为管理员分配权限
 */
function action_allot()
{

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];
    $_CFG = $GLOBALS['_CFG'];

    include_once(ROOT_PATH . 'languages/' . $_CFG['lang'] . '/admin/priv_action.php');

    admin_priv('allot_priv');
    if ($_SESSION['admin_id'] == $_GET['id']) {
        admin_priv('all');
    }

    /* 获得该管理员的权限 */
    $priv_str = $db->getOne("SELECT action_list FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_GET[id]'");

    /* 如果被编辑的管理员拥有了all这个权限，将不能编辑 */
    if ($priv_str == 'all') {
        $link[] = array('text' => $_LANG['back_admin_list'], 'href' => 'privilege.php?act=list');
        sys_msg($_LANG['edit_admininfo_cannot'], 0, $link);
    }

    /* 获取权限的分组数据 */
    $sql_query = "SELECT action_id, parent_id, action_code,relevance FROM " . $ecs->table('admin_action') .
        " WHERE parent_id = 0";
    $res = $db->query($sql_query);
    while ($rows = $db->FetchRow($res)) {
        $priv_arr[$rows['action_id']] = $rows;
    }


    /* 按权限组查询底级的权限名称 */
    $sql = "SELECT action_id, parent_id, action_code,relevance FROM " . $ecs->table('admin_action') .
        " WHERE parent_id " . db_create_in(array_keys($priv_arr));
    $result = $db->query($sql);
    while ($priv = $db->FetchRow($result)) {
        $priv_arr[$priv["parent_id"]]["priv"][$priv["action_code"]] = $priv;
    }

    // 将同一组的权限使用 "," 连接起来，供JS全选
    foreach ($priv_arr AS $action_id => $action_group) {
        $priv_arr[$action_id]['priv_list'] = join(',', @array_keys($action_group['priv']));

        foreach ($action_group['priv'] AS $key => $val) {
            $priv_arr[$action_id]['priv'][$key]['cando'] = (strpos($priv_str, $val['action_code']) !== false || $priv_str == 'all') ? 1 : 0;
        }
    }

    /* 赋值 */
    $smarty->assign('lang', $_LANG);
    $smarty->assign('ur_here', $_LANG['allot_priv'] . ' [ ' . $_GET['user'] . ' ] ');
    $smarty->assign('action_link', array('href' => 'privilege.php?act=list', 'text' => $_LANG['admin_list']));
    $smarty->assign('priv_arr', $priv_arr);
    $smarty->assign('form_act', 'update_allot');
    $smarty->assign('user_id', $_GET['id']);

    /* 显示页面 */
    assign_query_info();
    $smarty->display('privilege_allot.htm');
}

/**
 * 更新管理员的权限
 */
function action_update_allot()
{

    $_LANG = $GLOBALS['_LANG'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    admin_priv('admin_manage');

    /* 取得当前管理员用户名 */
    $admin_name = $db->getOne("SELECT user_name FROM " . $ecs->table('admin_user') . " WHERE user_id = '$_POST[id]'");

    /* 更新管理员的权限 */
    $act_list = @join(",", $_POST['action_code']);
    $sql = "UPDATE " . $ecs->table('admin_user') . " SET action_list = '$act_list', role_id = '' " .
        "WHERE user_id = '$_POST[id]'";

    $db->query($sql);
    /* 动态更新管理员的SESSION */
    if ($_SESSION["admin_id"] == $_POST['id']) {
        $_SESSION["action_list"] = $act_list;
    }

    /* 记录管理员操作 */
    admin_log(addslashes($admin_name), 'edit', 'privilege');

    /* 提示信息 */
    $link[] = array('text' => $_LANG['back_admin_list'], 'href' => 'privilege.php?act=list');
    sys_msg($_LANG['edit'] . "&nbsp;" . $admin_name . "&nbsp;" . $_LANG['action_succeed'], 0, $link);

}

/*------------------------------------------------------ */
//-- 删除一个管理员
/*------------------------------------------------------ */
function action_remove()
{

    $_LANG = $GLOBALS['_LANG'];
    $sess = $GLOBALS['sess'];
    $db = $GLOBALS['db'];
    $ecs = $GLOBALS['ecs'];

    /* 初始化 $exc 对象 */
    $exc = new exchange($ecs->table("admin_user"), $db, 'user_id', 'user_name');

    check_authz_json('admin_drop');

    $id = intval($_GET['id']);

    /* 获得管理员用户名 */
    $admin_name = $db->getOne('SELECT user_name FROM ' . $ecs->table('admin_user') . " WHERE user_id='$id'");

    /* demo这个管理员不允许删除 */
    if ($admin_name == 'demo') {
        make_json_error($_LANG['edit_remove_cannot']);
    }

    /* ID为1的不允许删除 */
    if ($id == 1) {
        make_json_error($_LANG['remove_cannot']);
    }

    /* 管理员不能删除自己 */
    if ($id == $_SESSION['admin_id']) {
        make_json_error($_LANG['remove_self_cannot']);
    }

    if ($exc->drop($id)) {
        $sess->delete_spec_admin_session($id); // 删除session中该管理员的记录

        admin_log(addslashes($admin_name), 'remove', 'privilege');
        clear_cache_files();
    }

    $url = 'privilege.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);

    ecs_header("Location: $url\n");
    exit;
}

/* 获取管理员列表 */
function get_admin_userlist()
{
    $belong_sys = BELONG_SYS_ADMIN; //后台
    $sql = 'SELECT' .
        $GLOBALS['ecs']->table('admin_user') . '.user_id,
           user_name, email, add_time, last_login ,phone,' . $GLOBALS['ecs']->table('users_safe') . '.locked' .
        ' FROM ' . $GLOBALS['ecs']->table('admin_user') .
        ' left join ' . $GLOBALS['ecs']->table('users_safe') . 'on' . $GLOBALS['ecs']->table('admin_user') . '.user_id' . '=' .
        $GLOBALS['ecs']->table('users_safe') . '.user_id and ' . $GLOBALS['ecs']->table('users_safe') . ".belong_sys =$belong_sys" .
        ' ORDER BY ' . $GLOBALS['ecs']->table('admin_user') . '.user_id DESC';
    /*  取得admin_user 表phone */
//    $sql = 'SELECT user_id, user_name, email, add_time, last_login ,phone' .
//        ' FROM ' . $GLOBALS['ecs']->table('admin_user') .' ORDER BY ' .  'user_id DESC';

//    \maqu\Log::debug($sql);
    $list = $GLOBALS['db']->getAll($sql);
    foreach ($list AS $key => $val) {
        $list[$key]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $val['add_time']);
        $list[$key]['last_login'] = local_date($GLOBALS['_CFG']['time_format'], $val['last_login']);
    }

    return $list;
}


/* 获取角色列表 */
function get_role_list()
{
    $list = array();
    $sql = 'SELECT role_id, role_name, action_list ' .
        'FROM ' . $GLOBALS['ecs']->table('role');
    $list = $GLOBALS['db']->getAll($sql);
    return $list;
}
