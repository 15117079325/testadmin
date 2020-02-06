<?php

namespace App\Console\Commands;

use App\Events\JihuolaoyonghuEvent;
use App\Events\XinbaodanluojiEvent;
use App\Events\XinjiesuanluojiEvent;
use App\Events\GqchoujiangEvent;
use App\Events\NewOrderEvent;
use App\Events\UserPayEvent;
use App\Events\ExplosionOrderEvent;
use App\Events\UpdateOrderStatusEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HuodanPayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:huodanpay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '火单结算脚本';

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
        $order_sn_arr = [
            0 => 'X2019082821373799140',
            1 => 'X2019082815274057299',
            2 => 'X2019082814485443646',
            3 => 'X2019082813173388418',
            4 => 'X2019082812445854036',
            5 => 'X2019082811392857327',
            6 => 'X2019082723473598407',
            7 => 'X2019082723455994792',
            8 => 'X2019082723084982556',
            9 => 'X2019082722514843409',
            10 => 'X2019072012055152287',
            11 => 'X2019072012075627640',
            12 => 'X2019072012232080240',
            13 => 'X2019072012322235625',
            14 => 'X2019072012375840631',
            15 => 'X2019072012522547658',
            16 => 'X2019072012531034557',
            17 => 'X2019072012572959721',
            18 => 'X2019072013092246625',
            19 => 'X2019072013274963399',
        ];
        echo "脚本开始";
        echo PHP_EOL;
        $redis = app('redis.connection');
//        $length = $redis->llen('orderPay');
        $length = 20;
        echo "共有个" . $length . "条订单需要处理";
        echo PHP_EOL;
        $second = 10;
        for ($i = 0; $i < $second; $i++) {
            $is_set = $redis->get('is_set');
            sleep(5);
            if ($redis->llen('orderPay') <= 0) {
                $redis->set('is_set', 0);
                echo "订单为空，脚本终止" . PHP_EOL;
                continue;
            }
            $redis->set('is_set', 1);
            for ($j = 0; $j < $length; $j++) {
                $order_sn = $redis->lpop('orderPay');
//                $order_sn = 'B2019092316510664794';
//                $order_sn = $order_sn_arr[$j];
                echo $order_sn . PHP_EOL;
                if (empty($order_sn)) {
                    $redis->set('is_set', 0);
                    echo "订单为空,脚本终止" . PHP_EOL;
                    continue;
                }
                echo PHP_EOL;
                $orders = DB::table('orders')->where('order_sn', $order_sn)->first();
                if (!isset($orders->is_explosion) || $orders->is_explosion == 1) {
                    echo "订单已受理" . PHP_EOL;
                    continue;
                } else {
                    if (isset($orders->order_type) && $orders->order_type == PRODUCT_TYPE_BAODAN || $orders->order_type == PRODUCT_TYPE_BAODAN_MONEY) {
                        if (isset($orders->is_pay) && $orders->is_pay == 0) {
                            event(new XinjiesuanluojiEvent($orders));
                        }

//                        if (isset($orders->is_team) && $orders->is_team == 0) {
//                            event(new UserPayEvent($orders));
//                        }
//                        if ($orders->is_direct == 0 || $orders->is_admin == 0) {
//                            event(new ExplosionOrderEvent($orders));
//                        }
                        if (isset($orders->is_explosion) && $orders->is_explosion == 0) {
                            event(new UpdateOrderStatusEvent($orders));
                        }
                    }
                    echo "订单,处理完毕" . PHP_EOL;
                }
            }
            $redis->set('is_set', 0);
            event(new XinbaodanluojiEvent());
//            event(new JihuolaoyonghuEvent());
//            event(new GqchoujiangEvent('pro'));
            echo "脚本结束,执行成功" . PHP_EOL;

        }
    }
}
