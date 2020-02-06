<?php

namespace maqu\filesystem;

/**
 * @Copyright(C),Hangzhou Maqu Technology Co.,Ltd
 *
 * 文件系统管理类
 *
 * @author: yang
 * @version: 1.0.0
 * @date:  2017/12/28
 * @History:
 * 2017/12/28 新建 yang
 * @desc:
 */
class  FileSystemManager
{

    private static $adapter =null;

    /**
     *
     * 获取适配器
     *
     * @param null $driver 驱动
     * @return AliossAdapter|LocalAdapter
     * @throws \Exception
     */
    public static function getAdapter($driver = null){

        if(self::$adapter ==null){

            $driver = !$driver?FILESYSTEM_DRIVER:$driver;

            switch($driver){
                case 'local':
                    return new LocalAdapter();
                case 'alioss':
                    return new AliossAdapter(OSS_ACCESS_ID,OSS_ACCESS_KEY,OSS_ENDPOINT,OSS_BUCKET);
                default:
                    throw new \Exception('file system driver [' . $driver . '] not found');
                    break;
            }
        }

        return self::$adapter;
    }
}