<?php

class CardAli
{
    const APPCODE = "72c5c7bb37884d47ba52a7ebacf54e90";
    const HOST = "https://yixi.market.alicloudapi.com";
    const PATH = "/ocr/idcard";
    const METHOD = "POST";

    public function sendCard($image = '')
    {
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . self::APPCODE);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type" . ":" . "application/x-www-form-urlencoded; charset=UTF-8");
        $image = base64_encode(file_get_contents($image));
        $bodys = "image=" . $image . "&side=front";
        $url = self::HOST . self::PATH;
        $host = self::HOST;
        $method = self::METHOD;
        return $this->sendPath($url, $host, $bodys, $method, $headers);
    }

    public function sendPath($url, $host, $bodys, $method, $headers)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$" . $host, "https://")) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        return curl_exec($curl);
    }
}