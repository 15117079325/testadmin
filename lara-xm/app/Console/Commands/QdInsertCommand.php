<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QdInsertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:qdinsert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
//        $insert = $this->getCheckUsers();
//        DB::table('insert_qd')->insert($insert);
//        die;
        $insert = DB::table('insert_qd')->get()->toArray();
        foreach ($insert as $k => $v) {
            $url = "http://api.myls1688.com/api/sign";
            $params['user_id'] = $v->user_id;
            $params['is_debug'] = 1;
            $params['begin_time'] = strtotime('2019-09-09');
            $this->curlPostData($url, $params);
            print_r($v->user_id);
            echo PHP_EOL;
        }


    }

    public function curlPostData($url, $params = '')
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0); //在发起连接前等待的时间，如果设置为0，则无限等待。
        if (empty($params)) {
            return 'data is null';
        }
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_HEADER, 0);
//    if (empty($header)) {
//        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
//    }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorno = curl_errno($curl);
        if ($errorno) {
            return $errorno;
        }
        curl_close($curl);
        return $res;
    }

    public function getCheckUsers()
    {
        $sql_old = "SELECT user_id FROM insert_users GROUP BY user_id";
        $res_old = DB::select($sql_old);
        $sql_new = "SELECT user_id FROM insert_users_new GROUP BY user_id";
        $res_new = DB::select($sql_new);
        $old = array_column($res_old, null, 'user_id');
        $new = array_column($res_new, null, 'user_id');
        $insert_arr = [];
        foreach ($old as $k => $v) {
            if (!isset($new[$k])) {
                $insert_arr[$k] = $v;
            }
        }
        return json_decode(json_encode($insert_arr), true);
    }
}
