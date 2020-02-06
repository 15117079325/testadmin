<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HdVoteController extends Controller
{
    /**
     * description:查询排行榜信息
     * @author libaowei
     * @date 2019/8/17
     */
    public function show_top(Request $request)
    {
        header('Content-type:application/json');
    	//获取参赛的用户信息
        $user = DB::table('hd_orders')->join('hd_users','hd_orders.user_id','hd_users.user_id')->join('users','hd_users.user_id','users.user_id')->select('hd_users.user_id','hd_users.nick_name','hd_users.poll_sum','hd_users.region','users.headimg')->where('type',1)->orderBy('hd_users.poll_sum','desc')->get();
        if(count($user) == 0) {
            return error('10006','暂时还没有用户信息');
        } else {
            success($user);
        }
    }
    /**
     * description:进行投票
     * @author libaowei
     * @date 2019/8/17
     */
    public function vote(Request $request) 
    {
        //用户ID
        $user_id = $request->user_id;
        //支付金额
        $money = $request->money;
        //支付方式
        $order_type = $request->order_type;
        //订单类型
        $type = $request->type;
        //被投票人ID
        $voting_user = $request->voting_user;

        if(!isset($user_id) || !isset($money) || !isset($order_type) || !isset($type) || !isset($voting_user)) {
            return error('00000','参数不全');
        }

        if($user_id == $voting_user) {
            return error('10008','不能给自己投票');
        }
        DB::beginTransaction();

        //查询是否有该用户的信息
        $vote_user = DB::table('hd_orders')->where([['user_id',$voting_user],['type',1],['order_status',2]])->first();
        if(isset($vote_user)) {
            //默认一次10票
            $votes_num = 10;
            //获取该用户是否已经给该用户投过票
            $user_conut = DB::table('hd_vote')->where([['voting_user',$voting_user],['user_id',$user_id]])->count();

            if($user_conut >=1) {
                return error('10004','您已经给该用户投过票了');
            }

            //先查询用户已经投多少票了
            $count = DB::table('hd_vote')->where('voting_user',$voting_user)->count();

            if($count > 10) {
                return error('10002','您最多只能为10人投票');
            }

            $now = time();
            $order_data = [
                'user_id' => $user_id,
                'order_sn' => date('YmdHis',$now) . rand(100, 999),
                'order_money' => $money,
                'order_type' => $order_type,
                'type' => $type,
                'creation_time' => $now
            ];

            $order_id = DB::table('hd_orders')->insertGetId($order_data, 'id');

            if(empty($order_id)) {
                DB::rollBack();
                return error('99999', '操作失败');
            }

            $hd_vote = [
                'user_id' => $voting_user,
                'voting_user' => $user_id,
                'vote_money' => $money,
                'votes_num' => $votes_num,
                'join_order' => $order_id,
                'creation_time' => $now
            ];
            $vote = DB::table('hd_vote')->insert($hd_vote);

            if($vote || $user_votes_num){
                DB::commit();
                success(array('order_id' => $order_id ));
            } else {
                DB::rollBack();
                return error('99999', '操作失败');
            }
        } else {
            return error('10006','此用户还未报名');
        }
    }
}
