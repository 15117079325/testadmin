<?php
namespace maqu\Services;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use maqu\Log;
use maqu\Models\Account;
use maqu\Models\AccountLog;
use maqu\Models\AdminUser;
use maqu\Models\ShopConfig;
use maqu\Models\User;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * 我的账户
 *
 * @author ji 2018年1月25日09:48:35
 *
 */
class UserAccountService extends BaseService {
    /*获取服务中心3w，10w,30w服务中心充值次数*/
    public function getWechatBusiness($user_name){

        $return = [
          '3w_count'=>0,
          '10w_count'=>0,
          '30w_count'=>0,
        ];
        /*服务中心充值次数金额*/
        $count_3w = DB::table('mq_pre_recharge_log')
            ->where('user_name2',$user_name)
            ->where('sum',30000)
            ->count();
        if($count_3w){
            $return['3w_count'] = $count_3w;
        }
        $count_10w = DB::table('mq_pre_recharge_log')
            ->where('user_name2',$user_name)
            ->where('sum',10000)
            ->count();
        if($count_10w){
            $return['10w_count'] = $count_10w;
        }
        $count_30w = DB::table('mq_pre_recharge_log')
            ->where('user_name2',$user_name)
            ->where('sum',300000)
            ->count();
        if($count_30w){
            $return['30w_count'] = $count_30w;
        }
        return $return;
    }

    /**
     * wechatBusinessApply
     * 服务中心充值申请
     * @param $user_id int
     * @param $exchange_for_user int 被充值的用户账号
     * @param $cash_credit int //需要消耗操作者的新美积分
     * @param $consume_credit int 需要消耗操作者的消费积分
     * @param $register_credit int 需要消耗操作者的注册积分
     * @param $pay_password varchar 操作者的支付密码
     * @param $recharge_amount int 充值等级
     * @return array
     */
    public function wechatBusinessApply($user_id, $exchange_for_user, $cash_credit, $consume_credit, $register_credit, $pay_password, $recharge_amount,$country,$province,$city,$district,$address){

        if(!$user_id || !$exchange_for_user || !$cash_credit || !$consume_credit || !$pay_password){
            return $this->failure('提交信息有误，请填写符合的数据');
        }

        $user =User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_id',$user_id)
            ->first();
        if(!$user){
            return $this->failure('账号不存在');
        }
        if($user->user_cx_rank== 0){
            return $this->failure('该账户未激活');
        }
        if($user->status!=1){
            return $this->failure('您的账户未实名认证，请先实名认证。');
        }
        //check pay_password
        if($user->pay_password != md5($pay_password)){
            return $this->failure('交易密码不正确。');
        }
        //check user_name2 exist
        $user2 = User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_name',$exchange_for_user)->first();

        if(!$user2){
            return $this->failure('消费预充值对象账号不存在');
        }
        //用户账号
        $account =Account::where('account_id',$user->account_id)
            ->first();

        if(!$account){
            return $this->failure("账户不存在.");
        }
        //接收用户账号
        $account2 =Account::where('account_id',$user2->account_id)
            ->first();

        if(!$account2){
            return $this->failure("接收账户不存在.");
        }

        $sum = $cash_credit + $consume_credit + $register_credit;
        $rank_id="";
        if($recharge_amount == 0){//3w
            $rank_id = CX_RANK_3W_WEISHANG_USER;
        }elseif($recharge_amount == 1){//10w
            $rank_id = CX_RANK_10W_WEISHANG_USER;
        }else{//30w
            $rank_id = CX_RANK_30W_WEISHANG_USER;
        }
        $rank =DB::table('mq_cx_ranks')->where('rank_id',$rank_id)
            ->first();

        if(!$rank){
            return $this->failure('invalid parameter.');
        }

        if($rank->condition_amount_from != $sum){
            return $this->failure("充值服务中心的积分合计必须是".$rank->condition_amount_from);
        }

        /*判断用户剩余积分是否足够*/
        $cash_account = Account::where('account_id',$user->account_id)
            ->where('account_type','cash')
            ->first();

        $consume_account = Account::where('account_id',$user->account_id)
            ->where('account_type','consume')
            ->first();

        if($cash_account->money < $cash_credit || $consume_account->money < $consume_credit){
            return $this->failure("积分不足.");
        }

        DB::beginTransaction();


        try{

            //充值服务中心申请
            $apply_data=[
                'account_id'=>$user->account_id,
                'cash_credit'=>$cash_credit,
                'consume_credit'=>$consume_credit,
                'register_credit'=>$register_credit,
                'cx_rank'=>$recharge_amount,
                'create_at'=>time(),
                'update_at'=>time(),
                'country'=>$country,
                'province'=>$province,
                'city'=>$city,
                'district'=>$district,
                'address'=>$address,
                'add_user_id'=>$user->user_id,
                'status'=>XM_WEISHANG_USER_STATUS_WAIT,//待审核
                'accept_id'=>$user2->user_id,
                'accept_account_id'=>$user2->account_id,
            ];
            DB::table('mq_wechat_business_apply')->insert($apply_data);
            //冻结积分
            $userService = new UserCenterService();
            $userService->frozen($user->account_id,'cash',$cash_credit,'充值服务中心',$withTrans =false);
            $userService->frozen($user->account_id,'consume',$consume_credit,'充值服务中心',$withTrans =false);
            DB::commit();
            return $this->success();
        } catch(\Exception $e){
            DB::rollback();
            throw $e;
        }
    }

    /**
     * wechatBusinessapproval
     * 服务中心审核
     * @param $apply_id int
     * @param $status int 状态
     * @param $desc varchar //描述
     * @return mix
     */
    public function wechatBusinessapproval($apply_id,$status,$desc){

        if(!$apply_id || !$status ){
            return $this->failure('提交信息有误，请填写符合的数据');
        }

        $apply = DB::table('mq_wechat_business_apply')
            ->where('id',$apply_id)
            ->first();
        if(!$apply){
            return $this->failure('充值申请数据不存在');
        }
        if($apply->status != XM_WEISHANG_USER_STATUS_WAIT){ //待审核状态
            return $this->failure('该记录已审核过,请勿重复审核！');
        }
        $user_id = $apply->add_user_id;
        $accept_account = $apply->accept_id;
        $cash_credit = $apply->cash_credit;
        $consume_credit = $apply->consume_credit;
        $register_credit = $apply->register_credit;
        $sum = $cash_credit + $consume_credit + $register_credit;
        if(!$cash_credit || !$consume_credit){
            return $this->failure('金额必须大于0。');
        }
        $user =User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_id',$user_id)
            ->first();

        if(!$user){
            return $this->failure('账号不存在');
        }
        if($user->user_cx_rank== 0){
            return $this->failure('该账户未激活');
        }
        if($user->status!=1){
            return $this->failure('您的账户未实名认证，请先实名认证。');
        }


        $user2 = User::join('mq_users_extra','mq_users_extra.user_id','=','users.user_id')
            ->where('users.user_id',$accept_account)->first();

        if(!$user2){
            return $this->failure('转入账号不存在');
        }
        //用户账号
        $account =Account::where('account_id',$user->account_id)
            ->first();

        if(!$account){
            return $this->failure("账户不存在.");
        }
        //接收用户账号
        $account2 =Account::where('account_id',$user2->account_id)
            ->first();

        if(!$account2){
            return $this->failure("账户不存在.");
        }

        $userService = new UserCenterService();
        if($status == XM_WEISHANG_USER_STATUS_SUCCESS){ //同意转账

            DB::beginTransaction();

            try {

                //审核通过
                $apply_data = [
                    'update_at'=>time(),
                    'status'=> XM_WEISHANG_USER_STATUS_SUCCESS,
                    'app_result'=>$desc,
                ];
                DB::table('mq_wechat_business_apply')->where('id',$apply_id)->update($apply_data);

                //account 扣除冻结积分
                $this->minusFrozenMoeny($user->account_id,ACCOUNT_TYPE_cash,$cash_credit,'充值服务中心审核通过',USER_ACCOUNT_CHANGE_WEISHANG);
                $this->minusFrozenMoeny($user->account_id,ACCOUNT_TYPE_consume,$consume_credit,'充值服务中心审核通过',USER_ACCOUNT_CHANGE_WEISHANG);
                $config = ShopConfig::get()->toArray();
                $configs = array_column($config,'value','code');
                $xm_precharge_rate_all_consume = $configs[CONFIG_CODE_xm_precharge_rate_all_consume];
                $temps = explode(':',$xm_precharge_rate_all_consume);
                if(count($temps)!=2){
                    DB::rollback();
                    return $this->failure("报单设置[消费积分产出比]不正确。");
                }
                $amount = $sum * $temps[1]/$temps[0];
                if($amount){
                    $result = $userService->addMoney($user2->account_id,ACCOUNT_TYPE_consume,$amount,"对方账户：$user->user_name",USER_ACCOUNT_CHANGE_PRE_RECHARGE);
                    if(!$result['result']){
                        DB::rollback();
                        return $result;
                    }
                }
                $xm_precharge_rate_all_invest = $configs[CONFIG_CODE_xm_precharge_rate_all_invest];
                $temps = explode(':',$xm_precharge_rate_all_invest);
                if(count($temps)!=2){
                    DB::rollback();
                    return $this->failure("报单设置[待用积分产出比]不正确。");
                }
                $amount = $sum * $temps[1]/$temps[0];
                if($amount) {
                    $result = $userService->addMoney($user2->account_id, ACCOUNT_TYPE_invest, $amount, "对方账户：$user->user_name", USER_ACCOUNT_CHANGE_PRE_RECHARGE);
                    if (!$result['result']) {
                        DB::rollback();
                        return $result;
                    }
                }
                $xm_precharge_rate_all_usable = $configs[CONFIG_CODE_xm_precharge_rate_all_usable];
                $temps = explode(':',$xm_precharge_rate_all_usable);
                if(count($temps)!=2){
                    DB::rollback();
                    return $this->failure("报单设置[可用积分产出比]不正确。");
                }
                $amount = $sum * $temps[1]/$temps[0];
                if($amount) {
                    $result = $userService->addMoney($user2->account_id, ACCOUNT_TYPE_useable, $amount, "对方账户：$user->user_name",USER_ACCOUNT_CHANGE_PRE_RECHARGE);
                    if (!$result['result']) {
                        DB::rollback();
                        return $result;
                    }
                }

                $xm_precharge_rate_all_share = $configs[CONFIG_CODE_xm_precharge_rate_all_share];
                $temps = explode(':',$xm_precharge_rate_all_share);
                if(count($temps)!=2){
                    DB::rollback();
                    return $this->failure("报单设置[分享积分产出比]不正确。");
                }
                $amount = $sum * $temps[1]/$temps[0];
                if($amount) {
                    $result = $userService->addMoney($user2->account_id, ACCOUNT_TYPE_share, $amount, "对方账户：$user->user_name", USER_ACCOUNT_CHANGE_PRE_RECHARGE);
                    if (!$result['result']) {
                        DB::rollback();
                        return $result;
                    }
                }
                $xm_precharge_rate_all_cash = $configs[CONFIG_CODE_xm_precharge_rate_all_cash];
                $temps = explode(':',$xm_precharge_rate_all_cash);
                if(count($temps)!=2){
                    DB::rollback();
                    return $this->failure("报单设置[新美积分产出比]不正确。");
                }
                $amount = $sum * $temps[1]/$temps[0];
                if($amount) {
                    $result = $userService->addMoney($user2->account_id, ACCOUNT_TYPE_cash, $amount, "对方账户：$user->user_name", USER_ACCOUNT_CHANGE_PRE_RECHARGE);
                    if (!$result['result']) {
                        DB::rollback();
                        return $result;
                    }
                }
                //被推荐人的积分倍增
                $xm_precharge_rate_all_invest = $configs[CONFIG_CODE_xm_precharge_rate_all_invest];
                $temps = explode(':',$xm_precharge_rate_all_invest);

                $payback_amount = $sum*$temps[1]/$temps[0];
                $invest_data=[
                  'user_id'=>$user2->user_id,
                  'init_credit'=>$sum,
                  'invest_credit'=>$sum,
                  'payback_credit'=>$payback_amount,
                  'usable_credit'=>0,
                  'invest_status'=>1,
                  'invest_at'=>time(),
                  'update_at'=>time(),
                  'round'=>0,
                  'note'=>"新增投资 $sum",
                  'type'=>0,
                ];
                $invest_id = DB::table('mq_credit_invest')->insertGetId($invest_data);

                $investlog = [
                    'invest_id'=>$invest_id,
                    'user_id'=>$user2->user_id,
                    'invest_credit'=>$sum,
                    'payback_credit'=>$payback_amount,
                    'invest_at'=>time(),
                    'round'=>0,
                    'note'=>"新增投资 $sum",
                    'type'=>0,
                ];
                DB::table('mq_credit_invest_log')->insert($investlog);
                //报单日志表
                $rechargelog = [
                    'user_id'=>$user_id,
                    'user_name2'=>$user2->user_name,
                    'cash_money'=>$cash_credit,
                    'consume_money'=>$consume_credit,
                    'register_money'=>$register_credit,
                    'sum'=>$sum,
                    'create_at'=>time(),
                ];
                DB::table('mq_pre_recharge_log')->insert($rechargelog);

                $uer_ranks = DB::table('mq_cx_ranks')
                    ->orderBy('rank_id','desc')
                    ->get();

                //一次性充值10w，30w
//                if(!in_array($user2->user_cx_rank,[CX_RANK_30W_WEISHANG_USER])){
                $value = $sum;
                if($uer_ranks){
                    foreach($uer_ranks as $item){
                        if($value>=$item->condition_amount_from && $value<=$item->condition_amount_to){
                            if($user2->user_cx_rank == $item->rank_id){ //同一等级无法多次充值
                                return $this->failure("您之前已经充值过了,同一等级只能充值一次");
                            }
                            if($user2->user_cx_rank>$item->rank_id){
                                return $this->failure("您之前已经充值过更高等级,无法再充值低等级");
                            }
                            DB::table('mq_users_extra')->where('user_id',$user2->user_id)->update(['user_cx_rank'=> $item->rank_id]);
                        }
                    }
                }
//                }
                DB::commit();

            } catch(\Exception $e){
                DB::rollback();
                throw $e;
            }

        } else { //拒绝申请退还冻结

            DB::beginTransaction();

            try {
                //审核拒绝
                $apply_data = [
                    'update_at'=>time(),
                    'status'=> XM_WEISHANG_USER_STATUS_FAILURE,
                    'app_result'=>$desc,
                ];
                DB::table('mq_wechat_business_apply')->where('id',$apply_id)->update($apply_data);
                //解冻
                $userService->unfrozen($user->account_id,ACCOUNT_TYPE_cash,$cash_credit,'充值服务中心审核拒绝',true);
                $userService->unfrozen($user->account_id,ACCOUNT_TYPE_consume,$consume_credit,'充值服务中心审核拒绝',true);

                DB::commit();

            } catch(\Exception $e){
                DB::rollback();
                throw $e;
            }

        }

        return $this->success();
    }

    /**
     * 扣除冻结
     * @param $account_id
     * @param $account_type
     * @param $amount
     * @param $desc
     * @param bool|true $withTrans
     * @return array
     * @throws \Exception
     */
    public function minusFrozenMoeny($account_id,$account_type,$amount,$desc,$change_type,$withTrans =true){

        if($withTrans){
            DB::beginTransaction();
        }

        try{

            //account
            $account =Account::where('account_id',$account_id)
                ->where('account_type',$account_type)
                ->first();

            if(!$account){
                return $this->failure("account:$account_id account_type:$account_type not exist.");
            }
            $account->frozen_money-=$amount;
            $account->update_at=time();

            //$account->save();
            DB::table('mq_account')
                ->where('account_id',$account_id)
                ->where('account_type',$account_type)
                ->update($account->toArray());

            //account_log
            $log = new AccountLog();
            $log_data=[
                'account_id'=>$account_id,
                'account_type'=>$account_type,
                'money'=>$account->money,
                'frozen_money'=>$account->frozen_money?$account->frozen_money:0,
                'change_money'=>$amount,
                'change_time'=>time(),
                'change_desc'=>$desc,
                'change_type'=>$change_type,
                'income_type'=>'-',
            ];
            $log->insert($log_data);
            if($withTrans){
                DB::commit();
            }

        } catch(\Exception $e){
            if($withTrans){
                DB::rollback();
            }
            throw $e;
        }

    }
}