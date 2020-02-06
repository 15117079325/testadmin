<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class RedisOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:redis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '所有订单插入Redis到队列中';

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
        //查询所有订单
        $orders = DB::table('orders')->select('order_sn')->where('order_type', '=', '1')->orwhere('order_type', '=', '4')->inRandomOrder()->limit(200)->get();
        foreach ($orders as $order) {
            $redis = app('redis.connection');
            $redis->rpush('orderPay', $order->order_sn);
        }
    }
}
