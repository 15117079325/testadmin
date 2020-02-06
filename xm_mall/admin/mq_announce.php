<?php
/**
 * Created by PhpStorm.
 * User: liuhuaxia
 * Date: 2017/8/11
 * Time: 15:20
 */
/**
 * ECSHOP 管理中心公告处理程序文件
 * ============================================================================
 * 版权所有 2005-2011 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 */

define('IN_ECS', true);
define('ANNOUNCE_ID', '公告id');

require(dirname(__FILE__) . '/includes/init.php');

/*初始化数据交换对象 */
$exc   = new exchange($ecs->table("mq_announce"), $db, 'id', 'title');

/*------------------------------------------------------ */
//-- 文章列表
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list') {
    /* 取得过滤条件 */
    $filter = array();
    $smarty->assign('ur_here', $_LANG['list_announce']);
    $smarty->assign('action_link', array('text' => $_LANG['add_announce'], 'href' => 'mq_announce.php?act=add'));
    $smarty->assign('full_page', 1);
    $smarty->assign('filter', $filter);

    $announce_list = get_announceslist();

    $smarty->assign('announce_list', $announce_list['arr']);
    $smarty->assign('filter', $announce_list['filter']);
    $smarty->assign('record_count', $announce_list['record_count']);
    $smarty->assign('page_count', $announce_list['page_count']);

    $sort_flag = sort_flag($announce_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    assign_query_info();
    $smarty->display('announce_list.htm');
}

/*------------------------------------------------------ */
//-- 翻页，排序
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'query')
{
    check_authz_json('list_announce');

    $announce_list = get_announceslist();

    $smarty->assign('announce_list',    $announce_list['arr']);
    $smarty->assign('filter',          $announce_list['filter']);
    $smarty->assign('record_count',    $announce_list['record_count']);
    $smarty->assign('page_count',      $announce_list['page_count']);

    $sort_flag  = sort_flag($announce_list['filter']);
    $smarty->assign($sort_flag['tag'], $sort_flag['img']);

    make_json_result($smarty->fetch('announce_list.htm'), '',
        array('filter' => $announce_list['filter'], 'page_count' => $announce_list['page_count']));
}

/*------------------------------------------------------ */
//-- 添加文章
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'add')
{
    /* 权限判断 */
    admin_priv('add_announce');

    /* 创建 html editor */
    $toolbars = "toolbars: [[  
        'link',
        'unlink',  
        '|',  
        'forecolor',  
        'backcolor',   
        'fontfamily',   
        'fontsize',          
        '|',        
        'bold',
        'italic', 
        'underline', 
        'strikethrough', 
        '|',  
        'formatmatch', 
        'removeformat',  
        '|',  
        'insertorderedlist',   
        'insertunorderedlist',  
        '|',  
        'inserttable',  
        'paragraph',   
        'imagecenter',  
        'attachment', 
          
        '|',  
        'justifyleft', 
        'justifycenter',   
        'horizontal',   
        '|',  
        'blockquote',  
        'insertcode',   
          
        '|',  
        'source',  
        'preview',  
        'fullscreen',
        ]] ";
    create_html_editor('content','',$toolbars);

    /*初始化*/
    $announce = array();
    $announce['is_open'] = 1;

    $smarty->assign('announce',     $announce);
    $smarty->assign('ur_here',     "添加新公告");
    $smarty->assign('action_link', array('text' => $_LANG['list_announce'], 'href' => 'mq_announce.php?act=list'));
    $smarty->assign('form_action', 'insert');

    assign_query_info();
    $smarty->display('announce_info.htm');
}

/*------------------------------------------------------ */
//-- 添加文章
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'insert')
{
    /* 权限判断 */
    admin_priv('announce_manage');

    if($_POST['content']){

        $pattern = array(

            '/ /',//半角下空格

            '/　/',//全角下空格

            '/\r\n/',//window 下换行符

            '/\n/',//Linux && Unix 下换行符

        );

        $replace = array('&nbsp;','&nbsp;','<br />','<br />');

       $content =  preg_replace($pattern, $replace, $_POST['content']);
    }else{
        $content='';
    }
    /*插入数据*/
    $add_time = time();
    $sql = "INSERT INTO ".$ecs->table('mq_announce')."(title, content, is_open, author, add_time) ".
        "VALUES ('$_POST[title]', '".$content."', '$_POST[is_open]', '$_POST[author]','$add_time')";
    $db->query($sql);
    $announce_id=$db->insert_id();
    admin_log($_POST['title'],'add','announce',ANNOUNCE_ID.$announce_id);

    clear_cache_files(); // 清除相关的缓存文件
    $link[0]['text'] = $_LANG['continue_add'];
    $link[0]['href'] = 'mq_announce.php?act=list';
    sys_msg($_LANG['articleadd_succeed'],0, $link);
}

/*------------------------------------------------------ */
//-- 切换是否显示
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'toggle_show')
{
    check_authz_json('announce_manage');

    $id     = intval($_POST['id']);
    $val    = intval($_POST['val']);

    $exc->edit("is_open = '$val'", $id);
    clear_cache_files();

    make_json_result($val);
}



/*------------------------------------------------------ */
//-- 删除公告
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'remove')
{
    check_authz_json('announce_manage');

    $id = intval($_GET['id']);

    /* 删除公告 */
    $sql = "delete FROM " . $ecs->table('mq_announce') . " WHERE id = '$id'";
    $res = $db->query($sql);
    admin_log($_POST['title'],'remove','announce',ANNOUNCE_ID.$id);
    $url = 'mq_announce.php?act=query&' . str_replace('act=remove', '', $_SERVER['QUERY_STRING']);
    ecs_header("Location: $url\n");

    exit;
}

/* 获得文章列表 */
function get_announceslist()
{
    $result = get_filter();

    if ($result === false)
    {
        $filter = array();
        $filter['keyword']    = empty($_REQUEST['keyword']) ? '' : trim($_REQUEST['keyword']);
        if (isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keyword'] = json_str_iconv($filter['keyword']);
        }
        $filter['sort_by']    = empty($_REQUEST['sort_by']) ? 'a.id' : trim($_REQUEST['sort_by']);
        $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC' : trim($_REQUEST['sort_order']);

        $where = '';
        if (!empty($filter['keyword']))
        {
            $where = " AND a.title LIKE '%" . mysql_like_quote($filter['keyword']) . "%'";
        }

        /* 公告总数 */
        $sql = 'SELECT COUNT(*) FROM ' .$GLOBALS['ecs']->table('mq_announce'). ' AS a '.
            'WHERE 1 ' .$where;
        $filter['record_count'] = $GLOBALS['db']->getOne($sql);

        $filter = page_and_size($filter);

        /* 获取文章数据 */
        $sql = 'SELECT a.* '.
            'FROM ' .$GLOBALS['ecs']->table('mq_announce'). ' AS a '.
            'WHERE 1 ' .$where. ' ORDER by '.$filter['sort_by'].' '.$filter['sort_order'];

        $filter['keyword'] = stripslashes($filter['keyword']);
        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $arr = array();
    $res = $GLOBALS['db']->selectLimit($sql, $filter['page_size'], $filter['start']);

    while ($rows = $GLOBALS['db']->fetchRow($res))
    {
        $rows['add_time_fmt'] = local_date($GLOBALS['_CFG']['time_format'], $rows['add_time']);

        $arr[] = $rows;
    }

    return array('arr' => $arr, 'filter' => $filter,
        'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);
}
