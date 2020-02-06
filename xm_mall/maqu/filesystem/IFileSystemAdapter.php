<?php
/**
 * Created by PhpStorm.
 * User: yangxy
 * Date: 2017/12/28
 * Time: 9:46
 */

namespace maqu\filesystem;

/**
 * @Copyright(C),Hangzhou Maqu Technology Co.,Ltd
 *
 * 文件系统接口
 *
 * @author: yang
 * @version: 1.0.0
 * @date:  2017/12/28
 * @History:
 * 2017/12/28 新建 yang
 * @desc:
 */
interface IFileSystemAdapter
{

    /**
     * 删除文件
     *
     * @param $filename 文件名（相对路径）
     * @return mixed
     */
    public function deleteFile($filename);

    /**
     * 获取访问图片的完整路径
     *
     * @param $filename 文件名（相对路径）
     * @return mixed
     */
    public function getFullPath($filename);

    /**
     * 从url复制文件
     *
     * @param $url 文件url
     * @param $filename 保存时的文件
     * @param $deleteTemp 删除临时文件
     * @return mixed
     */
    public function copyFromUrl($url,$filename,$deleteTemp=false);

    /**
     * 从自身url复制文件
     *
     * @param $source 源文件
     * @param $dest 目标文件
     * @return mixed
     */
    public function copyFromSelf($source,$dest);

    /**
     *
     * 上传本地文件到云服务器
     *
     * @param $local_file 本地文件的全路径
     * @param $dest 目标文件
     * @return mixed
     */
    public function uploadFile($local_file,$dest);
}