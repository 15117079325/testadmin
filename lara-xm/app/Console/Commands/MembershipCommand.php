<?php

namespace App\Console\Commands;

use function foo\func;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MembershipCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:member';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '会员等级修改脚本';

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
        echo "脚本开始";
        echo PHP_EOL;
        //获取配置信息
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        //会员2级升级所需要人数
        $member_twonum = $distriBution['member_number_twonum']->value;
        //会员2级升级所需要人数
        $member_threenum = $distriBution['member_number_threenum']->value;
        //邀请人暂时先算1个人
        $personnel = $distriBution['primary_direct_num']->value;
        //$personnel = 1;
        //初级业绩额度
        $moneyMent = $distriBution['primary_performance_limit']->value * '10000';
        //业绩暂时算1000
        $moneyMent = 1000;
        //会员销量和直推人数sql查询
        $memberSql = "SELECT invite_user_id,COUNT( * ) AS num,tp.moneyNum FROM xm_mq_users_extra AS ue LEFT JOIN (SELECT user_id, SUM( tp_num ) AS moneyNum FROM xm_trade_performance GROUP BY user_id) AS tp ON ue.user_id = tp.user_id GROUP BY ue.invite_user_id HAVING invite_user_id != 0 AND num >" . $personnel;
        $memberRecommend = DB::select($memberSql);

        $checkLinkInfoTwoSql = "SELECT tp.user_id,SUM( tp_num ) AS moneyNum,invite_user_id FROM xm_trade_performance tp LEFT JOIN xm_mq_users_extra mue ON mue.user_id=tp.user_id GROUP BY tp.user_id";
        $checkLinkInfoTwoArr = DB::select($checkLinkInfoTwoSql);
        //可分发直推奖人数
        $userLikeInfos = DB::table('mq_users_extra')->get()->toArray();
        $checkLinkInfo = array_column($userLikeInfos, null, 'user_id');
        //判断二级会员
        $checkLinkInfoTwo = [];
        array_map(function ($value) use (&$checkLinkInfoTwo) {
            $checkLinkInfoTwo[$value->invite_user_id][$value->user_id] = $value;
        }, $checkLinkInfoTwoArr);
        //判断会员等级
        $userLikeInfo = [];
        foreach ($userLikeInfos as $row) {
            $userLikeInfo[$row->invite_user_id][$row->user_cx_rank][] = $row;
        }
        $toIssetTwoarr = array();
        foreach ($memberRecommend as $kk => $vv) {
            foreach ($checkLinkInfoTwo[$vv->invite_user_id] as $twokey => $rowtwo) {
                if ($rowtwo->moneyNum > $moneyMent && !in_array($vv->invite_user_id, $toIssetTwoarr)) {
                    $toIssetTwoarr[] = $vv->invite_user_id;
                    unset($checkLinkInfoTwo[$vv->invite_user_id][$twokey]);
                    break;
                }
            }
        }
//            $insertArr = [];
        foreach ($memberRecommend as $k => $v) {
            if ($v->moneyNum >= $moneyMent && $checkLinkInfo[$v->invite_user_id]->user_cx_rank < 2) {
                if (in_array($v->invite_user_id, $toIssetTwoarr) && isset($checkLinkInfoTwo[$v->invite_user_id])) {
                    $insertArr[$v->invite_user_id]['user_cx_rank'] = 2;
                }
            }
            if (isset($userLikeInfo[$v->invite_user_id][2]) && count($userLikeInfo[$v->invite_user_id][2]) >= $member_twonum && $checkLinkInfo[$v->invite_user_id]->user_cx_rank < 3) {
                $insertArr[$v->invite_user_id]['user_cx_rank'] = 3;
            }
            if (isset($userLikeInfo[$v->invite_user_id][3]) && count($userLikeInfo[$v->invite_user_id][3]) >= $member_threenum && $checkLinkInfo[$v->invite_user_id]->user_cx_rank < 4) {
                $insertArr[$v->invite_user_id]['user_cx_rank'] = 4;
            }
        }
        foreach ($insertArr as $k => $v) {
            echo "处理了userid为{$k}的用户信息，会员变为了{$v['user_cx_rank']}";
            echo PHP_EOL;
            DB::table('mq_users_extra')->where('user_id', $k)->update($v);
        }
        exit("脚本结束" . PHP_EOL);
    }
}
