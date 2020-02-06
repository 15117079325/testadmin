<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class TeamnumberController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    /**
     * description:团队人数
     * @author douhao
     * @date 2018/8/21
     */
    public function detail(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        //查出该会员的直推人数
        $direct_num = DB::table('mq_users_extra')->where('invite_user_id',$user_id)->count();
        //查出会员信息
        $user_info = DB::table('mq_users_extra')->select('team_number','mq_users_extra.real_name','headimg','user_name','mq_users_extra.status')->where('mq_users_extra.user_id',$user_id)->join('users','users.user_id','=','mq_users_extra.user_id')->first();
        if($user_info->status!=1){
            $user_info->real_name = '';
        }
        $user_info->headimg = strpos_domain($user_info->headimg);
        $user_info->direct_num = $direct_num;
        //查出间推人数
        $where = [
            ['invite_user_id','=',$user_id],
            ['user_cx_rank','!=',4]
        ];
        $direct_users = DB::table('mq_users_extra')->select('user_id')->where($where)->get();
        if(count($direct_users)){
            foreach($direct_users as $k=>$v){
                $arr[] = $v->user_id;
            }
            $user_info->indirect_num = DB::table('mq_users_extra')->whereIn('invite_user_id',$arr)->count();
        }else{
            $user_info->indirect_num = 0;
        }

        success($user_info);
    }

    /**
     * description:直推团队人数
     * @author douhao
     * @date 2018/8/21
     */
    public function directDetail(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        //查出会员信息
        $limit = 20;
        $offset = $limit * $page;
        $user_infos = DB::table('mq_users_extra')->select('team_number','mq_users_extra.real_name','user_name','mq_users_extra.user_id','mq_users_extra.status')->where('mq_users_extra.invite_user_id',$user_id)->join('users','users.user_id','=','mq_users_extra.user_id')->offset($offset)->limit($limit)->get();
        foreach ($user_infos as $k=>$item) {
            $item->direct_num = DB::table('mq_users_extra')->where('invite_user_id',$item->user_id)->count();
            //查询用户的释放优惠券
            $user_account = DB::table('user_account')->where('user_id',$item->user_id)->value('release_balance');

            if($user_account > 0) {
                //如果用户有待释放优惠券
                $item->release_balance = 1;
            } else {
                //如果用户没有待释放优惠券
                $item->release_balance = 0;
            }

            if($item->status!=1){
                $item->real_name = '无';
            }
            unset($item->user_id);
        }
        success($user_infos);
    }
}
