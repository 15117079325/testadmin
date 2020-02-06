<?php

/**
 * ECSHOP 轮播管理
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: liubo $
 * $Id: ads.php 17217 2011-01-19 06:29:08Z liubo $
 */

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
include_once(ROOT_PATH . 'includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$exc = new exchange($ecs->table("ad"), $db, 'ad_id', 'ad_name');

/* act操作项的初始化 */
if (empty($_REQUEST['act'])) {
    $_REQUEST['act'] = 'list';
} else {
    $_REQUEST['act'] = trim($_REQUEST['act']);
}
admin_priv('carousel');
/*------------------------------------------------------ */
//-- 广告列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list') {
    $pid = !empty($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0;
    //获取父类ID和名称
    $cate = $db->getAll("SELECT cate_id,cate_title FROM xm_cate WHERE parent_id=0 AND type NOT IN (1,2,3)");
    
    $smarty->assign('cate',$cate);
    $smarty->assign('ur_here', $_LANG['ad_list']);
    $smarty->assign('action_link', array('text' => '添加轮播', 'href' => 'carousel.php?act=add'));
    $smarty->assign('pid', $pid);
    $smarty->assign('full_page', 1);
    $ads_list = get_adslist();
    $smarty->assign('ads_list', $ads_list['ads']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);
    $smarty->assign('type', $ads_list['type']);
    $sort_flag = sort_flag($ads_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);
    assign_query_info();
    $smarty->display('carousel_list.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {
    $ads_list = get_adslist();

    $smarty->assign('ads_list', $ads_list['ads']);
    $smarty->assign('filter', $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count', $ads_list['page_count']);

    $sort_flag = sort_flag($ads_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('carousel_list.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加新广告页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add') {

    //查出所有商品
    $goods = $GLOBALS['db']->getAll("SELECT p_id,p_title FROM xm_product WHERE p_delete=1 AND p_putaway=1");
    //获取父类ID和名称
    $cate = $db->getAll("SELECT cate_id,cate_title FROM xm_cate WHERE parent_id=0 AND type NOT IN (1,2,3)");
    $smarty->assign('cate',$cate);

    $smarty->assign('goods', $goods);
    $smarty->assign('ur_here', $_LANG['ads_add']);
    $smarty->assign('action_link', array('href' => 'carousel.php?act=list', 'text' => $_LANG['carousel_list']));
    $smarty->assign('is_add', true);
    $smarty->assign('form_act', 'insert');
    $smarty->assign('action', 'add');
    $smarty->assign('cfg_lang', $_CFG['lang']);

    assign_query_info();
    $smarty->display('carousel_info.htm');
}

/*------------------------------------------------------ */
//-- 新广告的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert') {


    /* 初始化变量 */
    $type = !empty($_POST['type']) ? $_POST['type'] : 'goods';
    $ad_name = !empty($_POST['ad_name']) ? trim($_POST['ad_name']) : '';
    $itemId = trim($_POST['itemId']);
    $enabled = $_POST['enabled'];
    $color_value = $_POST['color_value'];
    $status = $_POST['status'];
    $type_c = $_POST['type_c'];

    if ($type == 'goods') {
        $itemId = $_POST['goods_id'];
    }

    if($type == 'cate') {
        $itemId = $_POST[cate_id];
    }

    if($type == 'novice') {
        $itemId = 0;
    }

    if (!isset($itemId)) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('itemId不能为空', 0, $link);
    }
    if ($type == 'goods') {
        if (!$GLOBALS['db']->getAll("SELECT p_id,p_title FROM xm_product WHERE p_delete=1 AND p_putaway=1 AND p_id={$itemId}")) {
            $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg('商品不存在', 0, $link);
        }
    }
//    if (empty($_FILES['ad_img']['tmp_name']) || empty($_FILES['ad_video']['tmp_name'])) {
//        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
//        sys_msg('图片或视频不能为空', 0, $link);
//    }
    if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['ad_img']['tmp_name'] != 'none')) {
        $ad_image_code = 'carousel/' . basename($image->upload_image($_FILES['ad_img'], 'carousel'));
    } else {
        $ad_video_code = 'video/' . basename($image->upload_image($_FILES['ad_video'], 'video'));
    }

    if (((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] > 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['ad_img']['tmp_name'] == 'none')) && empty($_POST['img_url'])) {
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg($_LANG['js_languages']['ad_photo_empty'], 0, $link);
    }

    if(empty($color_value)) {
        sys_msg('颜色值不能为空', 0, $link);
    }

//    if(!isset($status)) {
//        sys_msg('属性不能为空',0,$link);
//    }

    if(isset($ad_image_code)) {
        $ad_video_code = '';
    } else {
        $ad_image_code = '';
    }

    /* 插入数据 */
    $sql = "INSERT INTO " . $ecs->table('carousel_ad') . " (position_id,ca_name,img,type,itemId,enabled,color_value,status,video,type_c)
    VALUES ('$_POST[position_id]',
            '$ad_name',
            '$ad_image_code',
            '$type',
            '$itemId',
            '$enabled',
            '$color_value',
            '$status',
            '$ad_video_code',
            '$type_c')";

    $db->query($sql);
    /* 记录管理员操作 */
    admin_log($_POST['ad_name'], 'add', 'ads');

    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'carousel.php?act=list';
    sys_msg($_LANG['add'] . "&nbsp;" . $_POST['ad_name'] . "&nbsp;" . $_LANG['attradd_succed'], 0, $link);

}

/*------------------------------------------------------ */
//-- 广告编辑页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit') {


    /* 获取广告数据 */
    $sql = "SELECT * FROM " . $ecs->table('carousel_ad') . " WHERE ca_id='" . intval($_REQUEST['id']) . "'";
    $ads_arr = $db->getRow($sql);
    $ads_arr['ad_name'] = htmlspecialchars($ads_arr['ad_name']);
    $smarty->assign('url_src', $ads_arr['img']);
    $smarty->assign('video_src',$ads_arr['video']);
    $goods = $GLOBALS['db']->getAll("SELECT p_id,p_title FROM xm_product WHERE p_delete=1 AND p_putaway=1 ORDER BY p_sold_num DESC");
    //获取父类ID和名称
    $cate = $db->getAll("SELECT cate_id,cate_title FROM xm_cate WHERE parent_id=0 AND type NOT IN (1,2,3)");
    $smarty->assign('cate',$cate);
    
    $smarty->assign('is_add', false);
    $smarty->assign('goods', $goods);
    $smarty->assign('ur_here', $_LANG['ads_edit']);
    $smarty->assign('action_link', array('href' => 'carousel.php?act=list', 'text' => $_LANG['carousel_list']));
    $smarty->assign('form_act', 'update');
    $smarty->assign('action', 'edit');
    $smarty->assign('ads', $ads_arr);
    assign_query_info();
    $smarty->display('carousel_info.htm');
}

/*------------------------------------------------------ */
//-- 广告编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update') {


    /* 初始化变量 */
    $id = !empty($_POST['id']) ? intval($_POST['id']) : 0;

    /* 编辑图片类型的广告 */

    if(empty($_POST[color_value])) {
        sys_msg('颜色值不能为空', 0, $link);
    }

//    if(!isset($_POST[status])) {
//        sys_msg('属性不能为空',0,$link);
//    }

    if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['ad_img']['tmp_name'] != 'none')) {
        $img_up_info = 'carousel/' . basename($image->upload_image($_FILES['ad_img'], 'carousel'));
        $ad_code = "img = '" . $img_up_info . "'" . ',';
        $ad_video = "video = '' ";
    } else {
        $video_up_info = 'video/' . basename($image->upload_image($_FILES['ad_video'], 'video'));
        $ad_video = "video = '" .$video_up_info . "' ";
        $ad_code = "img = ''" . ',';
//        if (!empty($_POST['img_url'])) {
//            $ad_code = "img = '$_POST[img_url]', ";
//            $ad_video = "video = '' ";
//        } else {
//            $ad_code = "img = '', ";
//        }
    }
//    var_dump($_FILES['ad_video']['name']);die;
    if(!empty($_POST['img_url']) && empty($_FILES['ad_img']['name'])) {
        $ad_code = "img = '$_POST[img_url]', ";
        $ad_video = "video = '' ";
    } else if(!empty($_POST['video_url']) && empty($_FILES['ad_video']['name'])) {
        $ad_video = "video = '$_POST[video_url]' ";
        $ad_code = "img = '', ";
    }

    if((isset($_FILES['ad_video']['error']) && $_FILES['ad_video']['error'] == 0) || (!isset($_FILES['ad_video']['error']) && isset($_FILES['ad_video']['tmp_name']) && $_FILES['ad_video']['tmp_name'] != 'none')) {
        $video_up_info = 'video/' . basename($image->upload_image($_FILES['ad_video'], 'video'));
        $ad_video = "video = '" .$video_up_info . "' ";
        $ad_code = "img = ''" . ',';
    } else {
        if (!empty($_POST['video_url'])) {
            $ad_video = "video = '$_POST[video_url]' ";
            $ad_code = "img = '', ";
        } else {
            $ad_video = "video = '' ";
        }
    }

    if ($_POST['type'] == 'goods') {
        $itemId = $_POST['goods_id'];
    }else if($_POST['type'] == 'cate'){
        $itemId = $_POST[cate_id];
    }else if($_POST['type'] == 'novice') {
        $itemId = 0;
    }else{
        $itemId = $_POST['itemId'];
    }

    /* 更新信息 */
    $sql = "UPDATE " . $ecs->table('carousel_ad') . " SET " .
        "position_id = '$_POST[position_id]', " .
        "ca_name     = '$_POST[ad_name]', " .
        $ad_code .
        "itemId     = '$itemId', " .
        "type     = '$_POST[type]', " .
        "enabled     = '$_POST[enabled]', " .
        "color_value  = '$_POST[color_value]', ".
        "status = '$_POST[status]', ".
        "type_c = '$_POST[type_c]', ".
        $ad_video .
        "WHERE ca_id = '$id'";
    $db->query($sql);

    /* 记录管理员操作 */
    admin_log($_POST['ad_name'], 'edit', 'carousel_ad');


    /* 提示信息 */
    $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'carousel.php?act=list');
    sys_msg($_LANG['edit'] . ' ' . $_POST['ad_name'] . ' ' . $_LANG['attradd_succed'], 0, $href);

}

/*------------------------------------------------------ */
//-- 删除广告位置
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove') {
    check_authz_json('ad_manage');

    $id = intval($_GET['id']);

    $sql = "SELECT img FROM " . $ecs->table('carousel_ad') . " WHERE ca_id={$id}";
    $img = $db->getOne($sql);
    $sql = 'DELETE FROM ' . $ecs->table('carousel_ad') . " WHERE ca_id={$id}";
    $db->query($sql);
    if ((strpos($img, 'http://') === false) && (strpos($img, 'https://') === false)) {
        $img_name = basename($img);
        \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile(DATA_DIR . '/carousel/' . $img_name);
    }

    admin_log('', 'remove', 'carousel');

    $url = 'carousel.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);

    ecs_header("Location: $url\n");
    exit;
}elseif ($_REQUEST['act'] == 'update_status')
{
    $ca_id   = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $status = intval($_POST['status']);
    $status= empty($status) ? 1 : 0;
   $sql = "UPDATE xm_carousel_ad SET $key=$status WHERE ca_id=$ca_id";
    if ($db->query($sql))
    {
        clear_cache_files();
        echo json_encode(['status'=>$status]);die;
    }
}

/* 获取广告数据列表 */
function get_adslist()
{

    $filter = array();

    $type = empty($_REQUEST['type']) ? 1 : intval($_REQUEST['type']);

    $filter['type'] = $type;
    if($type == -1) {
        $where = " WHERE ad.status = 1 ";
    } else if($type == -2) {
        $where = " WHERE ad.status = 2 ";
    } else if($type == -3) {
        $where = " WHERE ad.status = 3 ";
    } else {
        $where = " WHERE ad.position_id={$type} AND status = 0";
    }

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('carousel_ad') . ' AS ad ' .$where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.* ' .
        'FROM ' . $GLOBALS['ecs']->table('carousel_ad') . 'AS ad ' .$where.
        ' ORDER by ad.position_id';
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {

        $rows['ad_code'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/' . $rows['img']);
        $arr[] = $rows;
    }

    return array('ads' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count'],'type' => $type);
}

?>
