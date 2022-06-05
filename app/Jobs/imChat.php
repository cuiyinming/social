<?php

namespace App\Jobs;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogImChatModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RongCloud;

class imChat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fromUser;
    protected $toUser;
    protected $msg;
    protected $sex;

    public function __construct($fromUser, $toUser, $msg, $sex)
    {
        $this->fromUser = $fromUser;
        $this->toUser = $toUser;
        $this->msg = $msg;
        $this->sex = $sex;
    }


    public function handle()
    {
        //检测
        if ($this->msg != '未知消息') {
            try {
                $err = 0;
                $imageIs = stripos($this->msg, 'ronghub.com') !== false;

                if ($this->toUser < 200) {
                    //追加客服消息转发 == S
                    $msgTitle = "客服消息通知";
                    $msg = "**用户ID [ " . $this->fromUser . " ]**\n" . "\n > *****     \n";
                    if ($imageIs) {
                        $msg .= "**![cont](" . $this->msg . ")**\n";
                    } else {
                        $msg .= "**" . $this->msg . "**\n";
                    }
                    return (new DingTalk(env('SERVICE_PUSH')))->sendMdMessage($msgTitle, $msg);
                }

                //追加客服消息转发 == E
                if ($imageIs) {
                    $res = (new AliyunCloud($this->fromUser))->GreenScanImage($this->msg);
                } else {
                    $res = (new AliyunCloud($this->fromUser))->GreenScanText($this->msg);
                }
                if ($res != 'pass') {
                    $err = 1;
                    UsersSettingsModel::setViolation($this->fromUser, 'violation_chat');
                    //在这里统计禁言
                    HR::updateUniqueNum($this->fromUser, H::gainStrId(), 'banned-chat', true, config('settings.banned')['banned_time']);
                }
                LogImChatModel::create([
                    'user_id' => $this->fromUser,
                    'target_user_id' => $this->toUser,
                    'cont' => $this->msg,
                    'err' => $err,
                ]);
                //极光推送
                //$toUser = UsersModel::getUserInfo($this->toUser);
                //跳转地址
                // $jump = UsersMsgModel::schemeUrl('', 24, '立即查看', $this->toUser, '立即查看');
                // JpushModel::pushSender($this->toUser, ['title' => '您有新消息', 'cont' => $toUser->nick . ' 发来一条新的消息', 'extras' => ['jump' => $jump]]);
                //file_put_contents('/tmp/push_log.log', print_r($sender, 1) . PHP_EOL, FILE_APPEND);
                //对消息数量进行统计并扣费
                //$chat_num = HR::incr($this->fromUser);
                //$chat_rate = $this->sex == 1 ? 2 : 1;   //女的聊2句话扣费一次，男的是1句
                //if ($chat_num % $chat_rate == 1) {  //每聊天2句扣一次费
                //    //vip 不扣币
                //    $profile = UsersProfileModel::getUserInfo($this->fromUser);
                //    if ($profile->vip_is != 1) {
                //        //系统级默认收费值
                //        $price = config('settings.im_chat_price');
                //        //用户的价格配置
                //        $toSetting = UsersSettingsModel::getUserSettings($this->toUser);
                //        if (isset($toSetting['msg_price']) && $toSetting['msg_price'] > 0) {
                //            $price = $toSetting['msg_price'];
                //        }
                //        if ($price > 0) {
                //            //收费设置
                //            $user = UsersModel::where('id', $this->fromUser)->first();
                //            $chat_sex = config('settings.im_chat_sex');
                //            if (($this->sex == 1 && in_array($chat_sex, [1, 3])) || ($this->sex == 2 && in_array($chat_sex, [2, 3]))) {
                //                $desc = '与用户 ' . $this->toUser . ' 聊天，花费' . $price . '友币';
                //                $remark = '与用户 ' . $this->toUser . ' 聊天，花费' . $price . '友币';
                //                if (!$user) {
                //                    throw new \Exception('im聊天对象' . $this->toUser . '不存在');
                //                }
                //                $before = $user->sweet_coin;
                //                if ($before > 0) {
                //                    $after = $before - $price;
                //                    if ($after <= 0) $after = 0;
                //                    $user->sweet_coin = $after;
                //                    $user->save();
                //                    LogBalanceModel::gainLogBalance($this->fromUser, $before, $price, $after, 'im_chat', $desc, $remark);
                //                }
                //            }
                //            //给用户聊天分成
                //            $settings = config('settings.benefit_share');
                //            $msg_sex = $settings['msg_sex'];
                //            //1女 2男 设置收费的性别
                //            if ($toUser && (($toUser->sex == 1 && in_array($msg_sex, [1, 3])) || ($toUser->sex == 2 && in_array($msg_sex, [2, 3])))) {
                //                $profile = UsersProfileModel::getUserInfo($this->toUser);
                //                $authed = $profile->real_is == 1 && $profile->identity_is == 1;
                //                $rate = $authed ? $settings['msg_rate'] : $settings['msg_rate_unverified'];
                //                $get_price = floor($price * $rate);
                //                if ($get_price > 0) {
                //                    $desc = "与 {$this->fromUser} 聊天，奖励心钻 {$get_price} 颗";
                //                    $remark = "与 {$this->fromUser} 聊天，奖励心钻 {$get_price} 颗，";
                //                    if (!$authed) {
                //                        $remark .= "【未完成认证】";
                //                    }
                //                    $before = $toUser->jifen;
                //                    $toUser->jifen += $get_price;
                //                    $toUser->save();
                //                    LogBalanceModel::gainLogBalance($this->toUser, $before, $get_price, $toUser->jifen, 'im_chat', $desc, $remark, 0, 'log_jifen');
                //                }
                //            }
                //        }
                //    }
                //
                //}
                //首次聊天下发聊天奖励
                //UsersRewardModel::userDailyRewardSet($this->fromUser, 'sixinliaotian');
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }

        }

    }
}
