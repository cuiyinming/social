<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Requests\ForgetRequest;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\Request;
use App\Http\Models\Client\ClientUsersModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Helpers\{T, H};
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        if (!$request->has('mobile')) {
            return $this->jsonExit(208, '手机号码未传递');
        }
        if (!$request->has('pwd')) {
            return $this->jsonExit(209, '登陆密码未传递');
        }
        $credentials = $request->only('mobile', 'pwd');
        $user = ClientUsersModel::select(['id', 'name', 'mobile', 'amount', 'head_img', 'last_ip', 'last_login', 'status'])->where('mobile', $credentials['mobile'])->first();
        if ($user->status !== 1) {
            return $this->jsonExit(210, '您的账号已经被锁定，请稍后重试或联系管理员');
        }
        //存在的情况
        if ($user->unlock_time > date('Y-m-d H:i:s')) {
            return $this->jsonExit(211, trans('您的账号临时锁定30分钟，请稍后再试'));
        }
        if (!$user) {
            return $this->jsonExit(201, '账号信息不存在');
        } else {
            try {
                $login = [];
                if ($user->name == $credentials['mobile']) {
                    $login['name'] = $credentials['mobile'];
                } else {
                    $login['mobile'] = $credentials['mobile'];
                }
                $login['password'] = $credentials['pwd'];

                if (!$token = auth()->guard('client')->attempt($login)) {
                    $user->login_try_time += 1;
                    if ($user->login_try_time > 5) {
                        $user->unlock_time = date('Y-m-d H:i:s', time() + 60 * 30);
                        $user->login_try_time = 0;
                    }
                    $user->save();
                    LoginErrModel::gainLog($user->id, $user->mobile, 403, '账号或密码错误', 2);
                    return $this->jsonExit(403, '账号或密码错误');
                }
                //查询上次登录时间
                //更新用户登录信息
                $userInfo = ClientUsersModel::find($user->id);
                $userInfo->last_ip = IP;
                $userInfo->last_login = CORE_TIME;
                $userInfo->last_city = CITY;
                $userInfo->last_os = T::get_os();
                $userInfo->last_broswer = T::get_broswer();
                //计算注册时间
                $userInfo->reg_day = T::diffBetweenTwoDays($userInfo->register_time, CORE_TIME);
                $userInfo->save();
            } catch (JWTException $e) {
                LoginErrModel::gainLog($user->id, $user->mobile, 500, '登陆token创建失败', 2);
                return $this->jsonExit(500, '创建token失败');
            }
        }
        LoginLogModel::gainLoginLog($user->id, '正常登陆', 2, T::get_os(), T::get_broswer());   //type 默认0为用户 2代理
        //卸载id
        unset($user->id);
        return $this->jsonExit(200, 'OK', ['user' => $user, 'token' => $token]);
    }


    public function register(Request $request)
    {
        $data = $request->all();
        $check_rs = LogSmsModel::checkCode($data['mobile'], $data['check_code']);
        if (!$check_rs) {
            LoginErrModel::gainLog(0, $data['mobile'], 402, '验证码错误', 2);
            return $this->jsonExit(402, '验证码错误');
        }
        //查询用户是否已经注册
        if (ClientUsersModel::where('mobile', $data['mobile'])->first()) {
            return $this->jsonExit(204, '该用户已经注册，请直接登陆');
        }
        $client_invite = $request->input('invite', '');
        if (!empty($client_invite)) {
            $client = ClientUsersModel::where('invite_code', $client_invite)->first();
            if (!$client) {
                return $this->jsonExit(406, '邀请码错误');
            } else {
                $client->invited_counter += 1;
                $client->save();
            }
        }
        $nick = LibNickModel::orderBy(DB::Raw('RAND()'))->first()->nick;
        $avatar = config('app.cdn_url') . '/ava/' . rand(1, 2) . '-' . rand(1, 75) . '.jpg';
        $user = ClientUsersModel::create([
            'name' => $nick,
            'mobile' => $data['mobile'],
            'password' => Hash::make($data['pwd']),
            'amount' => 0,
            'invited' => $client_invite,
            'invite_code' => T::get_os(),
            'register_ip' => IP,
            'register_time' => CORE_TIME,
            'head_img' => $avatar,
            'last_ip' => IP,
            'last_login' => CORE_TIME,
            'last_city' => CITY,
            'last_os' => T::get_os(),
            'last_broswer' => T::get_broswer(),
        ]);
        $user->invite_code = T::inviteCodeGet($user->id);
        $user->save();
        $token = auth()->guard('client')->tokenById($user->id);
        LoginLogModel::gainLoginLog($user->id, '注册登陆', 2, T::get_os(), T::get_broswer());
        unset($user->id);
        return $this->jsonExit(200, 'OK', ['user' => $user, 'token' => $token]);
    }

    public function checkCode(Request $request)
    {
        $data = $request->all();
        if (strlen($data['code']) != 4) {
            return $this->jsonExit(403, '验证码错误');
        }
        if (!T::isMobile($data['mobile'])) {
            return $this->jsonExit(405, '手机号码不正确');
        }
        $check_rs = LogSmsModel::checkCode($data['mobile'], $data['code'], 'find_password');
        if (!$check_rs) {
            LoginErrModel::gainLog(0, $data['mobile'], 402, '找回密码验证码错误', 2);
            return $this->jsonExit(402, '验证码错误');
        }
        //查询用户是否已经注册
        if (!$userInfo = ClientUsersModel::where('mobile', $data['mobile'])->first()) {
            return $this->jsonExit(204, '用户不存在');
        }
        if ($userInfo->status != 1) {
            return $this->jsonExit(205, '该用户已锁定，请联系管理员');
        }
        return $this->jsonExit(200, 'OK');
    }

    public function forget(Request $request)
    {
        $data = $request->all();
        if (strlen($data['code']) != 4) {
            return $this->jsonExit(403, '验证码错误');
        }
        if (!T::isMobile($data['mobile'])) {
            return $this->jsonExit(405, '手机号码不正确');
        }
        $check_rs = LogSmsModel::checkCode($data['mobile'], $data['code'], 'find_password');
        if (!$check_rs) {
            LoginErrModel::gainLog(0, $data['mobile'], 402, '找回密码验证码错误', 2);
            return $this->jsonExit(402, '验证码错误');
        }
        //查询用户是否已经注册
        if (!$userInfo = ClientUsersModel::where('mobile', $data['mobile'])->first()) {
            return $this->jsonExit(204, '用户不存在');
        }
        if ($userInfo->status != 1) {
            return $this->jsonExit(205, '该用户已锁定，请联系管理员');
        }
        //密码一致性
        if (trim($data['password']) != trim($data['cpassword'])) {
            return $this->jsonExit(209, '两次密码不一致');
        }
        $userInfo->password = Hash::make($data['password']);
        $userInfo->last_ip = IP;
        $userInfo->last_login = CORE_TIME;
        $userInfo->last_city = H::Ip2City(IP);
        $userInfo->last_os = T::get_os();
        $userInfo->last_broswer = T::get_broswer();
        $userInfo->save();
        LogUserModel::gainLog($userInfo->id, 'find_password', '******', '******', '找回密码操作', 2);
        LoginLogModel::gainLoginLog($userInfo->id, '找回密码登陆', 2, T::get_os(), T::get_broswer());
        unset($userInfo->id);
        return $this->jsonExit(200, 'Ok');
    }

    public function logout()
    {
        auth()->guard('client')->logout();
        auth()->guard('client')->invalidate(true);
        return $this->jsonExit(200, 'OK');
    }


    public function getcheckcode(Request $request, $type)
    {
        $mobile = $request->input('mobile');
        if (!T::isMobile($mobile)) {
            return $this->jsonExit(201, '请填写正确的手机号码');
        }
        //判断指定时间发送数量
        $max_setting = config('common.max_sms_time');
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

}
