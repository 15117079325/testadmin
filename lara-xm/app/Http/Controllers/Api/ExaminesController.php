<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExaminesController extends Controller
{
    public function __construct()
    {
        $this->middleware('userLoginValidate');
    }

    public function getUserMoney(Request $request)
    {
        try {
            $this->validate($request, [
                'token' => 'required',
                'user_id' => 'required',
            ]);
            $input = $request->input();
            $userInfo = DB::table('users')->select('user_id', 'wallet_balance', 'wallet_time')->where(['user_id' => $input['user_id'], 'is_wallet' => 0])->first();
            if ($userInfo->wallet_time != 0) {
                $userInfo->wallet_time = date("Y-m-d H:i:s", $userInfo->wallet_time);
            }
            $userInfo->custom_time = date("Y-m-d H:i:s");
            return successCode($userInfo);

        } catch (ValidationException $exception) {
            $msg = array_column($exception->errors(), '0');
            return errorcode($msg[0]);
        } catch (\Exception $exception) {
            return errorcode('接口请求失败');
        }
        //wallet_balance
    }

    //返回银行卡信息
    public function getCard(Request $request)
    {
        try {
            $this->validate($request, [
                'token' => 'required',
            ]);


            $cardInfo = DB::table('examines_card')->where('balance', '>', '0')->where(['status' => 0, 'is_emergency' => 0])->get();

            if (empty($cardInfo)) {
                $cardInfo = DB::table('examines_card')->where(['status' => 0, 'is_emergency' => 1])->get();
            } else {
                $cardInfo = $cardInfo->toArray();

            }
            $returnRes = $cardInfo[array_rand($cardInfo, 1)];
            $returnRes->logo_img = IMAGE_PATH . $returnRes->logo_img;
            $returnRes->card_img = IMAGE_PATH . $returnRes->card_img;
            return successCode($returnRes);

        } catch (ValidationException $exception) {
            $msg = array_column($exception->errors(), '0');
            return errorcode($msg[0]);
        } catch (\Exception $exception) {
            return errorcode('接口请求失败');
        }
    }

    //提交审核
    public function optionCard(Request $request)
    {
        try {
            $this->validate($request, [
                'token' => 'required',
                'card_id' => 'required',
                'card_no' => 'required',
                'money' => 'required',
                'user_id' => 'required',
                'image_info' => 'required',
            ]);
            $input = $request->input();
            $insertData = [];
            $insertData['card_id'] = $input['card_id'];
            $insertData['exam_no'] = "HD" . uniqid();
            $insertData['card_no'] = $input['card_no'];
            $insertData['money'] = $input['money'];
            $insertData['user_id'] = $input['user_id'];
            $insertData['image_info'] = str_replace(IMAGE_OSS_PATH, '', $input['image_info']);
            $updateData['id'] = $input['card_id'];
            $updateData['money'] = $input['money'];
            $cardArr = DB::table('examines_card')->where(['card_no' => $insertData['card_no']])->first();
            $cardUpdate = [];
            if ($cardArr->balance - $insertData['money'] < 0 && $cardArr->is_emergency != 1) {
                $cardUpdate['status'] = 1;
                $cardUpdate['balance'] = 0;
            } else {
                $cardUpdate['status'] = 0;
                $cardUpdate['balance'] = $cardArr->balance - $insertData['money'];
            }
            //获取配置信息
            $distriButions = DB::table('master_config')->get()->toArray();
            $distriBution = array_column($distriButions, null, 'code');
            $max_time = $distriBution['verify_max_time']->value;
            DB::beginTransaction();
            try {
                DB::update('UPDATE xm_examines_card SET balance=?,status=? WHERE id=?', [$cardUpdate['balance'], $cardUpdate['status'], $insertData['card_id']]);
                DB::table('examines_info')->insert($insertData);
                DB::commit();
                return successCode(['money' => $updateData['money'], 'maxtime' => date("Y-m-d H:i:s", strtotime("+ $max_time day"))]);
            } catch (\Exception $e) {
                DB::rollback();
                return errorcode('接口请求失败');
            }
        } catch (ValidationException $exception) {
            $msg = array_column($exception->errors(), '0');
            return errorcode($msg[0]);
        } catch (\Exception $exception) {
            return errorcode('接口请求失败');
        }
    }

    //返回订单信息
    public function listexamines(Request $request)
    {
        try {
            $this->validate($request, [
                'token' => 'required',
                'user_id' => 'required',
            ]);

            $input = $request->input();
            $cardInfo = DB::table('examines_info')->where(['user_id' => $input['user_id']])->where('status', '!=', '-1')->get()->toArray();
            $returnArr = [];
            foreach ($cardInfo as $k => $v) {
                $returnArr[$k]['user_id'] = $v->user_id;
                $returnArr[$k]['card_no'] = $v->card_no;
                $returnArr[$k]['image_info'] = IMAGE_PATH . $v->image_info;
                $returnArr[$k]['money'] = $v->money;
                $returnArr[$k]['exam_no'] = $v->exam_no;
                $returnArr[$k]['type'] = $v->status == 0 ? '审核通过' : '审核中';
                $returnArr[$k]['status'] = $v->status;
            }
            return successCode($returnArr);
        } catch (ValidationException $exception) {
            $msg = array_column($exception->errors(), '0');
            return errorcode($msg[0]);
        } catch (\Exception $exception) {
            return errorcode('接口请求失败');
        }
    }
}
