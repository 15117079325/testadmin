<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserTongjiyejiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tpcount:user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户每天晚上统计业绩';

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
        //
        $userId = DB::table('users')->select('user_id')->get();
        $tpCount = DB::table('trade_performance')->select('tp_top_user_ids', 'tp_num')->get()->toArray();
        array_map(function (&$item) {
            $item->tp_top_user_ids = explode(',', $item->tp_top_user_ids);
        }, $tpCount);
        unset($item);
        $updateUser = [];
        foreach ($userId as $k => $v) {
            if (!isset($updateUser[$v->user_id])) {
                $updateUser[$v->user_id] = 0;
            }
            foreach ($tpCount as $kk => $vv) {
                if (in_array($v->user_id, $vv->tp_top_user_ids)) {
                    $updateUser[$v->user_id] += $vv->tp_num;
                }
            }
        }
        foreach ($updateUser as $k => $v) {
            $updateArr['user_id'] = $k;
            $updateArr['user_tp_count'] = $v;
            DB::table('users')->where(['user_id' => $updateArr['user_id']])->update($updateArr);
            echo "把用户ID为{$k}的业绩总数修改为{$v}" . PHP_EOL;
        }
        exit("脚本执行完毕" . PHP_EOL);
    }
}
