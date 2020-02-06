<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class RongChatController extends Controller
{

    public function __construct()
    {
        $this->middleware('userLoginValidate')->except(['getUserOrGroup']);
    }

    /**
     * description:根据昵称或者手机号搜索用户
     * @author Harcourt
     * @date 2018/8/2
     */
    public function searchUser(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $match = $request->input('match');
        if (empty($match) || empty($user_id) || empty($gc_id)) {
            return error('00000', '参数不全');
        }
        $group = DB::table('group_chat')->select('user_id', 'gc_admin')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        $user = DB::table('users')->selectRaw('user_id,headimg,user_name,mobile_phone,nickname')->where('user_name', $match)->orWhere('mobile_phone', $match)->first();
        if (empty($user)) {
            return error('10001', '用户不存在');
        }
        $user->headimg = strpos_domain($user->headimg);
        success($user);
    }


    /**
     *description:拉人进群
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupJoin(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $add_uid = $request->input('add_uid', 0);

        if (empty($user_id) || empty($add_uid) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_uid', 'gc_admin', 'gc_title')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        $group_uid = json_decode($group->gc_uid, true);

        if (in_array($add_uid, $group_uid)) {
            return error('50001', '该用户已经进群了');
        }
        $add_user_name = DB::table('users')->where('user_id', $add_uid)->pluck('nickname')->first();
        if (empty($add_user_name)) {
            return error('10001', '用户不存在');
        }

        $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();
        $targetUserIds = [$add_uid];

        $targetUserDisplayNames = [$add_user_name];

        $serverapi = new \ServerAPI();
        $ret = $serverapi->groupJoin($targetUserIds, $gc_id, $group->gc_title);

        $ret = json_decode($ret, TRUE);


        if ($ret && $ret['code'] == '200') {


            $content = array(
                'operatorUserId' => $user_id,
                'operation' => 'Add',
                'data' => array(
                    'operatorNickname' => $user->nickname,
                    'targetUserIds' => $targetUserIds,
                    'targetUserDisplayNames' => $targetUserDisplayNames
                ),
                "message" => $add_user_name . "已经进群了",
                'extra' => ''

            );
            $content = json_encode($content);
            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:GrpNtf', $content);

            //更新
            $group_uid[] = $add_uid;
            $gc_uid = json_encode($group_uid);
            $update_data = ['gc_uid' => $gc_uid];
            DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);
            success();
        } else {
            error('99999', '操作失败');
        }


    }


    /**
     *description:社群踢人 针对普通群用户
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupQuit(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $delete_uids = $request->input('delete_uids', 0);

        if (empty($user_id) || empty($delete_uids) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }
        $admins[] = $user_id;

        $group_uid = json_decode($group->gc_uid);

        $targetUserIds = json_decode($delete_uids, TRUE);
        if (count(array_intersect($admins, $targetUserIds))) {
            return error('99998', '非法操作');
        }
        $serverapi = new \ServerAPI();
        $ret = $serverapi->groupQuit($targetUserIds, $gc_id);

        $ret = json_decode($ret, TRUE);
        if ($ret && $ret['code'] == '200') {

            $targetUserDisplayNames = DB::table('users')->whereIn('user_id', $targetUserIds)->pluck('nickname')->toArray();

            $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();
            $content = array(
                'operatorUserId' => $user_id,
                'operation' => 'Kicked',
                'data' => array(
                    'operatorNickname' => $user->nickname,
                    'targetUserIds' => $targetUserIds,
                    'targetUserDisplayNames' => $targetUserDisplayNames
                ),
                "message" => implode(',', $targetUserDisplayNames) . "被移出了群",
                'extra' => ''

            );

            $content = json_encode($content);

            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:GrpNtf', $content);


            $left = array_diff($group_uid, $targetUserIds);

            $gc_uid = json_encode(array_values($left));

            $update_data = ['gc_uid' => $gc_uid];

            DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);

            success();

        } else {
            error('99999', '操作失败');
        }

    }


    /**
     *description:社群禁言 针对普通群用户，最多每次20人
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupUserGagAdd(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $gag_uid = $request->input('gag_uid', 0);

        if (empty($user_id) || empty($gag_uid) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_uid', 'gc_admin', 'gc_gag')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }
        $admins[] = $user_id;


        $group_gag = json_decode($group->gc_gag, true);
        if (empty($group_gag)) {
            $group_gag = [];
        }

        $targetUserIds = [$gag_uid];

        if (count(array_intersect($admins, $targetUserIds))) {
            return error('99998', '非法操作');
        }

        $serverapi = new \ServerAPI();
        $ret = $serverapi->groupUserGagAdd($gag_uid, $gc_id, 0);

        $ret = json_decode($ret, TRUE);
        if ($ret && $ret['code'] == '200') {

            $gag_user_name = DB::table('users')->whereIn('user_id', $targetUserIds)->pluck('nickname')->first();
            $targetUserDisplayNames = [$gag_user_name];

            $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();
            $content = array(
                'operatorUserId' => $user_id,
                'operation' => 'Gag',
                'data' => array(
                    'operatorNickname' => $user->nickname,
                    'targetUserIds' => $targetUserIds,
                    'targetUserDisplayNames' => $targetUserDisplayNames
                ),
                "message" => $gag_user_name . "已被" . $user->nickname . '禁言了',
                'extra' => ''

            );

            $content = json_encode($content);

            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:InfoNtf', $content);

            $allGag = array_merge($group_gag, $targetUserIds);

            $gc_gag = json_encode($allGag);

            $update_data = ['gc_gag' => $gc_gag];

            DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);

            success();

        } else {
            error('99999', '操作失败');
        }

    }


    /**
     *description:社群取消禁言 针对普通群用户，最多每次20人
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupUserGagRollback(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $gag_uid = $request->input('gag_uid', 0);

        if (empty($user_id) || empty($gag_uid) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_uid', 'gc_admin', 'gc_gag')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }
        $admins[] = $user_id;

        $group_gag = [];

        if ($group->gc_gag) {
            $group_gag = json_decode($group->gc_gag);
        }

        $targetUserIds = [$gag_uid];

        if (count(array_intersect($admins, $targetUserIds)) || count(array_intersect($group_gag, $targetUserIds)) != count($targetUserIds)) {
            return error('99998', '非法操作');
        }

        $serverapi = new \ServerAPI();

        $ret = $serverapi->groupUserGagRollback($gag_uid, $gc_id, 0);

        $ret = json_decode($ret, TRUE);
        if ($ret && $ret['code'] == '200') {

            $gag_user_name = DB::table('users')->whereIn('user_id', $targetUserIds)->pluck('nickname')->first();
            $targetUserDisplayNames = [$gag_user_name];

            $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();
            $content = array(
                'operatorUserId' => $user_id,
                'operation' => 'Gag',
                'data' => array(
                    'operatorNickname' => $user->nickname,
                    'targetUserIds' => $targetUserIds,
                    'targetUserDisplayNames' => $targetUserDisplayNames
                ),
                "message" => $gag_user_name . "已被" . $user->nickname . '取消禁言了',
                'extra' => ''

            );

            $content = json_encode($content);

            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:InfoNtf', $content);

            $allGag = array_diff($group_gag, $targetUserIds);

            $gc_gag = json_encode(array_values($allGag));

            $update_data = ['gc_gag' => $gc_gag];

            DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);

            success();

        } else {
            error('99999', '操作失败');
        }

    }

    /**
     *description:全员禁言 除管理员和群主外
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupBanAdd(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);

        if (empty($user_id) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();
        if (empty($group) || $group->gc_status != 1) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        $admins[] = $user_id;

        $serverapi = new \ServerAPI();


        $banRet = $serverapi->groupBanAdd($gc_id);
        $banRet = json_decode($banRet, true);
        if ($banRet['code'] == '200') {

            $whitelistRet = $serverapi->groupUserBanWhitelistAdd($admins, $gc_id);
            $whitelistRet = json_decode($whitelistRet, true);
            if ($whitelistRet['code'] == '200') {
                $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();

                $content = array(
                    'operatorUserId' => $user_id,
                    'operation' => 'Gag',
                    'data' => array(
                        'operatorNickname' => $user->nickname,
                    ),
                    "message" => $group->gc_title . "已被" . $user->nickname . '全员禁言了',
                    'extra' => ''

                );

                $content = json_encode($content);

                $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:InfoNtf', $content);//RC:GrpNtf

                $update_data = ['gc_status' => 2];
                DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);

                success();
            } else {
                $serverapi->groupBanRollback($gc_id);
                error('99999', '操作失败');
            }

        } else {
            error('99999', '操作失败');
        }


    }

    /**
     *description:群组解禁
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupBanRollback(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);

        if (empty($user_id) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();
        if (empty($group) || $group->gc_status != 2) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        $admins[] = $user_id;

        $serverapi = new \ServerAPI();


        $banRet = $serverapi->groupBanRollback($gc_id);
        $banRet = json_decode($banRet, true);
        if ($banRet['code'] == '200') {

            $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();

            $content = array(
                'operatorUserId' => $user_id,
                'operation' => 'Gag',
                'data' => array(
                    'operatorNickname' => $user->nickname,
                ),
                "message" => $group->gc_title . "已被" . $user->nickname . '解禁了，大家现在可以交流了',
                'extra' => ''

            );

            $content = json_encode($content);

            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:InfoNtf', $content);

            $update_data = ['gc_status' => 1];
            DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);

            success();

        } else {
            error('99999', '操作失败');
        }


    }


    /**
     *description:退出社群
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupLeave(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);

        if (empty($user_id) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();

        if (empty($group) || $group->user_id == $user_id) {
            return error('99998', '非法操作');
        }

        $group_uid = json_decode($group->gc_uid, true);

        if (!in_array($user_id, $group_uid)) {
            return error('99998', '非法操作');
        }


        $serverapi = new \ServerAPI();
        $ret = $serverapi->groupQuit($user_id, $gc_id);
        $ret = json_decode($ret, TRUE);
        if ($ret && $ret['code'] == '200') {

            $leave = [$user_id];

            $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();

            $content = array(
                'operatorUserId' => $user_id,
                'operation' => 'Quit',
                'data' => array(
                    'operatorNickname' => $user->nickname,
                    'targetUserIds' => $leave,
                    'targetUserDisplayNames' => array($user->nickname),
                    'newCreatorId' => null,
                ),
                "message" => $user->nickname . "退出了群组",
                'extra' => ''

            );
            $content = json_encode($content);
            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:GrpNtf', $content);

            $left_group_uid = array_diff($group_uid, $leave);
            $left_group_uid = json_encode(array_values($left_group_uid));
            $admins = [];
            if ($group->gc_admin) {
                $admins = json_decode($group->gc_admin, true);
            }
            if (in_array($user_id, $admins)) {
                $left_admins = array_diff($admins, $leave);
                $left_admins = json_encode(array_values($left_admins));
            } else {
                $left_admins = $group->gc_admin;
            }
            $update_data = array(
                'gc_uid' => $left_group_uid,
                'gc_admin' => $left_admins
            );
            DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);
            success();
        } else {
            error('99999', '操作失败');
        }
    }


    /**
     *description:修改社群名称
     * @author Harcourt
     * @date 2018/8/2
     */
    public function refreshGroupTitle(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $gc_title = $request->input('gc_title');

        if (empty($user_id) || empty($gc_id) || empty($gc_title)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
//        $admins = [];
//        if($group->gc_admin){
//            $admins = json_decode($group->gc_admin,true);
//        }
//        !in_array($user_id,$admins) &&
        if ($group->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        $serverapi = new \ServerAPI();

        $ret = $serverapi->groupRefresh($gc_id, $gc_title);
        $ret = json_decode($ret, TRUE);
        if ($ret && $ret['code'] == '200') {

            $user = DB::table('users')->select('user_id', 'nickname')->where('user_id', $user_id)->first();

            $content = array(
                'operatorUserId' => $user_id,
                'operation' => 'Rename',
                'data' => array(
                    'operatorNickname' => $user->nickname,
                    'targetGroupName' => $gc_title,
                ),
                "message" => $user->nickname . "将群名称修改成" . $gc_title,
                'extra' => ''

            );
            $content = json_encode($content);

            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:GrpNtf', $content);

            DB::table('group_chat')->where('gc_id', $gc_id)->update(['gc_title' => $gc_title]);

            success();

        } else {
            error('99999', '操作失败');
        }
    }

    /**
     *description:修改社群头像
     * @author Harcourt
     * @date 2018/8/2
     */
    public function refreshGroupImg(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $gc_pic = $request->input('gc_pic');

        if (empty($user_id) || empty($gc_id) || empty($gc_pic)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
//        $admins = [];
//        if($group->gc_admin){
//            $admins = json_decode($group->gc_admin,true);
//        }
//        !in_array($user_id,$admins) &&
        if ($group->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        DB::table('group_chat')->where('gc_id', $gc_id)->update(['gc_pic' => $gc_pic]);

        success(strpos_domain($gc_pic));

    }

    /**
     * description:添加公告
     * @author Harcourt
     * @date 2018/8/2
     */
    public function publishNotice(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $notice = $request->input('notice');

        if (empty($user_id) || empty($gc_id) || empty($notice)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();
        if (empty($group)) {
            return error('99998', '非法操作');
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        if (!in_array($user_id, $admins) && $group->user_id != $user_id) {
            return error('99998', '非法操作');
        }

        $insert_data = array(
            'gc_id' => $gc_id,
            'user_id' => $user_id,
            'gcn_content' => $notice,
            'gcn_gmt_create' => time()
        );

        $insert_id = DB::table('group_chat_notice')->insertGetId($insert_data, 'gcn_id');
        if ($insert_id) {
            success();

            $nickname = DB::table('users')->where('user_id',$user_id)->value('nickname');
            $content = array(
//                'operatorUserId' => $user_id,
//                'operation' => 'Gag',
//                'data' => array(
//                    'operatorNickname' => $nickname,
//                ),
                "message" => '群主发布了新的群公告',
                'extra' => ''

            );

            $content = json_encode($content);
            $serverapi = new \ServerAPI();
            $serverapi->messageGroupPublish($user_id, $gc_id, 'RC:InfoNtf', $content);//RC:GrpNtf
        } else {
            error('99999', '操作失败');
        }

    }

    /**
     * description:获取群公告详细
     * @author Harcourt
     * @date 2018/8/23
     */
    public function getNotice(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        if (empty($gc_id) || empty($user_id)) {
            return error('00000', '参数不全');
        }
        $gc_uid = DB::table('group_chat')->where('gc_id', $gc_id)->pluck('gc_uid')->first();
        if (empty($gc_uid)) {
            return error('99998', '非法操作');
        }
        $group_userids = json_decode($gc_uid, true);
        if (!in_array($user_id, $group_userids)) {
            return error('99998', '非法操作');
        }

        $notice = DB::table('group_chat_notice')->select('group_chat_notice.user_id', 'users.nickname', 'users.headimg', 'gcn_content', 'gcn_gmt_create')->join('users', 'users.user_id', '=', 'group_chat_notice.user_id')->where('gc_id', $gc_id)->orderBy('gcn_id', 'desc')->first();
        if (empty($notice)) {
            return error('50002', '还未设置群公告');
        }
        $notice->headimg = strpos_domain($notice->headimg);

        $notice->gcn_gmt_create = date('Y-m-d H:s', $notice->gcn_gmt_create);
        success($notice);
    }


    /**社群信息
     * description:xxx
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupInfo(Request $request)
    {

        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);

        if (empty($user_id) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_pic', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();

        if (empty($group)) {
            return error('99998', '非法操作');
        }

        $notice = DB::table('group_chat_notice')->where('gc_id', $gc_id)->orderBy('gcn_id', 'desc')->pluck('gcn_content')->first();
        if (empty($notice)) {
            $notice = '暂无公告';
        }
        $group_uid = json_decode($group->gc_uid, true);

        if (!in_array($user_id, $group_uid)) {
            return error('99998', '非法操作');
        }
        $group->gc_pic = strpos_domain($group->gc_pic);
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
            $group_uid =  array_diff($group_uid,$admins);

        }
        $role = 3;
        if (in_array($user_id, $admins)) {
            $role = 2;
        }
        if ($user_id == $group->user_id) {
            $role = 1;
        }

        $group_uid =  array_diff($group_uid,[$group->user_id]);

        $group_uid = array_merge([$group->user_id],$admins,$group_uid);

        $users = DB::table('users')->select('user_id', 'nickname', 'headimg')->whereIn('user_id', $group_uid)->orderByRaw("FIELD(user_id, " . implode(", ", $group_uid) . ")")->offset(0)->limit(10)->get();
        foreach ($users as $user) {
            $user->headimg = strpos_domain($user->headimg);
        }

        $total = count($group_uid);

        $base = array(
            'gc_id' => $gc_id,
            'master_id' => $group->user_id,
            'gc_title' => $group->gc_title,
            'gc_pic' => $group->gc_pic,
            'notice' => $notice
        );

        $data = array(
            'base' => $base,
            'users' => $users,
            'total' => $total,
            'role' => $role
        );
        success($data);
    }

    /**
     * description:群成员列表
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupUserList(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        if (empty($user_id) || empty($gc_id)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_pic', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin', 'gc_gag')->where('gc_id', $gc_id)->first();

        if (empty($group)) {
            return error('99998', '非法操作');
        }

        $group_uid = json_decode($group->gc_uid, true);
        if (!in_array($user_id, $group_uid)) {
            return error('99998', '非法操作');
        }
        $gags = [];
        if ($group->gc_gag) {
            $gags = json_decode($group->gc_gag, true);
        }
        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        array_unshift($admins, $group->user_id);

//        if(!in_array($user_id,$admins)) {
//            return error('99998','非法操作');
//        }

        $adminUsers = DB::table('users')->select('user_id', 'nickname', 'headimg')->whereIn('user_id', $admins)->orderByRaw("FIELD(user_id, " . implode(", ", $admins) . ")")->get();
        foreach ($adminUsers as $adminUser) {

            $adminUser->headimg = strpos_domain($adminUser->headimg);
            if (in_array($adminUser->user_id, $gags)) {
                $adminUser->is_gag = '1';
            } else {
                $adminUser->is_gag = '0';
            }
        }
        $diff = array_diff($group_uid, $admins);
        $other = array_values($diff);
        if ($other) {
            $users = DB::table('users')->select('user_id', 'nickname', 'headimg')->whereIn('user_id', $other)->orderByRaw("FIELD(user_id, " . implode(", ", $other) . ")")->get();
            foreach ($users as $user) {
                $user->headimg = strpos_domain($user->headimg);
                if (in_array($user->user_id, $gags)) {
                    $user->is_gag = '1';
                } else {
                    $user->is_gag = '0';
                }
            }
        } else {
            $users = [];
        }

        $base = array(
            'gc_id' => $gc_id,
            'master_id' => $group->user_id,
            'gc_status' => $group->gc_status,
        );

        $data = array(
            'base' => $base,
            'managers' => $adminUsers,
            'users' => $users
        );

        success($data);

    }

    /**
     * description:群内搜索用户
     * @author Harcourt
     * @date 2018/8/2
     */
    public function groupUserSearch(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $match = $request->input('match', 0);
        if (empty($user_id) || empty($gc_id) || empty($match)) {
            return error('00000', '参数不全');

        }
        $group = DB::table('group_chat')->select('user_id', 'gc_pic', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin', 'gc_gag')->where('gc_id', $gc_id)->first();

        if (empty($group)) {
            return error('99998', '非法操作');
        }

        $group_uid = json_decode($group->gc_uid, true);
        if (!in_array($user_id, $group_uid)) {
            return error('99998', '非法操作');
        }

        $admins = [];
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }
        array_unshift($admins, $group->user_id);

        if (!in_array($user_id, $admins)) {
            return error('99998', '非法操作');
        }

        $user = DB::table('users')->select('user_id', 'user_name', 'nickname', 'headimg')->where('user_name', $match)->orWhere('mobile_phone',$match)->whereIn('user_id', $group_uid)->first();
        if (empty($user)) {
            return error('10001', '用户不存在');
        }
        $gags = [];
        if ($group->gc_gag) {
            $gags = json_decode($group->gc_gag);
        }
        $is_gag = 0;
        if (in_array($user->user_id, $gags)) {
            $is_gag = 1;
        }
        $user->is_gag = $is_gag;

        $role = 3;
        $user->headimg = strpos_domain($user->headimg);
        if (in_array($user->user_id, $admins)) {
            $role = 2;
        }
        if ($user->user_id == $group->user_id) {
            $role = 1;
        }
        $user->role = $role;

        success($user);


    }

    /**
     * description:将用户设置为管理员或者将管理员设置成普通用户
     * @author Harcourt
     * @date 2018/8/2
     */
    public function dealManager(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $gc_id = $request->input('gc_id', 0);
        $other_uid = $request->input('other_uid', 0);
        if (empty($user_id) || empty($gc_id) || empty($other_uid)) {
            return error('00000', '参数不全');

        }
        if ($user_id == $other_uid) {
            return error('99998', '非法操作');
        }

        $group = DB::table('group_chat')->select('user_id', 'gc_pic', 'gc_status', 'gc_title', 'gc_uid', 'gc_admin')->where('gc_id', $gc_id)->first();

        if (empty($group) || $user_id != $group->user_id) {
            return error('99998', '非法操作');
        }

        $group_uid = json_decode($group->gc_uid, true);
        if (!in_array($other_uid, $group_uid)) {
            return error('99998', '非法操作');
        }

        $admins = [];
        $serverapi = new \ServerAPI();
        if ($group->gc_admin) {
            $admins = json_decode($group->gc_admin, true);
        }

        if (!in_array($other_uid, $admins)) {
            array_push($admins, $other_uid);
            $whitelistRet = $serverapi->groupUserBanWhitelistAdd($admins, $gc_id);
        } else {
            $admins = array_values(array_diff($admins, [$other_uid]));
            $whitelistRet = $serverapi->groupUserBanWhitelistRollback($other_uid, $gc_id);

        }
        $update_data = array(
            'gc_admin' => json_encode($admins)
        );
        DB::table('group_chat')->where('gc_id', $gc_id)->update($update_data);

        success();


    }

    /**
     * description:通过用户/群 id获取头像和昵称
     * @author Harcourt
     * @date 2018/8/22
     */
    public function getUserOrGroup(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('type', 1);//1、用户2、群
        if (empty($id) || !in_array($type, [1, 2])) {
            return error('00000', '参数不全');

        }
        if ($type == 1) {
            $data = DB::table('users')->selectRaw('user_id as id,headimg as img,nickname as name')->where('user_id', $id)->first();
        } else {
            $data = DB::table('group_chat')->selectRaw('gc_id as id,gc_pic as img,gc_title as name,gc_uid as totalNum')->where('gc_id', $id)->first();
        }
        if (empty($data)) {
            return error('99998', '非法操作');
        }
        if($type == 1){
            $data->totalNum = 0;
        }else{
            $data->totalNum = count(json_decode($data->totalNum,true));
        }
        $data->img = strpos_domain($data->img);
        success($data);
    }


}










