<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\TradeDetailCommand::class,
//        Commands\RedPacketCommand::class,
        // Commands\BuyBackReleaseCommand::class,
        Commands\OrderCommand::class,
        //      Commands\CustomsCommand::class,
//        Commands\FakeTradeCommand::class,
        Commands\UserIdentityCommand::class,
        // Commands\AuditMessageCommand::class,
        Commands\TeamNumberAddCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('xm:trade-detail')->everyMinute();
//        $schedule->command('xm:redpacket')->everyMinute();
        $schedule->command('xm:order')->everyMinute();
//       $schedule->command('xm:hOrder')->everyMinute();
        $schedule->command('xm:teamNumber')->everyMinute();
        $schedule->command('xm:tradeData')->dailyAt('23:00');
        //      $schedule->command('xm:customs')->everyMinute();
        $schedule->command('xm:userIdentity')->dailyAt('01:00');
        $schedule->command('sign:rise')->dailyAt('21:00');
        $schedule->command('sign:newcount')->dailyAt('22:00');
        $schedule->command('sign:newRedis')->dailyAt('23:00');
        $schedule->command('command:huodanpay')->everyMinute();
        $schedule->command('command:userlink')->everyMinute();
        $schedule->command('auto:balance')->everyMinute();
        $schedule->command('huodan:profit')->dailyAt('01:00');
        $schedule->command('huodan:stat')->dailyAt('01:00');
        $schedule->command('huodan:available')->dailyAt('01:00');
        $schedule->command('huodan:produce')->dailyAt('02:00');
        $schedule->command('huodan:gift')->dailyAt('02:00');
        $schedule->command('huodan:exclusive')->dailyAt('03:00');
        $schedule->command('huodan:released')->dailyAt('03:00');
        $schedule->command('huodan:powder')->dailyAt('03:00');
        $schedule->command('huodan:sign')->dailyAt('04:00');
        $schedule->command('huodan:signcount')->dailyAt('04:00');
        $schedule->command('baodan:xinde')->dailyAt('23:00');
//        $schedule->command('xm:teamTradeRank')->dailyAt('01:00');
//        $schedule->command('xm:transferIntegral')->dailyAt('03:00');


//        $trade_switch = DB::table('master_config')->where('code','xm_trade_switch')->first();
//
//        if($trade_switch && $trade_switch->value == 1){
//            return;
//        }
//        $now = time();
//        $nowDay = date('Y-m-d',$now);
//        $limitTime = DB::table('master_config')->where('code','deal_open_close_time')->pluck('value')->first();
//
//        $limitArr = explode('-',$limitTime);
//
//        if(count($limitArr) == 2){
//            $bottom_limit = strtotime($nowDay.' '.$limitArr[0]);
//            $top_limit = strtotime($nowDay.' '.$limitArr[1]);
//
//        }else{
//            $bottom_limit = strtotime($nowDay.' 08:00');
//            $top_limit = strtotime($nowDay.' 20:00');
//        }


//        if($bottom_limit <= $now && $top_limit >= $now){
//            $schedule->command('xm:fake')->everyMinute();

//            $trade = DB::table('trade')->orderBy('trade_id','desc')->first();
//            if($trade && $trade->trade_type == 2){
//                $minute = rand(1,59);
//                $schedule->command('xm:fake')->hourlyAt($minute);
//            }else{
//                $schedule->command('xm:fake')->everyTenMinutes();
//            }
//        }


    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
