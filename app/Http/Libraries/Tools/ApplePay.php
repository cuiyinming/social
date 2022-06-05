<?php

namespace App\Http\Libraries\Tools;

use App\Http\Models\Payment\AppleLog\AppleIapInAppModel;
use App\Http\Models\Payment\AppleLog\AppleIapLatestReceiptInfoModel;
use App\Http\Models\Payment\AppleLog\AppleIapModel;
use App\Http\Models\Payment\AppleLog\AppleIapPendingRenewalInfoModel;
use App\Http\Models\SettingsModel;
use Curl\Curl;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Log;

class ApplePay
{
    private $curlBuilder = null;

    //字符串长度
    public function __construct()
    {
        $this->curlBuilder = new Curl();
        $this->curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
        $this->curlBuilder->setOpt(CURLOPT_CONNECTTIMEOUT, 300);
    }

    /**** 本段代码的逻辑：先向正式环境的url发起验证本次支付信息是否有误的请求，若本次请求的数据是沙箱环境中的数据，则只返回 status = 21007，
     ** 那么会再向沙箱环境url发送一次请求。无论哪种请求，只要支付信息消息验证无误，status = 0 就会将本次返回的数据都会（json）写入到数据库中（iap表）
     * /*
     * 21000 App Store不能读取你提供的JSON对象
     * 21002 receipt-data域的数据有问题
     * 21003 receipt无法通过验证
     * 21004 提供的shared secret不匹配你账号中的shared secret
     * 21005 receipt服务器当前不可用
     * 21006 receipt合法，但是订阅已过期。服务器接收到这个状态码时，receipt数据仍然会解码并一起发送
     * 21007 receipt是Sandbox receipt，但却发送至生产系统的验证服务
     * 21008 receipt是生产receipt，但却发送至Sandbox环境的验证服务
     * $receipt_data 苹果返回的支付凭证
     * 正式 ： https://buy.itunes.apple.com/verifyReceipt
     * 沙箱 ： https://sandbox.itunes.apple.com/verifyReceipt
     **/
    public function validateApplePay($receiptData)
    {
        $env = true; //true 正式 false 测试
        if (strlen($receiptData) < 1000) {
            throw new \Exception('receipt参数有误');
        }
        //正式购买地址 沙盒购买地址
        $buyUrl = "https://buy.itunes.apple.com/verifyReceipt";
        $urlSandbox = "https://sandbox.itunes.apple.com/verifyReceipt";
        $base_url = $env ? $buyUrl : $urlSandbox;
        $postData = [
            "receipt-data" => $receiptData,
            "password" => '',
        ];
        $html = $this->curlBuilder->post($base_url, json_encode($postData));
        $data = H::object2array($html);
        // 如果是沙盒数据 则验证沙盒模式
        if (isset($data['status']) && $data['status'] == '21007') {
            // 请求验证  1代表向沙箱环境url发送验证请求
            $html = $this->curlBuilder->post($urlSandbox, $postData);
            $data = H::object2array($html);
        }
        $ret = [
            'success' => false,
            'data' => []
        ];
        // 判断是否购买成功  【状态码,0为成功（无论是沙箱环境还是正式环境只要数据正确status都会是：0）】
        if (isset($data['status']) && intval($data['status']) === 0) {
            $ret['success'] = true;
            $ret['data'] = $data;
        }
        return $ret;
    }

    public function __destruct()
    {
        $this->curlBuilder->close();
    }


}
