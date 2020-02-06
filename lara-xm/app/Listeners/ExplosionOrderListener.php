<?php

namespace App\Listeners;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\ExplosionOrderEvent;
use Illuminate\Support\Facades\DB;

class ExplosionOrderListener
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
    public function handle(ExplosionOrderEvent $event)
    {
        $orderInfo = $event->orderInfo;
        $userInfo = DB::table('users')->where('user_id', $orderInfo->user_id)->first();
        $userLike = explode(',', mb_substr($userInfo->user_like, 0, mb_strrpos($userInfo->user_like, ',')));
        if (isset($userInfo->user_like) && !empty($userInfo->user_like)) {
            if ($orderInfo->is_direct == 0) {
                //可分发直推奖人数
                $userLikeInfo = DB::table('user_account')->whereIn('user_id', $userLike)->get()->toArray();
                //获取配置信息
                $distriButions = DB::table('master_config')->get()->toArray();
                $distriBution = array_column($distriButions, null, 'code');
                $linkeUserId = array();
                array_map(function ($userLikeInfo) use (&$linkeUserId) {
                    $linkeUserId[$userLikeInfo->user_id]['release_balance'] = $userLikeInfo->release_balance;
                    $linkeUserId[$userLikeInfo->user_id]['temporary_balance'] = $userLikeInfo->temporary_balance;
                }, $userLikeInfo);
                //直推奖发放要插入的数据xm_user_account---直推奖
                $insertListDirect = $this->insertExplosion($linkeUserId, $orderInfo, $distriBution);
                //爆单记录表xm_customs_order---直推奖
                $customsOrderDataDirect = $this->insertCustomsOrder($insertListDirect, $orderInfo);
                //日志记录表xm_flow_log---直推奖-支出
                $flowLogDataDirect = $this->insertFlowLog($insertListDirect, $orderInfo, 2, $userLikeInfo);
                //日志记录表xm_flow_log---直推奖-收入
                $flowLogDataDirectIncome = $this->insertFlowLogIncome($insertListDirect, $orderInfo, 2, $userLikeInfo);
                DB::beginTransaction();
                try {
                    foreach ($insertListDirect as $key => $v) {
                        list($customNum, $updateData) = update_custom($v['user_id'], $v['moneyadmin']);

                        if (!empty($updateData)) {
                            foreach ($updateData as $k => $updateDatas) {
                                DB::table('customs_order')->where('co_id', $k)->update($updateDatas);
                            }
                        }
                        DB::update("UPDATE xm_user_account SET temporary_balance = temporary_balance + ?,release_balance = ?,update_at = ? WHERE user_id = ?", [$v['moneyadmin'], $v['release_balance'], time(), $v['user_id']]);
//                        DB::table('customs_order')->insert($customsOrderDataDirect[$key]);
                        DB::insert("INSERT INTO xm_flow_log SET user_id='{$flowLogDataDirect[$key]['user_id']}',type='{$flowLogDataDirect[$key]['type']}',status='{$flowLogDataDirect[$key]['status']}',amount='{$flowLogDataDirect[$key]['amount']}',surplus='{$flowLogDataDirect[$key]['surplus']}',notes='{$flowLogDataDirect[$key]['notes']}',create_at='{$flowLogDataDirect[$key]['create_at']}',sign_time='{$flowLogDataDirect[$key]['sign_time']}',isall='{$flowLogDataDirect[$key]['isall']}',target_id='{$flowLogDataDirect[$key]['target_id']}',target_type='{$flowLogDataDirect[$key]['target_type']}',msectime='{$flowLogDataDirect[$key]['msectime']}'");
                        DB::insert("INSERT INTO xm_flow_log SET user_id='{$flowLogDataDirectIncome[$key]['user_id']}',type='{$flowLogDataDirectIncome[$key]['type']}',status='{$flowLogDataDirectIncome[$key]['status']}',amount='{$flowLogDataDirectIncome[$key]['amount']}',surplus='{$flowLogDataDirectIncome[$key]['surplus']}',notes='{$flowLogDataDirectIncome[$key]['notes']}',create_at='{$flowLogDataDirectIncome[$key]['create_at']}',sign_time='{$flowLogDataDirectIncome[$key]['sign_time']}',isall='{$flowLogDataDirectIncome[$key]['isall']}',target_id='{$flowLogDataDirectIncome[$key]['target_id']}',target_type='{$flowLogDataDirectIncome[$key]['target_type']}',msectime='{$flowLogDataDirectIncome[$key]['msectime']}'");
//                    DB::table('flow_log')->insert($flowLogDataDirect[$key]);
//                    DB::table('flow_log')->insert($flowLogDataDirectIncome[$key]);
                        DB::table('orders')->where('order_id', $orderInfo->order_id)->update(['is_direct' => 1]);
                    }
                    DB::commit();
                    echo "OK直推奖完毕";
                    echo PHP_EOL;
                } catch (\Exception $e) {
                    $redis = app('redis.connection');
                    $redis->rpush('orderPay', $orderInfo->order_sn);
                    DB::rollback();
                    throw $e;
                }
            }

            /**
             * 处理管理奖
             */
            if ($orderInfo->is_admin == 0) {
                //可分发直推奖人数
                $userLikeInfoAdmin = DB::table('user_account')->whereIn('user_id', $userLike)->get()->toArray();

                $linkeUserIdAdmin = array();
                array_map(function ($userLikeInfoAdmin) use (&$linkeUserIdAdmin) {
                    $linkeUserIdAdmin[$userLikeInfoAdmin->user_id]['release_balance'] = $userLikeInfoAdmin->release_balance;
                    $linkeUserIdAdmin[$userLikeInfoAdmin->user_id]['temporary_balance'] = $userLikeInfoAdmin->temporary_balance;
                }, $userLikeInfoAdmin);
                //处理直推奖
                $directUserInfo = $insertListDirect;
                $raa = end($directUserInfo);

                if (isset($raa['user_id'])) {
                    $raaUserLinkId = mb_substr($userInfo->user_like, 0, mb_strrpos($userInfo->user_like, $raa['user_id']) - 1);
                } else {
                    $raaUserLinkId = $userInfo->user_like;
                }
                //获取管理奖有资格的人数
                $adminLikeInfo = $this->getAdminLikeUser(explode(",", $raaUserLinkId), $distriBution);
                //临时关闭管理奖资格条件
                //$adminLikeInfo = explode(",", $raaUserLinkId);
                //管理奖发放要插入的数据xm_user_account---管理奖
                $insertListAdmin = $this->insertAdminReward($raa, $linkeUserIdAdmin, $distriBution, $adminLikeInfo);
                //爆单记录表xm_customs_order---管理奖
                $customsOrderDataAdmin = $this->insertCustomsOrder($insertListAdmin, $orderInfo);
                //日志记录表xm_flow_log---管理奖-支出
                $flowLogDataAdmin = $this->insertFlowLog($insertListAdmin, $orderInfo, 1, $userLikeInfoAdmin);
                //日志记录表xm_flow_log---管理奖-收入
                $flowLogDataAdminIncome = $this->insertFlowLogIncome($insertListAdmin, $orderInfo, 1, $userLikeInfoAdmin);

                DB::beginTransaction();
                try {
                    foreach ($insertListAdmin as $key => $v) {
                        list($customNum, $updateData) = update_custom($v['user_id'], $v['moneyadmin']);
                        if (!empty($updateData)) {
                            foreach ($updateData as $k => $updateDatas) {
                                DB::table('customs_order')->where('co_id', $k)->update($updateDatas);
                            }
                        }
                        DB::update('UPDATE xm_user_account SET temporary_balance = temporary_balance + ?,release_balance = ?,update_at = ? WHERE user_id = ?', [$v['moneyadmin'], $v['release_balance'], time(), $v['user_id']]);
//                        DB::table('customs_order')->insert($customsOrderDataAdmin[$key]);
                        DB::insert("INSERT INTO xm_flow_log SET user_id='{$flowLogDataAdmin[$key]['user_id']}',type='{$flowLogDataAdmin[$key]['type']}',status='{$flowLogDataAdmin[$key]['status']}',amount='{$flowLogDataAdmin[$key]['amount']}',surplus='{$flowLogDataAdmin[$key]['surplus']}',notes='{$flowLogDataAdmin[$key]['notes']}',create_at='{$flowLogDataAdmin[$key]['create_at']}',sign_time='{$flowLogDataAdmin[$key]['sign_time']}',isall='{$flowLogDataAdmin[$key]['isall']}',target_id='{$flowLogDataAdmin[$key]['target_id']}',target_type='{$flowLogDataAdmin[$key]['target_type']}',msectime='{$flowLogDataAdmin[$key]['msectime']}'");
                        DB::insert("INSERT INTO xm_flow_log SET user_id='{$flowLogDataAdminIncome[$key]['user_id']}',type='{$flowLogDataAdminIncome[$key]['type']}',status='{$flowLogDataAdminIncome[$key]['status']}',amount='{$flowLogDataAdminIncome[$key]['amount']}',surplus='{$flowLogDataAdminIncome[$key]['surplus']}',notes='{$flowLogDataAdminIncome[$key]['notes']}',create_at='{$flowLogDataAdminIncome[$key]['create_at']}',sign_time='{$flowLogDataAdminIncome[$key]['sign_time']}',isall='{$flowLogDataAdminIncome[$key]['isall']}',target_id='{$flowLogDataAdminIncome[$key]['target_id']}',target_type='{$flowLogDataAdminIncome[$key]['target_type']}',msectime='{$flowLogDataAdmin[$key]['msectime']}'");
                        //DB::table('flow_log')->insert($flowLogDataAdmin[$key]);
                        //DB::table('flow_log')->insert($flowLogDataAdminIncome[$key]);
                        DB::table('orders')->where('order_id', $orderInfo->order_id)->update(['is_admin' => 1]);
                    }
                    DB::commit();
                    echo "OK管理奖完毕";
                    echo PHP_EOL;
                } catch (\Exception $e) {
                    $redis = app('redis.connection');
                    $redis->rpush('orderPay', $orderInfo->order_sn);
                    DB::rollback();
                    throw $e;
                }
            }

        }
    }

    public function insertFlowLog($insertList, $orderInfo, $is_a = 1, $userLikeInfos)
    {
        $userLikeInfo = array_column($userLikeInfos, null, 'user_id');
        $flowLogData = [];
        foreach ($insertList as $key => $value) {
            list($usec, $sec) = explode(" ", microtime());
            $millisecond = ((float)$usec + (float)$sec);
            $millisecond = str_pad($millisecond, 3, '0', STR_PAD_RIGHT);
            $msectime = substr($millisecond, 0, strrpos($millisecond, '.')) . substr($millisecond, strrpos($millisecond, '.') + 1);
            $flowLogData[$key]['user_id'] = $key;
            $flowLogData[$key]['type'] = 3;
            $flowLogData[$key]['status'] = 2;
            $flowLogData[$key]['amount'] = $insertList[$key]['moneyadmin'];
            $flowLogData[$key]['surplus'] = $userLikeInfo[$key]->release_balance - $insertList[$key]['moneyadmin'];
            if ($is_a == 1) {
                $flowLogData[$key]['notes'] = '管理奖待释放优惠券释放';
                $flowLogData[$key]['is_prize'] = 2;
            } else {
                $flowLogData[$key]['notes'] = '直推奖待释放优惠券释放';
                $flowLogData[$key]['is_prize'] = 3;
            }
            $flowLogData[$key]['create_at'] = time();
            $flowLogData[$key]['msectime'] = $msectime;
            $flowLogData[$key]['sign_time'] = time();
            $flowLogData[$key]['isall'] = 0;
            $flowLogData[$key]['target_id'] = $orderInfo->order_id;
            $flowLogData[$key]['target_type'] = 2;
        }
        return $flowLogData;
    }


    public function insertFlowLogIncome($insertList, $orderInfo, $is_a = 1, $userLikeInfos)
    {
        $userLikeInfo = array_column($userLikeInfos, null, 'user_id');
        $flowLogData = [];
        foreach ($insertList as $key => $value) {
            list($msec, $sec) = explode(' ', microtime());
            $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
            $flowLogData[$key]['user_id'] = $key;
            $flowLogData[$key]['type'] = 2;
            $flowLogData[$key]['status'] = 1;
            $flowLogData[$key]['amount'] = $insertList[$key]['moneyadmin'];
            $flowLogData[$key]['surplus'] = $userLikeInfo[$key]->temporary_balance + $userLikeInfo[$key]->balance + $insertList[$key]['moneyadmin'];
            if ($is_a == 1) {
                $flowLogData[$key]['notes'] = '获得管理奖优惠券';
            } else {
                $flowLogData[$key]['notes'] = '获得直推奖优惠券';
            }
            $flowLogData[$key]['create_at'] = time();
            $flowLogData[$key]['msectime'] = $msectime;
            $flowLogData[$key]['sign_time'] = time();
            $flowLogData[$key]['isall'] = 0;
            $flowLogData[$key]['target_id'] = $orderInfo->order_id;
            $flowLogData[$key]['target_type'] = 2;
        }
        return $flowLogData;
    }

    public function insertAccount($insertListAdmin, $userLikeInfo)
    {
        $userLikeInfo = array_column($userLikeInfo, null, 'user_id');
        $accountDara = [];
        foreach ($insertListAdmin as $k => $v) {
            $accountDara[$k]['user_id'] = $k;
            $accountDara[$k]['temporary_balance'] = isset($userLikeInfo[$k]) ? $userLikeInfo[$k]->temporary_balance + $v['temporary_balance'] : 0;
            $accountDara[$k]['release_balance'] = $v['release_balance'];
        }
        return $accountDara;
    }

    public function insertCustomsOrder($insertList, $orderInfo)
    {
        $insertData = [];
        foreach ($insertList as $user => $v) {
            $insertData[$user]['order_id'] = $orderInfo->order_id;
            $insertData[$user]['user_id'] = $user;
            $insertData[$user]['top_user_id'] = isset(array_keys($insertList)[0]) ? array_keys($insertList)[0] : '';
            $insertData[$user]['customs_money'] = $orderInfo->order_money;
            $insertData[$user]['balance_money'] = $orderInfo->order_balance;
            $insertData[$user]['cash_money'] = $orderInfo->order_money;
            $insertData[$user]['release_balance'] = $v['release_balance'] + $v['temporary_balance'];
            $insertData[$user]['surplus_release_balance'] = $v['release_balance'];
            $insertData[$user]['create_at'] = time();
            $insertData[$user]['update_at'] = time();
        }
        return $insertData;
    }

    public function insertAdminReward($insertListDirect, $linkeUserId, $distriBution, $adminLikeInfo)
    {
        $insertAdminTeam = [];
        //管理奖百分比
        $proPortion['superior_ratio'] = ($distriBution['superior_ratio']->value) * 0.01;
        //管理奖爆单直推人数上限
        $proPortion['management_num'] = ($distriBution['management_num']->value);
        $directMoney = [];
        $directMoney['temporary_balance'] = $insertListDirect['temporary_balance'];
        $directMoney['release_balance'] = $insertListDirect['release_balance'];
        $directMoney['user_id'] = $insertListDirect['user_id'];
        $directMoney['moneyadmin'] = $insertListDirect['moneyadmin'];
        $insertUserId = [];

        foreach ($adminLikeInfo as $k => $v) {
            if (isset($linkeUserId[$v])) {
                $insertUserId[$v] = $linkeUserId[$v];
            }
        }
        $insertUserId = array_reverse($insertUserId, true);
        $moneyNum = isset($directMoney['moneyadmin']) ? $directMoney['moneyadmin'] * $proPortion['superior_ratio'] : 0;
        $i = 0;
        foreach ($insertUserId as $key => $row) {
            $moneyNum = substr($moneyNum, 0, strrpos($moneyNum, '.')) . substr($moneyNum, strrpos($moneyNum, '.'), 3);
            if ($row['release_balance'] == 0) {
                continue;
            }
            $i++;
            if ($i > $proPortion['management_num']) {
                break;
            }
            if ($moneyNum < 1) {
                break;
            }
            if ($row['release_balance'] <= $moneyNum) {
                $insertAdminTeam[$key]['temporary_balance'] = $row['temporary_balance'] + $row['release_balance'];
                $insertAdminTeam[$key]['moneyadmin'] = $row['release_balance'];
                $insertAdminTeam[$key]['release_balance'] = 0;
                $insertAdminTeam[$key]['user_id'] = $key;
            } else {
                $moneyNumInt = $moneyNum;
                $moneyUserLink = $row['release_balance'] - $moneyNumInt;
                $insertAdminTeam[$key]['temporary_balance'] = $row['temporary_balance'] + $moneyNumInt;
                $insertAdminTeam[$key]['release_balance'] = $moneyUserLink;
                $insertAdminTeam[$key]['moneyadmin'] = $moneyNumInt;
                $insertAdminTeam[$key]['user_id'] = $key;
            }
            $moneyNum = $moneyNum * $proPortion['superior_ratio'];
        }
        return $insertAdminTeam;
    }

    public function getAdminLikeUser($userLike, $distriBution)
    {
        //可分发管理奖人数
        $adminLikeInfo = DB::table('customs_order')->selectRaw('*, max(create_at) as cat')->whereIn('user_id', $userLike)->groupBy('user_id')->get()->toArray();
        //判断时间
        $adminLikeInfo = array_column($adminLikeInfo, null, 'user_id');

        //报单直推一个月考核合格会员人数
        $proPortion['direct_assess_num'] = ($distriBution['direct_assess_num']->value);
        //报单直推考核天数
        $proPortion['direct_assess_day'] = ($distriBution['direct_assess_day']->value);
        //判断邀请人数
        if ($proPortion['direct_assess_num'] <= 0) {
            $adminLikeInfoInvitation = DB::table('mq_users_extra as mue')->selectRaw('xm_mue.invite_user_id,COUNT(1) as num')->join('user_account as ua', function ($join) {
                $join->on('ua.user_id', '=', 'mue.user_id');
            })->where('mue.invite_user_id', '!=', '0')->whereIn('mue.invite_user_id', $userLike)->groupBy('mue.invite_user_id')->get()->toArray();
        } else {
            $adminLikeInfoInvitation = DB::table('mq_users_extra as mue')->selectRaw('xm_mue.invite_user_id,COUNT(1) as num')->join('user_account as ua', function ($join) {
                $join->on('ua.user_id', '=', 'mue.user_id')->where('ua.release_balance', '>', 0);
            })->where('mue.invite_user_id', '!=', '0')->whereIn('mue.invite_user_id', $userLike)->groupBy('mue.invite_user_id')->get()->toArray();
        }
        $adminLikeInfoInvitation = array_column($adminLikeInfoInvitation, null, 'invite_user_id');
        $currentTime = time();
        foreach ($userLike as $key => $row) {
            if (isset($adminLikeInfo[$row])) {
                if (ceil(($currentTime - $adminLikeInfo[$row]->create_at) / 86400) > $proPortion['direct_assess_day']) {
                    if (!isset($adminLikeInfoInvitation[$row]) || $adminLikeInfoInvitation[$row]->num < $proPortion['direct_assess_num']) {
                        unset($userLike[$key]);
                    }
                }
            }
        }
        return $userLike;
    }

    public function insertExplosion($linkeUserId, $orderInfo, $distriBution)
    {
        ///要插入数据库的用户待释放优惠券
        $useLinkUserInsert = [];
        //总配置信息
        $proPortion = [];
        //直推奖百分比
        $proPortion['direct_ratio'] = ($distriBution['direct_ratio']->value) * 0.01;
        //直推奖人数上限
        $proPortion['directpush_num'] = ($distriBution['directpush_num']->value);
        //数组反转
        $linkeUsers = array_reverse($linkeUserId, true);
        $moneyNum = $orderInfo->order_money * $proPortion['direct_ratio'];
        $moneyNum = substr($moneyNum, 0, strrpos($moneyNum, '.')) . substr($moneyNum, strrpos($moneyNum, '.'), 3);
        $i = 0;
        foreach ($linkeUsers as $k => $value) {
            $i++;
            if ($i > $proPortion['directpush_num']) {
                break;
            }
            if ($moneyNum < 1) {
                break;
            }
            if ($value['release_balance'] <= 0) {
                continue;
            }
            if ($value['release_balance'] <= $moneyNum) {
                $useLinkUserInsert[$k]['temporary_balance'] = $value['temporary_balance'] + $value['release_balance'];
                $useLinkUserInsert[$k]['moneyadmin'] = $value['release_balance'];
                $useLinkUserInsert[$k]['release_balance'] = 0;
                $useLinkUserInsert[$k]['user_id'] = $k;
                $moneyNum -= $value['release_balance'];
            } else {
                $useLinkUserInsert[$k]['temporary_balance'] = $moneyNum + $value['temporary_balance'];
                $useLinkUserInsert[$k]['release_balance'] = $value['release_balance'] - $moneyNum;
                $useLinkUserInsert[$k]['user_id'] = $k;
                $useLinkUserInsert[$k]['moneyadmin'] = $moneyNum;
                $moneyNum = 0;
                break;
            }
        }
        return $useLinkUserInsert;
    }
}
