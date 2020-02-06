<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class CustomsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:customs';

    /**
     * The console command description.
     *
     * @var string
     */


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
        $time = time();
        $end_time = strtotime(date('Ymd', $time));

        $datas = null;
        $count = DB::table('customs_order')->where('update_at', '<', $end_time)->where('surplus_release_balance', '!=', 0)->count();
        $total = ceil($count / $this->limit);

        //查出待释放比例
        $day_release_ratio = DB::table('master_config')->where('code', 'day_release_ratio')->pluck('value')->first();
        for ($i = 0; $i < $total; $i++) {
            $offset = $i * $this->limit;
            //条件：
            $datas = DB::table('customs_order')->select(['user_id', 'surplus_release_balance', 'order_id', 'co_id'])
                ->where('update_at', '<', $end_time)
                ->where('surplus_release_balance', '!=', 0)
                ->offset($offset)->limit($this->limit)
                ->get();
            if (count($datas) == 0) {
                break;
            }
            DB::beginTransaction();
            try {
                //读取相应得配置
                foreach ($datas as $item) {
                    //查询用户的配置
                    $person_release_ratio = DB::table('mq_users_limit')->where('user_id', $item->user_id)->first();

                    if ($person_release_ratio && $person_release_ratio->day_release_ratio) {
                        $money = ($person_release_ratio->day_release_ratio * $item->surplus_release_balance) / 100;
                        if ($item->surplus_release_balance < $money) {
                            $money = $item->surplus_release_balance;
                        }
                        DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$money, $money, $time, $item->user_id]);
                        DB::update('UPDATE xm_customs_order SET surplus_release_balance = surplus_release_balance - ?,update_at = ? WHERE user_id = ?', [$money, $time, $item->user_id]);
                    } else {
                        $money = ($day_release_ratio * $item->surplus_release_balance) / 100;
                        if ($item->surplus_release_balance < $money) {
                            $money = $item->surplus_release_balance;
                        }
                        DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$money, $money, $time, $item->user_id]);
                        DB::update('UPDATE xm_customs_order SET surplus_release_balance = surplus_release_balance - ?,update_at = ? WHERE user_id = ?', [$money, $time, $item->user_id]);
                    }
                    $balance = DB::table('user_account')->where('user_id', $item->user_id)->first();
                    //余额流水
                    $flow_data = [
                        'user_id' => $item->user_id,
                        'type' => 2,
                        'status' => 1,
                        'amount' => $money,
                        'surplus' => $balance->balance,
                        'notes' => '待释放余额释放',
                        'create_at' => $time,
                        'target_id' => $item->order_id,
                        'target_type' => 2
                    ];
                    DB::table('flow_log')->insertGetId($flow_data, 'foid');

                    //待释放余额流水
                    $flow_data = [
                        'user_id' => $item->user_id,
                        'type' => 3,
                        'status' => 2,
                        'amount' => $money,
                        'surplus' => $balance->release_balance,
                        'notes' => '待释放余额释放',
                        'create_at' => $time,
                        'target_id' => $item->order_id,
                        'target_type' => 2
                    ];
                    DB::table('flow_log')->insertGetId($flow_data, 'foid');

                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
        }
    }

}
