<?php

namespace App\Http\Models\Users;

use App\Components\ESearch\ESearch;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Helpers\S;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogSignModel;
use App\Http\Models\Payment\SubscribeModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RongCloud;

//用户奖励发放模型
// 1 完善资料页面，
// 2 实名认证
// 3 更换头像
// 4 语音签名
// 5 指定话题列表
// 6 真人认证
// 7 跳转VIP页面
// 8 关注 -> 首页列表
// 9 动态评论 -> 动态首页
// 10 完善相册 -> 相册编辑页面
// 11 录音签名 -> 语音签名页面
// 12 首冲奖励 -> 充值页面
// 13 每日动态奖励 -> 发动态
// 14 私信聊天 -> 首页列表
// 15 语音通话 ->  首页列表
// 16 女神认证
// 17 每日签到 --> 任务列表
// 18 跳转完善QQ
// 19 跳转完善微信
// 20 邀请好友赚现金
class UsersRewardModel extends Model
{
    //新手任务
    public static function userRewardSet($uid, $task = 'guanzhu', $user = null)
    {
        $user = is_null($user) ? UsersModel::getUserInfo($uid) : $user;
        //获取奖励数 && 只处理一次
        $reward = S::getTaskReward($task);
        $follow = UsersSettingsModel::getSingleUserSettings($uid, $task);
        if ($reward > 0 && $follow == 0) {
            $before = $user->sweet_coin;
            $user->sweet_coin = $after = $before + $reward;
            $user->save();
            extract(self::_descGet($task, $reward));
            LogBalanceModel::gainLogBalance($user->id, $before, $reward, $after, 'reward_' . $task, $desc, $remark);
            UsersSettingsModel::where('user_id', $user->id)->update([$task => 1]);
            UsersSettingsModel::refreshUserSettings($user->id, $task, 1); //刷新redis的数据
            //同步下发奖励
            self::sendImMsg($user->id, $desc, $remark, $reward);
        }
        //首充奖励

    }

    //完善资料扩展任务
    public static function userRewardSetExt($uid, $task = 'guanzhu', $user = null)
    {
        if ($task == 'ziliao') {
            $score = self::_judgeSense($uid, $task);
            if ($score) {
                self::userRewardSet($uid, $task, $user);
                //新增更新资料完善标记位
                UsersProfileModel::where('user_id', $uid)->update(['complete' => 1]);
            }
        }
    }

    //首充奖励vip 3天
    public static function firstChargeReward($uid, $task = 'shouchongjiangli')
    {
        //下发3天奖励 1手动修改 2奖励下发
        $exp = date('Y-m-d H:i:s', time() + 86400 * 2);
        $exp_date = date('Y-m-d', time() + 86400 * 2);
        $update = [
            'vip_handle' => 2,
            'vip_is' => 1,
            'vip_level' => 1,
            'vip_at' => null,
            'vip_exp_time' => $exp,   //赠送3天剑士VIP
        ];
        UsersProfileModel::where('user_id', $uid)->update($update);
        UsersSettingsModel::where('user_id', $uid)->update(['shouchongjiangli' => 1]);
        //刷新用户配置
        UsersSettingsModel::refreshUserSettings($uid, $task, 1); //刷新redis的数据
        //更新es数据
        $esVipArr = [
            [
                'id' => $uid,
                'vip_is' => 1,
                'vip_level' => 1,
            ]
        ];
        (new ESearch('users:users'))->updateSingle($esVipArr);
        //添加用户金币变动记录
        $user = UsersModel::where('id', $uid)->first();
        $desc = "首充奖励VIP 3天，" . $exp_date . '到期';
        $remark = "首充奖励VIP 3天，" . $exp . '到期';
        $before = $after = $user->sweet_coin;
        $change = 0;
        LogBalanceModel::gainLogBalance($uid, $before, $change, $after, 'first_recharge', $desc, $remark);
        $desc = '首充奖励';
        self::sendImMsg($user->id, $desc, $remark, 0.01);
        //发送系统消息101并入库消息
        $msg = [
            'title' => '首次充值奖励',
            'cont' => '恭喜您首次充值完成并获得了为期3天的VIP奖励，该奖励预计：' . $exp_date . ' 到期，如果您觉得vip服务还满意欢迎续订哦！',
            'type' => 'first_recharge'
        ];
        UsersMsgSysModel::storeMsg($user->id, $msg);
        //file_put_contents('/tmp/reward_vip.log', '奖励时间：' . date('Y-m-d H:i:s') . '_' . $uid . '_' . $exp . PHP_EOL, FILE_APPEND);
    }


    public static function firstVipReward($uid, $task)
    {
        UsersSettingsModel::where('user_id', $uid)->update([$task => 1]);
        //刷新用户配置
        UsersSettingsModel::refreshUserSettings($uid, $task, 1); //刷新redis的数据
        //下发对应奖励
        $level = 0;
        $name = '';
        if ($task == 'swordsman_reward') {
            $level = 1;
            $name = '剑士';
        }
        if ($task == 'knight_reward') {
            $level = 4;
            $name = '骑士';
        }
        if ($task == 'suzerain_reward') {
            $level = 7;
            $name = '领主';
        }
        if ($task == 'lord_reward') {
            $level = 10;
            $name = '勋爵';
        }
        $sub = SubscribeModel::getRightTimes($level);
        $reward = $sub['reward'];
        if ($reward > 0 && $level > 0) {
            //添加用户金币变动记录
            $user = UsersModel::where('id', $uid)->first();
            $desc = "首次开通" . $name . 'VIP，奖励友币' . $reward . '个';
            $remark = "首次开通" . $name . 'VIP，奖励友币' . $reward . '个';
            $before = $user->sweet_coin;
            $after = $before + $reward;
            $user->sweet_coin = $after;
            $user->save();
            LogBalanceModel::gainLogBalance($uid, $before, $reward, $after, 'first_' . $task, $desc, $remark);
            //发送系统奖励通知
            $desc = '开通奖励';
            self::sendImMsg($user->id, $desc, $remark, $reward);
            //添加系统消息一条
            $msg = [
                'title' => '首次开通' . $name . 'VIP送友币奖励',
                'cont' => '恭喜您首次开通:' . $name . '成功，系统奖励的 ' . $reward . '心友币已经下发到您的账户，请注意您的友币余额变化！',
                'type' => 'first_vip'
            ];
            UsersMsgSysModel::storeMsg($user->id, $msg);
        }
    }

    //每日任务 [每日直播+邀请好友+语音通话+视频通话 没做]
    public static function userDailyRewardSet($uid, $task = 'meiridongtai', $user = null)
    {
        $user = is_null($user) ? UsersModel::getUserInfo($uid) : $user;
        //获取奖励数 && 只处理一次
        $reward = S::getTaskReward($task, 'normal');
        $exist = HR::existUniqueNum($uid, date('Y-m-d'), 'users-' . $task);
        //file_put_contents('/tmp/everyday.log', print_r(['=======下发前======', $reward, $exist], 1), 8);
        if (in_array($user->id, [187499, 141093])) {
            //file_put_contents('/tmp/everyday.log', print_r(['=======测试======', $reward, $exist], 1), 8);
            extract(self::_descDailyGet($task, $reward));
            self::sendImMsg($user->id, $desc, $remark, $reward);
            return false;
        }
        if ($reward > 0 && $exist != 1) {
            $before = $user->sweet_coin;
            $user->sweet_coin = $after = $before + $reward;
            $user->save();
            extract(self::_descDailyGet($task, $reward));
            LogBalanceModel::gainLogBalance($user->id, $before, $reward, $after, 'reward_' . $task, $desc, $remark);
            //同步下发奖励
            self::sendImMsg($user->id, $desc, $remark, $reward);
            HR::updateUniqueNum($uid, date('Y-m-d'), 'users-' . $task);
        }
    }

    //签到奖励
    public static function signReward($uid, $day): array
    {
        $reward = S::getSignRewardByDay($day);
        $res = [];
        if ($day > 0 && $reward > 0) {
            $user = UsersModel::getUserInfo($uid);
            $before = $user->sweet_coin;
            $user->sweet_coin += $reward;
            $remark = "连续签到{$day}天，奖励友币{$reward}个";
            $desc = "已连续签到 {$day} 天";
            LogBalanceModel::gainLogBalance($uid, $before, $reward, $user->sweet_coin, 'day_sign', $desc, $remark);
            $user->save();
            //完成后下放奖励im 奖励下发弹窗已经变味实时显示的了
            //入库redis记录完成情况
            HR::updateUniqueNum($uid, date('Y-m-d'), 'users-meiriqiandao');
            $res = [
                'title' => '签到成功',
                'text' => $desc,
                'day' => $day,
                'reward' => $reward,
            ];
        }
        return $res;
    }

    //每日任务
    private static function _judgeSense($uid, $name = 'ziliao'): bool
    {
        $score = 0; //总共33
        if ($name == 'ziliao') {
            $columns = [
                'qq', 'wechat', 'hometown', 'profession', 'stature', 'weight', 'somatotype', 'charm', 'salary', 'degree', 'marriage', 'house',
                'cohabitation', 'dating', 'purchase_house', 'purchase_car', 'drink', 'smoke', 'cook', 'relationship', 'expect_stature',
                'expect_age', 'expect_degree', 'expect_salary', 'expect_hometown', 'expect_live_addr', 'tags', 'hobby_sport', 'hobby_music',
                'hobby_food', 'hobby_movie', 'hobby_book', 'hobby_footprint'
            ];
            $profile = UsersProfileModel::select($columns)->where('user_id', $uid)->first();
            foreach ($columns as $column) {
                if (!empty($profile->$column)) {
                    $score += 1;
                }
            }
            return $score > 25;
        }
    }

    /*-- 100 官方消息  101 系统通知 102 客服id 103 点赞等消息的推送  104 底部完善资料通知 105 批量打招呼通知
    --- 106 签到弹窗  107 心友币奖励弹窗 108 任务现金/心钻奖励弹窗 109 im聊天解锁联系方式推送弹窗 110 优质用户推荐弹窗---*/
    public static function sendImMsg($uid, $title, $cont, $reward, $channel = 107, $sex = 1)
    {
        //106不在推送 【签到不在每日直接推送了】
        if ($channel == 106) {
            return false;
        }
        //对于107 的安卓渠道全部不推送 [目前安卓存在逻辑问题]
//        if ($channel == 107 && CHANNEL == 'android') {
//            return false;
//        }
        if ($channel == 107) {
//            if ($reward == 0.01) {
//                $cont = '首充奖励VIP 2 天';
//            } else {
            $cont = '任务奖励友币' . $reward . '个';
//            }
        }
        if ($channel == 104) {
            $cont = '完善资料赚心友币，您有23个友币待领取';
            $title = $reward = '23个友币';
        }
        $notice = [
            'title' => $title,
            'content' => $cont,
            'extra' => [
                'reward' => $reward,
                'title_str' => $title,
                'cont_str' => $cont,
            ],
        ];
        if ($channel == 105) {
            //反性别推送推荐
            $sex = $sex == 1 ? 2 : 1;
            $randRes = UsersModel::getRandUsersByDistance($uid, $sex);
            $notice = [
                'title' => '批量打招呼',
                'content' => '批量打招呼',
                'extra' => json_encode($randRes),
            ];
        }
        if ($channel == 106) {
            $signMap = config('subscribe.sign');
            $sign = LogSignModel::where('user_id', $uid)->first();
            $title = '签到领友币';
            if ($sign) {
                $spacer = strtotime(date('Y-m-d') . ' 00:00:00') - strtotime($sign->last_date . ' 00:00:00');
                if ($spacer <= 86400) {
                    $title = '已连续签到 ' . $sign->serial . ' 天';
                    foreach ($signMap as &$item) {
                        if ($sign->last_date == date('Y-m-d') || $item['day'] <= $sign->serial) $item['tips'] = '';
                        if ($item['day'] == $sign->serial + 1 && $sign->last_date != date('Y-m-d')) $item['tips'] = '今日可签';
                        if ($item['day'] <= $sign->serial) $item['signed'] = true;
                        if ($item['day'] == $sign->serial + 1 && $sign->last_date != date('Y-m-d')) $item['day_str'] = '今天';
                    }
                }
            }
            foreach ($signMap as &$item) {
                unset($item['reward_int']);
                unset($item['day']);
            }
            $res['title'] = $title;
            $res['sign_remind'] = UsersSettingsModel::getSingleUserSettings($uid, 'sign_remind');
            $res['sign'] = $signMap;
            $notice = [
                'title' => '签到推送',
                'content' => '签到推送',
                'extra' => json_encode($res),
            ];
        }
        //file_put_contents('/tmp/everyday.log', print_r([$channel, $uid, json_encode($notice, JSON_UNESCAPED_UNICODE)], 1) . PHP_EOL, FILE_APPEND);
        $res = RongCloud::messageSystemPublish($channel, [$uid], 'RC:TxtMsg', json_encode($notice, JSON_UNESCAPED_UNICODE));

    }

    private static function _descDailyGet($name = 'meiridongtai', $reward = 0): array
    {
        switch ($name) {
            case 'meiridongtai':
                $desc = "动态奖励";
                $remark = date('m-d') . " 发布动态奖励友币 " . $reward . ' 个';
                break;
            case 'meiridashan':
                $desc = "搭讪奖励";
                $remark = date('m-d') . " 搭讪奖励友币 " . $reward . ' 个';
                break;
            case 'sixinliaotian':
                $desc = "私信奖励";
                $remark = date('m-d') . " 聊天奖励友币 " . $reward . ' 个';
                break;
            case 'yaoqinghaoyou':
                $desc = "邀请奖励";
                $remark = "邀请好友奖励友币 " . $reward . ' 个';
                break;
            case 'yuyintonghua':
                $desc = "语音奖励";
                $remark = date('m-d') . " 语音速配奖励友币 " . $reward . ' 个';
                break;
            case 'shipintonghua':
                $desc = "视频奖励";
                $remark = date('m-d') . " 视频速配奖励友币 " . $reward . ' 个';
                break;
            default:
                $desc = "每日奖励";
                $remark = date('m-d') . "任务奖励 " . $reward . ' 个';
        }
        return ['desc' => $desc, 'remark' => $remark];
    }

    private static function _descGet($name = 'guanzhu', $reward = 0): array
    {
        switch ($name) {
            case 'guanzhu':
                $desc = "关注好友";
                $remark = "关注3个好友奖励友币 " . $reward . ' 个';
                break;
            case 'pinglun':
                $desc = "评论动态";
                $remark = "评论3个动态奖励友币 " . $reward . ' 个';
                break;
            case 'touxiang':
                $desc = "完善头像";
                $remark = "设置头像奖励友币 " . $reward . ' 个';
                break;
            case 'xiangce':
                $desc = "完善相册";
                $remark = "上传超过3张相册照片奖励友币 " . $reward . ' 个';
                break;
            case 'ziliao':
                $desc = "完善资料";
                $remark = "完善个人信息资料奖励友币 " . $reward . ' 个';
                break;
            case 'yuyinqianming':
                $desc = "语音签名";
                $remark = "录制语音签名奖励友币 " . $reward . ' 个';
                break;
            case 'zhenrenrenzheng':
                $desc = "真人认证";
                $remark = "真人认证奖励友币 " . $reward . ' 个';
                break;
            case 'shimingrenzheng':
                $desc = "实名认证";
                $remark = "实名认证奖励友币 " . $reward . ' 个';
                break;
            case 'nvshenrenzheng':
                $desc = "女神认证";
                $remark = "女神认证奖励友币 " . $reward . ' 个';
                break;
            case 'shouhunvshen':
                $desc = "守护女生";
                $remark = "守护1个女生奖励友币 " . $reward . ' 个';
                break;
            case 'yuyinsupei':
                $desc = "语音速配";
                $remark = "完成语音速配奖励友币 " . $reward . ' 个';
                break;
            case 'shipinsupei':
                $desc = "视频速配";
                $remark = "完成视频速配奖励友币 " . $reward . ' 个';
                break;
            case 'shouchongjiangli':
                $desc = "充值奖励";
                $remark = "抽次充值奖励友币 " . $reward . ' 个';
                break;
            default:
                $desc = "任务奖励";
                $remark = "完成任务奖励友币 " . $reward . ' 个';
        }
        return ['desc' => $desc, 'remark' => $remark];
    }
}
