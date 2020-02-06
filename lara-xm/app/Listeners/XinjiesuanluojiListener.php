<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\XinjiesuanluojiEvent;
use Illuminate\Support\Facades\DB;

class XinjiesuanluojiListener
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
    public function handle(XinjiesuanluojiEvent $event)
    {
        //
        $orderInfo = $event->orderInfo;
        $userInfo = DB::table('users')->where('user_id', $orderInfo->user_id)->first();
        if (isset($userInfo->user_like) && !empty($userInfo->user_like)) {
            $userLike = explode(',', $userInfo->user_like);
            $link = array_slice($userLike, -2, 2);
            $userLikeInfo = DB::table('mq_users_extra')->whereIn('user_id', $link)->get()->toArray();
            $linkeUserId = array();
            array_map(function ($userLikeInfo) use (&$linkeUserId) {
                $linkeUserId[$userLikeInfo->user_id] = $userLikeInfo->user_cx_rank;
            }, $userLikeInfo);
            //爆单发放二级分销金额
            $insertList = $this->insertPay($linkeUserId, $orderInfo);
            $userIds = array_keys($insertList);
            $customsBalance = DB::table('user_account')->whereIn('user_id', $userIds)->get()->toArray();
            $customsBalances = array_column($customsBalance, null, 'user_id');
            foreach ($insertList as $key => $item) {
                if (!isset($customsBalances[$key]->release_balance) || $customsBalances[$key]->release_balance < $item) {
                    unset($insertList[$key]);
                }
            }

            //日志记录表xm_flow_log-支出
            $flowLogData = $this->insertFlowLog($insertList, $orderInfo, $customsBalances);
            //日志记录表xm_flow_log-收入
            $flowLogDataIncome = $this->insertFlowLogIncome($insertList, $orderInfo, $customsBalances);
            DB::beginTransaction();
            try {
                foreach ($insertList as $key => $v) {
                    DB::table('orders')->where('order_id', $orderInfo->order_id)->update(['is_pay' => 1]);
                    DB::update('UPDATE xm_user_account SET temporary_balance = temporary_balance + ?,update_at = ? WHERE user_id = ?', [$v, time(), $key]);
                    DB::insert("INSERT INTO xm_flow_log SET user_id='{$flowLogData[$key]['user_id']}',type='{$flowLogData[$key]['type']}',status='{$flowLogData[$key]['status']}',amount='{$flowLogData[$key]['amount']}',surplus='{$flowLogData[$key]['surplus']}',notes='{$flowLogData[$key]['notes']}',create_at='{$flowLogData[$key]['create_at']}',sign_time='{$flowLogData[$key]['sign_time']}',isall='{$flowLogData[$key]['isall']}',target_id='{$flowLogData[$key]['target_id']}',target_type='{$flowLogData[$key]['target_type']}',msectime='{$flowLogData[$key]['msectime']}'");
                    DB::insert("INSERT INTO xm_flow_log SET user_id='{$flowLogDataIncome[$key]['user_id']}',type='{$flowLogDataIncome[$key]['type']}',status='{$flowLogDataIncome[$key]['status']}',amount='{$flowLogDataIncome[$key]['amount']}',surplus='{$flowLogDataIncome[$key]['surplus']}',notes='{$flowLogDataIncome[$key]['notes']}',create_at='{$flowLogDataIncome[$key]['create_at']}',sign_time='{$flowLogDataIncome[$key]['sign_time']}',isall='{$flowLogDataIncome[$key]['isall']}',target_id='{$flowLogDataIncome[$key]['target_id']}',target_type='{$flowLogDataIncome[$key]['target_type']}',msectime='{$flowLogData[$key]['msectime']}'");
                }
                DB::commit();
                echo '二级分销完成';
                echo PHP_EOL;
            } catch (\Exception $e) {
                DB::rollback();
                $redis = app('redis.connection');
                $redis->rpush('orderPay', $orderInfo->order_sn);
                echo 'error';
            }
        }
    }

    public function insertFlowLogIncome($insertList, $orderInfo, $customsBalances)
    {
        $flowLogData = [];
        foreach ($insertList as $key => $value) {
            list($usec, $sec) = explode(" ", microtime());
            $millisecond = ((float)$usec + (float)$sec);
            $millisecond = str_pad($millisecond, 3, '0', STR_PAD_RIGHT);
            $msectime = substr($millisecond, 0, strrpos($millisecond, '.')) . substr($millisecond, strrpos($millisecond, '.') + 1);
            $flowLogData[$key]['user_id'] = $key;
            $flowLogData[$key]['type'] = 2;
            $flowLogData[$key]['status'] = 1;
            $flowLogData[$key]['amount'] = $value;
            $flowLogData[$key]['surplus'] = ($customsBalances[$key]->temporary_balance) + ($customsBalances[$key]->balance) + $value;
            $flowLogData[$key]['notes'] = '获得团队奖优惠券';
            $flowLogData[$key]['create_at'] = time();
            $flowLogData[$key]['sign_time'] = time();
            $flowLogData[$key]['msectime'] = $msectime;
            $flowLogData[$key]['isall'] = 0;
            $flowLogData[$key]['target_id'] = $orderInfo->order_id;
            $flowLogData[$key]['target_type'] = 2;
        }
        return $flowLogData;
    }

    public function insertFlowLog($insertList, $orderInfo, $customsBalances)
    {
        $flowLogData = [];
        foreach ($insertList as $key => $value) {
            list($usec, $sec) = explode(" ", microtime());
            $millisecond = ((float)$usec + (float)$sec);
            $millisecond = str_pad($millisecond, 3, '0', STR_PAD_RIGHT);
            $msectime = substr($millisecond, 0, strrpos($millisecond, '.')) . substr($millisecond, strrpos($millisecond, '.') + 1);
            $flowLogData[$key]['user_id'] = $key;
            $flowLogData[$key]['type'] = 3;
            $flowLogData[$key]['status'] = 2;
            $flowLogData[$key]['amount'] = $value;
            $flowLogData[$key]['surplus'] = ($customsBalances[$key]->release_balance) - $value;
            $flowLogData[$key]['notes'] = '团队奖待释放优惠券释放';
            $flowLogData[$key]['is_prize'] = 1;
            $flowLogData[$key]['create_at'] = time();
            $flowLogData[$key]['msectime'] = $msectime;
            $flowLogData[$key]['sign_time'] = time();
            $flowLogData[$key]['isall'] = 0;
            $flowLogData[$key]['target_id'] = $orderInfo->order_id;
            $flowLogData[$key]['target_type'] = 2;
        }
        return $flowLogData;
    }

    public function insertAccount($insertList, $orderInfo, $customsBalances)
    {
        $accountDara = [];
        foreach ($insertList as $k => $v) {
            $accountDara[$k]['user_id'] = $k;
            $accountDara[$k]['temporary_balance'] = $customsBalances[$k]->temporary_balance + $v;
            $accountDara[$k]['release_balance'] = ($customsBalances[$k]->release_balance) - $v;
            $accountDara[$k]['moneyadmin'] = $v;
        }
        return $accountDara;
    }

    public function insertCustomsOrder($insertList, $orderInfo, $customsBalances)
    {
        $insertData = [];
        foreach ($insertList as $user => $v) {
            $insertData[$user]['order_id'] = $orderInfo->order_id;
            $insertData[$user]['user_id'] = $user;
            $insertData[$user]['top_user_id'] = isset(array_keys($insertList)[0]) ? array_keys($insertList)[0] : '';
            $insertData[$user]['customs_money'] = $orderInfo->order_money;
            $insertData[$user]['balance_money'] = $orderInfo->order_balance;
            $insertData[$user]['cash_money'] = $v;
            $insertData[$user]['release_balance'] = $customsBalances[$user]->release_balance;
            $insertData[$user]['surplus_release_balance'] = ($customsBalances[$user]->release_balance) - $v;
            $insertData[$user]['create_at'] = time();
            $insertData[$user]['update_at'] = time();
        }
        return $insertData;
    }

    public function insertPay($linkeUserId = array(), $orderInfo = array())
    {
        //已经分配过的用户优惠券
        $useLinkUsers = [];
        //跟踪用户等级
        $userGrades = [];
        //要插入数据库的用户待释放优惠券
        $useLinkUserInsert = [];
        //报单配置
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        //获取总金额百分比
        $proPortion = ($distriBution['erji_fenxiao_money']->value) * 0.01;
        //数组反转
        $linkeUsers = array_reverse($linkeUserId, true);
        //需要分配计算的总金额
        $countMoney = $orderInfo->order_money;
        //循环所有需要爆单的用户
        $money = substr($countMoney * $proPortion, 0, strrpos($countMoney * $proPortion, '.')) . substr($countMoney * $proPortion, strrpos($countMoney * $proPortion, '.'), 3);
        foreach ($linkeUsers as $k => $linkeUser) {
            if (empty($useLinkUsers)) {
                if ($countMoney * $proPortion < 1) {
                    continue;
                }
                $useLinkUserInsert[$k] = floatval($money);
                $money = substr($money / 2, 0, strrpos($money / 2, '.')) . substr($money / 2, strrpos($money / 2, '.'), 3);
            }
        }
        return $useLinkUserInsert;
    }
}
