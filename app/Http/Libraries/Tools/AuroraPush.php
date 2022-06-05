<?php

namespace App\Http\Libraries\Tools;

use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use Curl\Curl;
use App\Http\Helpers\H;

use mobile\push\Jpush;
use mobile\push\Jreport;

class AuroraPush
{
    static private $jpush;
    static private $instance;
    static private $jreport;
    static private $isOn;
    static private $pro;
    static private $user;
    static private $curlBuilder = null;

    //防止使用new直接创建对象
    private function __construct()
    {
        $smsBase = SettingsModel::getSigConf('sms');
        self::$isOn = $smsBase['jpush_on'];
        //self::$pro = $smsBase['jpush_pro'] == 1;
        self::$pro = false;
        self::$user = $smsBase['jpush_appkey'] . ':' . $smsBase['jpush_secret'];
        self::$jpush = new Jpush($smsBase['jpush_appkey'], $smsBase['jpush_secret']);
        self::$jreport = new Jreport($smsBase['jpush_appkey'], $smsBase['jpush_secret']);
        $curlBuilder = new Curl();
        $curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
        $curlBuilder->setOpt(CURLOPT_CONNECTTIMEOUT, 300);
        self::$curlBuilder = $curlBuilder;
    }


    static public function getInstance()
    {
        //判断$instance是否是Singleton的对象，不是则创建
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getLoginToken($token)
    {
        $data = [
            'loginToken' => $token,
            'exID' => 'jpush',
        ];
        $base_url = 'https://api.verification.jpush.cn/v1/web/loginTokenVerify';
        $header = [
            'Content-Type:application/json',
        ];
        self::$curlBuilder->setOpt(CURLOPT_HTTPHEADER, $header);
        self::$curlBuilder->setOpt(CURLOPT_USERPWD, self::$user);
        $res = self::$curlBuilder->post($base_url, json_encode($data));
        return $res;
    }

    //批量推送
    public function batchPush($msg)
    {
        if (self::$isOn == 0) return false;
        return self::$jpush->setPlatform('all')->addAllAudience()->allNotification($msg)->send(self::$pro);
    }

    //单个人员推送
    public function aliasPush($alias, $msg, $channel = 'all', $remote_pic = '')
    {
        if (self::$isOn == 0) return false;
        $res = $resAndroid = $resIos = [];
        //设置别名
        try {
            //推送的消息体,安卓调用androidNotification，iOS调用iosNotification
            //减少一次调用
            $sended = false;
            if (in_array($channel, ['ios', 'all'])) {
                $resIos = self::$jpush->setPlatform('ios')->addAlias($alias)->iosNotification($msg)->send(self::$pro);
                if (isset($resIos['code']) && $resIos['code'] == 200) $sended = true;
                $res = $resIos;
            }
            if (in_array($channel, ['android', 'all']) && !$sended) {
                if (isset($msg['sound'])) unset($msg['sound']);
                if (isset($msg['badge'])) unset($msg['badge']);
                if (isset($msg['content-available'])) unset($msg['content-available']);

                $msg['extras']['jump']['jump_scheme_id'] = 0;

                //角标图通知栏小图
                if (!empty($remote_pic)) $msg['small_icon_uri'] = $remote_pic;
                $msg['title'] = $msg['alert']['body'];
                $msg['alert'] = $msg['alert']['title'];
                $resAndroid = self::$jpush->setPlatform('android')->addAlias($alias)->androidNotification($msg)->send(self::$pro);
                $res = $resAndroid;
            }
            file_put_contents('/tmp/dedug.log', print_r([$channel, $alias, $res], 1) . PHP_EOL, 8);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
        return $res;
    }

    //送达统计
    public function pushReport(array $aliasArr)
    {
        if (self::$isOn == 0) return false;
        return self::$jreport->receivedUrl()->received($aliasArr)->send();
    }

    //获取别名绑定关系
    public function pushDecive(array $aliasArr)
    {
        self::$jpush->device();
    }


    //防止使用clone克隆对象
    private function __clone()
    {
        self::$curlBuilder->close();
    }

}
