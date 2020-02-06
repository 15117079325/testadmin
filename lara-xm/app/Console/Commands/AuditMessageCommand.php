<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ShaoZeMing\GeTui\Facade\GeTui;

class AuditMessageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:audit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '后台实名认证,发送通知';

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
        // status 0审核不通过,审核通过 ai_id
        $informs = DB::table('audit_inform')->get();
        foreach ($informs as $inform) {
            $user = DB::table('users')->select('clientid','device')->where('user_id',$inform->user_id)->first();
            if($user && $user->clientid && $user->device){
                $title = '审核通知';

                if($inform->status == 1){
                    $content = '实名认证成功';
                }else{
                    $content = '实名认证失败,请上传正确的身份信息';
                }

                $mtype = '1';
                $custom_content = ['id' => '0', 'type' => $mtype, 'content' => $content,'title'=>$title];

                $push_data = array(
                    'user_id' => $inform->user_id,
                    'm_type' => $mtype,
                    'o_id'=>0,
                    'm_title' => $title,
                    'm_read' => '1',
                    'm_content' => $content,
                    'm_gmt_create' => time()
                );
                $message_id = DB::table('message')->insertGetId($push_data,'m_id');
                if($message_id){
//                    $bol = $user->device=='android'?true:false;
                    $bol = false;
                    GeTui::push($user->clientid,$custom_content,$bol);
                }
                DB::table('audit_inform')->where('ai_id',$inform->ai_id)->delete();
            }

        }


        $sys_where = [
            ['am_status',1],
            ['am_delete',0]
        ];
        $system_message = DB::table('system_message')->where($sys_where)->first();

        if(empty($system_message)){
            exit();
        }
        $title = $system_message->am_title;

        $content = $system_message->am_content;
        $isWeb = '1';
        if($system_message->am_detail){
            $isWeb = '2';
        }

        $mtype = '2';
        $custom_content = [
            'id' => $system_message->am_id,
            'type' => $mtype,
            'content' => $content,
            'title'=>$title,
            'isWeb'=>$isWeb
        ];
        GeTui::pushToApp($custom_content,false);
        DB::table('system_message')->where('am_id',$system_message->am_id)->update(['am_status'=>2]);
    }
}
