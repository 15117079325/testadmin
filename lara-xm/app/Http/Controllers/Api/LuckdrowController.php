<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Events\GqchoujiangEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\EventListener\ValidateRequestListener;

class LuckdrowController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }


    /**
     * @param Request $request
     * 获取商品信息
     */
    public function storage(Request $request)
    {
        $type = $request->input('type', 1);
        $user_id = $request->input('user_id', 1);
        $userInfo = $request->input();
        $redis = app('redis.connection');
        $key = 'webstore:userinfo';
        if ($type == 1) {
            $data = [$user_id => json_encode($userInfo)];
            $redis->hmset($key, $data);
        } else {
            $getRedis = $redis->hgetall($key);
            $userInfo = json_decode($getRedis[$user_id], true);
        }
        success($userInfo);
    }

    /**
     * @param Request $request
     * 国庆节用户抽奖接口
     */
    public function guoqing(Request $request)
    {
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $endTime = $distriBution['luck_end_time']->value;
        $startTime = $distriBution['luck_begin_time']->value;
        $cut_time = time();
        if ($cut_time >= strtotime($endTime) || $cut_time < strtotime($startTime)) {
            error('抽奖还未开始');
            exit();
        }
        $user_id = $request->input('user_id', 0);

        //验证用户权限
        $userInfo = DB::table('luck_draw_user')->where(['user_id' => $user_id])->first();
        if ($userInfo->num < 1) {
            error('您没有抽奖权限了');
            exit();
        }
        //中奖率
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $rate = $distriBution['luck_rate']->value;
        $luckContainer = [];
        for ($i = 0; $i <= 100; $i++) {
            if ($i <= $rate) {
                $luckContainer[] = 1;
            } else {
                $luckContainer[] = 0;
            }
        }
        shuffle($luckContainer);
        $is_luck = $luckContainer[array_rand($luckContainer)];
        if ($is_luck == 0) {
            $goods_id = $distriBution['luck_participation']->value;
            list($insertLogArr, $luckGoodsArr) = $this->getGoodsInsert($goods_id, $user_id, 1, $distriBution['luck_participation']->value);
        } else {
            $goods_id = explode(",", $userInfo->goods_id_luck)[array_rand(explode(",", $userInfo->goods_id_luck))];
            list($insertLogArr, $luckGoodsArr) = $this->getGoodsInsert($goods_id, $user_id, 2, $distriBution['luck_participation']->value);
        }
        DB::beginTransaction();
        try {
            DB::table('luck_draw_log')->insert($insertLogArr);
            DB::table('luck_draw_user')->where('user_id', $user_id)->update(['num' => $userInfo->num - 1]);
            DB::commit();
        } catch (\Exception $e) {
            error("操作失败");
            DB::rollback();
            throw $e;
        }
        success($luckGoodsArr);
        exit();
    }

    private function getGoodsInsert($goods_id, $user_id, $type = 1, $goods_id_luck = 60)
    {
        $luckGoodsArr = DB::table('luck_goods_draw')->select('id', 'goods_img', 'goods_name', 'is_prize', 'goods_code', 'deployment_num')->where(['id' => $goods_id])->first();

        if ($type == 2) {
            if ($luckGoodsArr->deployment_num <= 0) {
                $luckGoodsArr = DB::table('luck_goods_draw')->select('id', 'goods_img', 'goods_name', 'is_prize', 'goods_code', 'goods_num')->where(['id' => $goods_id_luck])->first();
            }
        }
        if ($luckGoodsArr != $goods_id_luck) {
            DB::table('luck_goods_draw')->lock()->where('id', $luckGoodsArr->id)->update(['deployment_num' => $luckGoodsArr->deployment_num - 1]);
        }

        $checkTime = time();
        $insertLogArr = [];
        $insertLogArr['user_id'] = $user_id;
        $insertLogArr['goods_id'] = $luckGoodsArr->id;
        $insertLogArr['is_prize'] = $luckGoodsArr->is_prize;
        $insertLogArr['update_time'] = $checkTime;
        $insertLogArr['create_time'] = $checkTime;
        $insertLogArr['channel'] = 1;
        $luckGoodsArr->goods_img = IMAGE_DOMAIN . $luckGoodsArr->goods_img;
        unset($luckGoodsArr->deployment_num);
        return [$insertLogArr, $luckGoodsArr];
    }

    /**
     * @param Request $request
     * 获取商品信息
     */
    public function getgoodsList(Request $request)
    {
        $goods = DB::table('luck_goods_draw')->select('id', 'goods_img', 'goods_name', 'goods_code')->get()->toArray();

        success($goods);
    }

    /**
     * @param Request $request
     * 获取用户抽奖次数
     */
    public function index(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $where = [];
        if (empty($user_id)) {
            return error('00000', '参数不全');
        } else {
            $where['status'] = 0;
            $where['user_id'] = $user_id;
        }
        $userInfo = DB::table('luck_draw_user')->select('user_id', 'num')->where($where)->orderBy('create_time')->first();
        if (empty($userInfo)) {
            $userInfo['user_id'] = $user_id;
            $userInfo['num'] = 0;
        }
        success($userInfo);
    }

    /**
     * @param Request $request
     * 获取商品信息
     */
    public function getgoods(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $where = [];
        if (empty($user_id)) {
            return error('00000', '参数不全');
        } else {
            $where['ldl.status'] = 0;
            $where['ldl.channel'] = 1;
            $where['ldl.user_id'] = $user_id;
            $where['ldl.is_prize'] = 0;
        }
        $orderInfo = DB::table('luck_draw_log as ldl')->leftjoin('luck_goods_draw as gd', function ($join) {
            $join->on('ldl.goods_id', '=', 'gd.id')->where(['gd.status' => 0]);
        })->where($where)->orderBy('ldl.update_time')->get()->toArray();
        foreach ($orderInfo as $k => $v) {
            unset($orderInfo[$k]->id);
            unset($orderInfo[$k]->user_id);
//            unset($orderInfo[$k]->goods_id);
            unset($orderInfo[$k]->update_time);
            unset($orderInfo[$k]->create_time);
            unset($orderInfo[$k]->status);
//            unset($orderInfo[$k]->is_deliver);
            unset($orderInfo[$k]->order_no);
            unset($orderInfo[$k]->goods_num);
            unset($orderInfo[$k]->deployment_num);
            unset($orderInfo[$k]->goods_weight);
            unset($orderInfo[$k]->is_prize);
            $orderInfo[$k]->goods_img = IMAGE_DOMAIN . $orderInfo[$k]->goods_img;
        }
        success(array_values($orderInfo));
    }

    /**
     * @param Request $request
     * 抽奖功能
     */
    public function checkluck(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        //验证用户权限
        $userInfo = DB::table('luck_draw_user')->where(['user_id' => $user_id])->first();
        if ($userInfo->num < 1) {
            error('您没有抽奖权限了');
            exit();
        }

        //最大权重
        $bigWeight = 4;
        $luckdrawArr = [];
        for ($i = 1; $i <= 100; $i++) {
            if ($i <= 1) {
                $luckdrawArr[$i] = 1;
            }
            if ($i > 1 && $i <= 11) {
                $luckdrawArr[$i] = 2;
            }
            if ($i > 11 && $i <= 23) {
                $luckdrawArr[$i] = 3;
            }
            if ($i > 23) {
                $luckdrawArr[$i] = 4;
            }

        }

        shuffle($luckdrawArr);
        $roundKey = mt_rand(0, 99);
        $roundWeight = $luckdrawArr[$roundKey];
        $luckGoodsArr = DB::table('luck_goods_draw')->get()->toArray();
        if (empty($luckGoodsArr)) {
            error('暂无抽奖商品');
            exit();
        }
        $weightArr = [];
        array_map(function ($value) use (&$weightArr) {
            $weightArr[$value->goods_weight][$value->id] = $value->deployment_num;
        }, $luckGoodsArr);
        $goodsArr = [];
        array_map(function ($value) use (&$goodsArr) {
            $goodsArr[$value->id] = $value;
        }, $luckGoodsArr);
        $luckPond = [];
        $this->getLuck($weightArr, $roundWeight, $luckPond);
        if (count($luckPond) < 1) {
            $obtainKey = array_rand($weightArr[$bigWeight]);
        } else {
            $obtainKey = array_rand($luckPond);
        }
        $checkTime = time();
        $insertLogArr = [];
        $insertLogArr['user_id'] = $user_id;
        $insertLogArr['goods_id'] = $obtainKey;
        $insertLogArr['is_prize'] = $goodsArr[$obtainKey]->is_prize;
        $insertLogArr['update_time'] = $checkTime;
        $insertLogArr['create_time'] = $checkTime;
        DB::beginTransaction();
        try {
            DB::table('luck_draw_log')->insert($insertLogArr);
            DB::table('luck_draw_user')->where('user_id', $user_id)->update(['num' => $userInfo->num - 1]);
            if ($goodsArr[$obtainKey]->deployment_num <= 0) {
                DB::table('luck_goods_draw')->where('id', $obtainKey)->update(['goods_num' => $goodsArr[$obtainKey]->goods_num - 1]);
            } else {
                DB::table('luck_goods_draw')->where('id', $obtainKey)->update(['deployment_num' => $goodsArr[$obtainKey]->deployment_num - 1]);
            }
            DB::commit();
        } catch (\Exception $e) {
            error("操作失败");
            DB::rollback();
            throw $e;
        }
        $goodsArr[$obtainKey]->goods_img = IMAGE_DOMAIN . $goodsArr[$obtainKey]->goods_img;
//        unset($goodsArr[$obtainKey]->id);
        unset($goodsArr[$obtainKey]->status);
        unset($goodsArr[$obtainKey]->deployment_num);
        unset($goodsArr[$obtainKey]->goods_weight);
        unset($goodsArr[$obtainKey]->goods_num);
        unset($goodsArr[$obtainKey]->create_time);
        unset($goodsArr[$obtainKey]->update_time);
        success($goodsArr[$obtainKey]);
        exit();
    }

    /**
     * @param $weightArr
     * @param $roundWeight
     * @param $luckPond
     * @return bool
     * 递归获取奖品
     */
    public function getLuck($weightArr, $roundWeight, &$luckPond)
    {
        //最大权重
        $bigWeight = 4;
        foreach ($weightArr[$roundWeight] as $k => $v) {
            if ($roundWeight < 1 || $roundWeight == 4) {
                foreach ($weightArr[$bigWeight] as $kk => $vv) {
                    $luckPond[$kk] = $kk;
                }
                return true;
            }
            if ($v > 0) {
                $luckPond[$k] = $k;
            } else {
                foreach ($weightArr[$bigWeight] as $kk => $vv) {
                    $luckPond[$kk] = $kk;
                }
                return true;
            }
        }

        if (count($luckPond) < 1) {
            foreach ($weightArr[$bigWeight] as $kk => $vv) {
                $luckPond[$kk] = $kk;
            }
        }
        return true;

    }

    public function insertUseraddr(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $address = $request->input('address', '');
        $name = $request->input('name', '');
        $phone = $request->input('phone', 0);
        if ($user_id == 0 || empty($address) || $phone == 0 || empty($name)) {
            error("参数不全，请正确填写收货地址");
        }
        if (preg_match("/^1[34578]\d{9}$/", $phone)) {
            $updateData = [];
            $updateData['address'] = $address;
            $updateData['name'] = $name;
            $updateData['phone'] = $phone;

            $userLuck = DB::table('luck_draw_user')->where(['user_id' => $user_id])->first();

            if (empty($userLuck)) {
                $updateData['user_id'] = $user_id;
                $updateData['num'] = 0;
                $updateData['create_time'] = time();
                $updateData['update_time'] = time();
                $result = DB::table('luck_draw_user')->insert($updateData);

            } else {
                $result = DB::table('luck_draw_user')->where(['user_id' => $user_id])->update($updateData);
            }
            if ($result) {
                success($updateData, '收货地址添加成功');
            } else {
                error("收货地址添加异常");
            }

        } else {
            error("手机号码填写有误");
        }
    }

    public function checkuseraddr(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $userLuck = DB::table('luck_draw_user')->select('address', 'phone', 'name')->where('user_id', $user_id)->first();
        if (empty($userLuck->address) || empty($userLuck->phone) || empty($userLuck->name)) {
            error($userLuck, "收货地址未填写");
        } else {
            success($userLuck, "收获地址已填写");
        }
    }
}
