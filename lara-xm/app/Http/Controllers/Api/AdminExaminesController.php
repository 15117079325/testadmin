<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminExaminesController extends Controller
{
    //
    //提交审核
    public function getExamCard(Request $request)
    {
        try {
            $this->validate($request, [
            ]);
            $cardInfo = DB::table('examines_card')->where(['status' => 0])->get()->toArray();
            $returnArr = [];
            foreach ($cardInfo as $k => $v) {
                $returnArr[$k]['id'] = $v->id;
                $returnArr[$k]['card_no'] = $v->card_no;
                $returnArr[$k]['truename'] = $v->truename;
                $returnArr[$k]['money'] = $v->money;
                $returnArr[$k]['balance'] = $v->balance;
                $returnArr[$k]['count'] = $v->count;
                $returnArr[$k]['phone'] = $v->phone;
                $returnArr[$k]['status'] = $v->status;
                $returnArr[$k]['card_name'] = $v->card_name;
                $returnArr[$k]['card_img'] = $v->card_img;
                $returnArr[$k]['logo_img'] = $v->logo_img;
                $returnArr[$k]['is_emergency'] = $v->is_emergency;
                $returnArr[$k]['emergency'] = $v->is_emergency == 1 ? '是' : '否';
                $returnArr[$k]['status'] = $v->status;
                $returnArr[$k]['type'] = $v->status == 0 ? '正常' : '不可用';
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
