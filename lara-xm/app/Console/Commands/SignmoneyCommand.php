<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SignmoneyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sign:count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '签到逻辑计算';

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
        //获取配置信息
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        if (!isset($distriBution['small_money']->value) || !isset($distriBution['small_value']->value) || !isset($distriBution['middle_money']->value) || !isset($distriBution['middle_value']->value) || !isset($distriBution['big_money']->value) || !isset($distriBution['big_value']->value)) {
            exit("签到配置为空");
        }
        $arr = [];
        $timeCheck = date("Ymd", strtotime("-1 day"));
        //临时用有数据的时间做判断
        $timeCheck = '20190906';
        $status = 1;
        $sql = "SELECT user_id,SUM(release_balance) AS num FROM xm_customs_order WHERE status=? AND FROM_UNIXTIME(create_at,'%Y%m%d') = ? GROUP BY user_id";
        $result = DB::select($sql, [$status, $timeCheck]);
        //低挡位价格
        $smallMoney = explode(",", $distriBution['small_money']->value);
        //低挡位区间
        $smallValue = explode(",", $distriBution['small_value']->value);
        //中挡位价格
        $middleMoney = explode(",", $distriBution['middle_money']->value);
        //中挡位区间
        $middleValue = explode(",", $distriBution['middle_value']->value);
        //高挡位价格
        $bigMoney = explode(",", $distriBution['big_money']->value);
        //高挡位区间
        $bigValue = explode(",", $distriBution['big_value']->value);
        //统计
        $insertRedis = [];

        $smallArr = [];
        $middleArr = [];
        $bigArr = [];

        $userKey = [];

        foreach ($result as $key => $v) {
            $userKey[] = $v->user_id;
            if ($v->num > $smallMoney[0] && $v->num <= $smallMoney[1]) {
                $roundNum = rand(($smallValue[0] * 10), (sprintf("%.1f", ($smallValue[1] + $smallValue[0]) / 2) * 10));
                $insertRedis[$v->user_id] = sprintf("%.2f", $v->num * ($roundNum * 0.01));
                $smallArr[$v->user_id] = $v;
            }
            if ($v->num > $middleMoney[0] && $v->num <= $middleMoney[1]) {
                $roundNum = rand(($middleValue[0] * 10), (sprintf("%.1f", ($middleValue[1] + $middleValue[0]) / 2) * 10));
                $insertRedis[$v->user_id] = sprintf("%.2f", $v->num * ($roundNum * 0.01));
                $middleArr[$v->user_id] = $v;
            }
            if ($v->num > $bigMoney[0]) {
                $roundNum = rand(($bigValue[0] * 10), (sprintf("%.1f", ($bigValue[1] + $bigValue[0]) / 2) * 10));
                $insertRedis[$v->user_id] = sprintf("%.2f", $v->num * ($roundNum * 0.01));
                $bigArr[$v->user_id] = $v;
            }
        }
        $profitTime = date("Ymd", strtotime("-1 day"));
        $profitTime = '20190910';
        $profitSql = "SELECT user_id,SUM(amount) AS num FROM xm_flow_log WHERE FROM_UNIXTIME(create_at,'%Y%m%d') =? AND user_id IN (?) GROUP BY user_id";
        $profitResult = DB::select($profitSql, [$profitTime, implode(',', $userKey)]);

        //动态配置
        //低档位
        $smallProfitMoney = explode(",", $distriBution['small_profit_money']->value);
        $smallProfitValue = explode(",", $distriBution['small_profit_value']->value);
        //中档位
        $middleProfitMoney = explode(",", $distriBution['middle_profit_money']->value);
        $middleProfitValue = explode(",", $distriBution['middle_profit_value']->value);
        //高档位
        $bigProfitMoney = explode(",", $distriBution['big_profit_money']->value);
        $bigProfitValue = explode(",", $distriBution['big_profit_value']->value);
        foreach ($profitResult as $key => $item) {
            if ($item->num > $smallProfitMoney[0] && $item->num <= $smallProfitMoney[1] && isset($insertRedis[$item->user_id])) {
                $insertRedis[$item->user_id] = sprintf("%.2f", $insertRedis[$item->user_id] + ($insertRedis[$item->user_id] * sprintf("%.2f", $smallProfitValue[0] * 0.01)));
            }
            if ($item->num > $middleProfitMoney[0] && $item->num <= $middleProfitMoney[1] && isset($insertRedis[$item->user_id])) {
                $insertRedis[$item->user_id] = sprintf("%.2f", $insertRedis[$item->user_id] + ($insertRedis[$item->user_id] * sprintf("%.2f", $middleProfitValue[0] * 0.01)));
            }
            if ($item->num > $bigProfitMoney[0] && isset($insertRedis[$item->user_id])) {
                $insertRedis[$item->user_id] = sprintf("%.2f", $insertRedis[$item->user_id] + ($insertRedis[$item->user_id] * sprintf("%.2f", $bigProfitValue[0] * 0.01)));
            }
        }
        $redis = app('redis.connection');
        $redis->hmset("sign:prize", $insertRedis);
    }
}
