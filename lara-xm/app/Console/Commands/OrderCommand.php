<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:order';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '1小时未支付取消订单,发货后7天自动收货';

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
        $owhere = [
            ['order_status',1],
            ['order_cancel',1],
            ['order_gmt_expire','<=', $now]
        ];

        $oids = DB::table('orders')->where($owhere)->pluck('order_id');

        //查出订单的相关信息
        $orders = DB::table('orders')->where($owhere)->get()->toArray();

        if($oids){
            $update_data = ['order_cancel'=>3];
            DB::table('orders')->whereIn('order_id',$oids)->update($update_data);
            //库存返还
            $order_goods = DB::table('order_detail')->select('p_id','size_id','od_num')->whereIn('order_id',$oids)->get();
            foreach ($order_goods as $order_good) {
                if($order_good->size_id){
                    DB::update('UPDATE xm_size SET size_stock = size_stock + ? WHERE size_id = ?',[$order_good->od_num,$order_good->size_id]);
                }else{
                    DB::update('UPDATE xm_product SET p_stock = p_stock + ? WHERE p_id = ?',[$order_good->od_num,$order_good->p_id]);

                }
            }

        }
        //判断是否有相应的订单
        if(count($orders)) {
            //开始返回优惠券并扣除下单先扣余额
            foreach ($orders as $order) {
                if($order->order_balance >0 && $order->order_type == 3) {
                    //更新用户优惠券
                    DB::table('user_account')->where('user_id',$order->user_id)->increment('balance',$order->order_balance);
                    DB::table('user_account')->where('user_id',$order->user_id)->decrement('pending_balance',$order->order_balance);
                }
            }
        }

        //**

        $where = [
            ['order_status',3],
            ['order_gmt_send','<=',$now - AUTO_SURE_ORDER]
        ];
        $ids = DB::table('orders')->where($where)->pluck('order_id');
        if($ids){
            $update_data = ['order_status'=>4,'order_gmt_sure'=>$now];
            DB::table('orders')->whereIn('order_id',$ids)->update($update_data);
        }
    }
}
