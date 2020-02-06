<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StatisticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huodan:stat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '火单每日用户统计脚本执行';

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
        //每日新增用户
        $code = config('adminmaster.statis.user_statis_count');
        $config = DB::table('master_config')->get()->toArray();
        $config = array_column($config, null, 'code');
        $timeNum = isset($config[$code]->value) ? $config[$code]->value : 7;
        $day_time = getBetweenTime(strtotime('-1 day'), 'Y-m-d', $timeNum);
        $registerSql = "SELECT COUNT(*) AS num,FROM_UNIXTIME(reg_time,'%Y-%m-%d') AS register_time FROM xm_users GROUP BY register_time HAVING register_time >=? AND register_time<? ";
        $registerUser = DB::select($registerSql, [$day_time[1], date("Y-m-d")]);
        $registerUser = array_column($registerUser, null, 'register_time');
        $registerUser = json_decode(json_encode($registerUser), true);
        foreach ($day_time as $days) {
            if (!isset($registerUser[$days])) {
                $registerUser[$days]['num'] = 0;
                $registerUser[$days]['register_time'] = $days;
            }
            $registerUser[$days]['register_time'] = date("m-d", strtotime($registerUser[$days]['register_time']));
        }
        ksort($registerUser);
        $arrEmpty = DB::table('statis_imgs')->where(['code' => $code])->first();
        $insertData = [];
        $insertData['title'] = '用户每日注册统计';
        $insertData['code'] = $code;
        $insertData['value'] = json_encode($registerUser);
        $insertData['create_time'] = time();
        $insertData['update_time'] = time();
        if (empty($arrEmpty)) {
            $result = DB::table('statis_imgs')->insert($insertData);
        } else {
            $result = DB::table('statis_imgs')->where(['id' => $arrEmpty->id])->update($insertData);
        }
        if ($result) {
            exit('脚本执行完毕，用户每日注册数量统计完毕' . PHP_EOL);
        } else {
            exit('脚本执行完毕，用户每日注册数量统计失败' . PHP_EOL);
        }
    }
}
