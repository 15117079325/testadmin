<?php
/**
 * Created by PhpStorm.
 * User: yangxy
 * Date: 2017/12/28
 * Time: 10:03
 */

namespace maqu\filesystem;

use OSS\OssClient;
use OSS\Core\OssException;

/**
 * @Copyright(C),Hangzhou Maqu Technology Co.,Ltd
 *
 * 阿里云OSS文件系统适配器
 *
 * @author: yang
 * @version: 1.0.0
 * @date:  2017/12/28
 * @History:
 * 2017/12/28 新建 yang
 * @desc:
 */
class AliossAdapter implements IFileSystemAdapter
{
    private $access_id;
    private $access_key;
    private $endpoint;
    private $bucket;
    private $bucket_name = '';

    private $oss = null;

    /**
     * AliossAdapter constructor.
     */
    public function __construct($access_id,$access_key,$endpoint,$bucket)
    {
        $this->access_id = $access_id;
        $this->access_key = $access_key;
        $this->endpoint = $endpoint;
        $this->bucket_name = $bucket;

        $this->oss = new OssClient($access_id,$access_key,$endpoint,false);

    }

    /**
     * 删除文件
     * @param $filename 文件名（相对路径）
     * @return mixed
     */
    public function deleteFile($filename)
    {
        $this->oss->deleteObject($this->bucket_name,$filename);
    }

    /**
     * 获取访问图片的完整路径
     *
     * @param $filename 文件名（相对路径）
     * @return mixed
     */
    public function getFullPath($filename)
    {
        if($filename){
            return 'https://'. $this->bucket_name . '.' . $this->endpoint . '/' .$filename;
        } else {
            return '';
        }
    }

    /**
     * 从url复制文件
     *
     * @param 文件url $url
     * @param 保存时的文件名 $filename
     * @return 保存时的文件名
     */
    public function copyFromUrl($url, $filename,$deleteTemp = false)
    {
        $temp = ROOT_PATH . $filename;

        if(!copy($url,$temp)){
            throw new \Exception('fail to copy file');
        }

        $content = file_get_contents($temp);

        $this->oss->putObject(OSS_BUCKET,$filename,$content);

        if($deleteTemp){
            @unlink($temp);
        }

        return $filename;
    }

    /**
     * 从自身url复制文件
     *
     * @param 源文件 $source
     * @param 目标文件 $dest
     * @return 目标文件
     */
    public function copyFromSelf($source, $dest)
    {
        $temp = ROOT_PATH . $dest;

        if(!copy($this->getFullPath($source),$temp)){
            throw new \Exception('fail to copy file');
        }

        $content = file_get_contents($temp);

        $this->oss->putObject(OSS_BUCKET,$dest,$content);

        @unlink($temp);

        return $dest;
    }

    /**
     * 上传本地文件到云服务器
     *
     * @param 本地文件的全路径 $local_file
     * @param 目标文件 $dest
     */
    public function uploadFile($local_file, $dest)
    {
        $content = file_get_contents($local_file);

        $this->oss->putObject(OSS_BUCKET,$dest,$content);

        return $dest;
    }

}