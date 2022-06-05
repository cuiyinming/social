<?php

namespace App\Http\Helpers;

use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Libraries\Tools\IpLocation;
use App\Http\Models\MessageModel;
use App\Http\Models\System\BlackIpModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Http\Libraries\Crypt\Encrypt;
use App\Http\Libraries\Crypt\Decrypt;

class H
{

    public static function exchangeDate($datetime)
    {
        $timestmp = strtotime($datetime);
        $diff = time() - $timestmp;
        if ($diff > 0 && $diff <= 60) {
            return '几秒前';
        }
        if ($diff > 60 && $diff <= 3600) {
            return intval($diff / 60) . '分钟前';
        }
        if ($diff > 3600 && $diff <= 86400) {
            return intval($diff / 3600) . '小时前';
        }
        if ($diff > 86400 && $diff <= 2592000) {
            return intval($diff / 86400) . '天前';
        }
        if ($diff > 2592000 && $diff <= 31536000) {
            return intval($diff / 2592000) . '月前';
        }
        if ($diff > 31536000 && $diff <= 3153600000) {
            return intval($diff / 31536000) . '年前';
        }
        return '';
    }

    public static function exchangeNumStr($num)
    {
        if ($num < 100) return $num;
        if ($num >= 100) return round($num / 1000, 1) . 'K';
    }

    public static function timeStr($date)
    {
        if ($date >= date('Y-m-d 00:00:00')) {
            return '今日';
        }
        if ($date < date('Y-m-d 00:00:00') && $date >= date('Y-m-d 00:00:00', time() - 86400)) {
            return '昨日';
        }
        return '';
    }

    public static function exchangeDateStr($datetime)
    {
        $start_time = strtotime(date("Y-m-d", time()));
        $year_etime = strtotime(date('Y', time()) . '-12-31');
        $year_stime = strtotime(date('Y', time()) . '-01-01');
        $end_time = $start_time + 60 * 60 * 24;
        $last_time = $start_time - 60 * 60 * 24;
        $timestmp = strtotime($datetime); //创建时间戳
        $diff = time() - $timestmp;
        if ($diff < 10800) {
            return '刚刚';
        }
        if ($diff > 10800 && $diff < 57600) {
            return (int)($diff / 3600).'小时前';
        }
        if ($timestmp > $start_time && $timestmp <= $end_time) {
            return '今天.' . date('H:i', $timestmp);
        }
        if ($timestmp >= $last_time && $timestmp <= $start_time) {
            return '昨天.' . date('H:i', $timestmp);
        }
        if ($diff > 86400 && $diff <= 604800) { //7天前
            return intval($diff / 86400) . '天前';
        }
        if ($diff > 604800 && $timestmp <= $year_etime) {
            return date('m-d', $timestmp);
        }
        if ($timestmp <= $year_stime) {
            return date('y-m-d', $timestmp);
        }
        return '';
    }

    //获取一个月的开始和起始时间
    public static function getShiJianChuo($nian = 0, $yue = 0): array
    {
        if (empty($nian) || empty($yue)) {
            $now = time();
            $nian = date("Y", $now);
            $yue = date("m", $now);
        }
        $time['begin'] = mktime(0, 0, 0, $yue, 1, $nian);
        $time['end'] = mktime(23, 59, 59, ($yue + 1), 0, $nian);
        return $time;
    }

    public static function leftTime(): int
    {
        return 86400 - (time() + 8 * 3600) % 86400;
    }

    //对头像进行压缩处理
    public static function compressAva($path, $size = 'sm')
    {
        if (in_array($size, ['sm', 'mid', 'max']) && stripos($path, '!') === false) {
            $path = $path . '!' . $size;
        }
        if ($size == 'blur' && stripos($path, '!blur') === false) {
            $path = str_replace('!sm', '!blur', $path);
            $path = str_replace('!mid', '!blur', $path);
            $path = str_replace('!max', '!blur', $path);
        }
        if ($size == 'return_avatar') {
            $path = str_replace('!sm', '', $path);
        }
        return $path;
    }

    //还原原始的oss 文件路径
    public static function ossPath($path, $img = 1)
    {
        $path = str_replace('!sm', '', $path);
        $path = str_replace('!mid', '', $path);
        $path = str_replace('!max', '', $path);
        return $path;
    }

    // 获取完整路径
    public static function path($path)
    {
        $host = config('app.url');
        //如果路径中有阿里云就直接返回原始路径
        if (stripos($path, 'aliyuncs') !== false || stripos($path, 'static') !== false) {
            return $path;
        }
        return $host . '/storage/' . $path;
    }

    //根据生日获取星座
    public static function getConstellationByBirthday($birthday)
    {
        //传入$birthday格式如：2018-05-06
        $month = intval(substr($birthday, 5, 2));
        $day = intval(substr($birthday, 8, 2));
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) return NULL;
        $signs = [['20' => '水瓶座'], ['19' => '双鱼座'], ['21' => '白羊座'], ['20' => '金牛座'], ['21' => '双子座'], ['22' => '巨蟹座'], ['23' => '狮子座'], ['23' => '处女座'], ['23' => '天秤座'], ['24' => '天蝎座'], ['22' => '射手座'], ['22' => '摩羯座']];
        foreach ($signs[$month - 1] as $start => $name) {
            if ($day < $start) {
                $arrs = $signs[($month - 2 < 0) ? 11 : $month - 2];
                $name = array_values($arrs)[0];
            }
        }
        return $name;
    }

    //获取年龄根据生日
    public static function getAgeByBirthday($birthday)
    {
        if (empty($birthday)) return 18;
        if (!self::checkDateTime($birthday)) return 18;
        list($year, $month, $day) = explode("-", $birthday);
        $year_diff = date("Y") - $year;
        $month_diff = date("m") - $month;
        $day_diff = date("d") - $day;
        if ($day_diff < 0 || $month_diff < 0)
            $year_diff--;
        return $year_diff + 1;
    }

    public static function checkDateTime($date)
    {
        return date('Y-m-d', strtotime($date)) == $date;
    }

    //递归创建文件夹
    public static function mkdirs($dir)
    {
        return is_dir($dir) or (self::mkdirs(dirname($dir)) and mkdir($dir, 0755));
    }


    //对称加解密
    public static function encryption($encrypt = "", $key = "wP!wRRTgJ/q", $iv = 'fdakieli;njajdj1')
    {
        return openssl_encrypt($encrypt, 'AES-128-CBC', $key, 0, $iv);
    }

    //解密钥匙
    public static function deciphering($encrypt = '', $key = "wP!wRRTgJ/q", $iv = 'fdakieli;njajdj1')
    {
        return openssl_decrypt($encrypt, 'AES-128-CBC', $key, 0, $iv);
    }

    //加解密
    public static function encrypt($str)
    {
        $str = trim($str);
        $encrypt = new Encrypt();
        return $encrypt->encrypt($str, '', 1, 2);
    }

    public static function decrypt($str)
    {
        $str = trim($str);
        if (stripos($str, '!!&c$%') === false) {
            return $str;
        }
//        try {
        $decrypt = new Decrypt();
        return $decrypt->decrypt($str, '');
//        } catch (\Exception $e) {
//            MessageModel::gainLog($e, __FILE__, __LINE__, $str);
//            return '';
//        }

    }


    //生成平台的id
    public static function getPlatformId($id = 0): int
    {
        return 212801320 + $id;
    }

    public static function setPlatformId($id = 0): int
    {
        return $id - 212801320;
    }

    //生成推荐码
    public static function createCode()
    {
        $code = 'abcdefghijklmnopqrstuvwxyz';
        $rand = $code[rand(0, 25)] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
        for ($a = md5($rand, true),
             $s = '0123456789abcdefghijklmnopqrstuvwxyz',
             $d = '',
             $f = 0;
             $f < 8;
             $g = ord($a[$f]),
             $d .= $s[($g ^ ord($a[$f + 8])) - $g & 0x1F],
             $f++
        ) ;
        return strtoupper($d);
    }

    //判断手机号是否合法
    public static function checkPhoneNum($phone)
    {
        if (strlen(trim($phone)) != 11) return false;
        $check = '/^(1(([35789][0-9])|(47)))\d{8}$/';
        if (preg_match($check, $phone)) {
            return true;
        } else {
            return false;
        }
    }

    public static function Ip2City($ip = '190.206.19.21')
    {
        return self::_getCityByIP($ip);
    }

    //获取ip转城市
    private static function _getCityByIP($ip = '')
    {
        $location = '';
        $path = base_path('resources/data/UTFWry.dat');
        $IpLocation = new IpLocation($path);
        $client = $IpLocation->getlocation($ip);
        if (isset($client['country'])) {
            $location = $client['country'];
        }
        return $location;
    }

    //获取随机字符串
    public static function randstr($len = 6, $format = 'NUMBER')
    {
        switch ($format) {
            case 'CHAR':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
                break;
            case 'UPPER':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'LOWER':
                $chars = 'abcdefghijklmnopqrstuvwxyz';
                break;
            case 'NUPPER':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                break;
            case 'NLOWER':
                $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
                break;
            case 'NUMBER':
                $chars = '0123456789';
                break;
            case 'ALL':
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                break;
            default :
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                break;
        }
        $output = '';
        while (strlen($output) < $len) {
            $output .= substr($chars, (mt_rand() % strlen($chars)), 1);
        }
        return $output;
    }

    public static function getIp()
    {
        if (getenv("x-forwarded-for") && strcasecmp(getenv("x-forwarded-for"), "unknown")) {
            $ip = getenv("x-forwarded-for");
        } else if (getenv("Proxy-Client-IP") && strcasecmp(getenv("Proxy-Client-IP"), "unknown")) {
            $ip = getenv("Proxy-Client-IP");
        } else if (getenv("WL-Proxy-Client-IP") && strcasecmp(getenv("WL-Proxy-Client-IP"), "unknown")) {
            $ip = getenv("WL-Proxy-Client-IP");
        } else if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown")) {
            $ip = getenv("HTTP_CLIENT_IP");
        } else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("X-Real-IP") && strcasecmp(getenv("X-Real-IP"), "unknown")) {
            $ip = getenv("X-Real-IP");
        } else if (isset ($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = "unknown";
        }
        if (strpos($ip, ',') !== false) {
            $ipArr = explode(',', $ip);
            return isset($ipArr[0]) ? $ipArr[0] : '';
        }
        return ($ip);
    }


    public static function getClientIP()
    {
        $realip = '127.0.0.1';
        if (isset($_SERVER) && isset($_SERVER['SSH_CLIENT'])) {
            $arr = explode(' ', $_SERVER['SSH_CLIENT']);
            if (isset($arr[0]) && !empty($arr[0])) {
                return $arr[0];
            }
        } else {
            //不允许就使用getenv获取
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } elseif (getenv("HTTP_X_REAL_IP")) {
                $realip = getenv("HTTP_X_REAL_IP");
            } elseif (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }
        if (trim($realip) == "::1") {
            $realip = "127.0.0.1";
        }

        return $realip;
    }


    public static function hideStr($str = '', $lcon = 0, $rcon = 0)
    {
        $newStr = "";
        $strLen = strlen($str);
        $newStr = substr($str, 0, $lcon);
        $count = $strLen - $lcon - $rcon;
        for ($i = 0; $i < $count; $i++) {
            $newStr .= "*";
        }
        $newStr .= substr($str, -$rcon);
        return $newStr;
    }

    public static function filterEmoji($str)
    {
        if ($str) {
            $name = $str;
            $name = preg_replace('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', '', $name);
            $name = preg_replace('/xE0[x80-x9F][x80-xBF]‘.‘|xED[xA0-xBF][x80-xBF]/S', '?', $name);
            $return = @json_decode(preg_replace("#(\\\ud[0-9a-f]{3})#ie", "", json_encode($name)));
        } else {
            $return = '';
        }
        return $return;
    }

    //对象转数组
    public static function object2array($e)
    {
        $e = (array)$e;
        foreach ($e as $k => $v) {
            if (gettype($v) == 'resource') return [];
            if (gettype($v) == 'object' || gettype($v) == 'array')
                $e[$k] = (array)self::object2array($v);
        }
        return $e;
    }

    /**
     * 计算两点地理坐标之间的距离
     * @param Decimal $longitude1 起点经度
     * @param Decimal $latitude1 起点纬度
     * @param Decimal $longitude2 终点经度
     * @param Decimal $latitude2 终点纬度
     * @param Int $unit 单位 1:米 2:公里
     * @param Int $decimal 精度 保留小数位数
     * @return string
     */
    public static function getDistance($longitude1, $latitude1, $longitude2, $latitude2, $decimal = 2)
    {

        $EARTH_RADIUS = 6370.996; // 地球半径系数
        $PI = 3.1415926;

        $radLat1 = $latitude1 * $PI / 180.0;
        $radLat2 = $latitude2 * $PI / 180.0;

        $radLng1 = $longitude1 * $PI / 180.0;
        $radLng2 = $longitude2 * $PI / 180.0;

        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;

        $distance = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
        $distance = intval($distance * $EARTH_RADIUS * 1000);
        $unit = 'm';
        if ($distance > 1000) {
            $distance = $distance / 1000;
            $unit = 'km';
        }
        if (!defined('COOR')) define('COOR', true);
        if ($distance > 11000 || !COOR) {
            return '来自火星';
        }
        return round($distance, $decimal) . $unit;
    }


    /**
     * 生成订单号
     */
    public static function genOrderSn($type = 1)
    {
        $date = date("YmdHis");
        //日期+交易类型+时分+6为随机
        return substr($date, 0, 8) . str_pad($type, 4, '0', STR_PAD_RIGHT) . substr($date, 8, 4)
            . str_pad(mt_rand(1000, 999999), 6, '0', STR_PAD_LEFT);
    }

    //计算两个日期之间的间隔
    public static function getDiffDayNum($date)
    {
        if (is_null($date) || empty($date)) {
            return 0;
        }
        $nowTime = strtotime(date('Y-m-d', time()));
        $giveTime = strtotime(date('Y-m-d', strtotime($date)));
        $diff = ceil(($giveTime - $nowTime) / 86400);
        return $diff > 0 ? $diff : 0;
    }

    //计算生成唯一的uuid
    public static function guid()
    {
        if (function_exists('com_create_guid')) {
            return strtolower(com_create_guid());
        } else {
            mt_srand((double)microtime() * 10000);//optional for php 4.2.0 and up.
            $charid = strtolower(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
            return strtolower($uuid);
        }
    }

    public static function checkBlackIp($ip)
    {
        if ($ip == '127.0.0.1') $ip = self::getClientIP();
        return HR::existLockedIp($ip) == 1;
    }

    public static function prDates($start, $end)
    {
        $dates = [];
        $dt_start = strtotime($start);
        $dt_end = strtotime($end);
        while ($dt_start <= $dt_end) {
            $dates[] = date('Y-m-d', $dt_start);
            $dt_start = strtotime('+1 day', $dt_start);
        }
        return $dates;
    }

    public static function getOssPath($url)
    {
        $urlArr = explode('/', $url);
        unset($urlArr[0]);
        unset($urlArr[1]);
        unset($urlArr[2]);
        return join('/', $urlArr);
    }

    public static function errUrl($type = 'avatar')
    {
        $url = '';
        if ($type == 'avatar') $url = './vips/error_avatar.png';
        if ($type == 'img') $url = './vips/error_img.png';
        if ($type == 'album') $url = './vips/error_img.png';
        if ($type == 'sound') $url = './sound/sounderr.mp3';
        return $url;
    }


    public static function getFileMime($ext)
    {
        if (stripos($ext, "bmp")) {
            return "image/bmp";
        }
        if (stripos($ext, "gif")) {
            return "image/gif";
        }
        if (stripos($ext, "jpeg") || stripos($ext, "jpg") || stripos($ext, "png")) {
            return "image/jpg";
        }
        if (stripos($ext, "html")) {
            return "text/html";
        }
        if (stripos($ext, "txt")) {
            return "text/plain";
        }
        if (stripos($ext, "vsd")) {
            return "application/vnd.visio";
        }
        if (stripos($ext, "pptx") || stripos($ext, "ppt")) {
            return "application/vnd.ms-powerpoint";
        }
        if (stripos($ext, "docx") || stripos($ext, "doc")) {
            return "application/msword";
        }
        if (stripos($ext, "xml")) {
            return "text/xml";
        }
        return "image/jpg";
    }

    //判断
    public static function hasChineseStr($str)
    {
        if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $str, $match)) {
            return true;
        } else {
            return false;
        }
    }

    public static function getNumStr($num = 0)
    {
        if ($num < 1000) return (string)$num;
        if ($num > 1000) return round($num / 1000, 1) . 'K';
    }

    //转换文件大小
    public static function getFileSize($size)
    {
        $res = '0 B';
        if ($size > 0 && $size < 1024) {
            $res = $size . ' B';
        }
        if ($size >= 1024 && $size < 1048576) {
            $res = round($size / 1024, 1) . ' KB';
        }
        if ($size >= 1048576 && $size < (1024 * 1024 * 1024)) {
            $res = round($size / 1024 / 1024, 1) . ' MB';
        }
        if ($size >= (1024 * 1024 * 1024) && $size < (1024 * 1024 * 1024 * 1024)) {
            $res = round($size / 1024 / 1024 / 1024, 1) . 'GB';
        }
        return $res;
    }

    //获取文件是不是视频文件
    public static function videoIs($mime = '')
    {
        if (empty($mime)) return false;
        if (stripos($mime, 'video') !== false) return true;
        if (stripos($mime, 'mp4') !== false) return true;
        if (stripos($mime, 'mpg') !== false) return true;
        if (stripos($mime, 'dat') !== false) return true;
        if (stripos($mime, 'mov') !== false) return true;
        if (stripos($mime, 'avi') !== false) return true;
        if (stripos($mime, 'rm') !== false) return true;
        if (stripos($mime, 'wmv') !== false) return true;
        if (stripos($mime, 'rmvb') !== false) return true;
        if (stripos($mime, 'mov') !== false) return true;
        if (stripos($mime, 'flv') !== false) return true;
        if (stripos($mime, 'amv') !== false) return true;
        if (stripos($mime, 'asf') !== false) return true;
        return false;
    }

    public static function soundIs($mime)
    {
        if (empty($mime)) return false;
        if (stripos($mime, 'm3u8') !== false) return true;
        if (stripos($mime, 'm4a') !== false) return true;
        if (stripos($mime, 'mp3') !== false) return true;
        if (stripos($mime, 'wav') !== false) return true;
        if (stripos($mime, 'wma') !== false) return true;
        if (stripos($mime, 'ogg') !== false) return true;
    }

    public static function imageIs($mime = '')
    {
        if (empty($mime)) return false;
        if (stripos($mime, 'image') !== false) return true;
        if (stripos($mime, 'png') !== false) return true;
        if (stripos($mime, 'jpg') !== false) return true;
        if (stripos($mime, 'jpeg') !== false) return true;
        if (stripos($mime, 'bmp') !== false) return true;
        if (stripos($mime, 'gif') !== false) return true;
        return false;
    }

    //显示处理用户联系方式
    public static function showStr($profileModel, $col, $isShow)
    {
        if (isset($profileModel->$col) && !empty($profileModel->$col) && $isShow) {
            return H::decrypt($profileModel->$col);
        } else if (isset($profileModel->$col) && !empty($profileModel->$col) && $col == 'wechat' && $profileModel->hide_wechat == 1) {
            return '用户不公开';
        } else if (isset($profileModel->$col) && !empty($profileModel->$col) && !$isShow) {
            return '******';
        } else {
            return '暂未完善';
        }
    }

    //获取一个月的开始和起始时间
    public static function getMonthTimeSection($nian = 0, $yue = 0)
    {
        if (empty($nian) || empty($yue)) {
            $now = time();
            $nian = date("Y", $now);
            $yue = date("m", $now);
        }
        $time['begin'] = mktime(0, 0, 0, $yue, 1, $nian);
        $time['end'] = mktime(23, 59, 59, ($yue + 1), 0, $nian);
        return $time;
    }


    //生成数字推荐码
    public static function createInviteCodeById($id)
    {
        return 129820 + $id;
    }

    //生成数字id 唯一
    public static function gainStrId()
    {
        return base_convert(microtime(1) * 10000, 10, 16);
    }


    public static function getCityByCoor()
    {
        //获取城市
        $city = (new BaiduCloud())->getCityByPoint(COORDINATES);
        if ($city == '来自火星') {
            $city = CITY;
        }
        return $city;
    }

    //身份证号码合法性验证
    public static function isValidCard($id)
    {
        if (18 != strlen($id)) {
            return false;
        }
        $weight = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $code = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $mode = 0;
        $ver = substr($id, -1);
        if ($ver == 'x') {
            $ver = 'X';
        }
        foreach ($weight as $key => $val) {
            if ($key == 17) {
                continue;
            }
            $digit = intval(substr($id, $key, 1));
            $mode += $digit * $val;
        }
        $mode %= 11;
        if ($ver != $code[$mode]) {
            return false;
        }
        list($month, $day, $year) = self::getMDYFromCard($id);
        $check = checkdate($month, $day, $year);
        if (!$check) {
            return false;
        }
        $today = date('Ymd');
        $date = substr($id, 6, 8);
        if ($date >= $today) {
            return false;
        }
        return true;
    }

    private static function getMDYFromCard($id)
    {
        $date = substr($id, 6, 8);
        $year = substr($date, 0, 4);
        $month = substr($date, 4, 2);
        $day = substr($date, 6);
        return [$month, $day, $year];
    }

    public static function getBlurNick($uid)
    {
        $last = substr($uid, -1);
        $tail = intval(substr($uid, -2) / 10) % 3;
        if ($tail != 0) {
            return config('app.cdn_url') . '/nick/2101/' . $tail . $last . '.png!nick';
        } else {
            return config('app.cdn_url') . '/nick/2101/' . $last . '.png!nick';
        }
    }

    public static function nickColor($level)
    {
        if ($level == 0) return '#191919';
        if ($level >= 1 && $level <= 3) return '#e1a303';
        if ($level >= 4 && $level <= 6) return '#df3e3e';
        if ($level >= 7 && $level <= 9) return '#df3e3e';
        if ($level >= 10 && $level <= 12) return '#df3e3e';
    }

    //隐藏昵称打码
    public static function hideNick($nick)
    {
        $nick = str_replace(mb_substr($nick, 0, 1), '*', $nick);
        $len = mb_strlen($nick);
        if ($len > 2) {
            $nick = str_replace(mb_substr($nick, $len - 1, 1), '*', $nick);
        }
        if ($len > 4) {
            $nick = str_replace(mb_substr($nick, 2, 2), '**', $nick);
        }
        return $nick;
    }

    /*************************************************************************************
     ************************************** 封装下载方法 ***********************************
     *************************************************************************************/
    public static function downfile($src, $dest, $type = 2)
    {
        if ($type == 1) {
            return self::get_file2($src, $dest);
        }
        if ($type == 2) {
            return self::get_file($src, $dest);
        }
    }

    public static function get_file($url, $dest)
    {
        try {
            if (is_file($dest)) return true;
            set_time_limit(24 * 60 * 60); // 设置超时时间
            $dest_folder = dirname($dest) . '/'; // 文件下载保存目录，默认为当前文件目录
            self::mkdirs($dest_folder);
            $file = fopen($url, "rb"); // 远程下载文件，二进制模式
            if ($file) { // 如果下载成功
                $newf = fopen($dest, "wb"); // 远在文件文件
                if ($newf) // 如果文件保存成功
                    while (!feof($file)) { // 判断附件写入是否完整
                        fwrite($newf, fread($file, 1024 * 8), 1024 * 8); // 没有写完就继续
                    }
            }
            if ($file) fclose($file); // 关闭远程文件
            if ($newf) fclose($newf); // 关闭本地文件
            return true;
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return false;
        }
    }

    public static function get_file2($src, $dest)
    {
        try {
            $hex = file_get_contents($src);
            file_put_contents($dest, $hex);
            return true;
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return false;
        }
    }


    //微信签名
    public static function Sign($arrayobj, $mchKey = ''): string
    {
        //步骤一：字典排序
        ksort($arrayobj);
        //步骤二：在
        $str = self::ToUrlParams($arrayobj);
        //步骤三：在$str后面加KEY
        $str .= "&key=" . $mchKey;
        //步骤四：MD5或HMAC-SHA256C加密
        $str = md5($str);
        //步骤五：所有字符转大写
        return strtoupper($str);
    }

    public static function ToUrlParams($arrayobj)
    {
        $buff = "";
        foreach ($arrayobj as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    public static function array2XML($paramsobj)
    {
        $xml = "<xml>\n";
        foreach ($paramsobj as $key => $value) {
            $xml .= "<" . $key . ">" . $value . "</" . $key . ">\n";
        }
        $xml .= "</xml>";
        return $xml;
    }


    public static function xmlToArray($xml)
    {
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

}
