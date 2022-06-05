<?php

namespace App\Http\Models;

use App\Components\ESearch\ESearch;
use App\Http\Helpers\H;
use App\Http\Helpers\S;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogPushModel;
use App\Http\Models\Payment\AppleLog\AppleIapModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\System\SysMessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommonModel extends Model
{
    protected $guarded = [];

    //验证苹果支付凭证后存储数据  is_order 泳衣判断传递的模型是否是 order 还是票据
    public static function storeReceipt($order, $res, $is_order = true): array
    {
        try {
            if ($res['success']) {
                //先反查用户
                if (!isset($res['data']['latest_receipt_info'])) return [];
                $last = current($res['data']['latest_receipt_info']);
                $lastPending = OrderModel::where([['original_transaction_id', $last['original_transaction_id']], ['status', 1]])->orderBy('id', 'asc')->first();
                $user_id = $lastPending ? $lastPending->user_id : ($is_order ? $order->user_id : 0);
                if ($user_id <= 0) return [];
                //存储解析内容
                //AppleIapModel::storeAppleIap($order->receipt, $res['data'], $user_id);
                //更新用户的vip & 获取用户标识
                $user = UsersModel::find($user_id);
                if (!$user) {
                    throw new \Exception('苹果支付用户不存在=>' . $user_id);
                }
                if ($user->status != 1) {
                    throw new \Exception('苹果支付用户状态异常=>' . $user_id);
                }

                $productId = S::getVipPriceById($last['product_id'], 'id_num');
                $productPrice = S::getVipPriceById($last['product_id']);
                //连续订阅会员
                if (isset($last['expires_date_ms'])) {
                    $lastMs = $last['expires_date_ms'] / 1000;
                    $lastData = date('Y-m-d H:i:s', $lastMs);
                }
                //体验会员 这里增加了一个体验会员【这里添加兼容逻
                if (!isset($last['expires_date_ms']) && isset($last['purchase_date_ms'])) {
                    $lastMs = S::getPriceAndTimeLast($last['product_id'], $last['purchase_date_ms'] / 1000);
                    $lastData = date('Y-m-d H:i:s', $lastMs);
                }
                //在这里添加退款逻辑
                if (isset($last['cancellation_date_ms'])) {
                    $lastMs = $last['cancellation_date_ms'] / 1000;
                    $lastData = date('Y-m-d H:i:s', $lastMs);
                }

                $nexp = $lastMs > time();  //未过期 true | false
                $profile = UsersProfileModel::where('user_id', $user_id)->first();
                if ($profile) {
                    //更新用户的最新的支付票据
                    $latest = $res['data']['latest_receipt'] ?? '';
                    $profile->receipt = $latest ?: ($is_order ? $order->receipt : '');
                    if (empty($profile->vip_at)) {
                        $profile->vip_at = date('Y-m-d H:i:s');
                    }
                    //更新vip 情况
                    $profile->vip_is = $nexp ? 1 : 0;
                    $profile->vip_level = $nexp ? $productId : 0;
                    $profile->vip_level_last = $nexp ? $productId : 0;
                    $profile->vip_exp_time = $lastData;
                    $profile->save();
                }
                //更新es
                $esVipArr = [
                    [
                        'id' => $user_id,
                        'vip_is' => $nexp ? 1 : 0,
                        'vip_level' => $nexp ? $productId : 0,
                    ]
                ];
                (new ESearch('users:users'))->updateSingle($esVipArr);
                //返回可用数据
                return [
                    'last' => $last,
                    'productPrice' => $productPrice
                ];
            }
            return [];
        } catch (\Exception $e) {
            file_put_contents('/tmp/apple.log', print_r([$last, $productPrice, $res['data']], 1) . PHP_EOL, 8);
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return [];
        }
    }

    public static function storeInnerPay($res, $order)
    {
        $user_id = $order->user_id;
        $receiptData = $order->receipt;
        //更新用户的vip & 获取用户标识
        if (!isset($res['data']['latest_receipt_info']) && !isset($res['data']['latest_receipt'])) {
            throw new \Exception('凭证错误');
        }

        //优化接口处理数据形式 === S
        if (empty($res['data']['receipt']['in_app'])) {
            throw new \Exception('凭证数组为空');
        }
        if (count($res['data']['receipt']['in_app']) == 1) {
            $first = current($res['data']['receipt']['in_app']);
        } else {
            $dataArr = $res['data']['receipt']['in_app'];
            //首次排序删除订阅
            foreach ($dataArr as $k => $var) {
                if (stripos($var['product_id'], 'xinyou') === false) unset($dataArr[$k]);
            }
            $flag = [];
            foreach ($dataArr as $va) {
                $flag[] = $va['purchase_date_ms'];
            }
            array_multisort($flag, SORT_DESC, $dataArr);
            $first = current($dataArr);
        }
        //优化接口处理数据形式 === E

        $user = UsersModel::find($user_id);
        $lastMs = $first['purchase_date_ms'] / 1000;
        if ((time() - $lastMs) > 3600) {
            throw new \Exception('订单已超过一小时');
        }

        $productId = S::getDiamondPriceById($first['product_id'], 'diamond');
        $amount = S::getDiamondPriceById($first['product_id']);
        $lastData = date('Y-m-d H:i:s', $lastMs);
        //增加用户的友币
        $before_sweet_coin = $user->sweet_coin;
        $user->sweet_coin += intval($productId);
        //增加累计充值友币金额
        $user->sweet_coin_grand += intval($productId);
        $user->save();
        //添加购买记录
        $remark = "用户{$user_id}在{$lastData}充值{$productId}个友币";
        $desc = "充值友币{$productId}个";
        LogBalanceModel::gainLogBalance($user_id, $before_sweet_coin, $productId, $user->sweet_coin, 'recharge_diamond', $desc, $remark);
        //更新订单信息
        $order->amount = $amount;
        if (empty($order->product_id)) $order->product_id = $first['product_id'];
        $order->paid_at = date('Y-m-d H:i:s', $first['purchase_date_ms'] / 1000);
        $order->receipt = $receiptData;
        $order->transaction_id = $first['transaction_id'];
        $order->original_transaction_id = $first['original_transaction_id'];
        $order->status = 1;
        $order->save();
        return [
            'amount' => $amount,
            'diamond' => $productId
        ];
    }

    //处理视频音频和文字
    public static function greenScan($uid, $is_video, $localRule)
    {
        $pass = true;
        $setting = config('settings.scan');
        if ($is_video == 0 && $setting['image_on'] == true) {
            $scanRes = (new AliyunCloud($uid))->GreenScanImage(H::path($localRule));
            if ($scanRes != 'pass') {
                $pass = false;
                UsersSettingsModel::setViolation($uid, 'violation_image');
            }
        }
        if ($is_video == 1 && $setting['video_on'] == true) {
            $res = (new AliyunCloud($uid))->GreenScanVideo(H::path($localRule));
            if ($res != 'pass') {
                $pass = false;
                UsersSettingsModel::setViolation($uid, 'violation_video');
            }
        }

        if ($is_video == 2 && $setting['audio_on'] == true) {
            $res = (new AliyunCloud($uid))->GreenScanAudio(H::path($localRule));
            if (empty($res['text'])) {
                throw new \Exception('您好像没说话哦');
            }
            //自动审核语音
            if ($res['pass'] != 'pass') {
                $pass = false;
                UsersSettingsModel::setViolation($uid, 'violation_audio');
            }
        }

        if (!$pass) {
            throw new \Exception("您上传的文件存在不适宜展示的内容，请修改 [{$uid}] ");
        }
    }


    //批量定时发送
    public static function JPushBatch()
    {
        //获取所有定时消息
        $messages = SysMessageModel::where([['delete', 0], ['msg_type', 1]])->whereIn('type', [0, 2])->get();
        if (!$messages->isEmpty()) {
            foreach ($messages as $message) {
                if ($message->auth == date('H:i')) {
                    $msgText = [
                        'alert' => [
                            'title' => $message->title,
                            'body' => $message->cont,
                        ],
                        'ios' => [
                            "badge" => (int)1,
                            'sound' => 'default',
                            'extras' => [
                                'content' => $message->cont,
                            ]
                        ],
                    ];
                    (AuroraPush::getInstance())->batchPush($msgText);
                    //入库
                    $toDb = [
                        'title' => $message->title,
                        'body' => $message->cont,
                    ];
                    LogPushModel::storeToDb(0, $toDb, 1, 0);
                }
            }
        }
    }


    //一到两天及以上不打开App，则会在早10点至晚12点之间每隔两个小时推送一条激活唤醒通知*（诱导点击类）
    public static function JPushNotice($user_id)
    {
        $msg = [
            '美好的一天，从沟通开始吧，快去何你感兴趣的人一起聊天吧',
            '请问，我还有机会么？ 想认识你，交个朋友吧！',
            '你在哪呢？',
            '今晚，我去找你吧',
            '阳光炽热，空气炽热',
            '让我找你，我会用一颗火热的新爱着你',
            '上线聊天',
            '来呀，月亮不睡 我不睡~',
            '因为有爱',
            '所以，每一句话都想对你亲口说',
            '您喜欢的人已到达',
            '一键收获，并获取所在的位置',
            '嗨~你好呀',
            '很高兴认识你',
            '今天星期几？',
            '好想吻你呀！',
            '喜欢验证中...',
            '无限期有效，请接受',
        ];
        $randId = array_rand($msg, 1);
        $randText = $msg[$randId];
        $pushMsg = [
            "alert" => $randText,
            'extras' => [
                'content' => $randText,
                "badge" => (int)1,
            ]
        ];
        $pushed = LogPushModel::where([['user_id', $user_id], ['status', 1], ['msg_id', $randId], ['created_at', '>', date('Y-m-d H:i:s', time() - 86400 * 7)]])->first();
        if (!$pushed) {
            $auroraPush = AuroraPush::getInstance();
            $auroraPush->aliasPush($user_id, $pushMsg);
            //入库推送消息
            LogPushModel::storeToDb($user_id, $randText, 2, $randId);
        }

    }

    public static function JPushNoticeNewVersion($user_id)
    {
        $msg = [
            [
                'title' => '美好的一天，从沟通开始吧，快去何你感兴趣的人一起聊天吧',
                'body' => '请问，我还有机会么？ 想认识你，交个朋友吧！',
            ], [
                'title' => '你在哪呢？',
                'body' => '今晚，我去找你吧',
            ], [
                'title' => '阳光炽热，空气炽热',
                'body' => '让我找你，我会用一颗火热的新爱着你',
            ], [
                'title' => '上线聊天',
                'body' => '来呀，月亮不睡 我不睡~',
            ], [
                'title' => '因为有爱',
                'body' => '所以，每一句话都想对你亲口说',
            ], [
                'title' => '您喜欢的人已到达',
                'body' => '一键收获，并获取所在的位置',
            ], [
                'title' => '嗨~你好呀',
                'body' => '很高兴认识你',
            ], [
                'title' => '今天星期几？',
                'body' => '好想吻你呀！',
            ], [
                'title' => '喜欢验证中...',
                'body' => '无限期有效，请接受',
            ]
        ];
        $randId = array_rand($msg, 1);
        $randText = $msg[$randId];
        $pushMsg = [
            "alert" => [
                'title' => $randText['title'],
                'body' => $randText['body'],
            ],
            'extras' => [
                'ext' => [
                    'text' => $randText['title'],
                    'type' => 'notice'
                ],
            ],
            'content-available' => false,
            'sound' => 'default',
            'badge' => '+1',
        ];
        $pushed = LogPushModel::where([['user_id', $user_id], ['status', 1], ['type', 2], ['msg_id', $randId], ['created_at', '>', date('Y-m-d H:i:s', time() - 86400)]])->first();
        if (!$pushed) {
            $auroraPush = AuroraPush::getInstance();
            $res = $auroraPush->aliasPush($user_id, $pushMsg);
            //入库推送消息
            $db = [
                'title' => $randText['title'],
                'body' => $randText['body'],
            ];
            LogPushModel::storeToDb($user_id, $db, 2, $randId);
        }

    }


}
