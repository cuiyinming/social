<?php

namespace App\Http\Controllers\Admin;

use App\Http\Models\Admin\AdminModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Models\UserLogModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Admin\AuthAdmController;

class AdminSettingController extends AuthAdmController
{
    public function modifyAllPwd(Request $request)
    {
        if (!$old = $request->input('old_pwd', '')) {
            return $this->jsonExit(201, '原始密码不能为空');
        }
        if (!$new = $request->input('new_pwd', '')) {
            return $this->jsonExit(202, '新密码不能为空');
        }
        if (!$confirm = $request->input('confirm_pwd', '')) {
            return $this->jsonExit(203, '确认密码不能为空');
        }
        if (!$new_safe_pwd = $request->input('new_safe_pwd', '')) {
            return $this->jsonExit(204, '新的安全码不能为空');
        }
        if (!$new_safe_confirm_pwd = $request->input('new_safe_confirm_pwd', '')) {
            return $this->jsonExit(205, '确认安全码不能为空');
        }

        if ($new != $confirm) {
            return $this->jsonExit(206, '两次密码不一致');
        }
        if ($new_safe_pwd != $new_safe_confirm_pwd) {
            return $this->jsonExit(207, '两次安全码不一致');
        }
        if (!$userInfo = AdminModel::find($this->uid)) {
            return $this->jsonExit(208, '用户信息不存在');
        }
        if (!Hash::check($old, $userInfo->password)) {
            return $this->jsonExit(209, '原始密码不正确');
        }
        $userInfo->password = Hash::make(trim($new));
        $userInfo->safepassword = Hash::make(trim($new_safe_pwd));
        $userInfo->save();
        //记录日志
        LogUserModel::gainLog($this->uid, '修改密码', '******', '******', $old, 1, 2);
        return $this->jsonExit(200, 'OK');
    }

}
