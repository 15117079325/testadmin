<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleasedCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huodan:released';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每日产生待释放统计';

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
        $code = config('adminmaster.statis.released_statis_count');
        $config = DB::table('master_config')->get()->toArray();
        $config = array_column($config, null, 'code');
        $timeNum = isset($config[$code]->value) ? $config[$code]->value : 7;
        $day_time = getBetweenTime(strtotime('-1 day'), 'Y-m-d', $timeNum);
        $giftSql = "SELECT SUM(release_balance) AS num,FROM_UNIXTIME(create_at,'%Y-%m-%d') AS cus_time FROM xm_customs_order WHERE FROM_UNIXTIME(create_at,'%Y-%m-%d')>=? AND  FROM_UNIXTIME(create_at,'%Y-%m-%d')<? GROUP BY cus_time";
        $giftResult = DB::select($giftSql, [$day_time[1], date("Y-m-d")]);
        $giftResult = array_column($giftResult, null, 'cus_time');
        $giftResult = json_decode(json_encode($giftResult), true);
        foreach ($day_time as $days) {
            if (!isset($giftResult[$days])) {
                $giftResult[$days]['num'] = 0;
                $giftResult[$days]['cus_time'] = $days;
            }
            $giftResult[$days]['cus_time'] = date("m-d", strtotime($giftResult[$days]['cus_time']));
        }
        ksort($giftResult);
        $arrEmpty = DB::table('statis_imgs')->where(['code' => $code])->first();
        $insertData = [];
        $insertData['title'] = '每日产生待释放统计';
        $insertData['code'] = $code;
        $insertData['value'] = json_encode($giftResult);
        $insertData['create_time'] = time();
        $insertData['update_time'] = time();
        if (empty($arrEmpty)) {
            $result = DB::table('statis_imgs')->insert($insertData);
        } else {
            $result = DB::table('statis_imgs')->where(['id' => $arrEmpty->id])->update($insertData);
        }
        if ($result) {
            exit('脚本执行完毕，用户每日产生待释放统计完毕' . PHP_EOL);
        } else {
            exit('脚本执行完毕，用户每日产生待释放统计失败' . PHP_EOL);
        }
    }
}
