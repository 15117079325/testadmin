<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class XinbaodanzuihouluojiListener
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
        $beg_time = strtotime(date('Y-m-d'));
        $end_time = strtotime("+1 day");
        $sql = "SELECT foid as id,user_id,SUM(amount) as num FROM xm_flow_log WHERE is_shiyong=0 AND create_at>='{$beg_time}' AND create_at<'{$end_time}' AND is_baodan=1 AND status=1 GROUP BY user_id";
        $res = DB::select($sql);
        $res = array_column($res, null, 'user_id');
        $selectAccount = DB::table('mq_users_extra')->select('user_id', 'invite_user_id')->get()->toArray();
        $selectAccount = array_column($selectAccount, null, 'user_id');
        $insert = [];
        foreach ($res as $k => $v) {
            if (isset($selectAccount[$v->user_id])) {
                $insert[$k]['insert'] = $selectAccount[$v->user_id]->invite_user_id;
                $insert[$k]['amount'] = substr($v->num / 2, 0, strrpos($v->num / 2, '.')) . substr($v->num / 2, strrpos($v->num / 2, '.'), 3);
                $insert[$k]['id'] = $v->id;
            }
        }
        DB::beginTransaction();
        try {
            foreach ($insert as $k => $v) {
                list($usec, $sec) = explode(" ", microtime());
                $millisecond = ((float)$usec + (float)$sec);
                $millisecond = str_pad($millisecond, 3, '0', STR_PAD_RIGHT);
                $msectime = substr($millisecond, 0, strrpos($millisecond, '.')) . substr($millisecond, strrpos($millisecond, '.') + 1);
                $flow = DB::table('user_account')->where(['user_id' => $v['insert']])->first();
                if ($flow->release_balance < $v['amount']) {
                    continue;
                }
                $insertFlowJian['user_id'] = $v['insert'];
                $insertFlowJian['type'] = 3;
                $insertFlowJian['status'] = 2;
                $insertFlowJian['amount'] = $v['amount'];
                $insertFlowJian['surplus'] = $flow->release_balance - $v['amount'];
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

                $insertFlowJia['user_id'] = $v['insert'];
                $insertFlowJia['type'] = 2;
                $insertFlowJia['status'] = 1;
                $insertFlowJia['amount'] = $v['amount'];
                $insertFlowJia['surplus'] = $flow->balance + $v['amount'];
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
                DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$v['amount'], $v['amount'], time(), $v['insert']]);
                $upFlowLog['foid'] = $v['id'];
                $upFlowLog['is_shiyong'] = 1;
                DB::table('flow_log')->where(['foid'=>$upFlowLog['foid']])->update($upFlowLog);
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
