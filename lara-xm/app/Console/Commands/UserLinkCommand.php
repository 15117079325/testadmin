<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserLinkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:userlink';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动建立用户关系';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        ini_set('memory_limit', '2048M');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        echo "脚本开始";
        static $user_like = array();
        $user = DB::table('mq_users_extra')->where('invite_user_id', 0)->get();
        $user_data = json_decode(json_encode($user), true);
        unset($user);
        foreach ($user_data as $k => $v) {
            if ($v['invite_user_id'] == 0) {
                $user_like[$v['user_id']] = $v['user_id'];
//                DB::table('orders')->where('id', $v['id'])->update(['user_like' => $v['id']]);
            }
            $this->getUserLike($v['user_id'], $user_like);
        }
        // print_r($user_like);
        // die;
        foreach ($user_like as $k => $v) {
            echo "插入用户-" . $k . "-的数据";
            echo PHP_EOL;
            DB::table('users')->where('user_id', $k)->update(['user_like' => $v]);
        }
    }

    public function getUserLike($user_id = 0, &$user_like)
    {
        echo "递归++" . $user_id;
        echo PHP_EOL;
        $user = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->get();
        if ($user != '') {
            $user_data = json_decode(json_encode($user), true);
            foreach ($user_data as $k => $vv) {
                $user_like[$vv['user_id']] = $user_like[$user_id] . ',' . $vv['user_id'];
//                DB::table('orders')->where('id', $v['id'])->update(['user_like' => $v['id']]);
                if ($vv['invite_user_id'] != 0) {
                    $this->getUserLike($vv['user_id'], $user_like);
                }
            }
        }
    }
}
