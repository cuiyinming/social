<?php

namespace App\Http\Controllers;

use App\Components\ESearch\ESearch;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Helpers\{H, S};
use App\Http\Models\Payment\Callback\CallbackWechatModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\PaymentOrderModel;
use App\Http\Models\Payment\Callback\CallbackAlipayModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Payment\Contracts\IPayNotify;
use Payment\Exceptions\ClassNotFoundException;
use Payment\Exceptions\GatewayException;
use \Payment\Client;
use RongCloud;

class NotifyController implements IPayNotify
{
    /**
     * 处理自己的业务逻辑，如更新交易状态、保存通知数据等等
     * @param string $channel 通知的渠道，如：支付宝、微信、招商
     * @param string $notifyType 通知的类型，如：支付、退款
     * @param string $notifyWay 通知的方式，如：异步 async，同步 sync
     * @param array $notifyData 通知的数据
     * @return bool
     */
    public function handle(string $channel, string $notifyType, string $notifyWay, array $notifyData)
    {
        //file_put_contents('/tmp/paylog.log', print_r($notifyData, 1) . PHP_EOL, FILE_APPEND);
        if (!empty($notifyData)) {
            try {
                if (isset($notifyData['openid'])) {
                    $pay_channel = 'wechat';
                    $pay_name = '微信';
                    $payFor = $notifyData['attach'] ?? 'payment';
                    CallbackWechatModel::getWechatCallBack($notifyData);
                } else {
                    $pay_channel = 'alipay';
                    $pay_name = '支付宝';
                    $payFor = $notifyData['passback_params'] ?? 'payment';
                    CallbackAlipayModel::getAlipayCallBack($notifyData);
                }
                #更新充值的具体结果(防止刷余额)
                $order = PaymentOrderModel::where([['order_no', $notifyData['out_trade_no']], ['payment', $pay_channel]])->first();
                if ($order->status == 0) {
                    $order->log_sn = $pay_channel == 'alipay' ? $notifyData['trade_no'] : $notifyData['transaction_id'];
                    $order->paid_at = date('Y-m-d H:i:s');
                    $order->status = 1;
                    $order->save();
                    file_put_contents('/tmp/debug.log', print_r([$payFor], 1) . PHP_EOL, 8);
                    //开始加值
                    if ($payFor == 'vip') {
                        $profile = UsersProfileModel::where('user_id', $order->user_id)->first();
                        $res = S::getPriceAndTime($order->relate_id, $profile->vip_exp_time, 'android');
                        file_put_contents('/tmp/debug.log', print_r([$res], 1) . PHP_EOL, 8);
                        if ($res['price'] > 0 && $res['price'] == $order->amount) {
                            $profile->vip_is = 1;
                            $profile->vip_level = $res['level'];
                            $profile->vip_exp_time = $res['time'];
                            if (empty($profile->vip_at)) $profile->vip_at = date('Y-m-d H:i:s');
                            $profile->vip_level_last = $res['level'];
                            $esVipArr[] = [
                                'id' => $order->user_id,
                                'vip_is' => 1,
                                'vip_level' => $res['level'],
                            ];
                            (new ESearch('users:users'))->updateSingle($esVipArr);
                            $profile->save();

                            //通知及钉钉
                            $msgTitle = "VIP购买";
                            $msg = "### 订阅订单"
                                . " \n\n > 应用"
                                . " \n\n > 用户：" . $order->user_id
                                . " \n\n > 系统：安卓"
                                . " \n\n > 价格：" . $order->amount
                                . " \n\n > 平台：" . $pay_name
                                . " \n\n > 项目：订阅";
                            (new DingTalk(env('ES_PUSH')))->sendMdMessage($msgTitle, $msg);
                            //极光推送
                            JpushModel::JpushCheck($order->user_id, '', 0, 17);
                            $title = 'VIP订单处理完成';
                            $cont = '您的VIP购买订单已经处理完成，请注意VIP权益变化。';
                            $sysMsg = ['content' => $title, 'title' => $cont, 'extra' => ""];
                            UsersMsgSysModel::updateOrCreate([
                                'user_id' => $order->user_id,
                                'event_id' => $order->id,
                                'event' => 'vip_buy_end',
                            ], [
                                'title' => $title,
                                'cont' => $cont,
                            ]);
                            RongCloud::messageSystemPublish(101, [$order->user_id], 'RC:TxtMsg', json_encode($sysMsg));
                        }
                    }
                    if ($payFor == 'recharge') {
                        $res = S::getPriceAndCoin($order->relate_id);
                        if ($res['price'] > 0 && $res['price'] == $order->amount) {
                            //增加用户的友币
                            $user_id = $order->user_id;
                            $change = $res['diamond'];
                            $user = UsersModel::getUserInfo($user_id);
                            $before_sweet_coin = $user->sweet_coin;
                            $user->sweet_coin += $change;
                            //增加累计充值友币金额
                            $user->sweet_coin_grand += $change;
                            $user->save();
                            //添加购买记录
                            $lastData = date('Y-m-d H:i:s');
                            $remark = "用户{$user_id}在{$lastData}充值" . $change . "个友币，安卓端{$pay_name}";
                            $desc = "充值友币" . $res['diamond'] . "个";
                            LogBalanceModel::gainLogBalance($user_id, $before_sweet_coin, $change, $user->sweet_coin, 'recharge_diamond', $desc, $remark);

                            //通知及钉钉
                            $msgTitle = "内购购买";
                            $msg = "### 内购订单"
                                . " \n\n > 应用"
                                . " \n\n > 用户：" . $order->user_id
                                . " \n\n > 系统：安卓"
                                . " \n\n > 价格：" . $order->amount
                                . " \n\n > 平台：" . $pay_name
                                . " \n\n > 项目：内购";
                            (new DingTalk(env('ES_PUSH')))->sendMdMessage($msgTitle, $msg);
                            //极光推送
                            JpushModel::JpushCheck($user_id, $change, 0, 19);
                            //推送融云系统消息
                            $title = "友币购买订单处理完成";
                            $cont = "您购买的友币已经充值到您的账户，请注意友币数量变化。";
                            UsersMsgSysModel::updateOrCreate([
                                'user_id' => $order->user_id,
                                'event_id' => $order->id,
                                'event' => 'vip_buy_end',
                            ], [
                                'title' => $title,
                                'cont' => $cont,
                            ]);
                            $sysMsg = ['content' => $title, 'title' => $cont, 'extra' => ""];
                            RongCloud::messageSystemPublish(101, [$user_id], 'RC:TxtMsg', json_encode($sysMsg));

                        }
                    }
                    //处理代理分润
                    \App\Jobs\clientLiquidation::dispatch($order, 'android_pay')->delay(now()->addSeconds(20))->onQueue('im');
                } else {
                    //file_put_contents('/tmp/charge_log.log', $order->user_id . '用户状态异常充值失败' . PHP_EOL, FILE_APPEND);
                }
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }
        }
        return true;
    }
}

class PaymentController extends Controller
{
    public function notifyAlipay(Request $request)
    {
        $config = config('subscribe.alipay');
        // 实例化继承了接口的类
        $callback = new NotifyController();
        try {
            $client = new Client(Client::ALIPAY, $config);
            $xml = $client->notify($callback);
            echo $xml;
        } catch (GatewayException $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage();
            exit;
        } catch (ClassNotFoundException $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage();
            exit;
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage();
            exit;
        }
    }

    public function notifyWechat(Request $request)
    {
        $config = config('subscribe.wechat');
        // 实例化继承了接口的类
        $callback = new NotifyController();
        try {
            $client = new Client(Client::WECHAT, $config);
            $xml = $client->notify($callback);
            echo $xml;
        } catch (GatewayException $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage();
            exit;
        } catch (ClassNotFoundException $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage();
            exit;
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage();
            exit;
        }
    }
}
