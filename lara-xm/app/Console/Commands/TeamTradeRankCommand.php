<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TeamTradeRankCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:teamTradeRank';
    private static $in_arr = [];
    private static $perfor_arr = [];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '计算服务中心的交易排名';

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
        $start_time = strtotime(date('Ymd', strtotime('-1 day')));
        $end_time = $start_time + 24 * 3600 - 1;
        $time = $end_time - 59;
        //获取当天的出售总额
        $sell_where = [
            ['trade.trade_status', '!=', 4],
            ['trade.trade_type', '=', 1],
            ['trade.trade_gmt_create', '>', $start_time],
            ['trade.trade_gmt_create', '<', $end_time],
        ];

        $sell_money = DB::table('trade')->selectRaw('sum(trade_num) as money,user_id')->where($sell_where)->groupBy('user_id')->get();
        if ($sell_money->count()) {
            foreach ($sell_money as $k => $v) {
                $this->get_up($v->user_id, $v->money);
            }
            $sell_arr = self::$in_arr;
            self::$in_arr = [];
            $sell_money = "INSERT INTO xm_trade_team_rank (`user_id`,`user_cx_rank`,`top_user_id`,`type`,`money`,`create_time`) VALUES ";
            foreach ($sell_arr as $k => $v) {
                if ($v['rank'] == 2) {
                    //获取上一个等级的user_id
                    $in_user_id = DB::table('mq_users_extra')->where('user_id', $k)->pluck('invite_user_id')->first();
                    $top_user_id = $this->get_top_user($in_user_id, 2);
                    $top_user_id = empty($top_user_id) ? 0 : $top_user_id;
                } elseif ($v['rank'] == 3) {
                    $in_user_id = DB::table('mq_users_extra')->where('user_id', $k)->pluck('invite_user_id')->first();
                    $top_user_id = $this->get_top_user($in_user_id, 3);
                    $top_user_id = empty($top_user_id) ? 0 : $top_user_id;
                } else {
                    $top_user_id = 0;
                }
                $sell_money .= "({$k},{$v['rank']},{$top_user_id},1,{$v['money']},{$time}),";
            }
            $sell_money = rtrim($sell_money, ',');
            DB::insert($sell_money);
        }


        //获取当天的购买成功总额
        $success_where = [
            ['trade_status', '=', 3],
            ['trade_type', '=', 1],
            ['trade_gmt_sure', '>', $start_time],
            ['trade_gmt_sure', '<', $end_time],
        ];
        $success_money = DB::table('trade')->selectRaw('sum(trade_num) as money,buy_user_id')->where($success_where)->groupBy('buy_user_id')->get();
        if ($success_money->count()) {
            foreach ($success_money as $k => $v) {
                $this->get_up($v->buy_user_id, $v->money);
            }
            $success_arr = self::$in_arr;
            self::$in_arr = [];
            $success_money = "INSERT INTO xm_trade_team_rank (`user_id`,`user_cx_rank`,`top_user_id`,`type`,`money`,`create_time`) VALUES ";
            foreach ($success_arr as $k => $v) {
                if ($v['rank'] == 2) {
                    //获取上一个等级的user_id
                    $in_user_id = DB::table('mq_users_extra')->where('user_id', $k)->pluck('invite_user_id')->first();
                    $top_user_id = $this->get_top_user($in_user_id, 2);
                    $top_user_id = empty($top_user_id) ? 0 : $top_user_id;
                } elseif ($v['rank'] == 3) {
                    $in_user_id = DB::table('mq_users_extra')->where('user_id', $k)->pluck('invite_user_id')->first();
                    $top_user_id = $this->get_top_user($in_user_id, 3);
                    $top_user_id = empty($top_user_id) ? 0 : $top_user_id;
                } else {
                    $top_user_id = 0;
                }
                $success_money .= "({$k},{$v['rank']},{$top_user_id},3,{$v['money']},{$time}),";
            }
            $success_money = rtrim($success_money, ',');
            DB::insert($success_money);
        }


        //获取当天求购的总额
        $buy_where = [
            ['tb_gmt_create', '>', $start_time],
            ['tb_gmt_create', '<', $end_time],
        ];
        $buy_money = DB::table('trade_buy')->selectRaw('sum(buy_num) as money,user_id')->where($buy_where)->groupBy('user_id')->get();
        if ($buy_money->count()) {
            foreach ($buy_money as $k => $v) {
                $this->get_up($v->user_id, $v->money);
            }
            $buy_arr = self::$in_arr;
            self::$in_arr = [];
            $buy_money = "INSERT INTO xm_trade_team_rank (`user_id`,`user_cx_rank`,`top_user_id`,`type`,`money`,`create_time`) VALUES ";
            foreach ($buy_arr as $k => $v) {
                if ($v['rank'] == 2) {
                    //获取上一个等级的user_id
                    $in_user_id = DB::table('mq_users_extra')->where('user_id', $k)->pluck('invite_user_id')->first();
                    $top_user_id = $this->get_top_user($in_user_id, 2);
                    $top_user_id = empty($top_user_id) ? 0 : $top_user_id;
                } elseif ($v['rank'] == 3) {
                    $in_user_id = DB::table('mq_users_extra')->where('user_id', $k)->pluck('invite_user_id')->first();
                    $top_user_id = $this->get_top_user($in_user_id, 3);
                    $top_user_id = empty($top_user_id) ? 0 : $top_user_id;
                } else {
                    $top_user_id = 0;
                }
                $buy_money .= "({$k},{$v['rank']},{$top_user_id},2,{$v['money']},{$time}),";
            }
            $buy_money = rtrim($buy_money, ',');
            DB::insert($buy_money);
        }

         //计算业绩
        $perfor_where = [
            ['tp_gmt_create', '>', $start_time],
            ['tp_gmt_create', '<', $end_time],
        ];
        $perfor_money = DB::table('trade_performance')->selectRaw('tp_num as money,user_id,tp_top_user_ids')->where($perfor_where)->get();
        if ($perfor_money->count()) {
            foreach ($perfor_money as $k => $v) {
                $top_user_ids = explode(',',$v->tp_top_user_ids);
                foreach ($top_user_ids as $kk=>$vv){
                    if(isset(self::$perfor_arr[$vv])){
                        self::$perfor_arr[$vv] = self::$perfor_arr[$vv] + $v->money;
                    }else{
                        self::$perfor_arr[$vv] = $v->money;
                    }
                }
            }
            $perfor_arr = self::$perfor_arr;
            self::$perfor_arr = [];
            $perfor_money = "INSERT INTO xm_trade_performance_data (`user_id`,`pd_money`,`pd_gmt_create`) VALUES ";
            foreach ($perfor_arr as $k => $v) {
                $perfor_money .= "({$k},{$v},{$time}),";
            }
            $perfor_money = rtrim($perfor_money, ',');
            DB::insert($perfor_money);
        }

        //水果机每天数据
        $fruit_where = [
            ['ll_gmt_create', '>', $start_time],
            ['ll_gmt_create', '<', $end_time],
        ];
        $cost_money = DB::table('lottery_logs')->where($fruit_where)->sum('ll_cost_money');
        $reward_money = DB::table('lottery_logs')->where($fruit_where)->sum('ll_reward_money');
        $bet_num = DB::table('lottery_logs')->distinct('user_id')->where($fruit_where)->count('user_id');
        $time = date('Ymd',$time);
        $data = [
            'ld_cost_money' => $cost_money,
            'ld_reward_money'=>$reward_money,
            'ld_bet_num'=>$bet_num,
            'ld_create_at'=>$time
        ];
        DB::table('lottery_data')->insertGetId($data,'ld_id');
    }

    private function get_up($in_user_id, $money)
    {
        $user_info = DB::table('mq_users_extra')->select('invite_user_id', 'user_cx_rank', 'user_id')->where('user_id', '=', $in_user_id)->first();
        if ($user_info->user_cx_rank > 1) {
            if (isset(self::$in_arr[$user_info->user_id])) {
                self::$in_arr[$user_info->user_id]['money'] = self::$in_arr[$user_info->user_id]['money'] + $money;
            } else {
                self::$in_arr[$user_info->user_id]['rank'] = $user_info->user_cx_rank;
                self::$in_arr[$user_info->user_id]['money'] = $money;
            }

        }
        if ($user_info->user_cx_rank == 4) {
            return true;
        }
        if ($user_info->invite_user_id == 0) {
            return true;
        }
        $this->get_up($user_info->invite_user_id, $money);
    }

    private function get_top_user($in_user_id, $rank)
    {
        $user_info = DB::table('mq_users_extra')->select('invite_user_id', 'user_cx_rank', 'user_id')->where('user_id', '=', $in_user_id)->first();
        if (empty($user_info)) {
            return 0;
        }
        if ($user_info->user_cx_rank > $rank) {
            return $user_info->user_id;
        }
        $this->get_top_user($user_info->invite_user_id, $rank);
    }
}
