<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use ShaoZeMing\GeTui\Facade\GeTui;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    private $testPhone = ['32'];

    /**
     * description:检查是否可以抵扣
     * @author Harcourt
     * @date 2018/8/13
     */
    public function checkDiscount(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $goods = $request->input('goods');
        if (empty($user_id) || empty($goods)) {
            return error('00000', '参数不全');
        }

        $goods = json_decode($goods, true);
        $verification = new \Verification();
        $num = count($goods);
        $target = ['p_id', 'size_id', 'od_num'];
        for ($i = 0; $i < $num; $i++) {
            if (!$verification->fun_array($goods[$i], $target)) {
                return error('99998', '非法操作');
            }
        }
        $userUseableShopp = DB::table('tps')->where('user_id', $user_id)->value('shopp');
        if ($userUseableShopp == null) {
            $userUseableShopp = '0';
        }
        $market_price = DB::table('master_config')->where('code', 'tscore_consume')->pluck('value')->first();

        $order_discount = '0.00';
        $has = '0';
        $surplus = '0.00';
        if ($market_price) {
            $user_surplus_money = round($userUseableShopp / $market_price, 2);

            $ids = array_column($goods, 'p_id');
            $nums = array_column($goods, 'od_num');

            $id_num = array_combine($ids, $nums);

            $deductionProducts = DB::table('product')->select('p_id', 'deduction_money')->where('is_deduction', 1)->whereIn('p_id', $ids)->get()->toArray();

            if (!empty($deductionProducts)) {
                $has = '1';
                foreach ($deductionProducts as $deductionProduct) {

                    $goodsNum = $id_num[$deductionProduct->p_id];
                    //单个商品最多可抵扣
                    $product_discount = $deductionProduct->deduction_money * $goodsNum;

                    if ($user_surplus_money >= $product_discount) {
                        //全部抵消
                        $order_discount += $product_discount;

                        $user_surplus_money -= $product_discount;
                    } else {
                        $order_discount += $user_surplus_money;

                        $user_surplus_money = '0';
                    }
                }
                if ($user_surplus_money == 0) {
                    //抵扣完
                    $surplus = round($userUseableShopp, 2);
                } else {
                    $surplus = round($order_discount * $market_price, 2);
                }

            }

        }

        if ($order_discount == 0) {
            $has = '0';
        }

        $data = [
            'has' => $has,
            'surplus' => (string)$surplus,
            'equal' => (string)$order_discount

        ];

        success($data);
    }


    /**
     * description:下单 品牌专区
     * @author Harcourt
     * @date 2018/8/10
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
        if (empty($user_id) || empty($consignee) || empty($area) || empty($address) || empty($goods)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }

        $target = ['p_id', 'size_id', 'od_num'];
        $goods = json_decode($goods, true);
        if (!is_array($goods)) {
            return error('00000', '参数不全');
        }

        $num = count($goods);

        for ($i = 0; $i < $num; $i++) {
            if (!$verification->fun_array($goods[$i], $target)) {
                return error('99998', '商品格式不正确');
            }
        }
        $account = DB::table('user_account')->where('user_id', $user_id)->first();
        if (empty($account)) {
            return error('99998', '账号不存在');
        }

        $ids = array_column($goods, 'p_id');
        $pids = array_values(array_unique($ids));

        $pwhere = [
            ['p_putaway', 1],
            ['p_type', PRODUCT_TYPE_PINPAI],
            ['p_delete', 1]
        ];
        $validateProducts = DB::table('product')->select('shop_id', 'p_id', 'p_title', 'p_list_pic', 'p_balance', 'p_cash', 'is_size')->where($pwhere)->whereIn('p_id', $pids)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();

        if (count($validateProducts) != count($pids)) {
            return error('99998', '提交的商品数量不正确');
        }


        $details = [];
        $totalMoney = '0';
        $totalBalance = '0';
        $totalCash = '0';
        for ($i = 0; $i < $num; $i++) {
            $product = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_balance', 'p_cash', 'is_size', 'p_stock', 'max_num')->where('p_id', $goods[$i]['p_id'])->first();

            //用于判断购买商品最大件数
            if ($product->max_num >= 0) {
                $is_next = $this->is_max($product->p_id, $user_id, $product->max_num);
                if ($is_next == 1) {
                    return error('30004', '累计只能购买' . $product->max_num . '件');
                }
            }

            $product->size_id = $goods[$i]['size_id'];
            $product->od_num = $goods[$i]['od_num'];
            if ($goods[$i]['size_id'] && $product->is_size) {
                $size = DB::table('size')->where('size_id', $goods[$i]['size_id'])->first();
                if ($size->size_stock < $goods[$i]['od_num']) {
                    return error('30005', '有商品库存不足', ['p_id' => $goods[$i]['p_id'], 'size_id' => $goods[$i]['size_id']]);
                }
                if ($size->size_img) {
                    $product->p_list_pic = $size->size_img;
                }
                $product->p_balance = $size->size_balance;
                $product->p_cash = $size->size_cash;
                $product->size_title = $size->size_title;
            } else {
                if ($product->p_stock < $goods[$i]['od_num']) {
                    return error('30005', '有商品库存不足', ['p_id' => $goods[$i]['p_id'], 'size_id' => $goods[$i]['size_id']]);
                }
                $product->size_title = '';
            }

            $totalBalance += $product->p_balance * $goods[$i]['od_num'];
            $totalCash += $product->p_cash * $goods[$i]['od_num'];
            $totalMoney += $totalBalance + $totalCash;
            $details[] = $product;

        }

        $totalBalance = round($totalBalance, 2);

        if ($account->balance < $totalBalance) {
            return error('40014', '余额不足');
        }
        $now = time();
        DB::beginTransaction();

        $order_data = [
            'user_id' => $user_id,
            'order_sn' => 'P' . date('YmdHis', $now) . rand(10000, 99999),
            'consignee' => $consignee,
            'mobile' => $mobile,
            'area' => $area,
            'address' => $address,
            'order_remarks' => $remarks,
            'order_money' => $totalMoney,
            'order_cash' => $totalCash,
            'order_balance' => $totalBalance,
            'order_type' => PRODUCT_TYPE_PINPAI,
            'order_gmt_create' => $now,
            'order_gmt_expire' => $now + ORDER_EXPIRE_TIME,

        ];

        $order_id = DB::table('orders')->insertGetId($order_data, 'order_id');
        $flag = true;
        if (empty($order_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            foreach ($details as $detail) {
                $detail_data = [
                    'order_id' => $order_id,
                    'p_id' => $detail->p_id,
                    'p_title' => $detail->p_title,
                    'p_img' => $detail->p_list_pic,
                    'size_id' => $detail->size_id,
                    'size_title' => $detail->size_title,
                    'od_num' => $detail->od_num,
                    'od_balance' => $detail->p_balance,
                    'od_cash' => $detail->p_cash,
                ];

                $od_id = DB::table('order_detail')->insertGetId($detail_data, 'od_id');

                if (empty($od_id)) {
                    $flag = false;
                }
                if ($detail->is_size) {
                    DB::table('size')->where('size_id', $detail->size_id)->decrement('size_stock', $detail->od_num);
                } else {
                    DB::table('product')->where('p_id', $detail->p_id)->decrement('p_stock', $detail->od_num);
                }

            }
            if ($flag) {
                DB::table('user_account')->where('user_id', $user_id)->decrement('balance', $totalBalance);
                DB::table('user_account')->where('user_id', $user_id)->increment('pending_balance', $totalBalance);

                DB::commit();
                $data = array(
                    'order_id' => $order_id,
                );
                return success($data);
            } else {
                DB::rollBack();
                return error('99999', '操作失败');
            }
        }


    }

    public function doOrder_old(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $consignee = $request->input('consignee');
        $mobile = $request->input('mobile');
        $area = $request->input('area');
        $address = $request->input('address');
        $goods = $request->input('goods');
        $discount = $request->input('discount', 0);//0、不使用蓝宝石抵扣1、抵扣
        $remarks = $request->input('remarks', '无');
//        dd($mobile.'=='.$consignee.'=='.$mobile.'=='.$area.'=='.$mobile);
        if (empty($user_id) || empty($consignee) || empty($area) || empty($address) || empty($goods)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }

        $target = ['p_id', 'size_id', 'od_num'];
        $goods = json_decode($goods, true);

        $num = count($goods);

        for ($i = 0; $i < $num; $i++) {
            if (!$verification->fun_array($goods[$i], $target)) {
                return error('99998', '非法操作');
            }
        }

        $user_extra = DB::table('mq_users_extra')->select('new_status', 'unlimit', 'shopp')->where('mq_users_extra.user_id', $user_id)->join('tps', 'tps.user_id', '=', 'mq_users_extra.user_id')->first();
        if (empty($user_extra->new_status)) {
            return error('99998', '非法操作');
        }
        $new_status = explode('-', $user_extra->new_status);

        if (empty($new_status)) {
            return error('99998', '非法操作');
        }

        if ($new_status[1] == 1) {
            return error('99996', '该账号暂时不能购买');
        }


        $ids = array_column($goods, 'p_id');

        $pids = array_values(array_unique($ids));


        $nowDay = date('Y-m-d', time());
//        $where = [
//            ['mv_gmt_create','>=',strtotime($nowDay)],
//            ['mv_gmt_create','<',strtotime($nowDay.'+ 1 day')]
//        ];
        //$market_price = DB::table('market_value')->where($where)->orderBy('mv_id','desc')->pluck('mv_price')->first();
        $market_price = DB::table('master_config')->where('code', 'tscore_consume')->pluck('value')->first();
        if ($market_price == null) {
            return error('99997', '暂时无法操作');
        }

        $pwhere = [
            ['p_putaway', 1],
            ['p_type', 2],
            ['p_delete', 1]
        ];
        $validateProducts = DB::table('product')->select('shop_id', 'p_id', 'p_title', 'p_list_pic', 'p_t_score', 'is_deduction', 'is_size')->where($pwhere)->whereIn('p_id', $pids)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();


        if (count($validateProducts) != count($pids)) {
            return error('99998', '非法操作');

        }

        $deductions = array_column($validateProducts, 'is_deduction');
        if ($discount == 1 && !in_array(1, $deductions)) {
            return error('99998', '非法操作');
        }

        $pid_count = array_count_values($ids);

        foreach ($pid_count as $key => $value) {
            $daily_limit = DB::table('product_extra')->where('p_id', $key)->pluck('daily_buy_limit')->first();

            $buy_num = 0;
            if ($daily_limit) {
                for ($i = 0; $i < $num; $i++) {
                    if ($goods[$i]['p_id'] == $key) {
                        $buy_num += $goods[$i]['od_num'];
                    }
                }

                if ($buy_num > $daily_limit) {
                    return error('30004', '有商品超出购买限制', ['p_id' => $key]);
                }
                $owhere = [
                    ['user_id', $user_id],
                    ['order_gmt_create', '>=', strtotime($nowDay)],
                    ['order_gmt_create', '<', strtotime($nowDay . '+ 1 day')],
                    ['order_status', '>=', '2']
                ];
                $order_ids = DB::table('orders')->where($owhere)->pluck('order_id');
                if ($order_ids) {
                    $detail_nums = DB::table('order_detail')->where('p_id', $key)->whereIn('order_id', $order_ids)->pluck('od_num');
                    $detail_num = array_sum((array)$detail_nums);
                } else {
                    $detail_num = 0;
                }
                if ($buy_num + $detail_num > $daily_limit) {
                    return error('30004', '有商品超出购买限制', ['p_id' => $key]);
                }
            }

        }
        $details = [];
        $totalMoney = '0';
        $order_discount = '0';
        $user_surplus_money = round($user_extra->shopp / $market_price, 2);

        for ($i = 0; $i < $num; $i++) {
            $product = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_t_score', 'is_deduction', 'deduction_money', 'is_size', 'p_stock', 'max_num')->where('p_id', $goods[$i]['p_id'])->first();

            //用于判断购买商品最大件数
            if ($product->max_num >= 0) {
                $is_next = $this->is_max($product->p_id, $user_id, $product->max_num);
                if ($is_next == 1) {
                    return error('30004', '累计只能购买' . $product->max_num . '件');
                }
            }

            $product->size_id = $goods[$i]['size_id'];
            $product->od_num = $goods[$i]['od_num'];
            if ($goods[$i]['size_id'] && $product->is_size) {
                $size = DB::table('size')->where('size_id', $goods[$i]['size_id'])->first();
                if ($size->size_stock < $goods[$i]['od_num']) {
                    return error('30005', '有商品库存不足', ['p_id' => $goods[$i]['p_id'], 'size_id' => $goods[$i]['size_id']]);
                }
                if ($size->size_img) {
                    $product->p_list_pic = $size->size_img;
                }
                $product->p_t_score = $size->size_t_score;
                $product->size_title = $size->size_title;
            } else {
                if ($product->p_stock < $goods[$i]['od_num']) {
                    return error('30005', '有商品库存不足', ['p_id' => $goods[$i]['p_id'], 'size_id' => $goods[$i]['size_id']]);
                }
                $product->size_title = '';
            }

            $totalMoney += $product->p_t_score * $goods[$i]['od_num'];

            if ($discount) {
                //单个商品最多可抵扣
                $product_discount = $product->deduction_money * $goods[$i]['od_num'];


                if ($user_surplus_money >= $product_discount) {
                    //全部抵消
                    $order_discount += $product_discount;

                    $product->discount = $product_discount;

                    $user_surplus_money -= $product_discount;
                } else {
                    $order_discount += $user_surplus_money;

                    $product->discount = $user_surplus_money;

                    $user_surplus_money = '0';
                }
            } else {
                $product->discount = '0';
            }
            $details[] = $product;


        }

        $totalMoney = round($totalMoney, 2);

//        if($user_extra->unlimit < $totalMoney){
//            return error('40014','余额不足');
//        }
        $now = time();
        DB::beginTransaction();

        $order_data = [
            'user_id' => $user_id,
            'order_sn' => 'P' . date('YmdHis', $now) . rand(10000, 99999),
            'consignee' => $consignee,
            'mobile' => $mobile,
            'area' => $area,
            'address' => $address,
            'order_remarks' => $remarks,
            'order_money' => $totalMoney,
            'order_discount' => $order_discount,
            'market_price' => $market_price,
            'order_gmt_create' => $now,
            'order_gmt_expire' => $now + ORDER_EXPIRE_TIME,

        ];

        $order_id = DB::table('orders')->insertGetId($order_data, 'order_id');
        $flag = true;
        if (empty($order_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            foreach ($details as $detail) {
                $detail_data = [
                    'order_id' => $order_id,
                    'p_id' => $detail->p_id,
                    'p_title' => $detail->p_title,
                    'p_img' => $detail->p_list_pic,
                    'size_id' => $detail->size_id,
                    'size_title' => $detail->size_title,
                    'od_num' => $detail->od_num,
                    'od_price' => $detail->p_t_score,
                    'od_discount' => $detail->discount,
                ];

                $od_id = DB::table('order_detail')->insertGetId($detail_data, 'od_id');

                if (empty($od_id)) {
                    $flag = false;
                }
                if ($detail->is_size) {
                    DB::table('size')->where('size_id', $detail->size_id)->decrement('size_stock', $detail->od_num);
                } else {
                    DB::table('product')->where('p_id', $detail->p_id)->decrement('p_stock', $detail->od_num);
                }

            }
            if ($flag) {
                DB::commit();
                $data = array(
                    'order_id' => $order_id,
                );
                return success($data);
            } else {
                DB::rollBack();
                return error('99999', '操作失败');
            }
        }


    }

    /**
     * description:下单 余额专区
     * @author Harcourt
     * @date 2018/8/10
     */
    public function doYOrder(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $consignee = $request->input('consignee');
        $mobile = $request->input('mobile');
        $area = $request->input('area');
        $address = $request->input('address');
        $goods = $request->input('goods');
        $remarks = $request->input('remarks', '无');
        $password = $request->input('password');

        if (empty($user_id) || empty($consignee) || empty($area) || empty($address) || empty($goods) || empty($password)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }

        $target = ['p_id', 'size_id', 'od_num'];
        $goods = json_decode($goods, true);
        if (!$verification->fun_array($goods, $target)) {
            return error('99998', '非法操作,商品不正确');
        }

        $detail = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_balance', 'is_size', 'p_type', 'max_num')->where('p_id', $goods['p_id'])->first();

        //用于判断购买商品最大件数
        if ($detail->max_num >= 0) {
            $is_next = $this->is_max($detail->p_id, $user_id, $detail->max_num);
            if ($is_next == 1) {
                return error('30004', '累计只能购买' . $detail->max_num . '件');
            }
        }

        if (empty($detail)) {
            return error('30001', '商品不存在');
        }
        // if ($detail->p_type != PRODUCT_TYPE_YUE) {
        //     return error('99998', '非法操作,非指定商品');
        // }
        $detail->size_id = $goods['size_id'];
        $detail->od_num = $goods['od_num'];
        $detail->size_title = '';

        if ($detail->is_size && $goods['size_id']) {
            $size = DB::table('size')->where('size_id', $goods['size_id'])->first();
            if (empty($size)) {
                $detail->size_title = '';
            } else {

                if ($size->size_stock < $goods['od_num']) {
                    return error('30005', '商品库存不足', ['p_id' => $goods['p_id'], 'size_id' => $goods['size_id']]);
                }
                if ($size->size_img) {
                    $detail->p_list_pic = $size->size_img;
                }
                $detail->size_title = $size->size_title;
                $detail->p_balance = $size->size_balance;
            }
        }
        $totalMoney = round($detail->p_balance * $goods['od_num'], 2);

        $now = time();
        $user_extra = DB::table('users')->select('mq_users_extra.pay_password', 'users.clientid', 'users.device', 'user_account.balance')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->join('user_account', 'user_account.user_id', '=', 'users.user_id')->where('users.user_id', $user_id)->first();
        if (empty($user_extra)) {
            return error('99998', '非法操作,用户信息不存在');
        }

//        if (strcmp($user_extra->pay_password, $password) !== 0) {
//            return error('40005', '支付密码不正确');
//        }

        if ($totalMoney > $user_extra->balance) {
            return error('40014', '余额不足');
        }

        DB::beginTransaction();

        $order_data = [
            // 'user_id' => $user_id,
            // 'order_sn' => 'Y' . date('YmdHis', $now) . rand(10000, 99999),
            // 'consignee' => $consignee,
            // 'mobile' => $mobile,
            // 'area' => $area,
            // 'address' => $address,
            // 'order_remarks' => $remarks,
            // 'order_money' => $totalMoney,
            // 'order_balance' => $totalMoney,
            // 'order_type' => PRODUCT_TYPE_YUE,
            // 'order_status' => '2',
            // 'order_gmt_create' => $now,
            // 'order_gmt_pay' => $now,
            'user_id' => $user_id,
            'order_sn' => 'P' . date('YmdHis', $now) . rand(10000, 99999),
            'consignee' => $consignee,
            'mobile' => $mobile,
            'area' => $area,
            'address' => $address,
            'order_remarks' => $remarks,
            'order_money' => $totalMoney,
            //'order_cash' => $totalCash,
            'order_balance' => $totalMoney,
            'order_type' => PRODUCT_TYPE_PINPAI,
            'order_status' => '2',
            'order_gmt_create' => $now,
            'order_gmt_expire' => $now + ORDER_EXPIRE_TIME,
        ];
        DB::table('carts')->where(['p_id' => $goods['p_id'], 'user_id' => $user_id])->delete();
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
        ];
        $od_id = DB::table('order_detail')->insertGetId($detail_data, 'od_id');
        if (empty($od_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        }
        if ($detail->is_size) {
            DB::table('size')->where('size_id', $detail->size_id)->decrement('size_stock', $detail->od_num);
        } else {
            DB::table('product')->where('p_id', $detail->p_id)->decrement('p_stock', $detail->od_num);
        }
        DB::table('user_account')->where('user_id', $user_id)->decrement('balance', $totalMoney);
        $flow_data = [
            'user_id' => $user_id,
            'type' => FLOW_LOG_TYPE_BALANCE,
            'status' => 2,
            'amount' => $totalMoney,
            'surplus' => $user_extra->balance - $totalMoney,
            'notes' => '购买专区商品',
            'create_at' => $now,
            'target_id' => $order_id,
            'target_type' => 1

        ];
        $foid = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        if (empty($foid)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            $data = ['order_id' => $order_id];

            success($data);
        }


    }


    public function doYOrder_old(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $consignee = $request->input('consignee');
        $mobile = $request->input('mobile');
        $area = $request->input('area');
        $address = $request->input('address');

        $goods = $request->input('goods');
        $remarks = $request->input('remarks', '无');
        $password = $request->input('password');

//        $user_id = 20175;
//        $goods = '{"size_id":"0","p_id":"1","od_num":"1"}';
//        $mobile = '15757166666';

        if (empty($user_id) || empty($consignee) || empty($area) || empty($address) || empty($goods) || empty($password)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }

        $target = ['p_id', 'size_id', 'od_num'];
        $goods = json_decode($goods, true);
        if (!$verification->fun_array($goods, $target)) {
            return error('99998', '非法操作');
        }

        $user_extra = DB::table('users')->select('mq_users_extra.pay_password', 'users.clientid', 'users.device')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('users.user_id', $user_id)->first();
        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }

        if (strcmp($user_extra->pay_password, $password) !== 0) {
            return error('40005', '支付密码不正确');
        }


        $detail = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_ticket_score', 'is_size', 'max_num')->where('p_id', $goods['p_id'])->first();
        if (empty($detail)) {
            return error('30001', '商品不存在');
        }

        //用于判断购买商品最大件数
        if ($product->max_num >= 0) {
            $is_next = $this->is_max($product->p_id, $user_id, $product->max_num);
            if ($is_next == 1) {
                return error('30004', '累计只能购买' . $product->max_num . '件');
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
                $detail->p_ticket_score = $size->size_ticket_score;
            }
        }
        $totalMoney = round($detail->p_ticket_score * $goods['od_num'], 2);

        $daily_limit = DB::table('product_extra')->where('p_id', $goods['p_id'])->pluck('daily_buy_limit')->first();

        $buy_num = 0;
        $now = time();
        if ($daily_limit) {
            $buy_num += $goods['od_num'];

            if ($buy_num > $daily_limit) {
                return error('30004', '有商品超出购买限制', ['p_id' => $goods['p_id']]);
            }
            $nowDay = date('Y-m-d', $now);

            $owhere = [
                ['user_id', $user_id],
                ['order_gmt_create', '>=', strtotime($nowDay)],
                ['order_gmt_create', '<', strtotime($nowDay . '+ 1 day')],
                ['order_status', '>=', '2']
            ];
            $order_ids = DB::table('orders')->where($owhere)->pluck('order_id');
            if ($order_ids) {
                $detail_nums = DB::table('order_detail')->where('p_id', $goods['p_id'])->whereIn('order_id', $order_ids)->pluck('od_num');
                $detail_num = array_sum((array)$detail_nums);
            } else {
                $detail_num = 0;
            }
            if ($buy_num + $detail_num > $daily_limit) {
                return error('30004', '有商品超出购买限制', ['p_id' => $goods['p_id']]);
            }
        }

        $coupon = DB::table('tps')->where('user_id', $user_id)->value('coupon');
        if ($coupon == null) {
            $coupon = '0';
        }
        if ($totalMoney > $coupon) {
            return error('40014', '余额不足');
        }

        DB::beginTransaction();

        $order_data = [
            'user_id' => $user_id,
            'order_sn' => 'Y' . date('YmdHis', $now) . rand(10000, 99999),
            'consignee' => $consignee,
            'mobile' => $mobile,
            'area' => $area,
            'address' => $address,
            'order_remarks' => $remarks,
            'order_money' => $totalMoney,
            'order_type' => '1',
            'order_status' => '2',
            'order_gmt_create' => $now,
            'order_gmt_pay' => $now,
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
            'od_price' => $detail->p_ticket_score,
        ];
        $od_id = DB::table('order_detail')->insertGetId($detail_data, 'od_id');
        if (empty($od_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        }
        if ($detail->is_size) {
            DB::table('size')->where('size_id', $detail->size_id)->decrement('size_stock', $detail->od_num);
        } else {
            DB::table('product')->where('p_id', $detail->p_id)->decrement('p_stock', $detail->od_num);
        }

        DB::update('UPDATE xm_tps SET coupon = coupon - ? WHERE user_id = ?', [$totalMoney, $user_id]);
        $flow_data = [
            'user_id' => $user_id,
            'type' => 5,
            'status' => 2,
            'amount' => $totalMoney,
            'surplus' => $coupon - $totalMoney,
            'notes' => '购买易业商城商品',
            'create_at' => $now,
            'target_id' => $order_id,
            'target_type' => 1

        ];
        $foid = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        if (empty($foid)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            $data = ['order_id' => $order_id];

            success($data);
        }


    }

    /**
     * description:收银台
     * @author Harcourt
     * @date 2018/8/16
     */
    public function readyToPay(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);

        if (empty($user_id) || empty($order_id)) {
            return error('00000', '参数不全');
        }
        $order = DB::table('orders')->select('user_id', 'order_status', 'order_cancel', 'order_gmt_expire', 'order_money', 'order_discount', 'order_balance', 'order_cash', 'order_delete', 'order_type')->where('order_id', $order_id)->first();
        if (empty($order) || $order->order_status != 1 || $user_id != $order->user_id || $order->order_cancel != 1) {
            // || !in_array($order->order_type, [PRODUCT_TYPE_BAODAN,PRODUCT_TYPE_PINPAI])
            return error('99998', '非法操作');
        }
        $need_money = (string)($order->order_cash);
        $t_score = DB::table('user_account')->where('user_id', $user_id)->pluck('balance')->first();
        if ($t_score == null) {
            return error('99998', '非法操作');
        }
        $payable = '0';
        if ($t_score >= $need_money) {
            $payable = '1';
        }

        $tpay_show = '1';
        $wxpay_show = '1';
        $alipay_show = '1';

        $configStr = DB::table('master_config')->where('code', 'is_t_wechat_alipay')->value('value');

        if ($configStr) {
            $configs = explode('-', $configStr);
            $sumC = array_sum($configs);
            if (count($configs) == 3 && $sumC != 0) {
                $tpay_show = $configs[0];
                $wxpay_show = $configs[1];
                $alipay_show = $configs[2];
            }
        }

        $data = [
            'money' => $need_money,
            'expire_time' => $order->order_gmt_expire,
            't_score' => $t_score,
            'payable' => $payable,
            'tpay_show' => $tpay_show,
            'wxpay_show' => $wxpay_show,
            'alipay_show' => $alipay_show
        ];
        success($data);

    }


    /**
     * description:检查订单是否已完成支付
     * @author Harcourt
     * @date 2018/8/15
     */
    public function checkOrderPay(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);

        if (empty($user_id) || empty($order_id)) {
            return error('00000', '参数不全');
        }
        $order = DB::table('orders')->select('user_id', 'order_status', 'order_cancel', 'order_gmt_create', 'order_money', 'order_discount', 'order_delete')->where('order_id', $order_id)->first();
        if (empty($order) || $user_id != $order->user_id || $order->order_cancel != 1) {
            return error('99998', '非法操作');
        }

        $toUser = DB::table('users')->select('clientid', 'device')->where('user_id', $user_id)->first();
        if (empty($toUser)) {
            return error('99998', '非法操作');
        }

        if ($order->order_status != 2) {
            $msg = '支付失败';
        } else {
            $msg = '支付成功';


            $title = '订单成功通知';

            $content = '订单已支付成功,请等待商家发货';

            $mtype = '5';
            $custom_content = ['id' => $order_id, 'type' => $mtype, 'content' => $content, 'title' => $title];

            $push_data = array(
                'user_id' => $user_id,
                'm_type' => $mtype,
                'o_id' => $order_id,
                'm_title' => $title,
                'm_read' => '1',
                'm_content' => $content,
                'm_gmt_create' => time()
            );
            $message_id = DB::table('message')->insertGetId($push_data, 'm_id');
            if ($message_id) {
//                $bol = $toUser->device=='android'?true:false;
                $bol = false;
                GeTui::push($toUser->clientid, $custom_content, $bol);
                //查询购买的商品数量
                $order_detail = DB::table('order_detail')->select('p_id', 'od_num')->where('order_id', $order_id)->first();
                //更新商品的字段
                DB::table('product')->where('p_id', $order_detail->p_id)->increment('p_sold_num', $order_detail->od_num);
            }
        }

        success([], $msg);
    }

    /**
     * description:余额支付
     * @author Harcourt
     * @date 2018/8/14
     */
    public function yuePay(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);

        $password = $request->input('password');

        if (empty($user_id) || empty($order_id) || empty($password)) {
            return error('00000', '参数不全');
        }

        $order = DB::table('orders')->select('user_id', 'order_status', 'order_cancel', 'order_gmt_create', 'order_gmt_expire', 'order_money', 'order_discount', 'order_payway', 'market_price', 'order_delete')->where('order_id', $order_id)->first();
        $now = time();
        if (empty($order) || $user_id != $order->user_id || $order->order_status != 1 || $order->order_cancel != 1 || $order->order_delete != 1 || $now > $order->order_gmt_expire) {
            return error('99998', '非法操作');
        }
        $user_tps = DB::table('tps')->select('unlimit', 'shopp', 'pay_password', 'new_status')->where('tps.user_id', $user_id)->join('mq_users_extra', 'mq_users_extra.user_id', '=', 'tps.user_id')->first();
        if (empty($user_tps)) {
            return error('99998', '非法操作');
        }
        if (strcmp($user_tps->pay_password, $password) !== 0) {
            return error('40005', '支付密码不正确');
        }
        $need_consume = round($order->order_discount * $order->market_price, 2);
        if ($need_consume > $user_tps->shopp) {
            DB::table('orders')->where('order_id', $order_id)->update(['order_cancel' => 3]);
            return error('60001', '订单状态发生改变，请重新下单');
        }

        $new_status = explode('-', $user_tps->new_status);

        if (empty($new_status)) {
            return error('99998', '非法操作');
        }

        if ($new_status[1] == 1) {
            return error('99996', '该账号暂时不能购买');
        }

        $nowDay = date('Y-m-d', $now);
        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');


        $sell_limit = DB::table('mq_users_limit')->select('daily_deal_sell_max_money as daily_limit', 'unlimit as useable_t_score', 'freeze as freeze_t_score')->join('tps', 'tps.user_id', '=', 'mq_users_limit.user_id')->where('mq_users_limit.user_id', $user_id)->first();

        if (empty($sell_limit)) {
            return error('99998', '非法操作');
        }
        //个人未设置
        if ($sell_limit->daily_limit == '-1') {

            $group_daily_limit = DB::table('group_limit')->where('user_id', $user_id)->pluck('daily_deal_sell_max_money')->first();

            //服务中心未设置
            if ($group_daily_limit == '-1') {

                $system_daily_limit = DB::table('master_config')->where('code', 'daily_deal_sell_max_money')->pluck('value')->first();
                $sell_limit->daily_limit = $system_daily_limit;
            } else {
                $sell_limit->daily_limit = $group_daily_limit;

            }
        }

        //每天余额购买精品商品
        $orderwhere = [
            ['order_gmt_pay', '>=', $day_start],
            ['order_gmt_pay', '<', $day_end],
            ['user_id', $user_id],
            ['order_type', 2]
        ];
        $orderMoney = DB::table('orders')->selectRaw('sum(order_money) as orderMoney')->where($orderwhere)->value('orderMoney');
        if ($orderMoney == null) {
            $orderMoney = '0';
        }

        //每天出售的额度
        $tradeWhere = [
            ['user_id', $user_id],
            ['trade_status', '!=', 4],
            ['trade_gmt_create', '>=', $day_start],
            ['trade_gmt_create', '<', $day_end],
        ];

        $tradeMoney = DB::table('trade')->selectRaw('sum(trade_num) as tradeMoney')->where($tradeWhere)->value('tradeMoney');
        if ($tradeMoney == null) {
            $tradeMoney = '0';
        }

        if ($tradeMoney + $orderMoney + $order->order_money > $sell_limit->daily_limit) {
            return error('40019', '超出当天买卖限额');
        }


        $need_money = $order->order_money - $order->order_discount;
        if ($need_money > $user_tps->unlimit) {
            return error('40014', '余额不足');
        }

        $this_details = DB::table('order_detail')->select('p_id', 'size_id', 'od_num')->where('order_id', $order_id)->get()->toArray();
        if (empty($this_details)) {
            return error('99998', '非法操作');
        }
        $num = count($this_details);
        $pids = array_column($this_details, 'p_id');
        $pid_count = array_count_values($pids);


        foreach ($pid_count as $key => $value) {
            $daily_limit = DB::table('product_extra')->where('p_id', $key)->pluck('daily_buy_limit')->first();

            $buy_num = 0;
            if ($daily_limit) {
                for ($i = 0; $i < $num; $i++) {
                    if ($this_details[$i]->p_id == $key) {
                        $buy_num += $this_details[$i]->od_num;
                    }
                }

                if ($buy_num > $daily_limit) {
                    return error('30004', '有商品超出购买限制', ['p_id' => $key]);
                }
                $owhere = [
                    ['user_id', $user_id],
                    ['order_gmt_create', '>=', $day_start],
                    ['order_gmt_create', '<', $day_end],
                    ['order_status', '>=', '2']
                ];
                $order_ids = DB::table('orders')->where($owhere)->pluck('order_id');
                if ($order_ids) {
                    $detail_nums = DB::table('order_detail')->where('p_id', $key)->whereIn('order_id', $order_ids)->pluck('od_num');
                    $detail_num = array_sum((array)$detail_nums);
                } else {
                    $detail_num = 0;
                }
                if ($buy_num + $detail_num > $daily_limit) {
                    return error('30004', '有商品超出购买限制', ['p_id' => $key]);
                }
            }

        }
        $t_all = DB::table('master_config')->where('code', 'xm_t_all')->value('amount');
        if ($t_all == null) {
            $t_all = 0;
        }
        DB::beginTransaction();

        $update_data = [
            'order_status' => '2',
            'order_gmt_pay' => $now,
        ];
        DB::table('orders')->where('order_id', $order_id)->update($update_data);
        DB::update('update xm_tps set unlimit = unlimit - ?,shopp = shopp - ? WHERE user_id = ?', [$need_money, $need_consume, $user_id]);
        $tps = DB::table('tps')->select('shopp', 'unlimit')->where('user_id', $user_id)->first();

        $base_data = [
            'user_id' => $user_id,
            'status' => 2,
            'create_at' => $now,
            'type' => 2,
            'amount' => $need_money,
            'surplus' => $tps->unlimit,
            'notes' => '购买精品',
            'target_id' => $order_id,
            'target_type' => 1
        ];
        $foid1 = DB::table('flow_log')->insertGetId($base_data, 'foid');


        $base_data['user_id'] = 0;
        $base_data['status'] = 1;
        $base_data['amount'] = $need_money;
        $base_data['surplus'] = $t_all + $need_money;
        $base_data['notes'] = '购买精品';
        $base_data['isall'] = 1;
        $foid2 = DB::table('flow_log')->insertGetId($base_data, 'foid');

        if ($need_consume) {
            $base_data['type'] = 3;
            $base_data['status'] = 2;
            $base_data['user_id'] = $user_id;
            $base_data['amount'] = $need_consume;
            $base_data['surplus'] = $tps->shopp;
            $base_data['notes'] = '购买精品,消耗消费积分';
            $base_data['isall'] = 0;

            $foid3 = DB::table('flow_log')->insertGetId($base_data, 'foid');
            if (empty($foid3)) {
                DB::rollBack();
                return error('99999', '操作失败');
            }
        }


        if (empty($foid1) || empty($foid2)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            return success();
        }

    }

    /**
     * description:支付宝、微信支付
     * @author Harcourt
     * @date 2018/8/15
     */

    public function onlinePay(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);

        $type = $request->input('type');//1、支付宝2、微信

        if (empty($user_id) || empty($order_id) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');
        }

        $order = DB::table('orders')->select('order_sn', 'user_id', 'order_status', 'order_cancel', 'order_gmt_create', 'order_gmt_expire', 'order_money', 'order_cash', 'order_discount', 'order_delete')->where('order_id', $order_id)->first();
        if (empty($order) || $user_id != $order->user_id || $order->order_status != 1 || $order->order_cancel != 1 || $order->order_delete != 1 || time() > $order->order_gmt_expire) {
            return error('99998', '非法操作');
        }
        $need_money = $order->order_cash;
        if ($need_money <= 0) {
            return error('99998', '非法操作');
        }

        if (in_array($user_id, $this->testPhone)) {
            $need_money = '0.01';
        }
        $sn = $order->order_sn;
        $body = '品牌专区购物';
        if ($type == '1') {
            $alipay = new \Alipay();
            $subject = '火单';
            $subject = '火单';
            $expire = time() + 60 * 5;
            $res = $alipay->unifiedorder($sn, $subject, $body, $need_money, $expire);
            $data['sign'] = $res;

        } elseif ($type == '2') {
            $wxpay = new \Wxpay();
            $data = $wxpay->unifiedorder($sn, $need_money, $body);
        }
        $data['order_id'] = $order_id;

        success($data);

    }

    public function onlinePay_old(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);

        $type = $request->input('type');//1、支付宝2、微信

        if (empty($user_id) || empty($order_id) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');
        }

        $order = DB::table('orders')->select('order_sn', 'user_id', 'order_status', 'order_cancel', 'order_gmt_create', 'order_gmt_expire', 'order_money', 'order_discount', 'market_price', 'order_delete')->where('order_id', $order_id)->first();
        $now = time();
        if (empty($order) || $user_id != $order->user_id || $order->order_status != 1 || $order->order_cancel != 1 || $order->order_delete != 1 || $now > $order->order_gmt_expire) {
            return error('99998', '非法操作');
        }
        $user_tps = DB::table('tps')->select('unlimit', 'shopp', 'pay_password', 'new_status')->where('tps.user_id', $user_id)->join('mq_users_extra', 'mq_users_extra.user_id', '=', 'tps.user_id')->first();
        if (empty($user_tps)) {
            return error('99998', '非法操作');
        }
        $need_consume = round($order->order_discount * $order->market_price, 2);

        if ($need_consume > $user_tps->shopp) {
            DB::table('orders')->where('order_id', $order_id)->update(['order_cancel' => 3]);
            return error('60001', '订单状态发生改变，请重新下单');
        }

        $new_status = explode('-', $user_tps->new_status);

        if (empty($new_status)) {
            return error('99998', '非法操作');
        }

        if ($new_status[1] == 1) {
            return error('99996', '该账号暂时不能购买');
        }

        $need_money = $order->order_money - $order->order_discount;


        $this_details = DB::table('order_detail')->select('p_id', 'size_id', 'od_num')->where('order_id', $order_id)->get()->toArray();
        if (empty($this_details)) {
            return error('99998', '非法操作');
        }
        $num = count($this_details);
        $pids = array_column($this_details, 'p_id');
        $pid_count = array_count_values($pids);

        $nowDay = date('Y-m-d', $now);

        foreach ($pid_count as $key => $value) {
            $daily_limit = DB::table('product_extra')->where('p_id', $key)->pluck('daily_buy_limit')->first();

            $buy_num = 0;
            if ($daily_limit) {
                for ($i = 0; $i < $num; $i++) {
                    if ($this_details[$i]->p_id == $key) {
                        $buy_num += $this_details[$i]->od_num;
                    }
                }

                if ($buy_num > $daily_limit) {
                    return error('30004', '有商品超出购买限制', ['p_id' => $key]);
                }
                $owhere = [
                    ['user_id', $user_id],
                    ['order_gmt_create', '>=', strtotime($nowDay)],
                    ['order_gmt_create', '<', strtotime($nowDay . '+ 1 day')],
                    ['order_status', '>=', '2']
                ];
                $order_ids = DB::table('orders')->where($owhere)->pluck('order_id');
                if ($order_ids) {
                    $detail_nums = DB::table('order_detail')->where('p_id', $key)->whereIn('order_id', $order_ids)->pluck('od_num');
                    $detail_num = array_sum((array)$detail_nums);
                } else {
                    $detail_num = 0;
                }
                if ($buy_num + $detail_num > $daily_limit) {
                    return error('30004', '有商品超出购买限制', ['p_id' => $key]);
                }
            }

        }
        $sn = $order->order_sn;
        $body = '精品商城购物';
        if ($type == '1') {
            $alipay = new \Alipay();
            $subject = '火单';
            $expire = time() + 60 * 5;
            // $need_money = '0.01';
            $res = $alipay->unifiedorder($sn, $subject, $body, $need_money, $expire);
            $data['sign'] = $res;

        } elseif ($type == '2') {
            $wxpay = new \Wxpay();
            // $need_money = '0.01';
            $data = $wxpay->unifiedorder($sn, $need_money, $body);
        }
        $data['order_id'] = $order_id;

        success($data);

    }

    /**
     * description:订单列表
     * @author Harcourt
     * @date 2018/8/21
     */
    public function lists(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 0);//0、全部1、待付款2、待发货3、待收货4、待评价
        $page = $request->input('page', 0);
        $pos = $request->input('pos', 3);//1 报单2、余额3、品牌,4新人

        if (empty($user_id) || !in_array($type, [0, 1, 2, 3, 4]) || !in_array($pos, [PRODUCT_TYPE_BAODAN, PRODUCT_TYPE_YUE, PRODUCT_TYPE_PINPAI, PRODUCT_TYPE_BAODAN_MONEY])) {
            return error('00000', '参数不全');
        }
        $where = [
            ['user_id', $user_id],
            ['order_delete', 1],
            ['order_cancel', '!=', 2],
        ];
        if ($pos == 2) {
            $where[] = ['order_type', PRODUCT_TYPE_PINPAI];
            if ($type) {
                $where[] = ['order_status', $type];
            }
        } elseif ($pos == 1) {
            $where[] = ['order_type', '!=', PRODUCT_TYPE_PINPAI];
            if ($type != 0) {
                $where[] = ['order_status', $type];
            } else {
                $where[] = ['order_status', '>', 0];
            }
        }

//        if($pos == PRODUCT_TYPE_YUE){
//            $where[] = ['order_type',PRODUCT_TYPE_YUE];
//            if($type != 1 && $type != 0){
//                $where[] = ['order_status', $type];
//            }
//        }else{
//            if($type){
//                $where[] = ['order_status', $type];
//            }
//        }


        $orderBy = 'order_id';
        if ($type == 2) {
            $orderBy = 'order_gmt_pay';
        } elseif ($type == 3) {
            $orderBy = 'order_gmt_send';
        } elseif ($type == 4) {
            $orderBy = 'order_gmt_sure';
        }

        $limit = 20;
        $offset = $limit * $page;
        $orders = DB::table('orders')->selectRaw('order_id,order_sn,(order_money - order_discount) as order_money,order_status,order_cancel,order_type')->where($where)->orderBy($orderBy, 'desc')->offset($offset)->limit($limit)->get();

        foreach ($orders as $order) {
            $order_goods = DB::table('order_detail')->selectRaw('od_id,p_id,p_title,concat(?,p_img) as p_img,size_title,od_num,od_balance,od_cash,is_comment', [IMAGE_DOMAIN])->where('order_id', $order->order_id)->get();
            $order->order_goods = $order_goods;
        }
        success($orders);

    }

    /**
     * description:订单详情
     * @author Harcourt
     * @date 2018/8/23
     */
    public function detail(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);


        if (empty($user_id) || empty($order_id)) {
            return error('00000', '参数不全');
        }

        $order = DB::table('orders')->select('order_id', 'user_id', 'order_cancel', 'order_sn', 'order_money', 'order_discount', 'consignee', 'mobile', 'area', 'address', 'order_status', 'order_remarks', 'order_gmt_create', 'order_gmt_pay', 'order_gmt_send', 'order_gmt_sure', 'order_gmt_expire', 'order_type', 'order_cash', 'order_balance', 'shipping_number', 'shipping_name')->where('order_id', $order_id)->first();
        if (empty($order)) {
            return error('30007', '订单不存在');
        }
        if ($order->user_id != $user_id) {
            return error('99998', '非法操作');
        }
        $order->order_auto_sure = $order->order_gmt_send + AUTO_SURE_ORDER;

        $order->order_gmt_create = getDateFormate($order->order_gmt_create);
        $order->order_gmt_pay = getDateFormate($order->order_gmt_pay);
        $order->order_gmt_send = getDateFormate($order->order_gmt_send);
        $order->order_gmt_sure = getDateFormate($order->order_gmt_sure);

        $order_goods = DB::table('order_detail')->selectRaw('od_id,p_id,p_title,concat(?,p_img) as p_img,size_title,od_num,od_balance,od_cash,is_comment', [IMAGE_DOMAIN])->where('order_id', $order->order_id)->get();
        $order->order_goods = $order_goods;
        success($order);
    }

    /**
     * description:订单删除、取消
     * @author Harcourt
     * @date 2018/8/23
     */
    public function deal(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $order_id = $request->input('order_id', 0);

        $type = $request->input('type');//1、取消2、删除3、确认收货

        if (empty($user_id) || empty($order_id) || !in_array($type, [1, 2, 3])) {
            return error('00000', '参数不全');
        }

        $order = DB::table('orders')->select('user_id', 'order_type', 'order_status', 'order_cancel', 'order_gmt_expire', 'order_delete', 'order_balance')->where('order_id', $order_id)->first();
        if (empty($order)) {
            return error('30007', '订单不存在');
        }
        if ($order->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        if (($type == 1 && $order->order_status != 1) || ($type == 2 && ($order->order_status != 4 && $order->order_status != 5 && $order->order_cancel != 3)) || ($type == 3 && $order->order_status != 3)) {
            return error('99998', '非法操作');
        }
        DB::beginTransaction();
        if ($type == 1) {
            $update_data = ['order_cancel' => 2];
            if ($order->order_type == PRODUCT_TYPE_PINPAI) {
                DB::table('user_account')->where('user_id', $user_id)->decrement('pending_balance', $order->order_balance);
                DB::table('user_account')->where('user_id', $user_id)->increment('balance', $order->order_balance);
            }
            //返还库存
            $order_goods = DB::table('order_detail')->select('p_id', 'size_id', 'od_num')->where('order_id', $order_id)->get();
            foreach ($order_goods as $order_good) {
                if ($order_good->size_id) {
                    DB::update('UPDATE xm_size SET size_stock = size_stock + ? WHERE size_id = ?', [$order_good->od_num, $order_good->size_id]);
                } else {
                    DB::update('UPDATE xm_product SET p_stock = p_stock + ? WHERE p_id = ?', [$order_good->od_num, $order_good->p_id]);
                }

            }


        } else if ($type == 2) {
            $update_data = ['order_delete' => 2];
        } else {
            $update_data = ['order_status' => 4];
        }

        $aff_row = DB::table('orders')->where('order_id', $order_id)->update($update_data);
        if ($aff_row) {
            DB::commit();
            success();
        } else {
            DB::rollBack();
        }
    }

    /**
     * description:查看是否超出设置
     * @author libaowei
     * @date 2019/8/7
     */
    private function is_max($p_id, $user_id, $max_num)
    {
        //查询用户之前购买的该商品数量
        $order = DB::table('order_detail')->join('orders', 'order_detail.order_id', '=', 'orders.order_id')->where([['order_detail.p_id', $p_id], ['orders.user_id', $user_id], ['orders.order_cancel', 1], ['orders.order_delete', 1]])->count();
        //进行判断
        if ($max_num <= $order) {
            return $is_next = 1;
        }
    }

}
