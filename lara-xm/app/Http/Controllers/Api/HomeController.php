<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate')->only(['checkRole','saveInfo']);
    }

    /**
     * description:首页
     * @author Harcourt
     * @date 2018/7/24
     */
    public function index(Request $request)
    {
        $page = $request->input('page', 0);

        $carousel = DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video,type_c', [IMAGE_DOMAIN])->where([['enabled', 1], ['position_id', 1]])->get();
        //处理视频地址
        $this->video_url($carousel);

        $outers = DB::table('outer')->where('status', 1)->orderBy('outer_id', 'asc')->get();

        foreach ($outers as $outer) {
            $outer->outer_img = IMAGE_DOMAIN . $outer->outer_img;
        }

        $where = [
            ['p_putaway', 1],
            ['p_recommend', 1],
            ['p_delete', 1],

        ];
        $upright = DB::table('product_recommend')->selectRaw('xm_product_recommend.p_id,concat(?,pr_img) as p_list_pic', [IMAGE_DOMAIN])->where($where)->where('product_recommend.pr_status', 1)->join('product', 'product.p_id', '=', 'product_recommend.p_id')->orderBy('pr_sort', 'asc')->orderBy('product_recommend.p_id', 'desc')->offset(0)->limit(2)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        if ($upright) {
            $uprightIds = array_column($upright, 'p_id');
        } else {
            $uprightIds = [];
        }
        $where[] = ['p_type','!=',4];
        if ($uprightIds) {
            $transverses = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_cash', 'p_balance', 'market_price','p_stock')->where($where)->whereNotIn('p_id', $uprightIds)->orderBy('p_sort', 'asc')->orderBy('p_id', 'desc')->get();
        } else {
            $transverses = DB::table('product')->select('p_id', 'p_title', 'p_list_pic', 'p_cash', 'p_balance', 'market_price','p_stock')->where($where)->orderBy('p_sort', 'asc')->orderBy('p_id', 'desc')->get();
        }


        foreach ($transverses as $transvers) {
            $transvers->p_list_pic = strpos_domain($transvers->p_list_pic);
        }

        $pwhere = [
            ['p_putaway', 1],
            ['p_type', 3],
            ['p_delete', 1]
        ];
        $limit = 20;
        $offset = $limit * $page;
        $boutiques = DB::table('product')->select('p_id', 'p_list_pic', 'p_title', 'p_sold_num', 'p_cash', 'p_balance', 'market_price', 'p_type','p_describe','p_stock')->where($pwhere)->orderBy('p_sort', 'desc')->offset($offset)->limit($limit)->get();

        foreach ($boutiques as $boutique) {
            $boutique->p_list_pic = strpos_domain($boutique->p_list_pic);
        }

        $data['carousel'] = $carousel;
        $data['outer'] = $outers;
        $data['upright'] = $upright;
        $data['transverse'] = $transverses;
        $data['boutique'] = $boutiques;
        $showsale = DB::table('master_config')->where('code','is_show_sale')->value('value');
        if ($showsale == null){
            $showsale = '1';
        }
        $data['showsale'] = $showsale;
        success($data);
    }


    /**
     * @param Request $request
     * description:热搜词列表
     * @author Harcourt
     * @date 2018/7/24
     */
    public function searchList(Request $request)
    {
        $type = $request->input('type', 0);
        $shop_id = $request->input('shop_id', 0);
        if (!in_array($type, array('1', '2'))) {
            return error('00000', '参数不全');
        }
        if ($type == 2 && empty($shop_id)) {
            return error('00000', '参数不全');
        }
        if ($type == 1) {
            $shop_id = 0;
        }
        $where = [
            ['sk_type', $type],
            ['shop_id', $shop_id]
        ];
        $res = DB::table('search_keywords')->select('sk_id', 'sk_title')->where($where)->orderBy('sk_sort', 'asc')->get();

        success($res);
    }

    /**
     * description:搜索
     * @author Harcourt
     * @date 2018/7/24
     */
    public function doSearch(Request $request)
    {
        $position_id = $request->input('position_id', 1);//1、首页2、店铺里
        $type = $request->input('type', 1);//1、全部2、商品3、店铺
        $match = $request->input('match');
        $shop_id = $request->input('shop_id', 0);
        $page = $request->input('page', 0);

        if (empty($match) || !in_array($type, [1, 2, 3]) || !in_array($position_id, [1, 2])) {
            return error('00000', '参数不全');
        }

        if ($position_id == 2 && empty($shop_id)) {
            return error('00000', '参数不全');
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

        if ($type != 3) {
            $products = DB::table('product')->selectRaw('xm_product.p_id,p_type,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price', [IMAGE_DOMAIN])->where($where)->where('p_title', 'like', '%' . $match . '%')->orderBy('p_sort', 'asc')->orderBy('p_id', 'desc')->offset($offset)->limit($limit)->get();
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

    /**
     * description:检查是否达到
     * @author Harcourt
     * @date 2018/9/4
     */
    public function checkRole(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $user = DB::table('mq_users_extra')->select('user_cx_rank', 'team_number')->where('user_id', $user_id)->first();
        if (empty($user)) {
            return error('99998', '非法操作');
        }

        $team_number = $user->team_number;
        if (empty($user->user_cx_rank)) {
            $now_rank = 0;
        } elseif ($team_number >= TEAM_30_NUMBER) {
            $now_rank = 4;
        } elseif ($team_number >= TEAM_10_NUMBER) {
            $now_rank = 3;
        } elseif ($team_number >= TEAM_3_NUMBER) {
            $now_rank = 2;
        } else {
            $now_rank = 1;
        }
        //原等级不变
        if ($user->user_cx_rank < $now_rank) {
            DB::table('mq_users_extra')->where('user_id', $user_id)->update(['user_cx_rank' => $now_rank]);
        }else{
            $now_rank = $user->user_cx_rank;
        }
        $leaderInfo = DB::table('team_leaders')->where('user_id', $user_id)->first();
        $hasSaved = 0;
        if ($leaderInfo) {
            $hasSaved = 1;
        }
        $data = [
            'hasSaved'=>$hasSaved,
            'roleRank'=>$now_rank
        ];
        success($data);


    }

    /**
     * description:完善信息
     * @author Harcourt
     * @date 2018/9/4
     */
    public function saveInfo(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $name = $request->input('name');
        $mobile = $request->input('mobile');
        $area = $request->input('area');
        $detail = $request->input('detail');

        if (empty($user_id) || empty($name)|| empty($mobile) || empty($area) || empty($detail)) {
            return error('00000', '参数不全');
        }
        $verification = new \Verification();

        if(!$verification->fun_phone($mobile)){
            return error('01000','请输入合法的手机号码');
        }
        $rank = DB::table('mq_users_extra')->where('user_id',$user_id)->value('user_cx_rank');
        if($rank == null || $rank < 2){
            return error('99998','非法操作');
        }
        $leaderInfo = DB::table('team_leaders')->where('user_id', $user_id)->first();
        if($leaderInfo){
            return error('10004','信息已完善');
        }
        $insert_data = [
            'user_id'=>$user_id,
            'tl_name'=>$name,
            'tl_mobile'=>$mobile,
            'tl_area'=>$area,
            'tl_detail'=>$detail,
        ];
        $tl_id = DB::table('team_leaders')->insertGetId($insert_data,'tl_id');
        if($tl_id){
            success();
        }else{
            error('99999','操作失败');
        }
    }

    //获取首页分类
    public function Getcate()
    {
        //查询3个特殊的分类
        $tx = DB::table('cate')->selectRaw('cate_id,concat(?,img) as img,type,cate_sort,cate_title,parent_id', [IMAGE_DOMAIN])->whereIn('type',[1,2,3])->limit(3)->get()->toArray();
        //火粉头条链接
        foreach ($tx as $hf) {
            if($hf->type == 3) {
                $hf->like = "http://web.myls1688.com/#/newsFire";
            }else {
                $hf->like = "";
            }
        }

        //查询有类型的分类
        $cate1 = DB::table('cate')->selectRaw('cate_id,concat(?,img) as img,type,cate_sort,cate_title,parent_id', [IMAGE_DOMAIN])->where('parent_id',0)->orderBy('cate_sort')->limit(7)->get()->toArray();
        //其他分类链接为空
        foreach ($cate1 as $cate) {
            $cate->like = "";

        }
        $cate = array_merge($tx,$cate1);
        success($cate);
    }


    public function GetTree()
    {
        //查询上面的2个图片都信息
        $two = DB::table('product_recommend')->selectRaw('p_id,pr_sort,concat(?,pr_img) as img,cate_type,pr_status,type,itemId', [IMAGE_DOMAIN])->where('pr_status',1)->orderBy('pr_sort')->limit(2)->get();
        //查询出首页3个图片的信息
        $tree = DB::table('product_recommend')->selectRaw('p_id,pr_sort,concat(?,pr_img) as img,cate_type,pr_status,type,itemId', [IMAGE_DOMAIN])->where([['cate_type','>',0],['pr_status',0]])->orWhere('cate_type',0)->orderBy('pr_sort')->limit(3)->get();

        $data['two'] = $two;
        $data['tree'] = $tree;
        success($data);
    }

    public function Getwo(Request $request)
    {
        //获取分类的父类ID
        $cate_id = $request->id;
        //如果不存在默认为0
        if(!isset($child)) {
            $child = 0;
        }
        //查询分类父类标题
        $title = DB::table('cate')->select('cate_title')->where('cate_id',$cate_id)->first();
        //查询出二级分类的信息
        $cate = DB::table('cate')->selectRaw('cate_id,concat(?,img) as img,type,cate_sort,cate_title,parent_id', [IMAGE_DOMAIN])->where('parent_id',$cate_id)->get();
        //查询分类下面的商品
        //$product = DB::table('product')->selectRaw('xm_product.p_id,p_type,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price', [IMAGE_DOMAIN])->where('cate_id',$cate_id)->orWhere('child_cate_id',$child)->get();
        //查询轮播图
        $carousel = DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video,type_c', [IMAGE_DOMAIN])->where([['enabled', 1], ['position_id', 1]])->get();
        //处理视频地址
        $this->video_url($carousel);
        //数据整合
        $data['cate'] = $cate;
        //$data['product'] = $product;
        $data['carousel'] = $carousel;
        $data['title'] = $title->cate_title;
        success($data);
    }

    public function Getby(Request $request)
    {
        //获取分类的父类ID
        //$cate_id = $request->cate_id;
        //获取二级ID
        $cate_id = $request->cate_id;
        //排序的字段
        $by = $request->by;
        //区分ID是父类还是子类(1 父类  2 子类)
        $type = $request->type;

        if(!isset($cate_id) || !isset($by) || !isset($type)) {
            return error('0000','请求参数不完整');
        }

        //页数
        $page = $request->page;
        //如果是综合和热销根据销量排序
        if($by == 1 || $by == 2) {
            $field = 'p_sold_num';
            $orderby = 'desc';
            //根据价格排序
        } else {
            $field = 'p_cash';
            $orderby = 'asc';
        }
        //默认取出的条数
        $limit = 20;
        //计算出第几页的数据
        $offset = $limit * $page;
        //子类下面的商品
        if($type == 2) {
            //查询出符合条件的商品信息
            $product = DB::table('product')->selectRaw('xm_product.p_id,p_type,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price,p_describe,p_stock', [IMAGE_DOMAIN])->where([['child_cate_id',$cate_id],['p_putaway',1],['p_delete','<',3]])->orderBy($field,$orderby)->offset($offset)->limit($limit)->get();
            success($product);
        }else {
            //查询出符合条件的商品信息
            $product = DB::table('product')->selectRaw('xm_product.p_id,p_type,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price,p_describe,p_stock', [IMAGE_DOMAIN])->where([['cate_id',$cate_id],['p_putaway',1],['p_delete','<',3]])->orderBy($field,$orderby)->offset($offset)->limit($limit)->get();
            success($product);
        }
    }

    /**
     * description:查询热销商品
     * @author libaowei
     * @date 2019/8/30
     */
    public function hot(Request $request)
    {
//        header("ACCESS-CONTROL-ALLOW-ORIGIN:*");
        //页数
        $page = $request->page;
        //一页多少数据
        $quantity = $request->size;
        //是否需要显示爆款推荐
        $explosion = $request->explosion;
        //初始化数组
        $data = [];

        //如果页数不存在给默认值
        if(!isset($page)) {
            $page = 0;
        }
        //如果一页的数量不存在给默认值
        if(!isset($quantity)){
            $quantity = 10;
        }
        //如果显示爆款推荐不存在给默认值
        if(!isset($explosion)) {
            $explosion = 0;
        }

        $offset = $quantity * $page;
        //查询热销商品
        $products = DB::table('product')->select('p_id','p_title','p_describe','p_list_pic','p_cash','p_balance','market_price','p_sold_num','p_stock','is_size','p_type')->where([['p_delete',1],['p_putaway',1]])->orderBy('p_sold_num','DESC')->limit(10)->get();
        //统计数量为最下面的商品处理
        $count = count($products);
        //防止没有数据出错
        if($count > 0) {
            //进行处理
            foreach ($products as $product) {
                //商品图片
                $product->p_list_pic = IMAGE_DOMAIN.$product->p_list_pic;
                //判断是否多规格
                if($product->is_size) {
                    //查询库存,按库存最多的查询
                    $size = DB::table('size')->where('p_id',$product->p_id)->orderBy('size_stock','DESC')->first();
                    $product->p_stock = $size->size_stock;
                }
            }
            //判断是否需要爆款
            if($explosion == 1) {
                $carousel = DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video',[IMAGE_DOMAIN])->where('status',1)->first();
                $itemid = '';
            } else if($explosion == 3) {
                $carousel = DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video',[IMAGE_DOMAIN])->where('status',3)->get();
                $itemid = $data['more'] = $carousel[0]->itemid;
            } else {
                $carousel = '';
                $itemid = '';
            }

            if($count >= 10) {
                //防止会查询到之前的数据
                if($page <= 0) {
                    $offset = $count + 1;
                } else {
                    $offset = $quantity * $page;
                }

                //查询剩余的数据
                $end_products = DB::table('product')->select('p_id','p_title','p_describe','p_list_pic','p_cash','p_balance','market_price','p_sold_num','p_stock','is_size','p_type')->where([['p_delete',1],['p_putaway',1]])->orderBy('p_sold_num','DESC')->offset($offset)->limit($quantity)->get();
                //进行处理
                foreach ($end_products as $product) {
                    //商品图片
                    $product->p_list_pic = IMAGE_DOMAIN.$product->p_list_pic;
                    //判断是否多规格
                    if($product->is_size) {
                        //查询库存,按库存最多的查询
                        $size = DB::table('size')->where('p_id',$product->p_id)->orderBy('size_stock','DESC')->first();
                        $product->p_stock = $size->size_stock;
                    }
                }
            } else {

                $end_products = '';
            }
            //数据整合
            $data['boutique'] = $products;
            $data['carousel'] = $carousel;
            $data['end_products'] = $end_products;
            $data['more'] = $itemid;
            //返回数据
            success($data);
        }
    }

    /**
     * description:查询新品上新
     * @author libaowei
     * @date 2019/8/30
     */
    public function new(Request $request)
    {
//        header("ACCESS-CONTROL-ALLOW-ORIGIN:*");
        //页数
        $page = $request->page;
        $quantity = $request->size;

        //初始化数组
        $data = [];
        if(!isset($page)) {
            $page = 0;
        }

        if(!isset($size)) {
            $quantity = 20;
        }

        $offset = $quantity * $page;

        //查询推荐图
        $carousel = DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid',[IMAGE_DOMAIN])->where('status',2)->get();
        //查询商品
        $products = DB::table('product')->select('p_id','p_title','p_describe','p_list_pic','p_cash','p_balance','market_price','p_sold_num','p_stock','is_size')->where([['p_delete',1],['p_putaway',1]])->orderBy('p_gmt_putaway','DESC')->offset($offset)->limit($quantity)->get();

        if(count($products) > 0) {
            //进行处理
            foreach ($products as $product) {
                //商品图片
                $product->p_list_pic = IMAGE_DOMAIN.$product->p_list_pic;
                //判断是否多规格
                if($product->is_size) {
                    //查询库存,按库存最多的查询
                    $size = DB::table('size')->where('p_id',$product->p_id)->orderBy('size_stock','DESC')->first();
                    $product->p_stock = $size->size_stock;
                }
            }
        }
        $data['carousel'] = $carousel;
        $data['products'] = $products;

        success($data);
    }

    /**
     * description:处理轮播图视频
     * @author libaowei
     * @date 2019/9/12
     */
    public function video_url ($carousel) {
        foreach ($carousel as $carouse){
            if(!empty($carouse->video)) {
                $carouse->video = IMAGE_DOMAIN.$carouse->video;
            }
        }
    }

    /**
     * 查询进入App弹窗数据
     */
    public function alert_img() {
        //查询出信息
        $shares = DB::table('alert_img')->selectRaw('type,status,concat(?,img_src) as img_src,target', [IMAGE_DOMAIN])->where('status',1)->get();

        success($shares);

    }
    
        /**
     * 查询首页分类商品
     */
    public function cate_pro(Request $request) {
        $cid = $request->c_id;
        $page = $request->page;

        $date = [];
        if(!isset($cid)) {
            $cid = 1;
        }

        if(!isset($page)){
            $page = 0;
        }

        $limit = 20;
        $offset = $limit * $page;

        //查询分类
        $cate = DB::table('cate')->select('cate_title','cate_id')->where([['parent_id',0],['type',0]])->orderBy('cate_sort','ASC')->get()->toArray();
        $tuijian = array('cate_title' => '推荐','cate_id' => 1);
        array_unshift($cate,$tuijian);

        //查询推荐的商品
        if($cid == 1) {
            $product = DB::table('product')->selectRaw('xm_product.p_id,p_type,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price', [IMAGE_DOMAIN])->where([['p_recommend',1],['p_delete',1],['p_putaway',1]])->orderBy('p_sort','DESC')->offset($offset)->limit($limit)->get();
        } else {
            //查询分类下的商品
            $product = DB::table('product')->selectRaw('xm_product.p_id,p_type,concat(?,p_list_pic) as p_list_pic,p_title,p_sold_num,p_cash,p_balance,market_price', [IMAGE_DOMAIN])->where([['cate_id',$cid],['p_delete',1],['p_putaway',1]])->orderBy('p_sort','DESC')->offset($offset)->limit($limit)->get();
        }

        $date['cate'] = $cate;
        $date['product'] = $product;

        success($date);
    }
}
