<?php

/**
 * 每月20号奖金池发放到t积分
 */

/*
 * 查出哪些人奖金池是优惠券,并符合条件
 */

function update_gold_tp($ret,$user_id){
    $data = get_gold_num($ret);
    $db  = $GLOBALS['db'];
    $ecs  = $GLOBALS['ecs'];
    include_once (ROOT_PATH . 'includes/lib_tran.php');
    if($data['code']==1){
            $sql = " UPDATE `xm_tps` SET unlimit=unlimit+{$data['money']},gold_pool=gold_pool-{$data['money']} WHERE user_id={$data['user_id']}";
            $flag = $db->query($sql);
            $gold_money = get_user_points($data['user_id']);
           if($flag){
                $part = [
                    'user_id' => $user_id,
                    'money' => $data['money'],
                    'create_time' => time(),
                ];
                insert_flow($data['user_id'],$data['money'],'inc-2','每月20号团队奖的'.$data['percent'].'转入T积分',$gold_money['tps']['unlimit']);
                insert_flow($data['user_id'],$data['money'],'dec-4','每月20号团队奖的'.$data['percent'].'转入T积分',$gold_money['tps']['gold_pool']);
              $db->autoExecute($ecs->table('gold_to_tp_log'), $part, 'INSERT');
               return ['code'=>1, 'msg'=>'兑换申请成功'];
           }else{
               return ['code'=>2, 'msg'=>'兑换申请失败，请稍后再试'];
           }
    }else{
        return $data;
    }
}
/*
 * 查出哪些人奖金池是优惠券,并符合条件
 */
function get_gold_num($ret){
    $flag = judge_H_xinmei($ret['user_id']);
        if($flag['code']==1){
            $result = judge_people_gold($ret['user_id']);
            if($result['code']==1){
                $money = (($ret['gold_pool']*$result['msg'])/100)<$ret['gold_pool'] ? ($ret['gold_pool']*$result['msg'])/100 : $ret['gold_pool'];
                $data = [
                         'code'=>1,
                         'user_id'=>$ret['user_id'],
                        'money'=>number_format($money,2,'.',''),
                        'percent'=>$result['msg']."%",
                ];
                return $data;
            }else{
              return $result;
            }
        }else{
           return $flag;
        }
}
/*
 * 判断直推人数有多少在回购中，计算出提现比例
 */
function judge_people_gold($user_id){
   //判断是否有H单回购中
    $config = get_gold_config();
    $db  = $GLOBALS['db'];
    //判断是否直推人数是否达标
    $direct_sql = "SELECT count(1) FROM `xm_mq_users_extra`  WHERE `invite_user_id` = {$user_id}";
    $direct_ret = $db->getOne($direct_sql);
    $direct_ret = empty($direct_ret) ? 0 : $direct_ret;
    if($direct_ret <$config['people_direct_num']){
        return ['code'=>2, 'msg'=>'对不起！直推人数未达标，无法申请兑换,请继续加油哦'];
    }
    $sql = "SELECT count(DISTINCT(b.user_id)) FROM `xm_mq_buy_back` b 
            LEFT JOIN `xm_mq_users_extra` e ON b.user_id=e.user_id 
            WHERE  b.bb_status>0 AND b.bb_status<3 AND e.`invite_user_id` = {$user_id}";
    $ret = $db->getOne($sql);
    $ret = empty($ret) ? '0' : $ret;
    $arr = explode(':', $config['people_gold_1']);
    $arr2 = explode(':', $config['people_gold_2']);
    $arr3 = explode(':', $config['people_gold_3']);
    $arr4 = explode(':', $config['people_gold_3']);
    if($ret<$arr[0]){
        return ['code'=>2, 'msg'=>'对不起！直推购买H单未达标，无法申请兑换'];
    }elseif($arr[0]<=$ret && $ret<$arr2[0]){
        return ['code'=>1, 'msg'=>$arr[1]];
    }elseif($arr2[0]<=$ret && $ret<$arr3[0]){
        return ['code'=>1, 'msg'=>$arr2[1]];
    }elseif($arr3[0]<=$ret && $ret<$arr4[0]){
        return ['code'=>1, 'msg'=>$arr3[1]];
    } elseif($arr4[0]<=$ret){
        return ['code'=>1, 'msg'=>$arr4[1]];
    }else{
         return ['code'=>2, 'msg'=>'对不起！直推购买H单未达标，无法申请兑换'];
    }
}
/*
 * 判断自己是否有H单在回购中，购买新美积分是否不低于10000
 */
function judge_H_xinmei($user_id){
   //判断是否有H单回购中
    $config = get_gold_config();
    $db  = $GLOBALS['db'];
    $sql = "SELECT count(1) FROM `xm_mq_buy_back` WHERE `user_id` = {$user_id} AND bb_status>0 AND bb_status<3 ";
    $ret = $db->getOne($sql);
    $sql1 = "SELECT SUM(cash_money) FROM `xm_mq_buy_back` WHERE `user_id` = {$user_id} AND bb_status>0 AND bb_status<3 GROUP BY user_id";
    $ret1 = $db->getOne($sql1);
    if($ret && $ret1>=$config['people_xinmei_min']){
        return ['code'=>1, 'msg'=>''];;
    }else{
        return ['code'=>2, 'msg'=>"对不起!自己的账号购买H单不能低于{$config['people_xinmei_min']}，请及时购买"];
    }
}
/*
 * 获取相应的配置
 */
function get_gold_config(){
    $db  = $GLOBALS['db'];
    $sql = "SELECT code,`value` FROM `xm_master_config` WHERE `tip` = 'g'";
    $ret = $db->getAll($sql);
    $config = [];
    foreach($ret as $k=>$v){
        $config[$v['code']] = $v['value'];
    }
    return $config;
}

