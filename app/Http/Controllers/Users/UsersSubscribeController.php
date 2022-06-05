<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\AuthController;
use App\Http\Libraries\Crypt\Rsa;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Libraries\Sms\RongIm;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogContactUnlockModel;
use App\Http\Models\Logs\LogSendInviteModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\Payment\PaymentOrderModel;
use App\Http\Models\Payment\SubscribeModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersMsgGovModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Models\Users\UsersSettingsModel;
use Curl\Curl;
use App\Http\Helpers\{H, HR, R, S};
use App\Http\Models\SettingsModel;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use \Payment\Client;
use RongCloud;

class UsersSubscribeController extends AuthController
{

    public function vipList(Request $request)
    {
        //获取用户vip 信息
        $profile = UsersProfileModel::getUserInfo($this->uid);
        $user = UsersModel::getUserInfo($this->uid);
        $expire = empty($profile->vip_exp_time) || strtotime($profile->vip_exp_time) < time();
        $strTips = '立即开通会员，享受专属特权';
        $dateTips = '开通会员享特权';
        $status = 0;
        if (!empty($profile->vip_exp_time) && strtotime($profile->vip_exp_time) >= time()) {
            $strTips = date('Y-m-d', strtotime($profile->vip_exp_time)) . '到期，续费后有效期将延长';
            $dateTips = date('Y-m-d', strtotime($profile->vip_exp_time)) . '到期';
            $status = 1;
        }
        if (!empty($profile->vip_exp_time) && strtotime($profile->vip_exp_time) < time()) {
            $strTips = '已失去特权' . ceil((time() - strtotime($profile->vip_exp_time)) / 86400) . '天';
            $dateTips = '已失去特权' . ceil((time() - strtotime($profile->vip_exp_time)) / 86400) . '天';
            $status = 2;
        }
        $data['base_info'] = [
            'nick' => $user->nick,
            'avatar' => $user->avatar,
            'surplus' => H::getDiffDayNum($profile->vip_exp_time), //计算剩余天数
            'vip_exp_date' => $profile->vip_exp_time ? date('Y-m-d', strtotime($profile->vip_exp_time)) : '',
            'vip_exp_time' => $profile->vip_exp_time,
            'vip_is' => $expire ? 0 : $profile->vip_is,
            'vip_level' => $expire ? 0 : $profile->vip_level,
            'expire' => $status,
            'str_tips' => $strTips,
            'date_tips' => $dateTips,
        ];
        $version = intval(str_replace('.', '', VER));
        if ($version >= 300) {
            $data['right'] = SubscribeModel::getRightByNameHighVer($profile->vip_is == 1);
        } else {
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
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    public function rechargeList(Request $request)
    {
        //获取用户vip 信息
        $user = UsersModel::find($this->uid);
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

    public function selfRight(Request $request)
    {
        //获取用户vip 信息
        $profile = UsersProfileModel::getUserInfo($this->uid);
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
        $right_str = '开通VIP享特权';

        if ($profile->vip_level >= 1 && $profile->vip_level <= 3) $right_str = '心友剑士专属特权5/12';
        if ($profile->vip_level >= 4 && $profile->vip_level <= 6) $right_str = '心友铁骑专属特权6/12';
        if ($profile->vip_level >= 7 && $profile->vip_level <= 9) $right_str = '心友领主专属特权11/12';
        if ($profile->vip_level >= 10 && $profile->vip_level <= 12) $right_str = '心友勋爵专属特权12/12';
        $data['base_info'] = [
            'surplus' => H::getDiffDayNum($profile->vip_exp_time), //计算剩余天数
            'vip_exp_date' => $profile->vip_exp_time ? date('Y-m-d', strtotime($profile->vip_exp_time)) : '',
            'vip_exp_time' => $profile->vip_exp_time,
            'vip_is' => $expire ? 0 : $profile->vip_is,
            'vip_level' => $expire ? 0 : $profile->vip_level,
            'expire' => $status,
            'str_tips' => $strTips,
            'str_right' => $right_str,
            'product_id' => 'quzhi201',  //默认直接选的剑士包季
            'price' => 83,
        ];
        //未开通的权限
        $right_list = [
            ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk1.png'],
            ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw1.png'],
            ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs1.png'],
            ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx1.png'],
            ['name' => '查看联系方式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx1.png'],
            ['name' => '超级曝光', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg1.png'],
            ['name' => '赠送友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb1.png'],
            ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb1.png'],
            ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc1.png'],
            ['name' => '隐身模式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_dtys1.png'],
            ['name' => 'VIP客服', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vkha1.png'],
            ['name' => '专属消息背景', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsbj1.png'],
        ];
        foreach ($right_list as $k => $right) {
            $sub = SubscribeModel::getRightTimes($profile->vip_level);
            if ($profile->vip_level >= 1 && $profile->vip_level <= 12) {
                $right_list[0] = ['name' => '查看访客', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ckfk.png'];
                $right_list[1] = ['name' => '专属礼物', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zslw.png'];
                $right_list[2] = ['name' => 'VIP标识', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vbs.png'];
                $right_list[3] = ['name' => '急速注销', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jszx.png'];
                $right_list[6] = ['name' => '赠送' . $sub['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'];
                $right_list[4] = ['name' => '解锁联系方式' . $sub['contact'] . '个 / 天', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'];
                $data['base_info']['product_id'] = 'quzhi201';  //升级选骑士包季
                $data['base_info']['price'] = 83.00;
            }
            if ($profile->vip_level >= 4 && $profile->vip_level <= 12) {
                $right_list[4] = ['name' => '解锁联系方式' . $sub['contact'] . '个/天', 'icon' => 'http://static.hfriend.cn/vips/icon_4_jslx.png'];
                $right_list[5] = ['name' => '超级曝光' . $sub['super_show'] . '次', 'icon' => 'http://static.hfriend.cn/vips/icon_4_cjbg.png'];
                $right_list[6] = ['name' => '赠送' . $sub['reward'] . '友币', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsjb.png'];
                $right_list[7] = ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'];
                $right_list[8] = ['name' => '昵称变红', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'];
                $data['base_info']['product_id'] = 'quzhi701';  //升级选领主包季
                $data['base_info']['price'] = 168.00;
            }
            if ($profile->vip_level >= 7 && $profile->vip_level <= 9) {
                $right_list[8] = ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'];
                $right_list[9] = ['name' => '隐身模式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_dtys.png'];
                $right_list[10] = ['name' => 'VIP客服', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vkha.png'];
                $data['base_info']['product_id'] = 'quzhi401';  //升级选领主包月
                $data['base_info']['price'] = 448.00;
            }
            if ($profile->vip_level >= 10 && $profile->vip_level <= 12) {
                $right_list[8] = ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'];
                $right_list[7] = ['name' => '开通广播', 'icon' => 'http://static.hfriend.cn/vips/icon_4_ktgb.png'];
                $right_list[8] = ['name' => '专属昵称色', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsnc.png'];
                $right_list[9] = ['name' => '隐身模式', 'icon' => 'http://static.hfriend.cn/vips/icon_4_dtys.png'];
                $right_list[10] = ['name' => 'VIP客服', 'icon' => 'http://static.hfriend.cn/vips/icon_4_vkha.png'];
                $right_list[11] = ['name' => '专属消息背景', 'icon' => 'http://static.hfriend.cn/vips/icon_4_zsbj.png'];
                $data['base_info']['product_id'] = 'quzhi501';  //升级选勋爵包季
                $data['base_info']['price'] = 1398.00;
            }
        }
        $data['right_list'] = $right_list;
        return $this->jsonExit(200, 'OK', $data);
    }

    public function fastPay(Request $request)
    {
        $tips_type = $request->input('tips_type', 0);
        $vip_type = $request->input('vip_level', 'swordsman');
        $data = SubscribeModel::fastPayRight($vip_type, 1, $tips_type);
        return $this->jsonExit(200, 'OK', $data);
    }


    //订阅购买[优化验证逻辑为异步处理][如果没有订单号则按照续订逻辑进行处理]
    public function appleBuy(Request $request)
    {
        if (!$request->has('receipt_data')) {
            return $this->jsonExit(201, '参数错误');
        }
        $receiptData = $request->input('receipt_data');
        $order_sn = $request->input('order_sn', '');
        //这里有几个情况【一个是只有订单号票据丢失的，则直接删除订单。有票据也有订单号--失败订单补登，有票据没订单号，续费监听】
        if (empty($receiptData)) {
            OrderModel::where([['sn', $order_sn], ['status', 0]])->delete();
            return $this->jsonExit(200, 'OK');
        }
        //如果订单号不存在且票据不为空  下面为补登情况
        if (empty($order_sn)) {
            //创建一个补登订单
            $order_sn = H::genOrderSn(2);
            $data = [
                'user_id' => $this->uid,
                'status' => 0,
                'sn' => $order_sn,
                'create_type' => 2,   //0前台 1异步订单  2 前台订阅续费
                'receipt' => $receiptData,
                'type' => 0,  //补登订单只可能为 订阅
            ];
            OrderModel::create($data);
        }
        //不管啥情况 ，先把凭证存起来 更新订单信息
        $order = OrderModel::where([['user_id', $this->uid], ['sn', $order_sn], ['status', 0]])->first();
        if (!$order) {
            return $this->jsonExit(200, '该订单已支付或不存在');
        }
        //先保存票据
        $order->receipt = $receiptData;
        $order->sign = md5($receiptData);
        $order->save();
        //发送队列
        \App\Jobs\applePay::dispatch($order);
        //创建系统消息
        $title = $order->type == 0 ? 'VIP订单已提交' : '友币购买订单已提交';
        $cont = $order->type == 0 ? '您的VIP购买订单已经成功提交，系统正在处理，请稍等...' : '您的友币购买订单已经成功提交，系统正在处理，请稍等...';
        $event = $order->type == 0 ? 'vip_buy_start' : 'inner_buy_start';
        $sysMsgData = [
            'user_id' => $this->uid,
            'event_id' => $order->id,
            'event' => $event,
            'title' => $title,
            'cont' => $cont,
        ];
        UsersMsgSysModel::create($sysMsgData);
        //极光推送
        JpushModel::JpushCheck($this->uid, '', 0, $order->type == 0 ? 16 : 18);
        //推送融云系统消息
        $sysMsg = [
            'content' => $title,
            "title" => $cont,
            "extra" => ""
        ];
        RongCloud::messageSystemPublish(101, [$this->uid], 'RC:TxtMsg', json_encode($sysMsg));
        return $this->jsonExit(200, "OK");
    }


    /*----生成订单----*/
    public function orderMake(Request $request)
    {
        $productId = $request->input('product_id', ''); //产品id
        if (!in_array($productId, S::getPro(2))) {
            return $this->jsonExit(201, '产品id错误');
        }
        $type = stripos($productId, 'xinyou') !== false ? 1 : 0;
        try {
            $order = OrderModel::where([['user_id', $this->uid], ['status', 0], ['type', $type], ['product_id', $productId]])->orderBy('id', 'desc')->first();
            if ($order) {
                $orderSn = $order->sn;
                $productId = $order->product_id;
            } else {
                $orderSn = H::genOrderSn(2);
                $data = [
                    'user_id' => $this->uid,
                    'status' => 0,
                    'sn' => $orderSn,
                    'product_id' => $productId,
                    'create_type' => 0,
                    'type' => $type,
                    'platform' => 'ios',
                ];
                OrderModel::create($data);
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage() . $e->getLine());
        }
        return $this->jsonExit(200, 'OK', ['order_sn' => $orderSn, 'product_id' => $productId]);
    }

    /*----查询订单状态----*/
    public function orderStatus(Request $request)
    {
        $order_sn = $request->input('order_sn');
        $order = OrderModel::where('user_id', $this->uid)->where(function ($query) use ($order_sn) {
            $query->where('sn', $order_sn)->orWhere('transaction_id', $order_sn);
        })->first();
        if ($order && $order->status == 1) {
            return $this->jsonExit(200, 'OK');
        } else {
            return $this->jsonExit(205, 'Err');
        }
    }

    public function orderFail(Request $request)
    {
        $orders = OrderModel::where([['user_id', $this->uid], ['status', 0]])->get();
        $items = [];
        if (!$orders->isEmpty()) {
            foreach ($orders as $order) {
                $items[] = [
                    'order_sn' => $order->sn,
                    'product_id' => $order->product_id,
                ];
            }
        }
        return $this->jsonExit(200, 'OK', $items);
    }

    /*----支付宝微信购买-----*/
    public function createOrder(Request $request)
    {
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
        $orderSn = H::genOrderSn(3);
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
            'user_id' => $this->uid,
            'amount' => $amount,
            'payment' => $payment,
            'log_sn' => '',
            'status' => 0,
            'type' => $pay_type ? 0 : 1,
            'body' => $body,
            'relate_id' => $id,
            'user_ip' => $request->ip(),
            'order_no' => $orderSn,
            'channel' => CHANNEL,
            'platform' => 'android',
            'expire_at' => date('Y-m-d H:i:s', time() + 1800),
        ]);
        // 使用
        try {
            if ($payment == 'alipay') {
                $config = config('subscribe.alipay');
                $client = new Client(Client::ALIPAY, $config);
                $res = $client->pay(Client::ALI_CHANNEL_APP, $payData);
                if (isset($res) && !empty($res)) {
                    PaymentOrderModel::where('order_no', $orderSn)->update(['qr_str' => $res]);
                }
                return $this->jsonExit(200, 'OK', ['qr_str' => $res, 'order_sn' => $orderSn]);
            }
            if ($payment == 'wechat') {
                $config = config('subscribe.wechat');
                $client = new Client(Client::WECHAT, $config);
                $res = $client->pay(Client::WX_CHANNEL_APP, $payData);
                if (isset($res) && !empty($res)) {
                    PaymentOrderModel::where('order_no', $orderSn)->update(['qr_str' => json_encode($res)]);
                }
                return $this->jsonExit(200, 'OK', ['qr_str' => json_encode($res), 'order_sn' => $orderSn]);
            }

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    //用户友币变动记录
    public function userBalanceLog(Request $request)
    {
        $res = [];
        $type = $request->input('type', 0);
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $diamonds = $request->input('diamonds', 2);  //1是钻石 2心友币
        $date = $request->input('date', date('Y-m'));
        if (is_null($date)) {
            return $this->jsonExit(201, '日期不能为空');
        }
        $dateExp = explode('-', $date);
        $dateArr = H::getShiJianChuo($dateExp[0], $dateExp[1]);
        if ($diamonds == 1) {
            $builder = LogBalanceModel::suffix('log_jifen')->where('user_id', $this->uid);
        } else {
            $builder = LogBalanceModel::where('user_id', $this->uid);
        }
        if (!is_null($type) && $type > 0) {
            $operate = $type == 1 ? '+' : '-';
            $builder->where('operate', $operate);
        }
        $builder->whereBetween('created_at', [date('Y-m-d H:i:s', $dateArr['begin']), date('Y-m-d H:i:s', $dateArr['end'])]);
        $count = $builder->count();
        $items = $builder->orderBy('id', 'desc')->skip(($page - 1) * $size)->take($size)->get();
        $rows = [];
        if (!$items->isEmpty()) {
            foreach ($items as $k => $item) {
                $rows[] = [
                    'id' => $item->id,
                    'change_amount' => $item->operate . $item->change_amount,
                    'change_color' => $item->operate == '+' ? '#1AB5FF' : '#191919',
                    'amount' => intval($item->amount),
                    'created_at' => H::exchangeDateStr($item->created_at),
                    'desc' => $item->desc
                ];
            }
        }
        $res['items'] = $rows;
        $res['count'] = $count;
        return $this->jsonExit(200, 'OK', $res);
    }

    /*--------系统的配置信息----不需要登录----*/
    public function globalSettingsSign()
    {
        try {
            $res = [];
            $sms = SettingsModel::getSigConf('sms');
            $res['hosts'] = 'http://api.zfriend.cn';
            //客服域名
            $res['service'] = 'http://service.hfriend.cn';
            //隐私政策
            $res['privacy'] = CHANNEL == 'ios' ? 'http://www.hfriend.cn/cont/privacy' : 'http://www.hfriend.cn/cont/androidPrivacy';
            //会员政策
            $res['member'] = 'http://www.hfriend.cn/cont/member';
            //常见问题
            $res['question'] = 'http://www.hfriend.cn/question';
            //这里处理快捷登陆的问题  ===   可以删除其实
            if (isset($sms['login_fast']) && $sms['login_fast'] == 1) {
                //华为审核
                if (PLATFORM == 'huawei' && (intval(date('H')) >= 9 && intval(date('H')) <= 21)) {
                    $sms['login_fast'] = 0;
                }
                if (CHANNEL == 'ios' && (intval(date('H')) >= 21 || intval(date('H')) <= 6)) {
                    $sms['login_fast'] = 0;
                }
            }

            $res['login_fast'] = isset($sms['login_fast']) && $sms['login_fast'] == 1;
            //添加魔链分享字典
            $res['mlink'] = [
                'userinfo' => [
                    'qq' => 'userInfo-qq',
                    'wechat' => 'userInfo-wechat',
                ],
                'discover' => [
                    'qq' => 'discover-qq',
                    'wechat' => 'discover-wechat',
                ]
            ];
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    /*--------系统的配置信息----需要登录----*/
    public function globalSettings(Request $request)
    {
        try {
            $res = [];
            $check = SettingsModel::getSigConf('check');
            $base = SettingsModel::getSigConf('base');
            $sms = SettingsModel::getSigConf('sms');
            $userModel = UsersModel::find($this->uid);
            $res['im_key'] = config('latrell-rcloud.app_key');
            $sysMsg = UsersMsgSysModel::where('user_id', $this->uid)->orderBy('id', 'desc')->first();
            $sysGov = UsersMsgGovModel::where('status', 1)->orderBy('id', 'desc')->first();
            $res['im_server'] = [
                [
                    'user_id' => 100,
                    'pic_url' => 'http://static.hfriend.cn/vips/system.png',
                    'nick' => '官方消息',
                    'cont' => $sysGov ? $sysGov->title : '',
                ], [
                    'user_id' => 101,
                    'pic_url' => 'http://static.hfriend.cn/vips/active.png',
                    'nick' => '系统通知',
                    'cont' => $sysMsg ? $sysMsg->title : '',
                ]
            ];
            $res['user_coin'] = $userModel->sweet_coin;
            $res['contact_price'] = isset($check['contact_price']) ? intval($check['contact_price']) : 0;
            $res['super_show'] = isset($check['super_price']) ? intval($check['super_price']) : 0;
            //客服融云
            $res['service'] = [
                'server_id' => isset($base['service_id']) ? intval($base['service_id']) : 0,
                'pic_url' => 'http://static.hfriend.cn/vips/server.png',
                'nick' => '官方客服'
            ];
            $res['force_complete'] = isset($sms['force_complete']) ? intval($sms['force_complete']) : 0; //是否强制跳出资料完善页面
            $res['batch_say_hi_on'] = isset($sms['batch_say_hi_on']) ? intval($sms['batch_say_hi_on']) : 0; //是否弹出批量打招呼窗口
            $res['version_tip'] = isset($sms['update_on']) && $sms['update_on'] == 1 && isset($sms['update_ver']) && $sms['update_ver'] != VER;  //发现新版本弹窗
            $res['cmt_us'] = isset($sms['cmt_us_on']) && $sms['cmt_us_on'] == 1;  //评论我们
            $res['data_recover'] = isset($sms['recover_data']) && $sms['recover_data'] == 1;  //数据迁移
            //这里处理快捷登陆的问题  ===   可以删除其实
            if (isset($sms['login_fast']) && $sms['login_fast'] == 1) {
                //华为审核
                if (PLATFORM == 'huawei' && (intval(date('H')) >= 9 && intval(date('H')) <= 21)) {
                    $sms['login_fast'] = 0;
                }
                if (CHANNEL == 'ios' && (intval(date('H')) >= 21 || intval(date('H')) <= 6)) {
                    $sms['login_fast'] = 0;
                }
            }

            $res['login_fast'] = isset($sms['login_fast']) && $sms['login_fast'] == 1;
            $vip_center = isset($sms['vip_center']) && $sms['vip_center'] == 1;
            $res['vipcenter'] = $vip_center;
            $res['vipcenter_url'] = $vip_center ? 'http://www.hfriend.cn/vip/vipcenter' : '';
            $res['vipbuy_url'] = $vip_center ? 'http://www.hfriend.cn/vip/buy' : '';
            //添加关键词拦截
            $res['block_words'] = [
                [
                    'word' => '微信',
                    'tips' => '⚠️⚠️⚠️ 频繁恶意留外包联系方式，引流等，账号将被系统封禁，请勿转账，谨防被骗',
                ], [
                    'word' => '刷单',
                    'tips' => '⚠️⚠️⚠️ 温馨提示：凡涉及转账均为诈骗，切勿相信。若对方要求转账，请立即举报。请勿转账，谨防被骗',
                ], [
                    'word' => '兼职',
                    'tips' => '⚠️⚠️⚠️ 温馨提示：凡涉及转账均为诈骗，切勿相信。若对方要求转账，请立即举报。请勿转账，谨防被骗',
                ]
            ];
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    //购买联系方式
    public function unlockContact(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        try {
            DB::beginTransaction();
            $user = UsersModel::find($user_id);
            if (!$user || $user->status != 1) {
                return $this->jsonExit(209, '用户状态异常');
            }
            $settings = UsersSettingsModel::getUserSettings($user_id);
            if ($settings['hide_model'] == 1 && $this->uid != $user_id) {
                return $this->jsonExit(204, '用户隐身不能被查看');
            }
            $blackIdArr = UsersBlackListModel::getBlackIdArr($this->uid);
            if ($this->uid != $user_id && in_array($user_id, $blackIdArr)) {
                return $this->jsonExit(208, '黑名单用户不能被解锁');
            }
            //检查信息了
            $price = config('settings.contact_price');
            $self = UsersModel::find($this->uid);
            $self_profile = $self->profile;
            //兼容女会员私信不进行限制，可以无限私信
            $enough = $self->sweet_coin >= $price;  //分为金币充足与不足两个情况

            $isShow = false;
            //超级vip可以解锁
            if ($self_profile->vip_is > 0 || $this->uid == $user_id) {
                $isShow = true;
            }
            // 第一种情况：[金币不足且不是VIP]
            $exit = LogContactUnlockModel::where([['user_id', $this->uid], ['user_id_viewed', $user_id]])->first();
            //非vip
            $sub = SubscribeModel::getRightTimes($self_profile->vip_level);
            if ($self_profile->vip_is == 0 && !$exit) {
                //准备弹窗数据
                $alert = [
                    'sweet_coin' => $self->sweet_coin,
                    'price' => $price,
                    'tip_str' => '抱歉！当前心友币不足，请充值后解锁',
                    'invite_str' => '邀请好友免费解锁',
                    'can_unlock' => $enough,  //true支付  false充值
                    'pay_button_str' => $enough ? '立即解锁' : '立即充值',
                    'vip_button_str' => '开通VIP免费解锁',
                ];
                return $this->jsonExit(201, 'OK', $alert);
            }
            if ($self_profile->vip_is == 1 && HR::getUniqueNum($this->uid, 'users-view-contact-num') >= $sub['contact'] && !$exit) {
                $alert = [
                    'sweet_coin' => $self->sweet_coin,
                    'price' => $price,
                    'tip_str' => '抱歉！您的免费次数(' . $sub['contact'] . ')已用完，请付费解锁',
                    'invite_str' => '邀请好友免费解锁',
                    'can_unlock' => $enough,  //true支付  false充值
                    'pay_button_str' => $enough ? '立即解锁' : '立即充值',
                    'vip_button_str' => '升级VIP免费解锁权限',
                ];
                return $this->jsonExit(201, 'OK', $alert);
            }

            $timeLeft = 0;

            if ($exit) $isShow = true;
            if ($this->uid != $user_id && !$exit) {
                if (config('settings.contact_view_limit_on')) {
                    //在这路记录每日观看的用户次数
                    $scanNum = HR::getUniqueNum($this->uid, 'users-view-contact-num');
                    //如果是普通会员男[根据vip 等级确定次数]
                    $sub = SubscribeModel::getRightTimes($self_profile->vip_level);
                    if (!$exit && $scanNum >= $sub['contact']) {
                        return $this->jsonExit(209, '今日次数已超限，明天再来试试');
                    }
                    //因为先解锁在提示所以多减1
                    $leftNum = $sub['contact'] - $scanNum - 1;
                    $timeLeft = $leftNum > 0 ? $leftNum : 0;
                }

                //兼容互通解锁 ===== S
                LogContactUnlockModel::updateOrCreate([
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'channel' => 0,
                ], [
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'date' => date('Y-m-d'),
                    'channel' => 0,
                    'ip' => IP
                ]);
                //兼容互通解锁 ===== E
                HR::updateUniqueNum($this->uid, $user_id, 'users-view-contact-num');
                HR::updateUniqueNum($this->uid, $user_id, 'users-view-chat-num');
                $isShow = true;
            }
            //如果用户设置隐藏则全部不显示 *** E ***
            $baseData = [
                'user_id_me' => $this->uid,
                'user_id' => $user_id,
                'toast' => [
                    'is_show' => (!$exit && $timeLeft >= 0) ? 1 : 0,
                    'title' => '本次解锁免费',
                    'desc' => $timeLeft > 0 ? '免费解锁次数还剩余' . $timeLeft . '次' : '免费解锁机会已用完',
                    'left_time' => $timeLeft,
                ],
                'contact' => [
                    'together_unlock' => true,
                    'qq' => '**********',
                    'wechat' => '**********',
                ]
            ];
            if ($isShow) {
                $profile = $user->profile;
                $baseData['contact']['qq'] = empty($profile->qq) ? '暂未完善' : H::decrypt($profile->qq);
                $baseData['contact']['wechat'] = empty($profile->wechat) ? '暂未完善' : H::decrypt($profile->wechat);
            }
            DB::commit();
            //发送各种消息
            if ($settings['hide_unlock_push'] == 0) {
                JpushModel::JpushCheck($user_id, $self->nick, 0, 13, $this->uid);
            }
            return $this->jsonExit(200, 'OK', $baseData);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            DB::rollBack();
            return $this->jsonExit(208, $e->getMessage());
        }
    }


    public function unlockChat(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        try {
            DB::beginTransaction();
            $user = UsersModel::find($user_id);
            if (!$user || $user->status != 1) {
                return $this->jsonExit(209, '用户状态异常');
            }
            $settings = UsersSettingsModel::getUserSettings($user_id);
            if ($settings['hide_model'] == 1 && $this->uid != $user_id) {
                return $this->jsonExit(204, '用户隐身不能被查看');
            }
            $blackIdArr = UsersBlackListModel::getBlackIdArr($this->uid);
            if ($this->uid != $user_id && in_array($user_id, $blackIdArr)) {
                return $this->jsonExit(208, '黑名单用户不能被解锁');
            }
            //检查信息了
            $price = config('settings.chat_price');
            $self = UsersModel::find($this->uid);
            $self_profile = $self->profile;
            $enough = $self->sweet_coin >= $price;
            $isShow = false;
            //超级vip可以解锁
            if ($self_profile->vip_is > 0 || $this->uid == $user_id) {
                $isShow = true;
            }
            $sub = SubscribeModel::getRightTimes($self_profile->vip_level, $this->sex);
            $exit = LogContactUnlockModel::where([['user_id', $this->uid], ['user_id_viewed', $user_id]])->first();
            $exist = HR::existUniqueNum($this->uid, $user_id, 'users-im-num');
            if ($self_profile->vip_is == 0 && !$exit && !$exist) {
                //准备弹窗数据
                $alert = [
                    'sweet_coin' => $self->sweet_coin,
                    'price' => $price,
                    'tip_str' => '抱歉！当前心友币不足，请充值后解锁',
                    'invite_str' => '邀请好友免费解锁',
                    'can_unlock' => $enough,  //true支付  false充值
                    'pay_button_str' => $enough ? '立即解锁' : '立即充值',
                    'vip_button_str' => '开通VIP免费解锁',
                ];
                return $this->jsonExit(201, 'OK', $alert);
            }
            if ($self_profile->vip_is == 1 && HR::getUniqueNum($this->uid, 'users-view-chat-num') >= $sub['chat'] && !$exit && !$exist) {
                //准备弹窗数据
                $alert = [
                    'sweet_coin' => $self->sweet_coin,
                    'price' => $price,
                    'tip_str' => '抱歉！您的免费次数(' . $sub['chat'] . ')已用完，请付费解锁',
                    'invite_str' => '邀请好友免费解锁',
                    'can_unlock' => $enough,  //true支付  false充值
                    'pay_button_str' => $enough ? '立即解锁' : '立即充值',
                    'vip_button_str' => '升级VIP免费解锁权限',
                ];
                return $this->jsonExit(201, 'OK', $alert);
            }

            $timeLeft = 0;
            if ($exit) $isShow = true;
            if ($this->uid != $user_id && !$exit && !$exist) {
                if (config('settings.chat_limit_on')) {
                    //在这路记录每日观看的用户次数
                    $scanNum = HR::getUniqueNum($this->uid, 'users-view-chat-num');
                    $exist = HR::existUniqueNum($this->uid, $user_id, 'users-view-chat-num');
                    //如果是普通会员男[根据vip 等级确定次数]
                    if ($exist == 0 && $scanNum >= $sub['chat']) {
                        return $this->jsonExit(209, '今日次数已超限，明天再来试试');
                    }
                    //因为先解锁在提示所以多减1
                    if (!$exist) {
                        $leftNum = $sub['chat'] - $scanNum - 1;
                    } else {
                        $leftNum = $sub['chat'] - $scanNum;
                    }
                    $timeLeft = $leftNum > 0 ? $leftNum : 0;
                }
                LogContactUnlockModel::updateOrCreate([
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'channel' => 1,
                ], [
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'date' => date('Y-m-d'),
                    'channel' => 1,
                    'ip' => IP
                ]);
                HR::updateUniqueNum($this->uid, $user_id, 'users-view-chat-num');
                HR::updateUniqueNum($this->uid, $user_id, 'users-view-contact-num');
                $isShow = true;
            }
            //如果用户设置隐藏则全部不显示 *** E ***
            $baseData = [
                'user_id_me' => $this->uid,
                'user_id' => $user_id,
                'toast' => [
                    'is_show' => (!$exit && $timeLeft >= 0) ? 1 : 0,
                    'title' => '本次解锁免费',
                    'desc' => $timeLeft > 0 ? '免费解锁次数还剩余' . $timeLeft . '次' : '免费解锁机会已用完',
                    'left_time' => $timeLeft,
                ],
                'contact' => [
                    'together_unlock' => true,
                    'qq' => '**********',
                    'wechat' => '**********',
                ]
            ];
            if ($isShow) {
                $profile = $user->profile;
                $baseData['contact']['qq'] = empty($profile->qq) ? '暂未完善' : H::decrypt($profile->qq);
                $baseData['contact']['wechat'] = empty($profile->wechat) ? '暂未完善' : H::decrypt($profile->wechat);
            }
            DB::commit();
            return $this->jsonExit(200, 'OK', $baseData);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            DB::rollBack();
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    /*-----购买解锁次数------*/
    public function buyContactNum(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 'contact');
        $profile = UsersProfileModel::where('user_id', $user_id)->first();
        if (!$profile) {
            return $this->jsonExit(201, '用户状态异常');
        }
        if (empty($profile->qq) && empty($profile->wechat) && $type == 'contact') {
            return $this->jsonExit(202, '联系方式未完善不能解锁');
        }
        $settings = UsersSettingsModel::getUserSettings($user_id);
        if ($settings['hide_model'] == 1 && $this->uid != $user_id) {
            return $this->jsonExit(204, '用户隐身不能被查看');
        }
        $blackIdArr = UsersBlackListModel::getBlackIdArr($this->uid);
        if ($this->uid != $user_id && in_array($user_id, $blackIdArr)) {
            return $this->jsonExit(208, '黑名单用户不能被解锁');
        }
        $self_user = UsersModel::where('id', $this->uid)->first();
        $price = config('settings.contact_price');
        if ($price > 0 && $self_user->sweet_coin < $price) {
            return $this->jsonExit(202, '友币不足');
        }
        try {
            $exit = LogContactUnlockModel::where([['user_id', $this->uid], ['user_id_viewed', $user_id], ['channel', 0]])->first();
            if (!$exit) {
                DB::beginTransaction();
                //购买了需要记录解锁记录下次直接展示
                //兼容互通解锁 ===== S
                LogContactUnlockModel::updateOrCreate([
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'channel' => 0,
                ], [
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'date' => date('Y-m-d'),
                    'ip' => IP,
                    'channel' => 0,
                    'type' => 1,
                ]);

                //兼容互通解锁 ===== E
                //开始处理扣费逻辑
                $before = $self_user->sweet_coin;
                $amount = $self_user->sweet_coin - $price;
                $desc = "解锁联系方式消费";
                $remark = "解锁 " . $user_id . " 联系方式，消耗友币{$price}个";
                $type_tag = 'buy_contact';
                LogBalanceModel::gainLogBalance($this->uid, $before, $price, $amount, $type_tag, $desc, $remark);
                $self_user->sweet_coin = $amount;
                $self_user->save();
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        $baseData = [
            'user_id_me' => $this->uid,
            'user_id' => $user_id,
            'contact' => [
                'together_unlock' => true,
                'qq' => '**********',
                'wechat' => '**********',
            ]
        ];
        $baseData['contact']['qq'] = empty($profile->qq) ? '暂未完善' : H::decrypt($profile->qq);
        $baseData['contact']['wechat'] = empty($profile->wechat) ? '暂未完善' : H::decrypt($profile->wechat);
        //发送解锁消息
        if ($settings['hide_unlock_push'] == 0 && !$exit) {
            $user = UsersModel::getUserInfo($user_id);
            JpushModel::JpushCheck($user_id, $user->nick, 0, 13, $this->uid);
        }
        return $this->jsonExit(200, 'OK', $baseData);
    }


    public function buyChatNum(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $profile = UsersProfileModel::where('user_id', $user_id)->first();
        if (!$profile) {
            return $this->jsonExit(201, '用户状态异常');
        }
        $settings = UsersSettingsModel::getUserSettings($user_id);
        if ($settings['hide_model'] == 1 && $this->uid != $user_id) {
            return $this->jsonExit(204, '用户隐身不能被查看');
        }
        $blackIdArr = UsersBlackListModel::getBlackIdArr($this->uid);
        if ($this->uid != $user_id && in_array($user_id, $blackIdArr)) {
            return $this->jsonExit(208, '黑名单用户不能被解锁');
        }
        $self_user = UsersModel::where('id', $this->uid)->first();
        $price = config('settings.chat_price');
        if ($price > 0 && $self_user->sweet_coin < $price) {
            return $this->jsonExit(202, '友币不足');
        }
        try {
            $exit = LogContactUnlockModel::where([['user_id', $this->uid], ['user_id_viewed', $user_id], ['channel', 1]])->first();
            if (!$exit) {
                DB::beginTransaction();
                //购买了需要记录解锁记录下次直接展示
                LogContactUnlockModel::updateOrCreate([
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'channel' => 1,
                ], [
                    'user_id' => $this->uid,
                    'user_id_viewed' => $user_id,
                    'date' => date('Y-m-d'),
                    'ip' => IP,
                    'channel' => 1,
                    'type' => 1,
                ]);
                //兼容互通解锁 ===== E
                //开始处理扣费逻辑
                $before = $self_user->sweet_coin;
                $amount = $self_user->sweet_coin - $price;
                $desc = "解锁私信消费";
                $remark = "解锁 " . $user_id . " 私信，消耗友币{$price}个";
                $type_tag = 'buy_chat';
                LogBalanceModel::gainLogBalance($this->uid, $before, $price, $amount, $type_tag, $desc, $remark);
                $self_user->sweet_coin = $amount;
                $self_user->save();
                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        $baseData = [
            'user_id_me' => $this->uid,
            'user_id' => $user_id,
            'contact' => [
                'together_unlock' => true,
                'qq' => '**********',
                'wechat' => '**********',
            ]
        ];
        $baseData['contact']['qq'] = empty($profile->qq) ? '暂未完善' : H::decrypt($profile->qq);
        $baseData['contact']['wechat'] = empty($profile->wechat) ? '暂未完善' : H::decrypt($profile->wechat);
        return $this->jsonExit(200, 'OK', $baseData);
    }


    public function unlockImContact(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        try {
            $user = UsersModel::find($user_id);
            if (!$user || $user->status != 1) {
                return $this->jsonExit(209, '用户状态异常');
            }
            $settings = UsersSettingsModel::getUserSettings($user_id);
            if ($settings['hide_model'] == 1 && $this->uid != $user_id) {
                return $this->jsonExit(204, '用户隐身不能被查看');
            }
            $blackIdArr = UsersBlackListModel::getBlackIdArr($this->uid);
            if ($this->uid != $user_id && in_array($user_id, $blackIdArr)) {
                return $this->jsonExit(208, '黑名单用户不能被解锁');
            }
            if ($this->uid == $user_id) {
                return $this->jsonExit(209, '解锁用户不能是自己');
            }
            //检查信息了
            $price = config('settings.im_price');
            $self = UsersModel::find($this->uid);
            $self_profile = $self->profile;
            $enough = $self->sweet_coin >= $price;

            //限制男女  女
            $free_vip = 1;
            $free = 2;
            if ($this->sex == 1) {
                $free = 30;
                $free_vip = 50;
            }
            //限制男女  男
            if ($this->sex == 2) {
                $free = 2;
                $free_vip = 50;
            }


            $scanNum = HR::getUniqueNum($this->uid, 'users-im-num');
            //解锁过的就不再限制
            $exit = LogContactUnlockModel::where([['user_id', $this->uid], ['user_id_viewed', $user_id]])->first();
            $exist = HR::existUniqueNum($this->uid, $user_id, 'users-im-num');
            //非VIP 免费次数用完
            if ($self_profile->vip_is == 0 && $scanNum >= $free && !$exist && !$exit) {
                //准备弹窗数据
                $alert = [
                    'sweet_coin' => $self->sweet_coin,
                    'price' => $price,
                    'tip_str' => '抱歉！当前心友币不足，请充值后解锁',
                    'invite_str' => '邀请好友免费解锁',
                    'can_unlock' => $enough,  //true支付  false充值
                    'pay_button_str' => $enough ? '立即解锁' : '立即充值',
                    'vip_button_str' => '开通VIP免费解锁',
                ];
                return $this->jsonExit(201, 'OK', $alert);
            }
            //VIP 免费次数用完
            if ($self_profile->vip_is == 1 && $scanNum >= $free_vip && !$exist && !$exit) {
                $alert = [
                    'sweet_coin' => $self->sweet_coin,
                    'price' => $price,
                    'tip_str' => '抱歉！您的免费次数(' . $free_vip . ')已用完，请付费解锁',
                    'invite_str' => '邀请好友免费解锁',
                    'can_unlock' => $enough,  //true支付  false充值
                    'pay_button_str' => $enough ? '立即解锁' : '立即充值',
                    'vip_button_str' => '升级VIP免费解锁权限',
                ];
                return $this->jsonExit(201, 'OK', $alert);
            }
            //解锁时效每3天清理一次
            HR::updateUniqueNum($this->uid, $user_id, 'users-im-num', true, H::leftTime() + 86400 * 3);
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    //购买im解锁次数
    public function buyImContact(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $profile = UsersProfileModel::where('user_id', $user_id)->first();
        if (!$profile) {
            return $this->jsonExit(201, '用户状态异常');
        }
        $settings = UsersSettingsModel::getUserSettings($user_id);
        if ($settings['hide_model'] == 1 && $this->uid != $user_id) {
            return $this->jsonExit(204, '用户隐身不能被查看');
        }
        $blackIdArr = UsersBlackListModel::getBlackIdArr($this->uid);
        if ($this->uid != $user_id && in_array($user_id, $blackIdArr)) {
            return $this->jsonExit(208, '黑名单用户不能被解锁');
        }
        $self_user = UsersModel::where('id', $this->uid)->first();
        $price = config('settings.im_price');
        if ($price > 0 && $self_user->sweet_coin < $price) {
            return $this->jsonExit(202, '友币不足');
        }
        try {
            $exist = HR::existUniqueNum($this->uid, $user_id, 'users-im-num');
            if (!$exist) {
                DB::beginTransaction();
                //兼容互通解锁 ===== E
                //开始处理扣费逻辑
                $before = $self_user->sweet_coin;
                $amount = $self_user->sweet_coin - $price;
                $desc = "解锁私信im聊天消费";
                $remark = "解锁 " . $user_id . " im 聊天，消耗友币{$price}个";
                $type_tag = 'buy_im';
                LogBalanceModel::gainLogBalance($this->uid, $before, $price, $amount, $type_tag, $desc, $remark);
                $self_user->sweet_coin = $amount;
                $self_user->save();
                DB::commit();
                HR::updateUniqueNum($this->uid, $user_id, 'users-im-num', true, H::leftTime() + 86400 * 3);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }


    public function sendInvite(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 'contact');
        if ($this->uid == $user_id) {
            return $this->jsonExit(200, '邀请成功');
        }
        //添加判断
        $tar_profile = UsersProfileModel::where('user_id', $user_id)->first();
        if (!$tar_profile) {
            return $this->jsonExit(201, '用户状态异常');
        }
        if ($type == 'contact' && !empty($tar_profile->qq) && !empty($tar_profile->wechat)) {
            return $this->jsonExit(202, '用户资料已完善，无需邀请');
        }
        if ($type == 'auth' && $tar_profile->real_is == 1 && $tar_profile->identity_is == 1) {
            return $this->jsonExit(203, '用户已完成认证，无需邀请');
        }
        try {
            $invite = LogSendInviteModel::where([['type', $type], ['user_id', $this->uid], ['target_user_id', $user_id]])->first();
            if (!$invite) {
                LogSendInviteModel::create([
                    'type' => $type,
                    'user_id' => $this->uid,
                    'target_user_id' => $user_id,
                    'date' => date('Y-m-d'),
                ]);
                $user = UsersModel::where('id', $this->uid)->first();
                //发送邀请内容 【短信】
                $tarUser = UsersModel::where('id', $user_id)->first();
                $msg_type = $type == 'contact' ? 'invite_contact' : 'invite_auth';
                LogSmsModel::sendMsg(H::decrypt($tarUser->mobile), $msg_type, $user->nick);
                //发送极光推送 【极光+系统im 消息】
                $msg_code = $type == 'contact' ? 25 : 24;
                JpushModel::JpushCheck($user_id, $user->nick, 0, $msg_code);
                //发送系统消息通知[已经在极光推送中发送过了]
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
        return $this->jsonExit(200, '邀请发送成功');
    }


    /*--未完善的资料获取-这里只体现关键的信息部分-*/
    public function incompleteInfoGet(Request $request)
    {
        $res = [];
        $user = UsersModel::find($this->uid);
        $profile = $user->profile;
        $avatar = $user->avatar;
        $option = config('self.options');
        $ava = stripos($avatar, '/ava/') === false;
        if (!$ava) $res[] = [
            'column' => 'avatar',
            'title' => '设置头像',
            'value' => $ava,
        ];
        //身高
        if (empty($profile->stature)) $res[] = [
            'column' => 'stature',
            'title' => '您的身高是多少CM？',
            'value' => !empty($profile->stature),
            'map' => $option['stature']
        ];
        //体重
        if (empty($profile->weight)) $res[] = [
            'column' => 'weight',
            'title' => '您的体重是多少KG？',
            'value' => !empty($profile->weight),
            'map' => $option['weight']
        ];
        if (empty($profile->qq)) $res[] = [
            'column' => 'qq',
            'title' => '您的QQ是多少？',
            'value' => !empty($profile->qq),
        ];
        if (empty($profile->wechat)) $res[] = [
            'column' => 'wechat',
            'title' => '您的微信号是什么？',
            'value' => !empty($profile->wechat),
        ];
        //体型
        if (empty($profile->somatotype)) $res[] = [
            'column' => 'somatotype',
            'title' => '您的体型是什么样子？',
            'value' => !empty($profile->somatotype),
            'map' => $option['somatotype']
        ];
        //职业
        $profession = [
            'profession' => config('self.profession'),
            'max' => 1,
            'min' => 1,
        ];
        if (empty($profile->profession)) $res[] = [
            'column' => 'profession',
            'title' => '你从事的职业是？',
            'value' => !empty($profile->profession),
            'map' => $profession
        ];
        //魅力部位
        if (empty($profile->charm)) $res[] = [
            'column' => 'charm',
            'title' => '你的魅力部位是哪里？',
            'value' => !empty($profile->charm),
            'map' => $option['charm']
        ];
        //收入
        if (empty($profile->salary)) $res[] = [
            'column' => 'salary',
            'title' => '您的收入是多少？',
            'value' => !empty($profile->salary),
            'map' => $option['salary']
        ];
        //学历
        if (empty($profile->degree)) $res[] = [
            'column' => 'degree',
            'title' => '您的学历是什么？',
            'value' => !empty($profile->degree),
            'map' => $option['degree']
        ];
        //家乡
        if (empty($profile->hometown)) $res[] = [
            'column' => 'hometown',
            'title' => '您的家乡是哪里？',
            'value' => !empty($profile->hometown),
        ];
        //感情状态
        if (empty($profile->marriage)) $res[] = [
            'column' => 'marriage',
            'title' => '您的感情状态是什么样的？',
            'value' => !empty($profile->marriage),
            'map' => $option['marriage']
        ];
        if (empty($profile->house)) $res[] = [
            'column' => 'house',
            'title' => '您的居住状态是什么样？',
            'value' => !empty($profile->house),
            'map' => $option['house']
        ];
        if (empty($profile->cohabitation)) $res[] = [
            'column' => 'cohabitation',
            'title' => '您对婚前同居的态度是？',
            'value' => !empty($profile->cohabitation),
            'map' => $option['cohabitation']
        ];
        if (empty($profile->dating)) $res[] = [
            'column' => 'dating',
            'title' => '您接收约会吗？',
            'value' => !empty($profile->dating),
            'map' => $option['dating']
        ];
        if (empty($profile->purchase_house)) $res[] = [
            'column' => 'purchase_house',
            'title' => '您是否购房了？',
            'value' => !empty($profile->purchase_house),
            'map' => $option['purchase_house']
        ];
        if (empty($profile->purchase_car)) $res[] = [
            'column' => 'purchase_car',
            'title' => '您是否已经购车？',
            'value' => !empty($profile->purchase_car),
            'map' => $option['purchase_car']
        ];
        if (empty($profile->drink)) $res[] = [
            'column' => 'drink',
            'title' => '您喝酒吗？',
            'value' => !empty($profile->drink),
            'map' => $option['drink']
        ];
        if (empty($profile->smoke)) $res[] = [
            'column' => 'smoke',
            'title' => '您抽烟吗？',
            'value' => !empty($profile->smoke),
            'map' => $option['smoke']
        ];
        if (empty($profile->cook)) $res[] = [
            'column' => 'cook',
            'title' => '您会做饭吗？',
            'value' => !empty($profile->cook),
            'map' => $option['cook']
        ];
        if (empty($profile->relationship)) $res[] = [
            'column' => 'relationship',
            'title' => '您期待的关系是？',
            'value' => !empty($profile->relationship),
            'map' => $option['relationship']
        ];
        //感情状态
        $hobbySet = empty($profile->hobby_sport) || empty($profile->hobby_music) || empty($profile->hobby_food) || empty($profile->hobby_movie) || empty($profile->hobby_book) || empty($profile->hobby_footprint);
        $hobby = [];
        if (empty($profile->hobby_sport)) {
            $arr = [];
            if ($profile->hobby_sport) {
                foreach ($profile->hobby_sport as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
            }
            $arrData = [
                'column' => 'hobby_sport',
                'key' => '喜欢的运动',
                'value' => $arr,
                'map' => $option['hobby_sport'],
            ];
            $hobby[] = $arrData;
        }
        if (empty($profile->hobby_food)) {
            $arr = [];
            if ($profile->hobby_food) {
                foreach ($profile->hobby_food as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
            }
            $arrData = [
                'column' => 'hobby_food',
                'key' => '喜欢的美食',
                'value' => $arr,
                'map' => $option['hobby_food'],
            ];
            $hobby[] = $arrData;
        }
        if (empty($profile->hobby_music)) {
            $arr = [];
            if ($profile->hobby_music) {
                foreach ($profile->hobby_music as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
            }
            $arrData = [
                'column' => 'hobby_music',
                'key' => '喜欢的音乐',
                'value' => $arr,
                'map' => $option['hobby_music'],
            ];
            $hobby[] = $arrData;
        }
        if (empty($profile->hobby_movie)) {
            $arr = [];
            if ($profile->hobby_movie) {
                foreach ($profile->hobby_movie as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
            }
            $arrData = [
                'column' => 'hobby_movie',
                'key' => '喜欢的电影',
                'value' => $arr,
                'map' => $option['hobby_movie'],
            ];
            $hobby[] = $arrData;
        }
        if (empty($profile->hobby_book)) {
            $arr = [];
            if ($profile->hobby_book) {
                foreach ($profile->hobby_book as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
            }
            $arrData = [
                'column' => 'hobby_book',
                'key' => '喜欢的阅读',
                'value' => $arr,
                'map' => $option['hobby_book'],
            ];
            $hobby[] = $arrData;
        }
        if (empty($profile->hobby_footprint)) {
            $arr = [];
            if ($profile->hobby_footprint) {
                foreach ($profile->hobby_footprint as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
            }
            $arrData = [
                'column' => 'hobby_footprint',
                'key' => '喜欢城市',
                'value' => $arr,
                'map' => $option['hobby_footprint'],
            ];
            $hobby[] = $arrData;
        }

        if ($hobbySet) $res[] = [
            'column' => 'hobby',
            'title' => '您的兴趣爱好是？',
            'value' => false,
            'hobby' => $hobby
        ];

        if (empty($profile->expect_stature)) $res[] = [
            'column' => 'expect_stature',
            'title' => '期待对象的身高是多少CM？',
            'value' => !empty($profile->expect_stature),
            'map' => $option['expect_stature']
        ];
        if (empty($profile->expect_age)) $res[] = [
            'column' => 'expect_age',
            'title' => '期待对象的年龄是多少岁？',
            'value' => !empty($profile->expect_age),
            'map' => $option['expect_age']
        ];
        if (empty($profile->expect_degree)) $res[] = [
            'column' => 'expect_degree',
            'title' => '期待对象的学历是什么？',
            'value' => !empty($profile->expect_degree),
            'map' => $option['expect_degree']
        ];
        if (empty($profile->expect_salary)) $res[] = [
            'column' => 'expect_salary',
            'title' => '期待对象的年收入是？',
            'value' => !empty($profile->expect_salary),
            'map' => $option['expect_salary']
        ];
        if (empty($profile->expect_hometown)) $res[] = [
            'column' => 'expect_hometown',
            'title' => '期待对象的家乡是？',
            'value' => !empty($profile->expect_hometown),
        ];
        if (empty($profile->expect_live_addr)) $res[] = [
            'column' => 'expect_live_addr',
            'title' => '期待对象的常住地是？',
            'value' => !empty($profile->expect_live_addr),
        ];
        //切分数组只返回一个
        if (sizeof($res) > 6) {
            $res = array_splice($res, 0, 6);
        }
        $data = [
            'title' => '完成可获得 23 心友币',
            'items' => $res
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function sysReward()
    {
        $reward = config('self.reward_list');
        foreach ($reward as $key => &$rwd) {
            if ($key == 'new') {
                //新手的话查询新手任务情况
                $setting = UsersSettingsModel::getUserSettings($this->uid);
                foreach ($rwd as $k => &$item) {
                    if (isset($setting[$item['name']]) && $setting[$item['name']] == 1) $item['finish'] = true;
                    $item['jump'] = UsersMsgModel::schemeUrl('', $item['jump_scheme'], $item['title'], 0, '');
                }
            }
            if ($key == 'normal') {
                foreach ($rwd as $k => &$item) {
                    if (HR::getUniqueNum($this->uid, 'users-' . $item['name']) > 0) $item['finish'] = true;
                    $item['jump'] = UsersMsgModel::schemeUrl('', $item['jump_scheme'], $item['title'], 0, '');
                }
            }
        }
        //规整数据返回
        $res = [
            [
                'title' => '每日任务',
                'items' => $reward['normal'],
            ], [
                'title' => '新手任务',
                'items' => $reward['new'],
            ]
        ];
        return $this->jsonExit(200, 'OK', $res);
    }


    public function sysMsg(Request $request)
    {

    }

}
