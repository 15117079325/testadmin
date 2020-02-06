<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SeckillController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }


    /**
     * @param Request $request
     * 获取秒杀商品
     */
    public function get()
    {
        $distriButions = DB::table('master_config')->get()->toArray();
        $distriBution = array_column($distriButions, null, 'code');
        $seckill_start = strtotime($distriBution['seckill_start_time']->value);
        $seckill_end = strtotime($distriBution['seckill_end_time']->value);
        $custom_time = strtotime(date("H:s:i"));
        $whereand = [];
        if ($custom_time < $seckill_end){
            $whereand['gs.start_time'] = 0;
        }
            print_r($seckill_end);
        die;
        $where = [];
        $where['gs.status'] = 0;
        $goods_data = DB::table("goods_seckill as gs")->selectRaw("xm_gs.id,xm_gs.goods_id,xm_p.p_title,concat('" . IMAGE_DOMAIN . "',xm_p.p_list_pic) as p_list_pic,xm_gs.start_time")->leftJoin("product as p", function ($join) {
            $join->on("gs.goods_id", "=", "p_id");
        })->where($where)->get()->toArray();
        success($goods_data);
    }

    /**
     * @param Request $request
     * 秒杀下单接口
     */
    public function save()
    {
        print_r(12312);
        die;
    }


}