<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use ShaoZeMing\GeTui\Facade\GeTui;

class HdUserController extends Controller
{
    /**
     * description:用户报名参赛
     * @author libaowei
     * @date 2019/8/17
     */
    public function register(Request $request)
    {
    	//用户昵称
    	$nick_name = $request->nick_name;
    	//个人介绍
    	$introduce = $request->introduce;
    	//赛区
    	$region = $request->region;
    	//手机号
    	$tel = $request->tel;
        //验证码
        $msg = $request->msg;
        //支付金额
        $money = $request->money;
        //支付方式
        $order_type = $request->order_type;
        //订单类型
        $type = $request->type;

    	if(!isset($nick_name) || !isset($introduce) || !isset($region) || !isset($tel) || !isset($money) || !isset($order_type) || !isset($type) || !isset($msg)) {
    		return error('00000','参数不全');
    	}

        $where = [
            ['veri_mobile', $tel],
            ['veri_number', $msg],
            ['veri_type', 5]
        ];
        //判断验证码是否确
        // $verify = DB::table('verify_num')->where($where)->first();
        // if (empty($verify) || $verify->veri_gmt_expire <= time()) {
        //     return error('20001', '验证码或者手机号不正确');
        // }

        DB::beginTransaction();

        $now = time();

    	//先查询用户已存在的信息
    	$user = DB::table('users')->where('mobile_phone',$tel)->first();
        if(isset($user)){
            //查询用户是否已经报名
            $hd_user = DB::table('hd_users')->where('user_id',$user->user_id)->first();
            if(isset($hd_user)) {
                //return error('10005','您已经报过名了');
            }
            //插入新的信息
            $data = array(
                'user_id' => $user->user_id,
                'nick_name' => $nick_name,
                'introduce' => $request->introduce,
                'region' => $request->region,
                'creation_time' => time(),
                'update_time' => time()
            );
            //创建用户
            $in_user = DB::table('hd_users')->insert($data);

            $order_data = [
                'user_id' => $user->user_id,
                'order_sn' => date('YmdHis',$now) . rand(100, 999),
                'order_money' => $money,
                'order_type' => $order_type,
                'type' => $type,
                'creation_time' => $now
            ];

            $order_id = DB::table('hd_orders')->insertGetId($order_data, 'id');


            //判断是否成功
            if($in_user || $order_id) {
                DB::commit();
                success(array('order_id' => $order_id ));
            } else {
                DB::rollBack();
                return error('99999', '操作失败');
            }
        } else {
            return error('10001','用户不存在');
        }
    }

    /**
     * description:选手个人资料
     * @author libaowei
     * @date 2019/8/17
     */
    public function show_user(Request $request)
    {
    	//用户ID
    	$user_id = $request->user_id;

    	if(!isset($user_id)) {
    		return error('00000','参数不全');
    	}
    	//查询用户资料
    	$user = DB::table('hd_users')->where('user_id',$user_id)->first();

    	if (!isset($user)) {
    		return error('10001','用户不存在');
    	} else {
    		//查询用户头像
    		$head_img = DB::table('users')->where('user_id',$user_id)->value('headimg');
    		
	    	$data[] = array('nick_name' =>$user->nick_name,'head_img' => $head_img,'region' => $user->region,'introduce' => $user->introduce,'poll_sum' => $user->poll_sum,'ranking_list' => $user->ranking_list);
    		success($data);
    	}
    }

    /**
     * description:根据昵称搜索
     * @author libaowei
     * @date 2019/8/17
     */
    public function search_user(Request $request)
    {
    	//用户昵称
    	$nick_name = $request->nick_name;

    	if(!isset($nick_name)) {
    		return error('00000','参数不全');
    	}

    	//根据用户昵称搜索
    	$user = DB::table('hd_orders')->join('hd_users','hd_orders.user_id','hd_users.user_id')->join('users','hd_users.user_id','users.user_id')->select('hd_users.user_id','hd_users.nick_name','hd_users.poll_sum','hd_users.region','users.headimg')->where([['hd_orders.type',1],['hd_users.nick_name','like',"%{$nick_name}%"]])->orderBy('hd_users.poll_sum','desc')->get();

    	if(count($user) == 0) {
    		return error('10001','用户不存在');
    	} else {
    		success($user);
    	}
    }


    /**
     * description:创建订单
     * @author libaowei
     * @date 2019/8/17
     */
    public function doOrder(Request $request)
    {
    	//用户ID
    	$user_id = $request->$user_id;
    	//支付金额
    	$money = $request->money;
    	//支付方式
    	$order_type = $request->order_type;
    	//订单类型
    	$type = $request->type;

        if(!isset($user_id) || !isset($money) || !isset($order_type) || !isset($type)) {
            return error('00000','参数不全');
        }
    	DB::beginTransaction();

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
        } else if($type = 2) {
        	//投票人ID
        	$voting_user = $request->voting_user;
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

        	$hd_vote = [
        		'user_id' => $user_id,
        		'voting_user' => $voting_user,
        		'vote_money' => $money,
        		'votes_num' => $votes_num,
        		'join_order' => $order_id,
        		'creation_time' => $now
        	];
        	$vote = DB::table('hd_vote')->insert($hd_vote);
        	//更新用户表的总投票数
        	$user_votes_num = DB::table('hd_users')->where('user_id',$user_id)->increment('poll_sum',$votes_num);

        	if($vote || $user_votes_num){
        		DB::commit();
        	} else {
        		DB::rollBack();
        		return error('99999', '操作失败');
        	}
        } else {
        	DB::commit();

        	$data = array(
                'order_id' => $order_id,
            );
            return success($data);
        }

    }


    /**
     * description:短信验证
     * @author libaowei
     * @date 2019/8/25
     */
    public function sendMsg(Request $request)
    {
        //1、用户注册验证码2、登录确认验证码3、手机号改绑4、身份验证用户密码修改
        $u_mobile = $request->input('tel');
        $type = $request->input('type', '5');
        $verification = new \Verification();
        if ( empty($u_mobile) || !in_array($type, array('1', '2', '3', '4','5'))) {
            return error('00000', '请求参数不全');
        }
        if(!$verification->fun_phone($u_mobile)){
            return error('01000','请输入合法的手机号码');
        }
        $verify_num = rand('100000', '999999');


        $expire = time() + 5 * 60;


        $exitMsg = DB::table('verify_num')->where([[
            'veri_mobile', $u_mobile
        ], ['veri_type', $type]])->first();

        if ($exitMsg && $exitMsg->veri_gmt_create >= time() - 60) {

            return error('00003', '短信60秒只允许发送一次');

        }


        $result = sendSms($u_mobile, $verify_num, $type);

        if (empty($result)) {
            return error('00001', '短信发送失败');
        }

        $code = $result['Code'];
        switch ($code) {
            case 'OK':
                if ($exitMsg) {
                    $update_data = array(
                        'veri_number' => $verify_num,
                        'veri_gmt_expire' => $expire,
                        'veri_gmt_create' => time()
                    );
                    DB::table('verify_num')->where('veri_id', $exitMsg->veri_id)->update($update_data);
                } else {
                    $insert_data = array(
                        'veri_mobile' => $u_mobile,
                        'veri_number' => $verify_num,
                        'veri_gmt_expire' => $expire,
                        'veri_gmt_create' => time(),
                        'veri_type' => $type
                    );
                    DB::table('verify_num')->insert($insert_data);
                }
                success('','短信发送成功');
                break;
            case 'isv.BUSINESS_LIMIT_CONTROL':
                error('00002', '您发送验证码太过于频繁，请稍后重试');
                break;
            default:
                error('00001', '短信发送次数被限制，请稍后重试');
                break;
        }


    }

    public function show_division() {
        //查询赛区
        $division = DB::table('hd_division')->where('state',1)->get();

        if(count($division) == 0) {
            return error('10007','暂无赛区');
        } else {
            return success($division);
        }

    }

}
