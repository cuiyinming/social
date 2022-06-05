<?php

namespace App\Http\Controllers\Client;

use App\Http\Helpers\T;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Models\Client\ClientLogModel;
use App\Http\Models\Client\ClientUsersModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\DB;

class DbCollectController extends Controller
{
    public function dataUpload(Request $request)
    {
        $invited = $request->input('id');
        $city = $request->input('city', '');
        $ip = $request->input('ip', '127.0.0.1');
        $event = $request->input('event', '');
        $land = $request->input('land', '');
        $hour = date('H');
        if ($hour >= 0 && $hour < 2) {
            $hour = '0-2点';
        }
        if ($hour >= 2 && $hour < 4) {
            $hour = '2-4点';
        }
        if ($hour >= 4 && $hour < 6) {
            $hour = '4-6点';
        }
        if ($hour >= 6 && $hour < 8) {
            $hour = '6-8点';
        }
        if ($hour >= 8 && $hour < 10) {
            $hour = '8-10点';
        }
        if ($hour >= 10 && $hour < 12) {
            $hour = '10-12点';
        }
        if ($hour >= 12 && $hour < 14) {
            $hour = '12-14点';
        }
        if ($hour >= 14 && $hour < 16) {
            $hour = '14-16点';
        }
        if ($hour >= 16 && $hour < 18) {
            $hour = '16-18点';
        }
        if ($hour >= 18 && $hour < 20) {
            $hour = '18-20点';
        }
        if ($hour >= 20 && $hour < 22) {
            $hour = '20-22点';
        }
        if ($hour >= 22 && $hour < 24) {
            $hour = '22-24点';
        }

        if (empty($invited)) {
            return die('ok');
        }
        $city = explode('省', $city)[0];
        $city = str_replace('市', '', str_replace('省', '', $city));
        $os = T::get_os_sys();
        ClientLogModel::create([
            'invited' => $invited,
            'ip' => $ip,
            'event' => $event,
            'ua' => T::get_broswer_sys(),
            'os' => $os,
            'city' => $city,
            'land' => $land,
            'hour' => $hour,
        ]);
        //钉钉通知
        $notice = false;
        if ($notice === true) {
            $dingUrl = 'https://oapi.dingtalk.com/robot/send?access_token=8d0ada17eaaf1f2d9f97e1e093a53701e315872bffc6cfafa66cc62837317ff7';
            $str = '推广点击：【'
                . date('m-d H:i:s') . '】 / 邀请码：'
                . $invited . ' / 城市：' . $city
                . ' / IP:' . $ip
                . ' / OS:' . $os;
            (new DingTalk($dingUrl))->sendTextMessage($str);
        }
        die('ok');
    }


}
