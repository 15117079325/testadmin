<?php

namespace App\Shell;

use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '3072M');
set_time_limit(0);
/**
 * 消费积分释放 凌晨2点跑
 * Class ReleaseconsumeJob
 * @package App\Jobs
 * @author douhao
 *
 */
class ReleaseconsumeJob
{
    private $runtime = null;     //任务创建时的时间戳
    private $rows_per_loop = 10; //每次做几条

    /**
     * 创建一个新的任务实例。
     *
     * @param $time 启动时间
     */
    public function __construct()
    {
        $this->runtime = time();
    }

    /**
     * 运行任务。
     *
     * @return void
     */
    public function handle()
    {
        $this->releasePoints();
    }

    private function releasePoints()
    {
        $datas = null;
        //查询消费积分释放规则 天
        $value = DB::table('master_config')->where('tip', 'c')->where('code', 'precharge_rule')->value('value');
        $value = empty($value) ? 10 : $value;
        $time = $this->runtime - ($value * 60 * 60 * 24);
        while (true) {
            $datas = DB::table('customs_apply')->where('surpro', '>', 0)
                ->where('update_at', '<=', $time)
                ->skip(0)->take($this->rows_per_loop)
                ->get();
            if (count($datas) == 0) {
                break;
            }
            $new = [];
            $notes = '待用积分释放';
            DB::beginTransaction();
            try {
                foreach ($datas as $item) {
                    //查出用户的积分信息
                    $points = DB::table('tps')->where('user_id', $item->to_user_id)->first();
                    switch ($item->surpro) {
                        case  7:
                            DB::update('update xm_customs_apply set surplus = surplus * ?, surpro =  ?,update_at =  ? WHERE id = ?', [5 / 7, 5, $this->runtime, $item->id]);
                            $new = [
                                'user_id' => $item->to_user_id,
                                'type' => 3, // 消费积分
                                'status' => 1, // 收入
                                'amount' => $item->surplus * 2 / 7,
                                'surplus' => $points->shopp + $item->surplus * 2 / 7,
                                'notes' => $notes,
                                'create_at' => $this->runtime,
                                'target_id' => $item->id,
                                'target_type' => 11
                            ];
                            DB::table('flow_log')->insert($new);
                            $new = [
                                'user_id' => $item->to_user_id,
                                'type' => 6, // 待用积分
                                'status' => 2, // 支出
                                'amount' => $item->surplus * 2 / 7,
                                'surplus' => $points->surplus - $item->surplus * 2 / 7,
                                'notes' => $notes,
                                'create_at' => $this->runtime,
                                'target_id' => $item->id,
                                'target_type' => 11
                            ];
                            DB::table('flow_log')->insert($new);
                            DB::update('update xm_tps set surplus = surplus - ?, shopp = shopp + ?,update_at =  ? WHERE user_id = ?', [$item->surplus * 2 / 7, $item->surplus * 2 / 7, $this->runtime, $item->to_user_id]);
                            break;
                        case  5:
                            DB::update('update xm_customs_apply set surplus = surplus * ?, surpro =  ?,update_at =  ? WHERE id = ?', [3 / 5, 3, $this->runtime, $item->id]);
                            $new = [
                                'user_id' => $item->to_user_id,
                                'type' => 3, // 消费积分
                                'status' => 1, // 收入
                                'amount' => $item->surplus * 2 / 5,
                                'surplus' => $points->shopp + $item->surplus * 2 / 5,
                                'notes' => $notes,
                                'create_at' => $this->runtime,
                                'target_id' => $item->id,
                                'target_type' => 11
                            ];
                            DB::table('flow_log')->insert($new);
                            $new = [
                                'user_id' => $item->to_user_id,
                                'type' => 6, // 待用积分
                                'status' => 2, // 支出
                                'amount' => $item->surplus * 2 / 5,
                                'surplus' => $points->surplus - $item->surplus * 2 / 5,
                                'notes' => $notes,
                                'create_at' => $this->runtime,
                                'target_id' => $item->id,
                                'target_type' => 11
                            ];
                            DB::table('flow_log')->insert($new);
                            DB::update('update xm_tps set surplus = surplus - ?, shopp = shopp + ?,update_at =  ? WHERE user_id = ?', [$item->surplus * 2 / 5, $item->surplus * 2 / 5, $this->runtime, $item->to_user_id]);
                            break;
                        case  3:
                            DB::update('update xm_customs_apply set surplus = surplus * ?, surpro =  ?,update_at =  ? WHERE id = ?', [0, 0, $this->runtime, $item->id]);
                            $new = [
                                'user_id' => $item->to_user_id,
                                'type' => 3, // 消费积分
                                'status' => 1, // 收入
                                'amount' => $item->surplus,
                                'surplus' => $points->shopp + $item->surplus,
                                'notes' => $notes,
                                'create_at' => $this->runtime,
                                'target_id' => $item->id,
                                'target_type' => 11
                            ];
                            DB::table('flow_log')->insert($new);
                            $new = [
                                'user_id' => $item->to_user_id,
                                'type' => 6, // 待用积分
                                'status' => 2, // 支出
                                'amount' => $item->surplus,
                                'surplus' => $points->surplus - $item->surplus,
                                'notes' => $notes,
                                'create_at' => $this->runtime,
                                'target_id' => $item->id,
                                'target_type' => 11
                            ];
                            DB::table('flow_log')->insert($new);
                            DB::update('update xm_tps set surplus = surplus - ?, shopp = shopp + ?,update_at =  ? WHERE user_id = ?', [$item->surplus, $item->surplus, $this->runtime, $item->to_user_id]);
                            break;
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
            }
            if (count($datas) < $this->rows_per_loop) {
                break;
            }
        }
    }
}
