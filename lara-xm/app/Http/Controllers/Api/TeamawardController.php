<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class TeamawardController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    /**
     * description:团队奖页面
     * @author douhao
     * @date 2018/8/21
     */
    public function awardDetail(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        //获取配置
        $config = $this->get_gold_config();
        $arr = explode(':', $config['people_gold_1']);
        $arr2 = explode(':', $config['people_gold_2']);
        $arr3 = explode(':', $config['people_gold_3']);
        $arr4 = explode(':', $config['people_gold_4']);
        $tips = "温馨提示:\n1.自己必须有回购中的H单\n2.兑换时间:每月可申请兑换一次\n3.直推人数必须达到{$config['people_direct_num']},且{$config['people_direct_num']}人必须都有H单在回购中,兑换比例为{$arr[1]}%\n4.兑换规则：{$arr2[0]}个直推有H单在回购中，则可以兑换{$arr2[1]}%，（{$arr3[0]}人，{$arr3[1]}%，{$arr4[0]}人以上，{$arr4[1]}%）";
        //获取用户是否有未审核的兑换申请
        $ret1 = DB::table('gold_to_tp_log')->where(['user_id' => $user_id])->orderBy('create_time', 'desc')->limit(1)->pluck('create_time')->first();
        //获取本月的第一天以及最后一天
        $firstday = date('Y-m-01', strtotime(date("Y-m-d")));//本月第一天
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));//本月最后一天
        $firstday1 = strtotime($firstday);
        $lastday1 = strtotime($lastday) + 86399;
        if ($ret1) {
            if ($ret1 > $firstday1 && $ret1 < $lastday1) {
                $take_count = 1;
            } else {
                $take_count = 0;
            }
        } else {
            $take_count = 0;
        }
        //团队人数未达标
        $result_msg = $this->judge_people_gold($user_id);
        $result_msg2 = $this->judge_H_xinmei($user_id);
        if ($result_msg['code'] == 2) {
            $msg = $result_msg['msg'];
        } else {
            if ($result_msg2['code'] == 2) {
                $msg = $result_msg2['msg'];
            } else {
                $msg = '';
            }
        }
        //查看用户团队奖
        $ret = DB::table('tps')->where('user_id', $user_id)->pluck('gold_pool')->first();
        $ret = empty($ret) ? 0 : $ret;
        $data = [
            'tips' => $tips,
            'money' => $ret,
            'take_count' => $take_count,
            'msg' => $msg,
        ];
        success($data);
    }

    /**
     * description:申请兑换
     * @author douhao
     * @date 2018/8/21
     */
    public function awardApply(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        //查询是否有奖励金
        $ret = DB::table('tps')->where('user_id', $user_id)->first();
        if (empty(intval($ret->gold_pool))) {
            return error('80001', '没有可兑换奖金');
        }
        //获取用户是否有未审核的兑换申请
        $ret1 = DB::table('gold_to_tp_log')->where(['user_id' => $user_id])->orderBy('create_time', 'desc')->limit(1)->pluck('create_time')->first();
        //获取本月的第一天以及最后一天
        $firstday = date('Y-m-01', strtotime(date("Y-m-d")));//本月第一天
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));//本月最后一天
        $firstday1 = strtotime($firstday);
        $lastday1 = strtotime($lastday) + 86399;
        if ($ret1) {
            if ($ret1 > $firstday1 && $ret1 < $lastday1) {
                return error('80002', '兑换申请每月只能申请一次哦');
            }
        }

        $data = $this->update_gold_tp($ret, $user_id);
        if ($data['code'] == 2) {
            return error('80003', $data['msg']);
        }
        success($data);
    }

    protected function update_gold_tp($ret, $user_id)
    {
        $data = $this->get_gold_num($ret);
        $now = time();
        DB::beginTransaction();
        if ($data['code'] == 1) {
            $flag = DB::update('update xm_tps set unlimit = unlimit + ?,gold_pool = gold_pool - ? WHERE user_id = ?', [$data['money'], $data['money'], $data['user_id']]);
            $gold_money = DB::table('tps')->where('user_id', $data['user_id'])->first();
            if ($flag) {
                $part = [
                    'user_id' => $user_id,
                    'money' => $data['money'],
                    'create_time' => time(),
                ];
                $base_data = [
                    'user_id' => $user_id,
                    'status' => 1,
                    'create_at' => $now,
                    'type' => 2,
                    'amount' => $data['money'],
                    'surplus' => $gold_money->unlimit,
                    'notes' => '每月团队奖的' . $data['percent'] . '转入T积分',
                ];
                $foid1 = DB::table('flow_log')->insertGetId($base_data, 'foid');
                $base_data = [
                    'user_id' => $user_id,
                    'status' => 2,
                    'create_at' => $now,
                    'type' => 4,
                    'amount' => $data['money'],
                    'surplus' => $gold_money->gold_pool,
                    'notes' => '每月团队奖的' . $data['percent'] . '转入T积分',
                ];
                $foid2 = DB::table('flow_log')->insertGetId($base_data, 'foid');
                //插入兑换记录
                $foid3 = DB::table('gold_to_tp_log')->insertGetId($part, 'id');
                if (empty($foid1) || empty($foid2) || empty($flag) || empty($foid3)) {
                    DB::rollBack();
                    return ['code' => 2, 'msg' => '兑换申请失败，请稍后再试'];
                } else {
                    DB::commit();
                    return ['code' => 1, 'msg' => '兑换申请成功'];
                }
            } else {
                return ['code' => 2, 'msg' => '兑换申请失败，请稍后再试'];
            }
        } else {
            return $data;
        }
    }

    /*
     * 查出哪些人奖金池是余额,并符合条件
     */
    protected function get_gold_num($ret)
    {
        $flag = $this->judge_H_xinmei($ret->user_id);
        if ($flag['code'] == 1) {
            $result = $this->judge_people_gold($ret->user_id);
            if ($result['code'] == 1) {
                $money = (($ret->gold_pool * $result['msg']) / 100) < $ret->gold_pool ? ($ret->gold_pool * $result['msg']) / 100 : $ret->gold_pool;
                $data = [
                    'code' => 1,
                    'user_id' => $ret->user_id,
                    'money' => number_format($money, 2, '.', ''),
                    'percent' => $result['msg'] . "%",
                ];
                return $data;
            } else {
                return $result;
            }
        } else {
            return $flag;
        }
    }

    /*
     * 判断直推人数有多少在回购中，计算出提现比例
     */
    protected function judge_people_gold($user_id)
    {
        //判断是否有H单回购中
        $config = $this->get_gold_config();
        //判断是否直推人数是否达标
        $direct_ret = DB::table('mq_users_extra')->where('invite_user_id', $user_id)->count();
        $direct_ret = empty($direct_ret) ? 0 : $direct_ret;
        if ($direct_ret < $config['people_direct_num']) {
            return ['code' => 2, 'msg' => '对不起！直推人数未达标，无法申请兑换,请继续加油哦'];
        }

        $ret = DB::select("SELECT count(DISTINCT(b.user_id)) as count_name FROM `xm_mq_buy_back` b 
            LEFT JOIN `xm_mq_users_extra` e ON b.user_id=e.user_id 
            WHERE  b.bb_status>0 AND b.bb_status<3 AND e.`invite_user_id` = {$user_id}");
        $ret = empty($ret[0]->count_name) ? '0' : $ret[0]->count_name;
        $arr = explode(':', $config['people_gold_1']);
        $arr2 = explode(':', $config['people_gold_2']);
        $arr3 = explode(':', $config['people_gold_3']);
        $arr4 = explode(':', $config['people_gold_3']);
        if ($ret < $arr[0]) {
            return ['code' => 2, 'msg' => '对不起！直推购买H单未达标，无法申请兑换'];
        } elseif ($arr[0] <= $ret && $ret < $arr2[0]) {
            return ['code' => 1, 'msg' => $arr[1]];
        } elseif ($arr2[0] <= $ret && $ret < $arr3[0]) {
            return ['code' => 1, 'msg' => $arr2[1]];
        } elseif ($arr3[0] <= $ret && $ret < $arr4[0]) {
            return ['code' => 1, 'msg' => $arr3[1]];
        } elseif ($arr4[0] <= $ret) {
            return ['code' => 1, 'msg' => $arr4[1]];
        } else {
            return ['code' => 2, 'msg' => '对不起！直推购买H单未达标，无法申请兑换'];
        }
    }

    /*
     * 判断自己是否有H单在回购中，购买新美积分是否不低于10000
     */
    protected function judge_H_xinmei($user_id)
    {
        //判断是否有H单回购中
        $config = $this->get_gold_config();
        $where = [
            ['user_id', '=', $user_id],
            ['bb_status', '>', 0],
            ['bb_status', '<', 3]
        ];
        $ret = DB::table('mq_buy_back')->where($where)->count();
        $ret1 = DB::table('mq_buy_back')->where($where)->groupBy('user_id')->sum('cash_money');
        if ($ret && $ret1 >= $config['people_xinmei_min']) {
            return ['code' => 1, 'msg' => ''];;
        } else {
            return ['code' => 2, 'msg' => "对不起!自己的账号购买H单不能低于{$config['people_xinmei_min']}，请及时购买"];
        }
    }

    /*
     * 获取相应的配置
     */
    protected function get_gold_config()
    {

        $ret = DB::table('master_config')->select('code', 'value')->where('tip', 'g')->get();
        $config = [];
        foreach ($ret as $k => $v) {
            $config[$v->code] = $v->value;
        }
        return $config;
    }

}
