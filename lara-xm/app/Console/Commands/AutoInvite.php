<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;

class AutoInvite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auto:intval';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动生成邀请码';

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
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            $code = $this->invite_code();
            DB::table('mq_users_extra')->where('user_id',$user->user_id)->update(['invite'=> $code]);
        }
    }


    /**
     * description:生成邀请码
     * @author libaowei
     */
    public function invite_code($lenght = 11,$user_id = 0) {
        //如果random_bytes函数存在
        if (function_exists("random_bytes")) {
            //生成随机数,长度为指定的长度的一半并
            $bytes = random_bytes(ceil($lenght / 2));
        //如果openssl_random_pseudo_bytes函数存在
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            //生成随机字符串,长度为指定的长度的一半并
            $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
        } else {
            throw new Exception("生成失败");
        }
        //返回一个十六进制，并截取到指定长度
        return substr(bin2hex($bytes), 0, $lenght);
    }

}
