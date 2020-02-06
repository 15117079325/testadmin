<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class XinbaodanluojiListener
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
    public function handle($event)
    {
        //
        $custom = DB::table('customs_order')->where(['is_ziji' => 0])->get()->toArray();
        $customsjisuan = DB::table('customs_order')->where(['is_shiyong' => 0])->get()->toArray();
        $arr = [];
        foreach ($custom as $k => $v) {
            $arr[$v->user_id][] = $v;
        }
        $arrjisuan = [];
        foreach ($customsjisuan as $k => $v) {
            $arrjisuan[$v->user_id][] = $v;
        }
        //每个用户的可用报单余额
        $user_arr = [];
        foreach ($arr as $k => $v) {
            $user_arr[$k] = 0;
            foreach ($v as $kk => $vv) {
                $user_arr[$k] += $vv->customs_money;
            }
        }

        $user_arrjisuan = [];
        foreach ($arrjisuan as $k => $v) {
            $user_arrjisuan[$k] = 0;
            foreach ($v as $kk => $vv) {
                $user_arrjisuan[$k] += $vv->customs_money;
            }
        }

        $mq_users_extra = DB::table('mq_users_extra')->get()->where('invite_user_id', '!=', '0')->toArray();
        $users_extra_arr = [];
        foreach ($mq_users_extra as $k => $v) {
            $users_extra_arr[$v->invite_user_id][] = $v->user_id;
        }
        //报单金额
        $userzongjine = [];
        foreach ($users_extra_arr as $ke => $v) {
            $userzongjine[$ke] = 0;
            foreach ($v as $kk => $vv) {
                $userzongjine[$ke] += isset($user_arr[$vv]) ? $user_arr[$vv] : 0;

            }

        }

        $insertArray = [];
        foreach ($userzongjine as $k => $v) {
            if (isset($user_arrjisuan[$k]) && $user_arrjisuan[$k] <= $v) {
                if ($v >= 1000 && $v <= 5000) {
                    $insertArray[$k] = 1000 * 2 * 0.003;
                } elseif ($v >= 5000 && $v <= 10000) {
                    $insertArray[$k] = 5000 * 2.5 * 0.003;
                } elseif ($v >= 10000) {
                    $insertArray[$k] = 10000 * 3 * 0.003;

                }
            }
        }
        //区分百分之七十百分之三十
        $updateArray = [];

        foreach ($insertArray as $k => $v) {
            $updateArray[$k]['big'] = $v * 0.7;
            $updateArray[$k]['small'] = $v - $updateArray[$k]['big'];
        }
        //修改做废custom
        $feiqicustom = [];
        foreach ($updateArray as $k => $v) {
            if (isset($users_extra_arr[$k])) {
                foreach ($users_extra_arr[$k] as $kk => $vv) {
                    $feiqicustom[] = $vv;
                }
            }
        }
        DB::beginTransaction();
        try {
            foreach ($updateArray as $key => $v) {
                list($usec, $sec) = explode(" ", microtime());
                $millisecond = ((float)$usec + (float)$sec);
                $millisecond = str_pad($millisecond, 3, '0', STR_PAD_RIGHT);
                $msectime = substr($millisecond, 0, strrpos($millisecond, '.')) . substr($millisecond, strrpos($millisecond, '.') + 1);
                $flow = DB::table('user_account')->where(['user_id' => $key])->first();
                if ($flow->release_balance < $v['big']) {
                    continue;
                }
                $insertFlowJian['user_id'] = $key;
                $insertFlowJian['type'] = 3;
                $insertFlowJian['status'] = 2;
                $insertFlowJian['amount'] = $v['big'];
                $insertFlowJian['surplus'] = $flow->release_balance - $v['big'];
                $insertFlowJian['notes'] = '';
                $insertFlowJian['create_at'] = time();
                $insertFlowJian['sign_time'] = 0;
                $insertFlowJian['isall'] = 1;
                $insertFlowJian['target_id'] = '';
                $insertFlowJian['target_type'] = 2;
                $insertFlowJian['msectime'] = $msectime;
                $insertFlowJian['is_prize'] = 0;
                $insertFlowJian['is_sign'] = 0;
                $insertFlowJian['is_baodan'] = 1;
                DB::table('flow_log')->insert($insertFlowJian);

                $insertFlowJia['user_id'] = $key;
                $insertFlowJia['type'] = 2;
                $insertFlowJia['status'] = 1;
                $insertFlowJia['amount'] = $v['big'];
                $insertFlowJia['surplus'] = $flow->balance + $v['big'];
                $insertFlowJia['notes'] = '';
                $insertFlowJia['create_at'] = time();
                $insertFlowJia['sign_time'] = 0;
                $insertFlowJia['isall'] = 1;
                $insertFlowJia['target_id'] = '';
                $insertFlowJia['target_type'] = 2;
                $insertFlowJia['msectime'] = $msectime;
                $insertFlowJia['is_prize'] = 0;
                $insertFlowJia['is_sign'] = 0;
                $insertFlowJia['is_baodan'] = 1;
                DB::table('flow_log')->insert($insertFlowJia);
                DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,bi_balance =  bi_balance + ?,update_at = ? WHERE user_id = ?', [$v['big'], $v['big'], $v['small'], time(), $key]);
                $userIds = "'" . implode("','", $feiqicustom) . "'";
                DB::update('UPDATE xm_customs_order SET is_shiyong=1 WHERE user_id IN (' . $userIds . ')');
                echo "操作用户ID{$key}完毕";
                echo PHP_EOL;
            }
            echo "操作完毕";
            echo PHP_EOL;
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            echo 'error';
        }


    }
}
