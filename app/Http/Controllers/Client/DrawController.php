<?php

namespace App\Http\Controllers\Client;


use App\Http\Models\Client\ClientDrawModel;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogUserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthClientController;
use Image;
use JWTAuth;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\DB;

class DrawController extends AuthClientController
{
    /**
     * 获取用户的开票信息
     */
    public function invoiceInfo(Request $request)
    {
        $invoice = ClientUsersModel::select(['id', 'name', 'mobile', 'wechat_qr', 'alipay_qr'])->where('status', 1)->first($this->uid);
        if (!$invoice) {
            return $this->jsonExit(201, '开票信息不存在');
        }
        //计算可开票金额
        $invoice_amount = ClientUsersModel::select(DB::Raw("amount,amount_frozen,amount_process,(`amount` - `amount_frozen`) as amount_can"))->find($this->uid);
        $invoice->invoice = $invoice_amount;
        return $this->jsonExit(200, 'OK', $invoice);
    }

    public function askInvoice(Request $request)
    {
        $amount = $request->input('ask_invoice', 0);
        $account = $request->input('account', '');
        if ($amount < 10) {
            return $this->jsonExit(201, '申请提现金额不能小于10元');
        }
        if ($amount > 10000) {
            return $this->jsonExit(208, '申请提现金额不能大于10000元');
        }
        if (empty($account)) {
            return $this->jsonExit(208, '提现账号不能为空');
        }
        $userInfo = ClientUsersModel::find($this->uid);
        if (empty($userInfo->wechat_qr) && empty($userInfo->alipay_qr)) {
            return $this->jsonExit(204, '申请体现的收款信息未完善，请完善后处理');
        }
        if (empty($userInfo->name)) {
            return $this->jsonExit(208, '提现人姓名不能为空');
        }
        if ($amount > ($userInfo->amount - $userInfo->amount_frozen)) {
            return $this->jsonExit(202, '申请提现金额不能大于最大可提现金额');
        }
        //查询如果有正在开票的，不能再次申请
        $lastInvoice = ClientDrawModel::getLastRow($this->uid);
        if ($lastInvoice && $lastInvoice->status == 0) {
            return $this->jsonExit(203, '您有尚未处理完的提现申请，请等待平台处理完后再申请');
        }
        try {
            DB::beginTransaction();
            //创建申请
            $InvoiceLog = ClientDrawModel::create([
                'user_id' => $this->uid,
                'mobile' => $userInfo->mobile,
                'name' => $userInfo->name,
                'account' => $account,
                'amount' => $amount
            ]);
            //添加用户操作日志
            LogUserModel::gainLog($this->uid, '申请提现', '未申请提现', '申请提现 ￥' . $amount, '申请提现LOG记录ID' . $InvoiceLog->id);
            //减少可申请金额
            $before = $after = $userInfo->amount;
            $userInfo->amount_frozen += $amount;
            $userInfo->save();
            //记录提现变动
            $desc = date('m-d H:i') . ' 申请提现 ' . $amount . ' 元';
            $remark = date('m-d H:i') . ' 申请提现 ' . $amount . ' 元 [当前冻结：' . $userInfo->amount_frozen . '元]';
            LogBalanceModel::gainLogBalance($this->uid, $before, 0, $after, 'draw', $desc, $remark, 0, 'client_balance');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return $this->jsonExit(207, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK', $InvoiceLog);
    }


    public function getInvoiceList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 1);
        $data = ClientDrawModel::getPageRow($this->uid, $page, $size);
        return $this->jsonExit(200, 'OK', $data);
    }


    public function getInfoById(Request $request, $id)
    {
        $info = ClientDrawModel::getRow($id);
        if (!$info) {
            return $this->jsonExit(201, '提现信息不存在');
        }
        if ($info->user_id != $this->uid) {
            return $this->jsonExit(202, '您无权查看此提现信息');
        }
        $client = ClientUsersModel::getUserInfo($info->user_id);
        $info->account = $client->{$info->account};
        return $this->jsonExit(200, 'OK', $info);
    }
}
