<?php

namespace App\Http\Controllers\Api;

use function GuzzleHttp\Psr7\str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RedPacketController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['getUserOrGroup']);
    }

    /**
     * description:发群红包
     * @author Harcourt
     * @date 2018/8/23
     */
    public function issue(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $type = $request->input('type', 0);//1、消费积分2、新美积分(余额)
        $num = $request->input('num', 0);
        $amount = $request->input('amount', 0);
        $remarks = $request->input('remarks', '恭喜发财,大吉大利!');
        $pass = $request->input('pass');
        if (empty($user_id) || empty($gc_id) || !in_array($type, [1, 2]) || empty($num) || empty($amount) || empty($pass)) {
            return error('00000', '参数不全');
        }
        if ($num * 0.01 > $amount) {
            return error('99998', '非法操作');
        }
        $gc_uid = DB::table('group_chat')->where('gc_id',$gc_id)->pluck('gc_uid')->first();
        if(empty($gc_uid)) {
            return error('99998', '非法操作');
        }
        $group_uids = json_decode($gc_uid,true);
        if(!in_array($user_id,$group_uids)){
            return error('99998', '非法操作');
        }
        $payword = DB::table('mq_users_extra')->where('user_id',$user_id)->pluck('pay_password')->first();
        if(strcmp($payword,$pass) !== 0){
            return error('40005', '支付密码不正确');
        }
        $user_amount = DB::table('tps')->select('shopp','unlimit')->where('user_id', $user_id)->first();
        if(empty($user_amount)){
            return error('99998', '非法操作');
        }

        if($type == 1){
            $leftAmount = $user_amount->shopp - $amount;
        }else{
            $leftAmount = $user_amount->unlimit - $amount;
        }

        if ($leftAmount < 0) {
            return error('40014', '余额不足');
        }
        $packets = set_red_packet($amount, $num);
        if(count($packets) != $num){
            return error('99998', '非法操作');
        }


        $packet_data = [
            'user_id' => $user_id,
            'gc_id' => $gc_id,
            'rp_type' => $type,
            'rp_money' => $amount,
            'rp_num' => $num,
            'rp_left_num' => $num,
            'rp_notes' => $remarks,
            'rp_packets' => json_encode($packets),
            'rp_gmt_create' => time()
        ];
        DB::beginTransaction();
        $rp_id = DB::table('redpackets')->insertGetId($packet_data, 'rp_id');
        if (empty($rp_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        }

        $redis_name = 'redpacket-' . $rp_id;
        for($i = 0;$i < $num;$i ++){
            Redis::lpush($redis_name,$packets[$i]);
        }

        //修改余额，添加记录

        if ($type == 1) {
            $ftype = 3;
            $notes = '消费积分发红包';
        } else {
            $ftype = 2;
            $notes = 'T积分发红包';
        }
        $flow_data = [
            'user_id' => $user_id,
            'type' => $ftype,
            'status' => 2,
            'amount' => $amount,
            'surplus' => $leftAmount,
            'notes' => $notes,
            'create_at' => time(),
            'target_id' => $rp_id,
            'target_type' => 6
        ];

        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        $aff_row = 0;
        if($type == 1){
           $aff_row = DB::update('UPDATE xm_tps SET shopp = shopp - ? WHERE user_id = ?',[$amount,$user_id]);
        }else{
            $aff_row = DB::update('UPDATE xm_tps SET unlimit = unlimit - ?  WHERE user_id = ?',[$amount,$user_id]);
        }
        if(empty($foid1) || empty($aff_row)){
            DB::rollBack();
            return error('99999', '操作失败');

        }else{
            DB::commit();
            $data = [
                'rp_id' => $rp_id
            ];
            success($data);
        }

    }
    public function issue1(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $type = $request->input('type', 0);//1、消费积分2、新美积分
        $num = $request->input('num', 0);
        $amount = $request->input('amount', 0);
        $remarks = $request->input('remarks', '恭喜发财,大吉大利!');
        $pass = $request->input('pass');

        if (empty($user_id) || empty($gc_id) || !in_array($type, [1, 2]) || empty($num) || empty($amount) || empty($pass)) {
            return error('00000', '参数不全');
        }
        if ($num * 0.01 > $amount) {
            return error('99998', '非法操作');
        }
        $gc_uid = DB::table('group_chat')->where('gc_id',$gc_id)->pluck('gc_uid')->first();
        if(empty($gc_uid)) {
            return error('99998', '非法操作');
        }
        $group_uids = json_decode($gc_uid,true);
        if(!in_array($user_id,$group_uids)){
            return error('99998', '非法操作');
        }
        $payword = DB::table('mq_users_extra')->where('user_id',$user_id)->pluck('pay_password')->first();
        if(strcmp($payword,$pass) !== 0){
            return error('40005', '支付密码不正确');
        }

        if ($type == 1) {
            $user_amount = DB::table('tps')->where('user_id', $user_id)->pluck('shopp')->first();
            if ($user_amount == null) {
                $user_amount = 0;
            }
        } else {
            $xps = DB::table('xps')->select('unlimit', 'amount')->where('user_id', $user_id)->first();
            if ($xps) {
                $user_amount = $xps->unlimit;
                $xmtotal = $xps->amount;
            } else {
                $user_amount = 0;
                $xmtotal = 0;
            }

        }
        $leftAmount = $user_amount - $amount;
        if ($leftAmount < 0) {
            return error('40014', '余额不足');
        }
        $packets = set_red_packet($amount, $num);
        if(count($packets) != $num){
            return error('99998', '非法操作');
        }


        $packet_data = [
            'user_id' => $user_id,
            'gc_id' => $gc_id,
            'rp_type' => $type,
            'rp_money' => $amount,
            'rp_num' => $num,
            'rp_left_num' => $num,
            'rp_notes' => $remarks,
            'rp_packets' => json_encode($packets),
            'rp_gmt_create' => time()
        ];
        DB::beginTransaction();
        $rp_id = DB::table('redpackets')->insertGetId($packet_data, 'rp_id');
        if (empty($rp_id)) {
            DB::rollBack();
            return error('99999', '操作失败');
        }

        $redis_name = 'redpacket-' . $rp_id;
        for($i = 0;$i < $num;$i ++){
            Redis::lpush($redis_name,$packets[$i]);
        }

        //修改余额，添加记录

        if ($type == 1) {
            $ftype = 3;
            $notes = '消费积分发红包';
        } else {
            $ftype = 1;
            $notes = 'T积分发红包';
            $leftAmount = $xmtotal - $amount;
        }
        $flow_data = [
            'user_id' => $user_id,
            'type' => $ftype,
            'status' => 2,
            'amount' => $amount,
            'surplus' => $leftAmount,
            'notes' => $notes,
            'create_at' => time(),
            'target_id' => $rp_id,
            'target_type' => 6
        ];

        $foid1 = DB::table('flow_log')->insertGetId($flow_data, 'foid');
        $aff_row = 0;
        if($type == 1){
           $aff_row = DB::update('UPDATE xm_tps SET shopp = shopp - ? WHERE user_id = ?',[$amount,$user_id]);
        }else{
            $aff_row = DB::update('UPDATE xm_xps SET amount = amount - ? ,unlimit = unlimit - ? WHERE user_id = ?',[$amount,$amount,$user_id]);
        }
        if(empty($foid1) || empty($aff_row)){
            DB::rollBack();
            return error('99999', '操作失败');

        }else{
            DB::commit();
            $data = [
                'rp_id' => $rp_id
            ];
            success($data);
        }

    }

    /**
     * description:是否抢过红包
     * @author Harcourt
     * @date 2018/8/24
     */
    public function isOpened(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $rp_id = $request->input('rp_id', 0);
        $gc_id = $request->input('gc_id', 0);

        if (empty($user_id) || empty($rp_id) || empty($gc_id)) {
            return error('00000', '参数不全');
        }
        $gc_uid = DB::table('group_chat')->where('gc_id',$gc_id)->pluck('gc_uid')->first();
        if(empty($gc_uid)) {
            return error('99998', '非法操作');
        }
        $group_uids = json_decode($gc_uid,true);
        if(!in_array($user_id,$group_uids)){
            return error('99998', '非法操作');
        }

        $packet = DB::table('redpackets')->where('rp_id', $rp_id)->first();
        if (empty($packet) || $packet->gc_id != $gc_id) {
            return error('99998', '非法操作');
        }

        $master_user = DB::table('users')->select('nickname','headimg')->where('user_id',$packet->user_id)->first();
        if(empty($master_user)){
            return error('99998', '非法操作');
        }

        $master_user->headimg = strpos_domain($master_user->headimg);

        $hasLeft = '0';
        if($packet->rp_status != 3 && $packet->rp_left_num){
            $hasLeft = '1';
        }

        $where = [
            ['user_id', $user_id],
            ['rp_id', $rp_id]
        ];
        $detail = DB::table('redpacket_detail')->where($where)->first();
        if ($detail) {
            $isOpen = '1';
        } else {
            $isOpen = '0';
        }
        $data = [
            'isOpen' => $isOpen,
            'hasLeft' => $hasLeft,
            'nickname'=>$master_user->nickname,
            'headimg'=>$master_user->headimg,
            'remarks'=>$packet->rp_notes
        ];
        success($data);
    }

    /**
     * description:抢红包
     * @author Harcourt
     * @date 2018/8/24
     */
    public function open(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $rp_id = $request->input('rp_id', 0);
        $gc_id = $request->input('gc_id', 0);

        if (empty($user_id) || empty($rp_id) || empty($gc_id)) {
            return error('00000', '参数不全');
        }
        $user_amount = DB::table('tps')->select('shopp','unlimit')->where('user_id',$user_id)->first();
        if(empty($user_amount)){
            return error('99998', '非法操作');
        }

        $gc_uid = DB::table('group_chat')->where('gc_id',$gc_id)->pluck('gc_uid')->first();
        if(empty($gc_uid)) {
            return error('99998', '非法操作');
        }

        $group_uids = json_decode($gc_uid,true);
        if(!in_array($user_id,$group_uids)){
            return error('99998', '非法操作');
        }

        $packet = DB::table('redpackets')->where('rp_id', $rp_id)->first();
        if (empty($packet) || $packet->gc_id != $gc_id) {
            return error('99998', '非法操作');
        }
        if(empty($packet) || $packet->rp_status == 3){
            return error('50005','红包已过期');

        }

        $redis_name_open = 'openRedpacket-'.$user_id.'-'.$rp_id;

        if(Redis::exists($redis_name_open)){
            return error('99994','处理中...');
        }else{
            Redis::set($redis_name_open,'1');
        }

        $where = [
            ['user_id', $user_id],
            ['rp_id', $rp_id]
        ];
        $detail = DB::table('redpacket_detail')->where($where)->first();
        if ($detail) {
            //已经抢过该红包了
            return error('50003', '已经抢过该红包了');
        }

        $redis_name = 'redpacket-' . $rp_id;
        $money = Redis::lpop($redis_name);
        if(empty($money)){
            //已经被抢完了
            Redis::del($redis_name_open);
            return error('50004', '已经被抢完了');
        }

        $now = time();


        //塞进红包
        $detail_data = array(
            'user_id' => $user_id,
            'rd_amount' => $money,
            'rp_id' => $rp_id,
            'rd_gmt_create' => $now
        );
        //红包剩余个数
        $left_num = $packet->rp_left_num - 1;


        DB::beginTransaction();

        $rd_id = DB::table('redpacket_detail')->insertGetId($detail_data,'rd_id');

        if (empty($rd_id)) {
            DB::rollBack();
            Redis::rpush($redis_name,$money);
            Redis::del($redis_name_open);

            return error('99999','操作失败');

        }
        if ($left_num == 0) {
            DB::table('redpackets')->where('rp_id',$rp_id)->decrement('rp_left_num',1,['rp_status' => '2']);
        } else {
            DB::table('redpackets')->where('rp_id',$rp_id)->decrement('rp_left_num');
        }



        if($packet->rp_type == 1){
            //消费积分
            $surplus = $user_amount->shopp + $money;
            $ftype = 3;
            $notes = '抢到红包'.$money.'消费积分';
            DB::update('UPDATE xm_tps SET shopp = shopp + ? WHERE user_id = ?',[$money,$user_id]);
        }elseif ($packet->rp_type == 2){
            //T积分
            $surplus = $user_amount->unlimit + $money;
            $ftype = 2;
            $notes = '抢到红包'.$money.'T积分';
            DB::update('UPDATE xm_tps SET unlimit = unlimit + ? WHERE user_id = ?',[$money,$user_id]);

        }
        $flow_data = [
            'user_id' => $user_id,
            'type' => $ftype,
            'status' => 1,
            'amount' => $money,
            'surplus' => $surplus,
            'notes' => $notes,
            'create_at' => $now,
            'target_id' => $rd_id,
            'target_type' => 7
        ];
        $foid = DB::table('flow_log')->insertGetId($flow_data,'foid');

        if(empty($foid)){
            DB::rollBack();
            Redis::rpush($redis_name,$money);
            Redis::del($redis_name_open);

            return error('99999','操作失败');
        }

        DB::commit();


        if(empty(Redis::lLen($redis_name))){
            //红包被抢完了,删掉redis，key
            Redis::del($redis_name);

            DB::update('UPDATE xm_redpacket_detail SET is_max = 1 WHERE rp_id = ? ORDER BY  rd_amount DESC limit 1',[$rp_id]);
        }
        Redis::del($redis_name_open);
        $data = ['money' => (string)round($money,2)];

        success($data);

    }

    /**
     * description:红包详情
     * @author Harcourt
     * @date 2018/8/24
     */
    public function detail(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $rp_id = $request->input('rp_id', 0);
        if (empty($user_id) || empty($rp_id)) {
            return error('00000', '参数不全');
        }
        $packet = DB::table('redpackets')->select('redpackets.user_id as master_user_id','users.nickname as master_nickname','users.headimg as master_headimg','rp_id','rp_type','rp_notes','rp_money','rp_num','rp_status')->join('users','users.user_id','=','redpackets.user_id')->where('rp_id', $rp_id)->first();
        if (empty($packet)) {
            return error('99998', '非法操作');
        }
        $packet->master_headimg = strpos_domain($packet->master_headimg);

        $lists = DB::table('redpacket_detail')->select('redpacket_detail.user_id','users.headimg','users.nickname','redpacket_detail.rd_amount','redpacket_detail.is_max','redpacket_detail.rd_gmt_create')->join('users','users.user_id','=','redpacket_detail.user_id')->where('rp_id',$rp_id)->get();
        $takenNum = count($lists);
        $packet->takenNum = $takenNum;
        $takenMoney = '0';
        foreach ($lists as $list) {
            $list->headimg =  strpos_domain($list->headimg);
            $list->rd_gmt_create = getDateFormate($list->rd_gmt_create);
            $takenMoney += $list->rd_amount;

        }
        $packet->takenMoney = (string)round($takenMoney,2);
        $packet->lists = $lists;
        success($packet);
    }
}
