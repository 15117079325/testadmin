<?php
class Log_
{
    // 打印log
    function  log_result($file,$word)
    {
//        print_r($file);
//        print_r($word);
        $fp = fopen($file,"a");
        flock($fp, LOCK_EX) ;
        fwrite($fp,"执行日期：".strftime("%Y-%m-%d-%H：%M：%S",time())."\n".$word."\n\n");
        flock($fp, LOCK_UN);
        fclose($fp);
        // print_r(test);
    }

    function changestate($sn,$transaction_id,$trade_state){
//         header('Location: http://tool.aiyaole.cn/callback/changestate?sn='.$sn.'&trade_state='.$trade_state);

    }

}

?>