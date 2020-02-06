<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
class TestController extends Controller
{

    private $runtime = null;     //任务创建时的时间戳
    private $rows_per_loop = 10; //每次做几条
    private static $in_arr = [];
    private static $perfor_arr = [];

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
        $redis_name = $redis_name = 'doStart-22584';
        Redis::del($redis_name);
//        $count = DB::table('mq_buy_back')->count();
//        $total = ceil($count / 1000);
//        die;
//        $sql = " INSERT INTO xm_mq_buy_back_team (`user_id`,`tp_num`,`tp_gmt_create`,`user_name`,`tp_top_user_ids`) VALUES ";
//        for ($i = 0; $i < $total; $i++) {
//            $page = $i * 1000;
//            $users = DB::table('mq_buy_back')->select('mq_buy_back.user_id', 'mq_buy_back.cash_money', 'mq_buy_back.create_at', 'users.user_name')->join('users', 'users.user_id', '=', 'mq_buy_back.user_id')->offset($page)->limit(1000)->get();
//            if ($users->count()) {
//                foreach ($users as $k => $v) {
//                    $user_str = $this->get_up($v->user_id);
//                    $user_str .= $v->user_id;
//                    $sql .= " ({$v->user_id},{$v->cash_money},{$v->create_at},'{$v->user_name}','{$user_str}'),";
//                }
//                $data = rtrim($sql, ',');
//                DB::insert($data);
//            }
//
//        }
    }

    private function get_up($user_id, &$str = '')
    {
        $user_info = DB::table('mq_users_extra')->select('invite_user_id', 'user_id')->where('user_id', '=', $user_id)->first();
        if ($user_info->invite_user_id != 0) {
            $str .= $user_info->invite_user_id . ',';
            $this->get_up($user_info->invite_user_id, $str);
        }
        return $str;
    }
}
