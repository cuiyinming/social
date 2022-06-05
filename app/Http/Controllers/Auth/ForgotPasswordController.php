<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Logs\LogTokenModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Requests\ForgetRequest;
use Illuminate\Support\Facades\Hash;
use JWTAuth;

class ForgotPasswordController extends Controller
{
    //忘记密码信息
    public function forget(ForgetRequest $request)
    {
        $mobile = $request->input('mobile');
        $code = $request->input('code');
        $password = $request->input('password');
        $cpassword = $request->input('cpassword');
        $token_id = $request->input('token_id', 0);
        $fast = $request->input('channel', '');
        if ($password != $cpassword) {
            return $this->jsonExit(401, '两次密码不一致');
        }
        if (!H::checkPhoneNum($mobile)) {
            return $this->jsonExit(402, '手机号码错误');
        }
        $check_rs = LogSmsModel::checkCode($mobile, $code, 'find_password');
        if (!$check_rs) {
            LoginErrModel::gainLog(0, $code, 403, '验证码错误');
            return $this->jsonExit(403, '验证码错误');
        }
        $encryptMobile = H::encrypt($mobile);
        //查询用户是否已经注册
        $column = [
            'sweet_coin', 'nick', 'avatar', 'sex', 'status', 'birthday', 'constellation', 'invited',
            'id', 'platform_id', 'uinvite_code', 'rong_token', 'live_location'
        ];
        $user = UsersModel::select($column)->where('mobile', $encryptMobile)->first();
        if (!$user) {
            return $this->jsonExit(404, '用户不存在');
        }
        //如果存在但是状态不正常
        if ($user && $user->status != 1) {
            return $this->jsonExit(405, '该账号已封禁，请联系客服处理');
        }
        $salt = H::randstr(6, 'ALL');
        $updateData = [
            'password' => Hash::make($password . $salt),
            'salt' => $salt,
            'password_set' => 1,
        ];
        UsersModel::where('mobile', $encryptMobile)->update($updateData);
        //操作登陆
        //最后记录下redis的活跃时间
        HR::updateActiveTime($user->id);
        HR::updateActiveCoordinate($user->id);
        $user->online = 1;
        $user->save();
        //快捷登陆设置
        if (in_array($fast, ['apple', 'wechat', 'qq', 'fast']) && $token_id > 0) {
            $logToken = LogTokenModel::where([['status', 1], ['id', $token_id]])->first();
            if ($logToken && $logToken->user_id > 0) {
                return $this->jsonExit(209, '该快捷登陆已经绑定了其他账号，请更换');
            }
            $logToken->user_id = $user->id;
            $logToken->save();
        }
        //还原手机号
        $user->mobile = $mobile;
        $user->user_id = $user->id;
        unset($user->id);
        /**   新的产生token 的方式  S**/
        $loginData = [];
        $loginData['mobile'] = $encryptMobile;
        $loginData['password'] = $password . $salt;
        $token = JWTAuth::attempt($loginData);
        //单点登录
        $base = SettingsModel::getSigConf('base');
        if (isset($base['user_unique']) && $base['user_unique'] == 1) {
            HR::signLoginDel($user->id);
            HR::signLogin($user->id, $token);
        }
        $profile = UsersProfileModel::where('user_id', $user->user_id)->first();
        $user->base_str = CITY . ' | ' . ($user->sex == 1 ? '女' : '男') . '•' . H::getAgeByBirthday($user->birthday);
        if ($profile->stature != 0 && !empty($profile->stature)) {
            $user->base_str .= ' | ' . $profile->stature;
        }
        if ($profile->profession) {
            $user->base_str .= ' | ' . $profile->profession;
        }

        return $this->jsonExit(200, 'OK', ['user' => $user, 'token' => $token, 'password_set' => 1]);
    }
}
