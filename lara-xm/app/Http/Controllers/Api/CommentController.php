<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{

    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['lists']);
    }
    /**
     * description:评论列表
     * @author Harcourt
     * @date 2018/7/25
     */
    public function lists(Request $request)
    {
        $p_id = $request->input('p_id',0);
        $page = $request->input('page',0);

        if(empty($p_id)){
            return error('00000','参数不全');
        }
        $limit = 20;
        $offset = $limit*$page;
        $cwhere = [
            ['p_id',$p_id],
            ['cmt_status',1]
        ];
        $comments = DB::table('comments')->selectRaw('xm_comments.user_id,cmt_content,cmt_imgs,cmt_gmt_create,cmt_score,cmt_size,nickname as user_name,headimg')->join('users','users.user_id','=','comments.user_id')->where($cwhere)->orderBy('cmt_id','desc')->offset($offset)->limit($limit)->get();

        foreach ($comments as $comment) {
            if($comment->cmt_imgs){
                $imgs = explode(',',$comment->cmt_imgs);
                foreach ($imgs as $key=>$img) {
                    $imgs[$key] = strpos_domain($img);
                }

            }else{
                $imgs = [];
            }
            $comment->cmt_imgs = $imgs;
            $comment->headimg = strpos_domain($comment->headimg);
            $comment->cmt_content = base64_decode($comment->cmt_content);
            $comment->cmt_gmt_create = date('Y-m-d', $comment->cmt_gmt_create);
        }
        success($comments);

    }

    /**
     * description:评价
     * @author Harcourt
     * @date 2018/8/21
     */
    public function doComment(Request $request)
    {
        $user_id = $request->input('user_id',0);
        $od_id = $request->input('od_id',0);
        $order_id = $request->input('order_id',0);
        $imgs = $request->input('imgs');
        $content = $request->input('content');
        $outlook_score = $request->input('outlook_score');
        $service_score = $request->input('service_score');
        $shipp_score = $request->input('shipp_score');

        if(empty($od_id) || empty($order_id) || empty($user_id) || empty($content) || empty($outlook_score) || empty($service_score) || empty($shipp_score)){
            return error('00000','参数不全');
        }
        $order = DB::table('orders')->select('order_status','user_id')->where('order_id',$order_id)->first();
        if($order->user_id != $user_id || $order->order_status != 4 ){
            return error('99998','非法操作');
        }
        $order_detail = DB::table('order_detail')->where('od_id',$od_id)->first();

        if(empty($order_detail) || $order_detail->order_id != $order_id){
            return error('99998','非法操作');
        }
        if($order_detail->is_comment == 1){
            return error('30006','该商品已评价');
        }
        $where = [
            ['order_id',$order_id],
            ['is_comment',0]
        ];
        $uncomment_ids = DB::table('order_detail')->where($where)->pluck('od_id');
        $flag = false;
        if(count($uncomment_ids) == 1){
            $flag = true;
        }
        $now = time();
        $score = round(($outlook_score + $service_score + $shipp_score)/3,1);
        if($imgs){
           $imgsArr = json_decode($imgs,true);
           $imgs = implode(',',$imgsArr);
        }
        $insert_data = [
            'p_id'=>$order_detail->p_id,
            'user_id'=>$user_id,
            'order_id'=>$order_id,
            'cmt_size'=>$order_detail->size_title,
            'cmt_content'=>base64_encode($content),
            'outlook_score'=>$outlook_score,
            'service_score'=>$service_score,
            'shipp_score'=>$shipp_score,
            'cmt_score'=>$score,
            'cmt_imgs'=>$imgs,
            'cmt_gmt_create'=>$now,
        ];
        DB::beginTransaction();
        $cmt_id = DB::table('comments')->insertGetId($insert_data,'cmt_id');
        if(empty($cmt_id)){
            DB::rollBack();
            return error('99999','操作失败');
        }
        $aff_row = DB::table('order_detail')->where('od_id',$od_id)->update(['is_comment'=>1]);

        if($flag){
           DB::table('orders')->where('order_id',$order_id)->update(['order_status'=>5]);
        }

        if($aff_row){
            DB::commit();
            success();
        }else{
            DB::rollBack();
            error('99999','操作失败');
        }

    }
}
