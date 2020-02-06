<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use ShaoZeMing\GeTui\Facade\GeTui;

class TradeController extends Controller
{

    const TRADE_STATUS_TRADING = 1;
    const TRADE_STATUS_COMPLETE = 2;
    const TRADE_STATUS_CANCEL = 3;

    const BUY_STATUS_BUYER_UPLOAD = 0;
    const BUY_STATUS_UNSURE = 1;
    const BUY_STATUS_COMPLETE = 2;
    const BUY_STATUS_WRONG_COMMIT = 3;
    const BUY_STATUS_WRONG_SURE = 4;
    const BUY_STATUS_AUTO_CANCEL = 5;

    const REDIS_KEY_EXPIRE_TIME = 120;

    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['tradeRule', 'realTimeList', 'scoreRule', 'tradeLimit', 'tradeTip', 'saleList', 'buyList']);
    }

    public function sellerInfo(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $t_id = $request->input('t_id', 0);
        if (empty($user_id) || empty($t_id)) {
            return error('00000', '参数不全');
        }
        $info = DB::table('trade')->select('trade_id', 'user_id', 'bank_account', 'bank_name', 'ali_account', 'ali_owner', 'user_name', 'mobile')->where('trade_id', $t_id)->first();
        if ($info) {
            $info->user_name = DB::table('users')->where('user_id', $info->user_id)->pluck('nickname')->first();
            if(!$info->ali_owner || (strcasecmp($info->ali_owner,"无")== 0)){
                $info->ali_owner = DB::table('user_bankinfo')->where('user_id', $info->user_id)->pluck('owner_name')->first();
            }
        }
        success($info);
    }

    /**
     * description:兑换中心提示
     * @author Harcourt
     * @date 2018/10/8
     */
    public function tradeTip()
    {
        $num = 100;
        DB::connection()->enableQueryLog();
        $trades = DB::table('trade')->select('*', DB::raw('ABS(trade_num - ' . $num . ') as diff'))->where([
            ['trade_status', 0],
            ['user_id', '!=', 1]
        ])->orderBy('diff', 'asc')->having('diff', '<=', 300)->get();

        $log = DB::getQueryLog();

        $tip = '!输入值必须是100的整数倍，且范围在500~10000之间';
        $data['tip'] = $tip;
        success($data);
    }

    /**
     * description:积分倍增规则（激活）
     * @author Harcourt
     * @date 2018/8/29
     */
    public function tradeRule()
    {
        $scoreRule = DB::table('trading_hall_explain')->where('type', 1)->pluck('content')->first();
        success($scoreRule);
    }

    private function checkIP($user_id)
    {
        $now = time();
        $ch_where = [
            ['user_id', $user_id],
            ['belong_sys', 2]
        ];
        $ip = get_client_ip();
        $safeCheck = DB::table('ip_safecheck_log')->where($ch_where)->first();
        if (empty($safeCheck) || (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now)) {
            return false;
        }
        if (empty($safeCheck)) {
            //直接身份验证ip插入

            $check_insert_data = [
                'user_id' => $user_id,
                'belong_sys' => 2,
                'ip_address' => $ip,
                'check_time' => date('Y-m-d H:i:s', $now),
                'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)
            ];
            DB::table('ip_safecheck_log')->insertGetId($check_insert_data, 'log_id');
        } else {
            if ($safeCheck && (strcmp($ip, $safeCheck->ip_address) !== 0 || strtotime($safeCheck->expire_time) <= $now)) {
                $update_insert_data = [
                    'ip_address' => $ip,
                    'check_time' => date('Y-m-d H:i:s', $now),
                    'expire_time' => date('Y-m-d H:i:s', $now + IP_EXPIRE_TIME)
                ];
                DB::table('ip_safecheck_log')->where('log_id', $safeCheck->log_id)->update($update_insert_data);
            }
        }
        return true;
    }

    /**
     * description:出售余额
     * @author Harcourt
     * @date 2018/8/21
     */

    public function sale(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $num = $request->input('num', 0);
        $password = $request->input('password');
        if (empty($user_id) || empty($num) || empty($password)) {
            return error('00000', '参数不全');
        }
        if ($num % 100 != 0) {
            return error('99995', '出售额度100为单位');
        }
        $user = DB::table('users')->select('mobile_phone', 'user_name')->where('user_id', $user_id)->first();
        if (empty($user)) {
            return error('400', '用户不存在');
        }
        $now = time();
        $configs = DB::table('master_config')->where('tip', 'd')->get();

        return error('00000','火粉社区暂时关闭');

        if (empty($configs)) {
            return error('99997', '兑换中心已停止挂卖');
        }
        $limitTime = '';
        $totalRate = '0';
        $platformRate = '0';
        $buyerRate = '0';
        $holidays = '';
        foreach ($configs as $config) {
            if ($config->code == 'deal_open_close_time') {
                $limitTime = $config->value;
            }
            if ($config->code == 'total_service_charge') {
                $totalRate = $config->value;
            }
            if ($config->code == 'platform_service_charge') {
                $platformRate = $config->value;
            }
            if ($config->code == 'seller_service_charge') {
                $buyerRate = $config->value;
            }
            if ($config->code == 'holidays') {
                $holidays = $config->value;
            }
        }
        $nowDay = date('Y-m-d', $now);
        if ($limitTime) {
            $limitArr = explode('-', $limitTime);
        } else {
            $limitArr = [];
        }

        if (count($limitArr) == 2) {
            $bottom_limit = strtotime($nowDay . ' ' . $limitArr[0]);
            $top_limit = strtotime($nowDay . ' ' . $limitArr[1]);
        } else {
            $bottom_limit = strtotime($nowDay . ' 09:00');
            $top_limit = strtotime($nowDay . ' 17:00');
        }

        if ($bottom_limit > $now || $top_limit < $now) {
            return error('99997', '兑换中心已停止挂卖');
        }
        $isChecked = $this->checkIP($user_id);
        if (!$isChecked) {
            return error('10008', '需重新认证身份');
        }

        $no = $this->Holiday_buying($holidays);
        if($no == 0) {
            return error('99991','节假日或周末不能交易');
        }

        $user_extra = DB::table('mq_users_extra')->select('status', 'pay_password')->where('user_id', $user_id)->first();

        if (empty($user_extra->status)) {
            return error('40011', '未实名认证');
        }
        if ($user_extra->status == 2) {
            return error('10005', '实名认证信息已提交,请耐心等待审核');
        }
        if (empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (strcmp($user_extra->pay_password, md5($password)) !== 0) {
            return error('40005', '支付密码不正确');
        }


        $hasTrade = DB::table('trade')->where([
            ['user_id', $user_id],
            ['trade_status', self::TRADE_STATUS_TRADING],
            ['trade_num', '!=', 0]
        ])->first();
        if ($hasTrade) {
            return error('60004', '有未完成订单,无法进行出售');
        }

        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');

        $where = [
            ['user_id', $user_id],
            ['trade_status', '<=', self::TRADE_STATUS_TRADING],
            ['trade_gmt_create', '>=', $day_start],
            ['trade_gmt_create', '<', $day_end],
        ];

        $daily_num = DB::table('trade')->selectRaw('sum(trade_num) as daily_num')->where($where)->pluck('daily_num')->first();
        if ($daily_num == null) {
            $daily_num = '0';
        }

        $sell_limit = DB::table('mq_users_limit')->select('daily_deal_sell_max_money as daily_limit', 'balance', 'pending_balance')->join('user_account', 'user_account.user_id', '=', 'mq_users_limit.user_id')->where('mq_users_limit.user_id', $user_id)->first();
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
        if ($sell_limit->daily_limit && $num + $daily_num > $sell_limit->daily_limit) {
            return error('40019', '超出当天买卖限额');
        }

        $bank = DB::table('user_bankinfo')->select('account as bank_account', 'bank_name')->where('user_id', $user_id)->first();

        $alipay = DB::table('alipay_account')->select('ac_account as ali_account', 'ac_owner as ali_owner')->where('user_id', $user_id)->first();
        if (empty($bank) && empty($alipay)) {
            return error('40027', '请先到个人中心去绑定银行卡或者支付宝');
        }
        //用户优惠券减少的余额
        $total_num = $num;
        //总手续费金额
        $total_rate = $num * $totalRate / 100;
        //平台手续费
        $platform_rate = $num * $platformRate / 100;
        //用户额外得到的金额
        $buyer_rate = $num * $buyerRate / 100;
        //用户实际得到的金额
        $trade_num = $num - $total_rate;

        if ($sell_limit->balance < $total_num) {
            return error('40014', '余额不足');
        }

        $trade_data = [
            'user_id' => $user_id,
            'mobile' => $user->mobile_phone,
            'user_name' => $user->user_name,
            'trade_status' => self::TRADE_STATUS_TRADING,
            'trade_num' => $trade_num,
            'origin_trade_num' => $num,
            'trade_gmt_create' => $now,
            'total_rate' => $total_rate,
            'platform_rate' => $platform_rate,
            'buyer_rate' => $buyer_rate
        ];
        $trade_data = array_merge($trade_data, (array)$bank, (array)$alipay);
        DB::beginTransaction();
        $trade_id = DB::table('trade')->insertGetId($trade_data, 'trade_id');
        DB::table('user_account')->where('user_id', $user_id)->decrement('balance', $total_num);
        DB::table('user_account')->where('user_id', $user_id)->increment('pending_balance', $total_num);

        //出售记录
        $flow_data = [
            'user_id' => $user_id,
            'type' => FLOW_LOG_TYPE_BALANCE,
            'status' => 2,
            'amount' => $total_num,
            'surplus' => $sell_limit->balance - $total_num,
            'notes' => '火粉社区出售'.'--' . $total_num,
            'create_at' => $now,
            'target_type' => 3,
            'target_id' => 0
        ];
        $foid = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        if (empty($trade_id) || empty($foid)) {
            DB::rollBack();
            error('99999', '操作失败');
        } else {
            DB::commit();
            success();
        }
    }


    /**
     * description:出售列表
     * author:Harcourt
     * Date:2019/5/19
     */
    public function saleList(Request $request)
    {
        $page = $request->input('page', 0);
        $where = [
            ['trade_status', self::TRADE_STATUS_TRADING],
            ['trade_num', '>', 0]
        ];
        $lists = DB::table('trade')
            ->select('trade_id', 'user_id', 'user_name', 'mobile', 'trade_num', 'bank_account', 'bank_name', 'ali_account', 'ali_owner')
            ->where($where)
            ->orderBy('trade_id', 'desc')
            ->limit(20)
            ->offset($page * 20)
            ->get();
        foreach ($lists as $list) {
            $list->user_name = DB::table('users')->where('user_id', $list->user_id)->pluck('nickname')->first();
        }
        success($lists);
    }

    /**
     * description:求购大厅 卖家确认出售
     * author:Harcourt
     * Date:2019/5/19 下午1:04
     */

    public function confirmSale(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $tb_id = $request->input('tb_id', 0);
        $password = $request->input('password');
        //出售的金额
        $num = $request->input('num');

        if (empty($user_id) || empty($tb_id) || empty($password)) {
            return error('00000', '参数不全');
        }
        $user = DB::table('users')->select('mobile_phone', 'user_name')->where('user_id', $user_id)->first();
        if (empty($user)) {
            return error('400', '用户不存在');
        }
        $tradeBuy = DB::table('trade_buy')->where('tb_id', $tb_id)->first();
        if (empty($tradeBuy) || $tradeBuy->status != 1) {
            return error('400', '求购信息不存在');
        }
        if ($tradeBuy->user_id == $user_id) {
            return error('400', '不能操作自己的求购信息');
        }
        $buyer = DB::table('users')->select('user_id', 'mobile_phone', 'user_name')->where('user_id', $tradeBuy->user_id)->first();
        if (empty($buyer)) {
            return error('400', '求购信息不存在');
        }
        //如果出售的金额大于要购买的金额,则全部购买
        if($num > $tradeBuy->buy_num) {
            $num = $tradeBuy->buy_num;
        }

        $redis_name = 'confirmSale-'.$tb_id;
        if (Redis::exists($redis_name)) {
            $buyer_id = Redis::get($redis_name);
            if ($buyer_id != $user_id) {
                return error('99994', '求购信息不存在');
            } else {
                return error('99994', '正在处理中...');
            }
        }

        $now = time();

        return error('00000','火粉社区暂时关闭');

        $configs = DB::table('master_config')->where('tip', 'd')->get();
        if (empty($configs)) {
            return error('99997', '兑换中心已停止挂卖');
        }
        $limitTime = '';
        $totalRate = '0';
        $platformRate = '0';
        $buyerRate = '0';
        $holidays = '';
        foreach ($configs as $config) {
            if ($config->code == 'deal_open_close_time') {
                $limitTime = $config->value;
            }
            if ($config->code == 'total_service_charge') {
                $totalRate = $config->value;
            }
            if ($config->code == 'platform_service_charge') {
                $platformRate = $config->value;
            }
            if ($config->code == 'seller_service_charge') {
                $buyerRate = $config->value;
            }
            if ($config->code == 'holidays') {
                $holidays = $config->value;
            }
        }
        $nowDay = date('Y-m-d', $now);
        if ($limitTime) {
            $limitArr = explode('-', $limitTime);
        } else {
            $limitArr = [];
        }

        if (count($limitArr) == 2) {
            $bottom_limit = strtotime($nowDay . ' ' . $limitArr[0]);
            $top_limit = strtotime($nowDay . ' ' . $limitArr[1]);
        } else {
            $bottom_limit = strtotime($nowDay . ' 09:00');
            $top_limit = strtotime($nowDay . ' 17:00');
        }

        if ($bottom_limit > $now || $top_limit < $now) {
            return error('99997', '兑换中心已停止挂卖');
        }

        $isChecked = $this->checkIP($user_id);
        if (!$isChecked) {
            return error('10008', '需重新认证身份');
        }


        $user_extra = DB::table('mq_users_extra')->select('status', 'pay_password')->where('user_id', $user_id)->first();

        if (empty($user_extra->status)) {
            return error('40011', '未实名认证');
        }
        if ($user_extra->status == 2) {
            return error('10005', '实名认证信息已提交,请耐心等待审核');
        }
        if (empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (strcmp($user_extra->pay_password, md5($password)) !== 0) {
            return error('40005', '支付密码不正确');
        }

        $no = $this->Holiday_buying($holidays);
        if($no == 0) {
            return error('99991','节假日或周末不能交易');
        }
        $hasTrade = DB::table('trade')->where([
            ['user_id', $user_id],
            ['trade_status', self::TRADE_STATUS_TRADING]
        ])->first();
        if ($hasTrade) {
            return error('60004', '有未完成订单,无法进行出售');
        }

        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');

        $where = [
            ['user_id', $user_id],
            ['trade_status', '<=', self::TRADE_STATUS_TRADING],
            ['trade_gmt_create', '>=', $day_start],
            ['trade_gmt_create', '<', $day_end],
        ];

        $daily_num = DB::table('trade')->selectRaw('sum(trade_num) as daily_num')->where($where)->pluck('daily_num')->first();
        if ($daily_num == null) {
            $daily_num = '0';
        }

        $sell_limit = DB::table('mq_users_limit')->select('daily_deal_sell_max_money as daily_limit', 'balance', 'pending_balance')->join('user_account', 'user_account.user_id', '=', 'mq_users_limit.user_id')->where('mq_users_limit.user_id', $user_id)->first();
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
        if ($sell_limit->daily_limit && $num + $daily_num > $sell_limit->daily_limit) {
            return error('40019', '超出当天买卖限额');
        }

        $bank = DB::table('user_bankinfo')->select('account as bank_account', 'bank_name')->where('user_id', $user_id)->first();

        $alipay = DB::table('alipay_account')->select('ac_account as ali_account', 'ac_owner as ali_owner')->where('user_id', $user_id)->first();
        if (empty($bank) && empty($alipay)) {
            return error('40027', '请先到个人中心去绑定银行卡或者支付宝');
        }
        
        //用户实际得到的金额
        $trade_num = $num;
        //总手续费金额
        $total_rate = $num * $totalRate / 100;
        //用户额外得到的金额
        $buyer_rate = $num * $buyerRate / 100;
        //平台手续费
        $platform_rate = $total_rate-$buyer_rate;

        //用户优惠券减少的余额
        $total_num = $num + $total_rate;

        if ($sell_limit->balance < $total_num) {
            return error('40014', '余额不足');
        }

        $trade_data = [
            'tb_id' => $tb_id,
            'user_id' => $user_id,
            'mobile' => $user->mobile_phone,
            'user_name' => $user->user_name,
            'trade_status' => self::TRADE_STATUS_TRADING,
            'origin_trade_num' => $trade_num,
            'trade_gmt_create' => $now,
            'total_rate' => $total_rate,
            'platform_rate' => $platform_rate,
            'buyer_rate' => $buyer_rate
        ];
        $trade_data = array_merge($trade_data, (array)$bank, (array)$alipay);

        Redis::setex($redis_name, self::REDIS_KEY_EXPIRE_TIME, $user_id);
        DB::beginTransaction();
        $trade_id = DB::table('trade')->insertGetId($trade_data, 'trade_id');
        DB::table('user_account')->where('user_id', $user_id)->decrement('balance', $total_num);
        DB::table('user_account')->where('user_id', $user_id)->increment('pending_balance', $total_num);

        if($buyer->user_id == $user_id) {
            $is_status = 2;
        } else {
            $is_status = 1;
        }

        $detail_data = [
            't_id' => $trade_id,
            'seller_id' => $user_id,
            'seller_user_name' => $user->user_name,
            'seller_user_mobile' => $user->mobile_phone,
            'buyer_id' => $buyer->user_id,
            'buyer_user_name' => $buyer->user_name,
            'buyer_user_mobile' => $buyer->mobile_phone,
            'td_num' => $num,
            'td_platform_num' => $platform_rate,
            'td_buy_num' => $buyer_rate,
            'td_status' => self::BUY_STATUS_BUYER_UPLOAD,
            'create_at' => $now,
            'is_status' => $is_status,
        ];
        $td_id = DB::table('trade_detail')->insertGetId($detail_data, 'td_id');

        $tel = substr_replace($buyer->user_name, '****', 3,4);

        //出售记录
        $flow_data = [
            'user_id' => $user_id,
            'type' => FLOW_LOG_TYPE_BALANCE,
            'status' => 2,
            'amount' => $total_num,
            'surplus' => $sell_limit->balance - $total_num,
            'notes' => '出售给' . $tel . '--' . $num.'【先扣金额】',
            'create_at' => $now,
            'target_type' => 3,
            'target_id' => $td_id
        ];

        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        //如果出售的是部分金额
        if($tradeBuy->buy_num > $num) {
            DB::table('trade_buy')->where('tb_id',$tb_id)->decrement('buy_num',$num);
        } else {
            DB::table('trade_buy')->where('tb_id', $tb_id)->update(['status' => 2]);
        }

        if (empty($trade_id) || empty($td_id)) {
            DB::rollBack();
            error('99999', '操作失败');
        } else {
            DB::commit();
            success();
            //通知用户
            $this->send_message($tradeBuy->user_id,$td_id);
        }
        Redis::del($redis_name);
    }

    /**
     * description:买家的求购，上传凭证
     * author:Harcourt
     * Date:2019/5/19
     */
    public function surePay(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $td_id = $request->input('td_id', 0);
        $voucher = $request->input('voucher', '');
        if (empty($user_id) || empty($td_id) || empty($voucher)) {
            return error('00000', '参数不全');
        }
        $tradeDetail = DB::table('trade_detail')->where('td_id', $td_id)->first();
        if (empty($tradeDetail) || $tradeDetail->buyer_id != $user_id) {
            return error('400', '求购不存在');
        }
        if ($tradeDetail->td_status != self::BUY_STATUS_BUYER_UPLOAD) {
            return error('400', '已上传凭证');
        }
        DB::table('trade_detail')->where('td_id', $td_id)->update([
            'td_status' => self::BUY_STATUS_UNSURE,
            'td_voucher' => $voucher,
            'commit_at' => time()
        ]);
        //通知用户
        $this->send_message($tradeDetail->seller_id,$td_id);
        success();
    }


    private function getTradesByNumber($num, $page = 0, $searchUserId = 0)
    {
        $where = [
            ['trade_status', self::TRADE_STATUS_TRADING],
            ['trade_num', '>=', 100]
        ];
        if ($searchUserId) {
            $where[] = ['user_id', $searchUserId];
            $trades = DB::table('trade')->selectRaw('trade_id,user_id,user_name,mobile,trade_num,bank_account,bank_name,ali_account,ali_owner')
                ->where($where)
                ->orderByRaw('trade_id asc')
                ->limit(20)
                ->offset(20 * $page)
                ->get();
        } else {
            $trades = DB::table('trade')->selectRaw('trade_id,user_id,user_name,mobile,trade_num,bank_account,bank_name,ali_account,ali_owner')
                ->where($where)
                ->havingRaw('ABS(trade_num - ?) <= 300', [$num])
                ->orderByRaw('ABS(trade_num - ?) asc', [$num])
                ->limit(20)
                ->offset(20 * $page)
                ->get();
        }
        foreach ($trades as $trade) {
            $trade->user_name = DB::table('users')->where('user_id', $trade->user_id)->pluck('nickname')->first();
        }
        return $trades;
    }
    private function getBuyByNumber($num, $page = 0, $searchUserId = 0)
    {
        $where = [
            ['status', 1],
            ['buy_num', '>=', 100]
        ];
        if ($searchUserId) {
            $where[] = ['user_id', $searchUserId];
            $trades = DB::table('trade_buy')->selectRaw('tb_id,user_id,buy_num,user_name,mobile')
                ->where($where)
                ->orderByRaw('tb_id asc')
                ->limit(20)
                ->offset(20 * $page)
                ->get();
        } else {
            $trades = DB::table('trade_buy')->selectRaw('tb_id,user_id,buy_num,user_name,mobile')
                ->where($where)
                ->havingRaw('ABS(buy_num - ?) <= 300', [$num])
                ->orderByRaw('ABS(buy_num - ?) asc', [$num])
                ->limit(20)
                ->offset(20 * $page)
                ->get();
        }
        foreach ($trades as $trade) {
            $trade->user_name = DB::table('users')->where('user_id', $trade->user_id)->pluck('nickname')->first();
        }
        return $trades;
    }

    /**
     * description:匹配出售 列表 redis
     * @author Harcourt
     * @date 2018/8/21
     */
    public function matchTrade(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $num = $request->input('num', 0);
        $page = $request->input('page', 0);

        if (empty($num) || empty($user_id)) {
            return error('00000', '参数不全');
        }
        $isPhone = false;
        $verification = new \Verification();
        if (!$verification->fun_phone($num)) {
            if (!is_int($num / 100)) {
                //$num > 10000 || $num < 500 ||  在500~10000之间，且
                return error('99995', '输入值为100的整数倍');
                //请按提示填写
            }
        } else {
            $isPhone = true;
        }
        $searchUserId = 0;
        if ($isPhone) {
            $searchUser = DB::table('users')->where('mobile_phone', $num)->first();
            if (empty($searchUser)) {
                return error('400', '搜索的账号不存在');
            }
            $searchUserId = $searchUser->user_id;
        }
        $trades = $this->getTradesByNumber($num, $page, $searchUserId);

        success($trades);
    }

    /**
     * description:匹配求购列表
     * author:Harcourt
     * Date:2019/5/25
     */
    public function matchBuy(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $num = $request->input('num', 0);
        $page = $request->input('page', 0);

        if (empty($num) || empty($user_id)) {
            return error('00000', '参数不全');
        }
        $isPhone = false;
        $verification = new \Verification();
        if (!$verification->fun_phone($num)) {
            if (!is_int($num / 100)) {
                //$num > 10000 || $num < 500 ||  在500~10000之间，且
                return error('99995', '输入值为100的整数倍');
                //请按提示填写
            }
        } else {
            $isPhone = true;
        }
        $searchUserId = 0;
        if ($isPhone) {
            $searchUser = DB::table('users')->where('mobile_phone', $num)->first();
            if (empty($searchUser)) {
                return error('400', '搜索的账号不存在');
            }
            $searchUserId = $searchUser->user_id;
        }
        $trades = $this->getBuyByNumber($num, $page, $searchUserId);

        success($trades);
    }

    /**
     * description: 确认购买
     * author:Harcourt
     * Date:2019/5/18
     */
    public function confirmBuy(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $trade_id = $request->input('trade_id', 0);
        $num = $request->input('num', 0);
//        $voucher = $request->input('voucher', '');
        if (empty($user_id) || empty($trade_id) || empty($num)) {
            return error('00000', '参数不全');
        }
        $user = DB::table('users')->select('user_name', 'mobile_phone')->where('user_id', $user_id)->first();
        if (empty($user)) {
            return error('99998', '非法操作');
        }

//        if (empty($voucher)) {
//            return error('00000', '参数不全,必须上传凭证');
//        }
        // if (!is_int($num / 100)) {
        //     //$num > 10000 || $num < 500 ||  在500~10000之间，且
        //     return error('99995', '输入值为100的整数倍');
        //     //请按提示填写
        // }
        $redis_name = 'confirmBuy-' . $trade_id;
        if (Redis::exists($redis_name)) {
            $buyer_id = Redis::get($redis_name);
            if ($buyer_id != $user_id) {
                return error('99994', '该出售已被他人购买');
            } else {
                return error('99994', '正在处理中...');
            }
        }
        //查询用户出售优惠券的信息
        $trade = DB::table('trade')->where('trade_id', $trade_id)->first();

        if (empty($trade) || $trade->trade_status != self::TRADE_STATUS_TRADING) {
            return error('400', '该出售信息不存在');
        }
        if ($trade->trade_num < $num) {
            return error('400', '该出售金额不足');
        }
        if ($trade->user_id == $user_id) {
            return error('400', '无法购买自己的出售');
        }

        $trade_detail = DB::table('trade_detail')->where([['buyer_id',$user_id],['td_status','!=',2],['td_status','!=',5]])->get();
        if(count($trade_detail) > 0) {
            return error('60004','有未完成订单');
        }

        $daily_limit = DB::table('mq_users_limit')->where('user_id', $user_id)->value('daily_deal_buy_max_money');

        //个人未设置
        if ($daily_limit == null || $daily_limit == '-1') {

            $group_daily_limit = DB::table('group_limit')->where('user_id', $user_id)->pluck('daily_deal_buy_max_money')->first();
            //服务中心未设置
            if ($group_daily_limit == '-1' || $group_daily_limit == null) {
                $system_daily_limit = DB::table('master_config')->where('code', 'daily_deal_buy_max_money')->pluck('value')->first();
                $daily_limit = $system_daily_limit;
            } else {
                $daily_limit = $group_daily_limit;
            }
        }

        //查询后台配置手续费
        $configs = DB::table('master_config')->where('tip', 'd')->get();

        $totalRate = '0';
        $platformRate = '0';
        $buyerRate = '0';

        foreach ($configs as $config) {
            if ($config->code == 'total_service_charge') {
                //总手续费
                $totalRate = $config->value;
            }
            if ($config->code == 'platform_service_charge') {
                //平台手续费
                $platformRate = $config->value;
            }
            if ($config->code == 'seller_service_charge') {
                //买家多得手续费
                $buyerRate = $config->value;
            }
        }

        $now = time();
        $nowDay = date('Y-m-d', $now);
        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');

        $where = [
            ['buyer_id', $user_id],
            ['create_at', '>=', $day_start],
            ['create_at', '<', $day_end],
        ];
        $daily_num = DB::table('trade_detail')
            ->selectRaw('sum(td_num) as daily_num')
            ->where($where)
            ->whereIn('td_status', [
                self::BUY_STATUS_UNSURE,
                self::BUY_STATUS_COMPLETE,
            ])
            ->pluck('daily_num')
            ->first();
        if ($daily_limit < $daily_num + $num) {
            return error('40019', '超出当天买卖限额');
        }

//        if ($trade->trade_num > $num) {
//            //计算买1个优惠券多得的优惠券 = 整体多得的优惠券 除以 总出售的金额
//            $more = $trade->buyer_rate / $trade->origin_trade_num;
//            //计算买1个优惠券平台手续费 = 整体平台手续费 除以 总出售的金额
//            $platform_num = $trade->platform_rate / $trade->origin_trade_num;
//            //算出用户应该多得到的优惠券
//            $money = $num * $more;
//            //进行四舍五入
//            $money = round($money,2);
//            //求购的数量
//            $detail_num = $num;
//            //买家多得费用
//            $buyer_num = $money;
//        } else {
//            $detail_num = $trade->trade_num;
//            //买家多得费用
//            $buyer_num = $trade->buyer_rate;
//        }

        //总手续费金额
        $total_rate = $trade->origin_trade_num * $totalRate / 100;
        //求购数量
        $detail_num = $num;


        //买家多得费用
        $buyer_num = $num * ($buyerRate / 100);
        //$platform_num = $trade->platform_rate;
        //平台手续费
//        $platform_num = $num * ($platformRate / 100);
        $platform_num = $num/($trade->origin_trade_num*(1-$totalRate/100))*$total_rate-$buyer_num;

        if($user_id == $user_id) {
            $is_status = 2;
        } else {
            $is_status = 1;
        }

        $detai_data = [
            't_id' => $trade->trade_id,
            'seller_id' => $trade->user_id,
            'seller_user_name' => $trade->user_name,
            'seller_user_mobile' => $trade->mobile,
            'buyer_id' => $user_id,
            'buyer_user_name' => $user->user_name,
            'buyer_user_mobile' => $user->mobile_phone,
            'td_num' => $detail_num,
            'td_platform_num' => $platform_num,
            'td_buy_num' => $buyer_num,
            'td_status' => self::BUY_STATUS_BUYER_UPLOAD,
            'create_at' => $now,
            'is_status' => $is_status,
//            'td_voucher' => $voucher,
        ];
        Redis::setex($redis_name, self::REDIS_KEY_EXPIRE_TIME, $user_id);
        $td_id = DB::table('trade_detail')->insertGetId($detai_data, 'td_id');

        if (empty($td_id)) {
            DB::rollBack();
            Redis::del($redis_name);
            return error('99999', '操作失败');
        }
        DB::table('trade')->where('trade_id', $trade->trade_id)->decrement('trade_num', $detail_num);
        DB::commit();
        //发送通知
        $this->send_message($trade->user_id,$td_id);
        Redis::del($redis_name);
        success();
    }

    /**
     * description:求购
     * author:Harcourt
     * Date:2019/5/19
     */
    public function buy(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $num = $request->input('num', 0);
        if (empty($user_id)  || empty($num)) {
            return error('00000', '参数不全');
        }
        if (!is_int($num / 100)) {//$num > 10000 || $num < 500 ||     在500~10000之间，且
            return error('99995', '输入值为100的整数倍');
            //请按提示填写
        }
        $user = DB::table('users')->select('user_name','mobile_phone')->where('user_id', $user_id)->first();
        if (empty($user)) {
            return error('99998', '非法操作');
        }

        return error('00000','火粉社区暂时关闭');
        
        //查询求购状态
        $trade_buy = DB::table('trade_buy')->where([['user_id',$user_id],['status',1]])->get();

        if(count($trade_buy)) {
            return error('60004','只能同时发布一条求购的信息');
        }

        $insertData = [
            'user_id' => $user_id,
            'user_name' => $user->user_name,
            'mobile' => $user->mobile_phone,
            'buy_num' => $num,
            'status' => 1,
            'tb_gmt_create' => time()
        ];
        $id = DB::table('trade_buy')->insertGetId($insertData, 'tb_id');
        if (empty($id)) {
            error('99999', '操作失败');
        } else {
            success();
        }
    }

    /**
     * description:求购列表
     * author:Harcourt
     * Date:2019/5/19
     */
    public function buyList(Request $request)
    {
        $page = $request->input('page', 0);
        $lists = DB::table('trade_buy')
            ->select('tb_id', 'buy_num', 'user_id', 'user_name', 'mobile')
            ->where('status', 1)
            ->orderBy('tb_id', 'desc')
            ->limit(20)
            ->offset($page*20)
            ->get();
        foreach ($lists as $list) {
            $list->user_name = DB::table('users')->where('user_id', $list->user_id)->pluck('nickname')->first();
        }
        success($lists);
    }

    /**
     * description:确认完成交易 扣除冻结部分 给买家添加余额
     * @author Harcourt
     * @date 2018/8/28
     */
    public function confirmTrade(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $td_id = $request->input('td_id', 0);
        if (empty($user_id) || empty($td_id)) {
            return error('00000', '参数不全');
        }

        $redis_name = 'confirmTrade-' . $td_id;
        if (Redis::exists($redis_name)) {
            $seller_id = Redis::get($redis_name);
            if ($seller_id != $user_id) {
                return error('99994', '该交易不存在');
            } else {
                return error('99994', '正在处理中...');
            }
        }

        $tradeDetail = DB::table('trade_detail')->where('td_id', $td_id)->first();

        if (empty($tradeDetail) || $tradeDetail->seller_id != $user_id ) {
            return error('99998', '非法操作');
        }
        if ( ! in_array($tradeDetail->td_status, [self::BUY_STATUS_UNSURE, self::BUY_STATUS_WRONG_COMMIT])) {
            return error('400', '交易状态已发生改变');
        }
        $sellerAccount = DB::table('user_account')->where('user_id', $user_id)->first();
        $buyerAccount = DB::table('user_account')->where('user_id', $tradeDetail->buyer_id)->first();
        if (empty($sellerAccount) || empty($buyerAccount)){
            return error('99998', '非法操作');
        }
        $trade = DB::table('trade')->where('trade_id', $tradeDetail->t_id)->first();
        if (empty($trade)) {
            return error('99998', '非法操作');
        }
        $isOver = false;
        if ($trade->trade_num == 0) {
            $isOver = true;
        }


        Redis::setex($redis_name, self::REDIS_KEY_EXPIRE_TIME, $user_id);



        $now = time();
        $update_data = [
            'td_status' => self::BUY_STATUS_COMPLETE,
            'complete_at' => $now
        ];

        DB::beginTransaction();
        $aff_row = DB::table('trade_detail')->where('td_id', $td_id)->update($update_data);
        if (empty($aff_row)) {
            DB::rollBack();
            Redis::del($redis_name);
            return error('99999', '操作失败');
        }
        if ($isOver) {
            DB::table('trade')->where('trade_id', $tradeDetail->t_id)->update(['trade_status' => self::TRADE_STATUS_COMPLETE]);
            //更改交易的订单状态
            DB::table('trade_detail')->where('t_id',$tradeDetail->t_id)->update(['td_status' => self::TRADE_STATUS_COMPLETE]);
        }
        DB::table('user_account')->where('user_id', $user_id)->decrement('pending_balance', $tradeDetail->td_num + $tradeDetail->td_platform_num + $tradeDetail->td_buy_num);
        DB::table('user_account')->where('user_id', $tradeDetail->buyer_id)->increment('balance', $tradeDetail->td_num + $tradeDetail->td_buy_num);
//        DB::update('UPDATE xm_master_config SET amount = amount + ? WHERE code = ?', [$cost_num, 'xm_t_all']);
//
        $tel = substr_replace($tradeDetail->buyer_user_name, '****', 3,4);

        $flow_data = [
            'user_id' => $user_id,
            'type' => FLOW_LOG_TYPE_BALANCE,
            'status' => 2,
            'amount' =>$tradeDetail->td_num + $tradeDetail->td_buy_num + $tradeDetail->td_platform_num,
            'surplus' => $sellerAccount->balance,
            'notes' => '出售给' . $tel . '--' . $tradeDetail->td_num,
            'create_at' => $now,
            'target_type' => 3,
            'target_id' => $td_id
        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        $flow_data['user_id'] = $tradeDetail->buyer_id;
        $flow_data['status'] = 1;
        $flow_data['amount'] = $tradeDetail->td_num + $tradeDetail->td_buy_num;
        $flow_data['surplus'] = $buyerAccount->balance + $tradeDetail->td_num + $tradeDetail->td_buy_num;
        $flow_data['notes'] = '购买--' . ($tradeDetail->td_num);
        $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        if (empty($foid1) || empty($foid2)) {
            DB::rollBack();
            Redis::del($redis_name);
            error('99999', '操作失败');
        } else {
            DB::commit();
            Redis::del($redis_name);
            //发送通知
            $this->send_message($tradeDetail->buyer_id,$td_id);
            success();
        }

//
//        if ($cost_num) {
//            //出售方支付手续费
//            $flow_data['user_id'] = $user_id;
//            $flow_data['type'] = 2;
//            $flow_data['status'] = 2;
//            $flow_data['amount'] = $cost_num;
//            $flow_data['surplus'] = $userTotal_t - $trade->trade_num - $cost_num;
//            $flow_data['notes'] = '交易手续费';
//            $foid3 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
//
//            //平台获取手续费
//            $flow_data['user_id'] = 0;
//            $flow_data['type'] = 2;
//            $flow_data['status'] = 1;
//            $flow_data['amount'] = $cost_num;
//            $flow_data['surplus'] = $t_all + $cost_num;
//            $flow_data['notes'] = '交易手续费';
//            $flow_data['isall'] = 1;
//            $foid4 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
//            if (empty($foid3) || empty($foid4)) {
//                DB::rollBack();
//                Redis::del($redis_name);
//                error('99999', '操作失败');
//            }
//        }


    }


    /**
     * description:按最大值查询最佳匹配
     * @param $user_id  进行查找匹配的用户id
     * @param int $num 数额
     * @param array $res 存储查找到的订单
     * @return array
     * @author Harcourt
     * @date 2018/8/28
     */
    function getPropTrade($user_id, $num = 0, $res = [])
    {
        if ($num <= 0) {
            return $res;
        }
        $where = [
            ['trade_status', 0],
            ['user_id', '<>', $user_id],
            ['trade_num', '<=', $num]
        ];
        $trade = DB::table('trade')->where($where)->orderBy('trade_num', 'desc')->orderBy('trade_id', 'desc')->first();

        if (empty($trade)) {
            return $res;
        } else {
            DB::table('trade')->where('trade_id', $trade->trade_id)->update(['trade_status' => 1]);
            $trade->trade_status = 1;
            $res[] = (array)$trade;
            $num = $num - $trade->trade_num;
            $res = $this->getPropTrade($user_id, $num, $res);
            return $res;
        }

    }

    /**
     * description:交易实时信息
     * @author Harcourt
     * @date 2018/8/28
     */
    public function realTimeList(Request $request)
    {
        $page = $request->input('page', 0);
        $limit = 200;
        $offset = $page * $limit;
        $now = time();
        $nowDay = date('Y-m-d', $now);
        $day_start = strtotime($nowDay);
        $day_end = strtotime($nowDay . '+ 1 day');
        $rwhere = [
            ['trade_status', 3],
            ['trade_num', '>=', 500],
            ['trade_gmt_sure', '>=', $day_start],
            ['trade_gmt_sure', '<', $day_end],
        ];
        $lists = DB::table('trade')->select('mobile_phone', 'trade_num', 'trade_gmt_sure')->where($rwhere)->orderBy('trade_id', 'desc')->get();
        //->offset($offset)->limit($limit)
        if (empty($lists)) {
            return error('60003', '暂无交易信息');
        }
        foreach ($lists as $list) {
            $list->mobile_phone = substr($list->mobile_phone, 0, 3) . '****' . substr($list->mobile_phone, 7);
            $list->trade_gmt_sure = date('y/m/d H:i', $list->trade_gmt_sure);
            $list->status = '交易完成';
        }

//        $twhere = [
//            ['trade_status',3],
//            ['trade_gmt_sure','>=',$day_start],
//            ['trade_gmt_sure','<',$day_end],
//            ['trade_num','>=',500]
//        ];
//        $dayNum = DB::table('trade')->selectRaw('count( *) as dayNum')->where($twhere)->pluck('dayNum')->first();
        $dayNum = DB::table('trade')->where($rwhere)->count();
        $fakeNum = DB::table('master_config')->where('code', 'trade_volume')->value('value');
        if ($fakeNum == null) {
            $fakeNum = 0;
        }
        if ($dayNum >= 1) {
            $totalAmount = $dayNum + $fakeNum;
        } else {
            $totalAmount = $dayNum;
        }
        $data['totalAmount'] = $totalAmount;
//        $res = array_merge($lists->toArray(),$lists->toArray(),$lists->toArray(),$lists->toArray());
//        dd($res);
        $data['list'] = $lists;
//        $data['list'] = $res;
        success($data);
    }

    /**
     * description:上传转账凭证
     * @author Harcourt
     * @date 2018/8/28
     */
    public function reuploadVoucher(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $td_id = $request->input('td_id', 0);
        $voucher = $request->input('voucher');
        if (empty($user_id) || empty($td_id) || empty($voucher)) {
            return error('00000', '参数不全');
        }

        $tradeDetail = DB::table('trade_detail')->where('td_id', $td_id)->first();
        if (empty($tradeDetail) || $tradeDetail->td_status != self::BUY_STATUS_WRONG_COMMIT || $tradeDetail->buyer_id != $user_id) {
            return error('99998', '非法操作');
        }
        $toUser = DB::table('users')->select('clientid', 'device')->where('user_id', $tradeDetail->seller_id)->first();
        if (empty($toUser)) {
            return error('99998', '非法操作');
        }


        $now = time();
        $update_data = [
            'td_voucher' => $voucher,
            'td_status' => self::BUY_STATUS_UNSURE,
            'commit_at' => $now
        ];
        $aff_row = DB::table('trade_detail')->where('td_id', $td_id)->update($update_data);
        if (empty($aff_row)) {
            error('99999', '操作失败');
        } else {
            success();

            if ($toUser) {
                $title = '交易通知';

                $content = '凭证已重新上传，请到兑换中心查看';

                $mtype = '3';
                $custom_content = ['id' => $td_id, 'type' => $mtype, 'content' => $content, 'title' => $title];

                $push_data = array(
                    'user_id' => $tradeDetail->seller_id,
                    'm_type' => $mtype,
                    'o_id' => $td_id,
                    'm_title' => $title,
                    'm_read' => '1',
                    'm_content' => $content,
                    'm_gmt_create' => $now
                );
                $message_id = DB::table('message')->insertGetId($push_data, 'm_id');
                if ($message_id && $toUser->clientid) {
//                    $bol = $toUser->device=='android'?true:false;
                    $bol = false;
//                    GeTui::push($toUser->clientid, $custom_content, $bol);
                }

            }

        }
    }



    /**
     * description:记录列表
     * @author Harcourt
     * @date 2018/8/28
     */
    public function recordList(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 0);//1、出售2、求购
        $page = $request->input('page', 0);

        if (empty($user_id) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');
        }
        $limit = 20;
        $offset = $limit * $page;
        if ($type == 1) {
            $fieldName = 'seller_id';
        } else {
            $fieldName = 'buyer_id';
        }
        $now = time();
        $lists = DB::table('trade_detail')
            ->select('*')
            ->where($fieldName, $user_id)
            ->orderBy('td_id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();
        foreach ($lists as $list) {
            $list->td_voucher = strpos_domain($list->td_voucher);
            $diff = AUTO_CANCEL_TRADE - ($now - $list->create_at);
            if ($diff > 0 ) {
                $list->left_seconds = $diff;
            } else {
                $list->left_seconds = 0;
            }
            $list->seller_user_name = DB::table('users')->where('user_id', $list->seller_id)->pluck('nickname')->first();
            $list->buyer_user_name = DB::table('users')->where('user_id', $list->buyer_id)->pluck('nickname')->first();
        }
        success($lists);
    }

    /**
     * description:用户出售记录
     * author:Harcourt
     * Date:2019/5/21
     */
    public function saleRecord(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $where = [
            ['user_id', $user_id],
            ['trade_status', self::TRADE_STATUS_TRADING],
            ['trade_num', '>=', 100]
        ];
        $lists = DB::table('trade')
            ->select('trade_id', 'user_id', 'user_name', 'mobile', 'trade_num', 'bank_account', 'bank_name', 'ali_account', 'ali_owner')
            ->where($where)
            ->orderBy('trade_id', 'desc')
            ->limit(20)
            ->offset($page * 20)
            ->get();
        foreach ($lists as $list) {
            $list->user_name = DB::table('users')->where('user_id', $list->user_id)->pluck('nickname')->first();
        }
        success($lists);
    }
    /**
     * description:有误
     * @author Harcourt
     * @date 2018/8/28
     */
    public function doWrong(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $td_id = $request->input('td_id', 0);
        if (empty($user_id) || empty($td_id)) {
            return error('00000', '参数不全');
        }
        $tradeDetail = DB::table('trade_detail')->where('td_id', $td_id)->first();
        if (empty($tradeDetail) || $tradeDetail->td_status != self::BUY_STATUS_UNSURE || $tradeDetail->seller_id != $user_id) {
            return error('99998', '非法操作');
        }
        $update_data = [
            'td_status' => self::BUY_STATUS_WRONG_COMMIT,
            'wrong_at' => time()
        ];
        $aff_row = DB::table('trade_detail')->where('td_id', $td_id)->update($update_data);
        if (empty($aff_row)) {
            error('99999', '操作失败');
        } else {
            //客服将在24小时内处理,请耐心等待
            success([], '请联系买家，重新上传凭证');
        }
    }

    /**
     * description:取消交易
     * @author Harcourt
     * @date 2018/8/29
     */
    public function cancelTrade(Request $request)
    {
        //初始化购买金额
        $td_num = 0;
        //初始化多得的金额
        $buyer_num = 0;
        //初始化平台手续费
        $procedure = 0;

        $user_id = $request->input('user_id', 0);
        $trade_id = $request->input('trade_id', 0);
        if (empty($user_id) || empty($trade_id)) {
            return error('00000', '参数不全');
        }
        $trade = DB::table('trade')->where('trade_id', $trade_id)->first();
        if (empty($trade) || $trade->user_id != $user_id) {
            return error('400', '交易信息不存在');
        }
        if ($trade->trade_status != self::TRADE_STATUS_TRADING) {
            return error('400', '只有出售中的交易才能取消');
        }

        //查询是否上传凭证
        $detail1 = DB::table('trade_detail')->where([['t_id',$trade_id],['td_status','!=',2],['td_status','!=',5]])->first();
        if(isset($detail1)) {
            return error('400','正在进行交易，不能取消此操作');
        }

        $redis_name = 'cancelTrade-'.$trade_id;
        if (Redis::exists($redis_name)) {
            $seller_id = Redis::get($redis_name);
            if ($seller_id != $user_id) {
                return error('99994', '交易信息不存在');
            } else {
                return error('99994', '正在处理中...');
            }
        } else {
            Redis::setex($redis_name, self::REDIS_KEY_EXPIRE_TIME, $user_id);
        }
        DB::beginTransaction();
        //先查询用户有没有已经购买部分优惠券金额
        $detail = DB::table('trade_detail')->where([['t_id',$trade_id],['td_status',2]])->get();
        if(count($detail) > 0) {
            foreach ($detail as $v) {
                //一共成功购买多少优惠券金额
                $td_num += $v->td_num;
                //一共得到多少多得费用
                $buyer_num += $v->td_buy_num;
                //平台收取手续费
                $procedure += $v->td_platform_num;
            }
            //应该返回的金额
            $num = $trade->origin_trade_num - $td_num - $buyer_num - $procedure;
            //更新用户的交易信息
            $detail = DB::table('trade_detail')->where([['t_id',$trade_id],['td_status','!=',2]])->update(['td_status' => 5]);
        } else {
            //如果没有出售成功的订单，全部返回
            $num = $trade->origin_trade_num;
        }
        //更新交易信息的状态
        $aff_row = DB::table('trade')->where('trade_id', $trade_id)->update([
            'trade_status' => self::TRADE_STATUS_CANCEL,
            'trade_gmt_cancel' => time()
        ]);
        //总返回金额 = 剩余优惠券 + 剩余多得费用
        $money = $num;

        DB::table('user_account')->where('user_id', $user_id)->decrement('pending_balance', $money);
        DB::table('user_account')->where('user_id', $user_id)->increment('balance', $money);

        $account = DB::table('user_account')->select('balance')->where('user_id',$user_id)->first();
        //出售记录
        $flow_data = [
            'user_id' => $user_id,
            'type' => FLOW_LOG_TYPE_BALANCE,
            'status' => 1,
            'amount' => $money,
            'surplus' => $account->balance + $money,
            'notes' => '火粉社区取消出售'.'--' . $money,
            'create_at' => time(),
            'target_type' => 3,
            'target_id' => 0
        ];
        $foid = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        if (empty($aff_row)) {
            DB::rollBack();
            error('99999', '操作失败');
        } else {
            DB::commit();
            success();
        }
        Redis::del($redis_name);
    }

    /**
     * description:ip验证不通过是发送短信验证
     * author:Harcourt
     * Date:2019/5/25
     */
    public function validateMsg(Request $request)
    {
        $user_id = $request->input('user_id');
        $mobile = $request->input('mobile');
        $msg = $request->input('msg');
        if(empty($user_id) || empty($mobile) || empty($msg)){
            return error('00000', '请求参数不全');
        }
        $where = [
            ['veri_mobile',$mobile],
            ['veri_number',$msg],
            ['veri_type',5]
        ];
        $verify = DB::table('verify_num')->where($where)->first();
        $now = time();
        if(empty($verify) || $verify->veri_gmt_expire <= $now){
//            return error('20001', '验证码或者手机号不正确');
        }
        $ch_where = [
            ['user_id',$user_id],
            ['belong_sys',2]
        ];
        $ip = get_client_ip();
        $safeCheck = DB::table('ip_safecheck_log')->where($ch_where)->first();

        if(empty($safeCheck)){
            //直接身份验证ip插入

            $check_insert_data = [
                'user_id'=>$user_id,
                'belong_sys'=>2,
                'ip_address'=>$ip,
                'check_time'=>date('Y-m-d H:i:s',$now) ,
                'expire_time'=>date('Y-m-d H:i:s',$now + IP_EXPIRE_TIME)

            ];
            DB::table('ip_safecheck_log')->insertGetId($check_insert_data,'log_id');
        }else{
            $update_insert_data = [
                'ip_address'=>$ip,
                'check_time'=>date('Y-m-d H:i:s',$now) ,
                'expire_time'=>date('Y-m-d H:i:s',$now + IP_EXPIRE_TIME)
            ];
            DB::table('ip_safecheck_log')->where('log_id',$safeCheck->log_id)->update($update_insert_data);

        }
        success();
    }


    /**
     * description:交易允许时间
     * @author Harcourt
     * @date 2018/8/29
     */
    public function tradeLimit()
    {
        $limitTime = DB::table('master_config')->where('code', 'deal_open_close_time')->pluck('value')->first();
        $timeArr = explode('-', $limitTime);
        if (count($timeArr) != 2) {
            $limitTime = TRADE_LIMIT_TIME;
        }
        success($limitTime);
    }

    /**
     * description:积分倍增规则（激活）
     * @author Harcourt
     * @date 2018/8/29
     */
    public function scoreRule()
    {
        $scoreRule = DB::table('trading_hall_explain')->where('type', 3)->pluck('content')->first();
        success($scoreRule);
    }


    /**
     * description:积分倍增
     * @author Harcourt
     * @date 2018/8/17
     */
    public function activate(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $account = $request->input('account');
        $m_score = $request->input('m_score');
        $consume_score = $request->input('consume_score');
        $password = $request->input('password');
        if (empty($user_id) || empty($account) || empty($m_score) || empty($consume_score) || empty($password)) {
            return error('00000', '参数不全');
        }

        $total_score = $m_score + $consume_score;
        if ($total_score % 100 != 0) {
            return error('99995', '消费激活总数量必须为100的整数倍');
        }

        $config = DB::table('master_config')->where('code', 'xm_trade_switch')->first();

        if ($config && $config->value == 1) {
            return error('99997', '暂时关闭该功能');
        }

        $masterConfigs = DB::table('master_config')->where('tip', 'c')->get();

        if (empty($masterConfigs)) {
            return error('99998', '非法操作');
        }

        $rate = $m_score / $consume_score;
        $min = '0';
        $max = '0';
        $activateRate = '0';
        $totalRate = '0';
        $firstRate = '0';
        $leftRate = '0';
        $couponRate = '0';
        foreach ($masterConfigs as $masterConfig) {
            if ($masterConfig->code == 'precharge_min') {
                $min = $masterConfig->value;
            }
            if ($masterConfig->code == 'precharge_max') {
                $max = $masterConfig->value;
            }
            if ($masterConfig->code == 'precharge_propo') {
                $aRate = explode(':', $masterConfig->value);
                if (count($aRate) == 2) {
                    $activateRate = $aRate[0] / $aRate[1];
                }
            }
            if ($masterConfig->code == 'surplus_propo') {
                $aRate = explode(':', $masterConfig->value);
                if (count($aRate)) {
                    $firstRate = $aRate[0];
                    $totalRate = array_sum($aRate);
                    $leftRate = $totalRate - $firstRate;
                }
            }
            if ($masterConfig->code == 'coupon_propo') {
                $aRate = explode(':', $masterConfig->value);
                if (count($aRate) == 2) {
                    $couponRate = $aRate[1];
                }
            }

        }
        if ($total_score < $min || $total_score > $max || $rate != $activateRate) {
            return error('99995', '消费激活总数量范围' . $min . '~' . $max);
        }


        $user_extra = DB::table('mq_users_extra')->select('user_status', 'user_cx_rank', 'invite_user_id', 'new_status', 'pay_password', 'xps.unlimit as useable_m_score', 'tps.shopp as useable_consume_score')->join('xps', 'xps.user_id', '=', 'mq_users_extra.user_id')->join('tps', 'tps.user_id', '=', 'mq_users_extra.user_id')->where('mq_users_extra.user_id', $user_id)->first();

        if (empty($user_extra)) {
            return error('99998', '非法操作');
        }
        $userName = DB::table('users')->where('user_id', $user_id)->pluck('user_name')->first();
        if (empty($userName)) {
            return error('99998', '非法操作');

        }
        if ($user_extra->user_cx_rank == 0) {
            return error('40015', '自己的账号未激活，无法帮别人激活');
        }
        $to_user = DB::table('users')->select('user_cx_rank', 'users.user_id', 'invite_user_id', 'team_number')->join('mq_users_extra', 'mq_users_extra.user_id', '=', 'users.user_id')->where('user_name', $account)->first();
        if (empty($to_user)) {
            return error('40016', '被激活账号不存在');
        }
        if (empty($to_user->user_cx_rank)) {

            return error('40028', '该账户必须先在APP中被账户激活');
        }
        if (empty($user_extra->pay_password)) {
            return error('40004', '请先设置支付密码');
        }
        if (strcmp(md5($password), $user_extra->pay_password) !== 0) {
            return error('40005', '支付密码不正确');
        }

        if ($user_extra->useable_m_score < $m_score || $user_extra->useable_consume_score < $consume_score) {
            return error('40014', '余额不足');
        }
        $toUser_tps = DB::table('tps')->select('coupon as left_coupon', 'shopp as useable_consume', 'surplus as wait_consume')->where('user_id', $to_user->user_id)->first();

        //xm_customs_apply
        //xm_flow_log
        //xm_tps
        //xm_xps
        $redis_name = 'tradeActivate-' . $user_id;
        if (Redis::exists($redis_name)) {
            return error('99994', '处理中...');
        } else {
            Redis::set($redis_name, '1');
        }
        $now = time();
        DB::beginTransaction();
        $customs_appay = [
            'from_user_id' => $user_id,
            'to_user_id' => $to_user->user_id,
            'xpoints' => $m_score,
            'cpoints' => $consume_score,
            'surplus' => $leftRate * $total_score,
            'surpro' => $leftRate,
            'points' => $totalRate * $total_score,
            'create_at' => $now,
            'update_at' => $now
        ];
        $customs_appay_id = DB::table('customs_apply')->insertGetId($customs_appay, 'id');
        if (empty($customs_appay_id)) {
            DB::rollBack();
            Redis::del($redis_name);

            return error('99999', '操作失败');
        }
        DB::update('UPDATE xm_xps SET amount = amount - ?,unlimit = unlimit - ?  WHERE user_id = ?', [$m_score, $m_score, $user_id]);
        DB::update('UPDATE xm_tps SET shopp = shopp - ? WHERE user_id = ?', [$consume_score, $user_id]);

        DB::update('UPDATE xm_tps SET coupon = coupon + ?,shopp = shopp + ?,surplus = surplus + ? WHERE user_id = ?', [$total_score * $couponRate, $total_score * $firstRate, $total_score * $leftRate, $to_user->user_id]);


        $flow_data = [
            'user_id' => $user_id,
            'amount' => $m_score,
            'type' => 1,
            'status' => 2,
            'surplus' => $user_extra->useable_m_score - $m_score,
            'notes' => '消费激活-' . $account,
            'create_at' => $now,
            'target_type' => 3,
            'target_id' => $customs_appay_id
        ];
        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        $flow_data['amount'] = $consume_score;
        $flow_data['type'] = 3;
        $flow_data['surplus'] = $user_extra->useable_consume_score - $consume_score;
        $flow_data['notes'] = '消费激活-' . $account;
        $foid2 = DB::table('flow_log')->insertGetId($flow_data, 'foid');

        $flow_data['status'] = 1;
        $flow_data['target_type'] = 2;
        $flow_data['user_id'] = $to_user->user_id;
        $flow_data['notes'] = '被消费激活-' . $userName;
        $flow_data['amount'] = $total_score * $firstRate;
        $flow_data['surplus'] = $toUser_tps->useable_consume + $total_score * $firstRate;
        $foid3 = DB::table('flow_log')->insertGetId($flow_data, 'foid');


        //被消费激活赠送购物券-
        $flow_data['type'] = 5;
        $flow_data['notes'] = '被消费激活赠送购物券-' . $userName;
        $flow_data['amount'] = $total_score * $couponRate;
        $flow_data['surplus'] = $toUser_tps->left_coupon + $total_score * $couponRate;
        $foid4 = DB::table('flow_log')->insertGetId($flow_data, 'foid');


        //待释放 被消费激活剩余积分-
        $flow_data['type'] = 6;
        $flow_data['notes'] = '被消费激活剩余积分-' . $userName;
        $flow_data['amount'] = $total_score * $leftRate;
        $flow_data['surplus'] = $toUser_tps->wait_consume + $total_score * $leftRate;
        $foid5 = DB::table('flow_log')->insertGetId($flow_data, 'foid');


        if (empty($foid1) || empty($foid2) || empty($foid3) || empty($foid4) || empty($foid5)) {
            DB::rollBack();
            Redis::del($redis_name);
            error('99999', '操作失败');
        }

        //1、$to_user->user_id 那层团队奖
        $bol = $this->bonusWeishang((array)$to_user, $m_score, $account, $customs_appay_id, 2);

        if ($bol) {
            DB::commit();
            Redis::del($redis_name);
            success();
        } else {
            DB::rollBack();
            Redis::del($redis_name);
            error('99999', '操作失败');
        }


    }

    /**
     * @param $user_extra2 被报单人用户信息
     * @param $cash_money 报单使用新美积分部分
     */
    function bonusWeishang($user_extra2, $cash_money, $user_name, $target_id, $target_type)
    {
        $percent_3w = 5; //3w提成
        $percent_10w = 10;//10w提成
        $percent_30w = 15;//30w提成

        $percent_rest = 0; //剩余可分派点数
        $last_rank = 0;  //上一个等级
        $current_percent = 0;
        $last_percent = 0;//上一个提成百分比
        $jicha = 0; //级差
        $percent_total = 0;//可分配总点数
        $calc_percent = 0;//实际获得计算的百分比


        $percent_total = max($percent_3w, $percent_10w, $percent_30w);
        $amount = 0;//提成金额
        $percent_rest = $percent_total;
        $now = time();
        while (true) {

            //送完则退出处理
            if ($percent_rest == 0) {
                while (true) {
                    //更新到奖金池
                    if ($user_extra2['user_cx_rank'] == 4) {
                        $amount1 = 0.01 * $cash_money;
                        $ret = DB::update(' UPDATE xm_tps SET gold_pool=gold_pool+? WHERE user_id=?', [$amount1, $user_extra2['user_id']]);

                        $gold_pool = DB::table('tps')->where('user_id', $user_extra2['user_id'])->pluck('gold_pool')->first();
                        if ($ret) {
                            $notes = $user_name . '消费激活服务商获得平级奖励' . $amount1;
                            $insert_data = [
                                'user_id' => $user_extra2['user_id'],
                                'amount' => $amount1,
                                'surplus' => $gold_pool,
                                'type' => 4,
                                'status' => 1,
                                'notes' => $notes,
                                'create_at' => $now,
                                'target_type' => $target_type,
                                'target_id' => $target_id
                            ];
                            DB::table('flow_log')->insertGetId($insert_data, 'foid');
                        }
                        break;
                    } elseif ($user_extra2['invite_user_id']) {
                        $user_extra2 = $this->getUserExtra($user_extra2['invite_user_id']);
                    } else {
                        break;
                    }


                    if (!$user_extra2) {
                        break;
                    }


                    //已达到顶级用户则退出
                    if (!$user_extra2['invite_user_id']) {
                        break;
                    }
                }
                break;
            }

            //是服务中心
            if (in_array($user_extra2['user_cx_rank'], [2, 3, 4])) {
                if ($user_extra2['user_cx_rank'] > $last_rank) {
                    if ($user_extra2['user_cx_rank'] == 2) {
                        $current_percent = $percent_3w;
                    } else if ($user_extra2['user_cx_rank'] == 3) {
                        $current_percent = $percent_10w;
                    } else if ($user_extra2['user_cx_rank'] == 4) {
                        $current_percent = $percent_30w;
                    }
                    $jicha = $current_percent - $last_percent;
                    //提成
                    $calc_percent = min($percent_rest, $jicha);
                    $amount = $cash_money * $calc_percent / 100;
                    //更新到奖金池
                    $ret = DB::update('UPDATE xm_tps SET gold_pool=gold_pool+? WHERE user_id=?', [$amount, $user_extra2['user_id']]);
                    $gold_pool = DB::table('tps')->where('user_id', $user_extra2['user_id'])->pluck('gold_pool')->first();

                    if ($ret) {
                        $notes = $user_name . '消费激活服务商获得奖励' . $amount;

                        $insert_data = [
                            'user_id' => $user_extra2['user_id'],
                            'amount' => $amount,
                            'type' => 4,
                            'surplus' => $gold_pool,
                            'status' => 1,
                            'notes' => $notes,
                            'create_at' => $now,
                            'target_type' => $target_type,
                            'target_id' => $target_id
                        ];
                        DB::table('flow_log')->insertGetId($insert_data, 'foid');
                    }
                    //剩余信息
                    $last_percent = $current_percent;
                    $last_rank = $user_extra2['user_cx_rank'];
                    $percent_rest = $percent_rest - $calc_percent;

                }

            }
            //已达到顶级永和则退出
            if (!$user_extra2['invite_user_id']) {
                break;
            }
            $user_extra2 = $this->getUserExtra($user_extra2['invite_user_id']);

            if (!$user_extra2) {
                break;
            }
        }
        return true;
    }


    function getUserExtra($user_id)
    {
        $user_extra = DB::table('mq_users_extra')->select('user_id', 'user_cx_rank', 'invite_user_id', 'team_number')->where('user_id', $user_id)->first();
        return (array)$user_extra;
    }

    /**
     * description:积分倍增记录
     * @author Harcourt
     * @date 2018/8/29
     */
    public function activateList(Request $request)
    {
        $user_id = $request->input('user_id', 0);
//        $user_id = 13911;
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $limit = 20;
        $offset = $limit * $page;
        $lists = DB::table('customs_apply')->select('xpoints', 'cpoints', 'create_at')->where('from_user_id', $user_id)->orderBy('id', 'desc')->offset($offset)->limit($limit)->get();
        foreach ($lists as $list) {
            $list->create_at = date('y/m/d H:i', $list->create_at);
        }
        success($lists);
    }

    /**
     * description:积分倍增收益
     * @author Harcourt
     * @date 2018/8/29
     */
    public function benifitList(Request $request)
    {
        $user_id = $request->input('user_id', 0);
//        $user_id = 13911;
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $limit = 20;
        $offset = $limit * $page;
        $where = [
            ['user_id', $user_id],
            ['type', 3],
            ['status', 1],
            ['notes', 'like', '%被消费激活%']

        ];
        $totalAmount = DB::table('flow_log')->selectRaw('sum(amount) as totalA')->where($where)->pluck('totalA')->first();
        $lists = DB::table('flow_log')->select('amount', 'create_at')->where($where)->orderBy('foid', 'desc')->offset($offset)->limit($limit)->get();
        foreach ($lists as $list) {
            $list->create_at = date('y/m/d H:i', $list->create_at);
        }
        $data['totalAmount'] = $totalAmount;
        $data['list'] = $lists;
        success($data);
    }

    /**
     * description:获取待释放收益
     * @author Harcourt
     * @date 2018/9/7
     */
    public function getWaitRelease(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if ($user_id) {
            $surplus = DB::table('tps')->where('user_id', $user_id)->value('surplus');
        } else {
            $surplus = '0';
        }
        $data['waitRelease'] = $surplus;
        success($data);

    }


    /**
     * description:取消购买交易
     * @author libaowei
     * @date 2019/7/31
     */
    public function CancelSale(Request $request)
    {
        //交易ID
        $tb_id = $request->tb_id;
        //用户ID
        $user_id = $request->user_id;
        //token
        $token = $request->input('token');

        if(!isset($tb_id) || !isset($user_id) || !isset($token)) {
            return error('00000','参数不全');
        }

        //初始化
        $poun_money = 0;
        if(!isset($tb_id) || !isset($user_id)) {
            return error('00000','参数不全');
        }
        //查询当前的购买信息
        $trade_buy =DB::table('trade_buy')->where('tb_id',$tb_id)->first();
        //判断信息是否存在
        if(!isset($trade_buy)) {
            return error('400','交易信息不存在');
        }
        //如果当前是交易成功
        if($trade_buy->status == 2) {
            return error('400','不能取消成功的交易');
        }


        $trade_id = DB::table('trade')->where('tb_id',$tb_id)->value('trade_id');
        //查询是否上传凭证
        $detail1 = DB::table('trade_detail')->where([['t_id',$trade_id],['td_status','!=',2],['td_status','!=',5]])->first();
        if(isset($detail1)) {
            return error('400','正在进行交易，不能取消此操作');
        }

        //查询取消之前是否有未完成的交易
        $trades = DB::table('trade')->where([['tb_id',$tb_id],['trade_status',1]])->get();

        //如果有信息
        if(count($trades)) {
            $i = 0;
            DB::beginTransaction();

            //查询有没有完成出售的手续费
            $poundages = DB::table('trade_detail')->where([['t_id',$tb_id],['td_status',2]])->get();
            if(count($poundages)) {
                foreach ($poundages as $poundage) {
                    //已经出去多少手续费
                    $sum = $poundage->td_platform_num + $poundage->td_buy_num;
                    //总和
                    $poun_money += $sum;
                }
            } else {
                $poun_money = 0;
            }

            foreach ($trades as $trade) {
                //总返回的优惠券
                $money = $trade->trade_num + $trade->total_rate - $poun_money;
                //优惠券返回
                $account = DB::table('user_account')->where('user_id',$trade->user_id)->increment('balance',$money);
                //取消上传凭证
                $trade_detail = DB::table('trade_detail')->where('t_id',$trade->trade_id)->update(['td_status'=> 5]);

                $buy = DB::table('trade_buy')->where('tb_id',$tb_id)->update(['status'=> 3]);
                $trade1 = DB::table('trade')->where('tb_id',$tb_id)->update(['trade_status' => 3]);
                $i++;

                //取消记录
                $flow_data = [
                    'user_id' => $trade->user_id,
                    'type' => FLOW_LOG_TYPE_BALANCE,
                    'status' => 1,
                    'amount' => $money,
                    'surplus' => 0,
                    'notes' => '用户取消购买'.'--' . $money,
                    'create_at' => time(),
                    'target_type' => 3,
                    'target_id' => 0
                ];
                $foid = DB::table('flow_log')->insertGetId($flow_data, 'foid');
            }

            if ($i <= 0) {
                DB::rollBack();
                error('99999', '操作失败1');
            } else {
                DB::commit();
                //减少用户的锁定的优惠券
                DB::table('user_account')->where('user_id',$trade->user_id)->decrement('pending_balance',$money);
                success();
            }
        } else {
            $buy = DB::table('trade_buy')->where('tb_id',$tb_id)->update(['status'=> 3]);

            if(empty($buy)) {
                error('99999', '操作失败2');
            } else {
                success();
            }
        }
    }


    /**
     * description:假期是否能交易
     * @author libaowei
     * @date 2019/8/16
     */
    public function Holiday_buying($holiday)
    {

        if($holiday <= 0) {
            //0是不能购买，1是可以购买
            $date = date("Ymd",time());
            $url = "http://api.goseek.cn/Tools/holiday?date=".$date;
            $res = file_get_contents($url);
            $res = json_decode($res,true);
            if($res['data'] == 1 || $res['data'] == 2 || $res['data'] == 3){
                return $no = 0;
            } else {
                return 1;
            }
        }else {
            return 1;
        }

    }

    /**
     * description:购买者取消上传凭证
     * @author libaowei
     * @date 2019/8/21
     */
    public function buy_cancel(Request $request) {
        //交易ID
        $td_id = $request->td_id;
        //用户ID
        $user_id = $request->user_id;
        //取消类型 (1、购买  2、出售)
        //$type = $request->type;
        if(!isset($td_id) || !isset($user_id)) {
            return error('00000','参数不全');
        }
        //查询当前交易信息
        $trade_detail = DB::table('trade_detail')->where([['td_id',$td_id],['td_status',0]])->orWhere('td_status',3)->first();
        DB::beginTransaction();
        if(isset($trade_detail)) {
            $type = $trade_detail->is_status;

            if($type == 1) {

                //返回的金额
                // $money = $trade_detail->td_num;

                //给求购者订单返回优惠券
                //$trade = DB::table('trade_buy')->where('tb_id',$td_id)->increment('buy_num',$money);
                $trade = 1;
                //给出售者返回优惠券 = 求购数量 + 平台收取手续费 + 买家多得费用
                $money = $trade_detail->td_num + $trade_detail->td_platform_num + $trade_detail->td_buy_num;
                //返回优惠券
                DB::table('user_account')->where('user_id',$trade_detail->seller_id)->increment('balance',$money);
                //更改交易状态
                $state = DB::table('trade_detail')->where([['td_id',$td_id],['td_status',0]])->orWhere('td_status',3)->update(['td_status' => 5]);
                //修改出售记录状态
                $trade = DB::table('trade')->where('trade_id',$trade_detail->t_id)->update(['trade_status' => 3]);
                //更改用户锁定优惠券
                DB::table('user_account')->where('user_id',$trade_detail->seller_id)->decrement('pending_balance',$money);

            } else if($type == 2) {

                //返回的金额
                $money = $trade_detail->td_num;

                //优惠券金额远路返回
                $trade = DB::table('trade')->where('trade_id',$trade_detail->t_id)->increment('trade_num',$money);

                //更改交易状态
                $state = DB::table('trade_detail')->where([['td_id',$td_id],['td_status',0]])->orWhere('td_status',3)->update(['td_status' => 5]);

            } else {
                return error('99999','非法操作');
            }

            //取消记录
            $flow_data = [
                'user_id' => $trade_detail->seller_id,
                'type' => FLOW_LOG_TYPE_BALANCE,
                'status' => 1,
                'amount' => $money,
                'surplus' => 0,
                'notes' => '用户取消购买原路返回'.'--' . $money,
                'create_at' => time(),
                'target_type' => 3,
                'target_id' => 0
            ];
            //插入日志
            $foid = DB::table('flow_log')->insertGetId($flow_data, 'foid');
            //判断是否取消成功
            if(!empty($trade || !empty($foid))) {
                DB::commit();
                success();
            } else {
                DB::rollBack();
                error('99999', '操作失败');
            }
        } else {
            return error('99998','非法操作');
        }

    }

    public function send_message($user_id,$td_id) {
        $now = time();
        //进行通知
        $toUser = DB::table('users')->select('clientid', 'device')->where('user_id',$user_id)->first();
        if (empty($toUser)) {
            return error('99998', '非法操作');
        }
        if ($toUser) {
            $title = '交易通知';

            $content = '您的火粉社区订单状态发生变化，请及时查看！';

            $mtype = '3';
            $custom_content = ['id' => $td_id, 'type' => $mtype, 'content' => $content, 'title' => $title];
            $tradeDetail = DB::table('trade_detail')->where('td_id', $td_id)->first();
            $push_data = array(
                'user_id' => $tradeDetail->seller_id,
                'm_type' => $mtype,
                'o_id' => $td_id,
                'm_title' => $title,
                'm_read' => '1',
                'm_content' => $content,
                'm_gmt_create' => $now
            );
            $message_id = DB::table('message')->insertGetId($push_data, 'm_id');
            if ($message_id && $toUser->clientid) {
//                    $bol = $toUser->device=='android'?true:false;
                $bol = false;
                GeTui::push($toUser->clientid, $custom_content, $bol);
            }

        }

    }

}
