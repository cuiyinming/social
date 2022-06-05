<?php

namespace App\Http\Middleware;

use App\Http\Models\Logs\LogActionModel;
use App\Http\Models\System\SysMessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Helpers\{H, HR, R};
use App\Http\Models\SettingsModel;
use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class JwtAuth extends BaseMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $this->auth->setRequest($request)->getToken();
        if (!$token) {
            return $this->respond('tymon.jwt.absent', '登陆已过期', 50000);
        }
        try {
            $user = auth()->guard('api')->userOrFail();
        } catch (TokenExpiredException $e) {
            return $this->respond('tymon.jwt.expired', '登陆过期', 50000, [$e]);
        } catch (JWTException $e) {
            return $this->respond('tymon.jwt.invalid', '登陆过期啦', 50000, [$e]);
        } catch (TokenInvalidException $e) {
            return $this->respond('tymon.jwt.invalid', '登陆已经过期', 50000, [$e]);
        }
        if (!$user) {
            return $this->respond('tymon.jwt.user_not_found', '用户不存在', 50000);
        } else {
            if ($user->status != 1) {
                return $this->respond('', '当前账号已被封禁', 50008);
            }
            if (!is_null($user->unlock_time)) {
                return $this->respond('', '当前账号被临时冻结30分钟', 50008);
            }
        }
        //封禁手机处理
        if (!empty($user->mobile) && HR::existLockedMobile(H::decrypt($user->mobile)) == 1) {
            return $this->respond('', '暂不提供该手机的服务', 50007);
        }
        //中间追加资料完善情况
        $user_id = H::setPlatformId($user->platform_id);
        $request->attributes->add(['user_id' => $user_id]);
        $request->attributes->add(['token' => $token]);
        //在这里存下来行为记录
        $log = [
            'ip' => IP,
            'user_id' => $user_id,
            'path' => $request->path(),
            'coordinates' => COORDINATES,
            'params' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            'method' => $request->getMethod(),
            'channel' => CHANNEL,
            'coor' => COORD
        ];
        //LogActionModel::create($log);
        //在这里统计每日活跃统计
        HR::updateUniqueNum('counter', $user_id, 'daily-active-');
        return $next($request);
    }

    protected function respond($event, $error, $status, $payload = [])
    {
        $data = [
            'code' => $status,
            'msg' => $error,
            'data' => new \stdClass()
        ];
        $response = response()->json($data);
        return $response;
    }
}
