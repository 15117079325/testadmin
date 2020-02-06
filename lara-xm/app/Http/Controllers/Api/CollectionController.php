<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    /**
     * description:收藏/取消收藏
     * @author Harcourt
     * @date 2018/7/30
     */
    public function deal(Request $request)
    {
        $user_id = $request->input('user_id');
        $type = $request->input('type',1);//1、商品2、店铺
        $target_id = $request->input('target_id',0);

        if(empty($user_id) || !in_array($type,array(1,2)) ||empty($target_id)){
            return error('00000', '参数不全');
        }
        $where = [
            ['user_id',$user_id],
            ['clt_type',$type],
            ['target_id',$target_id]
        ];
        $collect = DB::table('collections')->where($where)->first();
        if($collect){
            $aff = DB::table('collections')->where('clt_id',$collect->clt_id)->delete();
        }else{
            $insert_data = array(
                'user_id'=>$user_id,
                'target_id'=>$target_id,
                'clt_type'=>$type,
                'clt_gmt_create'=>time()
            );
            $aff = DB::table('collections')->insertGetId($insert_data,'clt_id');
        }

        if($aff){
            success();
        }else{
            error('99999','操作失败');
        }

    }

    /**
     * description:收藏商品列表
     * @author Harcourt
     * @date 2018/7/30
     */
    public function productList(Request $request)
    {
        $user_id = $request->input('user_id');
        $page = $request->input('page',0);

        if(empty($user_id) ){
            return error('00000', '参数不全');
        }
        $where = [
            ['collections.user_id',$user_id],
            ['clt_type',1]
        ];
        $limit = 20;
        $offset = $page*$limit;

        $products = DB::table('collections')->select('clt_id','p_id','p_title','p_list_pic','p_t_score','p_consume_score','p_delete','p_putaway','p_sold_num')->where($where)->join('product','product.p_id','=','collections.target_id')->orderBy('clt_id','desc')->offset($offset)->limit($limit)->get();
        foreach ($products as $product) {
            if($product->p_delete != 1 || $product->p_putaway != 1){
                $useful = '0';
            }else{
                $useful = '1';
            }
            unset($product->p_delete);
            unset($product->p_putaway);
            $product->p_list_pic = IMAGE_DOMAIN.$product->p_list_pic;
            $product->useful = $useful;
        }
        success($products);
    }

    /**
     *description:关注店铺列表
     * @author Harcourt
     * @date 2018/7/30
     */
    public function shopList(Request $request)
    {
        $user_id = $request->input('user_id');
        $page = $request->input('page',0);

        if(empty($user_id) ){
            return error('00000', '参数不全');
        }
        $where = [
            ['collections.user_id',$user_id],
            ['clt_type',2]
        ];
        $limit = 20;
        $offset = $page*$limit;
        $shops = DB::table('collections')->select('clt_id','shop_summary','shop_title','shop_img','shop_status','shop_score','shop_id')->where($where)->join('shop','shop.shop_id','=','collections.target_id')->orderBy('clt_id','desc')->offset($offset)->limit($limit)->get();
        foreach ($shops as $shop) {
            if($shop->shop_status != 2 ){
                $useful = '0';
            }else{
                $useful = '1';
            }
            unset($shop->shop_status);
            $shop->shop_img = IMAGE_DOMAIN.$shop->shop_img;
            $shop->useful = $useful;
        }
        success($shops);
    }

    /**
     * description:删除
     * @author Harcourt
     * @date 2018/7/30
     */
    public function delete(Request $request)
    {
        $user_id = $request->input('user_id');
        $ids = $request->input('ids','');
        if(empty($user_id) || empty($ids)){
            return error('00000', '参数不全');
        }
        $ids = json_decode($ids);
        $aff_row = DB::table('collections')->where('user_id',$user_id)->whereIn('target_id',$ids)->delete();
        if($aff_row){
            return success();
        }else{
            return error('99999','操作失败');
        }

    }
}
