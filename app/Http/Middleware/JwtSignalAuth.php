<?php

namespace App\Http\Middleware;

use App\Http\Models\SettingsModel;
use App\Http\Helpers\{H, HR, R};
use Closure;

use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class JwtSignalAuth extends BaseMiddleware
{
    public function handle($request, Closure $next)
    {
        $user_id = $request->get('user_id');
        $token = $request->get('token');
        //单点登录判断
        $base = SettingsModel::getSigConf('base');
        if (isset($base['user_unique']) && $base['user_unique'] == 1) {
            $redis_token = HR::signLoginGet($user_id);
            if (!empty($redis_token) && $token != $redis_token) {
                return response()->json(['msg' => '请重新登陆', 'code' => 50000], 200);
            }
        }
        return $next($request);
    }
}
