<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class SignController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    public function sign(Request $request)
    {
        $user_id = $request->input('user_id');
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $redis = app('redis.connection');
        $signNum = $redis->hgetall("sign:prize");
        //获取用户待释放的优惠券
        $customs_order = DB::table('customs_order')->where([['user_id', $user_id], ['status', 1]])->get();
        //如果发现有待释放的优惠券才会继续进行
        if (count($customs_order) <= 0) {
            return error('00000', '暂无待释放优惠券');
        }

        $num = isset($signNum[$user_id]) ? $signNum[$user_id] : 0;

        $flow_log = DB::table('flow_log')->where([['user_id', $user_id], ['target_type', 4]])->orderBy('sign_time', 'desc')->first();
        if (isset($flow_log)) {
            //计算今天是否已经签到
            if (date('Ymd', time()) == date('Ymd', $flow_log->sign_time)) {
                return error('20004', '今天已经签到过了');
            }
        }

        //查询用户待释放优惠券
        $user_release_balance = DB::table('user_account')->where('user_id', $user_id)->first();
        if ($num == 0) {
            $user = DB::table('users')->where('user_id', $user_id)->first();
            $wigth = $user->cut_wigth;
            $distriButions = DB::table('master_config')->get()->toArray();
            $distriBution = array_column($distriButions, null, 'code');
            $distr = explode("/", $distriBution[$wigth]->value);
            $signArr['section'] = explode("-", $distr[2]);
            $timeCheck = date("Ymd", strtotime("-1 day"));
            //临时用有数据的时间做判断
//            $timeCheck = '20190906';
            $status = 1;
            $sql = "SELECT user_id,SUM(release_balance) AS num FROM xm_customs_order WHERE status=? GROUP BY user_id";
            $cusResults = DB::select($sql, [$status]);
            $cusResult = array_column($cusResults, null, 'user_id');
            $roundNum = rand(($signArr['section'][0] * 100), (($signArr['section'][1]) * 100)) * 0.01;
            $insertRedis[$user_id] = sprintf("%.2f", $cusResult[$user_id]->num * ($roundNum * 0.01));
            $num = $insertRedis[$user_id];
        }

        if ($user_release_balance->release_balance <= 0) {
            DB::table('customs_order')->where('user_id', $user_id)->update(['status' => 2]);
            return error('00000', '暂无待释放优惠券');
        }
        if ($user_release_balance->release_balance < $num) {
            list($customNum, $updateData) = update_custom($user_id, $user_release_balance->release_balance);
        } else {

            list($customNum, $updateData) = update_custom($user_id, $num);
        }

        DB::beginTransaction();
        try {
            addDrawcont(2, $user_id);
            //更新待释放优惠券
            DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$customNum, $customNum, time(), $user_id]);
            //签到的优惠券全部释放完更新报单的状态
            foreach ($updateData as $k => $updateDatas) {
                DB::table('customs_order')->where('co_id', $k)->update($updateDatas);
            }
            //余额流水
            $flow_data = [
                'user_id' => $user_id,
                'type' => 2,
                'status' => 1,
                'amount' => $customNum,
                'surplus' => $user_release_balance->balance + $customNum,
                'notes' => '签到收入',
                'create_at' => time(),
                'sign_time' => time(),
                'target_type' => 4,
            ];
            DB::table('flow_log')->insert($flow_data, 'foid');

            //待释放余额流水
            $flow_data = [
                'user_id' => $user_id,
                'type' => 3,
                'status' => 2,
                'amount' => $customNum,
                'surplus' => $user_release_balance->release_balance - $customNum,
                'notes' => '签到支出',
                'create_at' => time(),
                'sign_time' => time(),
                'target_type' => 4,
            ];
            DB::table('flow_log')->insert($flow_data, 'foid');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return error('10000', '签到失败');
        }
        success(['money' => $customNum]);
    }
}
