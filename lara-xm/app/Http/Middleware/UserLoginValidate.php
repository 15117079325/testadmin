<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class UserLoginValidate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user_id = $request->input('user_id',0);
        $token = $request->input('token');

        if(empty($user_id) || empty($token)){
            return response(error('00000','参数不全'));
        }


        $user = DB::table('users')->select('users.user_id','token','expire_time','user_status')->join('mq_users_extra', 'users.user_id', '=', 'mq_users_extra.user_id')->where('users.user_id',$user_id)->first();

        if(empty($user) || $user->user_status == '2'){
             return response(error('10001','用户不存在'));
        }
        if($user && (strcmp($token,$user->token) !== 0 || $user->expire_time <= time())){
            return  response(error('02000','用户登录信息不正确或者过期'));
        }

        DB::table('users')->where('user_id',$user_id)->update(['expire_time'=>time() + LOGIN_EXPIRE_TIME]);

        return $next($request);




    }

    public function terminate($request, $response)
    {
        //这里是响应后调用的方法
    }
}
