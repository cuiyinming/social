<?php

namespace App\Http\Libraries\Tools;

use App\Http\Models\AppleIapInAppModel;
use App\Http\Models\AppleIapLatestReceiptInfoModel;
use App\Http\Models\AppleIapModel;
use App\Http\Models\AppleIapPendingRenewalInfoModel;
use Curl\Curl;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Log;
use OSS\OSSClient;
use OSS\Core\OSSException;

class AliyunOss
{
    private static $curlBuilder;
    private static $instance;
    private static $accessKey = '';
    private static $secret = '';
    private static $endpoint = '';
    private static $bucket = "";
    protected $ossClient = null;


    static public function getInstance()
    {
        $curlBuilder = new Curl();
        $curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
        $curlBuilder->setOpt(CURLOPT_CONNECTTIMEOUT, 300);
        self::$curlBuilder = $curlBuilder;
        //判断$instance是否是Uni的对象
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    public function uploadToOss($name, $content)
    {
        try {
            $ossClient = new \OSS\OssClient(self::$accessKey, self::$secret, self::$endpoint);
            $obj = $ossClient->uploadFile(self::$bucket, $name, $content);
            return $obj['oss-request-url'];
        } catch (OssException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function deleteOssObj($name)
    {
        try {
            $ossClient = new \OSS\OssClient(self::$accessKey, self::$secret, self::$endpoint);
            $ossClient->deleteObject(self::$bucket, $name);
        } catch (OssException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function __destruct()
    {
        self::$curlBuilder->close();
    }

    private function __construct()
    {
    }

    private function __clone()
    {
    }

}
