<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TradeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:trade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '1小时取消订单绑定,24小时自动确认转账';

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
        $keys = Redis::keys('trade-*');
        $num = count($keys);
        $now = time();
        for ($i = 0; $i < $num; $i++) {
            if (Redis::exists($keys[$i])) {
                $trade = Redis::get($keys[$i]);
                $res = json_decode($trade, true);

                if ($res['expire_time'] <= $now) {
                    DB::table('trade')->where('trade_status', 1)->whereIn('trade_id', json_decode($res['tradeIds']))->update(['trade_status' => 0]);
                    Redis::del($keys[$i]);
                }
            }
        }
        $where = [
            ['trade_gmt_commit', '<=', $now - AUTO_CONFIRM_TRADE],
            ['trade_status', 2]
        ];

        $trades = DB::table('trade')->select('trade_id', 'user_id', 'trade_status', 'trade_num', 'buy_user_id','cost_rate')->where($where)->offset(0)->limit(20)->get();

        foreach ($trades as $trade) {

            if (empty($trade) || $trade->trade_status != 2) {
                continue;
            }


            $tps = DB::table('tps')->select('unlimit','freeze')->where('user_id',$trade->user_id)->first();

            if(empty($tps) || $tps->freeze < $trade->trade_num){
                continue;
            }
            $userTotal_t = $tps->unlimit + $tps->freeze;

            $to_user_t = DB::table('tps')->select('unlimit','freeze')->where('user_id',$trade->buy_user_id)->first();
            if(empty($to_user_t)){
                continue;
            }
            $toUserTotal_t = $to_user_t->unlimit + $to_user_t->freeze;
            $to_user_name = DB::table('users')->where('user_id',$trade->buy_user_id)->pluck('user_name')->first();


            $trade_id = $trade->trade_id;
            $update_data = [
                'trade_status' => 3,
                'trade_gmt_sure' => $now
            ];

            $cost_num = $trade->trade_num * $trade->cost_rate/100;

            $total_num = $trade->trade_num + $cost_num;
            $t_all = DB::table('master_config')->where('code','xm_t_all')->value('amount');
            if($t_all == null){
                $t_all = 0;
            }

            DB::beginTransaction();
            $aff_row = DB::table('trade')->where('trade_id', $trade_id)->update($update_data);
            if (empty($aff_row)) {
                DB::rollBack();
                continue;
            }

            DB::update('UPDATE xm_tps SET freeze = freeze - ? WHERE user_id = ?', [$total_num, $trade->user_id]);
            DB::update('UPDATE xm_tps SET unlimit = unlimit + ? WHERE user_id = ?', [$trade->trade_num, $trade->buy_user_id]);
            DB::update('UPDATE xm_master_config SET amount = amount + ? WHERE code = ?',[$cost_num,'xm_t_all']);

            $flow_data = [
                'user_id' => $trade->user_id,
                'type' => 2,
                'status' => 2,
                'amount' => $trade->trade_num,
                'surplus' => $userTotal_t - $trade->trade_num - $cost_num,
                'notes' => '出售给' . $to_user_name . '--' . $trade->trade_num,
                'create_at' => $now,
                'target_type' => 4,
                'target_id' => $trade_id
            ];
            $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
            $flow_data['user_id'] = $trade->buy_user_id;
            $flow_data['status'] = 1;
            $flow_data['surplus'] = $toUserTotal_t + $trade->trade_num;
            $flow_data['notes'] = '购买--'. $trade->trade_num;
            $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

            if($cost_num){
                //出售方支付手续费
                $flow_data['user_id'] = $trade->user_id;
                $flow_data['type'] = 2;
                $flow_data['status'] = 2;
                $flow_data['amount'] = $cost_num;
                $flow_data['surplus'] =  $userTotal_t - $trade->trade_num - $cost_num;
                $flow_data['notes'] = '交易手续费';
                $foid3 = DB::table('flow_log')->insertGetId($flow_data,'foid');

                //平台获取手续费
                $flow_data['user_id'] = 0;
                $flow_data['type'] = 2;
                $flow_data['status'] = 1;
                $flow_data['amount'] =$cost_num;
                $flow_data['surplus'] = $t_all + $cost_num;
                $flow_data['notes'] = '交易手续费';
                $flow_data['isall'] = 1;
                $foid4 = DB::table('flow_log')->insertGetId($flow_data,'foid');

                if(empty($foid3) || empty($foid4)){
                    DB::rollBack();
                    continue;
                }

            }
            if (empty($foid1) || empty($foid2) ) {
                DB::rollBack();
                continue;
            } else {
                DB::commit();
            }
        }




    }
}
