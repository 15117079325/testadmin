<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\UpdateOrderStatusEvent;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatusListener
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
    public function handle(UpdateOrderStatusEvent $event)
    {
        echo "走进了订单总状态";
        echo PHP_EOL;
        //
        $orderInfo = $event->orderInfo;
        if ($orderInfo->is_explosion == 0) {
            //获取配置信息
            $distriButions = DB::table('master_config')->get()->toArray();
            $distriBution = array_column($distriButions, null, 'code');
            //爆单比例
            $proportion = isset($distriBution['give_ratio']->value) ? explode(':', $distriBution['give_ratio']->value) : 1;
            $proportionMoney = $proportion[1] / $proportion[0];
            //计算需要添加的总金额
            $moneyNum = isset($orderInfo->order_money) ? $orderInfo->order_money * $proportionMoney : 0;
            $userAccount = DB::table('user_account')->where('user_id', $orderInfo->user_id)->first();
            $userMqAccount = DB::table('mq_users_extra')->where('user_id', $orderInfo->user_id)->first();
            $releaseBalanceMoney = isset($userAccount->release_balance) ? ($userAccount->release_balance + $moneyNum) : $moneyNum;
            //爆单记录表xm_customs_order
            $customsOrderData = $this->insertCustomsOrder($orderInfo, $userMqAccount);
            //日志记录表xm_flow_log
            $flowLogData = $this->insertFlowLog($userAccount, $orderInfo, $releaseBalanceMoney, $userMqAccount);

            //业绩表xm_trade_performance
            $tradeperInsert = $this->insertTrade($orderInfo);
            DB::beginTransaction();
            try {
//                $distriButions = DB::table('master_config')->get()->toArray();
//                $customUserCount = DB::table('customs_order')->where(['user_id' => $orderInfo->user_id, 'status' => 1])->sum('surplus_release_balance');
//                $distriBution = array_column($distriButions, null, 'code');
//                print_r($distriBution['release_max']);
//                if ($customUserCount + $customsOrderData['surplus_release_balance'] > $distriBution['release_max']->value) {
//                    DB::table('orders')->where('order_id', $orderInfo->order_id)->update(['is_explosion' => 1]);
//                    DB::commit();
//                    echo "报单超额，无法新增";
//                    return PHP_EOL;
//                }
                //减掉用户优惠券
                $now = time();
                $account = DB::table('user_account')->where('user_id', $orderInfo->user_id)->first();

                DB::update('UPDATE xm_user_account SET balance = balance - ?,update_at = ? WHERE user_id = ?', [$orderInfo->order_balance, $now, $orderInfo->user_id]);
                $flow_data = [
                    'user_id' => $orderInfo->user_id,
                    'type' => 2,
                    'status' => 2,
                    'amount' => $orderInfo->order_balance,
                    'surplus' => $account->balance - $orderInfo->order_balance,
                    'notes' => '购买礼包专区商品',
                    'create_at' => $now,
                    'target_id' => $orderInfo->order_id,
                    'target_type' => 1

                ];
                DB::table('flow_log')->insert($flow_data);

                DB::update('UPDATE xm_user_account SET release_balance = ?,update_at = ? WHERE user_id = ?', [$releaseBalanceMoney, time(), $orderInfo->user_id]);
                DB::table('orders')->where('order_id', $orderInfo->order_id)->update(['is_explosion' => 1]);
                DB::insert("INSERT INTO xm_flow_log SET user_id='{$flowLogData['user_id']}',type='{$flowLogData['type']}',status='{$flowLogData['status']}',amount='{$flowLogData['amount']}',surplus='{$flowLogData['surplus']}',notes='{$flowLogData['notes']}',create_at='{$flowLogData['create_at']}',sign_time='{$flowLogData['sign_time']}',isall='{$flowLogData['isall']}',target_id='{$flowLogData['target_id']}',target_type='{$flowLogData['target_type']}',msectime='{$flowLogData['msectime']}'");
//                DB::table('flow_log')->insert($flowLogData);
                DB::table('trade_performance')->insert($tradeperInsert);
                DB::table('customs_order')->insert($customsOrderData);
                DB::commit();
                echo "OK总订单完成";
                echo PHP_EOL;
            } catch (\Exception $e) {
                $redis = app('redis.connection');
                $redis->rpush('orderPay', $orderInfo->order_sn);
                DB::rollback();
                throw $e;
            }
        }

    }

    public function insertTrade($orderInfo)
    {
        $userInfo = DB::table('users')->where('user_id', $orderInfo->user_id)->first();

        $insertData = [];
        $insertData['user_id'] = $orderInfo->user_id;
        $insertData['user_name'] = $userInfo->user_name;
        $insertData['tp_num'] = $orderInfo->order_money;
        $insertData['order_cash'] = $orderInfo->order_cash;
        $insertData['tp_gmt_create'] = time();
        $insertData['tp_top_user_ids'] = $userInfo->user_like;
        return $insertData;
    }

    public function insertFlowLog($insertList, $orderInfo, $customsBalances, $userMqAccount)
    {
        list($usec, $sec) = explode(" ", microtime());
        $millisecond = ((float)$usec + (float)$sec);
        $millisecond = str_pad($millisecond, 3, '0', STR_PAD_RIGHT);
        $msectime = substr($millisecond, 0, strrpos($millisecond, '.')) . substr($millisecond, strrpos($millisecond, '.') + 1);
        $flowLogData = [];
        $flowLogData['user_id'] = $orderInfo->user_id;
        $flowLogData['type'] = 3;
        $flowLogData['status'] = 1;
        $flowLogData['amount'] = isset($insertList->release_balance) ? $customsBalances - $insertList->release_balance : $customsBalances - 0;
        $flowLogData['surplus'] = $customsBalances;
        $flowLogData['notes'] = '报单获得待释放优惠券奖励';
        $flowLogData['create_at'] = time();
        $flowLogData['msectime'] = $msectime;
        $flowLogData['sign_time'] = time();
        $flowLogData['isall'] = 0;
        $flowLogData['target_id'] = $orderInfo->order_id;
        $flowLogData['target_type'] = 2;
        return $flowLogData;
    }

    public function insertCustomsOrder($orderInfo, $userMqAccount)
    {
        $insertData = [];
        $insertData['order_id'] = $orderInfo->order_id;
        $insertData['user_id'] = $orderInfo->user_id;
        $insertData['top_user_id'] = isset($userMqAccount->invite_user_id) ? $userMqAccount->invite_user_id : '';
        $insertData['customs_money'] = $orderInfo->order_money;
        $insertData['balance_money'] = $orderInfo->order_balance;
        $insertData['cash_money'] = $orderInfo->order_cash;
        $insertData['release_balance'] = isset($orderInfo->order_money) ? ($orderInfo->order_money * 2) : 0;
        $insertData['surplus_release_balance'] = isset($orderInfo->order_money) ? ($orderInfo->order_money * 2) : 0;;
        $insertData['create_at'] = time();
        $insertData['update_at'] = time();
        return $insertData;
    }
}
