<?php
class Verification
{
    /**
     * 验证正整数
     * @param  [type] $arr [description]
     * @return [type]      [description]
     */
    function fun_num_all($arr){
        if (!is_array($arr)) {
            return (preg_match('/^[1-9][0-9]*$/', $arr))?true:false;
        }
        $flag = true;
        for ($i=0; $i < count($arr); $i++) {
            if (!preg_match('/^[1-9][0-9]*$/', $arr[$i])) {
                $flag = false;
                break;
            }
        }
        return $flag == true?true:false;
    }
    /**
     * 判断是否为数组且键是否存在
     * @param  [type]  $arr  [目标数组]
     * @param  [type]  $keys [键数组]
     * @return [type]  [description]
     */
    function fun_array($arr,$keys){
        if (!is_array($arr)||!is_array($keys)) {
            return false;
        }
        $flag = true;
        // foreach ($arr as $key => $value) {
        for ($i=0; $i < count($keys); $i++) {
            if (!array_key_exists($keys[$i], $arr)) {
                $flag = false;
                break;
            }
        }
        // if ($flag == false) {
        //     break;
        // }
        // }
        return $flag == true?true:false;
    }
//只有数字和字母
    function words_num_only($str = ''){
        if($str == ''){
            return false;
        }
        return preg_match('/^[0-9a-zA_Z]+$/', $str)?true:false;
    }


    // 验证有两位小数的正实数
    function fun_num_two($str)
    {
        return (preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $str))?true:false;
    }
//验证是否为指定长度的字母/数字组合
    function fun_text1($num1,$num2,$str)
    {
        Return (preg_match("/^[a-zA-Z0-9]{".$num1.",".$num2."}$/",$str))?true:false;
    }
//颜值验证是否为数字
    function fun_num($str){
        return (preg_match("/^\d*$/", $str))?true:false;
    }
//验证是否为指定长度数字
    function fun_text2($num1,$num2,$str)
    {
        return (preg_match("/^[0-9]{".$num1.",".$num2."}$/i",$str))?true:false;
    }
//验证是否为指定长度汉字
    function fun_font($num1,$num2,$str)
    {
// preg_match("/^[\xa0-\xff]{1,4}$/", $string);

        return (preg_match("/^([\x81-\xfe][\x40-\xfe]){".$num1.",".$num2."}$/",$str))?true:false;
    }
//验证用户名是否只有数字中文和字母和长度
    function fun_username($num1,$num2,$str){
        return (preg_match('/^[0-9a-zA-Z_\x{4e00}-\x{9fa5}]{'.$num1.','.$num2.'}+$/u',$str))?true:false;
    }

//验证身份证号码
    function fun_idcard($str)
    {
        return (preg_match('/(^([\d]{15}|[\d]{18}|[\d]{17}X)$)/',$str))?true:false;
    }
//验证邮件地址
    function fun_email($str){
        return (preg_match('/^[_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4}$/',$str))?true:false;
    }
//验证电话号码
    function fun_phone($str)
    {
        if(strlen($str) !== 11){
            return false;
        }
        return (preg_match("/(13\d|14[57]|15[^4\D]|17[^49\D]|18\d|19[89]|16[6])\d{8}/", $str))?true:false;
    }
//验证邮编
    function fun_zip($str)
    {
        return (preg_match("/^[1-9]\d{5}$/",$str))?true:false;
    }
//验证url地址
    function fun_url($str)
    {
        return (preg_match("/^http:\/\/[A-Za-z0-9]+\.[A-Za-z0-9]+[\/=\?%\-&_~`@[\]\':+!]*([^<>\"\"])*$/",$str))?true:false;
    }
// 数据入库 转义 特殊字符 传入值可为字符串 或 一维数组
    function data_join(&$data)
    {
        if(get_magic_quotes_gpc() == false)
        {
            if (is_array($data))
            {
                foreach ($data as $k => $v)
                {
                    $data[$k] = addslashes($v);
                }
            }
            else
            {
                $data = addslashes($data);
            }
        }
        Return $data;
    }
// 数据出库 还原 特殊字符 传入值可为字符串 或 一/二维数组
    function data_revert(&$data)
    {
        if (is_array($data))
        {
            foreach ($data as $k1 => $v1)
            {
                if (is_array($v1))
                {
                    foreach ($v1 as $k2 => $v2)
                    {
                        $data[$k1][$k2] = stripslashes($v2);
                    }
                }
                else
                {
                    $data[$k1] = stripslashes($v1);
                }
            }
        }
        else
        {
            $data = stripslashes($data);
        }
        Return $data;
    }
// 数据显示 还原 数据格式 主要用于内容输出 传入值可为字符串 或 一/二维数组
// 执行此方法前应先data_revert()，表单内容无须此还原
    function data_show(&$data)
    {
        if (is_array($data))
        {
            foreach ($data as $k1 => $v1)
            {
                if (is_array($v1))
                {
                    foreach ($v1 as $k2 => $v2)
                    {
                        $data[$k1][$k2]=nl2br(htmlspecialchars($data[$k1][$k2]));
                        $data[$k1][$k2]=str_replace(" "," ",$data[$k1][$k2]);
                        $data[$k1][$k2]=str_replace("\n","<br>\n",$data[$k1][$k2]);
                    }
                }
                else
                {
                    $data[$k1]=nl2br(htmlspecialchars($data[$k1]));
                    $data[$k1]=str_replace(" "," ",$data[$k1]);
                    $data[$k1]=str_replace("\n","<br>\n",$data[$k1]);
                }
            }
        }
        else
        {
            $data=nl2br(htmlspecialchars($data));
            $data=str_replace(" "," ",$data);
            $data=str_replace("\n","<br>\n",$data);
        }
        Return $data;
    }
}
?>