<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseConsumeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:reslease';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '余额按天释放脚本';


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
        $count = DB::table('user_account')->where('release_at', '<', $end_time)->where('release_balance', '!=', 0)->count();
        $total = ceil($count / $this->limit);

        //查出待释放比例
        $day_release_ratio = DB::table('master_config')->where('code', 'day_release_ratio')->pluck('value')->first();
        for ($i = 0; $i < $total; $i++) {
            $offset = $i * $this->limit;
            //条件：
            $datas = DB::table('user_account')->select(['user_id', 'release_balance'])
                ->where('release_at', '<', $end_time)
                ->where('release_balance', '!=', 0)
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
                    if($person_release_ratio==0){
                        continue;
                    }
                    if ($person_release_ratio>0 && $person_release_ratio->day_release_ratio) {
                        $money = ($person_release_ratio->day_release_ratio * $item->release_balance) / 100;
                        if ($item->release_balance < $money) {
                            $money = $item->release_balance;
                        }
                        DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$money, $money, $time, $item->user_id]);
                    } else {
                        $money = ($day_release_ratio * $item->release_balance) / 100;
                        if ($item->release_balance < $money) {
                            $money = $item->release_balance;
                        }
                        DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$money, $money, $time, $item->user_id]);
                    }
                    $balance = DB::table('user_account')->where('user_id', $item->user_id)->first();
                    //余额流水
                    $flow_data = [
                        'user_id' => $item->user_id,
                        'type' => 2,
                        'status' => 1,
                        'amount' => $money,
                        'surplus' => $balance->balance,
                        'notes' => '待释放优惠券释放',
                        'create_at' => $time,
                    ];
                    DB::table('flow_log')->insertGetId($flow_data, 'foid');

                    //待释放余额流水
                    $flow_data = [
                        'user_id' => $item->user_id,
                        'type' => 3,
                        'status' => 2,
                        'amount' => $money,
                        'surplus' => $balance->release_balance,
                        'notes' => '待释放优惠券释放',
                        'create_at' => $time,
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
