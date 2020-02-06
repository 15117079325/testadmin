<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class UserIdentityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:userIdentity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新用户身份信息';
    protected $common_rank = 1;
    protected $primary_rank = 2;
    protected $middle_rank = 3;
    protected $higher_rank = 4;
    protected $limit = 1000;

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
        //统计业绩
        $start_time = strtotime(date('Ymd', strtotime('-1 day')));
        $end_time = $start_time + 24 * 3600 - 1;
        $where = [
            ['tp_gmt_create', '>=', $start_time],
            ['tp_gmt_create', '<=', $end_time],
        ];
        $data = DB::table('trade_performance')->where($where)->select(['tp_top_user_ids', 'user_id', 'tp_num'])->get();

        if ($data->count()) {
            //查出有
            foreach ($data as $k => $v) {
                $array = explode(',', $v->tp_top_user_ids);
                foreach ($array as $vv) {
                    DB::update('UPDATE xm_mq_users_extra set performance = performance + ? WHERE user_id = ?', [$v->tp_num, $vv]);
                }
            }
        }
        //业绩处理完了，更新等级,后台配置会变，所以得更新所有用户
        $datas = null;
        $count = DB::table('mq_users_extra')->count();
        $total = ceil($count / $this->limit);
        //初级
        for ($i = 0; $i < $total; $i++) {
            $offset = $i * $this->limit;
            $datas = DB::table('mq_users_extra')->select(['user_id', 'performance','user_cx_rank'])
                ->offset($offset)->limit($this->limit)
                ->get();
            if (count($datas) == 0) {
                break;
            }
            DB::beginTransaction();
            try {
                //读取相应得配置
                $num = DB::table('master_config')->where('code', 'primary_direct_num')->pluck('value')->first();
                $performance_limit = DB::table('master_config')->where('code', 'primary_performance_limit')->pluck('value')->first();
                $performance_limit = $performance_limit * 10000;
                foreach ($datas as $item) {
                    //判断直推人数
                    $info = DB::table('mq_users_extra')->where('invite_user_id', $item->user_id)->select('performance')->get()->toArray();

                    //如果当前用户大于普通，就不需要更新
                    if($item->user_cx_rank > 1) {
                        continue;
                    }

                    if (count($info) < $num) {
                        DB::update("UPDATE xm_mq_users_extra set user_cx_rank = $this->common_rank  WHERE user_id = ?", [$item->user_id]);
                        continue;
                    }
                    $info = DB::table('mq_users_extra')->where('invite_user_id', $item->user_id)->select('performance')->get()->toArray();
                    $performance = array_column($info, 'performance');
                    $max = max($performance);
                    if ($max < $performance_limit) {
                        DB::update("UPDATE xm_mq_users_extra set user_cx_rank = $this->common_rank  WHERE user_id = ?", [$item->user_id]);
                        continue;
                    }
                    if ((array_sum($performance) - $max) < $performance_limit) {
                        DB::update("UPDATE xm_mq_users_extra set user_cx_rank = $this->common_rank  WHERE user_id = ?", [$item->user_id]);
                        continue;
                    }
                    DB::update("UPDATE xm_mq_users_extra set user_cx_rank = $this->primary_rank  WHERE user_id = ?", [$item->user_id]);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
        }

        //中级
        $datas = null;
        $count = DB::table('mq_users_extra')->where('user_cx_rank', $this->primary_rank)->count();
        $total = ceil($count / $this->limit);
        for ($i = 0; $i < $total; $i++) {
            $offset = $i * $this->limit;
            //条件：
            $datas = DB::table('mq_users_extra')->select(['user_id', 'performance','user_cx_rank'])
                ->where('user_cx_rank', $this->primary_rank)
                ->offset($offset)->limit($this->limit)
                ->get();
            if (count($datas) == 0) {
                break;
            }
            DB::beginTransaction();
            try {
                //读取相应得配置
                foreach ($datas as $item) {
                    //判断直推人数
                    $num = DB::table('mq_users_extra')->where('invite_user_id', $item->user_id)->where('user_cx_rank', 2)->count();

                    //如果当前用户大于初级，就不需要更新
                    if($item->user_cx_rank > 2) {
                        continue;
                    }

                    if ($num >= 2) {
                        DB::update("UPDATE xm_mq_users_extra set user_cx_rank = $this->middle_rank  WHERE user_id = ?", [$item->user_id]);
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
        }


        //高级
        $datas = null;
        $count = DB::table('mq_users_extra')->where('user_cx_rank', $this->middle_rank)->count();
        $total = ceil($count / $this->limit);
        for ($i = 0; $i < $total; $i++) {
            $offset = $i * $this->limit;
            //条件：
            $datas = DB::table('mq_users_extra')->select(['user_id', 'performance','user_cx_rank'])
                ->where('user_cx_rank', $this->middle_rank)
                ->offset($offset)->limit($this->limit)
                ->get();
            if (count($datas) == 0) {
                break;
            }
            DB::beginTransaction();
            try {
                //读取相应得配置
                foreach ($datas as $item) {
                    //判断直推人数
                    $num = DB::table('mq_users_extra')->where('invite_user_id', $item->user_id)->where('user_cx_rank', 3)->count();
                    //如果当前用户大于中级，就不需要更新
                    if($item->user_cx_rank > 3) {
                        continue;
                    }

                    if ($num >= 2) {
                        DB::update("UPDATE xm_mq_users_extra set user_cx_rank = $this->higher_rank  WHERE user_id = ?", [$item->user_id]);
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
        }
        // $this->customs();
    }

    public function customs()
    {
        $time = time();
        $end_time = strtotime(date('Ymd', $time));

        $datas = null;
        $count = DB::table('user_account')->where('release_at', '<', $end_time)->where('release_balance', '!=', 0)->count();
        $total = ceil($count / $this->limit);

        //查出待释放比例
        $day_release_ratio = DB::table('master_config')->where('code', 'day_release_ratio')->pluck('value')->first();
        for ($i = 0; $i < $total; $i++) {
            $offset = $i * $this->limit;
            //条件：
            $datas = DB::table('user_account')->select(['user_id', 'release_balance', 'customs_money'])
                ->where('release_at', '<', $end_time)
                ->where('release_balance', '!=', 0)
                ->offset($offset)->limit($this->limit)
                ->get();
            if (count($datas) == 0) {
                break;
            }
            DB::beginTransaction();
            try {
                //读取相应得配置
                foreach ($datas as $item) {
                    //查询用户的配置
                    $person_release_ratio = DB::table('mq_users_limit')->where('user_id', $item->user_id)->first();
                    if ($person_release_ratio->day_release_ratio == 0) {
                        continue;
                    }
                    if ($person_release_ratio->day_release_ratio > 0 && $person_release_ratio->day_release_ratio) {
                        $money = ($person_release_ratio->day_release_ratio * $item->customs_money) / 100;
                        if ($item->release_balance < $money) {
                            $money = $item->release_balance;
                        }
                        DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,release_at = ? WHERE user_id = ?', [$money, $money, $time, $item->user_id]);
                    } else {
                        $money = ($day_release_ratio * $item->customs_money) / 100;
                        if ($item->release_balance < $money) {
                            $money = $item->release_balance;
                        }
                        DB::update('UPDATE xm_user_account SET balance = balance + ?,release_balance = release_balance - ?,release_at = ? WHERE user_id = ?', [$money, $money, $time, $item->user_id]);
                    }
                    $balance = DB::table('user_account')->where('user_id', $item->user_id)->first();
                    //余额流水
                    $flow_data = [
                        'user_id' => $item->user_id,
                        'type' => 2,
                        'status' => 1,
                        'amount' => $money,
                        'surplus' => $balance->balance,
                        'notes' => '待释放优惠券释放',
                        'create_at' => $time,
                    ];
                    DB::table('flow_log')->insertGetId($flow_data, 'foid');

                    //待释放余额流水
                    $flow_data = [
                        'user_id' => $item->user_id,
                        'type' => 3,
                        'status' => 2,
                        'amount' => $money,
                        'surplus' => $balance->release_balance,
                        'notes' => '待释放优惠券释放',
                        'create_at' => $time,
                    ];
                    DB::table('flow_log')->insertGetId($flow_data, 'foid');

                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
        }
    }

    public function updatePer($user_id, $customs_money)
    {
        $higher_info = DB::table('mq_users_extra')->where('user_id', $user_id)->select('invite_user_id')->first();
        if ($higher_info->invite_user_id) {
            DB::update('UPDATE xm_mq_users_extra set performance = performance + ? WHERE user_id = ?', [$customs_money, $higher_info->invite_user_id]);
            $this->updatePer($higher_info->invite_user_id, $customs_money);
        }
    }
}
