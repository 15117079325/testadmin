<?php



define('IN_ECS', true);


require(dirname(__FILE__) . '/includes/init.php');
include_once(ROOT_PATH . 'includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);

/* act操作项的初始化 */
if (empty($_REQUEST['act']))
{
    $_REQUEST['act'] = 'list';
}
else
{
    $_REQUEST['act'] = trim($_REQUEST['act']);
}


admin_priv('cate_manage');
/* 代码增加_end  By jdy */

/*------------------------------------------------------ */
//-- 商品分类列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    /* 获取分类列表 */
    $cat_list = cate_list();
    /* 模板赋值 */
    $smarty->assign('ur_here',      $_LANG['04_category_list']);
    $smarty->assign('action_link',  array('href' => 'app_cate.php?act=add', 'text' => $_LANG['04_category_add']));
    $smarty->assign('full_page',    1);

    $smarty->assign('cat_info',     $cat_list);

    /* 列表页面 */
    assign_query_info();
    $smarty->display('home/cate_list.htm');
}


/*------------------------------------------------------ */
//-- 添加商品分类
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'add')
{
    $smarty = $GLOBALS['smarty'];
    $cate_sql = "SELECT * FROM xm_cate WHERE parent_id=0 ORDER BY cate_sort";
    $cate = $db->query($cate_sql);
    $smarty->assign('cate',$cate );
    $smarty->assign('action_link',   array('href' => 'app_cate.php?act=attr_list', 'text' => '返回列表'));

    $smarty->assign('form_act', 'insert');
    $smarty->assign('action',   'add');

    assign_query_info();

    $smarty->display('home/cate_info.htm');
}


/*------------------------------------------------------ */
//-- 商品分类添加时的处理
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'insert')
{
    $parent_id = $_POST['cate_id'];
    $cate_name = trim($_POST['cate_name']);
    $cate_sort = intval($_POST['cate_sort']);
    $cate_type = intval($_POST['cate_type']);
    if(empty($cate_name)){
        sys_msg('分类名称不能为空');
    }

    if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name'] ) &&$_FILES['ad_img']['tmp_name'] != 'none'))
    {
        $ad_code = 'goods/'.basename($image->upload_image($_FILES['ad_img'], 'goods'));
    }
    
    $sql = "INSERT INTO xm_cate (cate_title,parent_id,cate_sort,type,img) VALUES ('{$cate_name}',{$parent_id},{$cate_sort},{$cate_type},'$ad_code')";
    $db->query($sql);
 $link[0]['text'] = $_LANG['back_list'];
 $link[0]['href'] = 'app_cate.php?act=list';

 sys_msg('添加成功', 0, $link);

}


if ($_REQUEST['act'] == 'update_name')
{

    $sk_id   = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $title = json_str_iconv(trim($_POST['title']));

   $sql = "UPDATE xm_cate SET $key='$title' WHERE cate_id=$sk_id";
    if ($db->query($sql))
    {
        clear_cache_files();
        echo json_encode(['status'=>1]);die;
    }

}elseif ($_REQUEST['act'] == 'remove')
{

    $cate_id = intval($_POST['id']);
    $parent_id = $db->getOne("SELECT parent_id FROM xm_cate WHERE cate_id={$cate_id}") ;
    $sql = 'DELETE FROM ' . $ecs->table('cate') . " WHERE cate_id={$cate_id}";
   $id = $db->query($sql);
   if(empty($parent_id)){
       $sql = 'DELETE FROM ' . $ecs->table('cate') . " WHERE parent_id={$cate_id}";
       $db->query($sql);
   }
     if($id){
         echo json_encode(['status'=>1]);
     }
    exit;
}

//编辑分类信息的第一步查出分类信息
if($_REQUEST['act'] == 'update_list') {
    // //得到要编辑的分类ID
    $cate_id = intval($_GET['id']);
    //查询出所有分类的父类名称
    $cate_sql = "SELECT cate_id,cate_title,img FROM xm_cate WHERE parent_id=0 ORDER BY cate_sort";
    $cate = $db->query($cate_sql);
    $smarty->assign('cate',$cate );
    
    //查询出要编辑的分类信息
    $sql = "SELECT cate_id,cate_title,parent_id,type,img,cate_sort FROM xm_cate WHERE cate_id = {$cate_id}";
    $cate_list = $db->getRow($sql);
    $cate_list['cate_title'] = htmlspecialchars($cate_list['cate_title']);
    $smarty->assign('cate_list',$cate_list);
    $smarty->assign('url_src', $cate_list['img']);

    $smarty->assign('form_act', 'update');
    $smarty->assign('action',   'update_list');

    assign_query_info();
    $smarty->display('home/cate_info.htm');
}

if($_REQUEST['act'] == 'update') {
    //要编辑的分类ID
    $id = $_POST['u_id'];
    
    $parent_id = $_POST['cate_id'];
    $cate_name = trim($_POST['cate_name']);
    $cate_sort = intval($_POST['cate_sort']);
    $cate_type = intval($_POST['cate_type']);
    if(empty($cate_name)){
        sys_msg('分类名称不能为空');
    }

    if ((isset($_FILES['ad_img']['error']) && $_FILES['ad_img']['error'] == 0) || (!isset($_FILES['ad_img']['error']) && isset($_FILES['ad_img']['tmp_name'] ) &&$_FILES['ad_img']['tmp_name'] != 'none'))
    {
        $ad_code = 'goods/'.basename($image->upload_image($_FILES['ad_img'], 'goods'));
    }
    else {
        $ad_code = "$_POST[img_url]";
    }
    
    $sql = "UPDATE xm_cate SET cate_title = '{$cate_name}',parent_id = {$parent_id},cate_sort = {$cate_sort},type = {$cate_type},img = '$ad_code' WHERE cate_id = $id";
    $db->query($sql);
 $link[0]['text'] = $_LANG['back_list'];
 $link[0]['href'] = 'app_cate.php?act=list';

 sys_msg('更新成功', 0, $link);
}

function cate_list(){
    $cate_sql = "SELECT cate_id, cate_title, parent_id, cate_sort FROM xm_cate WHERE parent_id=0 ORDER BY cate_sort";
    $cate_child_sql = "SELECT cate_id, cate_title, parent_id, cate_sort FROM xm_cate WHERE parent_id!=0 ORDER BY cate_sort";
    $res = $GLOBALS['db']->getAll($cate_sql);
    $child_res = $GLOBALS['db']->getAll($cate_child_sql);
    $res = array_column($res,null,'cate_id');
    $child_res = array_column($child_res,null,'cate_id');

    foreach($child_res as $k=>$v){
        $arr[$v['parent_id']][]=$v['cate_id'];
    }
    foreach($res as $k=>$v){
        $total[$k] = $v;
        $total[$k]['level']  =0;
        foreach($arr[$k] as $kk=>$vv){
            $total[$vv] = $child_res[$vv];
            $total[$vv]['level']  =1;
        }
    }
    return $total;
}

