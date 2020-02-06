<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    /**
     * description:店铺商品分类
     * @author Harcourt
     * @date 2018/7/25
     */
    public function cates(Request $request)
    {
        $shop_id = $request->input('shop_id',0);
        $cate_id = $request->input('cate_id',0);

        if(empty($shop_id)){
            return error('00000','参数不全');
        }
        $where = [
            ['shop_id',$shop_id],
            ['p_putaway',1],
            ['p_delete',1]
        ];
        if(empty($cate_id)){
            $join = 'product.cate_id';
        }else{
            $join = 'product.child_cate_id';
            $where[] = ['product.cate_id',$cate_id];
        }

        $cates = DB::table('product')->select('cate.cate_id','cate_title')->join('cate',$join,'=','cate.cate_id')->where($where)->groupBy('cate.cate_id')->orderBy('cate_sort','asc')->get();

        success($cates);
    }

    /**
     * description:店铺中所有或者分类下商品
     * @author Harcourt
     * @date 2018/7/25
     */
    public function lists(Request $request)
    {
        $shop_id = $request->input('shop_id',0);
        $cate_id = $request->input('cate_id',0);
        $user_id = $request->input('user_id',0);
        $page = $request->input('page',0);

        if(empty($shop_id)){
            return error('00000','参数不全');
        }
        $pwhere = [
            ['shop_id',$shop_id],
            ['shop_status',2]
        ];
        $shop = DB::table('shop')->selectRaw('shop_id,user_id,shop_title,concat(?,shop_img) as shop_img,shop_sold_num,shop_concern_num,shop_score',[IMAGE_DOMAIN])->where($pwhere)->first();
        if(empty($shop)){
            return error('30002','店铺不存在');
        }
        $shop_concern = '0';
        if(empty($user_id)){
            $cwhere = [
                ['user_id',$user_id],
                ['clt_type',2],
                ['target_id',$shop_id]
            ];
            $collect = DB::table('collections')->where($cwhere)->first();
            if($collect){
                $shop_concern = '1';
            }
        }
        $shop->shop_concern = $shop_concern;

        $where = [
            ['shop_id',$shop_id],
            ['p_putaway',1],
            ['p_delete',1]
        ];
        if($cate_id){
            $where[] = ['child_cate_id',$cate_id];
        }
        $limit = 20;
        $offset = $limit*$page;

        $products = DB::table('product')->selectRaw('p_id,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_consume_score,p_t_score,p_ticket_score,p_m_score,p_type',[IMAGE_DOMAIN])->where($where)->orderBy('p_sort','asc')->orderBy('p_id','desc')->offset($offset)->limit($limit)->get();
        $data['product'] = $products;
        $data['shop'] = $shop;
        success($data);

    }



}
