<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProfitCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'huodan:profit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '火粉社区每日平台收益手续费总额';

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
        //每日新增用户
        $code = config('adminmaster.statis.trade_statis_handling');
        $config = DB::table('master_config')->get()->toArray();
        $config = array_column($config, null, 'code');
        $timeNum = isset($config[$code]->value) ? $config[$code]->value : 7;
        $day_time = getBetweenTime(strtotime('-1 day'), 'Y-m-d', $timeNum);

        $registerSql = "SELECT SUM( platform_rate ) AS num,FROM_UNIXTIME( trade_gmt_create, '%Y-%m-%d' ) AS register_time FROM xm_trade WHERE trade_status = 2 AND FROM_UNIXTIME( trade_gmt_create, '%Y-%m-%d' ) >= ? AND FROM_UNIXTIME( trade_gmt_create, '%Y-%m-%d' ) < ? GROUP BY register_time";
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
        $insertData['title'] = '每日平台收益手续费总额';
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
            exit('脚本执行完毕，火粉社区每日平台收益手续费总额统计完毕' . PHP_EOL);
        } else {
            exit('脚本执行完毕，火粉社区每日平台收益手续费总额统计失败' . PHP_EOL);
        }
    }
}
