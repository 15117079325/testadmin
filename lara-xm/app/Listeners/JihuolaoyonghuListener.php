<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\JihuolaoyonghuEvent;
use Illuminate\Support\Facades\DB;

class JihuolaoyonghuListener
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
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        //
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $customMoney = $distriBution['sign_custom_money']->value;
        $users = DB::table('users')->select("user_id", "cut_wigth")->where(['locked' => 0, 'is_new' => 0])->get()->toArray();
        $userId = array_column($users, 'user_id');
        $cusSql = DB::table('customs_order')->selectRaw("sum(`customs_money`) as num,user_id")->where(['status' => 1])->where("create_at", ">=", strtotime($distriBution['custom_time']->value))->groupBy('user_id')->get()->toArray();
        $updateUserId = array_column($cusSql, null, 'user_id');
        $defSign = $distriBution['sign_def']->value;
        $updateData = [];
        foreach ($updateUserId as $updateUserIds) {
            if ($updateUserIds->num >= $customMoney) {
                $updateData['cut_wigth'] = $defSign;
                $updateData['is_new'] = 1;
                DB::table("users")->where(['user_id' => $updateUserIds->user_id])->update($updateData);
//                DB::table("customs_order")->where(['user_id' => $updateUserIds->user_id])->update(['status' => 2]);
            }
        }
    }
}
