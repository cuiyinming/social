<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Helpers\S;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\Lib\LibCountriesModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogImCallModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\Payment\OrderSyncModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Libraries\Tools\{ApplePay, BaiduCloud};
use App\Http\Models\Payment\AppleLog\AppleIapLatestReceiptInfoModel;
use App\Http\Models\Payment\AppleLog\AppleIapPendingRenewalInfoModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CommonController extends Controller
{

    /**获取用户的填写的推荐id 是否合法**/
    public function checkInviteCode(Request $request)
    {
        $code = $request->only('code');
        $first = UsersModel::where('uinvite_code', $code['code'])->first();
        if (!$first) {
            $client = ClientUsersModel::where('invite_code', $code['code'])->first();
            if (!$client) {
                return $this->jsonExit(406, '推荐码不存在');
            }
        }
        return $this->jsonExit(200, 'OK');
    }

    /** * 获取验证码*/
    public function getSmsCode(Request $request)
    {
        $type = $request->input('type', 'verify_code');
        if (!in_array($type, ['verify_code', 'find_password'])) {
            return $this->jsonExit(205, '短信类型错误');
        }
        if (!$request->has('mobile')) {
            return $this->jsonExit(201, '手机号码未传递');
        }
        $mobile = $request->input('mobile');
        if (!H::checkPhoneNum($mobile)) {
            return $this->jsonExit(202, '非法的手机号');
        }
        //如果是找回密码需要判断是不是注册过
        if ($type == 'find_password') {
            $exist = UsersModel::where([['mobile', H::encrypt($mobile)], ['status', 1]])->first();
            if (!$exist) {
                return $this->jsonExit(202, '账号不存在');
            }
        }
        //判断指定时间发送数量
        $max_setting = config('common.max_sms_time');
        $has_send_time = LogSmsModel::geSmsNum($mobile, $type);
        if ($has_send_time >= $max_setting) {
            return $this->jsonExit(203, '获取短信过频，请稍后再试');
        }
        $sendResult = LogSmsModel::sendMsg($mobile, $type);
        if ($sendResult) {
            return $this->jsonExit(200, 'OK');
        } else {
            return $this->jsonExit(204, '发送失败');
        }
    }

    /** 验证手机号码系统是否存在验证 */
    public function mobileExistCheck(Request $request)
    {
        if (!$request->has('mobile')) {
            return $this->jsonExit(201, '手机号未传递');
        }
        $mobile = $request->input('mobile');
        if (!H::checkPhoneNum($mobile)) {
            return $this->jsonExit(202, '手机号码错误');
        }
        //查用户数据库判断用户存在与否
        $encryptMobile = H::encrypt($mobile);
        $userModel = UsersModel::where('mobile', $encryptMobile)->first();
        if (!$userModel) {
            return $this->jsonExit(200, '用户不存在', ['exist' => 0, 'password_set' => 0]);
        } else {
            if ($userModel->status != 1) {
                return $this->jsonExit(203, '用户已封禁', ['exist' => 2, 'password_set' => $userModel->password_set]);
            } else {
                return $this->jsonExit(200, '用户已存在', ['exist' => 1, 'password_set' => $userModel->password_set]);
            }
        }
    }

    public function smsCheck(Request $request)
    {
        $mobile = $request->input('mobile');
        $code = $request->input('code');
        $type = $request->input('type', 'verify_code');
        if (!H::checkPhoneNum($mobile)) {
            return $this->jsonExit(402, '手机号错误');
        }
        $checked = LogSmsModel::checkCode($mobile, $code, $type);
        if (!$checked) {
            LoginErrModel::gainLog(0, $code, 403, '验证码错误');
            return $this->jsonExit(403, trans('验证码错误'));
        }
        return $this->jsonExit(200, 'OK');
    }


    /**
     * 接收支付的异步通知
     * notification_type 表示触发此通知的事件类型。其值如下：
     * CANCEL：表示用户退款了，或者用户升级了订阅。（用户升级订阅后，会退款之前的订阅）
     * DID_CHANGE_RENEWAL_PREF：表示用户更改订阅计划，会在下次订阅生效。对当前订阅无影响。
     * DID_CHANGE_RENEWAL_STATUS：表示续订状态有改变。可检查auto_renew_status_change_date_ms和auto_renew_status字段。
     * DID_FAIL_TO_RENEW：表示由于账单问题，续订失败。可检查is_in_billing_retry_period
     * DID_RECOVER：表示成功续订。这个是针对过去续订失败的订阅。
     * INITIAL_BUY：表示第一次订阅。
     * INTERACTIVE_RENEWAL：表示用户手动续订成功。
     * RENEWAL：表示自动续订成功。（此字段苹果计划废弃，用DID_RECOVER代替。）
     * 下面是常见的事件和触发的通知
     * 首次购买：INITIAL_BUY
     * 升级订阅：CANCEL, DID_CHANGE_RENEWAL_STATUS, INTERACTIVE_RENEWAL
     * 降级订阅：INTERACTIVE_RENEWAL, DID_CHANGE_RENEWAL_PREF
     * 订阅已过期，重新订阅：DID_CHANGE_RENEWAL_STATUS
     * 订阅已过期，重新升级或降级订阅：INTERACTIVE_RENEWAL, DID_CHANGE_RENEWAL_STATUS
     * 用户取消订阅：DID_CHANGE_RENEWAL_STATUS
     * 用户退款：CANCEL, DID_CHANGE_RENEWAL_STATUS
     * 由于账单问题，续订失败：DID_FAIL_TO_RENEW
     * 账单问题解决，续订成功：DID_RECOVER
     * 由于账单问题，订阅被彻底取消：DID_CHANGE_RENEWAL_STATUS
     *
     * unified_receipt 字段中包含了最近的交易信息。可通过其中的original_transaction_id找到对应原始订阅。
     *
     */
    public function notifyApple(Request $request)
    {
        $data = $request->all();
        file_put_contents('/tmp/sync_log.log', print_r($data, 1) . PHP_EOL, FILE_APPEND);
        try {
            $receipt = $data['latest_receipt'] ?? $data['unified_receipt']['latest_receipt'];
            $notification_type = $data['notification_type'];
            $auto_renew_status = $data['auto_renew_status'] ? 1 : 0;
            $auto_renew_status_change_date = isset($data['auto_renew_status_change_date_ms']) ? date('Y-m-d H:i:s', $data['auto_renew_status_change_date_ms'] / 1000) : null;
            $last_receipt_info = $data['latest_receipt_info'] ?? $data['unified_receipt']['latest_receipt_info'][0];
            $transaction_id = $last_receipt_info['transaction_id'];
            $transaction_id_original = $last_receipt_info['original_transaction_id'];
            $product_id = $last_receipt_info['product_id'];
            $purchase_date_original = date('Y-m-d H:i:s', $last_receipt_info['original_purchase_date_ms'] / 1000);
            $purchase_date = date('Y-m-d H:i:s', $last_receipt_info['purchase_date_ms'] / 1000);
            if (isset($last_receipt_info['expires_date_ms'])) {
                $expires = $last_receipt_info['expires_date_ms'];
            } else {
                $expires = $last_receipt_info['expires_date'];
                if (!is_numeric($expires)) {
                    $expires = strtotime($expires);
                }
            }
            $expires_date = date('Y-m-d H:i:s', $expires / 1000);
            //反查用户id
            $pendingModel = OrderModel::where([['original_transaction_id', $transaction_id_original], ['status', 1]])->orderBy('id', 'asc')->first();
            $userId = $pendingModel ? $pendingModel->user_id : 0;
            OrderSyncModel::create([
                'user_id' => $userId,
                'product_id' => $product_id,
                'receipt' => $receipt,
                'purchase_date_original' => $purchase_date_original,
                'purchase_date' => $purchase_date,
                'expires_date' => $expires_date,
                'transaction_id' => $transaction_id,
                'transaction_id_original' => $transaction_id_original,
                'notify_type' => $notification_type,
                'auto_renew_status' => $auto_renew_status,
                'auto_renew_status_change_date' => $auto_renew_status_change_date,
            ]);
            //创建订单并进行异步处理----------------------
            //----------------------------------------
            //创建订单并进行异步处理----------------------
            $order = OrderModel::where([['transaction_id', $transaction_id], ['original_transaction_id', $transaction_id_original]])->first();
            if (strtolower($notification_type) != 'cancel') {
                if ($order && $order->status == 0) {
                    \App\Jobs\applePay::dispatch($order);
                    //日志
//                    file_put_contents('/tmp/subscribe_update.log', print_r([
//                            'user_id' => $order->user_id,
//                            'vip_is' => 1,
//                            'vip_level' => $product_id,
//                            'vip_exp_time' => $expires_date,
//                            'date' => date('Y-m-d H:i:s')
//                        ], 1) . PHP_EOL, FILE_APPEND);
                }
                if (!$order && $userId > 0) {
                    $order_sn = H::genOrderSn(2);
                    $order = OrderModel::create([
                        'user_id' => $userId,
                        'original_transaction_id' => $transaction_id_original,
                        'transaction_id' => $transaction_id,
                        'receipt' => $receipt,
                        'status' => 0,
                        'type' => 0,
                        'sn' => $order_sn,
                        'sign' => md5($receipt . $order_sn),
                        'create_type' => 1,
                    ]);
                    \App\Jobs\applePay::dispatch($order);
                    //日志
//                    file_put_contents('/tmp/subscribe_add.log', print_r([
//                            'user_id' => $order->user_id,
//                            'vip_is' => 1,
//                            'vip_level' => $product_id,
//                            'vip_exp_time' => $expires_date,
//                            'date' => date('Y-m-d H:i:s')
//                        ], 1) . PHP_EOL, FILE_APPEND);
                }
            }
            //处理取消订单
            if (strtolower($notification_type) == 'cancel' && $userId > 0) {
                $profile = UsersProfileModel::where('user_id', $userId)->first();
                $profile->vip_is = 0;
                $profile->vip_level = 0;
                $profile->vip_exp_time = date('Y-m-d H:i:s');
                $profile->save();
                //同步es及redis
                //更新es
                EsDataModel::updateEsUser([
                    'id' => $userId,
                    'vip_is' => 0,
                    'vip_level' => 0,
                ]);
                //日志
//                file_put_contents('/tmp/subscribe_cancel.log', print_r([
//                        'user_id' => $order->user_id,
//                        'vip_is' => 10,
//                        'vip_level' => $product_id,
//                        'vip_exp_time' => $expires_date,
//                        'date' => date('Y-m-d H:i:s')
//                    ], 1) . PHP_EOL, FILE_APPEND);
            }
        } catch (\Exception $e) {
            file_put_contents('/tmp/sync_err.log', print_r([$e->getMessage(), $e->getFile(), $e->getLine()], 1) . PHP_EOL, FILE_APPEND);
        }
    }


    public function videoChat(Request $request)
    {
        //file_put_contents('/tmp/video_chat.log', print_r($request->all(), 1) . PHP_EOL, FILE_APPEND);
        $event = $request->input('event', 0);
        $timestamp = $request->input('timestamp', 0);
        $members = $request->input('members', 0);
        $room_id = $request->input('roomId', '');
        $session_id = $request->input('sessionId', '');
        $app_key = $request->input('appKey', '');
        $room_get = HR::signLoginGet($room_id, 'im-call');
        //会话开始
        if (!empty($session_id) && !empty($app_key) && !empty($room_id)) {
            try {
                if ($event == 20 && !empty($members)) {
                    $plan_end = false;
                    $create = [
                        'app_key' => $app_key,
                        'room_id' => $room_id,
                        'session_id' => $session_id,
                        'status' => 0,
                        'end_type' => 1,
                    ];
                    //被邀请人
                    $merge = [
                        'call_invitee' => $members[0]['userId'],
                        'invitee_join_at' => date('Y-m-d H:i:s', intval($members[0]['joinTime'] / 1000)),
                        'plan_end_at' => null
                    ];
                    //邀请人
                    if (isset($members[0]['data']['role']) && $members[0]['data']['role'] == 'RC_CallInvter') {
                        $plan_end = true;
                        //计算预计自动结束时间
                        $invite = $members[0]['userId'];
                        $coin = UsersModel::where('id', $invite)->first()->sweet_coin;
                        $call_price = config('settings.im_call_price');  //单位是分钟价格
                        $end_time = date('Y-m-d H:i:s', (time() + floor($coin / $call_price) * 60));
                        $kick_time = time() + (floor($coin / $call_price) - 1) * 60 + 56;   //提前4s 结束会话
                        $merge = [
                            'call_inviter' => $members[0]['userId'],
                            'inviter_join_at' => date('Y-m-d H:i:s', intval($members[0]['joinTime'] / 1000)),
                            'plan_end_at' => $end_time,
                        ];
                    }
                    if (empty($room_get)) {
                        //创建
                        $data = array_merge($create, $merge);
                        LogImCallModel::create($data);
                        HR::signLogin($room_id, $session_id, 'im-call');
                    } else {
                        //更新
                        LogImCallModel::where('room_id', $room_id)->update($merge);
                    }
                    //有个大前提是在表创建后触发   在计划结束的时候执行下job 任务
                    if ($plan_end && isset($kick_time)) {
                        \App\Jobs\kickUser::dispatch($room_id)->delay(now()->addSeconds($kick_time))->onQueue('im');
                    }
                }
                //房间销毁了
                if ($event == 3) {
                    $call_duration = $request->input('durationTime', 0);
                    $end_time = date('Y-m-d H:i:s', intval($timestamp / 1000));
                    $call_duration = intval($call_duration / 1000);
                    $duration_minute = ceil($call_duration / 60);  //不足一分钟按一分钟计算
                    $logIm = LogImCallModel::where('room_id', $room_id)->first();
                    $logIm->call_end_at = $end_time;
                    $logIm->status = 1;
                    $logIm->call_duration = $call_duration;
                    $logIm->save();
                    //结束扣金币[增加判 如果是强制踢下线的就只扣一次费用，因为踢下线已经扣费一次了]
                    if ($logIm->end_type != 0) {  // 非强制中断才扣费
                        $call_price = config('settings.im_call_price');  //单位是分钟价格
                        $change = $call_price * $duration_minute;
                        $desc = '语音通话 ' . $duration_minute . ' 分钟，花费 ' . $change . ' 友币';
                        $remark = '与用户 ' . $logIm->call_invitee . ' 语音通话 ' . $duration_minute . ' 分钟，花费 ' . $change . ' 友币';
                        $user = UsersModel::where('id', $logIm->call_inviter)->first();
                        $before = $user->sweet_coin;
                        $after = $before - $change;
                        if ($after <= 0) $after = 0;
                        if ($before > 0) {
                            $user->sweet_coin = $after;
                            $user->save();
                            LogBalanceModel::gainLogBalance($logIm->call_inviter, $before, $change, $after, 'im_call', $desc, $remark);
                        }
                    }
                    //房间销毁后触发奖励
                    UsersRewardModel::userDailyRewardSet($logIm->call_inviter, 'yuyintonghua');
                }
            } catch (\Exception $e) {
                MessageModel::gainLog($e);
            }
        }
    }

    /**
     * @param Request $request
     * 测试使用票据完整性Rong
     */
    public function ticketCheck(Request $request)
    {
        $id = $request->input('id', 0);
        $key = $request->input('key');
        if ($key != 'abctest') {
            return $this->jsonExit(201, '秘钥错误');
        }
        $order = OrderModel::find($id);
        $ticket = $order->receipt;
        try {
            $applePay = new ApplePay();
            $res = $applePay->validateApplePay($ticket);
            if (isset($res['data']['latest_receipt'])) unset($res['data']['latest_receipt']);
            dd($res['data']);
        } catch (\Exception $e) {
            return $this->jsonExit(202, $e->getMessage());
        }

    }
}
