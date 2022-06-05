<?php

namespace App\Http\Middleware;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Crypt\Rsa;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Models\Users\UsersModel;
use App\Http\Requests\Request;
use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Http\Models\StatisticLogModel;
use Illuminate\Support\Facades\Session;

class StaticLog
{
    public function handle($request, Closure $next)
    {
        header("origin-server:" . env('SERVER_ID', 0));
        $method = $request->getRealMethod();
        if ($method == 'head') return $this->respond('暂无服务A', 201);
        /*------*这里检查黑名单---S------*/
        //针对负载均衡专门添加
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_REMOTEIP'] ?? $request->ip();
//        $server_addr = $_SERVER['SERVER_ADDR'] ?? '';
//        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
//        $str = 'realip => ' . $ip . ' ++ || request =>  ' . $request->ip() . ' ++ || SERVER_ADDR:' . $server_addr . ' ++ || REMOTE_ADDR:' . $remote_addr;
//        file_put_contents('/tmp/xxxxx.log', $str . PHP_EOL, 8);
        if ($ip == '127.0.0.1') {
            $ip = H::getClientIP();
        }

        if (H::checkBlackIp($ip)) return $this->respond('暂无服务I', 201);
        /*------这里检查黑名单----E------*/
        if (!defined('VER')) define('VER', $request->input('v', ''));
        /*------*这里检查封禁设备---S------*/
        $device = $request->header('device') ? $request->header('device') : $request->header('device_id');
        $device = $device ?: '';
        if (!defined('DEVICE')) define('DEVICE', strtolower(trim($device)));
        if (!empty($device) && HR::existLockedDevice($device) == 1) {
            return $this->respond('暂无服务D', 201);
        }
        if (!defined('IP')) define('IP', $ip);
        $coor = true;
        $coordinates = $request->header('coordinates');
        if (!defined('COORD')) define('COORD', $coordinates);
        if (empty($coordinates) || $coordinates == '31.23,121.47') {
            $coor = false;
            //处理在首次还没有获取到经纬度就打开的情况下的定位问题
            $coordinates = (new BaiduCloud())->getPointByIp(IP);
        }
        if (!defined('COOR')) define('COOR', $coor);
        if (!defined('COORDINATES')) define('COORDINATES', $coordinates); //实时坐标
        //定义platform
        $platform = $request->header('platform') ?: 'all';
        if (!defined('PLATFORM')) define('PLATFORM', strtolower(trim($platform)));
        $channel = $request->header('channel') ? $request->header('channel') : 'WEB';
        if (!defined('CHANNEL')) define('CHANNEL', strtolower($channel));
        if (!defined('CITY')) define('CITY', H::Ip2City(IP));

        $url = $request->path();
        $agent = $request->header('User-Agent');
        $requestData = $request->input();
        $userToken = md5($ip . $agent);
        $log = [
            'device' => $device,
            'channel' => $channel,
            'url' => $url,
            'ip' => $ip,
            'agent' => $agent,
            'token' => $userToken,
            'coordinates' => COORDINATES,
            'coor' => COORD,
            'data' => http_build_query($requestData)
        ];
        StatisticLogModel::create($log);
        /******S**在这里设置语言包*********/
        $lang = trim($request->header('lang'));
        if (!empty($lang) && in_array($lang, Config::get('app.locales'))) {
            $lang = 'cn';
            App::setLocale($lang);
        } else {
            //如果没有就默认系统设置
            App::setLocale(Config::get('app.locale'));
        }
        $now_version = intval(str_replace('.', '', VER));
        if ($now_version > 10 && $now_version <= 225) {
            return $this->respond('您的版本过低，已停止该版本服务，请及时在各大应用市场更新最新版本', 205);
        }
        /******E**在这里设置语言包*********/
        //在这里统计每日活跃统计
        HR::updateUniqueNum('counter', DEVICE, 'daily-device-');
        HR::updateUniqueNum('version-' . VER, DEVICE, 'daily-ver-');
        return $next($request);
    }


    protected function respond($error, $status)
    {
        $data = [
            'code' => $status,
            'msg' => $error,
            'data' => new \stdClass()
        ];
        return response()->json($data);
    }
}
