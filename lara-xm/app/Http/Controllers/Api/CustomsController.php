<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use vod\Request\V20170321\GetOSSStatisRequest;


class CustomsController extends Controller
{
    protected $primary_rank = 2;
    protected $middle_rank = 3;
    protected $higher_rank = 4;
    protected $limit = 1000;

    public function __construct()
    {
//        $this->middleware('userLoginValidate')->except(['aboutH', 'goodsList', 'doOrder', 'test']);
    }

    /**
     * description:下单
     * @author douhao
     * @date 2019/4/30
     */
    public function doOrder(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $consignee = $request->input('consignee');
        $mobile = $request->input('mobile');
        $area = $request->input('area');
        $address = $request->input('address');

        $goods = $request->input('goods');
        $remarks = $request->input('remarks', '无');
        //$password = $request->input('password');


        if (empty($user_id) || empty($consignee) || empty($area) || empty($address) || empty($goods)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }

        $target = ['p_id', 'size_id', 'od_num'];
        $goods = json_decode($goods, true);
        if (!$verification->fun_array($goods, $target)) {
            return error('99998', '商品数据格式不正确');
        }

        $user_extra = DB::table('users')->select('mq_users_extra.pay_password', 'users.clientid', 'users.device')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('users.user_id', $user_id)->first();
        if (empty($user_extra)) {
            return error('99998', '该账号不存在');
        }

//        if (strcmp($user_extra->pay_password, $password) !== 0) {
//            return error('40005', '支付密码不正确');
//        }


        $detail = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_cash', 'p_balance', 'is_size', 'p_stock','max_num')->where('p_id', $goods['p_id'])->first();
        if (empty($detail)) {
            return error('30001', '商品不存在');
        }

        //用于判断购买商品最大件数
        if($detail->max_num >= 0) {
            $is_next = $this->is_max($detail->p_id,$user_id,$detail->max_num);
            if($is_next == 1) {
                return error('30004','累计只能购买'.$detail->max_num.'件');
            }
        }

        $detail->size_id = $goods['size_id'];
        $detail->od_num = $goods['od_num'];
        $detail->size_title = '';

        if ($detail->is_size && $goods['size_id']) {
            $size = DB::table('size')->where('size_id', $goods['size_id'])->first();
            if (empty($size)) {
                $detail->size_title = '';
            } else {

                if ($size->size_stock < $goods['od_num']) {
                    return error('30005', '有商品库存不足', ['p_id' => $goods['p_id'], 'size_id' => $goods['size_id']]);
                }
                if ($size->size_img) {
                    $detail->p_list_pic = $size->size_img;
                }
                $detail->size_title = $size->size_title;
                $detail->p_balance = $size->p_balance;
                $detail->p_cash = $size->p_cash;
            }
        } else {
            if ($detail->p_stock < $goods['od_num']) {
                return error('30005', '商品库存不足');
            }
        }

        $balance_money = round($detail->p_balance * $goods['od_num'], 2);
        $cash_money = round($detail->p_cash * $goods['od_num'], 2);


        $now = time();


        $account = DB::table('user_account')->where('user_id', $user_id)->select('release_balance', 'balance')->first();

        //配置的报单释放优惠券限制
        $conf = DB::table('release_config')->get()->toArray();
        
        //查出用户的团队人数
        $count = DB::table('mq_users_extra')->where('user_id', $user_id)->value('team_number');
        //如果没有配置范围就用默认的
        if(isset($conf)) {
            foreach ($conf as $v) {
                //进行判断
                if($count <=$v->num) {
                    $release_max = $v->money;
                    break;
                } else if($count > $v->num){
                    $release_max = $v->money;
                }
            }
        } else {
            //查出配置
            $release_max = DB::table('master_config')->where('code', 'release_max')->value('value');
        }

        //查出配置
        $customs_config = DB::table('master_config')->where('tip', 'c')->get()->toArray();
        $customs_config = array_column($customs_config, 'value', 'code');
        $team_config = DB::table('master_config')->where('tip', 't')->get()->toArray();
        $team_config = array_column($team_config, 'value', 'code');
        //报单赠送金额
        $array = explode(':', $customs_config['give_ratio']);

        //用户的待释放优惠券 + 用户当前报单的金额
        $release_max1 = round($account->release_balance + ($balance_money + $cash_money) * $goods['od_num'] * $array[1] / $array[0],2);
        
        if ($release_max1 > $release_max) {
            return error('40014', '您的待释放优惠券已超过报单限制额度');
        }

        if ($balance_money > $account->balance) {
            return error('40014', '优惠券不足');
        }

        DB::beginTransaction();

        $order_data = [
            'user_id' => $user_id,
            'order_sn' => 'B' . date('YmdHis', $now) . rand(10000, 99999),
            'consignee' => $consignee,
            'mobile' => $mobile,
            'area' => $area,
            'address' => $address,
            'order_remarks' => $remarks,
            'order_money' => $balance_money + $cash_money,
            'order_balance' => $balance_money,
            'order_cash' => $cash_money,
            'order_type' => '1',
            'order_status' => '1',
            'order_gmt_create' => $now,
            'order_gmt_expire' => $now + ORDER_EXPIRE_TIME,
        ];

        $order_id = DB::table('orders')->insertGetId($order_data, 'order_id');
        if (empty($order_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        }
        $detail_data = [
            'order_id' => $order_id,
            'p_id' => $detail->p_id,
            'p_title' => $detail->p_title,
            'p_img' => $detail->p_list_pic,
            'size_id' => $detail->size_id,
            'size_title' => $detail->size_title,
            'od_num' => $detail->od_num,
            'od_balance' => $detail->p_balance,
            'od_cash' => $detail->p_cash
        ];
        $od_id = DB::table('order_detail')->insertGetId($detail_data, 'od_id');

        if ($detail->is_size) {
            DB::table('size')->where('size_id', $detail->size_id)->decrement('size_stock', $detail->od_num);
        } else {
            DB::table('product')->where('p_id', $detail->p_id)->decrement('p_stock', $detail->od_num);
        }
        if (empty($od_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            $data = ['order_id' => $order_id];
            success($data);
        }
    }

    /**
     * description:报单余额
     * @author douhao
     * @date 2019/4/30
     */
    public function doMorder(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $consignee = $request->input('consignee');
        $mobile = $request->input('mobile');
        $area = $request->input('area');
        $address = $request->input('address');

        $goods = $request->input('goods');
        $remarks = $request->input('remarks', '无');
        //$password = $request->input('password');


        if (empty($user_id) || empty($consignee) || empty($area) || empty($address) || empty($goods)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }

        $target = ['p_id', 'size_id', 'od_num'];
        $goods = json_decode($goods, true);
        if (!$verification->fun_array($goods, $target)) {
            return error('99998', '商品格式不正确');
        }

        $user_extra = DB::table('users')->select('mq_users_extra.pay_password', 'users.clientid', 'users.device')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('users.user_id', $user_id)->first();
        if (empty($user_extra)) {
            return error('99998', '该账号不存在');
        }


        $detail = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_cash', 'p_balance', 'is_size','max_num','p_stock')->where('p_id', $goods['p_id'])->first();
        if (empty($detail)) {
            return error('30001', '商品不存在');
        }

        //用于判断购买商品最大件数
        if($detail->max_num >= 0) {
            $is_next = $this->is_max($detail->p_id,$user_id,$detail->max_num);
            if($is_next == 1) {
                return error('30004','累计只能购买'.$detail->max_num.'件');
            }
        }

        $detail->size_id = $goods['size_id'];
        $detail->od_num = $goods['od_num'];
        $detail->size_title = '';
        // if ($goods['od_num'] > 1) {
        //     return error('30001', '该商品每天只能购买一个');
        // }

        //如果该商品不足
        if($detail->p_stock < $goods['od_num'] ) {
           return error('3005','该商品库存不足'); 
        }


        $balance_money = round($detail->p_balance * $goods['od_num'], 2);
        $cash_money = round($detail->p_cash * $goods['od_num'], 2);


        $now = time();


        // $account = DB::table('user_account')->where('user_id', $user_id)->select('release_balance', 'balance')->first();

        // //查出配置
        // $customs_goods_max_num = DB::table('master_config')->where('code', 'customs_goods_max_num')->value('value');
        // $start_time = strtotime(date('Y-m-d', time()));
        // $end_time = $start_time + 3600 * 24 - 1;
        // $where = [
        //     ['user_id', '=', $user_id],
        //     ['order_gmt_create', '>=', $start_time],
        //     ['order_gmt_create', '<=', $end_time],
        //     ['order_status', '>', 1],
        //     ['order_cancel', '=', 1],
        // ];
        // $h_goods_num = DB::table('orders')->where($where)->count();
        // // if ($h_goods_num > 1) {
        // //     return error('40014', '每天只能购买一单');
        // // }
        // $where = [
        //     ['user_id', '=', $user_id],
        //     ['order_status', '>', 1],
        //     ['order_cancel', '=', 1],
        // ];
        // $h_goods_num = DB::table('orders')->where($where)->count();
        // if ($h_goods_num > $customs_goods_max_num) {
        //     return error('40014', '该商城最多只能购买' . $customs_goods_max_num . '单');
        // }




        $account = DB::table('user_account')->where('user_id', $user_id)->select('release_balance', 'balance')->first();

        //配置的报单释放优惠券限制
        $conf = DB::table('release_config')->get()->toArray();
        
        //查出用户的团队人数
        $count = DB::table('mq_users_extra')->where('user_id', $user_id)->value('team_number');
        //如果没有配置范围就用默认的
        if(isset($conf)) {
            foreach ($conf as $v) {
                //进行判断
                if($count <=$v->num) {
                    $release_max = $v->money;
                    break;
                } else if($count > $v->num){
                    $release_max = $v->money;
                }
            }
        } else {
            //查出配置
            $release_max = DB::table('master_config')->where('code', 'release_max')->value('value');
        }

        //查出配置
        $customs_config = DB::table('master_config')->where('tip', 'c')->get()->toArray();
        $customs_config = array_column($customs_config, 'value', 'code');
        $team_config = DB::table('master_config')->where('tip', 't')->get()->toArray();
        $team_config = array_column($team_config, 'value', 'code');
        //报单赠送金额
        $array = explode(':', $customs_config['give_ratio']);
        //后台设置的新人专享金额
        $customs_goods_max_money = $customs_config['customs_goods_max_money'];
        //用户的待释放优惠券 + 用户当前报单的金额
        $release_max1 = round($account->release_balance + $cash_money * $goods['od_num'] * $array[1] / $array[0],2);
        
        if ($release_max1 > $release_max) {
            return error('40014', '您的待释放优惠券已超过报单限制额度');
        }

        //查出已经购买了多少新人专享的金额
        $money = DB::table('orders')->where([['order_cancel',1],['order_delete',1],['order_type',4],['user_id',$user_id]])->sum('order_cash');
        //现在下单的金额 + 总购买的金额
        $money += $cash_money * $goods['od_num'];

        if($customs_goods_max_money < $money) {
            return error('40015','购买新人专享累计最大金额为'.$customs_goods_max_money.'元');
        }
//        if ($balance_money > $account->release_balance && $balance_money > $account->balance) {
//            return error('40014', '优惠券不足');
//        }

        DB::beginTransaction();

        $order_data = [
            'user_id' => $user_id,
            'order_sn' => 'X' . date('YmdHis', $now) . rand(10000, 99999),
            'consignee' => $consignee,
            'mobile' => $mobile,
            'area' => $area,
            'address' => $address,
            'order_remarks' => $remarks,
            'order_money' => $cash_money,
            'order_balance' => 0,
            'order_cash' => $cash_money,
            'order_type' => '4',
            'order_status' => '1',
            'order_gmt_create' => $now,
            'order_gmt_expire' => $now + ORDER_EXPIRE_TIME,
        ];

        $order_id = DB::table('orders')->insertGetId($order_data, 'order_id');
        if (empty($order_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        }
        $detail_data = [
            'order_id' => $order_id,
            'p_id' => $detail->p_id,
            'p_title' => $detail->p_title,
            'p_img' => $detail->p_list_pic,
            'size_id' => $detail->size_id,
            'size_title' => $detail->size_title,
            'od_num' => $detail->od_num,
            'od_balance' => $detail->p_balance,
            'od_cash' => $detail->p_cash
        ];
        $od_id = DB::table('order_detail')->insertGetId($detail_data, 'od_id');

        if ($detail->is_size) {
            DB::table('size')->where('size_id', $detail->size_id)->decrement('size_stock', $detail->od_num);
        } else {
            DB::table('product')->where('p_id', $detail->p_id)->decrement('p_stock', $detail->od_num);
        }
        if (empty($od_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            $data = ['order_id' => $order_id];
            success($data);
        }
    }

    /**
     * description:查看是否超出设置
     * @author libaowei
     * @date 2019/8/7
     */
    private function is_max($p_id,$user_id,$max_num) {
        //查询用户之前购买的该商品数量
        $order = DB::table('order_detail')->join('orders','order_detail.order_id','=','orders.order_id')->where([['order_detail.p_id',$p_id],['orders.user_id',$user_id],['orders.order_cancel',1],['orders.order_delete',1]])->count();
        //进行判断
        if($max_num <= $order) {
            return $is_next = 1;
        }
    }

}
