<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TeamNumberAddCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:teamNumber';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '注册一个获取一个团队人数';

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
        $redis_name = 'newRegisterUser';

        $reLen = Redis::lLen($redis_name);
        if(empty($reLen)){
            exit();
        }
        for($i = 0;$i < $reLen;$i ++){
            $user_id = Redis::lpop($redis_name);
            $invite_user_id = DB::table('mq_users_extra')->where('user_id',$user_id)->value('invite_user_id');
            if(empty($invite_user_id)){
                break;
            }
            $bol = $this->get_upper($invite_user_id);
            if(!$bol){
                Redis::rpush($redis_name,$user_id);
            }
        }



    }

    function get_upper($user_id) {
        if (!$user_id) {
            return true;
        }

        $user = DB::table('mq_users_extra')->select('team_number','user_id','invite_user_id','user_cx_rank')->where('user_id',$user_id)->first();
        if(empty($user)){
            return true;
        }

        $now_number = $user->team_number;
        $now_rank = $this->judge_rank($now_number,$user->user_cx_rank);

        DB::update('UPDATE xm_mq_users_extra set team_number = team_number + 1 WHERE user_id = ?',[$user_id]);

//        if($now_rank == 4){
//            return true;
//        }

        if($user->invite_user_id){
            $this->get_upper($user->invite_user_id);
        }
        return true;


    }

    function judge_rank($num,$rank){
        if($rank == 0 || $rank == 5){
            return 0;
        }
        if($num < 10){
            return $rank > 1 ? $rank : 1;

        }elseif($num >= 10 && $num < 50){
            return $rank > 2 ? $rank : 2;
        }elseif ($num >= 50 && $num < 200){
            return $rank > 3 ? $rank : 3;
        }elseif($num >= 200){
            return $rank > 4 ? $rank : 4;
        }
    }
}
