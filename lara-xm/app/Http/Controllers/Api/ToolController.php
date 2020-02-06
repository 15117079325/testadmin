<?php

namespace App\Http\Controllers\Api;
require_once app_path('Libs/aliyun-oss/autoload.php');
require_once app_path('Libs/aliyun-oss/samples/Common.php');

include_once app_path('Libs/aliyun_vod/aliyun-php-sdk-core/Config.php');


use OSS\Core\OssException;
use Sts\Request\V20150401 as Sts;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ToolController extends Controller
{
    /**
     * description:图片上传临时凭证
     * @author Harcourt
     * @date 2018/9/3
     */
    public function assumeRole()
    {
        $bucket = \Common::getBucketName();
        $accessKeyId = \Config::OSS_ACCESS_ID;
        $accessKeySecret = \Config::OSS_ACCESS_KEY;
        $endpoint = \Config::OSS_ENDPOINT;
        $regionId = 'cn-hangzhou';
        $iClientProfile = \DefaultProfile::getProfile($regionId,$accessKeyId,$accessKeySecret);
        $client = new \DefaultAcsClient($iClientProfile);
        try{
            $assumeRoleRequest = new Sts\AssumeRoleRequest();
            $assumeRoleRequest->setDurationSeconds(900);
            $assumeRoleRequest->setRoleArn('acs:ram::1188556449444811:role/img-role');
            $assumeRoleRequest->setRoleSessionName('img-role');
            $response = $client->getAcsResponse($assumeRoleRequest);
            $obj = $response->Credentials;
            $obj->endPoint = $endpoint;
            $obj->bucket = $bucket;
            success($obj);
        }catch (\Exception $ex){
            die($ex->getMessage());
        }

    }


}
