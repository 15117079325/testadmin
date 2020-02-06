<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class CateController extends Controller
{
    public function index(Request $request)
    {
        //分类的父类ID
    	$parent_id = $request->cate_id;
    	if(!isset($parent_id)) {
    		return error('00000', '参数不全');
    	}
    	//获取二级分类信息
    	$parent = DB::table('cate')->selectRaw('cate_id,cate_title,parent_id,type,concat(?,img) as img,cate_sort', [IMAGE_DOMAIN])->where('parent_id',$parent_id)->get();
        //获分类下的轮播图的数据
        $balan = DB::table('carousel_ad')->selectRaw('ca_id,concat(?,img) as img,type,itemid,color_value,video', [IMAGE_DOMAIN])->where('position_id',$parent_id)->orderBy('ca_id')->get();
        //数据整合
        $date['banner'] = $balan;
        $date['parent'] = $parent;

    	success($date);
    }

    public function cate()
    {
        //获取父类分类信息
        $cate = DB::table('cate')->where([['parent_id',0],['type',0]])->get();
        
        success($cate);
    } 
}
