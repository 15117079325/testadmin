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
admin_priv('rec_goods');
/*------------------------------------------------------ */
//-- 广告列表页面
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    $pid = !empty($_REQUEST['pid']) ? intval($_REQUEST['pid']) : 0;


    $smarty->assign('action_link', array('text' => '添加商品推荐', 'href' => 'home_goods.php?act=add'));

     $smarty->assign('full_page',  1);

    $ads_list = get_adslist();

    $smarty->assign('nav_list',     $ads_list['nav']);
    $smarty->assign('filter',       $ads_list['filter']);
    $smarty->assign('record_count', $ads_list['record_count']);
    $smarty->assign('page_count',   $ads_list['page_count']);

    $sort_flag  = sort_flag($ads_list['filter']);

    assign_query_info();
    $smarty->display('home/home_goods.htm');
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

    make_json_result($smarty->fetch('home/home_goods.htm'), '',
        array('filter' => $ads_list['filter'], 'page_count' => $ads_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加新广告页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'add')
{


     //查出推荐商品
    $re_goods = $db->getAll("SELECT p_id,p_title FROM xm_product WHERE p_delete=1 AND p_putaway=1 AND p_recommend=1 LIMIT 20");
    
    //查出分类的父类信息
    $cate = $db->getAll("SELECT cate_id,cate_title FROM xm_cate WHERE parent_id=0 AND cate_id NOT IN (443,445,446,447)");
    $smarty->assign('cate',$cate);

    $smarty->assign('re_goods',$re_goods);
    $smarty->assign('goods',
        array('status' => 1));
    $smarty->assign('action_link',   array('href' => 'home_goods.php?act=list', 'text' => '返回列表'));

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action',   'add');
    $smarty->assign('cfg_lang', $_CFG['lang']);

    assign_query_info();
    $smarty->display('home/home_goods_info.htm');
}

/*------------------------------------------------------ */
//-- 新广告的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'insert')
{


    /* 初始化变量 */
    $p_id    = $_POST['goods_id'];
    $status = intval($_POST['status']);
    $pr_sort = intval($_POST['sort']);
    $cate_type = intval($_POST['cate_type']);
    // if(empty($p_id)){
    //     $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
    //     sys_msg('商品ID不能为空', 0, $link);
    // }
    // if(!($db->getOne("SELECT * FROM xm_product WHERE p_id={$p_id} AND p_delete=1"))){
    //     $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
    //     sys_msg('该商品不存在', 0, $link);
    // }
    // if(!($db->getOne("SELECT * FROM xm_product WHERE p_id={$p_id} AND p_delete=1 AND p_recommend = 1"))){
    //     $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
    //     sys_msg('请选择推荐商品进行添加', 0, $link);
    // }
    // if($p_id > 0 && $cate_type > 0) {
    //     $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
    //     sys_msg('商品ID和跳转分类不能同时存在');
    // }
    
     if(($db->getOne("SELECT * FROM xm_product_recommend WHERE p_id={$p_id}"))){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
        sys_msg('该商品已经为推荐商品，请重新选择', 0, $link);
    }

    if(empty($_FILES['ad_img']['tmp_name'])){
        $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
            sys_msg('图片不能为空', 0, $link);
    }
        if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name'] ) &&$_FILES['ad_img']['tmp_name'] != 'none'))
        {
            $ad_code = 'goods/'.basename($image->upload_image($_FILES['ad_img'], 'goods'));
        }


    /* 插入数据 */
    $sql = "INSERT INTO ".$ecs->table('product_recommend'). " (p_id,pr_img,pr_sort,pr_status,cate_type)
    VALUES ('$p_id',
            '$ad_code',
            '$pr_sort',
            '$status',
            '$cate_type')";

    $db->query($sql);

    $link[1]['text'] = $_LANG['back_ads_list'];
    $link[1]['href'] = 'home_goods.php?act=list';
    sys_msg($_LANG['add'] . "&nbsp;" .$_POST['ad_name'] . "&nbsp;" . $_LANG['attradd_succed'],0, $link);
}


/*------------------------------------------------------ */
//-- 广告编辑页面
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit')
{


    /* 获取广告数据 */
    $sql = "SELECT * FROM " .$ecs->table('product_recommend'). " WHERE pr_id='".intval($_REQUEST['id'])."'";
    $ads_arr = $db->getRow($sql);
    //查出推荐商品
    $re_goods = $db->getAll("SELECT p_id,p_title FROM xm_product WHERE p_delete=1 AND p_putaway=1 ORDER BY p_sold_num DESC");
    //查出分类的父类信息
    $cate = $db->getAll("SELECT cate_id,cate_title FROM xm_cate WHERE parent_id=0 AND cate_id NOT IN (443,445,446,447)");
    $smarty->assign('cate',$cate);
    $smarty->assign('is_add', false);
    $smarty->assign('re_goods',$re_goods);
    $smarty->assign('url_src', $ads_arr['pr_img']);

    $smarty->assign('action_link',   array('href' => 'home_goods.php?act=list', 'text' => '返回列表页'));
    $smarty->assign('form_act',      'update');
    $smarty->assign('action',        'edit');
    $smarty->assign('nav',           $ads_arr);

    assign_query_info();
    $smarty->display('home/home_goods_info.htm');
}

/*------------------------------------------------------ */
//-- 广告编辑的处理
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update')
{


    /* 初始化变量 */
    $id = !empty($_POST['id'])   ? intval($_POST['id'])   : 0;
    $itemId = $_POST['itemId'];
    $pr_sort = intval($_POST['sort']);
    $type = $_POST['type'];

    if ($type == 'goods') {
        $itemId = $_POST['goods_id'];
    }

    if($type == 'cate') {
        $itemId = $_POST['cate_id'];
    }

    if($type == 'novice') {
        $itemId = 0;
    }
    
    //  if(!($db->getOne("SELECT * FROM xm_product WHERE p_id={$_POST['goods_id']} AND p_delete=1"))){
    //     $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
    //     sys_msg('该商品不存在', 0, $link);
    // }

    //  if(!($db->getOne("SELECT * FROM xm_product WHERE p_id={$_POST['goods_id']} AND p_delete=1 AND p_recommend = 1"))){
    //     $link[] = array('text' => $_LANG['go_back'], 'href' => 'javascript:history.back(-1)');
    //     sys_msg('请选择推荐商品进行添加', 0, $link);
    // }
    /* 编辑图片类型的广告 */

        if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name']) && $_FILES['ad_img']['tmp_name'] != 'none'))
        {
            $img_up_info = 'goods/' . basename($image->upload_image($_FILES['ad_img'], 'goods'));
            $ad_code = "pr_img = '".$img_up_info."'".',';
        }
        else
        {
            $ad_code = "pr_img = '$_POST[img_url]', ";
        }

    /* 更新信息 */
    $sql = "UPDATE " .$ecs->table('product_recommend'). " SET ".
            "itemId     = '$itemId', ".
            $ad_code.
            "pr_status     = '$_POST[status]', ".
            "pr_sort     = '$pr_sort' ,".
            "type  =  '$type' ".
            "WHERE pr_id = '$id'";
    $db->query($sql);

   /* 提示信息 */
   $href[] = array('text' => $_LANG['back_ads_list'], 'href' => 'home_goods.php?act=list');
   sys_msg($_LANG['edit'] .' '.$_POST['ad_name'].' '. $_LANG['attradd_succed'], 0, $href);

}

/*------------------------------------------------------ */
//-- 删除广告位置
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('ad_manage');

    $id = intval($_POST['id']);

    $sql = "SELECT pr_img FROM " .$ecs->table('product_recommend'). " WHERE pr_id={$id}";
    $img = $db->getOne($sql);
    $sql = 'DELETE FROM ' . $ecs->table('product_recommend') . " WHERE pr_id={$id}";
    $id = $db->query($sql);
    if ((strpos($img, 'http://') === false) && (strpos($img, 'https://') === false))
    {
        $img_name = basename($img);
        \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile(DATA_DIR . '/goods/'.$img_name);
    }

     if($id){
         echo json_encode(['status'=>1]);
     }
    exit;
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
   $sql = "UPDATE xm_product_recommend SET $key=$status WHERE pr_id=$outer_id";
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
    $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('product_recommend'). ' AS ad ';
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT ad.* '.
            'FROM ' .$GLOBALS['ecs']->table('product_recommend'). 'AS ad ' .
            'ORDER by ad.pr_id';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
        $rows['pr_img'] = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/'.$rows['pr_img']);
         $arr[] = $rows;
    }

    return array('nav' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

?>
