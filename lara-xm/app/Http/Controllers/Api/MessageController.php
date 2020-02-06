<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['detail']);
    }

    /**
     * description:检查是否有新消息
     * @author Harcourt
     * @date 2018/8/31
     */
    public function checkNew(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $userLastRead = DB::table('mq_users_extra')->where('user_id',$user_id)->pluck('last_read')->first();
        if($userLastRead == null){
            $userLastRead = 0;
        }
        $swhere = [
            ['am_gmt_create','>=',$userLastRead],
            ['am_delete',0],
            ['am_status',2]
        ];
        $has = '0';
        $systemMessages = DB::table('system_message')->where($swhere)->get()->map(function ($value){return (array)$value;})->toArray();
        if($systemMessages) {
            $amids = array_column($systemMessages, 'am_id');
            $existMessages = DB::table('message')->where('user_id', $user_id)->whereIn('am_id', $amids)->get()->map(function ($value) {
                return (array)$value;
            })->toArray();
            if(count($existMessages) < count($amids)){
                $has = '1';
            }
        }
        $mwhere = [
            ['m_read',1],
            ['user_id',$user_id]
        ];
        $messageCount = DB::table('message')->where($mwhere)->count();
        if($messageCount){
            $has = '1';
        }
        $data = ['hasNew'=>$has];
        success($data);
    }
    /**
     * description:消息列表
     * @author Harcourt
     * @date 2018/8/31
     */
    public function lists(Request $request)
    {
        $user_id = $request->input('user_id', 0);
//        $user_id = 20176;
        $page = $request->input('page', 0);
        if (empty($user_id)) {
            return error('00000', '参数不全');
        }
        $userLastRead = DB::table('mq_users_extra')->where('user_id',$user_id)->pluck('last_read')->first();
        if($userLastRead == null){
            $userLastRead = 0;
        }
        $swhere = [
            ['am_gmt_create','>=',$userLastRead],
            ['am_delete',0]
        ];
        $systemMessages = DB::table('system_message')->where($swhere)->get()->map(function ($value){return (array)$value;})->toArray();
        $insertDatas = [];
        if($systemMessages){
            $amids = array_column($systemMessages,'am_id');
            $existMessages = DB::table('message')->where('user_id',$user_id)->whereIn('am_id',$amids)->get()->map(function ($value){return (array)$value;})->toArray();
            if(count($existMessages) < count($amids)){
                //把system插入到message
                if($existMessages){
                    $existIds = array_column($existMessages,'am_id');
                }else{
                    $existIds = [];
                }
                foreach ($systemMessages as $systemMessage) {
                    if(!in_array($systemMessage['am_id'],$existIds)){
                        if($systemMessage['am_detail']){
                            $isWeb = 2;
                        }else{
                            $isWeb = 1;
                        }
                        $insertData['user_id'] = $user_id;
                        $insertData['am_id'] = $systemMessage['am_id'];
                        $insertData['m_title'] = $systemMessage['am_title'];
                        $insertData['m_detail'] = $systemMessage['am_detail'];
                        $insertData['m_content'] = $systemMessage['am_content'];
                        $insertData['m_img'] = $systemMessage['am_img'];
                        $insertData['m_gmt_create'] = $systemMessage['am_gmt_create'];
                        $insertData['m_type'] = 2;
                        $insertData['m_read'] = 2;
                        $insertData['m_web'] = $isWeb;
                        $insertDatas[] = $insertData;
                    }
                }
                DB::table('message')->insert($insertDatas);

            }

        }


        $limit = 20;
        $offset = $limit * $page;
        $lists = DB::table('message')->select('m_id','o_id','m_type','m_title','m_content','m_img','m_gmt_create','m_web')->where('user_id', $user_id)->orderBy('m_id', 'desc')->offset($offset)->limit($limit)->get()->map(function ($value){return (array)$value;})->toArray();

        // $totalLists = array_merge($lists,$insertDatas);
        if($lists){
            $createTime = array_column($lists,'m_gmt_create');
            array_multisort($createTime,SORT_DESC,$lists);
        }
        foreach ($lists as $key => $list) {
            $lists[$key]['m_img'] = strpos_domain($list['m_img']);
            $lists[$key]['m_gmt_create'] = date('y/m/d',$list['m_gmt_create']);
        }
        $now = time();
        DB::table('message')->where('user_id',$user_id)->update(['m_read'=>2]);
        DB::table('mq_users_extra')->where('user_id',$user_id)->update(['last_read'=>$now]);
        success($lists);

    }

    /**
     * description:删除消息（除系统通知外）
     * @author Harcourt
     * @date 2018/8/31
     */
    public function delete(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $m_id = $request->input('m_id', 0);

        if (empty($user_id) || empty($m_id)) {
            return error('00000', '参数不全');
        }
        $where = [
            ['user_id',$user_id],
            ['m_id',$m_id]
        ];
        DB::table('message')->where($where)->delete();
        success();
    }

    /**
     * description:消息详情网页
     * @author Harcourt
     * @date 2018/8/31
     */
    public function detail(Request $request)
    {
        $m_id = $request->input('m_id', 0);

        $message = DB::table('message')->select('user_id','m_content','m_detail')->where('m_id',$m_id)->first();
        if(empty($message)){
            $des = '';
        }else{
            if($message->m_detail){
                $des = $message->m_detail;
            }else{
                $des = $message->m_content;
            }
        }
        return view('api.description',['des'=>$des]);

    }



}
