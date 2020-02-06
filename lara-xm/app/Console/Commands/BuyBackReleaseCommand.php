<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuyBackReleaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xm:hOrder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'H单回购';

    //private $runtime = null;     //任务创建时的时间戳
    //private $rows_per_loop = 10; //每次做几条

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->runtime = time();//任务创建时的时间戳
        $this->rows_per_loop =10;//每次做几条
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->releaseBuyback();
    }
    /**
     * 主处理
     */
    private function releaseBuyback()
    {
//        $log_ = new \Log_();
//        $log_name = base_path('public/notify_url.log');
//        $log_->log_result($log_name,"这里是H单脚本:".$this->runtime);

        $datas = null;
        while (true) {
            //条件：
            // bb_status 回购状态 申购成功，等待回购
            // expire_at 到期时间 <= runtime
            // is_stop == 0 没有暂停的 H 单子
            $datas = DB::table('mq_buy_back')->where('bb_status', 1)
                ->where('expire_at', '<=', time())
                ->where('is_stop', '=', 0)
                ->skip(0)->take(10)
                ->get();

            if (count($datas) == 0) {
                break;
            }
//            $log_->log_result($log_name,"这里是H单脚本:beginTransaction");
            DB::beginTransaction();
            try {
                foreach ($datas as $item) {


                    //更新数据状态到已回购
                    $update_data = [
                        'bb_status' => '3',
                        'update_at' => time(),
                    ];

                    DB::table('mq_buy_back')->where('bb_id', $item->bb_id)->update($update_data);
                    // 查询后台配置
                    $group_limit = DB::table('group_limit')->where('user_id', $item->user_id)->first();
                    $xmcs = explode('-', $group_limit->xmcs);
                    $user_xps = DB::table('xps')->where('user_id', $item->user_id)->first();
                    //用户回购获取新美积分
                    DB::update('update xm_xps set amount = amount + ?, unlimit = unlimit + ?,update_at =  ? WHERE user_id = ?', [$item->cash_money, $item->cash_money, $this->runtime, $item->user_id]);
                    // 流水记录
                    $new = [
                        'user_id' => $item->user_id,
                        'type' => 1, // 新美积分
                        'status' => 1,
                        'amount' => $item->cash_money,
                        'surplus' => $user_xps->amount + $item->cash_money,
                        'notes' => '回购结算',
                        'create_at' => $this->runtime,
                        'target_id' => $item->bb_id,
                        'target_type' => 10
                    ];
                    DB::table('flow_log')->insert($new);
                    // 查询上级用户 id
                    $top_user_0 = DB::table('mq_users_extra')->select('mq_users_extra.invite_user_id as uid', 'users.user_name')->where('mq_users_extra.user_id', $item->user_id)->join('users', 'users.user_id', '=', 'mq_users_extra.user_id')->first();
                    // 查询上上级用户 id
                    $top_user_1 = DB::table('mq_users_extra')->where('user_id', $top_user_0->uid)->select('invite_user_id as uid')->first();
                    //回购利润进行分配

                    $the_money = $item->cash_money_bb - $item->cash_money;

                    //用户本人获取相应分配
                    DB::update('update xm_tps set unlimit = unlimit + ?,shopp = shopp + ? ,update_at =  ? WHERE user_id = ?', [$the_money * ($xmcs[0] / 100), $the_money * ($xmcs[1] / 100), $this->runtime, $item->user_id]);
                    //平台扣除发放的T积分
                    DB::update('update xm_master_config set amount = amount - ? WHERE code = ?', [$the_money * ($xmcs[0] / 100), 'xm_t_all']);
                    //上级上上级获取团队奖
                    DB::update('update xm_tps set gold_pool = gold_pool + ?,update_at =  ? WHERE user_id = ?', [$the_money * ($xmcs[2] / 100), $this->runtime, $top_user_0->uid]);
                    DB::update('update xm_tps set gold_pool = gold_pool + ?,update_at =  ? WHERE user_id = ?', [$the_money * ($xmcs[3] / 100), $this->runtime, $top_user_1->uid]);
                    $this->flow_log($item->user_id, $the_money, $xmcs, $item->bb_id, 0);
                    $this->flow_log($top_user_0->uid, $the_money, $xmcs, $item->bb_id, 1, $top_user_0->user_name);
                    $this->flow_log($top_user_1->uid, $the_money, $xmcs, $item->bb_id, 2, $top_user_0->user_name);
                    //平台团队奖流水
                    $this->flow_log($item->user_id, $the_money, $xmcs, $item->bb_id, 3);
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

    /*
    |- 用户 ID - 回购利润 - 限制规则 - key - from_user (回购奖金来自谁)
    */
    private function flow_log($uid, $sps, $xmcs, $bb_id, $key, $from_user = '')
    {
        // 查询用户的 T 积分组
        $data = DB::table('tps')->where('user_id', $uid)->first();
        $amount = DB::table('master_config')->where('code','xm_t_all')->pluck('amount')->first();
        if (!$data) {
            return;
        }
        $flow = [
            [
                'user_id' => $uid,
                'type' => 2, // T 积分
                'status' => 1,
                'amount' => $sps * ($xmcs[0] / 100),
                'surplus' => $data->unlimit + $sps * ($xmcs[0] / 100),
                'notes' => '回购利润',
                'create_at' => $this->runtime,
                'target_id' => $bb_id,
                'target_type' => 10
            ],
            [
                'user_id' => $uid,
                'type' => 3, // 消费积分
                'status' => 1,
                'amount' => $sps * ($xmcs[1] / 100),
                'surplus' => $data->shopp + $sps * ($xmcs[1] / 100),
                'notes' => '回购利润',
                'create_at' => $this->runtime,
                'target_id' => $bb_id,
                'target_type' => 10
            ],
            [
                'user_id' => $uid,
                'type' => 4, // 奖金池
                'status' => 1,
                'amount' => $sps * ($xmcs[2] / 100),
                'surplus' => $data->gold_pool + $sps * ($xmcs[2] / 100),
                'notes' => '来自用户：' . $from_user . ' 的 H 单回购奖金',
                'create_at' => $this->runtime,
                'target_id' => $bb_id,
                'target_type' => 10
            ],
            [
                'user_id' => $uid,
                'type' => 4, // 奖金池
                'status' => 1,
                'amount' => $sps * ($xmcs[3] / 100),
                'surplus' => $data->gold_pool + $sps * ($xmcs[3] / 100),
                'notes' => '来自用户：' . $from_user . ' 的 H 单回购奖金',
                'create_at' => $this->runtime,
                'target_id' => $bb_id,
                'target_type' => 10
            ],
            [
                'user_id' => 0,
                'type' => 2, // T 积分
                'status' => 2,
                'amount' => $sps * ($xmcs[0] / 100),
                'surplus' => $amount + $sps * ($xmcs[0] / 100),
                'notes' => '回购利润',
                'create_at' => $this->runtime,
                'isall' => 1,
                'target_id' => $bb_id,
                'target_type' => 10
            ],
        ];
        switch ($key) {
            case '0':
                if (DB::table('flow_log')->insert($flow[0])) {
                    if (DB::table('flow_log')->insert($flow[1])) {
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
                break;
            case '1':
                if (DB::table('flow_log')->insert($flow[2])) {
                    return true;
                } else {
                    return false;
                }
                break;
            case '2':
                if (DB::table('flow_log')->insert($flow[3])) {
                    return true;
                } else {
                    return false;
                }
                break;
            case '3':
                if (DB::table('flow_log')->insert($flow[4])) {
                    return true;
                } else {
                    return false;
                }
                break;
            default:
                return;
                break;
        }
    }
}
