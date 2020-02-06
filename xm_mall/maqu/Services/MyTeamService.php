<?php
namespace maqu\Services;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use maqu\Log;
use maqu\Models\Account;
use maqu\Models\AccountLog;
use maqu\Models\AdminUser;
use maqu\Models\IpSafeCheckLog;
use maqu\Models\MqAccountTransferApply;
use maqu\Models\ShopConfig;
use maqu\Models\User;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * 我的团队
 *
 * @author ji 2018年1月19日16:38:19
 *
 */
class MyTeamService extends BaseService {

    /**
     *
     * 返回我的队伍统计
     * @param $user_id int
     * @param $layer varchar
     * @return array
     */
    public function  myTeamCount($user_id,$layer)
    {
        $return=[
          'user_cx_rank'=>[],
          'recharge_count'=>[],
          'hbuy_back'=>[],
          'hz_buy_back'=>[],
          'mid_order'=>[],
        ];
        /*获取除自己以外的团队用户id*/
        $team_ids = DB::table('mq_users_extra')->where('layer','like',$layer.'_%')->pluck('user_id');
        /*获取各个级别服务中心等级人数*/
        $user_cx_rank =  DB::table('mq_users_extra')
            ->whereIn('user_id',$team_ids)
            ->select(DB::raw('user_cx_rank,count(*) as num'))
            ->groupBy('user_cx_rank')
            ->get();
        if($user_cx_rank){
            $return['user_cx_rank'] = $user_cx_rank;
        }
        /*报单次数/金额*/
        $recharge_count = DB::table('mq_pre_recharge_log')
            ->whereIn('user_id',$team_ids)
            ->select(DB::raw('count(*) as num,sum(sum) as money'))
            ->first();
        if($recharge_count){
            $return['recharge_count'] = $recharge_count;
        }
        /*H单回购金额/次数*/
        $hbuy_back = DB::table('mq_buy_back')
            ->whereIn('user_id',$team_ids)
            ->where('bb_status',1)
            ->select(DB::raw('count(*) as num,sum(consume_money) as consume_money ,sum(cash_money) as cash_money'))
            ->first();
        if($hbuy_back){
            $return['hbuy_back'] = $hbuy_back;
        }
        /*H单直购金额/次数*/
        $hz_buy_back =DB::table('order_info')
            ->join('order_goods','order_goods.order_id','=','order_info.order_id')
            ->join('mq_goods_extra','mq_goods_extra.goods_id','=','order_goods.goods_id')
            ->whereIn('user_id',$team_ids)
            ->where('mq_goods_extra.belongto',2)
            ->where('mq_goods_extra.allow_bb',1)
            ->select(DB::raw('count(*) as num,sum(consume_money) as consume_money ,sum(cash_money) as cash_money'))
            ->first();
        if($hz_buy_back){
            $return['hz_buy_back'] = $hz_buy_back;
        }
        /*精品商城统计金额/次数*/
        $mid_order =DB::table('order_info')
            ->join('order_goods','order_goods.order_id','=','order_info.order_id')
            ->join('mq_goods_extra','mq_goods_extra.goods_id','=','order_goods.goods_id')
            ->whereIn('user_id',$team_ids)
            ->where('mq_goods_extra.belongto',2)
            ->where('mq_goods_extra.allow_bb',0)
            ->select(DB::raw('count(*) as num,sum(consume_money) as consume_money,sum(cash_money) as cash_money'))
            ->first();
        if($mid_order){
            $return['mid_order'] = $mid_order;
        }

        return $this->success($return);
    }

    /**
     *
     * 返回我的队伍人数
     * @param $user_id int
     * @return array
     */
    public function getTeams($user_id){
        $layer = DB::table('mq_users_extra')->where('user_id',$user_id)->value('layer');
        /*获取除自己以外的团队用户id*/
        $team_count = DB::table('mq_users_extra')->where('layer','like',$layer.'_%')->count();
        return $team_count?$team_count:0;
    }

}