<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class TransferIntegralCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:transferIntegral';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'T积分,H积分转移';

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
        //获取设置名单
        $time = time();
        $user_list = DB::table('transfer_list')->select('id', 'user_id', 'rank', 'create_time')->where('status', '1')->get();
        if ($user_list->count()) {
            $users = $user_list->map(function ($value) {
                return (array)$value;
            })->toArray();
            $users = array_column($users, 'create_time', 'user_id');

            //获取系统配置
            $transfer_t_proportion = DB::table('master_config')->where('code', 'transfer_t_proportion')->value('value');
            $transfer_h_proportion = DB::table('master_config')->where('code', 'transfer_h_proportion')->value('value');
            $limit = (object)[];
            $limit->transfer_t_proportion = $transfer_t_proportion;
            $limit->transfer_h_proportion = $transfer_h_proportion;
            foreach ($user_list as $k => $v) {
                DB::beginTransaction();
                try {
                $this->update_table($v->user_id, $limit, $v->id);
                $this->get_lower([$v->user_id], $users, $limit, $v->id, $users[$v->user_id]);
                //更新状态
                DB::update('UPDATE xm_transfer_list SET status = 2  WHERE id = ?', [$v->id]);
                DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();
                }
            }
        }
    }

    private function update_table($user_id, $limit, $id)
    {
        $time = time();
        //对当前服务商进行转移
        $limit1 = $this->judge_limit($user_id);
        if ($limit1) {
            $limit = $limit1;
        }
        //查出当前的用户账户信息
        //打印sql；
        $user_info = DB::table('xps')->select('xps.amount', 'xps.frozen', 'xps.unlimit as xunlimit', 'tps.unlimit as tunlimit')->where('xps.user_id', $user_id)->join('tps', 'xps.user_id', '=', 'tps.user_id')->first();
        $xunlimit = $user_info->xunlimit * $limit->transfer_h_proportion / 100;
        $tunlimit = $user_info->tunlimit * $limit->transfer_t_proportion / 100;
        $total_money = $xunlimit + $tunlimit;
        //更新用户账户
        if ($total_money) {
            DB::update('UPDATE xm_xps SET amount = amount + ?,unlimit = unlimit - ?,frozen = frozen + ?  WHERE user_id = ?', [$tunlimit, $xunlimit, $total_money, $user_id]);
            DB::update('UPDATE xm_tps SET unlimit = unlimit - ?  WHERE user_id = ?', [$tunlimit, $user_id]);
            //记录流水
            if ($xunlimit) {
                $flow_data = [
                    'user_id' => $user_id,
                    'amount' => $xunlimit,
                    'type' => 1,
                    'status' => 2,
                    'surplus' => $user_info->amount * (1 - $limit->transfer_h_proportion / 100),
                    'notes' => 'H积分的' . ($limit->transfer_h_proportion) . '%转移到待释放',
                    'create_at' => $time,
                    'target_type' => 13,
                    'target_id' => $id
                ];
                DB::table('flow_log')->insertGetId($flow_data, 'foid');
            }
            if ($tunlimit) {
                //记录流水
                $flow_data = [
                    'user_id' => $user_id,
                    'amount' => $tunlimit,
                    'type' => 2,
                    'status' => 2,
                    'surplus' => $user_info->tunlimit * (1 - $limit->transfer_t_proportion / 100),
                    'notes' => 'T积分的' . ($limit->transfer_t_proportion) . '%转移到待释放',
                    'create_at' => $time,
                    'target_type' => 13,
                    'target_id' => $id
                ];
            }
            DB::table('flow_log')->insertGetId($flow_data, 'foid');
            //记录流水
            $flow_data = [
                'user_id' => $user_id,
                'amount' => $total_money,
                'type' => 7,
                'status' => 1,
                'surplus' => $user_info->frozen + $total_money,
                'notes' => 'T积分的' . ($limit->transfer_t_proportion) . '%,H积分的' . ($limit->transfer_h_proportion) . '%转移到待释放',
                'create_at' => $time,
                'target_type' => 13,
                'target_id' => $id
            ];
            DB::table('flow_log')->insertGetId($flow_data, 'foid');
        }
    }

    private function judge_limit($user_id)
    {

        $person_limit = DB::table('mq_users_limit')->select('transfer_t_proportion', 'transfer_h_proportion')->where('user_id', $user_id)->first();
        if ($person_limit->transfer_t_proportion == 0 && $person_limit->transfer_h_proportion == 0) {
            $group_limit = DB::table('group_limit')->select('transfer_t_proportion', 'transfer_h_proportion')->where('user_id', $user_id)->first();
            if ($group_limit->transfer_t_proportion == 0 && $group_limit->transfer_h_proportion == 0) {
                return false;
            } else {
                return $group_limit;
            }
        } else {
            return $person_limit;
        }
    }

    private function get_lower($user_arr, $users, $limit, $id, $time)
    {

        $user_list = DB::table('mq_users_extra')->select('mq_users_extra.user_id', 'mq_users_extra.user_cx_rank', 'users.reg_time')->whereIn('invite_user_id', $user_arr)->join('users', 'users.user_id', '=', 'mq_users_extra.user_id')->get();
        if ($user_list->count()) {
            $user_arrs = [];
            foreach ($user_list as $k => $v) {
                //判断用户等级
                if ($v->user_cx_rank == 4) {
                    continue;
                }
                if ($v->user_cx_rank == 3 || $v->user_cx_rank == 2) {
                    //判断是否在设置名单里
                    if (isset($users[$v->user_id])) {
                        continue;
                    }
                }

                if ($v->reg_time > $time) {
                    continue;
                }
                $this->update_table($v->user_id, $limit, $id);
                $user_arrs[] = $v->user_id;
            }
            if ($user_arrs) {
                $this->get_lower($user_arrs, $users, $limit, $id, $time);
            }
        }

    }
}
