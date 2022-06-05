<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;

use App\Http\Helpers\S;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogGiftReceiveModel;
use App\Http\Models\Logs\LogGiftSendModel;
use App\Http\Models\Logs\LogSweetModel;
use App\Http\Models\Logs\LogSweetUniqueModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\PaymentOrderModel;
use App\Http\Models\Payment\SubscribeModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Payment\Client;
use Curl\Curl;

class H5Controller extends Controller
{

    public function vipList(Request $request)
    {
        $uid = $request->input('uid', 0);
        if ($uid == 0) {
            return $this->jsonExit(201, '系统错误');
        }
        $profile = UsersProfileModel::getUserInfo($uid);
        $expire = empty($profile->vip_exp_time) || strtotime($profile->vip_exp_time) < time();
        $strTips = '立即开通会员，享受专属特权';
        $status = 0;
        if (!empty($profile->vip_exp_time) && strtotime($profile->vip_exp_time) >= time()) {
            $strTips = date('Y-m-d', strtotime($profile->vip_exp_time)) . '到期，续费后有效期将延长';
            $status = 1;
        }
        if (!empty($profile->vip_exp_time) && strtotime($profile->vip_exp_time) < time()) {
            $strTips = '已失去特权' . ceil((time() - strtotime($profile->vip_exp_time)) / 86400) . '天';
            $status = 2;
        }
        $data['base_info'] = [
            'surplus' => H::getDiffDayNum($profile->vip_exp_time), //计算剩余天数
            'vip_exp_date' => $profile->vip_exp_time ? date('Y-m-d', strtotime($profile->vip_exp_time)) : '',
            'vip_exp_time' => $profile->vip_exp_time,
            'vip_is' => $expire ? 0 : $profile->vip_is,
            'vip_level' => $expire ? 0 : $profile->vip_level,
            'expire' => $status,
            'str_tips' => $strTips,
        ];
        $right_map = ['swordsman', 'knight', 'suzerain', 'lord'];
        $re_purchase = '';
        if ($status == 2 && $profile->vip_level_last > 0) {
            $re_purchase = S::getVipNameByLevelId($profile->vip_level_last, 'id');
        }
        $right = [];
        foreach ($right_map as $item) {
            $right[] = SubscribeModel::getRightByName($item, $re_purchase);
        }
        $data['right'] = $right;
        return $this->jsonExit(200, 'OK', $data);
    }

    public function rechargeList(Request $request)
    {
        $uid = $request->input('uid', 0);
        $user = UsersModel::find($uid);
        $profile = $user->profile;
        if (!$profile) {
            return $this->jsonExit(201, '用户不存在');
        }
        $expire = empty($userModel->vip_exp_time) || strtotime($userModel->vip_exp_time) < time();
        $data = [
            'base_info' => [
                'nick' => $user->nick,
                'sweet_coin' => $user->sweet_coin,
                'surplus' => H::getDiffDayNum($profile->vip_exp_time), //计算剩余天数
                'vip_exp_date' => $profile->vip_exp_time ? date('Y-m-d', strtotime($profile->vip_exp_time)) : '',
                'vip_exp_time' => $profile->vip_exp_time ?: '',
                'vip_is' => $expire ? 0 : $profile->vip_is,
                'vip_level' => $expire ? 0 : $profile->vip_level,
            ],
            'right_list' => config('subscribe.recharge_list'),
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    //h5 发起订单
    public function askH5Order(Request $request)
    {
        $user_id = $request->input('uid', 0);
        $user = UsersModel::getUserInfo($user_id);
        if (!$user) {
            return $this->jsonExit(201, '用不不存在');
        }
        $amount = $request->input('amount', 0);
        //金额非法
        if (!in_array($amount, S::getProPriceList(2))) {
            return $this->jsonExit(201, '金额错误');
        }
        $id = $request->input('id', 0);
        $payment = $request->input('payment', 'alipay');
        $pay_type = stripos($id, 'xinyou') === false;
        $subject = $pay_type ? '心友会员购买' : '心友内购购买';
        $body = $subject . ' 共计：' . $amount . '元';
        //过滤一次
        if (!in_array($id, S::getPro(2))) {
            return $this->jsonExit(201, '商品id错误');
        }
        if (!in_array($payment, ['alipay', 'wechat'])) {
            return $this->jsonExit(201, '支付方式传递错误');
        }
        $user_for = $pay_type ? 'vip' : 'recharge';
        $orderSn = H::genOrderSn(4);
        if ($payment == 'alipay') {
            $payData = [
                'trade_no' => $orderSn,
                'amount' => $amount,
                'subject' => $subject,
                'goods_type' => '0',
                'body' => $body,
                'time_expire' => time() + 1800,  //半个小时不支付就过期
                'return_params' => $user_for,
            ];
        }

        if ($payment == 'wechat') {
            $payData = [
                'body' => $body,
                'subject' => $subject,
                'trade_no' => $orderSn,
                'time_expire' => time() + 1800,  // 表示必须 600s 内付款
                'amount' => $amount, // 微信沙箱模式，需要金额固定为3.01
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', // 客户地址
                'return_param' => $user_for,
            ];
        }
        // 0订阅 1内购
        PaymentOrderModel::create([
            'user_id' => $user_id,
            'amount' => $amount,
            'payment' => $payment,
            'log_sn' => '',
            'status' => 0,
            'type' => $pay_type ? 0 : 1,
            'body' => $body,
            'relate_id' => $id,
            'user_ip' => $request->ip(),
            'order_no' => $orderSn,
            'channel' => 'h5',
            'expire_at' => date('Y-m-d H:i:s', time() + 1800),
        ]);
        // 使用
        try {
            if ($payment == 'alipay') {
                $config = config('subscribe.alipay');
                $client = new Client(Client::ALIPAY, $config);
                $res = $client->pay(Client::ALI_CHANNEL_WAP, $payData);
                if (isset($res) && !empty($res)) {
                    PaymentOrderModel::where('order_no', $orderSn)->update(['qr_str' => $res]);
                }
                return $this->jsonExit(200, 'OK', ['qr_str' => $res, 'order_sn' => $orderSn]);
            }
            if ($payment == 'wechat') {
                $config = config('subscribe.wechat');
                $client = new Client(Client::WECHAT, $config);
                $type = 'wap';
                if ($type == 'pub') {
                    $res = $client->pay(Client::WX_CHANNEL_PUB, $payData);
                }
                if ($type == 'wap') {
                    $res = $client->pay(Client::WX_CHANNEL_WAP, $payData);
                }
                if (isset($res) && !empty($res)) {
                    PaymentOrderModel::where('order_no', $orderSn)->update(['qr_str' => json_encode($res)]);
                }
                return $this->jsonExit(200, 'OK', ['qr_str' => $res, 'order_sn' => $orderSn]);
            }

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }


    //公众号支付å
    public function wechatPub(Request $request)
    {
        $appId = config('subscribe.wechat_pub')['appid'];
        $redirectUrl = "http://www.hfriend.cn/api/h5-wechat-pub-pay-two"; //此处填写回调地址
        $state = strval(time()); // 模拟自定义 state 参数
        $baseUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?";
        $requestUrl = $baseUrl;
        $requestUrl = $requestUrl . "appid=" . $appId;
        $requestUrl = $requestUrl . "&redirect_uri=" . urlencode($redirectUrl);
        $requestUrl = $requestUrl . "&response_type=code&scope=snsapi_base"; // 不调用授权页的方式
        $requestUrl = $requestUrl . "&state=" . $state;
        $requestUrl = $requestUrl . "#wechat_redirect";
        header("Location: $requestUrl"); //返回并直接跳转到拼接完成的接口地址去
        exit();
    }

    public function wechatPubTwo(Request $request)
    {
        $code = $request->input('code', '');
        if (empty($code)) {
            echo "获取参数错误";
            die();
        }
        $wechat = config('subscribe.wechat_pub');
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $wechat['appid'] . '&secret=' . $wechat['secret'] . '&code=' . $code . '&grant_type=authorization_code';
        $cont = file_get_contents($url);
        $cont = json_decode($cont, 1);
        dd($cont);
        $openid = $cont['openid'] ?? '';
        $appId = config('subscribe.wechat_pub')['appid'];
        $mchId = config('subscribe.wechat')['mch_id'];
        $mchSecret = config('subscribe.wechat')['md5_key'];
        $prepear = [
            'appid' => $appId,
            'mch_id' => $mchId,
            'device_info' => 'web',
            'nonce_str' => H::randstr(8),
            'sign_type' => 'MD5',
            'body' => '会员充值',
            'out_trade_no' => H::genOrderSn(8),
            'total_fee' => 100,
            'spbill_create_ip' => $request->ip(),
            'trade_type' => 'JSAPI',
            'openid' => $openid,
            'notify_url' => 'http://api.hfriend.cn/api/notify/notify-wechat'
        ];
        $prepear['sign'] = H::Sign($prepear, $mchSecret);
        $xml = H::array2XML($prepear);
        $curlBuilder = new Curl();
        $curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
        $res = $curlBuilder->post('https://api.mch.weixin.qq.com/pay/unifiedorder', $xml);
        dd(H::xmlToArray($res));

    }

    //获取公众号用户信息
    public function wechatPubUserInfo()
    {
        $appId = config('subscribe.wechat_pub')['appid'];
        $secret = config('subscribe.wechat_pub')['secret'];
        $base_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $appId . '&secret=' . $secret;
        $curlBuilder = new Curl();
        $curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
        $res = $curlBuilder->get($base_url);
        /***-----获取用户信息---START--****/
        $openid = 'oEP5U64c1PdDgCoQFcKn4zJI39Qg';
        $userinfo_url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=' . $res->access_token . '&openid=' . $openid;
        $res = $curlBuilder->get($userinfo_url);
        dd($res);
        /***-----获取用户信息---END--****/
    }

}
