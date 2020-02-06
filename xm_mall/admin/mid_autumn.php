<?php


define('IN_ECS', true);
define('GOODS_ID', '商品id');

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . '/' . ADMIN_PATH . "/nusoap/nusoap.php");   //代码增加  By  www.68ecshop.com
include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$exc = new exchange($ecs->table('luck_goods_draw'), $db, 'goods_id', 'goods_name');

$smarty->assign('lang', $_LANG);

/* 获取商品数据列表 */
function app_goods_list()
{

    $filter = array();
    $goods_name = empty($_REQUEST['goods_name']) ? '' : $_REQUEST['goods_name'];
    $status = empty($_REQUEST['status']) ? 0 : intval($_REQUEST['status']);

    $filter['status'] = $status;
    $filter['goods_name'] = $goods_name;
    if (!empty($_GET['is_ajax']) && $_GET['is_ajax'] == 1) {
        $_REQUEST['consignee'] = json_str_iconv($_REQUEST['consignee']);

    }
    if ($goods_name) {
        $where = " WHERE goods_name LIKE '%{$goods_name}%' ";
    }
    if ($status >= 0) {
        $where = " WHERE status = '{$status}' ";
    } else {
        $where = " WHERE status=0 ";
    }


    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('luck_goods_draw') . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);
    $filter = page_and_size($filter);

    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT * ' .
        'FROM ' . $GLOBALS['ecs']->table('luck_goods_draw') . $where . 'ORDER BY id DESC';

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {

        $arr[] = $rows;
    }

    return array('goods_list' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}


// act操作项的初始化
if (empty($_REQUEST['act'])) {
    $_REQUEST['act'] = 'list';
} else {
    $_REQUEST['act'] = trim($_REQUEST['act']);
}

if ($_REQUEST['act'] == 'list') {
    admin_priv('prize_setting');

    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty->assign('lang', $_LANG);
    $goods_list = app_goods_list();
    $smarty->assign('action_link', array('text' => '添加奖品', 'href' => 'mid_autumn.php?act=add'));
    $smarty->assign('goods_list', $goods_list['goods_list']);
    $smarty->assign('filter', $goods_list['filter']);
    $smarty->assign('record_count', $goods_list['record_count']);
    $smarty->assign('page_count', $goods_list['page_count']);
    $smarty->assign('full_page', 1);

    assign_query_info();
    $smarty->display('home/mid_autumn_list.html');
}

/*------------------------------------------------------ */
//-- 排序、分页、查询
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query') {

    $smarty = $GLOBALS['smarty'];
    $goods_list = app_goods_list();
    $smarty->assign('goods_list', $goods_list['goods_list']);
    $smarty->assign('filter', $goods_list['filter']);
    $smarty->assign('record_count', $goods_list['record_count']);
    $smarty->assign('page_count', $goods_list['page_count']);
    make_json_result($smarty->fetch('home/mid_autumn.html'), 'ok', array('filter' => $goods_list['filter'], 'page_count' => $goods_list['page_count']));
}
/*------------------------------------------------------ */
//-- 添加新商品 编辑商品
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'add' || $_REQUEST['act'] == 'edit') {

    $p_id = intval($_GET['p_id']);
    $is_add = $_REQUEST['act'] == 'add' ? true : false; // 添加还是编辑的标识
    if ($is_add) {
        //添加
        $goods = array(
            'goods_num' => 0,
            'goods_name' => '',
            'deployment_num' => 0,
            'goods_weight' => 0,
            'goods_img' => 0,
        );
    } else {
        //修改
        //查出商品信息
        $sql = "SELECT * FROM xm_luck_goods_draw WHERE id={$p_id}";
        $goods = $db->getRow($sql);
    }
    $smarty->assign('goods', $goods);
    $smarty->assign('cate', [1, 2, 3, 4]);
    $smarty->assign('form_act', $is_add ? 'insert' : ($_REQUEST['act'] == 'edit' ? 'update' : 'insert'));
    $smarty->display('mid_autumn_add.htm');
}

/*------------------------------------------------------ */
//-- 插入商品 更新商品
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update') {

    $goods_name = trim($_POST['goods_name']);
    $goods_stock = $_POST['goods_stock'];
    $goods_weight = $_POST['goods_weight'];
    $goods_deployment_num = $_POST['goods_deployment_num'];
    $goods_code = $_POST['goods_code'];
    $goods_code_luck = $_POST['goods_code_luck'];
    $is_prize = $_POST['is_prize'];
    //判断是添加还是更新
    $is_add = $_REQUEST['act'] == 'insert' ? true : false;
    //添加商品的时候传图片判断
    if ($is_add) {
        if (empty($_FILES['goods_img']['tmp_name'])) {
            sys_msg("请上传商品图片");
        }
    }

// 如果上传了商品图片，相应处理
    $sql = "SELECT goods_img,goods_num,deployment_num " .
        " FROM " . $ecs->table('luck_goods_draw') .
        " WHERE id = '{$_REQUEST['goods_id']}'";
    $rowGoods = $db->getRow($sql);
    if (($_FILES['goods_img']['tmp_name'] != '')) {
        if ($_REQUEST['goods_id'] > 0) {
            /* 删除原来的图片文件 */
            if ($rowGoods['goods_img'] != '' && is_file('../' . $rowGoods['goods_img'])) {
                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($rowGoods['goods_img']);
            }

        }
        $original_img = 'goods/' . basename($image->upload_image($_FILES['goods_img'], 'goods')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $p_list_pic = $original_img;   // 商品图片

    } else {
        $p_list_pic = $_POST['goods_img_hidden'];
    }
    $time = time();
    if ($is_add) {
        $sql = "INSERT INTO xm_luck_goods_draw (`goods_num`,`goods_img`,`goods_name`,`goods_weight`,`create_time`,`update_time`,`deployment_num`,`is_prize`,`goods_code_luck`,`goods_code`) VALUE ('{$goods_stock}','{$p_list_pic}','{$goods_name}','{$goods_weight}','{$time}','{$time}','{$goods_deployment_num}','{$is_prize}','{$goods_code_luck}','{$goods_code}')";
//        $sql = "INSERT INTO xm_luck_goods_draw (`goods_num`,`goods_img`,`goods_name`,`goods_weight`,`create_time`,`update_time`,`deployment_num`) VALUE ('{$goods_stock}','{$p_list_pic}','{$goods_name}','{$goods_weight}','{$time}','{$time}','{$goods_deployment_num}')";
        $db->query($sql);
        $goods_id = $db->insert_id();
    } else {
        if ($rowGoods['deployment_num'] != $goods_deployment_num) {

            $goods_num = $goods_stock + $rowGoods['deployment_num'];
            $goods_num_stock = $goods_num - $goods_deployment_num;
        } else {
            $goods_num_stock = $goods_stock;
        }
        if ($goods_num_stock <= 0) {
            sys_msg('库存超过了总预算，请核实');
        }
        //商品表更新
        $goods_sql = "UPDATE xm_luck_goods_draw SET goods_num='{$goods_num_stock}',goods_img='{$p_list_pic}',goods_name = '{$goods_name}',goods_weight='{$goods_weight}',update_time='{$time}',deployment_num='{$goods_deployment_num}',is_prize='{$is_prize}',goods_code_luck='{$goods_code_luck}',goods_code='{$goods_code}' WHERE id={$_REQUEST['goods_id']}";
        $db->query($goods_sql);
    }
    $href[] = array('text' => '商品更新成功', 'href' => 'mid_autumn.php?act=list');
    sys_msg('商品更新成功', 0, $href);
}



/*------------------------------------------------------ */
//-- 查询分类
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query_attr') {
    $attr_id = intval($_POST['key']);
    //查出二级分类
    $attr = $db->getAll("SELECT * FROM xm_attr_val WHERE attr_id={$attr_id}");
    if (empty($attr)) {
        $result['status'] = 0;
    } else {
        $result['status'] = 1;
        $result['attr'] = $attr;
    }
    echo json_encode($result);
    die;
}
/*------------------------------------------------------ */
//-- 查询分类
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query_cate') {
    $cate_id = intval($_POST['key']);
    if (empty($cate_id)) {
        $cate = '';
    } else {
        //查出二级分类
        $cate = $db->getAll("SELECT * FROM xm_cate WHERE parent_id={$cate_id} ORDER BY cate_sort ");
    }
    if (empty($cate)) {
        $result['status'] = 0;
    } else {
        $result['status'] = 1;
        $result['cate2'] = $cate;
    }
    echo json_encode($result);
    die;
}/*------------------------------------------------------ */
//-- 显示图片
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'remove_goods') {
    check_authz_json('goods_manage');

    $goods_id = intval($_POST['id']);

    $sql = "UPDATE  xm_luck_goods_draw SET status=-1  WHERE id=$goods_id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }

}


/*------------------------------------------------------ */
//-- 显示图片
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'update_name') {
    check_authz_json('goods_manage');

    $goods_id = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $title = json_str_iconv(trim($_POST['title']));

    $sql = "UPDATE xm_product SET $key='$title' WHERE p_id=$goods_id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => 1]);
        die;
    }

}

/*------------------------------------------------------ */
//-- 修改商品的各种状态
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'update_status') {
    check_authz_json('goods_manage');

    $goods_id = intval($_POST['id']);
    $key = json_str_iconv(trim($_POST['key']));
    $status = intval($_POST['status']);

    if ($key == 'p_putaway' && $status == 2) {
        $status = 1;
    } elseif ($key == 'p_putaway' && $status == 1) {
        $status = 2;
    } else {
        $status = empty($status) ? 1 : 0;
    }
    $sql = "UPDATE xm_product SET $key=$status WHERE p_id=$goods_id";
    if ($db->query($sql)) {
        clear_cache_files();
        echo json_encode(['status' => $status]);
        die;
    }
}

/*------------------------------------------------------ */
//-- 修改商品货号
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_goods_sn') {
    check_authz_json('goods_manage');

    $goods_id = intval($_POST['id']);
    $goods_sn = json_str_iconv(trim($_POST['val']));

    /* 检查是否重复 */
    if (!$exc->is_only('goods_sn', $goods_sn, $goods_id)) {
        make_json_error($_LANG['goods_sn_exists']);
    }
    $sql = "SELECT goods_id FROM " . $ecs->table('products') . "WHERE product_sn='$goods_sn'";
    if ($db->getOne($sql)) {
        make_json_error($_LANG['goods_sn_exists']);
    }
    if ($exc->edit("goods_sn = '$goods_sn', last_update=" . time(), $goods_id)) {
        clear_cache_files();
        make_json_result(stripslashes($goods_sn));
    }
} elseif ($_REQUEST['act'] == 'check_goods_sn') {
    check_authz_json('goods_manage');
    $goods_id = intval($_REQUEST['goods_id']);
    $goods_sn = htmlspecialchars(json_str_iconv(trim($_REQUEST['goods_sn'])));

    if (!empty($goods_sn)) {
        $sql = "SELECT goods_id FROM " . $ecs->table('product') . "WHERE p_sn='$goods_sn'";
        if ($db->getOne($sql)) {
            make_json_error($_LANG['goods_sn_exists']);
        }
    }
    make_json_result('');
} elseif ($_REQUEST['act'] == 'check_products_goods_sn') {
    check_authz_json('goods_manage');

    $goods_id = intval($_REQUEST['goods_id']);
    $goods_sn = json_str_iconv(trim($_REQUEST['goods_sn']));
    $products_sn = explode('||', $goods_sn);
    if (!is_array($products_sn)) {
        make_json_result('');
    } else {
        foreach ($products_sn as $val) {
            if (empty($val)) {
                continue;
            }
            if (is_array($int_arry)) {
                if (in_array($val, $int_arry)) {
                    make_json_error($val . $_LANG['goods_sn_exists']);
                }
            }
            $int_arry[] = $val;
            if (!$exc->is_only('goods_sn', $val, '0')) {
                make_json_error($val . $_LANG['goods_sn_exists']);
            }
            $sql = "SELECT goods_id FROM " . $ecs->table('products') . "WHERE product_sn='$val'";
            if ($db->getOne($sql)) {
                make_json_error($val . $_LANG['goods_sn_exists']);
            }
        }
    }
    /* 检查是否重复 */
    make_json_result('');
}


/*------------------------------------------------------ */
//-- 修改商品排序
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'edit_sort_order') {
    check_authz_json('goods_manage');

    $goods_id = intval($_POST['id']);
    $sort_order = intval($_POST['val']);

    if ($exc->edit("sort_order = '$sort_order', last_update=" . time(), $goods_id)) {
        clear_cache_files();
        make_json_result($sort_order);
    }
} elseif ($_REQUEST['act'] == 'show_image') {


    $img_url = \maqu\filesystem\FileSystemManager::getAdapter()->getFullPath(DATA_DIR . '/' . $_GET['img_url']);


    $smarty->assign('img_url', $img_url);
    $smarty->display('goods_show_image.htm');
}

?>
