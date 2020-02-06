<?php

namespace App\Shell;

use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '3072M');
set_time_limit(0);

/**
 * 团队人数 上线前跑一次
 * Class ReleaseconsumeJob
 * @package App\Jobs
 * @author douhao
 *
 */
class TeamnumberJob
{
    private $runtime = null;     //任务创建时的时间戳
    private $rows_per_loop = 10; //每次做几条
    private static $u_invi_c = 0;

    /**
     * 创建一个新的任务实例。
     *
     * @param $time 启动时间
     */
    public function __construct()
    {
        $this->runtime = time();
    }

    /**
     * 运行任务。
     *
     * @return void
     */
    public function handle()
    {
        $this->teamNumber();
    }

    private function teamNumber()
    {
        $count = DB::table('mq_users_extra')->count();
        $total = ceil($count / 1000);
        for ($i = 0; $i < $total; $i++) {
            $page = $i * 1000;
            $users = DB::table('mq_users_extra')->select('user_id')->offest($page)->limit(1000);
            foreach ($users as $k => $v) {
                //查出直推人员
                $user_num = DB::table('mq_users_extra')->where('user_cx_rank','!=',0)->where('invite_user_id',$v->user_id)->count();
                //查出间推人员
                if ($user_num) {
                    $i_num = $this->get_lower($v->user_id);
                } else {
                    $i_num = 0;
                }
                $total_num = intval($user_num) + intval($i_num);
                self::$u_invi_c = 0;
                DB::table('mq_buy_back')->where('user_id', $v->user_id)->update(['team_number',$total_num]);
            }
        }
    }


    private function get_lower($str, $count = -1)
    {

        if (!$str) {
            return false;
        }

        self::$u_invi_c += $count;
        $list = DB::table('mq_users_extra')->select('user_id')->where('user_cx_rank','!=',0)->whereIn('invite_user_id',$str)->get();
        if (count($list) != 0) {

            foreach ($list as $key => $value) {

                $ll[] = $value->user_id;
            }

            // 间推的人数统计 | 当 $count == -1, 说明上面的 SQL 查询的直推的结果，这里的人数要过滤掉
            $inv_c = ($count == -1) ? 0 : count($list);

            $this->get_lower($str, $inv_c);
        }

        // 从 -1 算起，少了一个人，要加上
        return self::$u_invi_c + 1;
    }
}
