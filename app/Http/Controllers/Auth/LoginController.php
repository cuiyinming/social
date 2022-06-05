<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Logs\LogTokenModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use JWTAuth;

class LoginController extends Controller
{
    public function authenticate(Request $request)
    {
        //file_put_contents('/tmp/header.log', print_r($request->all(), 1) . PHP_EOL, FILE_APPEND);
        if (!$request->has('mobile') || !$request->has('password')) {
            return $this->jsonExit(201, '账号或密码不能为空');
        }
        $token_id = $request->input('token_id', 0);
        $fast = $request->input('channel', '');
        $credentials = $request->only('mobile', 'password');
        $encryptMobile = H::encrypt($credentials['mobile']);
        $column = [
            'sweet_coin', 'nick', 'mobile', 'avatar', 'sex', 'salt', 'live_time_latest', 'live_coordinates',
            'online', 'last_ip', 'last_location', 'last_login', 'last_coordinates', 'login_try_time', 'unlock_time',
            'status', 'birthday', 'constellation', 'invited', 'id', 'platform_id', 'uinvite_code', 'rong_token'
        ];
        $user = UsersModel::select($column)->where('mobile', $encryptMobile)->first();
        if (!$user) {
            //不存在的情况[注册用户]
            LoginErrModel::gainLog(0, $credentials['mobile'], 201, '账号或密码错误');
            return $this->jsonExit(201, trans('账号或密码错误'));
        } else {
            //存在的情况
            if ($user->status !== 1) {
                return $this->jsonExit(210, trans('您的账号已被封禁，如有疑问请联系管理员'));
            }
            if ($user->unlock_time > date('Y-m-d H:i:s')) {
                return $this->jsonExit(211, trans('您的账号临时锁定30分钟，请稍后再试'));
            }

            //获取城市
            $city = H::getCityByCoor();
            try {
                $loginData = [];
                $loginData['mobile'] = $encryptMobile;
                $loginData['password'] = $credentials['password'] . $user->salt;
                if (!$token = JWTAuth::attempt($loginData)) {
                    $user->login_try_time += 1;
                    if ($user->login_try_time > 5) {
                        $user->unlock_time = date('Y-m-d H:i:s', time() + 60 * 30);
                        $user->login_try_time = 0;
                    }
                    $user->save();
                    LoginErrModel::gainLog($user->id, $user->mobile, 403, '账号或密码错误');
                    return $this->jsonExit(403, '账号或密码错误');
                }
                //查询上次登录时间 0 是用户
                $lastModel = LoginLogModel::where([['type', 0], ['user_id', $user->id], ['remark', '正常登陆']])->orderBy('id', 'desc')->first();
                if ($lastModel) {
                    $user->unlock_time = null;
                    $user->login_try_time = 0;
                    $user->last_ip = $lastModel->ip;
                    $user->last_location = $lastModel->last_city;
                    $user->last_login = $lastModel->login_time;
                    $user->last_coordinates = $lastModel->coordinates;
                }
                //更新活跃时间
                $user->online = 1;
                $user->invited = is_null($user->invited) ? '' : $user->invited;
                //最后活跃时间
                $user->live_location = $city;
                $user->live_time_latest = CORE_TIME;
                //登陆强制更新实时坐标
                $user->live_coordinates = COORDINATES;

                $user->save();
                //登陆成功后进行快捷账号的绑定
                if (in_array($fast, ['apple', 'wechat', 'qq', 'fast']) && $token_id > 0) {
                    $logToken = LogTokenModel::where([['status', 1], ['id', $token_id]])->first();
                    if ($logToken && $logToken->user_id > 0) {
                        return $this->jsonExit(209, '该快捷登陆已经绑定了其他账号，请更换');
                    }
                    $logToken->user_id = $user->id;
                    $logToken->save();
                }
            } catch (JWTException $e) {
                LoginErrModel::gainLog($user->id, $user->mobile, 500, '登陆token创建失败');
                return $this->jsonExit(500, '登陆token创建失败');
            }
        }
        //单点登录
        $base = SettingsModel::getSigConf('base');
        if (isset($base['user_unique']) && $base['user_unique'] == 1) {
            HR::signLogin($user->id, $token);
        }
        LoginLogModel::gainLoginLog($user->id, '正常登陆');   //type 默认0为用户
        //最后记录下redis的活跃时间
        HR::updateActiveTime($user->id);
        HR::updateActiveCoordinate($user->id);
        $user->user_id = $user->id;
        //拼接用户的base_str
        $profile = UsersProfileModel::where('user_id', $user->user_id)->first();
        $user->base_str = $city . ' | ' . ($user->sex == 1 ? '女' : '男') . '•' . H::getAgeByBirthday($user->birthday);
        if ($profile->stature != 0 && !empty($profile->stature)) {
            $user->base_str .= ' | ' . $profile->stature;
        }
        if ($profile->profession) {
            $user->base_str .= ' | ' . $profile->profession;
        }
        unset($user->id);
        unset($user->salt);
        unset($user->login_try_time);
        unset($user->last_ip);
        unset($user->last_login);
        unset($user->last_location);
        unset($user->last_coordinates);

        unset($user->live_time_latest);
        unset($user->live_coordinates);

        unset($user->unlock_time);
        //还原手机号
        $user->mobile = $credentials['mobile'];

        return $this->jsonExit(200, 'OK', ['user' => $user, 'token' => $token, 'password_set' => 1]);
    }

}
