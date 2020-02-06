<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StatisController extends Controller
{
    //
    public function __construct()
    {
//        $this->middleware('userLoginValidate');
    }

    /**
     * @param Request $request
     * 获取统计次数
     */
    public function index(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $statisInfos = DB::table('statis_imgs')->select('title', 'code', 'value')->get()->toArray();
        foreach ($statisInfos as &$statisInfo) {
            $statisInfo->value = array_values(json_decode($statisInfo->value, true));
        }
        unset($statisInfo);
        //火粉社区
        $tradePowCount = DB::table("trade_detail")->selectRaw("SUM(td_num) as num")->where(['is_status' => 0])->first();
        $tradeProCount = DB::table("trade_detail")->selectRaw("SUM(td_platform_num) as num")->where(['is_status' => 0])->first();
        //累计注册用户
        $registerCount = DB::table("users")->distinct('user_id')->count();
        //剩余待释放优惠券
        $accountCount = DB::table('user_account')->selectRaw("SUM(release_balance) as releaseNum,SUM(balance) as balanceNum")->first();
        $customCount = DB::table('customs_order')->selectRaw("SUM(cash_money) as cashNum")->first();
        $accountCount->signCount = DB::table('flow_log')->where(['target_type' => 4, 'status' => 1])->count();
        $tdCount = DB::table('trade_detail')->selectRaw("SUM(td_num) as tdNum")->where(['td_status' => 1])->first();
        $tCount = DB::table('trade')->selectRaw("SUM(trade_num) as tNum")->where(['trade_status' => 1])->first();
        $accountCount->balanceNum = sprintf('%.0f', $accountCount->balanceNum + $tdCount->tdNum + $tCount->tNum);
        $accountCount->userCount = sprintf('%.0f', $registerCount);
        $accountCount->customCount = sprintf('%.0f', $customCount->cashNum);
        $accountCount->tradePow = sprintf('%.0f', $tradePowCount->num);
        $accountCount->tradePro = sprintf('%.0f', $tradeProCount->num);
        //balanceNum 总可用券
        //releaseNum 总待释放可用
        //signCount 总签到数
        //userCount 总注册人数
        //customCount 报单金额
        $statisInfo = array_column($statisInfos, null, 'code');
        $statisInfo['countNum'] = $accountCount;
        success($statisInfo);
    }
}
