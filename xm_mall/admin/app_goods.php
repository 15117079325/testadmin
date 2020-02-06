<?php


define('IN_ECS', true);
define('GOODS_ID', '商品id');

require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . '/' . ADMIN_PATH . '/includes/lib_goods.php');
require_once(ROOT_PATH . '/' . ADMIN_PATH . "/nusoap/nusoap.php");   //代码增加  By  www.68ecshop.com
include_once(ROOT_PATH . '/includes/cls_image.php');
$image = new cls_image($_CFG['bgcolor']);
$exc = new exchange($ecs->table('goods'), $db, 'goods_id', 'goods_name');

$exc_extra = new exchange($ecs->table('mq_goods_extra'), $db, 'goods_id', 'mq_goods_extra');

admin_priv('goods_manage');
/* 获取广告数据列表 */
function app_goods_list()
{

    $filter = array();
    $goods_type = empty($_REQUEST['goods_type']) ? 0 : $_REQUEST['goods_type'];
    $goods_name = empty($_REQUEST['goods_name']) ? '' : $_REQUEST['goods_name'];
    $status = empty($_REQUEST['status']) ? 0 : intval($_REQUEST['status']);
    $putaway = empty($_REQUEST['putaway']) ? 0 : intval($_REQUEST['putaway']);
    $sort = empty($_REQUEST['sort']) ? 0 : intval($_REQUEST['sort']);

    $filter['status'] = $status;
    $filter['goods_type'] = $goods_type;
    $filter['goods_name'] = $goods_name;
    $filter['putaway'] = $putaway;
    $filter['sort'] = $sort;

    $where = "  WHERE p.p_delete=1 ";
    if ($goods_type) {
        $where .= " AND p.p_type={$goods_type} ";
    }
    if ($goods_name) {
        $where .= " AND p.p_title LIKE '%{$goods_name}%' ";
    }
    if ($status == 1) {
        $where .= " AND p.p_boutique=1 ";
    } elseif ($status == 2) {
        $where .= " AND p.p_recommend=1 ";
    }
    if ($putaway) {
        $where .= " AND p.p_putaway={$putaway} ";
    }

    if($sort == 1) {
        //排序降序
        $where .= " ORDER BY p_sort DESC";
    } else if($sort == 2) {
        //库存降序
        $where .= " ORDER BY p_stock DESC";
    } else if($sort == 3) {
        //销量降序
        $where .= " ORDER BY p_sold_num DESC";
    }

    /* 获得总记录数据 */
    $sql = 'SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('product') . ' p ' . $where;
    $filter['record_count'] = $GLOBALS['db']->getOne($sql);

    $filter = page_and_size($filter);


    /* 获得广告数据 */
    $arr = array();
    $sql = 'SELECT p.* ' .
        'FROM ' . $GLOBALS['ecs']->table('product') . 'AS p  ' . $where;

    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res)) {

        $arr[] = $rows;
    }

    return array('goods_list' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}

/*------------------------------------------------------ */
//-- 商品列表，商品回收站
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list') {
    $smarty = $GLOBALS['smarty'];
    $_LANG = $GLOBALS['_LANG'];
    $smarty->assign('lang', $_LANG);
    $goods_list = app_goods_list();
    $smarty->assign('action_link', array('text' => '添加商品', 'href' => 'app_goods.php?act=add'));
    $smarty->assign('goods_list', $goods_list['goods_list']);
    $smarty->assign('filter', $goods_list['filter']);
    $smarty->assign('record_count', $goods_list['record_count']);
    $smarty->assign('page_count', $goods_list['page_count']);
    $smarty->assign('full_page', 1);
    /* 显示商品列表页面 */
    assign_query_info();
    $smarty->display('app_goods_list.htm');
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
    make_json_result($smarty->fetch('app_goods_list.htm'), '',
        array('filter' => $goods_list['filter'], 'page_count' => $goods_list['page_count']));

}
/*------------------------------------------------------ */
//-- 添加新商品 编辑商品
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'add' || $_REQUEST['act'] == 'edit') {

    include_once(ROOT_PATH . '/includes/Pinyin.php');
    $is_add = $_REQUEST['act'] == 'add'; // 添加还是编辑的标识
    $p_id = intval($_GET['p_id']);
    //查出一级分类
    $cate = $db->getAll("SELECT * FROM xm_cate WHERE parent_id=0 AND type NOT IN (1,2,3) ORDER BY cate_sort ");
    $smarty->assign('cate', $cate);
    //查出属性
    $attr = $db->getAll("SELECT * FROM xm_attributes");
    foreach ($attr as $k => $v) {
        if ($v['attr_title'] == '颜色') {
            $attr_color_id = $v['attr_id'];
            break;
        }

    }
    $attrss = json_encode($attr);
    $smarty->assign('attr', $attrss);
    /* 取得商品信息 */
    if ($is_add) {
        $goods = array(
            'goods_id' => 0,
            'goods_desc' => '',
            'p_cash' => 0,
            'p_balance' => 0,
        );
        /* 图片列表 */
        $img_list = array();
    } else {
        //查出商品信息
        $sql = "SELECT p.* FROM xm_product p WHERE p.p_id={$p_id}";
        $goods = $db->getRow($sql);
        if(empty($goods['cate_id'])) {
            $child_cate = '';
        } else {
          //根据一级分类查出二级分类
          $child_cate = $db->getAll("SELECT * FROM xm_cate WHERE parent_id={$goods['cate_id']} ORDER BY cate_sort ");

        }
        $smarty->assign('child_cate', $child_cate);
        //查出颜色对应的属性值
        $attr_val_titles = $db->getAll("SELECT * FROM xm_attr_val WHERE attr_id={$attr_color_id}");
        foreach ($attr_val_titles as $k => $v) {
            $attr_val_title[$v['attr_val_id']] = $v['attr_val_name'];
        }
        //商品的轮播图
        $img_arr = explode(',', $goods['p_detail_pic']);
        $img_str = '';
        foreach ($img_arr as $k => $v) {
            if ($k == 0) {
                $img_str .= '<tr><td class="label"><span id="add_good_thumb">[+]</span>&nbsp;&nbsp;详情轮播图</td><td>
                         <input type="file" name="goods_thumb[' . $k . ']" size="35" /><input type="hidden" name="goods_thumb_hidden[' . $k . ']" size="35" value="' . $v . '" /><a href="app_goods.php?act=show_image&img_url=' . $v . '" target="_blank">
                         <img src="images/yes.gif" border="0" /></a></td></tr>';
            } else {
                $img_str .= '<tr><td class="label"><span onclick="remove_good_thumb(this)">[-]</span>&nbsp;&nbsp;详情轮播图</td><td>
                             <input type="file" name="goods_thumb[' . $k . ']" size="35" /><input type="hidden" name="goods_thumb_hidden[' . $k . ']" size="35" value="' . $v . '" />
                              <a href="app_goods.php?act=show_image&img_url=' . $v . '" target="_blank">
                              <img src="images/yes.gif" border="0" /></a></td></tr>';
            }
        }
        $smarty->assign('img_str', $img_str);
        $smarty->assign('is_size', $goods['is_size']);
        //针对多规格
        if ($goods['is_size']) {
            $attr_color_img = [];
            $sql = "SELECT * FROM xm_size WHERE p_id={$goods['p_id']}";
            $goods_size = $db->getAll($sql);
            $totalSize = [];
            foreach ($goods_size as $k => $v) {
                $arr1 = json_decode($v['size_portoties'], true);
                foreach ($arr1 as $kk => $vv) {
                    if ($attr_color_id == $kk) {
                        $attr_color_img[$vv][] = $v['size_id'];
                    }
                    $attr_ids[] = $kk;
                    $attr_val_ids[$kk][] = $vv;
                }
                $size_img[$v['size_id']] = empty($v['size_img']) ? '' : $v['size_img'];
                $size_goods_price[implode('_', $arr1)] = $v['size_cash'];

                $size_balance_price[implode('_', $arr1)] = $v['size_balance'];

                $size_stock[implode('_', $arr1)] = $v['size_stock'];
                $size_id[implode('_', $arr1)] = $v['size_id'];

            }
            $size_goods_price = json_encode($size_goods_price);
            $size_stock = json_encode($size_stock);
            $size_id = json_encode($size_id);
            $size_balance_price = json_encode($size_balance_price);
            $smarty->assign('size_id', $size_id);
            $smarty->assign('size_stock', $size_stock);
            $smarty->assign('size_goods_price', $size_goods_price);
            $smarty->assign('size_balance_price', $size_balance_price);
            $attr_ids = array_unique($attr_ids);
            foreach ($attr_ids as $k => $v) {
                $attr_val_ids[$v] = array_unique($attr_val_ids[$v]);
            }
            $attr_str = '';
            //针对多规格图片
            if (!empty($attr_color_img)) {
                foreach ($attr_color_img as $k => $v) {
                    $attr_img = implode(',', $v);
                    $attr_color_imgs[] = ['color_name' => $attr_val_title[$k], 'color_ids' => $attr_img, 'color_img' => $size_img[$v[0]]];
                }
            }
            $smarty->assign('attr_color_imgs', $attr_color_imgs);
            foreach ($attr_ids as $k => $v) {
                //查出属性值
                $attr_val = $db->getAll("SELECT * FROM xm_attr_val WHERE attr_id={$v}");
                $attr_str .= '<tr><td class="control-group lv1" style="padding-left: 300px;"><div class="controls"><select name="lv1[]" class="select" > <option value="-1">请选择</option>';
                foreach ($attr as $kk => $vv) {
                    $attr_str .= "<option value='{$vv['attr_id']}' ";
                    if ($v == $vv['attr_id']) {
                        $attr_str .= 'selected';
                    } else {
                        $attr_str .= '';
                    }

                    $attr_str .= ">{$vv['attr_title']}</option>";

                }

                $attr_str .= '</select><input type="button" value="+" class="button add_lv2" /><input type="button" value="-" class="button remove_lv1" /></div>
                             <div class="controls lv2s"></div>';
                foreach ($attr_val_ids[$v] as $val_key => $val_v) {
                    $attr_str .= '<span><div style="margin-top: 5px;"><select name="' . $v . 'lv2[]" class="select2"  ><option value="-1">请选择</option>';
                    foreach ($attr_val as $attr_val_key => $attr_val_v) {
                        $attr_str .= "<option value='{$attr_val_v['attr_val_id']}'";
                        if ($attr_val_v['attr_val_id'] == $val_v) {
                            $attr_str .= 'selected';
                        } else {
                            $attr_str .= '';
                        }
                        $attr_str .= ">{$attr_val_v['attr_val_name']}</option>";
                    }
                    $attr_str .= '<input type="button" value="-" class="button remove_lv2" /></div></span>';
                }
                $attr_str .= '</td></tr>';
            }

            $smarty->assign('attr_str', $attr_str);
            $smarty->assign('is_size', $goods['is_size']);

        }
    }

    /* 创建 html editor */
    create_html_editor('goods_desc', htmlspecialchars($goods['p_description'])); /* 修改 by www.68ecshop.com 百度编辑器 */
    $smarty->assign('form_act', $is_add ? 'insert' : ($_REQUEST['act'] == 'edit' ? 'update' : 'insert'));
    $smarty->assign('goods', $goods);
    /* 显示商品信息页面 */
    assign_query_info();

    $smarty->display('app_goods_info.htm');
}

/*------------------------------------------------------ */
//-- 插入商品 更新商品
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'insert' || $_REQUEST['act'] == 'update') {

    $p_title = trim($_POST['goods_name']);
    $p_describe = trim($_POST['goods_describe']);
    $p_sn = trim($_POST['goods_sn']);
    $cate_id = intval($_POST['cate1']);
    $child_cate_id = intval($_POST['cate2']);
    $goods_id = intval($_POST['goods_id']);
    $p_type = intval($_POST['goods_type']);
    $p_description = $_POST['goods_desc'];
    $attrs = $_POST['lv1'];
    $hidden_name = $_POST['hidden_name'];
    $p_stock = $_POST['goods_stock'];
    $market_price = intval($_POST['market_price']);
    $max_num = $_POST['max_num'];
    //$_FILES['video_url']['tmp_name'];
    //如果商品描述不存在
    if(!isset($p_describe)) {
        $p_describe = 'NULL';
    }

    //判断是添加还是更新
    $is_add = $_REQUEST['act'] == 'insert' ? true : false;

//    if (empty($p_title)) {
//        sys_msg("商品名称不能为空");
//    }
//    if (empty($p_description)) {
//        sys_msg("商品描述不能为空");
//    }
//    if (empty($cate_id)) {
//        sys_msg("请选择一级分类");
//    }
//    if (empty($child_cate_id)) {
//        sys_msg("请及时补充对应的二级分类");
//    }
//    if (empty($p_type)) {
//        sys_msg("请选择商城类型");
//    }
    //查出比列
    $cash_balance = $db->getOne("SELECT `value` FROM xm_master_config WHERE code='cash_balance'");
    if ($p_type == 4) {
        $p_cash = $_POST['y_cash'];
        $p_balance = 0;
    } elseif ($p_type == 1) {
        $p_cash = $_POST['b_cash'];
        $p_balance = $_POST['b_balance'];
        if ($p_cash < 0) {
            sys_msg("金额格式不正确");
        }
        $arr = explode(':', $cash_balance);
        if (($p_cash / $p_balance) != ($arr[0] / $arr[1])) {
            sys_msg("请按{$cash_balance}比列填写");
        }
    } else {
        $p_cash = $_POST['p_cash'];
        $p_balance = $_POST['p_balance'];
        if ($p_cash < 0) {
            sys_msg("金额格式不正确");
        }
    }

    /* 检查货号是否重复 */
    if ($is_add) {
        if ($p_sn) {
            $sql = "SELECT COUNT(*) FROM " . $ecs->table('product') .
                " WHERE p_sn = '$p_sn'";
            if ($db->getOne($sql) > 0) {
                sys_msg('商品号已存在', 1, array(), false);
            }
        }
    } else {
        $p_sn = $_POST['goods_sn'];
    }

    //添加商品的时候传图片判断
    if ($is_add) {
        if (empty($_FILES['goods_img']['tmp_name'])) {
            sys_msg("请上传商品图片");
        }
        foreach ($_FILES['goods_thumb']['tmp_name'] as $k => $v) {
            if (empty($v)) {
                sys_msg("请上传详情轮播图");
            }
        }
    }

    // 如果上传了商品图片，相应处理
    if (($_FILES['goods_img']['tmp_name'] != '')) {
        if ($_REQUEST['goods_id'] > 0) {
            /* 删除原来的图片文件 */
            $sql = "SELECT p_list_pic " .
                " FROM " . $ecs->table('product') .
                " WHERE p_id = '$_REQUEST[goods_id]'";
            $row = $db->getRow($sql);
            if ($row['p_list_pic'] != '' && is_file('../' . $row['p_list_pic'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['p_list_pic']);
            }

        }
        $original_img = 'goods/' . basename($image->upload_image($_FILES['goods_img'], 'goods')); // 原始图片

        if ($original_img === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $p_list_pic = $original_img;   // 商品图片
//        $original_video = '';   // 商品视频

    } else {
        if(empty($_POST['goods_img_hidden'])){
            $p_list_pic = '';
        }else {
            $p_list_pic = $_POST['goods_img_hidden'];
        }
    }

    // 如果上传了商品视频，相应处理
    if (($_FILES['video_url']['tmp_name'] != '')) {
        if ($_REQUEST['goods_id'] > 0) {
            /* 删除原来的视频文件 */
            $sql = "SELECT video_url " .
                " FROM " . $ecs->table('product') .
                " WHERE p_id = '$_REQUEST[goods_id]'";
            $row = $db->getRow($sql);
            if ($row['video_url'] != '' && is_file('../' . $row['video_url'])) {

                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($row['video_url']);
            }

        }
        $original_video = 'video/' . basename($image->upload_image($_FILES['video_url'], 'video')); // 原始视频

        if ($original_video === false) {
            sys_msg($image->error_msg(), 1, array(), false);
        }
        $original_video = $original_video;   // 商品视频
//        $p_list_pic = '';   // 商品图片

    } else {
        if(empty($_POST['video_url_hidden'])) {
            $original_video = '';
        } else {
            $original_video = $_POST['video_url_hidden'];
        }
    }

    //判断是否需要视频
    $is_video = $_POST['is_video'];
    if(empty($is_video)) {
        $is_video = 1;
    }

    //是否有规格
    if (empty($_POST['checkbox'])) {
        $is_size = 0;
    } else {
        $is_size = 1;
        if (empty($attrs)) {
            sys_msg("请刷新规格项目表");
        }

        $attr_ids = '';
        foreach ($attrs as $k => $v) {
            $attr_ids .= $v . ',';
        }
        $attr_ids = '(' . rtrim($attr_ids, ',') . ')';
        $attr_name = $db->getAll("SELECT * FROM xm_attributes WHERE attr_id IN {$attr_ids}");
        $temp_key = array_column($attr_name, 'attr_id');
        $temp_name = array_column($attr_name, 'attr_title');
        $attr_name = array_combine($temp_key, $temp_name);
        //查出属性值的名字
        $attr_val_name = $db->getAll("SELECT * FROM xm_attr_val WHERE attr_id IN {$attr_ids}");
        $temp_key = array_column($attr_val_name, 'attr_val_id');
        $temp_name = array_column($attr_val_name, 'attr_val_name');
        $attr_val_name = array_combine($temp_key, $temp_name);
        if (empty($hidden_name)) {
            sys_msg("请刷新规格项目表");
        }
        foreach ($hidden_name as $k => $v) {
            $key = $v . '_t_price';
            $keyy = $v . '_total';
            $keyyy = $v . '_t_balance';

            if (empty($_POST[$key])) {
                sys_msg("规格价格不能为空");
            }
            $arr = explode('_', $v);

            $str = '{';
            $str2 = '';
            foreach ($attrs as $kk => $vv) {
                $str .= '"' . $vv . '":"' . $arr[$kk] . '",';
                $str2 .= $attr_name[$vv] . ':' . $attr_val_name[$arr[$kk]] . ' ';
            }
            $str = rtrim($str, ',') . '}';
            $str2 = rtrim($str2, ',');
            $size_portoties[] = $str;

            $size_title[] = $str2;
            $size_t_score[] = $_POST[$key];
            $size_t_balance[] = $_POST[$keyyy];
            $size_stock[] = intval($_POST[$keyy]);
        }
    }
    if ($is_size == 1 && $p_type != 3) {
        sys_msg("品牌专区商品可设置多规格");
    }
    if ($is_add) {
        $lenth = count($_FILES['goods_thumb']['tmp_name']);
        $str = '';
        for ($i = 0; $i < $lenth; $i++) {
            if (empty($_FILES['goods_thumb']['tmp_name'][$i])) {
                continue;
            }
            $image_arr = array(
                'name' => $_FILES['goods_thumb']['name'][$i],
                'type' => $_FILES['goods_thumb']['type'][$i],
                'tmp_name' => $_FILES['goods_thumb']['tmp_name'][$i],
                'error' => $_FILES['goods_thumb']['error'][$i],
                'size' => $_FILES['goods_thumb']['size'][$i],
            );
            $str .= 'goods/' . basename($image->upload_image($image_arr, 'goods')) . ',';

            unset($image_arr);
        }
    } else {
        $imgs = $_POST['goods_thumb_hidden'];
        $str = '';
        if (count($_FILES['goods_thumb']['tmp_name']) > count($imgs)) {
            foreach ($_FILES['goods_thumb']['tmp_name'] as $k => $v) {
                if (!isset($imgs[$k])) {
                    $imgs[$k] = '';
                }
            }
        }
        foreach ($imgs as $k => $v) {
            if (empty($_FILES['goods_thumb']['tmp_name'][$k])) {
                $str .= $v . ',';
            } else {
                $image_arr = array(
                    'name' => $_FILES['goods_thumb']['name'][$k],
                    'type' => $_FILES['goods_thumb']['type'][$k],
                    'tmp_name' => $_FILES['goods_thumb']['tmp_name'][$k],
                    'error' => $_FILES['goods_thumb']['error'][$k],
                    'size' => $_FILES['goods_thumb']['size'][$k],
                );
                $str .= 'goods/' . basename($image->upload_image($image_arr, 'goods')) . ',';
            }
        }
    }

    $str = rtrim($str, ',');
    $p_detail_pic = $str;

    $time = time();
    /* 入库 */
    if ($is_add) {
        $sql = "INSERT INTO xm_product (`p_title`,`p_list_pic`,`p_detail_pic`,`p_sn`,`cate_id`,`child_cate_id`,`market_price`,
                `p_cash`,`p_balance`,`p_type`,`p_description`,`p_gmt_putaway`,`attr_ids_group`,`is_size`,`p_stock`,`p_describe`,`max_num`,`video_url`,`type_c`)
                VALUES ('{$p_title}','$p_list_pic','{$p_detail_pic}','','{$cate_id}','{$child_cate_id}','{$market_price}','{$p_cash}','{$p_balance}','{$p_type}','{$p_description}',{$time},'{$attr_ids_group}','{$is_size}','{$p_stock}','{$p_describe}','{$max_num}','{$original_video}','{$is_video}')";
        $db->query($sql);
        $goods_id = $db->insert_id();

        //更改商品编号
        $p_sn = $GLOBALS['_CFG']['sn_prefix'] . str_repeat('0', 6 - strlen($goods_id)) . $goods_id;
        $db->query("UPDATE xm_product SET p_sn='{$p_sn} 'WHERE p_id={$goods_id}");
        //针对多规格进行入库
        $val_sql = " INSERT INTO xm_size (`p_id`,`size_portoties`,`size_title`,`size_stock`,`size_cash`,`size_balance`) VALUES ";
        if ($is_size == 1) {
            foreach ($hidden_name as $k => $v) {
                $val_sql .= "({$goods_id},'$size_portoties[$k]','$size_title[$k]',$size_stock[$k],$size_t_score[$k],$size_t_balance[$k]),";
            }
            $val_sql = rtrim($val_sql, ',');
            $db->query($val_sql);
        }

    } else {
        //商品表更新
        $goods_sql = "UPDATE xm_product SET p_title='{$p_title}',p_list_pic='{$p_list_pic}',
                      p_detail_pic = '{$p_detail_pic}',p_sn='{$p_sn}',cate_id={$cate_id},
                      child_cate_id={$child_cate_id},market_price={$market_price},p_cash={$p_cash},p_balance={$p_balance},p_type={$p_type},p_description='{$p_description}',
                      is_size={$is_size},p_stock={$p_stock},p_describe='{$p_describe}',max_num='{$max_num}',video_url='{$original_video}',type_c='{$is_video}' WHERE p_id={$goods_id}";
        $db->query($goods_sql);
        //针对多规格更新
        if ($is_size == 1) {
            //查出所有的size_id
            $size_ids = $db->getAll("SELECT size_id FROM xm_size WHERE p_id={$goods_id}");
            $size_ids = array_column($size_ids, 'size_id');
            //多规格入库
            foreach ($hidden_name as $k => $v) {
                $key = $v . "_size_id";
                if (isset($_POST[$key]) && $_POST[$key] != 'undefined' && !empty($_POST[$key])) {
                    $size_sql = "UPDATE xm_size SET size_portoties='{$size_portoties[$k]}',size_title='{$size_title[$k]}',
                                size_stock={$size_stock[$k]},size_cash={$size_t_score[$k]},size_balance={$size_t_balance[$k]} WHERE size_id={$_POST[$key]}";
                    $keyy = array_search($_POST[$key], $size_ids);
                    unset($size_ids[$keyy]);
                } else {
                    $size_sql = "INSERT INTO xm_size (`p_id`,`size_portoties`,`size_title`,`size_stock`,`size_cash`,`size_balance`) VALUES
                                 ({$goods_id},'$size_portoties[$k]','$size_title[$k]',$size_stock[$k],$size_t_score[$k],$size_t_balance[$k])";
                }
                $db->query($size_sql);
            }


            if ($size_ids && is_array($size_ids)) {
                $size_str = implode(',', $size_ids);
                $db->query("DELETE FROM xm_size WHERE size_id IN ($size_str)");
            }

        }


        //针对是否有规格图片
        $size_lenth = count($_FILES['color_img']['tmp_name']);
        $size_img_ids = [];
        for ($i = 0; $i < $size_lenth; $i++) {
            if (empty($_FILES['color_img']['tmp_name'][$i])) {
                continue;
            }
            $size_image_arr = array(
                'name' => $_FILES['color_img']['name'][$i],
                'type' => $_FILES['color_img']['type'][$i],
                'tmp_name' => $_FILES['color_img']['tmp_name'][$i],
                'error' => $_FILES['color_img']['error'][$i],
                'size' => $_FILES['color_img']['size'][$i],
            );
            $img_path_id = '(' . $_POST['color_img_ids'][$i] . ')';
            $img_path = 'goods/' . basename($image->upload_image($size_image_arr, 'goods'));
            $size_img_sql = "UPDATE xm_size SET size_img='{$img_path}' WHERE size_id IN {$img_path_id}";
            $db->query($size_img_sql);

            if ($_POST['color_img_hidden'][$i] != '') {
                \maqu\filesystem\FileSystemManager::getAdapter()->deleteFile($_POST['color_img_hidden'][$i]);
            }
            unset($size_image_arr);
        }
    }

    /* 商品编号 */
    $goods_id = $is_insert ? $db->insert_id() : $_REQUEST['goods_id'];

    //end
    /* 记录日志 */
    if ($is_insert) {
        admin_log($_POST['goods_name'], 'add', $p_type, GOODS_ID . $goods_id);
    } else {
        admin_log($_POST['goods_name'], 'edit', $p_type, GOODS_ID . $goods_id);
    }
    $href[] = array('text' => '商品更新成功', 'href' => 'app_goods.php?act=list');
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
    if(empty($cate_id)) {
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

    $sql = "UPDATE  xm_product SET p_delete=3  WHERE p_id=$goods_id";
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