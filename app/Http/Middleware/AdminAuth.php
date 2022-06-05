<?php

namespace App\Http\Middleware;

use App\Http\Helpers\HR;
use App\Http\Models\SettingsModel;
use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\UserNotDefinedException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
class AdminAuth extends BaseMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = auth()->guard('admin')->userOrFail();
        } catch (TokenExpiredException $e) {
            return response()->json(['msg' => '登陆已过期', 'code' => 4001], 200);
        } catch (JWTException $e) {
            return response()->json(['msg' => '登陆已失效', 'code' => 4000], 200);
        } catch (TokenInvalidException $e) {
            return response()->json(['msg' => '登录信息不可用', 'code' => 4000], 200);
        } catch (UserNotDefinedException $e) {
            return response()->json(['msg' => '用户未定义', 'code' => 4000], 200);
        }
        if (!$user) {
            return response()->json(['msg' => '登陆已失效', 'code' => 4000], 200);
        } else {
            if ($user->status != 1) {
                return response()->json(['msg' => '账号异常，请检查', 'code' => 4000], 200);
            }
            if (!is_null($user->unlock_time)) {
                return response()->json(['msg' => '账号被临时锁定，请联系管理员处理', 'code' => 50008], 200);
            }
        }
        //中间追加资料完善情况
        $mid_params = ['user_name' => $user->username];
        $request->attributes->add($mid_params);//添加参数

        return $next($request);
    }
}
