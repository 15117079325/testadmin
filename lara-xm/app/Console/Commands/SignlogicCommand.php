<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SignlogicCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sign:newRedis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算出用户第二天签到应该得到多少钱';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //获取配置信息
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $timeCheck = date("Ymd", strtotime("-1 day"));
        //临时用有数据的时间做判断
//        $timeCheck = '20190906';
        $status = 1;
        $sql = "SELECT user_id,SUM(release_balance) AS num FROM xm_customs_order WHERE status=? GROUP BY user_id";
        $cusResults = DB::select($sql, [$status, $timeCheck]);
        $cusResult = array_column($cusResults, null, 'user_id');
        $users = DB::table('users')->select("user_id", "cut_wigth")->where(['locked' => 0])->get()->toArray();
        $user = array_column($users, null, 'user_id');
        //拿配置信息
        $signNum = explode(",", $distriBution['sign_num']->value);
        $signArr = [];
        array_map(function ($item) use ($distriBution, &$signArr) {
            if (!isset($distriBution[$item])) {
                return;
            }
            $distr = explode("/", $distriBution[$item]->value);
            $signArr[$item]['up'] = $distr[0];
            $signArr[$item]['drow'] = $distr[1];
            $signArr[$item]['section'] = explode("-", $distr[2]);
        }, $signNum);
        $insertRedis = [];
        $delUserId = [];
        foreach ($user as $k => $v) {
            if (isset($cusResult[$v->user_id])) {
                $roundNum = rand(($signArr[$v->cut_wigth]['section'][0] * 100), (($signArr[$v->cut_wigth]['section'][1]) * 100)) * 0.01;
                $insertRedis[$v->user_id] = sprintf("%.2f", $cusResult[$v->user_id]->num * ($roundNum * 0.01));
            } else {
                $delUserId[] = $v->user_id;
                continue;
            }
        }
        $redis = app('redis.connection');
        $redis->hDel("sign:prize", $delUserId);
        $redis->hmset("sign:prize", $insertRedis);
    }
}
