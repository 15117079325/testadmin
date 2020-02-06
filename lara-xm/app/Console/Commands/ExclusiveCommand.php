<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExclusiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huodan:exclusive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每日新人专享报单金额';

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
        //
        $code = config('adminmaster.statis.custome_statis_count');
        $config = DB::table('master_config')->get()->toArray();
        $config = array_column($config, null, 'code');
        $timeNum = isset($config[$code]->value) ? $config[$code]->value : 7;
        $day_time = getBetweenTime(strtotime('-1 day'), 'Y-m-d', $timeNum);
        $exclusiveSql = "SELECT SUM(customs_money) AS num,FROM_UNIXTIME(create_at,'%Y-%m-%d') AS cus_time FROM xm_customs_order WHERE balance_money=0 AND customs_money!=0 AND FROM_UNIXTIME(create_at,'%Y-%m-%d')>=? AND FROM_UNIXTIME(create_at,'%Y-%m-%d')<? GROUP BY cus_time";
        $exclusiveResult = DB::select($exclusiveSql, [$day_time[1], date("Y-m-d")]);
        $exclusiveResult = array_column($exclusiveResult, null, 'cus_time');
        $exclusiveResult = json_decode(json_encode($exclusiveResult), true);
        foreach ($day_time as $days) {
            if (!isset($exclusiveResult[$days])) {
                $exclusiveResult[$days]['num'] = 0;
                $exclusiveResult[$days]['cus_time'] = $days;
            }
            $exclusiveResult[$days]['cus_time'] = date("m-d", strtotime($exclusiveResult[$days]['cus_time']));

        }
        ksort($exclusiveResult);
        $arrEmpty = DB::table('statis_imgs')->where(['code' => $code])->first();
        $insertData = [];
        $insertData['title'] = '统计每日新人专享报单金额的天数';
        $insertData['code'] = $code;
        $insertData['value'] = json_encode($exclusiveResult);
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
