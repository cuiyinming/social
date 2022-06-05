<?php

namespace App\Http\Controllers\Client;

use App\Http\Helpers\T;
use App\Http\Models\Client\ClientBalanceModel;
use App\Http\Models\Client\ClientMessageModel;
use App\Http\Models\Client\ClientProfitModel;
use App\Http\Models\Client\ClientReportModel;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthClientController;
use JWTAuth;
use Image;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Hash;


class UcenterController extends AuthClientController
{
    public function userBaseInfo(Request $request)
    {
        $info = ClientUsersModel::getUserInfo($this->uid);
        if ($info) {
            unset($info->password);
            unset($info->remember_token);
        }
        //获取上次登录信息
        $lastInfo = LoginLogModel::getLastLoginInfo($this->uid, 2);
        $info->last_ip = $lastInfo ? $lastInfo['ip'] : '';
        $info->last_broswer = T::get_broswer();
        $info->last_city = $lastInfo ? $lastInfo['last_city'] : '';
        $info->last_os = T::get_os();
        $info->last_time = $lastInfo ? $lastInfo['created_at'] : '';
        //获取用户资料完善度
        $complete = ClientUsersModel::infoComplete($this->uid);
        $info->complete = $complete;
        //用户最新的3个app

        //查询用户是否有未读消息
        $message = ClientMessageModel::getOneUnread($this->uid, 0);
        //追加分成信息
        $rates = config('settings.client');
        $special_rate = config('settings.special_rate');
        foreach ($rates as $way => $rate) {
            $rate = isset($special_rate[$this->uid]) ? $special_rate[$this->uid] : $rate;
            foreach ($rate as $level => $rat) {
                foreach ($rat as $le => $ra) {
                    $rates[$way][$level][$le] = $ra * 100 . '%';
                }
            }
            //针对代理的分成逻辑删除 所以在这里注释掉了相应的逻辑
            //if ($way == 'user') {
            //} else {
            //    foreach ($rate as $level => $rat) {
            //        $rates[$way][$level] = $rat * 100 . '%';
            //    }
            //}
        }
        $rate_data['rate'] = $rates;
        $rate_data['level_info'] = [
            'user' => count($rates['user']['vip']) . ' 级',
            'client' => (isset($rates['client']) ? count($rates['client']) : 0) . ' 级',
        ];
        $_data = [
            'info' => $info,
            'message' => (bool)$message,
            'rate_rate' => $rate_data,
        ];
        return $this->jsonExit(200, 'OK', $_data);
    }

    public function getUserMessage(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $status = $request->input('status', '');
        $messages = ClientMessageModel::getUserMessage($this->uid, $status, $page, $size);
        return $this->jsonExit(200, 'OK', $messages);
    }

    public function userMessageRead(Request $request, $id = 0)
    {
        $message = ClientMessageModel::find($id);
        if (!$message) {
            return $this->jsonExit(201, '消息不存在');
        }
        $message->read = 1;
        $message->save();
        return $this->jsonExit(200, 'OK');
    }

    public function userMinBaseInfo(Request $request)
    {
        $col = $request->input('col', 'mobile');
        $info = ClientUsersModel::getUserInfo($this->uid);
        try {
            $val = $info->$col;
        } catch (\Exception $e) {
            $val = '';
        }
        $data = [
            $col => $val
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function getPageLog(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $loginLogs = LoginLogModel::getPageLog($page, $size, $this->uid, 2);
        return $this->jsonExit(200, 'OK', $loginLogs);
    }

    // 会员下级
    public function getClientUser(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $client_code = ClientUsersModel::getUserInfo($this->uid)->invite_code;
        $loginLogs = UsersModel::getClientUser($page, $size, $client_code);
        return $this->jsonExit(200, 'OK', $loginLogs);
    }

    //代理下级
    public function getClientAgent(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $client_code = ClientUsersModel::getUserInfo($this->uid)->invite_code;
        $loginLogs = ClientUsersModel::getClientAgent($page, $size, $client_code);
        return $this->jsonExit(200, 'OK', $loginLogs);
    }

    // 余额变动记录
    public function getBalancePageLog(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $loginLogs = ClientBalanceModel::getPageItems($page, $size, [], '', '', '', $this->uid);
        return $this->jsonExit(200, 'OK', $loginLogs);
    }

    //收益变动记录
    public function getProfitPageLog(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 11);
        $type = $request->input('type');
        $q = $request->input('q');
        $date = $request->input('dates', []);
        $loginLogs = ClientProfitModel::getPageItems($page, $size, $date, $type, $q, $this->uid);
        return $this->jsonExit(200, 'OK', $loginLogs);
    }

    public function getReportPageLog(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 11);
        $date = $request->input('dates', []);
        $loginLogs = ClientReportModel::getPageItems($page, $size, $date, $this->uid);
        return $this->jsonExit(200, 'OK', $loginLogs);
    }

    public function modifyLoginPwd(Request $request)
    {
        if (!$request->has('old_pwd')) {
            return $this->jsonExit(201, '原始密码不能为空');
        }
        if (!$request->has('new_pwd')) {
            return $this->jsonExit(202, '新密码不能为空');
        }
        if (!$request->has('confirm_pwd')) {
            return $this->jsonExit(203, '确认密码不能为空');
        }
        $old = $request->input('old_pwd', '');
        $new = $request->input('new_pwd', '');
        $confirm = $request->input('confirm_pwd', '');
        if ($new != $confirm) {
            return $this->jsonExit(204, '两次密码不一致');
        }
        if (!$userInfo = ClientUsersModel::find($this->uid)) {
            return $this->jsonExit(205, '用户信息不存在');
        }
        if (!Hash::check($old, $userInfo->password)) {
            return $this->jsonExit(206, '原始密码不正确');
        }
        $userInfo->password = Hash::make(trim($new));
        $userInfo->save();
        //记录日志
        LogUserModel::gainLog($this->uid, '修改密码', '******', '******', $old, 1, 2);
        return $this->jsonExit(200, 'OK');
    }

    public function baseInfoGet(Request $request)
    {
        $userinfo = ClientUsersModel::select(['qq', 'wechat', 'head_img', 'email', 'mobile', 'wechat_qr', 'alipay_qr', 'name'])->find($this->uid);
        $data = [
            'userinfo' => [
                'qq' => $userinfo->qq,
                'wechat' => $userinfo->wechat,
                'head_img' => $userinfo->head_img,
                'email' => $userinfo->email,
                'mobile' => $userinfo->mobile,
            ],
            'invoiceinfo' => [
                'name' => $userinfo->name,
                'wechat_qr' => $userinfo->wechat_qr,
                'alipay_qr' => $userinfo->alipay_qr,
            ],
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function profileSmsSend($type, Request $request)
    {
        //判断指定时间发送数量
        $max_setting = config('common.max_sms_time');
        $mobile = ClientUsersModel::getUserInfo($this->uid)->mobile;
        $has_send_time = LogSmsModel::geSmsNum($mobile, $type);
        if ($has_send_time >= $max_setting) {
            return $this->jsonExit(203, '您的操作过于频繁，请稍后重试');
        }
        $sendResult = LogSmsModel::sendMsg($mobile, $type);
        if ($sendResult) {
            return $this->jsonExit(200, 'OK');
        } else {
            return $this->jsonExit(202, '发送失败');
        }
    }


    public function invoiceSave(Request $request)
    {
        if (!$request->has('mcode')) {
            return $this->jsonExit(201, '验证码不能为空');
        }
        if (!$request->has('name')) {
            return $this->jsonExit(202, '收款人姓名不能为空');
        }
        if (!$request->has('wechat_qr')) {
            return $this->jsonExit(203, '微信收款码不能为空');
        }
        if (!$request->has('alipay_qr')) {
            return $this->jsonExit(204, '支付宝收款码不能为空');
        }
        $name = $request->input('name');
        $code = $request->input('mcode');
        $alipay_qr = $request->input('alipay_qr');
        $wechat_qr = $request->input('wechat_qr');
        if (empty($alipay_qr) && empty($wechat_qr)) {
            return $this->jsonExit(204, '支付宝和微信的收款码不能同时为空');
        }
        if (empty($name)) {
            return $this->jsonExit(202, '收款人姓名不能为空');
        }
        $mobile = ClientUsersModel::getUserInfo($this->uid)->mobile;
        $check_rs = LogSmsModel::checkCode($mobile, $code, 'profile_code');
        if (!$check_rs) {
            return $this->jsonExit(402, '验证码错误');
        }
        $invoice = ClientUsersModel::where('id', $this->uid)->update([
            'id' => $this->uid,
            'alipay_qr' => $alipay_qr,
            'wechat_qr' => $wechat_qr,
            'name' => $name,
        ]);
        return $this->jsonExit(200, 'OK', $invoice);
    }

    public function baseInfoSave(Request $request)
    {
        if (!$request->has('email')) {
            return $this->jsonExit(201, '用户邮箱未传递');
        }
        if (!$request->has('qq')) {
            return $this->jsonExit(202, 'qq联系方式未传递');
        }
        if (!$request->has('head_img')) {
            return $this->jsonExit(204, '头像未传递');
        }
        $data = $request->all();
        $userModel = ClientUsersModel::updateOrCreate(['id' => $this->uid], $data);
        return $this->jsonExit(200, 'OK', $userModel);
    }

    public function uploadImg(Request $request, $dir = 'appicon')
    {
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $name = date('YmdHis') . uniqid();
        $base_path = $dir . '/' . $name . '.' . $ext;
        //制作路径
        $img_path = storage_path() . '/app/public/' . $base_path;
        //接收文件裁剪并保存
        Image::make($file)->resize(300, 300)->save($img_path);
        $data = [
            'path' => $base_path,
            'name' => $name,
            'full_path' => H::Path($base_path)
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function changeHeadImg(Request $request)
    {
        $file = $request->file('head_img');
        $ext = $file->getClientOriginalExtension();
        $base_path = 'upload/headimgs/' . date('YmdHis') . uniqid() . '.' . $ext;
        //制作路径
        $img_path = storage_path() . '/app/public/' . $base_path;
        //接收文件裁剪并保存
        Image::make($file)->resize(200, 200)->save($img_path);
        //更新客户图像数据
        $userInfo = User::where('id', $this->user->id)->update(['head_img' => $base_path]);
        if ($userInfo) {
            return $this->jsonExit(200, 'OK',
                ['head_image' => H::Path($base_path)]);
        }
        return $this->jsonExit(401, '系统错误');
    }
}
