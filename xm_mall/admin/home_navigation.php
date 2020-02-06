<?php

/**
 * ECSHOP 广告管理程序
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
$exc   = new exchange($ecs->table("ad"), $db, 'ad_id', 'ad_name');

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}
 admin_priv('navigation');
/*------------------------------------------------------ */
//-- 广告列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    $pid = !empty($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0;


    $smarty->assign('action_link', array('text' => '添加导航栏', 'href' => 'home_navigation.php?act=add'));

     $smarty->assign('full_page',  1);

    $ads_list = get_adslist();

    $smarty->assign('nav_list',     $ads_list['nav']);
    $smarty->assign('filter',       $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count',   $ads_list['page_count']);

    $sort_flag  = sort_flag($ads_list['filter']);

    assign_query_info();
    $smarty->display('home/home_navigation.htm');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    $ads_list = get_adslist();

    $smarty->assign('nav_list',     $ads_list['nav']);
    $smarty->assign('filter',       $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count',   $ads_list['page_count']);

    $sort_flag  = sort_flag($ads_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('home/home_navigation.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加新广告页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add')
{



    $smarty->assign('nav',
        array('status' => 1));
    $smarty->assign('action_link',   array('href' => 'home_navigation.php?act=list', 'text' => '返回列表页'));

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action',   'add');
    $smarty->assign('cfg_lang', $_CFG['lang']);

    assign_query_info();
    $smarty->display('home/home_navigation_info.htm');
}

//-- 新广告的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert')
{


    /* 初始化变量 */
    $outer_title    = trim($_POST['title']);
    $outer_url = trim($_POST['link']);
    $sort = $_POST['sort'];
    $status = $_POST['status'];
    if (!preg_match('/^(http|https|ftp):\/\/[\w.]+[\w\/]*[\w.]*\??[\w=&\+\%]*/is', $outer_url)){
    $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('填写的网址有误', 0, $link);
    }
    if(empty($outer_title)){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('导航栏名字不能为空', 0, $link);
    }
    if(empty($outer_url)){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('跳转链接不能为空', 0, $link);
    }
    if(empty($_FILES['ad_img']['tmp_name'])){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg('图片不能为空', 0, $link);
    }
        if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name'] ) &&$_FILES['ad_img']['tmp_name'] != 'none'))
        {
            $img = 'navigation/'.basename($image->upload_image($_FILES['ad_img'], 'navigation'));
        }


    /* 插入数据 */
    $sql = "INSERT INTO ".$ecs->table('outer'). " (outer_title,outer_img,outer_url,outer_sort,status)
    VALUES ('$outer_title',
            '$img',
            '$outer_url',
            '$sort',
            '$status'
            )";

    $db->query($sql);
    /* 记录管理员操作 */
    admin_log($_POST['ad_name'], 'add', 'ads');

    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'carousel.php?act=list';
    sys_msg($_LANG['add'] . "&nbsp;" .$_POST['ad_name'] . "&nbsp;" . $_LANG['attradd_succed'],0, $link);

}

/*------------------------------------------------------ */
//-- 广告编辑页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit')
{


    /* 获取广告数据 */
    $sql = "SELECT * FROM " .$ecs->table('outer'). " WHERE outer_id='".intval($_REQUEST['id'])."'";
    $ads_arr = $db->getRow($sql);
    $ads_arr['outer_title'] = htmlspecialchars($ads_arr['outer_title']);
    $smarty->assign('url_src', $ads_arr['outer_img']);

    $smarty->assign('ur_here',       $_LANG['ads_edit']);
    $smarty->assign('action_link',   array('href' => 'home_navigation.php?act=list', 'text' => '返回列表页'));
    $smarty->assign('form_act',      'update');
    $smarty->assign('action',        'edit');
    $smarty->assign('nav',           $ads_arr);

    assign_query_info();
    $smarty->display('home/home_navigation_info.htm');
}

/*------------------------------------------------------ */
//-- 广告编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update')
{


    /* 初始化变量 */
    $id   = !empty($_POST['id'])   ? intval($_POST['id'])   : 0;

    /* 编辑图片类型的广告 */

        if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['ad_img']['tmp_name'] != 'none'))
        {
            $img_up_info = 'carousel/' . basename($image->upload_image($_FILES['ad_img'], 'carousel'));
            $ad_code = "outer_img = '".$img_up_info."'".',';
        }
        else
        {
             if (!empty($_POST['img_url']))
            {
                $ad_code = "outer_img = '$_POST[img_url]', ";
            }else{
                 $ad_code = '';
             }
        }

    /* 更新信息 */
    $sql = "UPDATE " .$ecs->table('outer'). " SET ".
            "outer_title = '$_POST[title]', ".
            "outer_url     = '$_POST[link]', ".
            $ad_code.
            "outer_sort     = '$_POST[sort]', ".
            "status     = '$_POST[status]' ".
            "WHERE outer_id = '$id'";
    $db->query($sql);

   /* 提示信息 */
   $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'home_navigation.php?act=list');
   sys_msg($_LANG['edit'] .' '.$_POST['ad_name'].' '. $_LANG['attradd_succed'], 0, $href);

}

/*------------------------------------------------------ */
//-- 删除广告位置
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('ad_manage');

    $id = intval($_POST['id']);

    $sql = "SELECT outer_img FROM " .$ecs->table('outer'). " WHERE outer_id={$id}";
    $img = $db->getOne($sql);
    $sql = 'DELETE FROM ' . $ecs->table('outer') . " WHERE outer_id={$id}";
    $id = $db->query($sql);
    if ((strpos($img, 'http://') === false) && (strpos($img, 'https://') === false))
    {
        $img_name = basename($img);
        \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile(DATA_DIR . '/navigation/'.$img_name);
    }

    admin_log('', 'remove', 'navigation');
     if($id){
         echo json_encode(['status'=>1]);
     }
    exit;
}/*------------------------------------------------------ */
//-- 显示导航栏排序
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'update_name')
{


    $outer_id   = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $title = json_str_iconv(trim($_POST['title']));

   $sql = "UPDATE xm_outer SET $key='$title' WHERE outer_id=$outer_id";
    if ($db->query($sql))
    {
        clear_cache_files();
        echo json_encode(['status'=>1]);die;
    }

}

/*------------------------------------------------------ */
//-- 修改导航栏状态
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update_status')
{


    $outer_id   = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $status = intval($_POST['status']);
    $status= empty($status) ? 1 : 0;
   $sql = "UPDATE xm_outer SET $key=$status WHERE outer_id=$outer_id";
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

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('outer'). ' AS ad ';
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.* '.
            'FROM ' .$GLOBALS['ecs']->table('outer'). 'AS ad ' .
            'ORDER by ad.outer_id';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
        $rows['outer_img'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/'.$rows['outer_img']);
         $arr[] = $rows;
    }

    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

?>
