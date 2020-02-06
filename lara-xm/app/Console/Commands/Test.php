<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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

        $users = DB::table('users')->select('user_id','nickname','headimg')->where('test_time',0)->offset(0)->limit(60)->get();
        $serverapi = new \ServerAPI();

        foreach ($users as $user) {
            $rong_chat = $serverapi->getToken($user->user_id, $user->nickname, strpos_domain($user->headimg));

            $rong_chat = json_decode($rong_chat);
            if($rong_chat->code == 200){
                $data = ['chat_token'=>$rong_chat->token,'test_time'=>time()];
                DB::table('users')->where('user_id',$user->user_id)->update($data);
            }
        }
//        $this->generateChatToekn();
    }
    function generateChatToekn()
    {

        $serverapi = new \ServerAPI();
        $path = 'headimg/default.png';
        $headimg = strpos_domain($path);
        $ids = DB::table('users')->where('chat_token','')->offset(0)->limit(60)->pluck('user_id');
        $num = count($ids);
        $suNum = 0;
        $guNUm = 0;
        $errorNum = 0;
        for($i = 0;$i < $num;$i ++){
            $nickname = '创新美'.rand(10000,99999);
            $rong_chat = $serverapi->getToken($ids[$i], $nickname, $headimg);
            $rong_chat = json_decode($rong_chat);
            if ($rong_chat->code == 200) {
                $chat_token = $rong_chat->token;
                $update_data['chat_token'] = $chat_token;
                $update_data['nickname'] = $nickname;
                DB::table('users')->where('user_id',$ids[$i])->update($update_data);
//                $group_chats = DB::table('group_chat')->select('gc_id','user_id','gc_uid', 'gc_title')->where('gc_delete',1)->get()->toArray();
//                $suNum += 1;
//                $group_chat = getRandGroup($group_chats);
//                if ($group_chat) {
//
//                    $gc_uid = json_decode($group_chat->gc_uid, true);
//
//                    $ret = $serverapi->groupJoin([$ids[$i]], $group_chat->gc_id, $group_chat->gc_title);
//
//                    $ret = json_decode($ret, TRUE);
//
//                    $ret['code'] = '200';
//
//                    if ($ret && $ret['code'] == '200') {
//                        $gc_uid[] = $ids[$i];
//                        $group_data = ['gc_uid' => json_encode($gc_uid)];
//                        DB::table('group_chat')->where('gc_id', $group_chat->gc_id)->update($group_data);
//                        $guNUm += 1;
//                        $this->joinGroupNoti($group_chat->gc_id, $group_chat->user_id, '群主', $ids[$i], $nickname);
//                    }
//                }
            }else{
                $errorNum += 1;
            }
        }

        $data = [
            'rongchat_num'=>$suNum,
            'groupchat_num'=>$guNUm,
            "errorNum"=>$errorNum

        ];

    }
    function joinGroupNoti($gc_id, $operatorUserId, $operatorNickname, $targetUserIds, $targetUserDisplayNames)
    {


        $content = array(
            'operatorUserId' => $operatorUserId,
            'operation' => 'Add',
            'data' => array(
                'operatorNickname' => $operatorNickname,
                'targetUserIds' => [(string)$targetUserIds],
                'targetUserDisplayNames' => [$targetUserDisplayNames]
            ),
            "message" => $targetUserDisplayNames . "已经进群了",
            'extra' => ''

        );
        $content = json_encode($content);

        $serverapi = new \ServerAPI();
        $ret =  $serverapi->messageGroupPublish((string)$operatorUserId,(string)$gc_id, 'RC:GrpNtf', $content);
//        dd($ret);
    }
}
