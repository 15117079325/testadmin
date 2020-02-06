<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SignnewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sign:newcount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '新签到逻辑计算';

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
        //
        //获取配置信息
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $signNum = explode(",", $distriBution['sign_num']->value);
        $signArr = [];
        array_map(function ($item) use ($distriBution, &$signArr) {
            if (!isset($distriBution[$item])) {
                return;
            }
            $distr = explode("/", $distriBution[$item]->value);
            $signArr[$item]['up'] = $distr[0];
            $signArr[$item]['drow'] = $distr[1];
            $signArr[$item]['section'] = explode("-", $distr[2]);
        }, $signNum);

        $users = DB::table('users')->select("user_id", "cut_wigth")->where(['locked' => 0, 'is_new' => 1])->get()->toArray();
        $user = array_column($users, null, 'user_id');
        //统计业绩，做升档准备
        $tpSql = "SELECT user_id,SUM(tp_num) AS num,tp_top_user_ids FROM xm_trade_performance WHERE is_sign=? GROUP BY user_id";
        $tpResults = DB::select($tpSql, [0]);
        $tpUserKey = '';
        $checkTpResults = [];
        foreach ($tpResults as $tpr => $tpv) {
            $checkTpResults[$tpv->user_id] = explode(",", $tpv->tp_top_user_ids);
            $tpUserKey .= $tpv->tp_top_user_ids . ',';
        }
        $tpUserKeyArr = explode(",", substr($tpUserKey, 0, -1));
        $tpUserKeyArr = array_unique($tpUserKeyArr);

        $tpResult = array_column($tpResults, null, 'user_id');

        $tpResultArr = [];
        foreach ($tpUserKeyArr as $tpUserKey => $tpUserVal) {
            foreach ($checkTpResults as $lk => $lv) {
                if (in_array($tpUserVal, $lv)) {
                    if (isset($tpResultArr[$tpUserVal])) {
                        $tpResultArr[$tpUserVal]->num = $tpResultArr[$tpUserVal]->num + $tpResult[$lk]->num;
                    } else {
                        $tpResultArr[$tpUserVal] = new \stdClass();
                        $tpResultArr[$tpUserVal]->user_id = $tpUserVal;
                        $tpResultArr[$tpUserVal]->num = $tpResult[$lk]->num;
                        $tpResultArr[$tpUserVal]->tp_top_user_ids = 123;
                    }
                }
            }
        }
        unset($tpResult);
        $tpResult = $tpResultArr;
        foreach ($tpResult as $k => &$item) {
            if (!isset($user[$item->user_id]->cut_wigth)) {
                unset($tpResult[$k]);
                continue;
            }
            $item->cut_wigth = $user[$item->user_id]->cut_wigth;
            $item->par_wigth = array_search($user[$item->user_id]->cut_wigth, $signNum) - 1 < 0 ? $user[$item->user_id]->cut_wigth : $signNum[array_search($user[$item->user_id]->cut_wigth, $signNum) - 1];
        }
        unset($item);
        $userUpdateUp = [];
        $tpUpdate = [];
        foreach ($tpResult as $value) {
            if ($value->par_wigth == $value->cut_wigth) {
                continue;
            }
            if (isset($signArr[$value->par_wigth])) {
                if ($signArr[$value->par_wigth]['up'] <= $value->num) {
                    $tpUpdate[$value->user_id]['user_id'] = $value->user_id;
                    $tpUpdate[$value->user_id]['is_sign'] = 1;
                    $userUpdateUp[$value->user_id]['user_id'] = $value->user_id;
                    $userUpdateUp[$value->user_id]['cut_wigth'] = $value->par_wigth;
                } else {
                    continue;
                }
            }
        }
        //签到下降
        $signSql = "SELECT user_id,SUM(amount) AS num FROM xm_flow_log WHERE `target_type`=? AND `status`=? AND `is_sign`=? GROUP BY user_id";
        $signResult = DB::select($signSql, [4, 1, 0]);
        $signResult = array_column($signResult, null, 'user_id');

        foreach ($signResult as $k => &$item) {
            if (!isset($user[$item->user_id]->cut_wigth)) {
                unset($signResult[$k]);
                continue;
            }
            $item->cut_wigth = $user[$item->user_id]->cut_wigth;
            $item->par_wigth = array_search($user[$item->user_id]->cut_wigth, $signNum) + 1 >= count($signNum) ? $user[$item->user_id]->cut_wigth : $signNum[array_search($user[$item->user_id]->cut_wigth, $signNum) + 1];
        }
        unset($item);
        $userUpdateDown = [];
        foreach ($signResult as $value) {
            if ($value->par_wigth == $value->cut_wigth) {
                continue;
            }
            if (isset($signArr[$value->par_wigth])) {
                if ($signArr[$value->cut_wigth]['drow'] != 0 && $signArr[$value->cut_wigth]['drow'] <= $value->num) {
                    $userUpdateDown[$value->user_id]['user_id'] = $value->user_id;
                    $userUpdateDown[$value->user_id]['cut_wigth'] = $value->par_wigth;
                } else {
                    continue;
                }
            }
        }

        $userUpdate = [];
        foreach ($userUpdateUp as $k => $v) {
            if (!isset($userUpdateDown[$k])) {
                $userUpdate[$k] = $v;
            } else {
                unset($userUpdateDown[$k]);
            }
        }
        $userUpdate = $userUpdate + $userUpdateDown;
        foreach ($userUpdate as $userList) {
            DB::table("users")->where(['user_id' => $userList['user_id']])->update($userList);
            DB::table("flow_log")->where(['user_id' => $userList['user_id']])->update(['is_sign' => 1]);
        }
        foreach ($tpUpdate as $tpUpdates) {
            DB::table("trade_performance")->where(['user_id' => $tpUpdates['user_id']])->update($tpUpdates);
            DB::table("flow_log")->where(['user_id' => $tpUpdates['user_id']])->update(['is_sign' => 1]);
        }
        exit("同步用户签到档位信息完成" . PHP_EOL);

    }
}
