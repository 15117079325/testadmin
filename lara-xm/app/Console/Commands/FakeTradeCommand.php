<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FakeTradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:fake';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '实时兑换中心假数据';

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
        $numArr = range(5,20);
        $key = array_rand($numArr,1);
        $trade_num = $numArr[$key] * 100;

//        $pre_trade = DB::table('trade')->where('trade_status',3)->orderBy('trade_id','desc')->select('trade_type','trade_gmt_sure')->first();
//        if($pre_trade && $pre_trade->trade_type == 2){
//            $minute = date('i',$pre_trade->trade_gmt_sure);
//            if($minute < 50){
//                $add_minute = rand(1,10);
//            }else{
//
//            }
//        }


        $mobile_phone = $this->getRandMobile();
        $insert_data = [
            'trade_num'=>$trade_num,
            'trade_gmt_sure'=>time(),
            'trade_type'=>2,
            'mobile_phone'=>$mobile_phone,
            'trade_status'=>3
        ];
        $foid = DB::table('trade')->insertGetId($insert_data,'foid');


    }
    function getRandMobile(){

        $starts = ['139', '138', '137', '136', '135', '134', '178','170', '188', '187' ,'183', '182', '159', '158' ,'157','152','150','147', '198'];
        $key = array_rand($starts,1);
        $start = $starts[$key];
        $mobile = $start.rand(1000,9999).rand(1000,9999);
        return $mobile;
    }
    function getRandTime($preTime){
        $preH = date();

    }
}
