<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class LotteryController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['lotteryRule']);
    }

    /**
     * description:开始抽奖
     * @author Harcourt
     * @date 2018/10/10
     */
    public function doStart(Request $request)
    {
        $user_id = $request->input('user_id',0);
        $lottery = $request->input('lottery');
        if(empty($user_id) || empty($lottery)){
            return error('00000','参数不全');
        }

        //1、苹果2、橙子3、菠萝4、西瓜5、双7 6、BAR
        $lottery = json_decode($lottery,true);

        $verification = new \Verification();
        $num = count($lottery);
        $target = ['pos','amount'];
        $total_cost = 0;
        for($i = 0;$i < $num;$i ++){
            if(!$verification->fun_array($lottery[$i],$target)){
                return error('99998','非法操作');
            }
            $total_cost += $lottery[$i]['amount'];
        }



        $consume_score = DB::table('tps')->where('user_id',$user_id)->value('shopp');
        if($consume_score == null){
            $consume_score = 0;
        }
        if($consume_score < $total_cost){
            return error('40014','余额不足');
        }


        if(count($lottery) == 6){
            $prize = $this->getRandPrize($lottery);
        }else{
            //区分用户等级
            $config = DB::table('master_config')->where('tip','f')->value('value');
            if($config == null){
                return error('99997','暂时无法操作');
            }

            $configArr = explode('|',$config);
            $config_num = count($configArr);
            if( $config_num != 2){
                return error('99997','暂时无法操作');
            }
            $firstArr = explode('-',$configArr[0]);
            $secondArr = explode('-',$configArr[1]);

            if(count($secondArr) != count($firstArr)+1){
                return error('99997','暂时无法操作');
            }

            array_push($firstArr,$consume_score);

            sort($firstArr);

            $key = array_search($consume_score,$firstArr);

            $rate = $secondArr[$key];

            $bol = $this->judgeNext($rate);
            if($bol){
                $prize = $this->getRandPrize($lottery);
            }else{
                $prize = $this->getLitterPrice($lottery);
            }
        }



        $redis_name = 'doStart-'.$user_id;

        if(Redis::exists($redis_name)){
            return error('99994','处理中...');
        }else{
            Redis::set($redis_name,'1');
        }

        $now = time();
        DB::beginTransaction();
        DB::update('UPDATE xm_tps SET shopp = shopp - ? WHERE user_id = ?',[$total_cost,$user_id]);
        $lottery_log = [
            'user_id'=>$user_id,
            'll_cost_money'=>$total_cost,
            'll_reward_money'=>$prize['prizeAmount'],
            'll_gmt_create'=>$now
        ];
        $llid = DB::table('lottery_logs')->insertGetId($lottery_log,'ll_id');
        if(empty($llid)){
            Redis::del($redis_name);
            DB::rollBack();
            return error('99999','操作失败');
        }
        $flow_data = [
            'user_id'=>$user_id,
            'type'=>3,
            'status'=>2,
            'amount'=>$total_cost,
            'surplus'=>$consume_score - $total_cost,
            'notes'=>'抽奖消耗消费积分'.$total_cost,
            'create_at'=>$now,
            'target_type'=>14,
        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data,'foid');
        if(empty($foid1)){
            Redis::del($redis_name);
            DB::rollBack();
            return error('99999','操作失败');
        }
        if($prize['iswin'] == '1'){
            $flow_data['status'] = 1;
            $flow_data['amount'] = $prize['prizeAmount'];
            $flow_data['surplus'] = $consume_score - $total_cost + $prize['prizeAmount'];
            $flow_data['notes'] = '中奖获得消费积分'.$prize['prizeAmount'];
            $foid2 = DB::table('flow_log')->insertGetId($flow_data,'foid');
            DB::update('UPDATE xm_tps SET shopp = shopp + ? WHERE user_id = ?',[$prize['prizeAmount'],$user_id]);

            if(empty($foid2)){
                Redis::del($redis_name);
                DB::rollBack();
                return error('99999','操作失败');
            }
        }
        Redis::del($redis_name);
        DB::commit();
        success($prize);


    }
    function getPrize1($rate,$lottery)
    {
        $prize_arrs = DB::table('lottery')->get()->map(function ($value){return (array)$value;})->toArray();
        $arr = array_column($prize_arrs,'lo_rate','lo_position');
        $actor = 100;
        $sum = array_sum($arr)*$actor;

        foreach ($arr as &$v) {
            $v = $v*$actor*$rate;
        }

        asort($arr);

        $rand = mt_rand(1,$sum);
        $result = ''; //中奖产品id
        foreach ($arr as $k => $x)
        {
            if($rand <= $x)
            {
                $result = $k;
                break;
            }
            else
            {
                $rand -= $x;
            }
        }

        $iswin = '0';
        $prizeAmount = '0';
        if($result - 1 < 0){
            $data = [
                'iswin'=>$iswin,
                'prizeAmount'=>$prizeAmount,
                'pos'=>'1',
            ];
            return $data;
        }

        $prize = $prize_arrs[$result-1];

        //按钮 1、苹果2、橙子3、菠萝4、西瓜5、双7 6、BAR
        $pos = $prize['lo_position'];
        $target = 0;
        if(in_array($pos,[4,7,8,15])){
            //苹果
            $target = 1;
        }elseif (in_array($pos,[1,9,10])){
            //橙子
            $target = 2;
        }elseif (in_array($pos,[11,12,16])){
            //菠萝
            $target = 3;
        }elseif (in_array($pos,[5,6])){
            //西瓜
            $target = 4;
        }elseif (in_array($pos,[13,14])){
            //双7
            $target = 5;
        }elseif (in_array($pos,[2,3])){
            //BAR
            $target = 6;
        }
        $posAmounts = array_column($lottery,'amount','pos');

        if(array_key_exists($target,$posAmounts)){
            $prizeAmount = $prize['lo_multiple'] * $posAmounts[$target];
            $iswin = '1';
        }
        $data = [
            'iswin'=>$iswin,
            'prizeAmount'=>$prizeAmount,
            'pos'=>$pos
        ];
        return $data;

    }

    function judgeNext($rate){
        $randNum = mt_rand(1, 100);
        if ($randNum <= $rate) {
            return true;
        } else {
            return false;
        }

    }
    function getLitterPrice($lottery){
        $pos = [6,8,10,12,14];//1,16,
        $prize_arrs = DB::table('lottery')->whereIn('lo_position',$pos)->get()->map(function ($value){return (array)$value;})->toArray();

        $key = array_rand($prize_arrs,1);
        $prize = $prize_arrs[$key];

        $res = $this->getResult($prize,$lottery);

        return $res;
    }
    function getRandPrize($lottery)
    {
        $prize_arrs = DB::table('lottery')->get()->map(function ($value){return (array)$value;})->toArray();
        $arr = array_column($prize_arrs,'lo_rate','lo_position');
//        $actor = 100;
//        $sum = array_sum($arr)*$actor;
//        foreach ($arr as &$v) {
//            $v = $v*$actor*$rate;
//        }



        asort($arr);

        $result = '';

        //概率数组的总概率精度
        $proSum = array_sum($arr);

        //概率数组循环
        foreach ($arr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }

        $iswin = '0';
        $prizeAmount = '0';
        if($result - 1 < 0){
            $data = [
                'iswin'=>$iswin,
                'prizeAmount'=>$prizeAmount,
                'pos'=>'1',
            ];
            return $data;
        }

        $prize = $prize_arrs[$result-1];

        $res = $this->getResult($prize,$lottery);

        return $res;


    }

    function getResult($prize,$lottery){
        //按钮 1、苹果2、橙子3、菠萝4、西瓜5、双7 6、BAR
        $pos = $prize['lo_position'];
        $target = 0;
        if(in_array($pos,[4,7,8,15])){
            //苹果
            $target = 1;
        }elseif (in_array($pos,[1,9,10])){
            //橙子
            $target = 2;
        }elseif (in_array($pos,[11,12,16])){
            //菠萝
            $target = 3;
        }elseif (in_array($pos,[5,6])){
            //西瓜
            $target = 4;
        }elseif (in_array($pos,[13,14])){
            //双7
            $target = 5;
        }elseif (in_array($pos,[2,3])){
            //BAR
            $target = 6;
        }
        $posAmounts = array_column($lottery,'amount','pos');
        if(array_key_exists($target,$posAmounts)){
            $prizeAmount = $prize['lo_multiple'] * $posAmounts[$target];
            $iswin = '1';
        }else{
            $iswin = '0';
            $prizeAmount = '0';
        }
        $data = [
            'iswin'=>$iswin,
            'prizeAmount'=>$prizeAmount,
            'pos'=>$pos
        ];
        return $data;
    }


    public function lotteryRecord(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $limit = 20;
        $offset = $limit * $page;
        $where = [
            'user_id' => $user_id,
        ];
        $res = DB::table('lottery_logs')->select('*')->where($where)->orderBy('ll_id', 'desc')->offset($offset)->limit($limit)->get();

        foreach ($res as $k => $v) {
            $v->ll_gmt_create = date("Y-m-d H:i",$v->ll_gmt_create);
        }
        success($res);
    }

    /**
     * description:抽奖规则
     * author:Harcourt
     * Date:2018/10/12 上午10:45
     */
    public function lotteryRule()
    {
       $content =  DB::table('trading_hall_explain')->where('type',5)->value('content');
        return view('api.description',['des'=>$content]);
    }


}
