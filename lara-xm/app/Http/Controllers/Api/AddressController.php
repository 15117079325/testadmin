<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    /**
     * description:添加收货地址
     * @author Harcourt
     * @date 2018/8/1
     */
    public function add(Request $request)
    {
        $user_id = $request->input('user_id',0);
        $contact = $request->input('contact');
        $area = $request->input('area');//浙江省-杭州市-西湖区
        $detail = $request->input('detail');
        $mobile = $request->input('mobile');
        $default = $request->input('default',0);//0、非默认1、默认
        if(empty($user_id) || empty($contact) || empty($area) || empty($detail) || empty($mobile)){
            return error('00000', '参数不全');
        }

        $areaArr = explode('-',$area);
        if(count($areaArr) != 3){
            return error('00000', '参数不全1');
        }

        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }

        $insert_data = array(
            'user_id'=>$user_id,
            'consignee'=>$contact,
//            'province'=>$areaArr[0],
//            'city'=>$areaArr[1],
//            'district'=>$areaArr[2],
            'area'=>$area,
            'address'=>$detail,
            'mobile'=>$mobile,
            'address_default'=>$default
        );

        DB::beginTransaction();

        $insert_id = DB::table('user_address')->insertGetId($insert_data,'address_id');

        if(empty($insert_id)){
            DB::rollback();
            return error('99999','操作失败');
        }

        if($default == 1){
            $awhere = [
                ['address_default',1],
                ['user_id',$user_id]
            ];
            $address = DB::table('user_address')->where($awhere)->first();
            if($address && $address->address_id != $insert_id){
                $aff_row = DB::table('user_address')->where('address_id',$address->address_id)->update(['address_default'=>0]);
                if($aff_row){
                    DB::commit();
                    return success();
                }else{
                    DB::rollback();
                    return error('99999','操作失败');
                }
            }else{
                DB::commit();
                return success();
            }
        }else{
            DB::commit();
            return success();
        }


    }
    /**
     * description:编辑收货地址
     * @author Harcourt
     * @date 2018/8/1
     */
    public function edit(Request $request)
    {
        $id = $request->input('id',0);
        $user_id = $request->input('user_id',0);
        $contact = $request->input('contact');
        $area = $request->input('area');
        $detail = $request->input('detail');
        $mobile = $request->input('mobile');
        $default = $request->input('default',0);//0、非默认1、默认

        if(empty($id) || empty($user_id) || empty($contact) || empty($area) || empty($detail) || empty($mobile)){
            return error('00000', '参数不全');
        }
        $areaArr = explode('-',$area);
        if(count($areaArr) != 3){
            return error('00000', '参数不全');
        }
        $address = DB::table('user_address')->where('address_id',$id)->first();

        if(empty($address) || $address->user_id != $user_id){
            return error('99998','非法操作');
        }

        $verification = new \Verification();

        if (!$verification->fun_phone($mobile)) {
            return error('01000', '请输入合法的手机号码');
        }
        $update_data = array(
            'consignee'=>$contact,
//            'province'=>$areaArr[0],
//            'city'=>$areaArr[1],
//            'district'=>$areaArr[2],
            'area' => $area,
            'address'=>$detail,
            'mobile'=>$mobile,
            'address_default'=>$default
        );
        $flag = true;
        DB::beginTransaction();

        if($default == 1 && $address->address_default != 1){

            $where = [
                ['address_default',1],
                ['user_id',$user_id]
            ];
            $old_address = DB::table('user_address')->where($where)->first();

            if($old_address && $old_address->address_id != $id){
                $aff_row = DB::table('user_address')->where('address_id',$old_address->address_id)->update(['address_default'=>0]);
                if(empty($aff_row)){
                    $flag = false;
                }
            }
        }
        DB::table('user_address')->where('address_id',$id)->update($update_data);

        if($flag){
            DB::commit();
            success();
        }else{
            DB::rollback();
            error('99999','操作失败');
        }


    }
    /**
     * description:收货地址列表
     * @author Harcourt
     * @date 2018/8/1
     */
    public function lists(Request $request)
    {
        $user_id = $request->input('user_id',0);
        if(empty($user_id)){
            return error('00000', '参数不全');
        }

        $where = [
            ['user_id',$user_id],
            ['type',0]
        ];

        $list = DB::table('user_address')->select('address_id','consignee','area','address','mobile','address_default')->where($where)->orderBy('address_default','desc')->orderBy('address_id','desc')->get();

        success($list);
    }

    /**
     * description:删除收货地址
     * @author Harcourt
     * @date 2018/8/1
     */
    public function delete(Request $request)
    {
        $user_id = $request->input('user_id',0);
        $id = $request->input('id',0);

        if(empty($user_id) || empty($id)){
            return error('00000', '参数不全');
        }

        $address = DB::table('user_address')->where('address_id',$id)->first();

        if(empty($address) || $address->user_id != $user_id){
            return error('99998','非法操作');
        }

        $aff_row = DB::table('user_address')->where('address_id',$id)->delete();

        if($aff_row){
            success();
        }else{
            error('99999','操作失败');
        }
    }


    /**
     * description:设置默认收货地址
     * @author Harcourt
     * @date 2018/8/1
     */
    public function setDefault(Request $request)
    {
        $user_id = $request->input('user_id',0);
        $id = $request->input('id',0);

        if(empty($user_id) || empty($id)){
            return error('00000', '参数不全');
        }

        $address = DB::table('user_address')->where('address_id',$id)->first();

        if(empty($address) || $address->user_id != $user_id){
            return error('99998','非法操作');
        }

        if($address->address_default == 1){
            //取消默认地址
            DB::table('user_address')->where('address_id',$id)->update(['address_default'=> 0]);
            return success();
        }

        $where = [
            ['address_default',1],
            ['user_id',$user_id]
        ];
        $old_address = DB::table('user_address')->where($where)->first();

        DB::beginTransaction();
        if($old_address){
            DB::table('user_address')->where('address_id',$old_address->address_id)->update(['address_default'=> 0]);
        }
        DB::table('user_address')->where('address_id',$id)->update(['address_default'=>1]);
        DB::commit();
        success();

    }

    /**
     * description:获取默认收货地址
     * @author Harcourt
     * @date 2018/8/1
     */
    public function getDefault(Request $request)
    {
        $user_id = $request->input('user_id',0);

        if(empty($user_id) ){
            return error('00000', '参数不全');
        }

        $where = [
            ['address_default',1],
            ['user_id',$user_id]
        ];

        $address = DB::table('user_address')->select('address_id','consignee','area','address','mobile','address_default')->where($where)->first();

        if(empty($address) ){
            return error('40001','未设置默认收货地址');
        }

        success($address);
    }




}
