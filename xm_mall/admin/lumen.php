<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

define('IN_ECS',true);
define('ROOT_PATH', __DIR__ . '/../');

include(ROOT_PATH.'data/config.php');

require(ROOT_PATH . 'languages/zh_cn/admin/common.php');
require(ROOT_PATH . 'languages/zh_cn/admin/log_action.php');

require_once ROOT_PATH . 'vendor/autoload.php';

$database = [
    'driver'    => 'mysql',
    'host'      => $db_host,
    'database'  => $db_name,
    'username'  => $db_user,
    'password'  => $db_pass,
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => $prefix,
];

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;//如果你不喜欢这个名称，as DB;就好

$capsule = new Capsule;

// 创建链接
$capsule->addConnection($database);

// 设置全局静态可访问
$capsule->setAsGlobal();

// 启动Eloquent
$capsule->bootEloquent();

//注意：GET POST等动作必须是大写

$dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addGroup('/admin/lumen.php',function($dispatch){
        $dispatch->addRoute('GET', '/usercenter/test', 'UserCenterController@test');
    });

});

try
{

    // Fetch method and URI from somewhere
    $httpMethod = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];

    // Strip query string (?foo=bar) and decode URI
    if (false !== $pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }

    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    $processed = false;

    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            // ... 404 Not Found
            break;
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            // ... 405 Method Not Allowed
            die();
        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];
            // ... call $handler with $vars
            if($handler instanceof Closure){
                $handler($vars);
                $processed = true;
            } else if(is_string($handler)){
                $temps =  explode('@',$handler);

                if(count($temps)==2){

                    $class = 'maqu\\Controllers\\' . $temps[0];
                    $instance = new $class();
                    $ref_class = new ReflectionClass($class);

                    if($ref_class->hasMethod($temps[1])){
                        $method = $ref_class->getMethod($temps[1]);
                        $res = $method->invokeArgs($instance,$vars);
                        exit($res->getContent());
                        $processed = true;
                    }

                }

            }
    }

    if($processed){
        die();
    }
} catch (\Exception $e){
    \maqu\Log::error($e->getTraceAsString());
}