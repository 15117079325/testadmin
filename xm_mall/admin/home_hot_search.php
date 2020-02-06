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
admin_priv('app_hot');
/*------------------------------------------------------ */
//-- 广告列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    $pid = !empty($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0;


    $smarty->assign('action_link', array('text' => '添加热搜词', 'href' => 'home_hot_search.php?act=add'));

     $smarty->assign('full_page',  1);

    $ads_list = get_adslist();

    $smarty->assign('nav_list',     $ads_list['nav']);
    $smarty->assign('filter',       $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count',   $ads_list['page_count']);

    $sort_flag  = sort_flag($ads_list['filter']);

    assign_query_info();
    $smarty->display('home/home_hot_search.htm');
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

    make_json_result($smarty->fetch('home/home_hot_search.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加新广告页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add')
{



    $smarty->assign('goods',
        array('status' => 1));

    $smarty->assign('action_link',   array('href' => 'home_hot_search.php?act=list', 'text' => '返回列表'));

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action',   'add');
    $smarty->assign('cfg_lang', $_CFG['lang']);

    assign_query_info();
    $smarty->display('home/home_hot_search_info.htm');
}

/*------------------------------------------------------ */
//-- 新广告的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert')
{


    /* 初始化变量 */
    $sk_title    = $_POST['keywords'];
    $sk_sort = intval($_PSOT['sort']);
    if(empty($sk_title)){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('热搜词不能为空', 0, $link);
    }
    /* 插入数据 */
    $sql = "INSERT INTO ".$ecs->table('search_keywords'). " (sk_title,sk_sort)
    VALUES ('$sk_title',
            '$sk_sort')";
    $db->query($sql);
    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'home_hot_search.php?act=list';
    sys_msg($_LANG['add'] . "&nbsp;" .$_POST['ad_name'] . "&nbsp;" . $_LANG['attradd_succed'],0, $link);
}


/*------------------------------------------------------ */
//-- 广告编辑页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit')
{


    /* 获取广告数据 */
    $sql = "SELECT * FROM " .$ecs->table('search_keywords'). " WHERE sk_id='".intval($_REQUEST['id'])."'";
    $ads_arr = $db->getRow($sql);

    $smarty->assign('action_link',   array('href' => 'home_hot_search.php?act=list', 'text' => '返回列表页'));
    $smarty->assign('form_act',      'update');
    $smarty->assign('action',        'edit');
    $smarty->assign('nav',           $ads_arr);

    assign_query_info();
    $smarty->display('home/home_hot_search_info.htm');
}

/*------------------------------------------------------ */
//-- 广告编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update')
{

    /* 初始化变量 */
    $id   = !empty($_POST['id'])   ? intval($_POST['id'])   : 0;

    /* 更新信息 */
    $sql = "UPDATE " .$ecs->table('search_keywords'). " SET ".
            "sk_title     = '$_POST[keywords]' ,".
             "sk_sort     = '$_POST[sort]' ".
            "WHERE sk_id = '$id'";
    $db->query($sql);

   /* 提示信息 */
   $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'home_hot_search.php?act=list');
   sys_msg($_LANG['edit'] .' '.$_POST['ad_name'].' '. $_LANG['attradd_succed'], 0, $href);

}

/*------------------------------------------------------ */
//-- 删除广告位置
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('ad_manage');

    $id = intval($_POST['id']);
    $sql = 'DELETE FROM ' . $ecs->table('search_keywords') . " WHERE sk_id={$id}";
    $id = $db->query($sql);

     if($id){
         echo json_encode(['status'=>1]);
     }
    exit;
}

elseif ($_REQUEST['act'] == 'update_name')
{

    $sk_id   = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $title = json_str_iconv(trim($_POST['title']));

   $sql = "UPDATE xm_search_keywords SET $key='$title' WHERE sk_id=$sk_id";
    if ($db->query($sql))
    {
        clear_cache_files();
        echo json_encode(['status'=>1]);die;
    }

}

/* 获取广告数据列表 */
function get_adslist()
{

    $filter = array();

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('search_keywords'). ' AS ad ';
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.* '.
            'FROM ' .$GLOBALS['ecs']->table('search_keywords'). 'AS ad ' .
            'ORDER by ad.sk_id';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
         if($rows['sk_type']==1){
             $rows['sk_type_name']= '首页热搜';
         }else{
             $rows['sk_type_name']= '店铺热搜';
         }
         $arr[] = $rows;
    }

    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

?>
