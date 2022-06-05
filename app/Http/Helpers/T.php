<?php

namespace App\Http\Helpers;

use App\Http\Models\Client\ClientShortUrlModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Http\Libraries\Crypt\Encrypt;
use App\Http\Libraries\Crypt\Decrypt;
use Wuchuheng\QrMerge\QrMerge;

class T
{
    public static function get_broswer()
    {
        $sys = $_SERVER['HTTP_USER_AGENT'];  //获取用户代理字符串
        if (stripos($sys, "Firefox/") > 0) {
            preg_match("/Firefox\/([^;)]+)+/i", $sys, $b);
            $exp[0] = "Firefox";
            $exp[1] = $b[1];  //获取火狐浏览器的版本号
        } elseif (stripos($sys, "Maxthon") > 0) {
            preg_match("/Maxthon\/([\d\.]+)/", $sys, $aoyou);
            $exp[0] = "傲游";
            $exp[1] = $aoyou[1];
        } elseif (stripos($sys, "MSIE") > 0) {
            preg_match("/MSIE\s+([^;)]+)+/i", $sys, $ie);
            $exp[0] = "IE";
            $exp[1] = $ie[1];  //获取IE的版本号
        } elseif (stripos($sys, "OPR") > 0) {
            preg_match("/OPR\/([\d\.]+)/", $sys, $opera);
            $exp[0] = "Opera";
            $exp[1] = $opera[1];
        } elseif (stripos($sys, "Edge") > 0) {
            //win10 Edge浏览器 添加了chrome内核标记 在判断Chrome之前匹配
            preg_match("/Edge\/([\d\.]+)/", $sys, $Edge);
            $exp[0] = "Edge";
            $exp[1] = $Edge[1];
        } elseif (stripos($sys, "Chrome") > 0) {
            preg_match("/Chrome\/([\d\.]+)/", $sys, $google);
            $exp[0] = "Chrome";
            $exp[1] = $google[1];  //获取google chrome的版本号
        } elseif (stripos($sys, 'rv:') > 0 && stripos($sys, 'Gecko') > 0) {
            preg_match("/rv:([\d\.]+)/", $sys, $IE);
            $exp[0] = "IE";
            $exp[1] = $IE[1];
        } else {
            $exp[0] = "未知浏览器";
            $exp[1] = "";
        }
        return $exp[0] . '(' . $exp[1] . ')';
    }

    public static function get_os()
    {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $os = false;
        if (preg_match('/win/i', $agent) && strpos($agent, '95')) {
            $os = 'Windows 95';
        } else if (preg_match('/win 9x/i', $agent) && strpos($agent, '4.90')) {
            $os = 'Windows ME';
        } else if (preg_match('/win/i', $agent) && preg_match('/98/i', $agent)) {
            $os = 'Windows 98';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.0/i', $agent)) {
            $os = 'Windows Vista';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.1/i', $agent)) {
            $os = 'Windows 7';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.2/i', $agent)) {
            $os = 'Windows 8';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 10.0/i', $agent)) {
            $os = 'Windows 10';#添加win10判断
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 5.1/i', $agent)) {
            $os = 'Windows XP';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 5/i', $agent)) {
            $os = 'Windows 2000';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt/i', $agent)) {
            $os = 'Windows NT';
        } else if (preg_match('/win/i', $agent) && preg_match('/32/i', $agent)) {
            $os = 'Windows';
        } else if (preg_match('/linux/i', $agent)) {
            $os = 'Linux';
        } else if (preg_match('/unix/i', $agent)) {
            $os = 'Unix';
        } else if (preg_match('/sun/i', $agent) && preg_match('/os/i', $agent)) {
            $os = 'SunOS';
        } else if (preg_match('/ibm/i', $agent) && preg_match('/os/i', $agent)) {
            $os = 'IBM';
        } else if (preg_match('/Mac/i', $agent)) {
            $os = 'Mac';
        } else if (preg_match('/PowerPC/i', $agent)) {
            $os = 'PowerPC';
        } else if (preg_match('/AIX/i', $agent)) {
            $os = 'AIX';
        } else if (preg_match('/HPUX/i', $agent)) {
            $os = 'HPUX';
        } else if (preg_match('/NetBSD/i', $agent)) {
            $os = 'NetBSD';
        } else if (preg_match('/BSD/i', $agent)) {
            $os = 'BSD';
        } else if (preg_match('/OSF1/i', $agent)) {
            $os = 'OSF1';
        } else if (preg_match('/IRIX/i', $agent)) {
            $os = 'IRIX';
        } else if (preg_match('/FreeBSD/i', $agent)) {
            $os = 'FreeBSD';
        } else if (preg_match('/teleport/i', $agent)) {
            $os = 'teleport';
        } else if (preg_match('/flashget/i', $agent)) {
            $os = 'flashget';
        } else if (preg_match('/webzip/i', $agent)) {
            $os = 'webzip';
        } else if (preg_match('/offline/i', $agent)) {
            $os = 'offline';
        } else {
            $os = '未知操作系统';
        }
        return $os;
    }

    public static function get_os_sys()
    {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $os = 'other';
        if (preg_match('/win/i', $agent)) {
            $os = 'windows';
        } else if (preg_match('/linux/i', $agent) || preg_match('/unix/i', $agent) || preg_match('/android/i', $agent)) {
            $os = 'android';
        } else if (preg_match('/iphone/i', $agent) || preg_match('/ipad/i', $agent)) {
            $os = 'ios';
        } else if (preg_match('/Mac/i', $agent)) {
            $os = 'mac';
        }
        return $os;
    }

    public static function get_broswer_sys()
    {
        $bro = 'other';
        $sys = $_SERVER['HTTP_USER_AGENT'];  //获取用户代理字符串
        if (stripos($sys, "QQ") !== false) {
            $bro = "qq";  //QQ内置浏览器
        } elseif (stripos($sys, "Firefox/") > 0) {
            $bro = "browser";
        } elseif (stripos($sys, "Maxthon") > 0) {
            $bro = "browser";
        } elseif (stripos($sys, "MSIE") > 0 || (stripos($sys, 'rv:') > 0 && stripos($sys, 'Gecko') > 0)) {
            $bro = "browser";
        } elseif (stripos($sys, "OPR") > 0) {
            $bro = "browser";
        } elseif (stripos($sys, "Edge") > 0) {
            $bro = "browser";
        } elseif (stripos($sys, "Chrome") > 0) {
            $bro = "browser";
        } elseif (stripos($sys, 'MicroMessenger') !== false) {
            $bro = "wechat";
        }
        return $bro;
    }

    public static function diffBetweenTwoDays($day1, $day2)
    {
        $second1 = strtotime($day1);
        $second2 = strtotime($day2);

        if ($second1 < $second2) {
            $tmp = $second2;
            $second2 = $second1;
            $second1 = $tmp;
        }
        return ceil(($second1 - $second2) / 86400);
    }

    public static function isMobile($mobile)
    {
        $chars = "/^13[0-9]{1}[0-9]{8}$|15[0-9]{1}[0-9]{8}$|14[0-9]{1}[0-9]{8}$|16[0-9]{1}[0-9]{8}$|18[0-9]{1}[0-9]{8}$|19[0-9]{1}[0-9]{8}$|16[0-9]{1}[0-9]{8}$|17[0-9]{1}[0-9]{8}$/";
        if (preg_match($chars, $mobile)) {
            return true;
        }
        return false;
    }

    //生成短连接
    public static function shortUrl($url, $custom = "", $format = "json")
    {
        return self::shortSelfUrl($url);
        $api_url = "http://y2e.cn/api/?key=9avL4Inp7nSq";
        $api_url .= "&url=" . urlencode(filter_var($url, FILTER_SANITIZE_URL));
        if (!empty($custom)) {
            $api_url .= "&custom=" . strip_tags($custom);
        }
        $curl = curl_init();
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_URL => $api_url]);
        $Response = curl_exec($curl);
        curl_close($curl);
        if ($format == "text") {
            $Ar = json_decode($Response, TRUE);
            if ($Ar["error"]) {
                return $Ar["msg"];
            } else {
                return $Ar["short"];
            }
        } else {
            return $Response;
        }
    }


    public static function shortSelfUrl($url)
    {
        $base_url = 'http://d.hfriend.cn/';
        $desc = '推广URL';
        //$url = urlencode($url);
        $shortCode = H::randstr(6, 'ALL');
        $shortModel = ClientShortUrlModel::where([['name', $desc], ['base_url', $url]])->first();
        if (!$shortModel) {
            $shortModel = ClientShortUrlModel::create([
                'name' => $desc,
                'base_url' => $url,
                'short_code' => $shortCode,
                'short_url' => $base_url . $shortCode,
            ]);
        }
        return $shortModel->short_url;
    }

    public static function mergePic($pic, $text)
    {
        $QrMerge = new QrMerge();
        $background = storage_path('app/public/pro/') . $pic . '.jpg';
        if ($pic >= 0 && $pic <= 225) {
            $mapArr = explode('_', config('promote.pro')[$pic]);
            $x = $mapArr[0];
            $y = $mapArr[1];
            $size = $mapArr[2];
        } else {
            $x = 296;
            $y = 640;
            $size = 180;
        }
        $binary = $QrMerge->generateQr($background, $x, $y, $size, $text);
        return $QrMerge->toBase64($binary, 'jpg');
    }

    public static function inviteCodeGet($id): int
    {
        return 4820 + $id;
    }
}
