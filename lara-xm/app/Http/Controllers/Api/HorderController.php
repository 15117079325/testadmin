<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use ShaoZeMing\GeTui\Facade\GeTui;

class HorderController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['aboutH', 'goodsList']);
    }

    /**
     * description:下单
     * @author douhao
     * @date 2018/8/10
     */
    public function doOrder(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $goods_id = $request->input('goods_id', 0);
        $buy_num = $request->input('buy_num', 1);
        $pay_password = $request->input('password', 0);

        if ($user_id == 0 || $goods_id == 0) {
            return error('00000', '参数不全');
        }
        $config = DB::table('master_config')->where('code', 'xm_trade_switch')->first();

        if ($config && $config->value == 1) {
            return error('99997', '暂时关闭该功能');
        }
        $toUser = DB::table('users')->select('clientid', 'device')->where('user_id', $user_id)->first();
        if (empty($toUser)) {
            return error('99998', '非法操作');
        }
        $now = time();
        //查询该账号是否可以购买
        $new_status = DB::table('mq_users_extra')->where('user_id', $user_id)->pluck('new_status')->first();
        $status = explode('-', $new_status);
        if (isset($status[2]) && $status[2] == 1) {
            return error('99996', '该账号暂时不能购买');
        }

        //获取商品当日限购
        $goods_info = DB::table('product_extra')->where('product_extra.p_id', $goods_id)->join('product', 'product_extra.p_id', '=', 'product.p_id')->first();

        //获取用户是否被限制
        $user_limit = DB::table('mq_users_limit')->whereRaw('user_id= ? AND start_time<=? AND (end_time=0 OR end_time>=?) ', [$user_id, $now, $now])->first();

        //每天相同商品只能买一单
        $start_time = strtotime(date('Y-m-d', time()));
        $end_time = $start_time + 3600 * 24 - 1;
        $where = [
            ['goods_id', '=', $goods_id],
            ['user_id', '=', $user_id],
            ['create_at', '>=', $start_time],
            ['create_at', '<=', $end_time],
        ];
        $h_goods_num = DB::table('mq_buy_back')->where($where)->sum('goods_number');
        if ($h_goods_num) {
            return error('70012', '同一款H单商品一天只能购买一单');
        }

        // 限购时间 2018 15:00:00 fuhuaquan 添加
//        $b_hour = DB::table('shop_config')->where('code', 'xm_h_begin')->pluck('value')->first();
//        $e_hour = DB::table('shop_config')->where('code', 'xm_h_end')->pluck('value')->first();
//        //处理好日期格式 2018-3-6 11:01:22
//        $start_time = date('Y-m-d') . " " . $b_hour;
//        $end_time = date('Y-m-d') . " " . $e_hour;
//        $begin_arr = explode(":", $b_hour); //分割数组
//        $end_arr = explode(":", $e_hour);

        //单算 时分秒 的时间戳
//        $b_time = $begin_arr[0] * 3600 + $begin_arr[1] * 60 + $begin_arr[2];
//        $e_time = $end_arr[0] * 3600 + $end_arr[1] * 60 + $end_arr[2];
//        //跨天的情况  2018.3.6 23:00:00 -- 2018.3.7 02:00:00
//        if ($b_time - $e_time >= 0) {
//            $start_time = strtotime($start_time);
//            $end_time = $start_time + 24 * 3600 - $b_time + $e_time;
//
//            //当前的 时分秒
//            $date = date("H:i:s");
//            $now_arr = explode(":", $date);
//            $timestamp = $now_arr[0] * 3600 + $now_arr[1] * 60 + $now_arr[2];
//            //这时候就需要减去一天
//            if ($timestamp <= $e_time) {
//                $start_time = $start_time - 24 * 3600;
//                $end_time = $end_time - 24 * 3600;
//            }
//
//        } else {
        //同一天的时候
//            $start_time = strtotime($start_time);
//            $end_time = strtotime($end_time);
        //  }
        // 2018/3/7 10:00:00  判断当前时间在 限购时间内才限购，否则不限购
        $buy_time = time();
        if ($buy_time >= $start_time && $buy_time <= $end_time) {
            if ($user_limit) {
                //有设置并且有效期内
                if ($user_limit->user_limited == 1) {

                    return error('70010', '该账户被限制');
                }
                //如果是h单商品 使用h单限购数量
                if ($user_limit->hdan_limited == 1) {
                    return error('99996', '该账号暂时不能购买');
                }
            }

            //商品本身的限购设置
            if ($goods_info->bb_daily_buy_limit > 0) {//大于0才限购验证

                //购买数量验证
                if ($buy_num > $goods_info->bb_daily_buy_limit) {
                    return error('70011', '只允许购买一份');
                }

                $where = [
                    ['goods_id', '=', $goods_id],
                    ['user_id', '=', $user_id],
                    ['create_at', '>=', $start_time],
                    ['create_at', '<=', $end_time],
                ];
                $h_nums = DB::table('mq_buy_back')->where($where)->sum('goods_number');
                if (($buy_num + $h_nums) > $goods_info->bb_daily_buy_limit) {
                    return error('70012', '限购');
                }
            }
        }

        //限购时间判断结束

        //支付密码验证

        $where = [
            ['user_id', '=', $user_id],
        ];
        $user_password = DB::table('mq_users_extra')->where($where)->first();
        if (empty($user_password->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (md5($pay_password) != $user_password->pay_password) {
            return error('40005', '密码不正确');
        }

        /* 检查：库存 */
        $xm_goods_info = DB::table('product')->where('p_id', $goods_id)->first();

        if (!$xm_goods_info) {
            return error('30001', '商品不存在');
        }

        //检查：商品购买数量是否大于总库存
        if ($buy_num > $xm_goods_info->p_stock) {
            return error('30005', '库存不足');
        }
        $cash_pay_money = $goods_info->p_m_score * $buy_num;
        $consume_pay_money = $goods_info->p_consume_score * $buy_num;

        if (!$cash_pay_money || !$consume_pay_money) {
            return error('60001', '订单状态发生改变，请重新下单');
        }

        // V 判断可用余额
        $upoint = DB::table('xps')->select('xps.unlimit', 'tps.shopp')->where('xps.user_id', $user_id)->join('tps', 'tps.user_id', '=', 'xps.user_id')->first();

        if ($cash_pay_money > $upoint->unlimit || $consume_pay_money > $upoint->shopp) {
            // 输出提示好几个操作，疯了
            return error('40014', '余额不足');
        }
        //查询联系人
        $user_info = DB::table('users')->select('user_name', 'mobile_phone')->where('user_id', $user_id)->first();
        //生成回购单号
        $bbsn = 'H' . date('Ymd', time()) . str_pad(mt_rand(1, 9999999), 7, '0', 0);

        $expire_at = $now + $goods_info->bb_life_days * 86400;
        $data = array(
            'user_id' => $user_id,
            'goods_id' => $goods_id,
            'goods_name' => $goods_info->p_title,
            'goods_sn' => $goods_info->p_sn,
            'goods_number' => $buy_num,
            'product_id' => 0,
            'consume_money' => $consume_pay_money,
            'cash_money' => $cash_pay_money,
            'bb_status' => 1,
            'create_at' => $now,
            'pay_at' => $now,
            'expire_at' => $expire_at,
            'bb_percent' => 0,
            'cash_money_bb' => $goods_info->cash_money_bb * $buy_num,
            'contact' => $user_info->user_name,
            'contact_phone' => $user_info->mobile_phone,
            'update_at' => $now,
            'goods_attr' => '',
            'goods_attr_id' => '',
            'bb_sn' => $bbsn,
        );

        DB::beginTransaction();
        $bb_id = DB::table('mq_buy_back')->insertGetId($data, 'bb_id');
        $flag = DB::update('update xm_product set p_stock = p_stock - ? WHERE p_id = ?', [$buy_num, $goods_id]);
        $flag1 = DB::update('update xm_xps set amount = amount -?,unlimit = unlimit - ? WHERE user_id = ?', [$cash_pay_money, $cash_pay_money, $user_id]);
        $flag2 = DB::update('update xm_tps set shopp = shopp - ? WHERE user_id = ?', [$consume_pay_money, $user_id]);
        //记录流水
        $base_data = [
            'user_id' => $user_id,
            'status' => 2,
            'create_at' => $now,
            'type' => 1,
            'amount' => $cash_pay_money,
            'surplus' => $upoint->unlimit - $cash_pay_money,
            'notes' => '购买 H 单',
            'target_id' => $bb_id,
            'target_type' => 9
        ];
        $foid = DB::table('flow_log')->insertGetId($base_data, 'foid');
        $base_data = [
            'user_id' => $user_id,
            'status' => 2,
            'create_at' => $now,
            'type' => 3,
            'amount' => $consume_pay_money,
            'surplus' => $upoint->shopp - $consume_pay_money,
            'notes' => '购买 H 单',
            'target_id' => $bb_id,
            'target_type' => 9
        ];
        $foid2 = DB::table('flow_log')->insertGetId($base_data, 'foid');

        $user_str = $this->get_up($user_id);
        $user_str .= $user_id;
        $user_name = DB::table('users')->where('user_id', $user_id)->pluck('user_name')->first();
        $performance = [
            'user_id' => $user_id,
            'tp_num' => $cash_pay_money,
            'tp_gmt_create' => $now,
            'user_name' => $user_name,
            'tp_top_user_ids' => $user_str
        ];
        $tp_id = DB::table('mq_buy_back_team')->insertGetId($performance, 'tp_id');
        if (empty($flag) || empty($flag1) || empty($flag2) || empty($foid) || empty($foid2) || empty($bb_id) || empty($tp_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        } else {
            DB::commit();
            return success();

            $title = 'H单通知';

            $content = '购买H单成功，请到兑换中心查看';

            $mtype = '4';
            $custom_content = ['id' => $bb_id, 'type' => $mtype, 'content' => $content, 'title' => $title];

            $push_data = array(
                'user_id' => $user_id,
                'm_type' => $mtype,
                'o_id' => $bb_id,
                'm_title' => $title,
                'm_read' => '1',
                'm_content' => $content,
                'm_gmt_create' => $now
            );
            $message_id = DB::table('message')->insertGetId($push_data, 'm_id');
            if ($message_id && $toUser->clientid) {
//                $bol = $toUser->device=='android'?true:false;
                $bol = false;
                GeTui::push($toUser->clientid, $custom_content, $bol);
            }


        }
    }

    /**
     * description:H单收益
     * @author douhao
     * @date 2018/8/10
     */
    public function earnings(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if ($user_id == 0) {
            return error('00000', '参数不全');
        }
        //查出最近一单的时间
        $where = [
            ['user_id', '=', $user_id],
            ['bb_status', '=', 1],
            ['is_stop', '=', 0],
        ];
        $info = DB::table('mq_buy_back')->select('expire_at', 'cash_money_bb', 'cash_money')->where($where)->orderBy('create_at', 'ASC')->limit(1)->first();
        //进度条
        $total_time = 16 * 24 * 3600;
        if ($info) {
            $left_time = $total_time - ($info->expire_at - time());
            $data = [
                'expire_time' => $info->expire_at - time(),
                'release_money' => ($info->cash_money_bb - $info->cash_money) * 0.8,
                'total_time' => $total_time,
                'left_time' => $left_time,
            ];
        } else {
            $data = [
                'expire_time' => 0,
                'release_money' => '0',
                'total_time' => 0,
                'left_time' => 0,
            ];
        }
        success($data);
    }

    /**
     * description:H单说明
     * @author douhao
     * @date 2018/8/10
     */
    public function aboutH(Request $request)
    {
//        $user_id = $request->input('user_id', 0);
//        if ($user_id == 0) {
//            return error('00000', '参数不全');
//        }
        //查出最近一单的时间
        $content = DB::table('trading_hall_explain')->where('type', 2)->pluck('content')->first();
        $data = [
            'content' => $content
        ];
        success($data);
    }

    /**
     * description:收益记录
     * @author douhao
     * @date 2018/8/10
     */
    public function earningsLog(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $limit = 20;
        $offset = $limit * $page;

        $where = [
            'user_id' => $user_id,
            'bb_status' => 3,
        ];
        //总收益
        $total_money = DB::table('mq_buy_back')->where($where)->sum('cash_money_bb');
        $cash_money = DB::table('mq_buy_back')->where($where)->sum('cash_money');
        $total_money = number_format(($total_money - $cash_money) * 0.8, 2, '.', '');
        $res = DB::table('mq_buy_back')->select('bb_id', 'cash_money_bb as earn_money', 'cash_money', 'expire_at')->where($where)->orderBy('bb_id', 'desc')->offset($offset)->limit($limit)->get();

        foreach ($res as $k => $v) {
            $v->earn_money = number_format(($v->earn_money - $v->cash_money) * 0.8, 2, '.', '');
            $v->cash_money = number_format($v->cash_money, 2, '.', '');
            $v->expire_at = date('Y/m/d H:i', $v->expire_at);
        }
        $data['total_money'] = $total_money;
        $data['earn_logs'] = $res;
        success($data);
    }

    /**
     * description:H单商品列表
     * @author douhao
     * @date 2018/8/10
     */
    public function goodsList(Request $request)
    {
//        $user_id = $request->input('user_id', 0);
//        if ($user_id == 0) {
//            return error('00000', '参数不全');
//        }
        //查出最近一单的时间
        $where = [
            ['p_delete', '=', 1],
            ['p_type', '=', 4],
            ['p_putaway', '=', 1]
        ];
        $goodsList = DB::table('product')->select('p_id as goods_id', 'p_title as title', 'p_list_pic as img', 'p_m_score as cash_money', 'p_consume_score as consume_money')->where($where)->orderBy('p_sort', 'ASC')->limit(20)->get();
        foreach ($goodsList as $k => $v) {
            $v->img = IMAGE_DOMAIN . $v->img;
        }
        success($goodsList);
    }

    /**
     * description:H单列表
     * @author douhao
     * @date 2018/8/10
     */
    public function hList(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $limit = 20;
        $offset = $limit * $page;


        $where = [
            'user_id' => $user_id,
            'bb_status' => 1,
        ];
        //总收益
        $total_money = DB::table('mq_buy_back')->where($where)->sum('cash_money_bb');
        $cash_money = DB::table('mq_buy_back')->where($where)->sum('cash_money');
        $total_money = number_format(($total_money - $cash_money) * 0.8, 2, '.', '');
        $res = DB::table('mq_buy_back')->select('bb_id', 'cash_money_bb as earn_money', 'cash_money', 'expire_at', 'is_stop')->where($where)->orderBy('bb_id', 'desc')->offset($offset)->limit($limit)->get();
        $data['total_money'] = $total_money;
        $data['earn_logs'] = $res;
        foreach ($res as $k => $v) {
            $v->earn_money = number_format(($v->earn_money - $v->cash_money) * 0.8, 2, '.', '');
            $v->cash_money = number_format($v->cash_money, 2, '.', '');
            $v->expire_at = $v->expire_at - time();
        }
        success($data);
    }

    /**
     * description:找出所有上级
     * @author douhao
     * @date 2018/8/24
     */
    private function get_up($user_id, &$str = '')
    {
        $user_info = DB::table('mq_users_extra')->select('invite_user_id', 'user_id')->where('user_id', '=', $user_id)->first();
        if ($user_info->invite_user_id != 0) {
            $str .= $user_info->invite_user_id . ',';
            $this->get_up($user_info->invite_user_id, $str);
        }
        return $str;
    }
}
