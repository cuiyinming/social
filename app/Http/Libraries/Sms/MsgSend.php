<?php

namespace App\Http\Libraries\Sms;


use App\Http\Models\SettingsModel;
use Curl\Curl;
use App\Http\Helpers\H;

class MsgSend
{

    private $curl = null;
    private $userInfo = [];
    private $templateId = '';



    public function __construct(array $userInfo, $templateId)
    {
        $this->userInfo = $userInfo;
        $this->templateId = $templateId;
        $this->curl = new Curl();
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
    }

    public function doSend($mobile, $content = '', $channel = 'dxbSend')
    {
        return $this->$channel($mobile, $content);
    }

    /**
     * 发送短信
     * 短信宝通道
     */
    public function dxbSend($mobile, $content = '')
    {
        $send_url = 'http://api.smsbao.com/sms?u=' . $this->userInfo['username'] . '&p=' . md5($this->userInfo['password']) . '&m=' . $mobile . '&c=' . $content;
        $header = [
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Accept-Language: zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Pragma: no-cache',
            'X-Requested-With: XMLHttpRequest',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
        ];
        $this->curl->setOpt(CURLOPT_HTTPHEADER, $header);
        $result = $this->curl->get($send_url);
        return intval($result) == 0 ? true : false;
    }

    /**
     * 发送短信
     * 阿里云短信接口
     */
    public function aliyunSend($mobile, $content = [])
    {
        $config = [
            'access_key' => $this->userInfo['username'],
            'access_secret' => $this->userInfo['password'],
            'sign_name' => $this->userInfo['sign'],
        ];
        $aliSms = new \Mrgoon\AliSms\AliSms();
        $response = $aliSms->sendSms($mobile, $this->templateId, $content, $config);
        $response = H::object2Array($response);
        if (isset($response['Code']) && strtolower($response['Code']) == 'ok') {
            return true;
        } else {
            return false;
        }
    }



    public function __destruct()
    {
        $this->curl->close();
    }


}
