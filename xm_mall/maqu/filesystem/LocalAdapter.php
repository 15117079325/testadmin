<?php
/**
 * Created by PhpStorm.
 * User: yangxy
 * Date: 2017/12/28
 * Time: 10:03
 */

namespace maqu\filesystem;

/**
 * @Copyright(C),Hangzhou Maqu Technology Co.,Ltd
 *
 * 本地文件系统适配器
 *
 * @author: yang
 * @version: 1.0.0
 * @date:  2017/12/28
 * @History:
 * 2017/12/28 新建 yang
 * @desc:
 */
class LocalAdapter implements IFileSystemAdapter
{
    /**
     * 删除文件
     *
     * @param $filename 文件名
     * @return mixed
     */
    public function deleteFile($filename)
    {
        @unlink(ROOT_PATH . $filename);
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
            return '../' .$filename;
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
        if(!copy($url,ROOT_PATH . $filename)){
            throw new \Exception('fail to copy file');
        }

        return $filename;
    }

    /**
     * 从自身url复制文件
     *
     * @param 源文件 $source
     * @param 目标文件 $dest
     * @return 目标文件
     * @throws \Exception
     */
    public function copyFromSelf($source, $dest)
    {
        if(!copy('../' . $source, '../' . $dest)){
            throw new \Exception('fail to copy file');
        }

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
        move_upload_file($local_file, ROOT_PATH . $dest);

        return str_replace(ROOT_PATH,'',$local_file);
    }


}