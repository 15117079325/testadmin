<?php
namespace maqu\Services;

/**
 *
 * 服务层基础类
 *
 * @author maqu
 *
 */
class BaseService {

    /**
     * 返回验证失败状态
     * @param null $msg
     * @param null $data
     * @return mixed array
     */
    public function error($msg){
        return array(
            'status' => RESPONSE_FAILURE,
            'code'=>'',
            'msg' => $msg,
            'auth_failure' => AUTH_FAILURE_NO,
            'data' => []
        );
    }

    /**
        * 返回失败状态
        * @param null $msg 消息
        * @param null $data 返回的data
        * @return mixed array
     */
    public function failure($msg,$data=null){
        return [
            'result'=>false,
            'message'=>$msg,
            'data'=>$data
        ];
    }

    /**
     * 返回成功状态
     * @param null $msg 消息
     * @param null $data 返回的data
     * @return mixed array
     */
    public function success($data=null){
        return [
            'result'=>true,
            'message'=>'success',
            'data'=>$data
        ];
    }

    /**
     * 返回失败状态的json数据
     * @param null $message 消息
     * @param null $data 返回的data
     * @return mixed json对象
     */
    public function args_invalid()
    {
        return array(
            'status' => RESPONSE_ARGUMENT_INVALID,
            'code'=>'',
            'msg' => '非法参数',
            'auth_failure' => AUTH_FAILURE_NO,
            'data' => []
        );
    }

}
