<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RedPacketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:redpacket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '24小时红包还有余额则返还';

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
        $now = time();
        $where = [
            ['rp_status',1],
            ['rp_gmt_create','<=',$now - AUTO_PAYBACK_REDPACKET]
        ];
        $ids = DB::table('redpackets')->where($where)->offset(0)->limit(20)->pluck('rp_id');
        $num = count($ids);
        for($i = 0;$i < $num;$i ++){
            $redis_name = 'redpacket-'.$ids[$i];
            if(Redis::exists($redis_name)){


                $packet = DB::table('redpackets')->select('rp_id','rp_money','rp_type','user_id','rp_status')->where('rp_id',$ids[$i])->first();
                if($packet && $packet->rp_status == 1){
                    $amount = DB::table('redpacket_detail')->selectRaw('sum(rd_amount) as amount')->where('rp_id',$packet->rp_id)->pluck('amount')->first();
                    if($amount != null){
                        $left_money = $packet->rp_money - $amount;
                        $user_amount = DB::table('tps')->select('shopp','unlimit')->where('user_id',$packet->user_id)->first();
                        if(empty($user_amount)){
                            break;
                        }
                        DB::beginTransaction();
                        $update_data = ['rp_status'=>3];

                        DB::table('redpackets')->where('rp_id',$ids[$i])->update($update_data);
                        if($packet->rp_type == 1){
                            //1、消费积分
                            $type = 3;
                            $notes = '红包过期返还消费积分'.$left_money;
                            $surplus = $user_amount->shopp + $left_money;
                            DB::update('UPDATE xm_tps SET shopp = shopp + ? WHERE user_id = ?',[$left_money,$packet->user_id]);
                        }else if($packet->rp_type == 2){
                            //T积分
                            $type = 2;
                            $notes = '红包过期返还T积分'.$left_money;
                            $surplus = $user_amount->unlimit + $left_money;
                            DB::update('UPDATE xm_tps SET unlimit = unlimit + ? WHERE user_id = ?',[$left_money,$packet->user_id]);
                        }else{
                            DB::rollBack();
                            exit();
                        }
                        $flow_data = [
                            'user_id'=>$packet->user_id,
                            'type'=>$type,
                            'status'=>1,
                            'amount'=>$left_money,
                            'surplus'=>$surplus,
                            'notes'=>$notes,
                            'create_at'=>$now,
                            'target_type'=>11,
                            'target_id'=>$packet->rp_id
                        ];
                        $foid = DB::table('flow_log')->insertGetId($flow_data,'foid');
                        if(empty($foid)) {
                            DB::rollBack();
                        }else{
                            Redis::del($redis_name);
                            DB::commit();
                        }

                    }
                }

            }
        }


    }
//    public function handle1()
//    {
//        $now = time();
//        $where = [
//            ['rp_status',1],
//            ['rp_gmt_create','<=',$now - AUTO_PAYBACK_REDPACKET]
//        ];
//        $ids = DB::table('redpackets')->where($where)->offset(0)->limit(20)->pluck('rp_id');
//        $num = count($ids);
//        for($i = 0;$i < $num;$i ++){
//            $redis_name = 'redpacket-'.$ids[$i];
//            if(Redis::exists($redis_name)){
//
//
//                $packet = DB::table('redpackets')->select('rp_id','rp_money','rp_type','user_id','rp_status')->where('rp_id',$ids[$i])->first();
//                if($packet && $packet->rp_status == 1){
//                    $amount = DB::table('redpacket_detail')->selectRaw('sum(rd_amount) as amount')->where('rp_id',$packet->rp_id)->pluck('amount')->first();
//                    if($amount != null){
//                        $left_money = $packet->rp_money - $amount;
//
//                        DB::beginTransaction();
//                        $update_data = ['rp_status'=>3];
//
//                        DB::table('redpackets')->where('rp_id',$ids[$i])->update($update_data);
//                        if($packet->rp_type == 1){
//                            //1、消费积分
//                            $type = 3;
//                            $shopp = DB::table('tps')->where('user_id', $packet->user_id)->pluck('shopp')->first();
//                            if($shopp == null){
//                                $shopp = 0;
//                            }
//                            $notes = '红包过期返还消费积分'.$left_money;
//                            $surplus = $shopp + $left_money;
//                            DB::update('UPDATE xm_tps SET shopp = shopp + ? WHERE user_id = ?',[$left_money,$packet->user_id]);
//                        }else if($packet->rp_type == 2){
//                            //新美积分
//                            $type = 1;
//                            $xmAmount = DB::table('xps')->where('user_id', $packet->user_id)->value('amount');
//                            if($xmAmount == null){
//                                $xmAmount = 0;
//                            }
//                            $notes = '红包过期返还新美积分'.$left_money;
//                            $surplus = $xmAmount + $left_money;
//                            DB::update('UPDATE xm_xps SET unlimit = unlimit + ?,amount = amount + ? WHERE user_id = ?',[$left_money,$left_money,$packet->user_id]);
//                        }else{
//                            DB::rollBack();
//                            exit();
//                        }
//                        $flow_data = [
//                            'user_id'=>$packet->user_id,
//                            'type'=>$type,
//                            'status'=>1,
//                            'amount'=>$left_money,
//                            'surplus'=>$surplus,
//                            'notes'=>$notes,
//                            'create_at'=>$now,
//                            'target_type'=>11,
//                            'target_id'=>$packet->rp_id
//                        ];
//                        $foid = DB::table('flow_log')->insertGetId($flow_data,'foid');
//                        if(empty($foid)) {
//                            DB::rollBack();
//                        }else{
//                            Redis::del($redis_name);
//                            DB::commit();
//                        }
//
//
//
//
//
//
//
//
//
//                    }
//                }
//
//            }
//        }
//
//
//    }
}
