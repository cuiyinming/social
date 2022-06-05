<?php

namespace App\Console\Commands\Report;

use App\Http\Libraries\Sms\DingTalk;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Logs\LogBrowseModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Payment\PaymentOrderModel;
use App\Http\Models\Report\ReportBrowseModel;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\CommonModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{R, HR};

class report extends Command
{

    protected $signature = 'report:daily {type?} {date?}';
    protected $description = '每日数据报表生成，需要在次日凌晨执行 【报表当天执行】';

    protected $date = null;
    protected $start = null;
    protected $end = null;
    protected $is_today = false;

    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $type = $this->argument('type') ?: 0;

        $this->date = $this->argument('date') ?: date('Y-m-d');
        $this->start = date('Y-m-d 00:00:00', strtotime($this->date));
        $this->end = date('Y-m-d 23:59:59', strtotime($this->date));
        $this->is_today = date('Y-m-d') == $this->date;

        if (in_array($type, [0, 1])) {
            //每日基础数据日报
            $this->_baseReport();
        }
        if (in_array($type, [0, 2])) {
            //每日浏览日报
//            $this->_baseBrowse();
        }
        if (in_array($type, [0, 3])) {
            //每日更新用户登录设备数量统计 [每两天更新一次]
            if (date('d') % 2 == 1) {
                $this->_baseDevice();
            }
        }
    }


    /*----用户报表支付基础维度报表----**/
    private function _baseReport()
    {
        try {
            $date = $this->date;
            $startTime = $this->start;
            $endTime = $this->end;
            //注册人数
            $register_female = UsersModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['sex', 1]])->count();
            $register_male = UsersModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['sex', 2]])->count();
            $register = UsersModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $register_android = UsersProfileModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['register_channel', 'android']])->count();
            $register_ios = UsersProfileModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['register_channel', 'ios']])->count();
            //开通vip 人数
            $vip_num = UsersProfileModel::where([['vip_at', '>', $startTime], ['vip_at', '<', $endTime]])->count();
            //认证人数
            $authed = UsersProfileModel::where([['real_at', '>', $startTime], ['real_at', '<', $endTime]])->count();
            $authed_android = UsersProfileModel::where([['real_at', '>', $startTime], ['real_at', '<', $endTime], ['register_channel', 'android']])->count();
            $authed_ios = UsersProfileModel::where([['real_at', '>', $startTime], ['real_at', '<', $endTime], ['register_channel', 'ios']])->count();
            //实名人数
            $identity = UsersProfileModel::where([['identity_at', '>', $startTime], ['identity_at', '<', $endTime]])->count();
            $identity_android = UsersProfileModel::where([['identity_ended_at', '>', $startTime], ['identity_ended_at', '<', $endTime], ['register_channel', 'android']])->count();
            $identity_ios = UsersProfileModel::where([['identity_ended_at', '>', $startTime], ['identity_ended_at', '<', $endTime], ['register_channel', 'ios']])->count();
            //女神/帅哥人数
            $goddess = UsersProfileModel::where([['goddess_at', '>', $startTime], ['goddess_at', '<', $endTime]])->count();
            //封禁人数
            $lockBuilder = UsersModel::where([['locked_at', '>', $startTime], ['locked_at', '<', $endTime]]);
            $locked = $lockBuilder->count();
            $lockIdArr = $lockBuilder->pluck('id')->toArray();
            $locked_android = UsersProfileModel::whereIn('user_id', $lockIdArr)->where('register_channel', 'android')->count();
            $locked_ios = UsersProfileModel::whereIn('user_id', $lockIdArr)->where('register_channel', 'ios')->count();

            //充值人数
            $recharge_ios = OrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 0], ['status', 1]])->count();
            $recharge_android = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 0], ['status', 1]])->count();
            $recharge_android_alipay = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 0], ['status', 1], ['payment', 'alipay']])->count();
            $recharge_android_wechat = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 0], ['status', 1], ['payment', 'wechat']])->count();
            $recharge = $recharge_ios + $recharge_android;
            //按性别统计数据
            $recharge_male = $recharge_female = $inner_male = $inner_female = $recharge_amount_male = $recharge_amount_female = $inner_amount_male = $inner_amount_female = $total_amount_male = $total_amount_female = 0;

            $tt_order = OrderModel::select(DB::Raw("count(`soul_order`.`id`) as tt,sum(`soul_order`.`amount`) as sum,soul_users.sex,soul_order.type"))
                ->where([
                    ['order.created_at', '>', $startTime],
                    ['order.created_at', '<', $endTime],
                    ['order.status', 1],
                ])
                ->leftjoin('users', 'users.id', '=', 'order.user_id')
                ->groupBy('users.sex', 'order.type')
                ->get();
            $tt_payment = PaymentOrderModel::select(DB::Raw("count(`soul_payment_order`.`id`) as tt,sum(`soul_payment_order`.`amount`) as sum,soul_users.sex,soul_payment_order.type"))
                ->where([
                    ['payment_order.created_at', '>', $startTime],
                    ['payment_order.created_at', '<', $endTime],
                    ['payment_order.status', 1],
                ])
                ->leftjoin('users', 'users.id', '=', 'payment_order.user_id')
                ->groupBy('users.sex', 'payment_order.type')
                ->get();
            if (!$tt_order->isEmpty()) {
                foreach ($tt_order as $order) {
                    //内购订单数
                    if ($order->type == 1) {
                        if ($order->sex == 1) {
                            $inner_female += $order->tt;
                        }
                        if ($order->sex == 2) {
                            $inner_male += $order->tt;
                        }
                    }
                    //vip 订单数
                    if ($order->type == 0) {
                        if ($order->sex == 1) {
                            $recharge_female += $order->tt;
                        }
                        if ($order->sex == 2) {
                            $recharge_male += $order->tt;
                        }
                    }
                    //会员金额
                    if ($order->type == 0) {
                        if ($order->sex == 1) {
                            $recharge_amount_female += $order->sum;
                            $total_amount_female += $order->sum;
                        }
                        if ($order->sex == 2) {
                            $recharge_amount_male += $order->sum;
                            $total_amount_male += $order->sum;
                        }
                    }
                    //内购金额
                    if ($order->type == 1) {
                        if ($order->sex == 1) {
                            $inner_amount_female += $order->sum;
                            $total_amount_female += $order->sum;
                        }
                        if ($order->sex == 2) {
                            $inner_amount_male += $order->sum;
                            $total_amount_male += $order->sum;
                        }
                    }
                }
            }
            if (!$tt_payment->isEmpty()) {
                foreach ($tt_payment as $payment) {
                    //内购订单数
                    if ($payment->type == 1) {
                        if ($payment->sex == 1) {
                            $inner_female += $payment->tt;
                        }
                        if ($payment->sex == 2) {
                            $inner_male += $payment->tt;
                        }
                    }
                    //vip 订单数
                    if ($payment->type == 0) {
                        if ($payment->sex == 1) {
                            $recharge_female += $payment->tt;
                        }
                        if ($payment->sex == 2) {
                            $recharge_male += $payment->tt;
                        }
                    }
                    //会员金额
                    if ($payment->type == 0) {
                        if ($payment->sex == 1) {
                            $recharge_amount_female += $payment->sum;
                            $total_amount_female += $payment->sum;
                        }
                        if ($payment->sex == 2) {
                            $recharge_amount_male += $payment->sum;
                            $total_amount_male += $payment->sum;
                        }
                    }
                    //内购金额
                    if ($payment->type == 1) {
                        if ($payment->sex == 1) {
                            $inner_amount_female += $payment->sum;
                            $total_amount_female += $payment->sum;
                        }
                        if ($payment->sex == 2) {
                            $inner_amount_male += $payment->sum;
                            $total_amount_male += $payment->sum;
                        }
                    }
                }
            }

            //内购人数
            $inner_buy_ios = OrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 1], ['status', 1]])->count();
            $inner_buy_android = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 1], ['status', 1]])->count();
            $inner_buy_android_alipay = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 1], ['status', 1], ['payment', 'alipay']])->count();
            $inner_buy_android_wechat = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 1], ['status', 1], ['payment', 'wechat']])->count();
            $inner_buy = $inner_buy_ios + $inner_buy_android;


            //充值金额
            $recharge_amount_ios = OrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['status', 1]])->whereIn('type', [0, 2])->sum('amount');
            $recharge_amount_android = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['status', 1], ['type', 0]])->sum('amount');
            $recharge_amount = $recharge_amount_ios + $recharge_amount_android;


            //内购金额
            $inner_amount_ios = OrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['type', 1], ['status', 1]])->sum('amount');
            $inner_amount_android = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['status', 1], ['type', 1]])->sum('amount');
            $inner_amount = $inner_amount_ios + $inner_amount_android;
            //总金额
            $total_amount_ios = OrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['status', 1]])->sum('amount');
            $total_amount_android = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['status', 1]])->sum('amount');
            $total_amount_android_alipay = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['status', 1], ['payment', 'alipay']])->sum('amount');
            $total_amount_android_wechat = PaymentOrderModel::where([['created_at', '>', $startTime], ['created_at', '<', $endTime], ['status', 1], ['payment', 'wechat']])->sum('amount');
            $total_amount = $total_amount_ios + $total_amount_android;

            $active = HR::getUniqueNum('counter', 'daily-active-');
            $device = HR::getUniqueNum('counter', 'daily-device-');
            $pay_rate = $register > 0 ? round(($vip_num + $inner_buy) / $register * 100, 2) : 0;
            $pay_rate_male = $register_male > 0 ? round(($recharge_male + $inner_male) / $register_male * 100, 2) : 0;
            $pay_rate_female = $register_female > 0 ? round(($recharge_female + $inner_female) / $register_female * 100, 2) : 0;
            $arpu = $register > 0 ? round($total_amount / $register, 2) : 0;
            $arpu_male = $register_male > 0 ? round($total_amount_male / $register_male, 2) : 0;
            $arpu_female = $register_female > 0 ? round($total_amount_female / $register_female, 2) : 0;

            //通道统计
            $_tencent = UsersProfileModel::where([['register_platform', 'tencent'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $_oppo = UsersProfileModel::where([['register_platform', 'oppo'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $_vivo = UsersProfileModel::where([['register_platform', 'vivo'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $_baidu = UsersProfileModel::where([['register_platform', 'baidu'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $_huawei = UsersProfileModel::where([['register_platform', 'huawei'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $_xiaomi = UsersProfileModel::where([['register_platform', 'xiaomi'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $_360 = UsersProfileModel::where([['register_platform', '360'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();
            $_taobao = UsersProfileModel::where([['register_platform', 'taobao'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->count();

            ReportDailyModel::updateOrCreate([
                'date' => $date
            ], [
                'date' => $date,
                'register' => $register,
                'register_female' => $register_female,
                'register_male' => $register_male,
                'register_android' => $register_android,
                'register_ios' => $register_ios,
                'pay_rate' => $pay_rate,
                'pay_rate_male' => $pay_rate_male,
                'pay_rate_female' => $pay_rate_female,

                'arpu' => $arpu,
                'arpu_male' => $arpu_male,
                'arpu_female' => $arpu_female,

                'vip_num' => $vip_num,
                'active_total' => $active,
                'device_total' => $device,
                'goddess' => $goddess,

                'identity' => $identity,
                'identity_android' => $identity_android,
                'identity_ios' => $identity_ios,

                'authed' => $authed,
                'authed_android' => $authed_android,
                'authed_ios' => $authed_ios,

                'locked' => $locked,
                'locked_android' => $locked_android,
                'locked_ios' => $locked_ios,

                'recharge' => $recharge,
                'recharge_ios' => $recharge_ios,
                'recharge_android' => $recharge_android,
                'recharge_android_alipay' => $recharge_android_alipay,
                'recharge_android_wechat' => $recharge_android_wechat,
                'recharge_male' => $recharge_male,
                'recharge_female' => $recharge_female,


                'inner_buy' => $inner_buy,
                'inner_buy_android' => $inner_buy_android,
                'inner_buy_ios' => $inner_buy_ios,
                'inner_buy_android_alipay' => $inner_buy_android_alipay,
                'inner_buy_android_wechat' => $inner_buy_android_wechat,
                'inner_male' => $inner_male,
                'inner_female' => $inner_female,

                'recharge_amount' => $recharge_amount,
                'recharge_amount_android' => $recharge_amount_android,
                'recharge_amount_ios' => $recharge_amount_ios,
                'recharge_amount_male' => $recharge_amount_male,
                'recharge_amount_female' => $recharge_amount_female,

                'inner_amount' => $inner_amount,
                'inner_amount_android' => $inner_amount_android,
                'inner_amount_ios' => $inner_amount_ios,
                'inner_amount_male' => $inner_amount_male,
                'inner_amount_female' => $inner_amount_female,

                'total_amount' => $total_amount,
                'total_amount_ios' => $total_amount_ios,
                'total_amount_android' => $total_amount_android,
                'total_amount_android_alipay' => $total_amount_android_alipay,
                'total_amount_android_wechat' => $total_amount_android_wechat,
                'total_amount_male' => $total_amount_male,
                'total_amount_female' => $total_amount_female,

                //通道统计
                'p_tencent' => $_tencent,
                'p_360' => $_360,
                'p_vivo' => $_vivo,
                'p_oppo' => $_oppo,
                'p_taobao' => $_taobao,
                'p_huawei' => $_huawei,
                'p_baidu' => $_baidu,
                'p_xiaomi' => $_xiaomi,
            ]);
            //短信占比
            $sms = LogSmsModel::select('mobile')->where([['type', 'verify_code'], ['created_at', '>', $startTime], ['created_at', '<', $endTime]])->distinct()->count();
            $smsRate = $sms > 0 ? round($register / $sms * 100, 2) : 0;

            //在这里进行钉钉的通知
            $msgTitle = "用户数据报表";
            $msg = "### 报表 [" . date('y-m-d') . "]:";
            $msg .= " \n\n > 注册：" . $register . "  |  短信：" . $sms . '  [占比:' . $smsRate . '%' . ']';
            $msg .= " \n\n > 性别分布：男 " . $register_male . " / 女 " . $register_female . " / 未知 " . ($register - $register_female - $register_male);
            $msg .= " \n\n > 终端分布：苹果 " . $register_ios . " / 安卓 " . $register_android;
            $msg .= " \n\n > 活跃终端：" . $active;
            $msg .= " \n\n *****";
            $msg .= " \n\n > 付费率：" . $pay_rate . "% [ 男 " . $pay_rate_male . "% / 女 " . $pay_rate_female . '% ]';
            $msg .= " \n\n > ARPU：" . $arpu . " [ 男 " . $arpu_male . " / 女 " . $arpu_female . ' ]';
            $msg .= " \n\n *****";
            $msg .= " \n\n > 累计认证：" . $authed;
            $msg .= " \n\n > 充值人次：" . ($recharge + $inner_buy);
            $msg .= " \n\n > 人次分布：订阅 " . $recharge . " / 内购 " . $inner_buy;
            $msg .= " \n\n > 订阅渠道：苹果 " . $recharge_ios . " / 安卓 " . $recharge_android;
            $msg .= " \n\n > 内购渠道：苹果 " . $inner_buy_ios . " / 安卓 " . $inner_buy_android;
            $msg .= " \n\n *****";
            $msg .= " \n\n > 累计充值：" . $total_amount;
            $msg .= " \n\n > 金额分布：订阅 " . $recharge_amount . " / 内购 " . $inner_amount;
            $msg .= " \n\n > 订阅渠道：苹果 " . $recharge_amount_ios . " / 安卓 " . $recharge_amount_android;
            $msg .= " \n\n > 内购渠道：苹果 " . $inner_amount_ios . " / 安卓 " . $inner_amount_android;
            (new DingTalk(env('ES_PUSH')))->sendMdMessage($msgTitle, $msg);

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }

    /*------用户的浏览记录，用于统计用户的活跃度-------**/
    private function _baseBrowse()
    {
        //有史以来
        ReportBrowseModel::where('type', 1)->delete();
        $sums = LogBrowseModel::select(DB::raw('count(*) as total, user_id'))->groupBy('user_id')->get();
        if ($sums) {
            $insert = [];
            foreach ($sums as $sum) {
                $insert[] = [
                    'user_id' => $sum->user_id,
                    'view_num' => $sum->total,
                    'date' => null,
                    'type' => 1,
                ];
            }
            ReportBrowseModel::insert($insert);
        }
        //当日
        $sums = LogBrowseModel::select(DB::raw('count(*) as total, user_id, date'))->where([['created_at', '>', $this->start], ['created_at', '<', $this->end]])->groupBy('user_id', 'date')->get();
        if ($sums) {
            $insertData = [];
            foreach ($sums as $summary) {
                $insertData[] = [
                    'user_id' => $summary->user_id,
                    'view_num' => $summary->total,
                    'date' => $summary->date,
                    'type' => 0,
                ];
            }
            ReportBrowseModel::insert($insertData);
        }
    }

    private function _baseDevice()
    {
        LoginLogModel::updateDeviceNum();
    }
}
