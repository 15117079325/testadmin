<?php
return [
    // APP_EVN
    'app_env' =>  'development',//env('APP_ENV') == 'production' ? 'production' :

    // The default default_client name which configured in `development` or `production` section
    'default_client' => 'client_1',


    'development' => [
//        'client_1' => [
//            'gt_appid' => 'n3P90Rlyen9dCrYPlCpLd4',
//            'gt_appkey' => 'hqLXNBYJpQ893j0lTTNR2A',
//            'gt_appsecret' => 'NPPsgrlOL1AwirewbQdyc2',
//            'gt_mastersecret' => '1bgfoS83br5fIWPgnlIEn',
//            'gt_domainurl' => 'http://sdk.open.api.igexin.com/apiex.htm',
//        ],
        'client_1' => [
            'gt_appid' => '2n2aPk9Zrk6z8T8UzJJhL8',
            'gt_appkey' => 'RMIVjtHJv595BWNcvbWsq5',
            'gt_appsecret' => 'belL2iHtE96aSSZCjmQKc',
            'gt_mastersecret' => 'OUyVwmztR88LlVjFB98dO8',
            'gt_domainurl' => 'http://sdk.open.api.igexin.com/apiex.htm',
        ],

        // other client_3   ......
    ],
    'production' => [
        'client_1' => [
            'gt_appid' => '2n2aPk9Zrk6z8T8UzJJhL8',
            'gt_appkey' => 'RMIVjtHJv595BWNcvbWsq5',
            'gt_appsecret' => 'belL2iHtE96aSSZCjmQKc',
            'gt_mastersecret' => 'OUyVwmztR88LlVjFB98dO8',
            'gt_domainurl' => 'http://sdk.open.api.igexin.com/apiex.htm',
        ],
//        'client_2' => [
//            'gt_appid' => '87klYMPe1o515SCcbx7Co5',
//            'gt_appkey' => 'dd9XpsgHff89DJgUgvW6L8',
//            'gt_appsecret' => 'aKMLyeXLCc8hFpjcuf8gW8',
//            'gt_mastersecret' => 'zx85PndZVf8Q1M1Iv9dEy3',
//            'gt_domainurl' => 'http://sdk.open.api.igexin.com/apiex.htm',
//        ],

        // other client_3   ......

    ],
];
