<?php

namespace App\Http\Libraries\Sms;

use App\Http\Helpers\H;
use Curl\Curl;

class RongIm
{
    private $curlBuilder;

    public function __construct()
    {
        $this->curlBuilder = new Curl();
        $this->curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
    }

    public function kickUser($content)
    {
        $imInfo = config('latrell-rcloud');
        $appKey = $imInfo['app_key']; // 开发者平台分配的 App Secret。
        $appSecret = $imInfo['app_secret'];
        $nonce = rand(10000, 99999); // 获取随机数。
        $timestamp = time() * 1000; // 获取时间戳（毫秒）。
        $signature = sha1($appSecret . $nonce . $timestamp);
        $header = [
            'Content-Type: application/x-www-form-urlencoded',
            'Timestamp: ' . $timestamp,
            'Host: api-cn.ronghub.com',
            'App-Key: ' . $appKey,
            'Nonce: ' . $nonce,
            'Signature: ' . $signature,
        ];
        $base_url = 'http://rtcapi-cn.ronghub.com/rtc/user/kick.json';
        $this->curlBuilder->setOpt(CURLOPT_HTTPHEADER, $header);
        $res = $this->curlBuilder->post($base_url, json_encode($content));
        $res = H::object2array($res);
        return isset($res['code']) && $res['code'] == 200;
    }


    public function __destruct()
    {
        $this->curlBuilder->close();
    }

}
