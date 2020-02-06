<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class CommunityController extends Controller
{
    //查询所有分类
    public function comm_class() {
        //查询所有类别
        $class = DB::table('community_class')->where('status',1)->get();
        //返回类别数据
        return success($class);
    }

    //查询分类下的内容
    public function comm(Request $request) {
        $page = $request->page;
        $size = $request->size;

        if(empty($page)) {
            $page = 0;
        }

        if(empty($size)) {
            $size = 20;
        }

        if(!is_numeric($page)) {
            return error('999','非法操作');
        }

        $offset = $size * $page;

        $counts = DB::table('community')->select('id','title','comm_describe','img_src','update_time','show_num')->where('status',1)->offset($offset)->limit($size)->get();
        if(isset($counts)) {
            foreach ($counts as $count) {
                $count->img_src = IMAGE_DOMAIN.$count->img_src;
            }
            success($counts);


        } else {
            success();
        }
    }

    //查询轮播图
    public function img_src(){
        $img = DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video,type_c', [IMAGE_DOMAIN])->where([['enabled', 1], ['position_id', 5]])->get();
        success($img);
    }

    //查询单个内容
    public function show_comm(Request $request){

        $id = $request->id;

        if(empty($id)) {
            return error('00000','参数不全');
        }

        $community = DB::table('community')->where('id',$id)->first();

        if(!empty($community)) {
            DB::table('community')->where('id',$id)->increment('show_num',1);

            success($community);
        } else {
            success();
        }
    }

    //搜索文章
    public function search(Request $request) {

        $title = $request->title;
        $page = $request->page;

        if(empty($page)) {
            $page = 0;
        }

        if(!is_numeric($page)) {
            return error('999','非法操作');
        }

        $limit = 20;
        $offset = $limit * $page;

        if(empty($title)) {
            return error('00000','参数不全');
        }

        $date = DB::table('community')->where('title', 'like', "%{$title}%")->offset($offset)->limit($limit)->get();

        success($date);
    }

}
