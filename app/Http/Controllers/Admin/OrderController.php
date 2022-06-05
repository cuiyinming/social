<?php

namespace App\Http\Controllers\Admin;


use App\Components\ESearch\ESearch;
use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogDrawModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Support\Facades\Artisan;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\CommonModel;
use App\Http\Models\Payment\PaymentOrderModel;
use App\Http\Models\SettingsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OrderController extends AuthAdmController
{
    public function orderList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type');
        $order = $request->input('order');
        $q = $request->input('q');
        $status = $request->input('status');
        $data = OrderModel::getDataByPage($page, $size, $q, $status, $type, $order);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function orderListPayment(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $data = PaymentOrderModel::getDataByPage($page, $size, $q, $status);
        return $this->jsonExit(200, 'OK', $data);
    }

    //补单
    public function orderRetry(Request $request)
    {
        $id = $request->input('id', 0);
        $model = OrderModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '订单不存在');
        }
        if (empty($model->receipt)) {
            return $this->jsonExit(202, '票据不能为空');
        }
        //开始补单
        try {
            //验证票据
            $applePay = new ApplePay();
            $res = $applePay->validateApplePay($model->receipt);
            if ($model->type == 0) {
                $dres = CommonModel::storeReceipt($model, $res);
                //更新订单信息
                $model->paid_at = CORE_TIME;
                $model->transaction_id = $dres['last']['transaction_id'] ?? '';
                $model->original_transaction_id = $dres['last']['original_transaction_id'] ?? '';
                $model->amount = $dres['productPrice'] ?? 0;
                $model->status = 1;
                $model->save();
            }
            if ($model->type == 1) {
                CommonModel::storeInnerPay($res, $model);
            }

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        $model->retry = $model->retry + 1;
        $model->save();
        return $this->jsonExit(200, 'OK');
    }

    public function usersVipSync(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if ($user_id > 0) {
            Artisan::call('vip:daily', ['type' => 0, 'user_id' => $user_id]);
        } else {
            Artisan::call('vip:daily', ['type' => 0]);
        }
        return $this->jsonExit(200, 'OK');
    }

    public function orderSyncCheck(Request $request)
    {
        Artisan::call('process:tenMin', ['type' => 3]);
        return $this->jsonExit(200, 'OK');
    }

    public function orderDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $order = OrderModel::where('id', $id)->first();
        if (!$order) {
            return $this->jsonExit(201, '记录不存在');
        }
        $order->delete();
        return $this->jsonExit(200, 'OK');
    }

    public function drawList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $data = LogDrawModel::getDataByPage($page, $size, $q, $status);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function drawUpdate(Request $request)
    {
        //val => 1 同意 2拒绝
        $id = $request->input('id', 0);
        $act = $request->input('act', '');
        $val = $request->input('value', '');
        $data = LogDrawModel::where('id', $id)->first();
        if (!$data) {
            return $this->jsonExit(202, '记录不存在');
        }
        if ($data->status == 1) {
            return $this->jsonExit(201, '订单已经处理过，无需重复操作');
        }
        if ($act == 'status') {
            try {
                DB::beginTransaction();
                $data->status = $val;
                $data->process_at = CORE_TIME;
                $data->save();
                $user = UsersModel::where('id', $data->user_id)->first();
                if ($user) {
                    //同意处理完毕
                    if ($val == 1) {
                        $user->jifen_frozen -= $data->jifen;
                        $user->jifen_draw_grand += $data->jifen;
                        $user->save();
                        //添加变动记录
                        $before = $after = $user->jifen;
                        $desc = '提现成功心钻：' . $data->jifen . ' 颗';
                        $remark = '提现成功心钻：' . $data->jifen . ' 颗';
                        LogBalanceModel::gainLogBalance($data->user_id, $before, 0, $after, 'draw_done', $desc, $remark, 0, 'log_jifen', $data->sn);
//                        //添加分润逻辑 【先统计提现成功的金额是不是大于30元】
//                        $draw_amount = LogDrawModel::where([['status', 1], ['user_id', $data->user_id]])->sum('amount');
//                        if ($draw_amount >= 30 && $user->invited_benefited == 0) {
//                            $invite_reward = config('settings.invite_reward');//开始分润
//                            $invite_diamond = $invite_reward * config('settings.points_rate');//折算为钻石
//                            //添加分润记录
//                            $father = UsersModel::where([['status', 1], ['uinvite_code', $user->invited]])->first();
//                            if ($father) {
//                                //分成
//                                $father_before = $father->jifen;
//                                $father->jifen += $invite_diamond;
//                                $father->save();
//                                $desc = '邀请的好友（' . $user->id . '）提现任务完成，奖励' . $invite_diamond . '心钻' . '(折合现金' . $invite_reward . '元)，可提现';
//                                $remark = '邀请的好友（' . $user->id . '）提现任务完成，奖励' . $invite_diamond . '心钻' . '(折合现金' . $invite_reward . '元)，可提现';
//                                LogBalanceModel::gainLogBalance($father->id, $father_before, $invite_diamond, $father->jifen, 'invite_draw_benefit', $desc, $remark, 0, 'log_jifen');
//                                //更新分成状态
//                                $user->invited_benefited = 1;
//                                $user->save();
//                            }
//                        }
                    }
                    //拒绝
                    if ($val == 2) {
                        $before = $user->jifen;
                        $user->jifen_frozen -= $data->jifen;
                        $user->jifen += $data->jifen;
                        $user->save();
                        //添加变动记录
                        $desc = '提现被拒绝，余额原路退回心钻：' . $data->jifen . ' 颗';
                        $remark = '提现拒绝：' . $data->jifen . ' 颗';
                        LogBalanceModel::gainLogBalance($data->user_id, $before, $data->jifen, $user->jifen, 'draw_refuse', $desc, $remark, 0, 'log_jifen', $data->sn);
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                MessageModel::gainLog($e, __FILE__, __LINE__);
                return $this->jsonExit(202, '服务错误');
            }
        }
        return $this->jsonExit(200, 'OK');
    }

}
