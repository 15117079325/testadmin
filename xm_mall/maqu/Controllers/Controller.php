<?php

namespace maqu\Controllers;

use Symfony\Component\HttpFoundation\JsonResponse;

class Controller
{

    /**
     * 返回成功状态的json数据
     * @param null $data 返回的data
     * @param null $message 消息
     * @return mixed json对象
     */
    protected function success($data=null, $message='成功')
    {
        return new JsonResponse(array(
            'status' => RESPONSE_SUCCESS,
            'code'=>'',
            'message' => $message,
            'auth_failure' => AUTH_FAILURE_NO,
            'data' => $data
        ),200,[],0);
    }

    /**
     * 返回失败状态的json数据
     * @param null $message 消息
     * @param null $data 返回的data
     * @return mixed json对象
     */
    protected function failure($message, $data=null)
    {
        if (!$message)
            $message = 'Request failed!';
        if (!is_string($message))
            $message = $message->first();

        return new JsonResponse(array(
            'status' => RESPONSE_FAILURE,
            'code'=>'',
            'message' => $message,
            'auth_failure' => AUTH_FAILURE_NO,
            'data' => $data
        ),200,[],0);

    }

    /**
     * 返回失败状态的json数据
     * @param null $message 消息
     * @param null $data 返回的data
     * @return mixed json对象
     */
    protected function args_invalid()
    {
        return new JsonResponse(array(
            'status' => RESPONSE_ARGUMENT_INVALID,
            'code'=>'',
            'message' => '非法参数',
            'auth_failure' => AUTH_FAILURE_NO,
            'data' => []
        ),200,[],0);
    }

    /**
     * 自定义状态的json对象
     * @param $status 状态值
     * @param $message 消息
     * @param $data 返回数据
     * @return mixed json对象
     */
    protected function jsonResult($status, $message, $data,$code,$auth_failure)
    {
        return new JsonResponse(array(
            'status' => $status,
            'code'=>$code,
            'message' => $message,
            'auth_failure' => $auth_failure,
            'data' => $data
        ),200,[],0);
    }
    
}
