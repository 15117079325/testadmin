<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class TradeDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:tradeData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算当天的兑换中心数据';

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
          //获取当天的时间
        $time = date('Ymd',time());
        $start_time = strtotime($time);
        $end_time = $start_time + 24*3600-1;
        //获取当天的出售总额
        $sell_where = [
            ['trade.trade_status', '=',0],
            ['trade.trade_type', '=',1],
            ['trade.trade_gmt_create','>',$start_time],
            ['trade.trade_gmt_create','<',$end_time],
        ];
        $sell_money = DB::table('trade')->where($sell_where)->sum('trade_num');
        $sell_num = DB::table('trade')->distinct('user_id')->where($sell_where)->count('user_id');
        //获取当天的购买成功总额
        $success_where = [
            ['td_status', '=',2],
            ['complete_at','>',$start_time],
            ['complete_at','<',$end_time],
        ];
        $success_money = DB::table('trade_detail')->where($success_where)->sum('td_num');
        $success_num = DB::table('trade_detail')->distinct('buyer_id')->where($success_where)->count('buyer_id');
        //获取当天的手续费总额
        $rate_where = [
            ['td_status', '=',2],
            ['complete_at','>',$start_time],
            ['complete_at','<',$end_time],
        ];
        $rate_money1 = DB::table('trade_detail')->where($rate_where)->sum('td_platform_num');
        $rate_money2 = DB::table('trade_detail')->where($rate_where)->sum('td_buy_num');
        $rate_money = $rate_money1 + $rate_money2;
        //获取当天求购的总额
        $buy_where = [
            ['tb_gmt_create','>',$start_time],
            ['tb_gmt_create','<',$end_time],
        ];
        $buy_money = DB::table('trade_buy')->where($buy_where)->sum('buy_num');
        $buy_num = DB::table('trade_buy')->distinct('user_id')->where($buy_where)->count('user_id');
        $data=[
            'td_success_money'=>$success_money,
            'td_success_num'=>$success_num,
            'td_buy_money'=>$buy_money,
            'td_buy_num'=>$buy_num,
            'td_sell_money'=>$sell_money,
            'td_sell_num'=>$sell_num,
            'td_service_money'=>$rate_money,
            'td_create_at'=>$time,
        ];
        DB::table('trade_data')->insertGetId($data,'id');
    }
}
