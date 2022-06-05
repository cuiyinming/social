<?php

namespace App\Http\Middleware;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Crypt\Rsa;
use Closure;
use Illuminate\Support\Facades\Log;

class SignAuth
{
    public function handle($request, Closure $next)
    {
        $params = $request->all();
        if (!isset($params['t']) || !isset($params['v']) || !isset($params['sign'])) {
            return $this->respond('请求必选参数缺失', 40000);
        }
        //定义加密字串的秘钥
        //$crypt = $request->header('crypt');
        //if ($crypt && VER >= 1.92) {
        //    $crypt = Rsa::privDecrypt($crypt, 1);//解密出加密字串
        //    define('CRYPT', $crypt); //实时秘钥
        //}

        $arg = $signData = '';
        if (!empty($params)) {
            ksort($params);
            reset($params);
            foreach ($params as $key => $val) {
                //空值不参与签名
                if ($val == '' || $key == 'sign' || is_null($val) || is_array($val)) {
                    continue;
                }
                $arg .= ($key . '=' . $val . '&');
            }
            $arg = $arg . 'key=$AwP!wRRT$gJ/q.X';
            $signData = strtolower(md5($arg));
        }
        //空数据的话不使用签名
        if ($arg != '' && !isset($params['sign'])) {
            return $this->respond(trans('login.sign_missing'), 40001);
        }
//        //验证签名正确性
//        if (isset($params['sign']) && $signData != $params['sign']) {
//            return $this->respond(trans('login.sign_err'), 40002);
//        }
//        //验证签名是不是过期
//        if ($arg != '' && isset($params['t']) && ($params['t'] - intval(microtime(1) * 1000)) > 300000) {
//            return $this->respond(trans('login.sign_expired'), 40003);
//        }
        //卸载无效参数

        $request->offsetUnset('sign');
        $request->offsetUnset('v');
        $request->offsetUnset('t');
        return $next($request);
    }

    protected function respond($error, $status, $payload = [])
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
