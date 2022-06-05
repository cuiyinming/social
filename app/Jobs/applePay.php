<?php

namespace App\Jobs;

use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\SubscribeModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\CommonModel;
use App\Http\Libraries\Sms\DingTalk;
use Illuminate\Support\Facades\DB;
use RongCloud;

class applePay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;

    /**
     * Create a new job instance.
     */
    protected function __construct(OrderModel $order)
    {
        if (!defined('CHANNEL')) define('CHANNEL', 'ios');
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $receiptData = $this->order->receipt;
            $applePay = new \App\Http\Libraries\Tools\ApplePay();
            $res = $applePay->validateApplePay($receiptData);
            if ($res['success']) {
                //vip订阅
                if ($this->order->type == 0) {
                    $product_id = $this->_vipBuy($res, $receiptData);
                    //首充开通VIP奖励
                    $this->_firstVipReward($this->order->user_id, $product_id);
                    //首次开通vip赠送超级曝光
                    $this->_freeSuperShow($this->order->user_id);
                    //发放奖励
                }
                //内购
                if ($this->order->type == 1) {
                    $this->_innerBuy($res);
                    //首充奖励
                    //$this->_firstRecharge($this->order->user_id);
                    //充值后分润给上级
                    //$this->_fatherBenefit($this->order->user_id);
                }
                \App\Jobs\clientLiquidation::dispatch($this->order, 'apple_pay')->delay(now()->addSeconds(20))->onQueue('im');
            } else {
                throw new \Exception('苹果接口获取数据失败');
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            throw new \Exception($e->getMessage());
        }
    }

    //内购
    private function _innerBuy($res)
    {
        try {
            //附加系统消息------通知支付消息
            $title = "友币购买订单处理完成";
            $cont = "您购买的友币已经充值到您的账户，请注意友币数量变化。";
            $sys = UsersMsgSysModel::updateOrCreate([
                'user_id' => $this->order->user_id,
                'event_id' => $this->order->id,
                'event' => 'inner_buy_end',
            ], [
                'title' => $title,
                'cont' => $cont,
            ]);
            //附加信息第一步------在这里进行钉钉的通知
            if ($sys->wasRecentlyCreated) {
                $diamond = CommonModel::storeInnerPay($res, $this->order);
                $msgTitle = "内购-";
                $msg = "### 内购购买"
                    . " \n\n > 应用："
                    . " \n\n > 用户：" . $this->order->user_id
                    . " \n\n > 价格：" . $diamond['amount']
                    . " \n\n > 平台：苹果"
                    . " \n\n > 项目：内购";
                (new DingTalk(env('ES_PUSH')))->sendMdMessage($msgTitle, $msg);
                //极光推送
                JpushModel::JpushCheck($this->order->user_id, $diamond['diamond'], 0, 19);
                //推送融云系统消息
                $sysMsg = ['content' => $title, 'title' => $cont, 'extra' => ""];
                RongCloud::messageSystemPublish(101, [$this->order->user_id], 'RC:TxtMsg', json_encode($sysMsg));
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }

    //订阅
    private function _vipBuy($res, $receiptData)
    {
        try {
            if (!isset($res['data']['latest_receipt_info'])) {
                throw new \Exception('支付异常');
            }
            $storeInfo = CommonModel::storeReceipt($this->order, $res);
            if (!isset($storeInfo['last']) || !isset($storeInfo['productPrice'])) {
                throw new \Exception('支付失败');
            }
            $last = $storeInfo['last'];
            $price = $storeInfo['productPrice'];
            //更新订单信息
            $this->order->paid_at = date('Y-m-d H:i:s', $last['purchase_date_ms'] / 1000);
            $this->order->receipt = $receiptData;
            if (empty($this->order->product_id)) $this->order->product_id = $last['product_id'];
            $this->order->transaction_id = $last['transaction_id'];
            $this->order->original_transaction_id = $last['original_transaction_id'];
            $this->order->amount = $price;
            $this->order->status = 1;
            $this->order->save();
            //处理原来重复的订单
            $repeat = OrderModel::where([['original_transaction_id', $last['original_transaction_id']], ['transaction_id', $last['transaction_id']], ['status', 1], ['id', '!=', $this->order->id]])->first();
            if ($repeat) {
                //记录日志
                $repeatData = [
                    'id' => $repeat->id,
                    'original_transaction_id' => $repeat->original_transaction_id,
                    'transaction_id' => $repeat->transaction_id,
                    'user_id' => $repeat->user_id,
                    'product_id' => $repeat->product_id,
                    'amount' => $repeat->amount,
                    'created_at' => $repeat->created_at,
                    'type' => $repeat->type,
                    'create_type' => $repeat->create_type,
                ];
                //file_put_contents('/tmp/repeat_order', print_r($repeatData, 1), FILE_APPEND);
                $repeat->delete();
            }
            //附加系统消息------通知支付消息
            $title = 'VIP订单处理完成';
            $cont = '您的VIP购买订单已经处理完成，请注意VIP权益变化。';
            $sys = UsersMsgSysModel::updateOrCreate([
                'user_id' => $this->order->user_id,
                'event_id' => $this->order->id,
                'event' => 'vip_buy_end',
            ], [
                'title' => $title,
                'cont' => $cont,
            ]);
            //附加信息第一步------在这里进行钉钉的通知
            if ($sys->wasRecentlyCreated) {
                $msgTitle = "VIP购买-心友";
                $msg = "### 订阅购买"
                    . " \n\n > 应用：心友"
                    . " \n\n > 用户：" . $this->order->user_id
                    . " \n\n > 平台：苹果"
                    . " \n\n > 价格：" . $price
                    . " \n\n > 项目：订阅";
                (new DingTalk(env('ES_PUSH')))->sendMdMessage($msgTitle, $msg);
                //极光推送
                JpushModel::JpushCheck($this->order->user_id, '', 0, 17);
                $sysMsg = ['content' => $title, 'title' => $cont, 'extra' => ""];
                RongCloud::messageSystemPublish(101, [$this->order->user_id], 'RC:TxtMsg', json_encode($sysMsg));
            }
            return $this->order->product_id;
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return '';
        }
    }

    //上级的邀请奖励
    private static function _fatherBenefit($uid)
    {
        $user = UsersModel::getUserInfo($uid);
        if (!empty($user->invited)) {
            try {
                DB::beginTransaction();
                //汇总我的充值金额
                $charge_amount = OrderModel::where([['status', 1], ['type', 1]])->whereNotNull('receipt')->sum('amount');
                if ($charge_amount >= 30 && $user->invited_benefited == 0) {
                    //开始分润
                    $invite_reward = config('settings.invite_reward');
                    //折算为钻石
                    $invite_diamond = $invite_reward * config('settings.points_rate');
                    //添加分润记录
                    $father = UsersModel::where([['status', 1], ['uinvite_code', $user->invited]])->first();
                    if ($father) {
                        //分成
                        $before = $father->jifen;
                        $father->jifen += $invite_diamond;
                        $father->save();
                        $desc = '邀请的好友（' . $user->id . '）充值任务完成，奖励' . $invite_diamond . '心钻' . '(折合现金' . $invite_reward . '元)，可提现';
                        $remark = '邀请的好友（' . $user->id . '）充值任务完成，奖励' . $invite_diamond . '心钻' . '(折合现金' . $invite_reward . '元)，可提现';
                        LogBalanceModel::gainLogBalance($father->id, $before, $invite_diamond, $father->jifen, 'invite_inner_benefit', $desc, $remark, 0, 'log_jifen');
                        //更新分成状态
                        $user->invited_benefited = 1;
                        $user->save();
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }
        }
    }

    //首充奖励
    private static function _firstRecharge($uid): bool
    {
        try {
            $task = 'shouchongjiangli';
            $finish = UsersSettingsModel::getSingleUserSettings($uid, $task);
            if ($finish == 1) return false;
            $counter = OrderModel::where([['user_id', $uid], ['status', 1], ['type', 1]])->count();  // 0 订阅  1内购
            if ($counter == 1) {
                UsersRewardModel::firstChargeReward($uid, $task);
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }

    //首次开通VIP奖励
    private static function _firstVipReward($uid, $product_id): bool
    {
        try {
            //每种VIP的首充奖励
            if (empty($product_id)) return false;
            $task = '';
//            if (in_array($product_id, ['quzhi11', 'quzhi12', 'quzhi13', 'quzhi200', 'quzhi201'])) $task = 'swordsman_reward';
//            if (in_array($product_id, ['quzhi21', 'quzhi22', 'quzhi23', 'quzhi300', 'quzhi301', 'quzhi700', 'quzhi701'])) $task = 'knight_reward';
//            if (in_array($product_id, ['quzhi31', 'quzhi32', 'quzhi33', 'quzhi400', 'quzhi401'])) $task = 'suzerain_reward';
//            if (in_array($product_id, ['quzhi41', 'quzhi42', 'quzhi43', 'quzhi500', 'quzhi501'])) $task = 'lord_reward';

            if (in_array($product_id, ['quzhi200', 'quzhi201'])) $task = 'swordsman_reward';
            if (in_array($product_id, ['quzhi700', 'quzhi701'])) $task = 'knight_reward';
            if (in_array($product_id, ['quzhi400', 'quzhi401'])) $task = 'suzerain_reward';
            if (in_array($product_id, ['quzhi500', 'quzhi501'])) $task = 'lord_reward';
            if (empty($task)) return false;
            $finish = UsersSettingsModel::getSingleUserSettings($uid, $task);
            if ($finish == 1) return false;
            $counter = OrderModel::where([['user_id', $uid], ['status', 1], ['type', 0], ['product_id', $product_id]])->count();  // 0 订阅  1内购
            if ($counter == 1) {
                //查询
                UsersRewardModel::firstVipReward($uid, $task);
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }

    private static function _freeSuperShow($uid)
    {
        $profile = UsersProfileModel::getUserInfo($uid);
        $sub = SubscribeModel::getRightTimes($profile->vip_level);
        $super_time = $sub['super_show'];
        UsersModel::where('id', $uid)->update(['super_show_left' => $super_time]);
    }
}
