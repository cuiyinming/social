<?php

namespace App\Http\Middleware;

use App\Http\Models\SettingsModel;
use App\Http\Helpers\{H, HR, R};
use Closure;

use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;


class SignalAuth extends BaseMiddleware
{
    public function handle($request, Closure $next)
    {
        $user_name = $request->get('user_name');
        $token = $this->auth->setRequest($request)->getToken();
        if (!$token) {
            return response()->json(['msg' => trans('login.login_expired'), 'code' => 4000], 200);
        }
        //单点登录判断
        $base = SettingsModel::getSigConf('base');
        if (isset($base['admin_unique']) && $base['admin_unique'] == 1) {
            $redis_token = HR::SignLoginGet($user_name, 'admin_sign_login');
            if (!empty($redis_token) && $token != $redis_token) {
                return response()->json(['msg' => trans('login.logged_another_device'), 'code' => 50004], 200);
            }
        }
        return $next($request);
    }
}
