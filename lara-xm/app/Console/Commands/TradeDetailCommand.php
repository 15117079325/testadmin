<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TradeDetailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:trade-detail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '24小时自动确认转账';

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
       $this->autoCancel();
       $this->autoConfirm();
    }

    private function autoCancel()
    {
        $now = time();
        $where = [
            ['td_status', 0],
            ['create_at', '<=', $now - AUTO_CANCEL_TRADE]
        ];
        $tradeDetails = DB::table('trade_detail')->where($where)->limit(20)->offset(0)->get();
        foreach ($tradeDetails as $tradeDetail) {
//            //查询出售信息
//            $trade = DB::table('trade')->where('trade_id', $tradeDetail->t_id)->first();
//            //查询当前出售用户的优惠券
//            $sellerAccount = DB::table('user_account')->where('user_id', $tradeDetail->seller_id)->first();

            DB::beginTransaction();
//            if ($trade && $trade->origin_trade_num == $tradeDetail->td_num) {
//                DB::table('trade')->where('trade_id', $tradeDetail->t_id)->update(['trade_status' => 3]);
//            }


//            DB::table('user_account')->where('user_id', $tradeDetail->seller_id)->increment('balance', $tradeDetail->td_num + $tradeDetail->td_buy_num + $tradeDetail->td_platform_num);
//            DB::table('user_account')->where('user_id', $tradeDetail->seller_id)->decrement('pending_balance', $tradeDetail->td_num + $tradeDetail->td_buy_num + $tradeDetail->td_platform_num);
            //标识是购买还是出售 (1购买  2出售)
            $status = $tradeDetail->is_status;
            if($status == 1) {
                $a = 1;
                //算出应该返回用户多少金额
                $money = $tradeDetail->td_num + $tradeDetail->td_platform_num + $tradeDetail->td_buy_num;
                //更新用户的优惠券
                DB::table('user_account')->where('user_id',$tradeDetail->seller_id)->increment('balance',$money);
                //更改用户锁定优惠券
                DB::table('user_account')->where('user_id',$tradeDetail->seller_id)->decrement('pending_balance',$money);
                //修改出售记录状态
                DB::table('trade')->where('trade_id',$tradeDetail->t_id)->update(['trade_status' => 3]);

            } else if($status == 2) {
                $money = $tradeDetail->td_num;
                //更新出售信息
                $trade = DB::table('trade')->where('trade_id',$tradeDetail->t_id)->increment('trade_num',$money);

            }
            //更改当前记录的状态
            $aff_row = DB::table('trade_detail')->where('td_id', $tradeDetail->td_id)->update(['td_status' => 5]);

            $flow_data = [
                'user_id' => $tradeDetail->seller_id,
                'type' => FLOW_LOG_TYPE_BALANCE,
                'status' => 1,
                'amount' => $money,
                'surplus' => 0,
                'notes' => '自动退还--' . ($money),
                'create_at' => $now,
                'target_type' => 3,
                'target_id' => $tradeDetail->td_id
            ];
            $foid = DB::table('flow_log')->insertGetId($flow_data, 'foid');

            if (empty($aff_row) || empty($foid)) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        }
    }
    private function autoConfirm()
    {
        $where = [
            ['td_status', 1],
            ['commit_at', '<=', time() - AUTO_CONFIRM_TRADE]
        ];
        $tradeDetails = DB::table('trade_detail')->where($where)->limit(20)->offset(0)->get();

        foreach ($tradeDetails as $tradeDetail) {
            $this->changeStatus($tradeDetail);
        }
    }
    private function changeStatus($tradeDetail)
    {
        $sellerAccount = DB::table('user_account')->where('user_id', $tradeDetail->seller_id)->first();
        $buyerAccount = DB::table('user_account')->where('user_id', $tradeDetail->buyer_id)->first();
        if (empty($sellerAccount) || empty($buyerAccount)){
            return true;
        }
        $trade = DB::table('trade')->where('trade_id', $tradeDetail->t_id)->first();
        if (empty($trade)) {
            return true;
        }
        $isOver = false;
        if ($trade->trade_num == 0) {
            $isOver = true;
        }
        $now = time();
        $update_data = [
            'td_status' => 2,
            'complete_at' => $now
        ];

        DB::beginTransaction();
        $aff_row = DB::table('trade_detail')->where('td_id', $tradeDetail->td_id)->update($update_data);
        if (empty($aff_row)) {
            DB::rollBack();
        }
        if ($isOver) {
            DB::table('trade')->where('trade_id', $tradeDetail->t_id)->update(['trade_status' => 2]);
        }
        DB::table('user_account')->where('user_id', $tradeDetail->seller_id)->decrement('pending_balance', $tradeDetail->td_num + $tradeDetail->td_platform_num + $tradeDetail->td_buy_num);
        DB::table('user_account')->where('user_id', $tradeDetail->buyer_id)->increment('balance', $tradeDetail->td_num + $tradeDetail->td_buy_num);

        $flow_data = [
            'user_id' => $tradeDetail->seller_id,
            'type' => FLOW_LOG_TYPE_BALANCE,
            'status' => 2,
            'amount' =>$tradeDetail->td_num + $tradeDetail->td_buy_num + $tradeDetail->td_platform_num,
            'surplus' => $sellerAccount->balance,
            'notes' => '出售给' . $tradeDetail->buyer_user_name . '--' . $tradeDetail->td_num,
            'create_at' => $now,
            'target_type' => 3,
            'target_id' => $tradeDetail->td_id

        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        $flow_data['user_id'] = $tradeDetail->buyer_id;
        $flow_data['amount'] = $tradeDetail->td_num + $tradeDetail->td_buy_num;
        $flow_data['status'] = 1;
        $flow_data['surplus'] = $buyerAccount->balance + $tradeDetail->td_num + $tradeDetail->td_buy_num;
        $flow_data['notes'] = '购买--' . ($tradeDetail->td_num);
        $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        if (empty($foid1) || empty($foid2)) {
            DB::rollBack();
        } else {
            DB::commit();
        }
    }
}
