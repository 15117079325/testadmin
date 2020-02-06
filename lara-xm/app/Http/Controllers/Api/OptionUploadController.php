<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\OSS;
use App\Http\Controllers\Controller;
use Intervention\Image\ImageManagerStatic as Image;

class OptionUploadController extends Controller
{
    //
    public function __construct()
    {
//        $this->middleware('userLoginValidate');
    }

    public function get(Request $request)
    {
        //头像
        $input = $request->file();
        $imgs = $input['img'];
        $imgArr = [];
        foreach ($imgs as $img) {
            $action = 'data';
            $content_type = mime_content_type($img->getRealPath());
            $file_name = $action . '/' . time() . rand(10, 99) . '.' . $img->extension();
            if ($action == 'data') {
                $content = Image::make($img)->resize(200, 200)->encode()->encoded;
            } else {
                $content = file_get_contents($img);
            }
            $bucket_name = 'huodan-test';
            OSS::publicUploadContent($bucket_name, $file_name, $content, ['ContentType' => $content_type]);//设置HTTP头
            //获取公开文件URL
            $url = OSS::getPublicObjectURL($bucket_name, $file_name);
            $imgArr[] = $url;
        }
        return success(['imgPath' => $imgArr]);
    }
}
