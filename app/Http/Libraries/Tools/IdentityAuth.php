<?php

namespace App\Http\Libraries\Tools;

use App\Http\Models\Logs\LogAuthModel;
use Curl\Curl;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Log;

/***********************************************************************
 * *************************身份证三要素对比 **************************
 ***********************************************************************/
class IdentityAuth
{
    private $curlBuilder = null;

    public function __construct()
    {
        $this->curlBuilder = new Curl();
        $this->curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
    }

    /******照片对比*****/
    public function getAuth($params)
    {
        try {
            return $this->_authData($params);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    //示例
    private function _authData(array $params)
    {
        $config = config('common.identity_check');
        $header = [
            'Content-Type:application/x-www-form-urlencoded; charset=utf-8',
            'Authorization:APPCODE ' . $config['app_code'],
        ];
        $base_url = 'https://dfphone3.market.alicloudapi.com/verify_id_name_phone';
        $this->curlBuilder->setOpt(CURLOPT_HTTPHEADER, $header);
        $res = $this->curlBuilder->post($base_url, $params);
        $res = H::object2array($res);
        if (isset($res['state']) && $res['state'] == 1) {
            $check_res = true;
        } else {
            $check_res = false;
        }
        return $check_res;
    }


    public function __destruct()
    {
        $this->curlBuilder->close();
    }

}
