<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class Autobalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:balance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '临时优惠券返回用户';

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

        $length = 17;
        for ($i = 0; $i < $length; $i++) {
            //查询有临时优惠券的用户
            $user_accounts = DB::table('user_account')->select('user_id', 'temporary_balance')->where('temporary_balance', '>', 0)->sharedLock()->get();
            if (count($user_accounts)) {
                //循环
                foreach ($user_accounts as $user_account) {
                    DB::transaction(function () use ($user_account) {
                        DB::table('user_account')->lockForUpdate()->where('user_id', $user_account->user_id)->increment('balance', $user_account->temporary_balance);
                        DB::table('user_account')->lockForUpdate()->where('user_id', $user_account->user_id)->decrement('temporary_balance', $user_account->temporary_balance);
                    });
                }
            }
            sleep(3);
        }

    }
}
