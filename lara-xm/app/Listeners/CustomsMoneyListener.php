<?php

namespace App\Listeners;

use App\Events\CustomsMoneyEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class CustomsMoneyListener
{
    private static $user_ids = '';

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
     * @param  CustomsMoneyEvent $event
     * @return void
     */
    public function handle(CustomsMoneyEvent $event)
    {
        $order_info = $event->order_info;
        $now = time();
        //插入业绩表,用于业绩统计
        //找出所有的上级
        $this->get_up($order_info->user_id);
        $user_str = self::$user_ids;
        $user_str .= $order_info->user_id;
        $user_name = DB::table('users')->where('user_id', $order_info->user_id)->pluck('user_name')->first();
        $performance = [
            'user_id' => $order_info->user_id,
            'tp_num' => $order_info->order_money,
            'tp_gmt_create' => $now,
            'user_name' => $user_name,
            'tp_top_user_ids' => $user_str
        ];
        DB::table('trade_performance')->insertGetId($performance, 'tp_id');
        $account = DB::table('user_account')->where('user_id', $order_info->user_id)->select('release_balance', 'balance')->first();
        //先使用优惠券
        if ($order_info->order_balance > $account->balance) {
            return error('40014', '优惠券不足');
        }
        DB::update('UPDATE xm_user_account SET balance = balance - ?,update_at = ? WHERE user_id = ?', [$order_info->order_balance, $now, $order_info->user_id]);
        $flow_data = [
            'user_id' => $order_info->user_id,
            'type' => 2,
            'status' => 2,
            'amount' => $order_info->order_balance,
            'surplus' => $account->balance - $order_info->order_balance,
            'notes' => '购买礼包专区商品',
            'create_at' => $now,
            'target_id' => $order_info->order_id,
            'target_type' => 1

        ];
        DB::table('flow_log')->insertGetId($flow_data, 'foid');
        //查出配置
        $customs_config = DB::table('master_config')->where('tip', 'c')->get()->toArray();
        $customs_config = array_column($customs_config, 'value', 'code');
        $team_config = DB::table('master_config')->where('tip', 't')->get()->toArray();
        $team_config = array_column($team_config, 'value', 'code');
        //报单赠送金额
        $array = explode(':', $customs_config['give_ratio']);
        $surplus_release_balance = ($order_info->order_money * $array[1]) / $array[0];
        //支付成功后插入customs_order,
        $int_info = $this->getUserExtra($order_info->user_id);
        $customs_data = [
            'order_id' => $order_info->order_id,
            'user_id' => $order_info->user_id,
            'top_user_id' => $int_info->invite_user_id,
            'customs_money' => $order_info->order_money,
            'balance_money' => $order_info->order_balance,
            'cash_money' => $order_info->order_cash,
            'release_balance' => $surplus_release_balance,
            'surplus_release_balance' => $surplus_release_balance,
            'status' => 1,
            'create_at' => $now,
            'update_at' => $now,
        ];
        $co_id = DB::table('customs_order')->insertGetId($customs_data, 'co_id');
        $customs_order = DB::table('customs_order')->where('co_id', $co_id)->first();

        //自己赠送1:2的待释放优惠券
        DB::update('UPDATE xm_user_account SET release_balance = release_balance + ?,customs_money = customs_money + ?, update_at = ? WHERE user_id = ?', [$surplus_release_balance, $surplus_release_balance, $now, $customs_order->user_id]);
        $release_balance = DB::table('user_account')->where('user_id', $order_info->user_id)->pluck('release_balance')->first();
        $flow_data = [
            'user_id' => $order_info->user_id,
            'type' => 3,
            'status' => 1,
            'amount' => $surplus_release_balance,
            'surplus' => $release_balance,
            'notes' => '购买礼包专区商品获得待释放优惠券',
            'create_at' => $now,
            'target_id' => $order_info->order_id,
            'target_type' => 1
        ];
        DB::table('flow_log')->insertGetId($flow_data, 'foid');
        $user_name = DB::table('users')->where('user_id', $customs_order->user_id)->pluck('user_name')->first();
        //查出直推人信息
        $user_extra2 = DB::table('mq_users_extra')->where('user_id', $customs_order->top_user_id)->first();
        if ($user_extra2) {
            $user_extra = $user_extra2;
            $money = $customs_order->customs_money * ($customs_config['direct_ratio'] / 100);
            
            //一条线释放优惠券
            $this->Upacc($money,$user_extra2->user_id,$user_name,$customs_order,$customs_config);
            
            //团队奖
            $this->bonusWeishang($user_extra, $customs_order->customs_money, $team_config, $user_name, $customs_order->order_id, 1);
            //管理奖
            $this->manage_acc($money,$user_extra2->user_id,$user_name,$customs_order,$customs_config);
        }
    }


    /**
     * description:找出所有上级
     * @author douhao
     * @date 2018/8/24
     */
    private function get_up($user_id)
    {
        $user_info = DB::table('mq_users_extra')->select('invite_user_id', 'user_id')->where('user_id', '=', $user_id)->first();
        if ($user_info->invite_user_id != 0) {
            self::$user_ids = self::$user_ids . $user_info->invite_user_id . ',';
        } else {
            return true;
        }
        $this->get_up($user_info->invite_user_id);
    }

    public function bonusWeishang($user_extra2, $customs_money, $team_config, $user_name, $target_id, $target_type)
    {
        $percent_primary = $team_config['primary_team_ratio'] / 100; //初级提成
        $percent_middle = $team_config['middle_team_ratio'] / 100;//中级提成
        $percent_high = $team_config['high_team_ratio'] / 100;//高级提成
        $percent_equative = $team_config['equative_team_ratio'] / 100;//平级提成

        $last_rank = 0;
        $current_percent = 0;
        $is_equative = 0;
        $reward_money = 0;
        $now = time();
        while (true) {
            if ($is_equative) {
                while (true) {
                    //更新到奖金池
                    if ($user_extra2->user_cx_rank == $last_rank) {
                        $amount1 = $percent_equative * $reward_money;
                        $dir_release_balance = $this->getReleaseBalance($user_extra2->user_id);
                        if ($amount1 > $dir_release_balance) {
                            $amount1 = $dir_release_balance;
                        }
                        DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$amount1, $amount1, $now, $user_extra2->user_id]);
                        $gold_pool = DB::table('user_account')->where('user_id', $user_extra2->user_id)->pluck('balance')->first();
                        $notes = $user_name . '报单专区购买获得同级奖' . $amount1;

                        //更新报单记录
                        $this->up_one_customs($user_extra2->user_id,$amount1);

                        $insert_data = [
                            'user_id' => $user_extra2->user_id,
                            'amount' => $amount1,
                            'surplus' => $gold_pool,
                            'type' => 2,
                            'status' => 1,
                            'notes' => $notes,
                            'create_at' => $now,
                            'target_type' => $target_type,
                            'target_id' => $target_id
                        ];
                        DB::table('flow_log')->insertGetId($insert_data, 'foid');
                        $insert_data = [
                            'user_id' => $user_extra2->user_id,
                            'amount' => $amount1,
                            'surplus' => $this->getReleaseBalance($user_extra2->user_id),
                            'type' => 3,
                            'status' => 2,
                            'notes' => $user_name . '礼包专区购买释放团队优惠券' . $amount1,
                            'create_at' => $now,
                            'target_type' => $target_type,
                            'target_id' => $target_id
                        ];
                        DB::table('flow_log')->insertGetId($insert_data, 'foid');
                        break;
                    }
                    //已达到顶级用户则退出
                    if (!$user_extra2->invite_user_id) {
                        break;
                    }
                    $user_extra2 = $this->getUserExtra($user_extra2->invite_user_id);
                    if (!$user_extra2) {
                        break;
                    }

                }
                break;
            }
            //初始化
            $is_rank = 0;
            //判断是否有等级
            if (in_array($user_extra2->user_cx_rank, [2, 3, 4])) {
                if ($user_extra2->user_cx_rank > $last_rank) {
                    if ($user_extra2->user_cx_rank == 2) {
                        $current_percent = $percent_primary;
                        //初级
                        $is_rank = 2;
                    } else if ($user_extra2->user_cx_rank == 3) {
                        $current_percent = $percent_middle;
                        //中级
                        $is_rank = 3;
                    } else if ($user_extra2->user_cx_rank == 4 && $is_rank == 3) {
                        $current_percent = $percent_high - $percent_middle;
                    } else if ($user_extra2->user_cx_rank == 4) {
                        $current_percent = $percent_high;
                    }
                    //提成
                    $reward_money = $customs_money * $current_percent;
                    $dir_release_balance = $this->getReleaseBalance($user_extra2->user_id);
                    if ($reward_money > $dir_release_balance) {
                        $reward_money = $dir_release_balance;
                    }
                    //更新到账户表
                    DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$reward_money, $reward_money, $now, $user_extra2->user_id]);
                    $gold_pool = DB::table('user_account')->where('user_id', $user_extra2->user_id)->pluck('balance')->first();
                    $notes = $user_name . '礼包专区购买获得团队奖释放' . $reward_money;


                    //更新报单记录
                    $this->up_one_customs($user_extra2->user_id,$reward_money);


                    $insert_data = [
                        'user_id' => $user_extra2->user_id,
                        'amount' => $reward_money,
                        'surplus' => $gold_pool,
                        'type' => 2,
                        'status' => 1,
                        'notes' => $notes,
                        'create_at' => $now,
                        'target_type' => $target_type,
                        'target_id' => $target_id
                    ];
                    DB::table('flow_log')->insertGetId($insert_data, 'foid');
                    $insert_data = [
                        'user_id' => $user_extra2->user_id,
                        'amount' => $reward_money,
                        'surplus' => $this->getReleaseBalance($user_extra2->user_id),
                        'type' => 3,
                        'status' => 2,
                        'notes' => $user_name . '礼包专区购买释放团队优惠券' . $reward_money,
                        'create_at' => $now,
                        'target_type' => $target_type,
                        'target_id' => $target_id
                    ];
                    DB::table('flow_log')->insertGetId($insert_data, 'foid');
                    //继续找同级
                    $is_equative = 1;
                    $last_rank = $user_extra2->user_cx_rank;
                }
            }
            //已达到顶级永和则退出
            if (!$user_extra2->invite_user_id) {
                break;
            }
            $user_extra2 = $this->getUserExtra($user_extra2->invite_user_id);
            if (!$user_extra2) {
                break;
            }
        }

        return true;
    }

    public function getBalance($user_id)
    {
        return DB::table('user_account')->where('user_id', $user_id)->pluck('balance')->first();
    }

    public function getReleaseBalance($user_id)
    {
        return DB::table('user_account')->where('user_id', $user_id)->pluck('release_balance')->first();
    }

    public function countNum($invie_user_id)
    {
        // //天数
        // $day = 30;
        // //算出距离的时间
        // $end_time = time() - $day * 86400;
        // //查询出用户第一次下单的新人或礼包的订单
        // $order = DB::table('orders')->where([['user_id',$invie_user_id],['order_type',1]])->orderBy('order_gmt_create','asc')->first();
        // //算出距离了多少天 = 当前时间 - 下单时间
        // $poor = time() - $$order->order_gmt_create;
        // //一个月内不计算用户的有效人数
        // if($poor <= $end_time) {
        //     return $direct_assess_num;
        // } else {
            return DB::table('mq_users_extra')->join('user_account', 'mq_users_extra.user_id', '=', 'user_account.user_id')->where('mq_users_extra.invite_user_id', $invie_user_id)->where('user_account.release_balance', '!=', 0)->count();
        // }
    }

    public function getUserExtra($user_id)
    {
        return DB::table('mq_users_extra')->select('user_id', 'user_cx_rank', 'invite_user_id', 'team_number')->where('user_id', $user_id)->first();
    }



    public function Upacc($money = 0,$user_id = 0,$user_name,$customs_order,$customs_config)
    {
        $now = time();
        //获取用户待释放优惠券
        $acc = DB::table('user_account')->where('user_id',$user_id)->first();
        //判断金额是否够减,如果为整数还有多余的金额，如果为负数证明已经释放完
        $num = $money - $acc->release_balance;
        //没有抵消完继续进行
        if($num > 0 ) {
            //得到要释放的优惠券金额
            $money1 = round($money - $num,2);

            //更新报单记录
            $this->up_one_customs($user_id,$money1);

            $date = [
                'release_balance' => 0,
                'balance' => $acc->balance + $money1,
            ];
            DB::table('user_account')->where('user_id',$user_id)->update($date);
            //实时算出用户待释放优惠券的金额
            $acc2 = DB::table('user_account')->where('user_id',$user_id)->first();
            //插入日志
            $notes = $user_name . '购买礼包专区商品优惠券释放' . $money1;
            $insert_data = [
                'user_id' => $user_id,
                'amount' => $money1,
                'surplus' => $acc2->release_balance,
                'type' => 2,
                'status' => 1,
                'notes' => $notes,
                'create_at' => $now,
                'target_type' => 1,
                'target_id' => $customs_order->order_id
            ];
            DB::table('flow_log')->insertGetId($insert_data, 'foid');
            $insert_data = [
                'user_id' => $user_id,
                'amount' => $money1,
                'surplus' => $acc2->release_balance,
                'type' => 3,
                'status' => 2,
                'notes' => $user_name . '购买礼包专区商品释放优惠券' . $money1,
                'create_at' => $now,
                'target_type' => 1,
                'target_id' => $customs_order->order_id
            ];
            DB::table('flow_log')->insertGetId($insert_data, 'foid');
            //找到当前用户上级的ID
            $id = DB::table('mq_users_extra')->select('invite_user_id')->where('user_id',$user_id)->first();

            $this->Upacc($num,$id->invite_user_id,$user_name,$customs_order,$customs_config);
        //可以抵消完
        } else {
            $date = [
                'release_balance' => $acc->release_balance - $money,
                'balance' => $acc->balance + $money,
            ];

            DB::table('user_account')->where('user_id',$user_id)->update($date);
            //实时算出用户待释放优惠券的金额
            $acc2 = DB::table('user_account')->where('user_id',$user_id)->first();

            //更新报单记录
            $this->up_one_customs($user_id,$money);

            //插入日志
            $notes = $user_name . '购买礼包专区商品释放优惠券' . $money;
            $insert_data = [
                'user_id' => $user_id,
                'amount' => $money,
                'surplus' => $acc2->release_balance,
                'type' => 2,
                'status' => 1,
                'notes' => $notes,
                'create_at' => $now,
                'target_type' => 1,
                'target_id' => $customs_order->order_id
            ];
            DB::table('flow_log')->insertGetId($insert_data, 'foid');
            $insert_data = [
                'user_id' => $user_id,
                'amount' => $money,
                'surplus' => $acc2->release_balance,
                'type' => 3,
                'status' => 2,
                'notes' => $user_name . '购买礼包专区商品释放优惠券' . $money,
                'create_at' => $now,
                'target_type' => 1,
                'target_id' => $customs_order->order_id
            ];
            DB::table('flow_log')->insertGetId($insert_data, 'foid');
        }
    }


    //释放管理奖
    public function manage_acc($money = 0,$user_id = 0,$user_name,$customs_order,$customs_config)
    {
        $now = time();
        for($i=1;$i <= 10;$i++) {

           //如果待释放的金额可以分配管理奖
            if($money/2 >= 1) {
                //获取得到管理奖的用户
                $user = $this->getUserExtra($user_id);
                //重新给变量新的用户ID
                $user_id = $user->invite_user_id;

                if (!$user) {
                    return;
                }
                //获取当前用户的待释放优惠券
                $acc1 = DB::table('user_account')->where('user_id',$user_id)->first();
                //计算出管理奖的金额
                $superior = ($customs_config['superior_ratio'] / 100) * $money;
                // //得到用户是否能够满足条件(有效人数)
                // $countNum = $this->countNum($user->invite_user_id);
                // //判断后台配置的参数是否大于有效的用户人数
                // if ($customs_config['direct_assess_num'] > $countNum) {
                //     return ;
                // }
                //算出待释放金额是否可以完全释放
                $num1 = $acc1->release_balance - $superior;

                // if($acc1->release_balance <= 0) {
                //     return ;
                // }

                //如果待释放金额小于得到的金额
                if($num1 <0) {
                    $superior1 = $superior;
                    //算出能够释放的金额
                    $superior = $acc1->release_balance;
                    //四舍五入
                    round($superior,2);
                } else {
                    $superior1 = $superior;
                }
                //重新给变量新的释放金额
                $money = round($superior1,2);
                //echo $money,$acc1->release_balance,$user_id;
                //让用户得到管理奖
                DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,update_at = ? WHERE user_id = ?', [$superior, $superior, $now, $user_id]);
                $notes = $user_name . '购买礼包专区商品释放优惠券' . $superior;

                //更新报单记录
                $this->up_one_customs($user_id,$superior);

                //实时取出用户待释放的金额
                $acc2 = DB::table('user_account')->where('user_id',$user_id)->first();
                $insert_data = [
                    'user_id' => $user_id,
                    'amount' => $superior,
                    'surplus' => $acc2->release_balance,
                    'type' => 3,
                    'status' => 2,
                    'notes' => $notes,
                    'create_at' => $now,
                    'target_type' => 1,
                    'target_id' => $customs_order->order_id
                ];
                DB::table('flow_log')->insertGetId($insert_data, 'foid');
                $insert_data = [
                    'user_id' => $user_id,
                    'amount' => $superior,
                    'surplus' => $acc2->release_balance,
                    'type' => 2,
                    'status' => 1,
                    'notes' => $user_name . '购买礼包专区商品释放优惠券' . $superior,
                    'create_at' => $now,
                    'target_type' => 1,
                    'target_id' => $customs_order->order_id
                ];
                DB::table('flow_log')->insertGetId($insert_data, 'foid');
            }
        }
    }



    /**
     *
     * 更新用户报单释放的优惠券
     *
     */
    public function up_one_customs($user_id,$money)
    {
        //先查询一条报单的记录，进行金额的更新
        $cusstoms = DB::table('customs_order')->where([['user_id',$user_id],['status',1]])->first();
        //算出总的报单金额，防止循环很多次
        $sum = DB::table('customs_order')->where([['user_id',$user_id],['status',1]])->sum('surplus_release_balance');
        if($sum <= $money) {
            //更新用户的全部报单状态为释放完毕
            DB::table('customs_order')->where('user_id',$user_id)->update(['status' => 2]);
            DB::table('customs_order')->where('user_id',$user_id)->update(['surplus_release_balance' => 0]);
            return;
        }
            
        //如果要剩余的金额大于当前减去的金额
        if($cusstoms->surplus_release_balance > $money) {
            DB::table('customs_order')->where('co_id',$cusstoms->co_id)->decrement('surplus_release_balance',$money);

        } else if($cusstoms->surplus_release_balance == $money){
            //更新当前报单记录为释放完毕
            DB::table('customs_order')->where('co_id',$cusstoms->co_id)->update(['status' => 2]);
            DB::table('customs_order')->where('co_id',$cusstoms->co_id)->update(['surplus_release_balance' => 0]);

        } else {
            //因为有报单减少了，所以要先更新下状态
            DB::table('customs_order')->where('co_id',$cusstoms->co_id)->update(['status' => 2]);
            DB::table('customs_order')->where('co_id',$cusstoms->co_id)->update(['surplus_release_balance' => 0]);
            //多的金额 = 释放的金额 - 剩余的金额
            $many = $money - $cusstoms->surplus_release_balance;
            //再执行一次
            $this->up_one_customs($user_id,$many);
          
        }

    }


}
