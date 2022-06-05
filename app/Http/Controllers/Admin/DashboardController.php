<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Models\Payment\PaymentOrderModel;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\SettingsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DashboardController extends AuthAdmController
{
    public function userDashboardOrder(Request $request)
    {
        $res = $rest = [];
        $orders = OrderModel::where([['created_at', '>=', date('Y-m-d 00:00:00', time() - 86400 * 14)], ['status', 1]])->get();
        if (!$orders->isEmpty()) {
            foreach ($orders as $order) {
                //规整数据
                $date = substr($order->created_at, 0, 10);
                if ($order->type == 0) {  //订阅
                    if (isset($res[$date]['buy'])) {
                        $res[$date]['buy'] = $res[$date]['buy'] + 1; //订单数
                        $res[$date]['buy_amount'] = $res[$date]['buy_amount'] + $order->amount; //订单额
                        $res[$date]['buy_amount_ios'] = $res[$date]['buy_amount_ios'] + $order->amount; //苹果订单额
                        $res[$date]['buy_ios'] = $res[$date]['buy_ios'] + 1; //苹果订单数
                    } else {
                        $res[$date]['buy'] = 1;
                        $res[$date]['buy_amount'] = $order->amount; //订单额
                        $res[$date]['buy_amount_ios'] = $order->amount; //苹果订单额
                        $res[$date]['buy_ios'] = 1;   //苹果订单数
                    }
                }

                if ($order->type == 1) {  //内购
                    if (isset($res[$date]['inner'])) {
                        $res[$date]['inner'] = $res[$date]['inner'] + 1; //订单数
                        $res[$date]['inner_amount'] = $res[$date]['inner_amount'] + $order->amount; //订单额
                        $res[$date]['inner_amount_ios'] = $res[$date]['inner_amount_ios'] + $order->amount; //苹果订单额
                        $res[$date]['inner_ios'] = $res[$date]['inner_ios'] + 1; //苹果订单数
                    } else {
                        $res[$date]['inner'] = 1;
                        $res[$date]['inner_amount'] = $order->amount; //订单额
                        $res[$date]['inner_amount_ios'] = $order->amount; //苹果订单额
                        $res[$date]['inner_ios'] = 1; //苹果订单数
                    }
                }
            }
        }
        //添加第三方订单统计
        $payments = PaymentOrderModel::where([['created_at', '>=', date('Y-m-d 00:00:00', time() - 86400 * 14)], ['status', 1]])->get();
        if (!$payments->isEmpty()) {
            foreach ($payments as $payment) {
                //规整数据
                $date = substr($payment->created_at, 0, 10);
                if ($payment->type == 0) {  //订阅
                    if (isset($res[$date]['buy'])) {
                        $res[$date]['buy'] = $res[$date]['buy'] + 1; //订单数
                        $res[$date]['buy_amount'] = $res[$date]['buy_amount'] + $payment->amount; //订单额
                        if (isset($res[$date]['buy_amount_android'])) {
                            $res[$date]['buy_amount_android'] = $res[$date]['buy_amount_android'] + $payment->amount; //android订单额
                        } else {
                            $res[$date]['buy_amount_android'] = 0;
                        }
                        if (isset($res[$date]['buy_android'])) {
                            $res[$date]['buy_android'] = $res[$date]['buy_android'] + 1; //android订单数
                        } else {
                            $res[$date]['buy_android'] = 0;
                        }

                    } else {
                        $res[$date]['buy'] = 1;
                        $res[$date]['buy_amount'] = $payment->amount; //订单额
                        $res[$date]['buy_amount_android'] = $payment->amount; //android订单额
                        $res[$date]['buy_android'] = 1;   //android订单数
                    }
                }
                if ($payment->type == 1) {  //内购
                    if (isset($res[$date]['inner'])) {
                        $res[$date]['inner'] = $res[$date]['inner'] + 1; //订单数
                        $res[$date]['inner_amount'] = $res[$date]['inner_amount'] + $payment->amount; //订单额
                        $inner_amount_android = $res[$date]['inner_amount_android'] ?? 0;
                        $res[$date]['inner_amount_android'] = $inner_amount_android + $payment->amount; //android订单额
                        $inner_android = $res[$date]['inner_android'] ?? 0;
                        $res[$date]['inner_android'] = $inner_android + 1; //android订单数
                    } else {
                        $res[$date]['inner'] = 1;
                        $res[$date]['inner_amount'] = $payment->amount; //订单额
                        $res[$date]['inner_amount_android'] = $payment->amount; //android订单额
                        $res[$date]['inner_android'] = 1; //android订单数
                    }
                }
            }
        }

        $data_arr = H::prDates(date('Y-m-d', time() - 86400 * 13), date('Y-m-d'));
        foreach ($data_arr as $k => $dataArr) {
            //订阅
            $rest['buy'][] = isset($res[$dataArr]['buy']) ? round($res[$dataArr]['buy'], 2) : 0;
            $rest['buy_amount'][] = isset($res[$dataArr]['buy_amount']) ? round($res[$dataArr]['buy_amount'], 2) : 0;
            $rest['buy_amount_android'][] = isset($res[$dataArr]['buy_amount_android']) ? round($res[$dataArr]['buy_amount_android'], 2) : 0;
            $rest['buy_android'][] = isset($res[$dataArr]['buy_android']) ? round($res[$dataArr]['buy_android'], 2) : 0;
            $rest['buy_amount_ios'][] = isset($res[$dataArr]['buy_amount_ios']) ? round($res[$dataArr]['buy_amount_ios'], 2) : 0;
            $rest['buy_ios'][] = isset($res[$dataArr]['buy_ios']) ? round($res[$dataArr]['buy_ios'], 2) : 0;

            //内购
            $rest['inner'][] = isset($res[$dataArr]['inner']) ? round($res[$dataArr]['inner'], 2) : 0;
            $rest['inner_amount'][] = isset($res[$dataArr]['inner_amount']) ? round($res[$dataArr]['inner_amount'], 2) : 0;
            $rest['inner_amount_android'][] = isset($res[$dataArr]['inner_amount_android']) ? round($res[$dataArr]['inner_amount_android'], 2) : 0;
            $rest['inner_android'][] = isset($res[$dataArr]['inner_android']) ? round($res[$dataArr]['inner_android'], 2) : 0;
            $rest['inner_amount_ios'][] = isset($res[$dataArr]['inner_amount_ios']) ? round($res[$dataArr]['inner_amount_ios'], 2) : 0;
            $rest['inner_ios'][] = isset($res[$dataArr]['inner_ios']) ? round($res[$dataArr]['inner_ios'], 2) : 0;
        }
        return $this->jsonExit(200, 'OK', array_values($rest));
    }

    public function userDashboard(Request $request)
    {
        $res = [];
        //用户数
        $userNum = UsersModel::count();
        $userLockNum = UsersModel::where('status', 0)->count();
        //今日
        $stime = date('Y-m-d 00:00:00');
        $etime = date('Y-m-d 23:59:59');
        $todayNum = UsersModel::where([['created_at', '>', $stime], ['created_at', '<', $etime]])->count();
        $todayLockNum = UsersModel::where([['created_at', '>', $stime], ['created_at', '<', $etime], ['status', 0]])->count();

        //订单额
        $orderBuy = OrderModel::where([['created_at', '>', $stime], ['created_at', '<', $etime], ['status', 1], ['type', 0]])->sum('amount'); //订阅
        $orderInner = OrderModel::where([['created_at', '>', $stime], ['created_at', '<', $etime], ['status', 1], ['type', 1]])->sum('amount'); //内购
        $paymentBuy = PaymentOrderModel::where([['created_at', '>', $stime], ['created_at', '<', $etime], ['status', 1], ['type', 0]])->sum('amount'); //订阅
        $paymentInner = PaymentOrderModel::where([['created_at', '>', $stime], ['created_at', '<', $etime], ['status', 1], ['type', 1]])->sum('amount'); //内购
        $orderBuy += $paymentBuy;
        $orderInner += $paymentInner;

        $totalOrderBuy = OrderModel::where([['status', 1], ['type', 0]])->sum('amount'); //订阅
        $totalOrderInner = OrderModel::where([['status', 1], ['type', 1]])->sum('amount'); //内购
        $paymentOrderBuy = PaymentOrderModel::where([['status', 1], ['type', 0]])->sum('amount'); //订阅
        $paymentOrderInner = PaymentOrderModel::where([['status', 1], ['type', 1]])->sum('amount'); //内购
        $totalOrderBuy += $paymentOrderBuy;
        $totalOrderInner += $paymentOrderInner;

        $res['users_num'] = $userNum;
        $res['users_lock_num'] = $userLockNum;
        $res['today_num'] = $todayNum;
        $res['today_lock_num'] = $todayLockNum;
        $res['buy_amount'] = $orderBuy;
        $res['inner_amount'] = $orderInner;
        $res['total_amount'] = $totalOrderBuy;
        $res['total_inner'] = $totalOrderInner;
        return $this->jsonExit(200, 'OK', $res);
    }

    public function userDashboardReport(Request $request)
    {
        $res = $rest = [];
        $users = UsersModel::select(DB::raw("count(sex) as tt, sex,SUBSTR(created_at,1,10) as date"))
            ->where([['created_at', '>=', date('Y-m-d 00:00:00', time() - 86400 * 7)]])->groupBy(['date', 'sex'])
            ->get();
        $profile = UsersProfileModel::select(DB::raw("count(register_channel) as channel, register_channel,SUBSTR(created_at,1,10) as date"))
            ->where([['created_at', '>=', date('Y-m-d 00:00:00', time() - 86400 * 7)]])->groupBy(['date', 'register_channel'])
            ->get();
        $data_arr = H::prDates(date('Y-m-d', time() - 86400 * 7), date('Y-m-d'));
        if (!$users->isEmpty()) {
            foreach ($users as $user) {
                //规整数据
                $date = $user->date;
                if (isset($res[$date]['total'])) {
                    $res[$date]['total'] += $user->tt;
                } else {
                    $res[$date]['total'] = $user->tt;
                }
                if ($user->sex == 1) $res[$date]['female'] = $user->tt ?? 0;
                if ($user->sex == 2) $res[$date]['male'] = $user->tt ?? 0;
            }
        }
        if (!$profile->isEmpty()) {
            foreach ($profile as $pro) {
                $date = $pro->date;
                if ($pro->register_channel == 'ios') {
                    if (isset($res[$date]['ios'])) {
                        $res[$date]['ios'] += $pro->channel;
                    } else {
                        $res[$date]['ios'] = $pro->channel;
                    }
                }
                if ($pro->register_channel == 'android') {
                    if (isset($res[$date]['android'])) {
                        $res[$date]['android'] += $pro->channel;
                    } else {
                        $res[$date]['android'] = $pro->channel;
                    }
                }
            }
        }
        foreach ($data_arr as $k => $dataArr) {
            $rest['total'][] = isset($res[$dataArr]['total']) ? round($res[$dataArr]['total'], 2) : 0;
            $rest['female'][] = isset($res[$dataArr]['female']) ? round($res[$dataArr]['female'], 2) : 0;
            $rest['male'][] = isset($res[$dataArr]['male']) ? round($res[$dataArr]['male'], 2) : 0;
            $rest['ios'][] = isset($res[$dataArr]['ios']) ? round($res[$dataArr]['ios'], 2) : 0;
            $rest['android'][] = isset($res[$dataArr]['android']) ? round($res[$dataArr]['android'], 2) : 0;
        }
        return $this->jsonExit(200, 'OK', array_values($rest));
    }

    public function channelDashboardReport(Request $request)
    {
        $rest = $this->_summary();
        return $this->jsonExit(200, 'OK', $rest);
    }

    private static function _summary()
    {
        $data = [];
        //金额分布

//        [
//              {value: 335, name: '直达', selected: true},
//              {value: 679, name: '营销广告'},
//              {value: 1548, name: '搜索引擎'}
//            ]


        //开始区分性别和渠道
        $summary = $sexReg = [];;
        $builder = ReportDailyModel::orderBy('id', 'desc');
        $register = $builder->sum('register');
        $register_female = $builder->sum('register_female');
        $register_male = $builder->sum('register_male');
        $register_ios = $builder->sum('register_ios');
        $register_android = $builder->sum('register_android');
        $data['user'][] = ['value' => $register, 'name' => '总注册'];
        $data['user'][] = ['value' => $register_female, 'name' => '女'];
        $data['user'][] = ['value' => $register_male, 'name' => '男'];
        $data['user'][] = ['value' => $register_ios, 'name' => 'ios'];
        $data['user'][] = ['value' => $register_android, 'name' => 'android'];


        $recharge_amount = $builder->sum('recharge_amount');
        $recharge_amount_android = $builder->sum('recharge_amount_android');
        $recharge_amount_ios = $builder->sum('recharge_amount_ios');
        $recharge_amount_male = $builder->sum('recharge_amount_male');
        $recharge_amount_female = $builder->sum('recharge_amount_female');

        $inner_amount = $builder->sum('inner_amount');
        $inner_amount_android = $builder->sum('inner_amount_android');
        $inner_amount_ios = $builder->sum('inner_amount_ios');
        $inner_amount_male = $builder->sum('inner_amount_male');
        $inner_amount_female = $builder->sum('inner_amount_female');

        $data['order'][] = ['value' => $recharge_amount, 'name' => '订阅金额'];
        $data['order'][] = ['value' => $recharge_amount_android, 'name' => '安卓订阅金额'];
        $data['order'][] = ['value' => $recharge_amount_ios, 'name' => '苹果订阅金额'];
        $data['order'][] = ['value' => $recharge_amount_male, 'name' => '男性订阅金额'];
        $data['order'][] = ['value' => $recharge_amount_female, 'name' => '女性订阅金额'];
        $data['order'][] = ['value' => $inner_amount, 'name' => '内购金额'];
        $data['order'][] = ['value' => $inner_amount_android, 'name' => '安卓内购金额'];
        $data['order'][] = ['value' => $inner_amount_ios, 'name' => '苹果内购金额'];
        $data['order'][] = ['value' => $inner_amount_male, 'name' => '男性内购金额'];
        $data['order'][] = ['value' => $inner_amount_female, 'name' => '女性内购金额'];

        $total_amount = $builder->sum('total_amount');
        $total_amount_ios = $builder->sum('total_amount_ios');
        $total_amount_male = $builder->sum('total_amount_male');
        $total_amount_female = $builder->sum('total_amount_female');
        $total_amount_android = $builder->sum('total_amount_android');
        $data['total'][] = ['value' => $total_amount, 'name' => '总额'];
        $data['total'][] = ['value' => $total_amount_ios, 'name' => '苹果总额'];
        $data['total'][] = ['value' => $total_amount_android, 'name' => '安卓总额'];
        $data['total'][] = ['value' => $total_amount_male, 'name' => '男性总额'];
        $data['total'][] = ['value' => $total_amount_female, 'name' => '女性总额'];
        return $data;
    }

}
