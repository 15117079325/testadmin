<?php

/**
 * 积分转账排名榜
 * @author yang
 * @company maqu
 * @create_at 2017-11-20
 */

define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');

$action = isset($_REQUEST['act']) ? trim($_REQUEST['act']) : 'list';
/* 路由 */
$function_name = 'action_' . $action;
if(! function_exists($function_name))
{
    $function_name = "action_list";
}
call_user_func($function_name);

/**
 * 积分转账排名榜
 */
function action_list()
{
    $smarty = $GLOBALS['smarty'];

     /* 检查权限 */
    admin_priv('mq_account_transfer_stats');

    /* 查询 */
    $result = getList();

    /* 模板赋值 */
    $smarty->assign('ur_here', "积分转账排行榜 "); // 当前导航

    $smarty->assign('full_page',        1); // 翻页参数

    $smarty->assign('datalist',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 显示模板 */
    assign_query_info();
    $smarty->display('mq_account_transfer_stats/mq_account_transfer_stats_list.htm');
}

/**
 * 积分转账排名榜查询
 */
function action_query()
{
    $smarty = $GLOBALS['smarty'];

    admin_priv('mq_account_transfer_stats');

    $result = getList();

    $smarty->assign('datalist',    $result['result']);
    $smarty->assign('filter',       $result['filter']);
    $smarty->assign('record_count', $result['record_count']);
    $smarty->assign('page_count',   $result['page_count']);

    /* 排序标记 */
    $sort_flag  = sort_flag($result['filter']);

    make_json_result($smarty->fetch('mq_account_transfer_stats/mq_account_transfer_stats_list.htm'), '',
        array('filter' => $result['filter'], 'page_count' => $result['page_count']));
}

/**
 *  H单回购排名榜
 *
 * @access  public
 * @param
 *
 * @return void
 */
function getList()
{
    $result = get_filter();
    if ($result === false)
    {
        //
        $inout_type = empty($_REQUEST['inout_type']) ? 'out' : trim($_REQUEST['inout_type']);
        $transfer_type = empty($_REQUEST['transfer_type']) ? 'cash' : trim($_REQUEST['transfer_type']);
        if(!in_array($transfer_type,['cash','consume'])){
            $transfer_type = 'cash';
        }

        $year = local_date('Y',gmtime());
        $month = local_date('m',gmtime());
        $allday = local_date('t',gmtime());
        $start_date = local_strtotime($year . '-' . $month . '-1');
        $end_date = local_strtotime($year . '-' . $month. '-' . $allday)+86399;

        $starttime =empty($_REQUEST['start_time']) ? local_date('Y-m-d',$start_date) : trim($_REQUEST['start_time']);
        $endtime =empty($_REQUEST['end_time']) ?  local_date('Y-m-d',$end_date) : trim($_REQUEST['end_time']);
        $keywords = empty($_REQUEST['keywords']) ? '' : trim($_REQUEST['keywords']);

        $filter = array();

        if(isset($_REQUEST['is_ajax']) && $_REQUEST['is_ajax'] == 1)
        {
            $filter['keywords'] = json_str_iconv($filter['keywords']);
        }

//        /* 过滤信息 */
        $where = '1 = 1 ';//审核通过
        $filter['keywords'] =$keywords;

        //用户名
        if($filter['keywords'])
        {
            $where .= " AND ( u.user_name LIKE '%" . mysql_like_quote($filter['keywords']) . "%' or u.email like  '%" . mysql_like_quote($filter['keywords']) . "%' or u.mobile_phone like  '%" . mysql_like_quote($filter['keywords']) . "%' ) ";
        }
        $filter['transfer_type'] = $transfer_type;
        $filter['inout_type'] = $inout_type;

        $filter['start_time'] = $starttime;
        $filter['end_time'] = $endtime;
        $start_date = local_strtotime($starttime);
        $end_date = local_strtotime($endtime)+24*60*60;

        /* 分页大小 */
        $filter['page'] = empty($_REQUEST['page']) || (intval($_REQUEST['page']) <= 0) ? 1 : intval($_REQUEST['page']);
        if (isset($_REQUEST['page_size']) && intval($_REQUEST['page_size']) > 0) {
            $filter['page_size'] = intval($_REQUEST['page_size']);
        } elseif (isset($_COOKIE['ECSCP']['page_size']) && intval($_COOKIE['ECSCP']['page_size']) > 0) {
            $filter['page_size'] = intval($_COOKIE['ECSCP']['page_size']);
        } else {
            $filter['page_size'] = 15;
        }

        /* 记录总数 */
        $sql= '';
        if($inout_type=='out'){
            $sql = <<<EOF
            SELECT
                COUNT(*)
            FROM
                (
                    SELECT
                        tl.user_id
                    FROM
                        %s tl
                    WHERE
                        tl.transfer_type = '%s'
                    AND tl.create_at BETWEEN %u
                    AND %u
                    GROUP BY
                        tl.user_id
                ) sub
            INNER JOIN %s u ON sub.user_id = u.user_id
            WHERE %s
EOF;
        } else {
            $sql = <<<EOF
            SELECT
                COUNT(*)
            FROM
                (
                    SELECT
                        tl.user_id2 as user_id
                    FROM
                        %s tl
                    WHERE
                        tl.transfer_type = '%s'
                    AND tl.create_at BETWEEN %u
                    AND %u
                    GROUP BY
                        tl.user_id2
                ) sub
            INNER JOIN %s u ON sub.user_id = u.user_id
            WHERE %s
EOF;
        }

        $sql = sprintf($sql,$GLOBALS['ecs']->table("mq_transfer_log"),$transfer_type,
            $start_date,$end_date,$GLOBALS['ecs']->table("users"),$where);

        $filter['record_count']   = $GLOBALS['db']->getOne($sql);
        $filter['page_count']     = $filter['record_count'] > 0 ? ceil($filter['record_count'] / $filter['page_size']) : 1;

        /* 查询 */

        if($inout_type=='out') {
            $sql = <<<EOF
                SELECT
                    sub.user_id,
                    u.user_name,
                    sub.CNT,
                    sub.money,
                    IFNULL(ul.user_id, 0) AS limit_flg
                FROM
                    (
                        SELECT
                            tl.user_id,
                            count(*) AS CNT,
                            sum(tl.money) AS money
                        FROM
                            %s tl
                        WHERE
                            tl.transfer_type = '%s'
                        AND tl.create_at BETWEEN %u
                        AND %u
                        GROUP BY
                            tl.user_id
                    ) sub
                INNER JOIN %s u ON sub.user_id = u.user_id
                LEFT JOIN %s ul ON sub.user_id = ul.user_id
                AND (
                    ul.cash_limited = 1
                    OR ul.consume_limited = 1
                )
                AND ul.start_time <= %u
                AND (
                    ul.end_time >= %u
                    OR ul.end_time IS NULL
                )
                WHERE
                    %s
                ORDER BY
                    sub.money DESC
                LIMIT %u ,%u
EOF;
        } else {
          $sql = <<<EOF
                SELECT
                    sub.user_id,
                    u.user_name,
                    sub.CNT,
                    sub.money,
                    IFNULL(ul.user_id, 0) AS limit_flg
                FROM
                    (
                        SELECT
                            tl.user_id2 as user_id,
                            count(*) AS CNT,
                            sum(tl.money) AS money
                        FROM
                            %s tl
                        WHERE
                            tl.transfer_type = '%s'
                        AND tl.create_at BETWEEN %u
                        AND %u
                        GROUP BY
                            tl.user_id2
                    ) sub
                INNER JOIN %s u ON sub.user_id = u.user_id
                LEFT JOIN %s ul ON sub.user_id = ul.user_id
                AND (
                    ul.cash_limited = 1
                    OR ul.consume_limited = 1
                )
                AND ul.start_time <= %u
                AND (
                    ul.end_time >= %u
                    OR ul.end_time IS NULL
                )
                WHERE
                    %s
                ORDER BY
                    sub.money DESC
                LIMIT %u ,%u
EOF;
        }


        $sql = sprintf($sql,$GLOBALS['ecs']->table("mq_transfer_log"),$transfer_type,
            $start_date,$end_date,$GLOBALS['ecs']->table("users"),$GLOBALS['ecs']->table("mq_users_limit"),
            time(),time(),$where,($filter['page'] - 1) * $filter['page_size'],$filter['page_size']);

        set_filter($filter, $sql);
    }
    else
    {
        $sql    = $result['sql'];
        $filter = $result['filter'];
    }

    $res = $GLOBALS['db']->query($sql);
    while ($row = $GLOBALS['db']->fetchRow($res)) {
        $arr[] = $row;
    }

    $arr = array('result' => $arr, 'filter' => $filter, 'page_count' => $filter['page_count'], 'record_count' => $filter['record_count']);

    return $arr;
}