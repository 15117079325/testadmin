<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use App\Events\GqchoujiangEvent;

class GqchoujiangListener
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
     * @param object $event
     * @return void
     */
    public function handle(GqchoujiangEvent $event)
    {
        //
        $orderId = $event->orderId;
        $userInfos = DB::table('luck_draw_user')->get()->toArray();
        $userInfo = array_column($userInfos, null, 'user_id');

        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');

        $customOrder = DB::table('customs_order')->selectRaw("SUM(`cash_money`) as num,user_id")->where('create_at', ">=", strtotime($distriBution['luck_begin_time']->value))->groupBy('user_id')->get()->toArray();
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $updateCode = explode("/", $distriBution['luck_opportunity']->value);
        foreach ($updateCode as $k => $v) {
            $updateCode[$k] = explode(",", $v);
        }

        $goodsArr = DB::table('luck_goods_draw')->get()->toArray();
        $goodsArr = array_column($goodsArr, null, 'goods_code_luck');
        $updateArr = [];
        $instllArr = [];
        foreach ($customOrder as $k => $v) {
            foreach ($updateCode as $item) {
                if ($v->num >= $item[1]) {
                    if (isset($goodsArr[$item[0]]->id)) {
                        if (isset($userInfo[$v->user_id]->goods_id_luck)) {
                            $updateArr[$v->user_id][] = $goodsArr[$item[0]]->id;

                        } else {
                            $instllArr[$v->user_id][] = $goodsArr[$item[0]]->id;
                        }
                    }
                }
            }
            if (isset($userInfo[$v->user_id]->goods_id_luck) && $userInfo[$v->user_id]->goods_id_luck != 0) {
                $updateArr[$v->user_id][] = $userInfo[$v->user_id]->goods_id_luck;
            }
        }
        foreach ($updateArr as $k => $item) {
            $updateArr[$k] = implode(",", $item);
        }
        foreach ($updateArr as $k => $item) {
            $updateArr[$k] = explode(",", $item);
        }

        foreach ($updateArr as $k => $item) {
            $updateArr[$k] = array_unique($item);

        }
        foreach ($updateArr as $k => $item) {
            DB::table('luck_draw_user')->where('user_id', $k)->update(['goods_id_luck' => implode(",", $item)]);
        }

    }
}
