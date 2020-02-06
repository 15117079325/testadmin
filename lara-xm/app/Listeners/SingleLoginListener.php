<?php

namespace App\Listeners;

use App\Events\SingleLoginEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use ShaoZeMing\GeTui\Facade\GeTui;

class SingleLoginListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  SingleLoginEvent  $event
     * @return void
     */
    public function handle(SingleLoginEvent $event)
    {
        $user = $event->user;
        $clientId = $event->clientId;
        //发送通知
        if ($user && $user->clientid && strcmp($user->clientid, $clientId) !== 0) {
            $title = '系统消息';

            $content = '您的账号已被强制退出';

            $custom_content = ['id' => '', 'type' => '1', 'content' => $content,'title'=>$title];

            $push_data = array(
                'user_id' => $user->user_id,
                'm_type' => '1',
                'm_title' => $title,
                'm_read' => '1',
                'm_content' => $content,
                'm_gmt_create' => time()
            );
            $message_id = DB::table('message')->insertGetId($push_data, 'm_id');

            if ($message_id && $user->clientid) {
                DB::table('users')->where([['clientid', '=', $clientId], ['user_id', '<>', $user->user_id]])->update(['clientid' => '', 'device' => '']);
                GeTui::push($user->clientid,$custom_content,false);

            }

        }
    }
}
