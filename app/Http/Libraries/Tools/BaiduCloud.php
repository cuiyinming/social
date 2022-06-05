<?php

namespace App\Http\Libraries\Tools;

use App\Http\Helpers\HR;
use Curl\Curl;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\R;
use Crisen\AI\AI;

class BaiduCloud
{
    private $curlBuilder = null;
    private $isTest = true;

    private $accessKey = '';
    private $secret = '';
    private $appId = '';

    //字符串长度
    public function __construct()
    {
        $this->curlBuilder = new Curl();
        $this->curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
        $this->curlBuilder->setOpt(CURLOPT_CONNECTTIMEOUT, 300);
    }

    //获取面部基本信息及相关分数
    public function getFaceBaseInfo($url, $sex = 0)
    {
        //百度ai
        $baiduConfig = [
            'app_id' => $this->appId,
            'api_key' => $this->accessKey,
            'secret_key' => $this->secret
        ];
        $option = [
            "face_field" => "age,beauty,face_type,quality,type,probability,face_shape,gender",
            "max_face_num" => 3,
            "face_type" => 'LIVE'
        ];
        $ai = AI::baidu($baiduConfig);
        //腾讯ai
        //$tencentConfig = [
        //    'app_id' => 'your appid',
        //    'app_key' => 'your secret id',
        //];
        //$ai = AI::tencent($tencentConfig);
        $res = $ai->face()->url($url)->detect($option);
        if ($res->success()) {
            $arr = $res->toArray();
            $face_num = isset($arr['face_num']) ? $arr['face_num'] : 0;
            if ($face_num == 0) {
                throw new \Exception('未检测到人脸');
            }
            if ($face_num > 1) {
                throw new \Exception('检测到超过1张人脸，请重试');
            }
            $face = isset($arr['face_list'][0]) ? $arr['face_list'][0] : [];
            $gender = (isset($face['gender']['type']) && isset($face['gender']['probability']) && $face['gender']['probability'] > 0.7) ? $face['gender']['type'] : '';
            $face_type = (isset($face['face_type']['type']) && isset($face['face_type']['probability']) && $face['face_type']['probability'] > 0.7) ? $face['face_type']['type'] : '';
            $beauty = isset($face['beauty']) ? $face['beauty'] : 0;
            if (!empty($face_type) && $face_type != 'human') {
                throw new \Exception('未检测到人出现');
            }
            if (!empty($gender) && $sex != 0) {
                if (($sex == 2 && $gender == 'female') || ($sex == 1 && $gender == 'male')) {
                    throw new \Exception('人脸与您的注册性别可能不符，请重试');
                }
            }
            return $beauty;
        } else {
            throw new \Exception('未检测到人脸');
        }
    }

    public function compareFace($sourceUrl, $targetUrl)
    {
        $compareData = [
            [
                'image' => $sourceUrl,
                'image_type' => 'URL',
                'face_type' => 'LIVE',
                'quality_control' => 'LOW',
            ], [
                'image' => $targetUrl,
                'image_type' => 'URL',
                'face_type' => 'LIVE',
                'quality_control' => 'LOW',
            ]
        ];
        $token = $this->_accessToken();
        $baseUrl = 'https://aip.baidubce.com/rest/2.0/face/v3/match?access_token=' . $token;
        $header = [
            'Content-Type:application/json;charset=utf-8',
        ];
        $this->curlBuilder->setOpt(CURLOPT_HTTPHEADER, $header);
        $res = $this->curlBuilder->post($baseUrl, json_encode($compareData));
        $res = H::object2array($res);
        return isset($res['result']) ? $res['result'] : [];

    }

    private function _accessToken()
    {
        $accessToken = R::gredis('baidu-acckey');
        if (empty($accessToken)) {
            $authUrl = 'https://aip.baidubce.com/oauth/2.0/token';
            $postData['grant_type'] = 'client_credentials';
            $postData['client_id'] = $this->accessKey;
            $postData['client_secret'] = $this->secret;
            $str = "";
            foreach ($postData as $k => $v) {
                $str .= "$k=" . urlencode($v) . "&";
            }
            $postData = substr($str, 0, -1);
            $res = $this->curlBuilder->post($authUrl, $postData);
            $res = H::object2array($res);
            if (isset($res['access_token'])) {
                $accessToken = [
                    'access_token' => $res['access_token']
                ];
                R::sredis($accessToken, 'baidu-acckey', 2505600);
            }
        }
        return isset($accessToken['access_token']) ? trim($accessToken['access_token']) : '';
    }

    //百度根据经纬度获取具体的地址信息
    public function getAddrByPoint($point)
    {
        $res = '未知';
        if (stripos($point, ',') !== false && $point != '0.00,0.00') {
            $base_url = 'http://api.map.baidu.com/geocoder/v2/?location=' . $point . '&output=json&pois=1&ak=';
            $res = $this->curlBuilder->get($base_url);
            $resArr = json_decode($res, 1);
            if (isset($resArr['result']['formatted_address'])) $res = $resArr['result']['formatted_address'];
            if (isset($resArr['result']['business'])) $res = $res . ' [ ' . $resArr['result']['business'] . ' ] ';
        }
        return $res;
    }

    public function getCityByPoint($point): string
    {
        $province = '';
        $city = '';
        if (stripos($point, ',') !== false && $point != '0.00,0.00') {
            $base_url = 'http://api.map.baidu.com/geocoder/v2/?location=' . $point . '&output=json&pois=1&ak=';
            $res = $this->curlBuilder->get($base_url);
            $resArr = json_decode($res, 1);
            if (isset($resArr['result']['addressComponent']['province'])) $province = $resArr['result']['addressComponent']['province'];
            if (isset($resArr['result']['addressComponent']['city'])) $city = $resArr['result']['addressComponent']['city'];
        }
        if (!empty($province)) {
            if (stripos($city, '土家族苗族自治州') !== false) {
                $city = str_replace('土家族苗族自治州', '', $city);
            }
            if (stripos($city, '藏族自治州') !== false) {
                $city = str_replace('藏族自治州', '', $city);
            }
            if (stripos($city, '朝鲜族自治州') !== false) {
                $city = str_replace('朝鲜族自治州', '', $city);
            }
            if (stripos($city, '傣族自治州') !== false) {
                $city = str_replace('傣族自治州', '', $city);
            }
            if (stripos($city, '哈尼族彝族自治州') !== false) {
                $city = str_replace('哈尼族彝族自治州', '', $city);
            }
            if (stripos($city, '布依族苗族自治州') !== false) {
                $city = str_replace('布依族苗族自治州', '', $city);
            }
            if (stripos($province, '壮族自治区') !== false) {
                $province = str_replace('壮族自治区', '', $province);
            }
            if (stripos($province, '回族自治区') !== false) {
                $province = str_replace('回族自治区', '', $province);
            }
            if (stripos($province, '维吾尔自治区') !== false) {
                $province = str_replace('维吾尔自治区', '', $province);
            }
            if (stripos($city, '自治州') !== false) {
                $city = str_replace('自治州', '', $city);
            }
            if (stripos($province, '自治区') !== false) {
                $province = str_replace('自治区', '', $province);
            }
            if ($province == $city) {
                return $province;
            }
            if (stripos($city, '市') !== false) {
                $city = str_replace('市', '', $city);
            }
            return $city;
            //return $province . '.' . $city;
        } else {
            return '来自火星';
        }
    }

    public function getPointByIp($ip)
    {
        $point = HR::getLocationByIp($ip, false);
        if (!empty($point) && $point != '0.00,0.00') {
            return $point;
        }
        $base_url = "https://api.map.baidu.com/location/ip?ak=&ip={$ip}&coor=bd09ll";
        $res = $this->curlBuilder->post($base_url);
        $resArr = H::object2array($res);
        $point = '31.23,121.47';
        if (isset($resArr['status']) && $resArr['status'] == 0 && isset($resArr['content']['point'])) {
            $point = $resArr['content']['point']['y'] . ',' . $resArr['content']['point']['x'];
            HR::getLocationByIp($ip, true, $point);
        }
        return $point;
    }

    public function __destruct()
    {
        $this->curlBuilder->close();
    }
}
