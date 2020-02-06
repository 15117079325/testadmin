<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    /**
     * description:加入购物车
     * @author Harcourt
     * @date 2018/7/26
     */
    public function add(Request $request)
    {
        $user_id = $request->input('user_id');
        $p_id = $request->input('p_id');
        $size_id = $request->input('size_id', 0);
        $number = $request->input('number', 1);


        if (empty($user_id) || empty($p_id) || empty($number)) {
            return error('00000', '参数不全');
        }

        $pwhere = [
            ['p_id', $p_id],
        ];
        $product = DB::table('product')->select('shop_id', 'is_size', 'p_type', 'p_putaway', 'p_delete')->where($pwhere)->first();

        if (empty($product) || $product->p_putaway != 1 || $product->p_delete != 1) {
            return error('30001', '商品不存在');
        }

        if (!in_array($product->p_type, [PRODUCT_TYPE_PINPAI])) {
            return error('30003', '非指定商品不能操作');
        }
        if ($product->is_size != 0 ) {
            if (empty($size_id)) {
                return error('00000', '参数不全');
            }
            $size = DB::table('size')->where([
                ['size_id', $size_id],
                ['p_id', $p_id]
            ])->first();
            if (empty($size)) {
                return error('30001', '商品不存在');
            }
        }


        $where = [
            ['user_id', $user_id],
            ['p_id', $p_id],
            ['size_id', $size_id]
        ];

        $cart = DB::table('carts')->where($where)->first();
        if ($cart) {

            $bol = DB::table('carts')->where('cart_id', $cart->cart_id)->increment('cart_num', $number, ['cart_gmt_update' => time()]);
            if ($bol) {
                success();
            } else {
                error('99999', '操作失败');
            }
        } else {
            $insert_data = array(
                'user_id' => $user_id,
                'p_id' => $p_id,
                'shop_id'=>$product->shop_id,
                'size_id' => $size_id,
                'cart_num'=>$number,
                'cart_gmt_create' => time(),
                'cart_gmt_update' => time()
            );

            $insert_id = DB::table('carts')->insertGetId($insert_data, 'cart_id');

            if ($insert_id) {
                success();
            } else {
                error('99999', '操作失败');
            }
        }


    }


    /**
     * description:编辑数量
     * @author Harcourt
     * @date 2018/7/30
     */
    public function edit(Request $request)
    {
        $user_id = $request->input('user_id');
        $cart_id = $request->input('cart_id',0);
        $number = $request->input('number',0);

        if (empty($user_id) || empty($cart_id)) {
            return error('00000', '参数不全');
        }

        $cart = DB::table('carts')->where('cart_id',$cart_id)->first();

        if(empty($cart) || $cart->user_id != $user_id){
            return error('99998','非法操作');
        }
        if(empty($number)){
            $aff_row = DB::table('carts')->where('cart_id',$cart_id)->delete();
        }else{
            $update_data = array(
                'cart_num'=>$number,
                'cart_gmt_update'=>time()
            );
            $aff_row = DB::table('carts')->where('cart_id',$cart_id)->update($update_data);
        }
        if($aff_row){
            return success();
        }else{
            return error('99999','操作失败');
        }
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
        $aff_row = DB::table('carts')->where('user_id',$user_id)->whereIn('cart_id',$ids)->delete();
        if($aff_row){
            return success();
        }else{
            return error('99999','操作失败');
        }

    }
    /**
     * description:购物车列表
     * @author Harcourt
     * @date 2018/7/27
     */
    public function lists(Request $request)
    {
        $user_id = $request->input('user_id', 0);

        $carts = DB::table('carts')->selectRaw('xm_carts.shop_id, IFNULL(shop_title,"")as shop_title, cart_id, p_id, size_id, cart_num,cart_id')->where('carts.user_id',$user_id)->leftJoin('shop', 'carts.shop_id', '=', 'shop.shop_id')->groupBy('carts.shop_id')->groupBy('carts.cart_id')->orderBy('carts.cart_id', 'desc')->get()->map(function ($value){return (array)$value;})->toArray();

        $shopIds = array_unique(array_column($carts, 'shop_id'));
        sort($shopIds);
        $shopNum = count($shopIds);
        $res = [];
        $unuse = [];

        for ($i = 0; $i < $shopNum; $i++) {
            $results = [];
            $products = [];
            foreach ($carts as $cart) {
                if ($shopIds[$i] == $cart['shop_id']) {
                    $results = [
                        'shop_id' => $cart['shop_id'],
                        'shop_title' => $cart['shop_title'],
                    ];
                    $product = DB::table('product')->select('p_id', 'p_title', 'is_size', 'p_delete', 'p_putaway', 'p_stock', 'p_cash', 'p_balance', 'p_list_pic')->where('p_id', $cart['p_id'])->first();
                    if ($product) {
                        $product->cart_id = $cart['cart_id'];
                        $product->size_id = $cart['size_id'];
                        $product->cart_num = $cart['cart_num'];

                        $product->p_list_pic = IMAGE_DOMAIN . $product->p_list_pic;
                        $product->size_title = '';
                        $useful = '0';
                        if ($product->p_delete != 1 || $product->p_putaway != 1) {
                            $useful = '0';
                        } else {

                            if ($product->is_size == 0 && $product->p_stock < $cart['cart_num']) {
                                $useful = '0';
                            } elseif ($product->is_size != 0 && $cart['size_id']) {
                                $size = DB::table('size')->select('size_title', 'size_cash', 'size_balance', 'size_stock', 'size_img')->where('size_id', $cart['size_id'])->first();
                                if (empty($size) || $size->size_stock < $cart['cart_num']) {
                                    $useful = '0';
                                } else {
                                    $useful = '1';
                                    $product->size_cash = $size->size_cash;
                                    $product->size_balance = $size->size_balance;
                                    if ($size->size_img) {
                                        $product->p_list_pic = IMAGE_DOMAIN . $size->size_img;
                                    }
                                    $product->size_title = $size->size_title;
                                }
                            } else {
                                $useful = '1';
                            }
                        }
                        $product->useful = $useful;
                        unset($product->p_delete);
                        unset($product->p_putaway);
                        unset($product->p_stock);
                        unset($product->is_size);
                        if($useful == '1'){
                            $products[] = $product;
                        }else{
                            $unuse[] = $product;
                        }
                    }

                }

            }
            $results['is_shop'] = '1';
            $res[$i]['shop'] = $results;
            $res[$i]['products'] = $products;
        }
        if($unuse){
            $results['is_shop'] = '0';
            $res[$shopNum] = array(
                'shop'=>$results,
                'products'=>$unuse,
            );
        }

        success($res);


    }


}
