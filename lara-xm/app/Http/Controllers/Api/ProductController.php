<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('enableCross');
        $this->middleware('userLoginValidate')->only(['share']);
    }

    /**
     * description:分享
     * author:Harcourt
     * Date:2019/6/9
     */
    public function share(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $p_id = $request->input('p_id', 0);
        if (empty($user_id) || empty($p_id)) {
            return error('00000', '参数不全');
        }
        $invite_code = DB::table('mq_users_extra')->where('user_id', $user_id)->value('invite');
        if (empty($invite_code)) {
            return error('99998', '非法操作');
        }
        $data['url'] = WEB_BASE_URL.'?id='.$p_id.'&remobile='.$invite_code;
        success($data);
    }
    /**
     * description:商品列表
     * @author Harcourt
     * @date 2018/7/24
     */
    public function lists(Request $request)
    {
        $type = $request->input('type', 0);
        $page = $request->input('page', 0);
        $cate_id = $request->input('cate_id', 0);

        if (!in_array($type, [PRODUCT_TYPE_BAODAN, PRODUCT_TYPE_YUE, PRODUCT_TYPE_PINPAI,PRODUCT_TYPE_BAODAN_MONEY])) {
            return error('00000', '参数不全');
        }


        $where = [
            ['p_putaway', 1],
            ['p_type', $type],
            ['p_delete', 1]
        ];

        if ($type == 2) {
            if ($cate_id) {
                $where[] = ['cate_id', $cate_id];
            } else {
                $where[] = ['p_boutique', 1];
            }
        }
        $limit = 20;
        $offset = $limit * $page;

        $products = DB::table('product')->selectRaw('p_id,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price,p_type,p_describe,p_stock', [IMAGE_DOMAIN])->where($where)->orderBy('p_sort', 'desc')->offset($offset)->limit($limit)->get();

       if($type == 2 || $type == 3){
           $carousel =DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video', [IMAGE_DOMAIN])->where([['enabled',1],['position_id',$type]])->get();
       }else if($type == 1){
           //精品
           $carousel =DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video', [IMAGE_DOMAIN])->where([['enabled',1],['position_id',4]])->get();;
       }else{
           $carousel = [];
       }

//       if($type == 2){
//            $cate = DB::table('cate')->select('cate_id','cate_title')->where('parent_id',0)->orderBy('cate_sort','asc')->get()->toArray();
//            array_unshift($cate,['cate_id'=>0,'cate_title'=>'精选']);
//       }else{
//           $cate = [];
//       }

        $data['product'] = $products;
        $data['carousel'] = $carousel;
//       $data['cate'] = $cate;
//        $showsale = DB::table('master_config')->where('code','is_show_sale')->value('value');
//        if ($showsale == null){
//            $showsale = '1';
//        }
//        $data['showsale'] = $showsale;

        success($data);

    }

    /**
     * description:详情
     * @author Harcourt
     * @date 2018/7/24
     */
    public function detail(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $p_id = $request->input('p_id', 0);

        //初始化
        $data = [];
        if (empty($p_id)) {
            return error('00000', '参数不全');
        }
        $pwhere = [
            ['product.p_id', $p_id],
            ['p_delete', 1],
            ['p_putaway', 1]
        ];
        $product = DB::table('product')->select('product.p_id', 'shop_id', 'p_type', 'p_list_pic', 'p_detail_pic', 'p_title', 'p_sold_num','p_cash', 'p_balance', 'market_price', 'p_type', 'is_size','p_describe','p_stock','video_url','type_c')->where($pwhere)->first();
        //,'daily_buy_limit'
        // ->join('product_extra','product.p_id','=','product_extra.p_id')
        if (empty($product)) {
            return error('30001', '商品不存在');
        }

        if ($product->is_size) {
            if ($product->p_type == 2) {
                $min_max = DB::table('size')->selectRaw('min(size_t_score) as min_t,max(size_t_score) as max_t')->where('p_id', $product->p_id)->first();
                $res = array_merge((array)$min_max, [$product->p_t_score]);
                $min = min($res);
                $max = max($res);
                $product->p_t_score = $min . '-' . $max;


            } else if ($product->p_type == 1) {
                $min_max = DB::table('size')->selectRaw('min(size_ticket_score) as min_ticket,max(size_ticket_score) as max_ticket')->where('p_id', $product->p_id)->first();
                $res = array_merge((array)$min_max, [$product->p_ticket_score]);
                $min = min($res);
                $max = max($res);
                $product->p_ticket_score = $min . '-' . $max;

            }
        }


        $product->p_list_pic = IMAGE_DOMAIN . $product->p_list_pic;

        $pics = explode(',', $product->p_detail_pic);
        for ($i = 0; $i < count($pics); $i++) {
            $pics[$i] = IMAGE_DOMAIN . $pics[$i];
        }

        //处理视频视频
        if(!empty($product->video_url)) {
            $product->video_url = IMAGE_DOMAIN . $product->video_url;
        }

        $product->p_detail_pic = $pics;

        $is_collected = '0';
        if ($user_id) {
            $where = [
                ['target_id', $p_id],
                ['clt_type', 1],
                ['user_id', $user_id]
            ];
            $collection = DB::table('collections')->where($where)->first();
            if ($collection) {
                $is_collected = '1';

            }
        }
        $product->is_collected = $is_collected;

        if ($product->p_type == 3 && $product->shop_id) {
            $shwhere = [
                ['shop_id', $product->shop_id],
                ['shop_status', 2]
            ];
            $shop = DB::table('shop')->select('shop_id', 'shop_title', 'shop_img', 'shop_sold_num', 'shop_concern_num', 'shop_score')->where($shwhere)->first();
            $shop_concern = '0';
            if ($shop) {
                $shop->shop_img = IMAGE_DOMAIN . $shop->shop_img;
                $swhere = [
                    ['target_id', $shop->shop_id],
                    ['clt_type', 1],
                    ['user_id', $user_id]
                ];
                $shop_collect = DB::table('collections')->where($swhere)->first();
                if ($shop_collect) {
                    $shop_concern = '1';
                }

                $shop->shop_concern = $shop_concern;
            }

        } else {
            $shop = [];
        }
        $product->shop = $shop;
        $cwhere = [
            ['p_id', $p_id],
            ['cmt_status', 1]
        ];
        $comment = DB::table('comments')->selectRaw('xm_comments.user_id,cmt_content,cmt_imgs,cmt_gmt_create,cmt_score,cmt_size,nickname as user_name,headimg')->join('users', 'users.user_id', '=', 'comments.user_id')->where($cwhere)->orderBy('cmt_id', 'desc')->first();

        if ($comment) {

            if ($comment->cmt_imgs) {
                $imgs = explode(',', $comment->cmt_imgs);
                for ($i = 0; $i < count($imgs); $i++) {
                    $imgs[$i] = strpos_domain($imgs[$i]);
                }
            } else {
                $imgs = [];
            }


            $comment->cmt_imgs = $imgs;
            $comment->headimg = IMAGE_DOMAIN . $comment->headimg;
            $comment->cmt_content = base64_decode($comment->cmt_content);
            $comment->cmt_gmt_create = date('Y-m-d', $comment->cmt_gmt_create);

        } else {
            $comment = (object)[];
        }

        $product->comment = $comment;
        $showsale = DB::table('master_config')->where('code', 'is_show_sale')->value('value');
        if ($showsale == null) {
            $showsale = '1';
        }
        $product->showsale = $showsale;
        $product->slideshow = array('p_detail_pic' => $product->p_detail_pic,'video_url' => $product->video_url);

        success($product);

    }
    public function webDetail(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $p_id = $request->input('p_id', 0);
        if (empty($p_id)) {
            return error('00000', '参数不全');
        }
        $pwhere = [
            ['product.p_id', $p_id],
            ['p_delete', 1],
            ['p_putaway', 1]
        ];
        $product = DB::table('product')->select('product.p_id', 'shop_id', 'p_type', 'p_list_pic', 'p_detail_pic', 'p_title', 'p_sold_num','p_cash', 'p_balance', 'market_price', 'p_type', 'is_size', 'p_description')->where($pwhere)->first();
        //,'daily_buy_limit'
        // ->join('product_extra','product.p_id','=','product_extra.p_id')
        if (empty($product)) {
            return error('30001', '商品不存在');
        }

        if ($product->is_size) {
            if ($product->p_type == 2) {
                $min_max = DB::table('size')->selectRaw('min(size_t_score) as min_t,max(size_t_score) as max_t')->where('p_id', $product->p_id)->first();
                $res = array_merge((array)$min_max, [$product->p_t_score]);
                $min = min($res);
                $max = max($res);
                $product->p_t_score = $min . '-' . $max;


            } else if ($product->p_type == 1) {
                $min_max = DB::table('size')->selectRaw('min(size_ticket_score) as min_ticket,max(size_ticket_score) as max_ticket')->where('p_id', $product->p_id)->first();
                $res = array_merge((array)$min_max, [$product->p_ticket_score]);
                $min = min($res);
                $max = max($res);
                $product->p_ticket_score = $min . '-' . $max;

            }
        }


        $product->p_list_pic = IMAGE_DOMAIN . $product->p_list_pic;

        $pics = explode(',', $product->p_detail_pic);
        for ($i = 0; $i < count($pics); $i++) {
            $pics[$i] = IMAGE_DOMAIN . $pics[$i];
        }


        $product->p_detail_pic = $pics;

        $is_collected = '0';
        if ($user_id) {
            $where = [
                ['target_id', $p_id],
                ['clt_type', 1],
                ['user_id', $user_id]
            ];
            $collection = DB::table('collections')->where($where)->first();
            if ($collection) {
                $is_collected = '1';

            }
        }
        $product->is_collected = $is_collected;

        if ($product->p_type == 3 && $product->shop_id) {
            $shwhere = [
                ['shop_id', $product->shop_id],
                ['shop_status', 2]
            ];
            $shop = DB::table('shop')->select('shop_id', 'shop_title', 'shop_img', 'shop_sold_num', 'shop_concern_num', 'shop_score')->where($shwhere)->first();
            $shop_concern = '0';
            if ($shop) {
                $shop->shop_img = IMAGE_DOMAIN . $shop->shop_img;
                $swhere = [
                    ['target_id', $shop->shop_id],
                    ['clt_type', 1],
                    ['user_id', $user_id]
                ];
                $shop_collect = DB::table('collections')->where($swhere)->first();
                if ($shop_collect) {
                    $shop_concern = '1';
                }

                $shop->shop_concern = $shop_concern;
            }

        } else {
            $shop = [];
        }
        $product->shop = $shop;
        $cwhere = [
            ['p_id', $p_id],
            ['cmt_status', 1]
        ];
        $comment = DB::table('comments')->selectRaw('xm_comments.user_id,cmt_content,cmt_imgs,cmt_gmt_create,cmt_score,cmt_size,nickname as user_name,headimg')->join('users', 'users.user_id', '=', 'comments.user_id')->where($cwhere)->orderBy('cmt_id', 'desc')->first();

        if ($comment) {

            if ($comment->cmt_imgs) {
                $imgs = explode(',', $comment->cmt_imgs);
                for ($i = 0; $i < count($imgs); $i++) {
                    $imgs[$i] = strpos_domain($imgs[$i]);
                }
            } else {
                $imgs = [];
            }


            $comment->cmt_imgs = $imgs;
            $comment->headimg = IMAGE_DOMAIN . $comment->headimg;
            $comment->cmt_content = base64_decode($comment->cmt_content);
            $comment->cmt_gmt_create = date('Y-m-d', $comment->cmt_gmt_create);

        } else {
            $comment = (object)[];
        }

        $product->comment = $comment;
        $showsale = DB::table('master_config')->where('code', 'is_show_sale')->value('value');
        if ($showsale == null) {
            $showsale = '1';
        }
        $product->showsale = $showsale;

        success($product);

    }

    /**
     * description:商品详情网页
     * @author Harcourt
     * @date 2018/7/25
     */
    public function detailView(Request $request)
    {
        $p_id = $request->input('p_id', 0);
        $des = DB::table('product')->where('p_id', $p_id)->value('p_description');
        return view('api.description', ['des' => $des]);
    }

    /**
     * description:规格参数
     * @author Harcourt
     * @date 2018/7/25
     */
    public function parameters(Request $request)
    {
        $p_id = $request->input('p_id', 0);
        if (empty($p_id)) {
            return error('00000', '参数不全');
        }
        $pwhere = [
            ['product.p_id', $p_id],
            ['p_delete', 1],
            ['p_putaway', 1]
        ];
        $product = DB::table('product')->select('p_id', 'p_title', 'p_gmt_putaway', 'p_sn')->where($pwhere)->first();

        if (empty($product)) {
            return error('30001', '商品不存在');
        }
        $data = array(
            '商品名称' => $product->p_title,
            '商品编号' => $product->p_sn,
            '上架时间' => date('Y-m-d', $product->p_gmt_putaway)
        );
        success($data);

    }

    /**
     * description:选择规格
     * @author Harcourt
     * @date 2018/7/25
     */
    public function showSize(Request $request)
    {
        $p_id = $request->input('p_id', 0);
        if (empty($p_id)) {
            return error('00000', '参数不全');
        }
        $pwhere = [
            ['p_id', $p_id],
            ['p_delete', 1],
            ['p_putaway', 1]
        ];
        $product = DB::table('product')->select('p_id', 'p_list_pic', 'attr_ids_group')->where($pwhere)->first();
        if (empty($product)) {
            return error('30001','商品不存在');
        }
        $size_portoties = DB::table('size')->where('p_id', $p_id)->pluck('size_portoties')->toArray();

        $totalSize = [];
        foreach ($size_portoties as $size_portoty) {
//            dd($size_portoty);
            $totalSize = array_merge($totalSize, json_decode($size_portoty, true));

        }
//        dd($totalSize);
        $attr_val_ids = array_unique($totalSize);


        $sqlRaw = "GROUP_CONCAT( CONCAT('{\"',xm_attr_val.attr_val_id,'\":\"',xm_attr_val.attr_val_name,'\"}')) as portoties, xm_attr_val.attr_id, xm_attributes.attr_title";


        $results = DB::table('attr_val')->selectRaw($sqlRaw)->join('attributes', 'attr_val.attr_id', '=', 'attributes.attr_id')->whereIn('attr_val_id', $attr_val_ids)->groupBy('attr_val.attr_id')->get();
//        $results = DB::table('attr_val')->select('attr_id','attr_val_name')->whereIn('attr_val_id',$attr_val_ids)->groupBy('attr_val.attr_id')->get();
        foreach ($results as $result) {
//            $result->portoties =  (json_decode("[".$result->portoties."]",true));
            $portoties = DB::table('attr_val')->select('attr_val_id', 'attr_val_name')->whereIn('attr_val_id', $attr_val_ids)->where('attr_id', $result->attr_id)->get();
            foreach ($portoties as $portoty) {
                $portoty->bind_id = "\"" . $result->attr_id . "\":\"" . $portoty->attr_val_id . "\"";
            }
            $result->portoties = $portoties;
//
        }

        $sizes = DB::table('size')->select('size_id', 'size_title', 'size_portoties', 'size_stock', 'size_cash', 'size_balance', 'size_img')->where('p_id', $p_id)->get();
        foreach ($sizes as $size) {
            $size_portoty = ltrim($size->size_portoties, '{');
            $size_portoty = rtrim($size_portoty, '}');
//            dd(explode(',',$size_portoty));
            //json_decode($size->size_portoties,true);
            $size->size_portoties = explode(',', $size_portoty);
            if ($size->size_img) {
                $size->size_img = IMAGE_DOMAIN . $size->size_img;
            }
        }
        $data['sizes'] = $sizes;
        $data['shows'] = $results;
        success($data);

    }

    /**
     * description:搜索商品
     * @author libaowei
     * @date 2019/8/23
     */
    public function search(Request $request)
    {
        //1、首页2、店铺里
        $position_id = $request->input('position_id', 1);
        //1、全部2、商品3、店铺
        $type = $request->input('type', 1);
        //商品名称
        $match = $request->input('match');
        //商家id
        $shop_id = $request->input('shop_id', 0);

        $page = $request->input('page', 0);

        //排序类型
        $by = $request->input('by',0);

        if (empty($match) || !in_array($type, [1, 2, 3]) || !in_array($position_id, [1, 2])) {
            return error('00000', '参数不全');
        }

        if ($position_id == 2 && empty($shop_id)) {
            return error('00000', '参数不全');
        }

        //默认排序方式
        if(isset($by)) {
            $by = 1;
        }
        $where = [
            ['p_putaway', 1],
            ['p_delete', 1]
        ];
        if ($position_id == 2) {
            $where[] = ['shop_id', $shop_id];
        }
        $limit = 20;
        $offset = $limit * $page;

        //如果是综合和热销根据销量排序
        if($by == 1 || $by == 2) {
            $field = 'p_sold_num';
            $orderby = 'desc';
        //根据价格排序
        } else {
            $field = 'p_cash';
            $orderby = 'asc';
        }

        if ($type != 3) {
            $products = DB::table('product')->selectRaw('xm_product.p_id,p_type,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price', [IMAGE_DOMAIN])->where($where)->where('p_title', 'like', '%' . $match . '%')->orderBy('p_sort', 'asc')->orderBy($field,$orderby)->offset($offset)->limit($limit)->get();
        } else {
            $products = [];
        }

        if ($position_id == 1 && $type != 2) {

            if ($type == 1) {
                $limit = 2;
                $offset = 0;
            }
            $shops = DB::table('shop')->selectRaw('shop_id,concat(?,shop_img) as shop_img,shop_title,shop_summary,shop_score', [IMAGE_DOMAIN])->where('shop_status', 2)->where('shop_title', 'like', '%' . $match . '%')->orderBy('shop_id', 'desc')->offset($offset)->limit($limit)->get();;

        } else {
            $shops = [];
        }

        $data['product'] = $products;
        $data['shop'] = $shops;
        $showsale = DB::table('master_config')->where('code','is_show_sale')->value('value');
        if ($showsale == null){
            $showsale = '1';
        }
        $data['showsale'] = $showsale;

        success($data);
    }

}
