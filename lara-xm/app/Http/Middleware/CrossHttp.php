<?php

namespace App\Http\Middleware;

use Closure;

class CrossHttp
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
//        return $next($request);
        $response = $next($request);
        $origin = $request->server('HTTP_ORIGIN') ? $request->server('HTTP_ORIGIN') : '*';
        $response->header('Access-Control-Allow-Origin', $origin);
        $response->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Cookie, Accept, multipart/form-data, application/json,X-Requested-With, application/x-www-form-urlencoded, text/plain');
//        $response->header('Access-Control-Allow-Headers', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, OPTIONS');
//        $response->header('Access-Control-Allow-Credentials', 'false');
        return $response;
    }
}
