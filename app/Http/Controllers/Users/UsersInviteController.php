<?php

namespace App\Http\Controllers\Users;

use App\Components\ESearch\ESearch;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogDrawModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Helpers\{H, HR};
use App\Http\Models\SettingsModel;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Models\EsDataModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\AuthController;

class UsersInviteController extends AuthController
{

    //邀请相关逻辑
    public function inviteItemsList(Request $request)
    {
        $res = [];
        //奖品
        $items = config('self.invite_items');
        $res['items'] = $items;
        return $this->jsonExit(200, 'OK', $res);
    }


    public function inviteRankList(Request $request)
    {
        //奖品[区分版本]
        $version = intval(str_replace('.', '', VER));
        if ($version >= 300) {
            $base_info = [
                'title' => '每邀请1人成功注册',
                'sub_title' => '即可获得1天VIP',
            ];
            //邀请码
            $user = UsersModel::getUserInfo($this->uid);
            $base_info['invite_code'] = $user->uinvite_code;
            $res['base_info'] = $base_info;
            $invite_rule = [
                [
                    [
                        'text' => '1.邀请好友注册，对方注册成功，则您可以获得1天的VIP时长',
                        'color' => '#FF3967',
                        'font' => 15,
                    ],
                    [
                        'text' => '2.邀请好友注册，当邀请的好友开通vip或充值后您可以额外在获得1天的vip',
                        'color' => '#FF3967',
                        'font' => 15,
                    ]
                ], [
                    [
                        'text' => '邀请奖励示例说明',
                        'color' => '#191919',
                        'font' => 16,
                    ]
                ], [
                    [
                        'text' => '你邀请的好友注册成功',
                        'color' => '#191919',
                        'font' => 14,
                    ], [
                        'text' => '你获得1天的VIP奖励',
                        'color' => '#FF3967',
                        'font' => 14,
                    ], [
                        'text' => '你邀请的好友开通VIP或内购充值成功',
                        'color' => '#191919',
                        'font' => 14,
                    ], [
                        'text' => '你在原有基础上额外获得1天的VIP奖励',
                        'color' => '#FF3967',
                        'font' => 14,
                    ], [
                        'text' => '您邀请的好友好友越多，收到的奖励就越多',
                        'color' => '#191919',
                        'font' => 14,
                    ], [
                        'text' => '快邀请好友一起参与吧',
                        'color' => '#FF3967',
                        'font' => 14,
                    ],
                ], [
                    [
                        'text' => '特殊说明：您的奖励会在会员注册成功后的1个小时内及时发放，请耐心等待，如遇问题可以及时联系客服，感谢您的支持',
                        'color' => '#999999',
                        'font' => 13,
                    ]
                ],
            ];
            //奖励排行榜
            $res['invite_rule'] = $invite_rule;
            //滚动人员列表
            $sex = $this->sex == 1 ? 2 : 1;
            $nicks = UsersModel::where('sex', $sex)->limit(15)->pluck('nick');
            foreach ($nicks as $k => $nick) {
                $nick = '【' . H::hideNick($nick) . '】邀请获' . (($k % 4) + 1) . '天VIP会员';
                $nicks[$k] = $nick;
            }
            $res['nick_list'] = $nicks;
            //邀请排行榜
            $res['site_rank'] = $this->_siteRank2();
            $user_list = $this->_inviteUser2($user->uinvite_code);
            $user = UsersModel::getUserInfo($this->uid);
            $res['self_invite'] = [
                'reward' => $user->vip_reward > 0 ? $user->vip_reward . '天VIP' : '暂无奖励',
                'ext_reward' => [],
                'divide' => [],
            ];
            $res['self_invite']['user_count'] = $user_list['count'];
            $res['self_invite']['user_list'] = $user_list['items'];
            //分享部分内容
            $res['share_info'] = $this->_shareInfo($user->uinvite_code);
        } else {
            $base_info = config('self.invite_base');
            //邀请码
            $user = UsersModel::getUserInfo($this->uid);
            $base_info['invite_code'] = $user->uinvite_code;
            $res['base_info'] = $base_info;
            //奖励排行榜
            $res['invite_rule'] = config('self.invite_rule');
            //滚动人员列表
            $sex = $this->sex == 1 ? 2 : 1;
            $nicks = UsersModel::where('sex', $sex)->limit(15)->pluck('nick');
            foreach ($nicks as $k => $nick) {
                $nick = '用户【' . H::hideNick($nick) . '】邀请获10元奖励';
                $nicks[$k] = $nick;
            }
            $res['nick_list'] = $nicks;
            //邀请排行榜
            $res['site_rank'] = $this->_siteRank();
            $user_list = $this->_inviteUser($user->uinvite_code);
            $res['self_invite'] = $this->_extInfo($this->uid);
            $res['self_invite']['user_count'] = $user_list['count'];
            $res['self_invite']['user_list'] = $user_list['items'];
            //分享部分内容
            $res['share_info'] = $this->_shareInfo($user->uinvite_code);
        }
        //邀请的好友
        return $this->jsonExit(200, 'OK', $res);
    }


    private function _siteRank2(): array
    {
        $res = $idArr = $profileArr = [];
        $users = UsersModel::select(['id', 'nick', 'avatar', 'vip_reward', 'live_location', 'sex', 'birthday', 'constellation'])->where([['status', 1], ['vip_reward', '>', 0]])->orderBy('vip_reward', 'desc')->limit(10)->get();
        foreach ($users as $user) {
            $idArr[] = $user->id;
            $sex_str = $user->sex == 1 ? '女' : '男';
            $res[] = [
                'user_id' => $user->id,
                'nick' => $user->nick,
                'avatar' => $user->avatar,
                'reward' => $user->vip_reward == 0 ? '暂未获得奖励' : '累计奖励 ' . $user->vip_reward . ' 天VIP',
                'base_str' => $user->live_location . ' | ' . $sex_str . '·' . H::getAgeByBirthday($user->birthday) . ' | ' . $user->constellation,
            ];
        }
        $profile = UsersProfileModel::whereIn('user_id', $idArr)->get();
        if (!$profile->isEmpty()) {
            foreach ($profile as $pro) {
                $profileArr[$pro->user_id] = [
                    'bio' => $pro->bio,
                    'base_str' => $pro->profession,
                ];
            }
        }
        foreach ($res as &$item) {
            $base_str = $item['base_str'];
            if (isset($profileArr[$item['user_id']]['base_str']) && !empty($profileArr[$item['user_id']]['base_str'])) {
                $base_str .= ' | ' . $profileArr[$item['user_id']]['base_str'];
            }
            $item['bio'] = $profileArr[$item['user_id']]['bio'] ?? $base_str;
            unset($item['user_id']);
            unset($item['base_str']);
        }
        return $res;
    }


    private function _siteRank(): array
    {
        $res = $idArr = $profileArr = [];
        $users = UsersModel::select(['id', 'nick', 'avatar', 'invite_reward_grand', 'live_location', 'sex', 'birthday', 'constellation'])->where('status', 1)->orderBy('invite_reward_grand', 'desc')->limit(10)->get();
        foreach ($users as $user) {
            $idArr[] = $user->id;
            $sex_str = $user->sex == 1 ? '女' : '男';
            $res[] = [
                'user_id' => $user->id,
                'nick' => $user->nick,
                'avatar' => $user->avatar,
                'reward' => '累计奖励 | ' . $user->invite_reward_grand . '元现金',
                'base_str' => $user->live_location . ' | ' . $sex_str . '·' . H::getAgeByBirthday($user->birthday) . ' | ' . $user->constellation,
            ];
        }
        $profile = UsersProfileModel::whereIn('user_id', $idArr)->get();
        if (!$profile->isEmpty()) {
            foreach ($profile as $pro) {
                $profileArr[$pro->user_id] = [
                    'bio' => $pro->bio,
                    'base_str' => $pro->profession,
                ];
            }
        }
        foreach ($res as &$item) {
            $base_str = $item['base_str'];
            if (isset($profileArr[$item['user_id']]['base_str']) && !empty($profileArr[$item['user_id']]['base_str'])) {
                $base_str .= ' | ' . $profileArr[$item['user_id']]['base_str'];
            }
            $item['bio'] = $profileArr[$item['user_id']]['bio'] ?? $base_str;
            unset($item['user_id']);
            unset($item['base_str']);
        }
        return $res;
    }

    private function _inviteUser2($invite_code): array
    {
        $res = ['items' => [], 'count' => 0];
        $builder = UsersModel::where([['status', 1], ['invited', $invite_code]])->orderBy('id', 'desc');
        $count = $builder->count();
        $usersInvites = $builder->get();
        if (!$usersInvites->isEmpty()) {
            foreach ($usersInvites as $usersInvite) {
                $res['items'][] = [
                    'user_id' => $usersInvite->id,
                    'nick' => $usersInvite->nick,
                    'avatar' => $usersInvite->avatar,
                    'invite_at' => $usersInvite->created_at->format('m/d H:i'),
                    'reward' => $usersInvite->vip_reward == 0 ? '暂未获得奖励' : '累计奖励 ' . $usersInvite->vip_reward . ' 天VIP',
                ];
            }
        }
        $res['count'] = $count;
        return $res;
    }

    private function _inviteUser($invite_code): array
    {
        $res = ['items' => [], 'count' => 0];
        $builder = UsersModel::where([['status', 1], ['invited', $invite_code]])->orderBy('id', 'desc');
        $count = $builder->count();
        $usersInvites = $builder->get();
        if (!$usersInvites->isEmpty()) {
            foreach ($usersInvites as $usersInvite) {
                $res['items'][] = [
                    'user_id' => $usersInvite->id,
                    'nick' => $usersInvite->nick,
                    'avatar' => $usersInvite->avatar,
                    'invite_at' => $usersInvite->created_at->format('m/d H:i'),
                ];
            }
        }
        $res['count'] = $count;
        return $res;
    }

    private function _extInfo($uid): array
    {
        $extReward = $divide = [];
        $total = 0;
        //额外奖励获取
        $logs = LogBalanceModel::suffix('log_jifen')
            ->where([['user_id', $uid], ['operate', '+']])
            ->whereIn('type', ['invite_inner_benefit', 'invite_draw_benefit', 'invite_gift_benefit'])
            ->orderBy('id', 'desc')
            ->get();
        if (!$logs->isEmpty()) {
            foreach ($logs as $log) {
                $total += $log->amount;
                $row = [
                    'title' => $log->remark,
                    'reward' => $log->operate . $log->amount,
                    'reward_at' => date('m/d H:i', strtotime($log->created_at)),
                ];
                if (in_array($log->type, ['invite_inner_benefit', 'invite_draw_benefit'])) {
                    $extReward[] = $row;
                }
                if (in_array($log->type, ['invite_gift_benefit'])) {
                    $divide[] = $row;
                }
            }
        }
        return [
            'reward' => $total . '心钻/折合￥' . number_format($total / config('settings.points_rate'), 1),
            'ext_reward' => $extReward,
            'divide' => $divide,
        ];
    }

    private function _shareInfo($code): array
    {
        $tencent = 'http://hfriend.cn/dnd/index.html?channel=invite&id=' . $code;
//        $tencent = 'http://nxw.so/5ftmY';
        $res = [];
        $share['url'] = [
            'qq' => 'http://hfriend.cn/dnd/index.html?channel=invite&id=' . $code,
            'wechat' => 'http://hfriend.cn/dnd/index.html?channel=invite&id=' . $code,
//            'qq' => 'http://nxw.so/5ftmY',
//            'wechat' => 'http://nxw.so/5ftmY',
        ];
        $share['title'] = '想脱单，来心友，海量小哥哥小姐姐等你来撩！';
        $share['text'] = '海量真实用户秒回复，真人认证安全有保证，万千会员的脱单选择。';
        $share['avatar'] = 'http://static.hfriend.cn/vips/icon_360.jpg';
        $res['share'] = $share;
        //连接文本
        $text = <<<EOL
最新脱单神器，真人小姐姐在线交友！
一键查看附近的人[色]

下载【心友App】
↓
注册登录
↓
填邀请码【{$code}】

离你462米有3位小姐姐已经注册！点击下载⬇
「{$tencent}」

（复制整条信息可用于粘贴邀请码）
EOL;
        $res['link'] = $text;
        $res['qrcode'] = $tencent;
        return $res;
    }


    //发起收益提现
    public function drawAsk(Request $request)
    {
        $draw_amount = $request->input('draw_amount', 100);
        $user = UsersModel::where('id', $this->uid)->first();
        if (empty($user->draw_account)) {
            return $this->jsonExit(201, '提现账户未完善');
        }
        $min_draw = config('settings.min_draw');
        if ($draw_amount < $min_draw) {
            return $this->jsonExit(204, '提现金额不能小于' . number_format($min_draw, 2) . '元');
        }
        $points_rate = config('settings.points_rate');
        $draw_jifen = $draw_amount * $points_rate;
        if ($draw_jifen > ($user->jifen - $user->jifen_frozen)) {
            return $this->jsonExit(203, '提现金额超限制');
        }
        $count = LogDrawModel::where([['status', 0], ['user_id', $this->uid]])->count();
        if ($count > 0) {
            return $this->jsonExit(206, '存在未完成的提现，请等待');
        }
        //认证状态核验
        $profile = UsersProfileModel::getUserInfo($this->uid);
        if ($profile->real_is == 0) {
            return $this->jsonExit(206, '提现需先完成真人认证');
        }
        //实名认证
        if ($profile->identity_is == 0) {
            return $this->jsonExit(206, '提现需先完成实名认证');
        }
        //获取实名姓名
        try {
            DB::beginTransaction();
            $order_sn = H::genOrderSn(9);
            $name = UsersProfileModel::getUserInfo($this->uid)->identity_name;
            //创建提现记录
            $data = [
                'user_id' => $this->uid,
                'auth_name' => $name,
                'jifen' => $draw_jifen,
                'account' => $user->draw_account,
                'amount' => number_format($draw_amount, 2),
                'status' => 0,
                'order_sn' => $order_sn,
            ];
            LogDrawModel::create($data);
            //冻结提现金额
            $before_jifen = $user->jifen;
            $user->jifen = $before_jifen - $draw_jifen;
            $user->jifen_frozen += $draw_jifen;
            $user->save();
            //添加心钻提现变动记录
            $drawLog = [
                'amount' => $user->jifen,
                'before_amount' => $before_jifen,
                'change_amount' => $draw_jifen,
                'order_sn' => $order_sn,
                'adm_id' => 0,
                'user_id' => $this->uid,
                'type' => 'draw_out',
                'operate' => '-',
                'desc' => '心钻提现：' . $draw_jifen . ' 颗，审核中',
                'remark' => '心钻提现：' . $draw_jifen . ' 颗',
                'created_at' => CORE_TIME,
                'updated_at' => CORE_TIME
            ];
            LogBalanceModel::suffix('log_jifen')->insert($drawLog);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(202, '服务错误');
        }
        return $this->jsonExit(200, 'OK');
    }

    //提现首页
    public function drawInfo(Request $request)
    {
        $user = UsersModel::getUserInfo($this->uid);
        $profile = UsersProfileModel::getUserInfo($this->uid);
        $rate = config('settings.points_rate');
        $min_draw = number_format(config('settings.min_draw'), 2);
        //查询今日收益 【定义是收益 gift_income  | chat_income | site_reward】
        $typeArr = [
            'gift_income',
            'chat_income',
            'site_reward',
        ];
        $sum = LogBalanceModel::suffix('log_jifen')
            ->where([['user_id', $this->uid], ['operate', '+'], ['created_at', '>', date('Y-m-d 00:00:00')], ['created_at', '<=', date('Y-m-d 23:59:59')]])
            ->whereIn('type', $typeArr)
            ->sum('change_amount');
        $base_info = [
            'diamond' => $user->jifen . ' 颗',
            'amount' => number_format($user->jifen / $rate, 2) . ' 元',
            'under_check' => number_format($user->jifen_frozen / $rate, 2),
            'today_income' => number_format($sum / $rate, 2),
            'base_str' => [
                [
                    'text' => $rate . '心钻 ≈ ￥1',
                    'color' => '#999999',
                    'font' => 12,
                ], [
                    'text' => '满 ' . $min_draw . ' 元即可提现',
                    'color' => '#999999',
                    'font' => 12,
                ], [
                    'text' => '提现收益会进入审核和处理状态，审核通过后系统会自动进行处理，审核和处理工作会在72小时内完成，节假日顺延。',
                    'color' => '#191919',
                    'font' => 12,
                ], [
                    'text' => '提现遇到问题请联系官方客服：客服中心',
                    'color' => '#999999',
                    'font' => 12,
                ]
            ],
            'tips_str' => '请用尾号' . substr(H::decrypt($user->mobile), 7, 4) . '的手机获取验证码',
            'account' => empty($user->draw_account) ? '' : H::decrypt($user->draw_account),
            'real_is' => $profile->real_is == 1,
            'identity_is' => $profile->identity_is == 1,
        ];
        return $this->jsonExit(200, 'OK', $base_info);
    }

    public function sendSmsCode(Request $request)
    {
        $type = $request->input('type', 'ask_draw');
        $mobile = H::decrypt(UsersModel::getUserInfo($this->uid)->mobile);
        //判断指定时间发送数量 [同一手机号]
        $max_setting = config('common.max_sms_time');
        $has_send_time = LogSmsModel::geSmsNum($mobile, $type);
        if ($has_send_time >= $max_setting) {
            return $this->jsonExit(203, '获取短信过频，请稍后再试');
        }
        //同一ip 通过redis 自动的拦截 【同一ip单日最大发送条数为6条】
        if (HR::getUniqueNum(IP, 'sms-mobile-') >= 6) {
            return $this->jsonExit(204, '获取短信超限，请联系管理员');
        }
        $sendResult = LogSmsModel::sendMsg($mobile, $type);
        if ($sendResult) {
            HR::updateUniqueNum(IP, microtime(1) * 1000, 'sms-mobile-');
            return $this->jsonExit(200, 'OK');
        } else {
            return $this->jsonExit(206, '发送失败');
        }
    }

    public function accountBind(Request $request)
    {
        $vcode = $request->input('mcode', '');
        $account = $request->input('account', '');
        if (empty($account)) {
            return $this->jsonExit(402, '绑定账号不能为空');
        }
        if (empty($vcode) || strlen($vcode) != 4) {
            return $this->jsonExit(403, '验证码有误');
        }
        $user = UsersModel::getUserInfo($this->uid);
        $mobile = H::decrypt($user->mobile);
        $checked = LogSmsModel::checkCode($mobile, $vcode, 'ask_draw');
        if (!$checked) {
            return $this->jsonExit(403, '验证码错误');
        }
        //限定一个支付宝账号不能绑定多账号
        $new_account = H::encrypt($account);
        $alipay = UsersModel::where([['id', '!=', $this->uid], ['draw_account', $new_account]])->first();
        if ($alipay) {
            return $this->jsonExit(406, '该支付宝已经绑定其他账号，请更换');
        }
        //默认显示认证的名字
        $user->draw_account = $new_account;
        $user->save();
        return $this->jsonExit(200, 'OK');
    }
}
