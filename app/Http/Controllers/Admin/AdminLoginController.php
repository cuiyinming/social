<?php

namespace App\Http\Controllers\Admin;

use App\Http\Libraries\Sms\MsgSend;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Helpers\{H, HR};
use App\Http\Controllers\Controller;
use App\Http\Models\Admin\AdminModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\SettingsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AdminLoginController extends Controller
{

    public function authenticate(Request $request)
    {
        if (!$request->has('username')) {
            return $this->jsonExit(208, '登陆用户名不能为空');
        }
        if (!$request->has('password')) {
            return $this->jsonExit(209, '登陆密码不能为空');
        }
        if (!$request->has('safepassword')) {
            return $this->jsonExit(209, '安全码不能为空');
        }
        $safepassword = $request->input('safepassword');
        $credentials = $request->only('username', 'password');
        $user = AdminModel::select(['id', 'username', 'last_login', 'unlock_time', 'status'])->where([['username', $credentials['username']], ['delete', 0]])->first();
        if (!$user) {
            LoginErrModel::gainLog(0, $credentials['username'], 201, '账号信息不存在', 1);
            return $this->jsonExit(201, '账号信息不存在');
        } else {
            if ($user->status !== 1) {
                return $this->jsonExit(210, '您的账号已经被锁定，请稍后重试或联系管理员');
            }
            if ($user->unlock_time > CORE_TIME) {
                return $this->jsonExit(211, '您的密码错误超过5次数，账号临时锁定30分钟，请稍后重试');
            }
            //登陆IP管理
            $bases = SettingsModel::getSigConf('base');
            if ($bases['ip_limit'] == 1 && !empty($bases['ip_list'])) {
                $ipArrs = [];
                $ipArr = explode('|', $bases['ip_list']);
                if (count($ipArr) > 0) {
                    foreach ($ipArr as $ip) {
                        if (empty($ip)) continue;
                        $ipArrs[] = trim($ip);
                    }
                }
                if (!in_array(IP, $ipArrs)) {
                    return $this->jsonExit(212, '您的登陆ip不在登陆IP白名单，无法登陆');
                }
            }
            try {
                if (!$token = auth()->guard('admin')->attempt($credentials)) {
                    LoginErrModel::gainLog($user->id, '', 403, '账号或密码错误', 1);
                    $user->login_try_time = $user->login_try_time + 1;
                    if ($user->login_try_time > 5) {
                        $user->unlock_time = date('Y-m-d H:i:s', time() + 60 * 30);
                        $user->login_try_time = 0;
                    }
                    $user->save();
                    return $this->jsonExit(403, '账号或密码错误');
                }
                //更新用户登录信息
                $userInfo = AdminModel::find($user->id);
                if (!$userInfo) {
                    return $this->jsonExit(405, '用户不存在');
                }
                //异常登陆短信
                if (isset($bases['login_sms_notice']) && $bases['login_sms_notice'] == 1 && isset($bases['admin_notice_mobile']) && empty($bases['admin_notice_mobile'])) {
                    if ($userInfo->last_ip != IP) {
                        $notice_mobile = $bases['admin_notice_mobile'];
                        if (!empty($notice_mobile)) {
                            LogSmsModel::sendMsg($notice_mobile, 'login_notice');
                        }
                    }
                }
                //校验安全码
                if (!Hash::check($safepassword, $userInfo->safepassword)) {
                    return $this->jsonExit(206, '安全码不正确');
                }
                $userInfo->last_login = CORE_TIME;
                $userInfo->last_city = CITY;
                $userInfo->save();
                $base = SettingsModel::getSigConf('base');
                #在这里进行单点登陆的存储工作[单点登录]
                if (isset($base['admin_unique']) && $base['admin_unique'] == 1) {
                    HR::SignLogin($userInfo->username, $token,'admin_sign_login');
                }
            } catch (JWTException $e) {
                LoginErrModel::gainLog($user->id, $user->mobile, 500, '创建token失败', 1);
                return $this->jsonExit(500, '创建token失败');
            }
        }
        LoginLogModel::gainLoginLog($user->id, '正常登陆', 1);
        //卸载id
        unset($user->id);
        return $this->jsonExit(200, 'OK', ['user' => $user, 'token' => $token]);
    }


    public function refreshToken()
    {
        $token = auth()->guard('admin')->refresh();
        return $this->jsonExit(200, 'OK', compact('token'));
    }

    public function loginOut()
    {
        auth()->guard('admin')->logout();
        auth()->guard('admin')->invalidate(true);
        return $this->jsonExit(200, '退出成功');
    }
}
