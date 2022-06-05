<?php

namespace App\Http\Middleware;

use App\Http\Helpers\H;
use Closure;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

class ClientAuth extends BaseMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $this->auth->setRequest($request)->getToken();
        if (!$token) {
            return $this->respond('tymon.jwt.absent', '登陆已失效', 4000);
        }
        try {
            $user = auth()->guard('client')->userOrFail();
            if (!$user) {
                return response()->json(['msg' => '登陆已失效', 'code' => 4000], 200);
            }
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['msg' => '登陆已过期', 'code' => 4000], 200);
        }
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
