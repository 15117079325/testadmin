<?php

/**
 * Created by PhpStorm.
 * User: yangxy
 * Date: 2016/5/27
 * Time: 11:27
 */

if (! function_exists('gmtime')) {

    /**
     * 返回时间戳
     * @return int 时间戳
     */
    function gmtime() {
        return time() - date('Z');
//        return time();
    }
}

if (! function_exists('server_timezone'))
{
    /**
     * 获得服务器的时区
     *
     * @return  integer
     */
    function server_timezone()
    {
        if (function_exists('date_default_timezone_get'))
        {
            return date_default_timezone_get();
        }
        else
        {
            return date('Z') / 3600;
        }
    }
}

if (! function_exists('gmstr2time'))
{
    /**
     * 转换字符串形式的时间表达式为GMT时间戳
     *
     * @param   string  $str
     *
     * @return  integer
     */
    function gmstr2time($str)
    {
        $time = strtotime($str);

        if ($time > 0)
        {
            $time -= date('Z');
        }

        return $time;
    }
}

if (! function_exists('local_date'))
{

    function local_date($format, $time = NULL)
    {
        if ($time === NULL)
        {
            $time = time();
        }
        elseif ($time <= 0)
        {
            return '';
        }

        /* 现在暂时还没有用户自定义时区的功能，所有的时区都跟着商城的设置走 */
//        $now = \Carbon\Carbon::now(config('flb.TIME_ZONE'));
//        $time += $now->offset;
//        unset($now);

        return date($format, $time);
    }
}


if (! function_exists('complete_url'))
{
    /**
     * 相对路径转化成绝对网址路径
     *
     * @access  public
     * @param   string $orignal_url
     *
     * @return  string
     */
    function complete_url($orignal_url)
    {
        if (empty($orignal_url))
        {
            return $orignal_url;
        }
        elseif(starts_with($orignal_url,'http:') || starts_with($orignal_url,'https:'))
        {
            return $orignal_url;
        } else {
            $root_path = str_finish(config('flb.STATIC_RESOURCE_URL'),'/');

            if(starts_with($orignal_url,'/')){
                return $root_path .substr($orignal_url, 1);
            } else {
                return $root_path . $orignal_url;
            }
        }
    }
}

if (! function_exists('addslashes_deep'))
{
    /**
     * 递归方式的对变量中的特殊字符进行转义
     *
     * @access  public
     * @param   mix     $value
     *
     * @return  mix
     */
    function addslashes_deep($value)
    {
        if (empty($value))
        {
            return $value;
        }
        else
        {
            return is_array($value) ? array_map('addslashes_deep', $value) : addslashes($value);
        }
    }
}

if (! function_exists('uuid'))
{

    /**
     *
     * 创建唯一码
     *
     * @return string
     */
    function uuid() {
        if (function_exists ( 'com_create_guid' )) {
            $str =com_create_guid ();
            $str = str_replace('{','',$str);
            $str = str_replace('}','',$str);
            return $str;
        } else {
            mt_srand ( ( double ) microtime () * 10000 ); //optional for php 4.2.0 and up.随便数播种，4.2.0以后不需要了。
            $charid = strtoupper ( md5 ( uniqid ( rand (), true ) ) ); //根据当前时间（微秒计）生成唯一id.
            $hyphen = chr ( 45 ); // "-"
            $uuid = '' . //chr(123)// "{"
                substr ( $charid, 0, 8 ) . $hyphen . substr ( $charid, 8, 4 ) . $hyphen . substr ( $charid, 12, 4 ) . $hyphen . substr ( $charid, 16, 4 ) . $hyphen . substr ( $charid, 20, 12 );
            //.chr(125);// "}"
            return $uuid;
        }
    }
}

if (! function_exists('think_ucenter_md5'))
{
    /**
     * 系统非常规MD5加密方法
     * @param  string $str 要加密的字符串
     * @return string
     */
    function think_ucenter_md5($str, $key = 'ThinkUCenter'){
        return '' === $str ? '' : md5(sha1($str) . $key);
    }
}

if (! function_exists('think_ucenter_encrypt'))
{
    /**
     * 系统加密方法
     * @param string $data 要加密的字符串
     * @param string $key  加密密钥
     * @param int $expire  过期时间 (单位:秒)
     * @return string
     */
    function think_ucenter_encrypt($data, $key, $expire = 0) {
        $key  = md5($key);
        $data = base64_encode($data);
        $x    = 0;
        $len  = strlen($data);
        $l    = strlen($key);
        $char =  '';
        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) $x=0;
            $char  .= substr($key, $x, 1);
            $x++;
        }
        $str = sprintf('%010d', $expire ? $expire + time() : 0);
        for ($i = 0; $i < $len; $i++) {
            $str .= chr(ord(substr($data,$i,1)) + (ord(substr($char,$i,1)))%256);
        }
        return str_replace('=', '', base64_encode($str));
    }
}

if (! function_exists('think_ucenter_decrypt'))
{

    /**
     * 系统解密方法
     * @param string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
     * @param string $key  加密密钥
     * @return string
     */
    function think_ucenter_decrypt($data, $key){
        $key    = md5($key);
        $x      = 0;
        $data   = base64_decode($data);
        $expire = substr($data, 0, 10);
        $data   = substr($data, 10);
        if($expire > 0 && $expire < time()) {
            return '';
        }
        $len  = strlen($data);
        $l    = strlen($key);
        $char = $str = '';
        for ($i = 0; $i < $len; $i++) {
            if ($x == $l) $x = 0;
            $char  .= substr($key, $x, 1);
            $x++;
        }
        for ($i = 0; $i < $len; $i++) {
            if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
                $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
            }else{
                $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
            }
        }
        return base64_decode($str);
    }
}

if (! function_exists('lastsql')){
    function lastsql(){

        $sql = DB::getQueryLog();

        $query = end($sql);

        dd($query);
        //return $query;

    }
}

if (! function_exists('print_sql')){
    function print_sql(){

        $sql = DB::getQueryLog();

        dd($sql);
    }
}


if (! function_exists('array_group')){

    /**
     *The array_group method returns a grouped array which group by the groupkey.
     *
     * @param $array
     * @param $groupkey
     *
     * @return array
     */
    function array_group($array,$groupkey){

        if(!$array){
            return [];
        }

        $is_stdclass=false;
        if(is_array($array[0])){
            $is_stdclass=false;
        } else {
            $is_stdclass =true;
        }

        $cur_arr=array();   //current row
        $result = array();
        foreach($array as $item){
            if($is_stdclass){
                $cur_arr = (array)$item;
            } else {
                $cur_arr=$item;
            }

            if(!array_key_exists($groupkey,$cur_arr)){
                return [];
            }

            $result[$cur_arr[$groupkey]][] =$cur_arr;

        }

        unset($cur_arr);

        return $result;

    }
}

if (! function_exists('complete_html')){

    /**
     *把富文本编辑器的内容转化为完整的html
     *
     * @param $base_url base url
     * @param $title 标题
     * @param $content 富文本编辑内容
     *
     * @return mixed
     */

    function complete_html($base_url,$title,$content){
        $base = sprintf('<base href="%s/" />', $base_url);
        $html = '<!DOCTYPE html><html><head><title>'.$title.'</title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>img {width: auto\9;height: auto;vertical-align: middle;border: 0;-ms-interpolation-mode: bicubic;max-width: 100%; }html { font-size:100%; } </style>'.$base.'</head><body>'.$content.'</body></html>';
        return $html;
    }
}

if (! function_exists('array_rand2')){

    /**
     *The array_group method returns a grouped array which group by the groupkey.
     *
     * @param $array
     * @param $groupkey
     *
     * @return array
     */
    function array_rand2(&$array){

        if(!$array || count($array)==0){
            return $array;
        }

        $array_result =[];

        $arr=range(1,count($array));
        shuffle($arr);
        foreach($arr as $values)
        {
            $array_result[]=$array[$values-1];
        }

        $array =$array_result;

        return $array;
    }
}

if (! function_exists('trans')){

    /**
     *The array_group method returns a grouped array which group by the groupkey.
     *
     * @param $array
     * @param $groupkey
     *
     * @return array
     */
    function trans($key){

        return app('translator')->trans($key);

    }
}

if (! function_exists('utf8_strlen')) {
    function utf8_strlen($str) {
        $count = 0;
        for($i = 0; $i < strlen($str); $i++){
            $value = ord($str[$i]);
            if($value > 127) {
                $count++;
                if($value >= 192 && $value <= 223) $i++;
                elseif($value >= 224 && $value <= 239) $i = $i + 2;
                elseif($value >= 240 && $value <= 247) $i = $i + 3;
                else die('Not a UTF-8 compatible string');
            }
            $count++;
        }

        return $count;
    }
}

/**
 * mysqli其实没有该函数，参考mysql_result
 */
if(! function_exists('mysqli_result')){
    function mysqli_result($result, $number, $field=0) {
        mysqli_data_seek($result, $number);
        $row = mysqli_fetch_array($result);
        return $row[$field];
    }
}

if(! function_exists('get_client_ip')){
    /**
     * 获取客户端ip
     * @param int $type 0- 字符串 1-long
     * @return mixed
     */
    function get_client_ip($type = 0) {
        $type       =  $type ? 1 : 0;
        $ip = real_ip();
//        static $ip  =   NULL;
//        if ($ip !== NULL) return $ip[$type];
//        if(isset($_SERVER['HTTP_X_REAL_IP'])){//nginx 代理模式下，获取客户端真实IP
//            $ip=$_SERVER['HTTP_X_REAL_IP'];
//        }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {//客户端的ip
//            $ip     =   $_SERVER['HTTP_CLIENT_IP'];
//        }elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {//浏览当前页面的用户计算机的网关
//            $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
//            $pos    =   array_search('unknown',$arr);
//            if(false !== $pos) unset($arr[$pos]);
//            $ip     =   trim($arr[0]);
//        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
//            $ip     =   $_SERVER['REMOTE_ADDR'];//浏览当前页面的用户计算机的ip地址
//        }else{
//            $ip=$_SERVER['REMOTE_ADDR'];
//        }
        // IP地址合法验证
        $long = sprintf("%u",ip2long($ip));
        $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }
}
