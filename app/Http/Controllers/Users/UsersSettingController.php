<?php

namespace App\Http\Controllers\Users;

use App\Components\ESearch\ESearch;
use App\Http\Controllers\AuthController;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Libraries\Tools\GraphCompare;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\JobsModel;
use App\Http\Models\Lib\LibBackgroundSetModel;
use App\Http\Models\Lib\LibBioTextModel;
use App\Http\Models\Lib\LibChatModel;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\Lib\LIbNickSetModel;
use App\Http\Models\Logs\CronCloseModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogBrowseModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Logs\LogContactUnlockModel;
use App\Http\Models\Logs\LogGiftReceiveModel;
use App\Http\Models\Logs\LogGiftSendModel;
use App\Http\Models\Logs\LogSignModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Logs\LogSoundLikeModel;
use App\Http\Models\Logs\LogSuperShowOnModel;
use App\Http\Models\Logs\LogSweetModel;
use App\Http\Models\Logs\LogSweetUniqueModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Models\Users\UsersSettingsModel;
use App\Http\Helpers\{H, HR, R, S};
use App\Http\Models\SettingsModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use App\Http\Requests\Profile\{StoreFollowRequest, StoreBlockRequest};
use JWTAuth;
use Illuminate\Support\Facades\Log;
use RongCloud;

class UsersSettingController extends AuthController
{
    /**密码设置**/
    public function passwordSet(Request $request)
    {
        $password = $request->input('password');
        $cpassword = $request->input('cpassword');
        if (empty($password) || empty($cpassword)) {
            return $this->jsonExit(201, '密码不能为空');
        }
        if ($password != $cpassword) {
            return $this->jsonExit(401, '两次密码不一致');
        }
        $salt = H::randstr(6, 'ALL');
        $updateData = [
            'password' => Hash::make($password . $salt),
            'salt' => $salt,
            'password_set' => 1,
        ];
        UsersModel::where('id', $this->uid)->update($updateData);
        return $this->jsonExit(200, 'OK');
    }

    public function simpleInfoGet(Request $request)
    {
        $info = $request->input('info', '');
        $simple = [];
        $user = UsersModel::getUserInfo($this->uid);
        //头像违规
        if ($user->avatar_illegal == 1) {
            $user->avatar = H::errUrl('avatar');
        }
        //规整数据
        $reward_tips = config('self.reward_tips');
        //数据规整处理
        $settings = config('settings.benefit_share');
        //礼物收益比例
        $reward_tips['gift_tips']['desc'] = sprintf($reward_tips['gift_tips']['desc'], ($settings['gift_rate'] * 100) . '%');
        //消息收益比例
        $msg_rate = ($settings['msg_rate'] * 100) . '%';
        $reward_tips['send_msg']['desc'] = sprintf($reward_tips['send_msg']['desc'], $msg_rate);
        //解锁比例
        $contact_rate = ($settings['contact_unlock'] * 100) . '%';
        $reward_tips['contact']['desc'] = sprintf($reward_tips['contact']['desc'], $contact_rate);
        $simple['wallet'] = [
            'diamond' => intval($user->jifen),  //心钻
            'sweet_coin' => $user->sweet_coin, //友币
            'reward_tips' => $reward_tips,
        ];
        if ($info == 'wallet') {
            return $this->jsonExit(200, 'OK', $simple);
        }
        $profile = UsersProfileModel::where('user_id', $this->uid)->first();;
        $simple['user_id'] = $user->id;
        $simple['avatar'] = $user->avatar;
        $simple['avatar_blur'] = str_replace('!sm', '!blura', $user->avatar);
        $simple['nick'] = $user->nick;
        $simple['bio'] = $profile->bio;
        $simple['sex'] = $user->sex;
        $simple['sex_str'] = $user->sex == 1 ? '女' : '男';
        $simple['nick_color'] = H::nickColor($user->profile->vip_level);
        $simple['real_is'] = $profile->real_is;
        $simple['vip_is'] = $profile->vip_is;
        $simple['vip_level'] = $profile->vip_level;
        $simple['identity_is'] = $profile->identity_is;
        $simple['goddess_is'] = $profile->goddess_is;
        $simple['auth_pic'] = $profile->auth_pic;
        //个人中心跳转
        $simple['banner_info'] = [
            'show' => true,
            'jump_info' => [
                [
                    'banner' => 'http://static.hfriend.cn/vips/banner_coin.jpg',
                    'jump' => UsersMsgModel::schemeUrl('', 17, '任务中心', 0, '立即前往'),
                ], [
                    'banner' => 'http://static.hfriend.cn/vips/banner_invite.jpg',
                    'jump' => UsersMsgModel::schemeUrl('', 20, '邀请好友', 0, '立即前往'),
                ]
            ],
        ];
        $simple['live_location'] = $user->live_location;
        //拼接用户的base_str
        $base_str = $user->live_location . ' | ' . ($user->sex == 1 ? '女' : '男') . '•' . H::getAgeByBirthday($user->birthday);
        if ($profile->stature != 0 && !empty($profile->stature)) {
            $base_str .= ' | ' . $profile->stature;
        }
        if ($profile->profession) {
            $base_str .= ' | ' . $profile->profession;
        }
        $simple['base_str'] = $base_str;
        //追加关注数据
        $simple['follow_data'] = UsersFollowModel::followInfoCounter($this->uid);
        //追加被浏览次数信息
        $simple['browse_data'] = LogBrowseModel::browseMeCounter($this->uid);
        return $this->jsonExit(200, 'OK', $simple);
    }

    public function imInfoList(Request $request)
    {
        $user_id = $request->input('user_id');
        $useridArr = is_array($user_id) ? $user_id : [$user_id];
        $info = S::imInfoGet($useridArr);
        $nickSet = LIbNickSetModel::where([['user_id', $this->uid], ['status', 1]])->whereIn('target_user_id', $useridArr)->get();
        $mapUser = [];
        if (!$nickSet->isEmpty()) {
            foreach ($nickSet as $nick) {
                $mapUser[$nick->target_user_id] = $nick->name;
            }
        }
        $simple = [];
        foreach ($info as $item) {
            if (is_null($item)) continue;
            $item = json_decode($item, 1);
            //头像违规
            if (isset($item['avatar_illegal']) && $item['avatar_illegal'] == 1) {
                $item['avatar'] = H::errUrl('avatar');
            }
            $vip_level = $item['vip_level'] ?? 0;
            $simple[] = [
                'user_id' => $item['user_id'],
                'avatar' => $item['avatar'],
                'nick' => $mapUser[$item['user_id']] ?? $item['nick'],
                'location' => $item['location'],
                'nick_color' => H::nickColor($vip_level),
            ];
        }
        return $this->jsonExit(200, 'OK', $simple);
    }

    public function imInfoDetail(Request $request)
    {
        $user_id = $request->input('user_id');
        if (is_null($user_id) || $user_id <= 0) {
            return $this->jsonExit(201, '用户信息错误');
        }
        $info = S::imInfoGet([$user_id]);
        $simple = [];
        if (!is_null($info[0])) {
            $item = json_decode($info[0], 1);
            //查询关注信息
            $followIs = UsersFollowModel::judgeFollow($this->uid, $user_id);
            //查询用户昵称
            $nickSet = LIbNickSetModel::where([['user_id', $this->uid], ['target_user_id', $user_id], ['status', 1]])->first();
            //查询用不聊天背景设置
            $backgroundSet = LibBackgroundSetModel::where([['user_id', $this->uid], ['target_user_id', $user_id], ['status', 1]])->first();
            //用户信息
            $user = UsersModel::getUserInfo($this->uid);
            $profile = UsersProfileModel::getUserInfo($this->uid);
            //头像违规
            if (isset($item['avatar_illegal']) && $item['avatar_illegal'] == 1) {
                $item['avatar'] = H::errUrl('avatar');
            }
            $vip_level = $item['vip_level'] ?? 0;
            //查询自己的的信息
            $selfInfo = S::imInfoGet([$this->uid]);
            $selfItem = !is_null($selfInfo[0]) ? json_decode($selfInfo[0], 1) : [];
            //联系方式展示
            //联系方式展示[如果填写且公开就打* 且添加解锁按钮，未填写或未公开就写保密]
            $userSettingModel = UsersSettingsModel::getUserSettings($user_id);
            $profileModel = UsersProfileModel::where('user_id', $user_id)->first();
            $contact = LogContactUnlockModel::getUserContact($this->uid, $profileModel, $userSettingModel);
            //头像违规
            if (isset($selfItem['avatar_illegal']) && $selfItem['avatar_illegal'] == 1) {
                $selfItem['avatar'] = H::errUrl('avatar');
            }
            //对方语音通话的价格
            $base_price = config('settings.im_call_price');
            if (isset($userSettingModel['call_price']) && $userSettingModel['call_price'] > 0) {
                $base_price = $userSettingModel['call_price'];
            }
            //查询亲密度
            $sweet = LogSweetUniqueModel::sweetGet($this->uid, $user_id);
            $burn = config('settings.burn');
            $simple = [
                'user_id' => $item['user_id'],
                'avatar' => $item['avatar'],
                'nick' => $nickSet ? $nickSet->name : $item['nick'],
                'location' => $item['location'],
                'nick_color' => H::nickColor($vip_level),
                'online' => HR::getOnlineStatus($user_id, $userSettingModel['hide_online']),
                'is_follow' => $followIs,
                'back_img' => $backgroundSet ? $backgroundSet->img_url : '',
                'vip_is' => $item['vip_is'],
                'vip_level' => $item['vip_level'],
                'real_is' => $item['real_is'],
                'goddess_is' => $item['goddess_is'],
                'identity_is' => $item['identity_is'],
                'call_price' => $base_price . '友币/分',
                'contact' => $contact,
                'burn' => [
                    'time_limit' => $burn['time_limit'],  //限定查看时长 默认0不限
                ],
                'sweet' => [
                    'heart_bg' => $sweet['heart_bg'],
                    'level_percent' => $sweet['level_percent'],
                ],
                'self_info' => [
                    'vip_is' => $selfItem['vip_is'] ?? 0,
                    'vip_level' => $selfItem['vip_level'] ?? 0,
                    'real_is' => $selfItem['real_is'] ?? 0,
                    'goddess_is' => $selfItem['goddess_is'] ?? 0,
                    'identity_is' => $selfItem['identity_is'] ?? 0,
                    'contact_completed' => !empty($profile->qq) || !empty($profile->wechat),
                    'sweet_coin' => $user->sweet_coin,
                    'wallet' => $user->wallet,
                ]
            ];
        }
        return $this->jsonExit(200, 'OK', $simple);
    }

    public function contactExchange(Request $request)
    {
        $notice = ['content' => '联系方式推送', 'extra' => []];
        RongCloud::messageSystemPublish(109, [$this->uid], 'RC:TxtMsg', json_encode($notice));
        return $this->jsonExit(200, 'OK');
    }

    public function sweetInfo(Request $request)
    {
        $user_id = $request->input('user_id');
        if (is_null($user_id) || $user_id <= 0) {
            return $this->jsonExit(201, '用户信息错误');
        }
        $sweet = config('self.sweet_list');
        $level_info = LogSweetUniqueModel::sweetGet($this->uid, $user_id);
        //处理解锁状态问题
        $now_level = $level_info['sweet_level'];
        foreach ($sweet as $k => $item) {
            $sweet[$k]['unlock'] = $now_level >= $k;
            $sweet[$k]['img_url'] = $now_level >= $k ? $item['img_unlock'] : $item['img_lock'];
            unset($sweet[$k]['img_unlock']);
            unset($sweet[$k]['img_lock']);
        }
        $base_info = [
            'user_id' => $this->uid,
            'heart_bg' => $level_info['heart_bg'],
            'level_percent' => $level_info['level_percent'],
            'numeric_percent' => floatval(str_replace('%', '', $level_info['level_percent']) / 100),
            'level_name' => $sweet[$level_info['sweet_level']]['name'],
            'level_str' => '当前亲密度' . $level_info['sweet'] . '，距离下级还需' . $level_info['next_unit'],
        ];
        $tips_str = [
            'send_msg' => '双方互发消息，每完成一个来回，亲密度 +2',
            'send_gift' => '任意一方赠送礼物，亲密度 +礼物的友币价格'
        ];
        $res['tips_str'] = $tips_str;
        //亲密度扩展信息
        $res['base_info'] = $base_info;
        $res['sweet_list'] = $sweet;
        return $this->jsonExit(200, 'OK', $res);
    }

    public function superShowGet(Request $request)
    {
        $super = config('settings.super');
        if (!$super['super_show_on']) {
            return $this->jsonExit(201, '超级曝光功能暂未开放');
        }
        $profile = UsersProfileModel::getUserInfo($this->uid);
        $user = UsersModel::getUserInfo($this->uid);
        $super_price = $super['super_show_price'];
        $super_time = intval($super['super_show_duration'] / 3600);
        $left = $user->super_show_left;
        if ($user->super_show == 1 && strtotime($user->super_show_exp_time) >= time()) {
            $hour = number_format((strtotime($user->super_show_exp_time) - time()) / 3600, 1);
            $btn_str = '曝光中，' . $hour . '小时后到期';
        } else {
            $btn_str = $left > 0 ? '立即使用(剩余' . $left . '次)' : '立即开启';
        }
        $data = [
            'enough_to_pay' => $user->sweet_coin >= $super_price,
            'super_str' => '让多十倍的人率先看到你',
            'price_str' => $super_price . '心友币',
            'show_str' => '将在首页列表中凸显自己，增加10倍曝光',
            'time_str' => '每次开启一次曝光时长为' . $super_time . '小时',
            'open_btn' => $btn_str,
            'free_tips' => '每月最高赠送' . $super['give_free'] . '次超级曝光',
            'free_btn_show' => $profile->vip_is == 0,
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function superShowSet(Request $request)
    {
        try {
            $super = config('settings.super');
            $price = $super['super_show_price'];
            $user = UsersModel::where('id', $this->uid)->first();
            //如果超级曝光未结束，不能开始新的曝光
            if ($user->super_show == 1) {
                return $this->jsonExit(202, '超级曝光进行中，无需重复开启');
            }
            if ($user->super_show_left > 0) {
                $user->super_show_left -= 1;
                $user->super_show = 1;
                $user->super_show_exp_time = date('Y-m-d H:i:s', time() + $super['super_show_duration']);
                $user->save();
                $type = 0;
            } else {
                $before = $user->sweet_coin;
                if ($user->sweet_coin < $price) {
                    return $this->jsonExit(201, '心友币不足，请充值');
                }
                DB::beginTransaction();
                $user->sweet_coin -= $price;
                $user->super_show = 1;
                $user->super_show_exp_time = date('Y-m-d H:i:s', time() + $super['super_show_duration']);
                $user->save();
                $desc = '购买超级曝光1次';
                $remark = '用户购买超级曝光花费' . $price . '友币';
                LogBalanceModel::gainLogBalance($this->uid, $before, $price, $user->sweet_coin, 'super_show', $desc, $remark);
                DB::commit();
                $type = 1;
            }
            //开启成功后就追加到队列中
            $key = $this->sex == 2 ? 'super_auto_queue_male' : 'super_auto_queue_female';
            $exit = HR::valueIsExists($key, $this->uid);
            if (!$exit) {
                HR::pushQueue($key, $this->uid);
            }
            //开启就更新es
            $esVipArr[] = [
                'id' => $this->uid,
                'super_show' => 1,
            ];
            //更新es
            (new ESearch('users:users'))->updateSingle($esVipArr);
            //添加开启日志
            LogSuperShowOnModel::create([
                'user_id' => $this->uid,
                'type' => $type,
                'super_show_left' => $user->super_show_left,
                'exp_time' => $user->super_show_exp_time,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    public function rankList(Request $request)
    {
        $type = $request->input('type', 'luck');
        if (!in_array($type, ['luck', 'charm', 'wealth'])) {
            return $this->jsonExit(201, '类型错误');
        }
        $rank = LogSweetUniqueModel::getRankList($type, $this->sex);
        $self_rank = LogSweetUniqueModel::getRankRecommend($this->uid);
        return $this->jsonExit(200, 'OK', ['rank' => $rank, 'self_rank' => $self_rank]);
    }

    public function honey(Request $request)
    {
        $page = $request->input('page', 1);
        $data['items'] = UsersModel::honeyRecommend($page, $this->uid, 10);
        $data['honey'] = [
            'call' => '',
            'match' => '',
            'play' => ''
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    //同城速配
    public function fastMatchGet(Request $request)
    {
        $price = config('settings.match_price');
        $balance = $this->sweet_coin;
        $data = [
            'price' => $price['price'],
            'sweet_coin' => $balance,
            'options' => [
                [
                    'name' => '1人',
                    'val' => 1,
                ], [
                    'name' => '3人',
                    'val' => 3,
                ], [
                    'name' => '5人',
                    'val' => 5,
                ], [
                    'name' => '10人',
                    'val' => 10,
                ]
            ],
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function fastMatchSet(Request $request)
    {
        $cont = $request->input('cont', '');
        $person = $request->input('person', 1);
        $price = config('settings.match_price')['price'];
        if (!in_array($person, [1, 3, 5, 10])) {
            return $this->jsonExit(201, '速配人数错误');
        }
        if (empty($cont)) {
            return $this->jsonExit(202, '要说话的不能为空');
        }
        $total = $price * $person;
        if ($total > $this->sweet_coin) {
            return $this->jsonExit(202, '友币数量不足');
        }
        try {
            //开始执行扣费及相关操作
            DB::beginTransaction();
            $selfUser = UsersModel::getUserInfo($this->uid);
            $before = $selfUser->sweet_coin;
            $selfUser->sweet_coin -= $total;
            $selfUser->save();
            $desc = '同城速配' . $person . '人，消耗' . $total . '友币';
            $remark = '同城速配消耗' . $total . '友币';;
            LogBalanceModel::gainLogBalance($this->uid, $before, $total, $selfUser->sweet_coin, 'local_match', $desc, $remark);
            //开始广播【广播前先检测语句非法性】
            $res = (new AliyunCloud())->GreenScanText($cont);
            if ($res != 'pass') {
                UsersSettingsModel::setViolation($this->uid, 'violation_bio');
                return $this->jsonExit(204, '要说的话存在非法词汇，请检查');
            }
            //随机用户开始广播，先提出黑名单等用户
            $sex = $this->sex == 1 ? 2 : 1;
            $exclude = UsersModel::getExcludeIdArr($this->uid);
            $user = UsersModel::whereNotIn('id', $exclude)->where([['status', 1], ['sex', $sex]])->orderBy(DB::raw('RAND()'))->limit($person)->pluck('id')->toArray();
            if (!empty($user)) {
                //获取批量打招呼内容
                $content = [
                    'content' => $cont,
                    'extra' => ""
                ];
                try {
                    RongCloud::messagePrivatePublish($this->uid, $user, 'RC:TxtMsg', json_encode($content));
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
        return $this->jsonExit(200, 'OK');
    }


    //相亲广场
    public function blindDatePark(Request $request)
    {
        //在es 中查询收费的语音用户
        $size = 20;
        $page = $request->input('page', 1);
        $location = $request->input('location', COORDINATES);
        $sourceArr = explode(',', $location);
        $exclusion = $blackIdArr = [];
        $followIdArr = UsersFollowModel::getFollowIdArr($this->uid);
        if ($this->uid > 0) {
            //黑名单的人不做推荐
            $blackIdArr = UsersFollowModel::_exclude($this->uid);
            $merge = array_merge($blackIdArr, $followIdArr);
            $merge[] = $this->uid;
            $exclusion = array_unique($merge);
        }
        //登陆了
        if ($this->uid > 0 && $this->sex > 0) {
            $sex = $this->sex == 1 ? 2 : 1;
        }
        //未登录 就混合推荐男女
        if ($this->uid == 0) {
            $sex = 0;
        }
        if ($page > 100) {
            return $this->jsonExit(201, '今日查看内容过多，试试别的频道吧');
        }
        //测试es
        try {
            $sortArr = $resData = [];
            $sortArr['live_time_latest'] = 2; //0不排序 1倒序 2正序
            $sort = $this->getSort($sortArr);
            $params = [
                'sex' => $sex,
                'page' => 1,
                'exclusion' => $exclusion,
                'distance' => 5000,
                'size' => $size,
                'location' => $location,
                'sort' => $sort,
                'from' => 'date',
            ];
            $users = EsDataModel::getEsData($params, $sourceArr, $followIdArr);
            $users = !empty($users) ? $users : [];
            //追加用户金币信息
            $resData['user_info'] = [
                'sweet_coin' => $this->sweet_coin,
            ];
            $resData['blind_dates'] = $users;

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK', $resData);
    }

    //语音速配
    public function voiceMatchGet(Request $request)
    {
        $sex = $this->sex == 1 ? 2 : 1;
        $exclude = UsersModel::getExcludeIdArr($this->uid);
        $user = UsersModel::whereNotIn('id', $exclude)->where([['status', 1], ['sex', $sex]])->orderBy(DB::raw('RAND()'))->limit(7)->get();
        $data['self_info']['sweet_coin'] = $this->sweet_coin;
        if (!$user->isEmpty()) {
            foreach ($user as $key => $item) {
                if ($key == 3) {
                    $data['match_user_id'] = $item->id;
                    $data['avatar'] = $item->avatar;
                } else {
                    $data['rand_avatar'][] = $item->avatar;
                }
            }
        }
        return $this->jsonExit(200, 'OK', $data);
    }


    public function imChat(Request $request)
    {
        try {
            $cont = $request->input('cont', '');
            $user_id = $request->input('user_id', 0);
            $target_user_id = $request->input('target_user_id', 0);
            //获取对方聊天收费情况
            $price = config('settings.im_chat_price');
            //用户的价格配置
//            $toSetting = UsersSettingsModel::getUserSettings($target_user_id);
//            if (isset($toSetting['msg_price']) && $toSetting['msg_price'] > 0) {
//                $price = $toSetting['msg_price'];
//            }
            //非客服才验证币够不够
//            if ($target_user_id > 200) {
//                $user = UsersModel::getUserInfo($user_id);
//                if ($user->sweet_coin < 1) {
//                    return $this->jsonExit(201, '友币不足', ['price' => $price]);
//                }
//            } else {
//                return $this->jsonExit(200, 'OK', ['price' => 0]);
//            }
            //在这路判断禁言用户 [如果禁言超过5句话禁言5分钟]
            $banned = HR::getUniqueNum($user_id, 'banned-chat');
            $im_settings = config('settings.banned');
            if ($banned >= $im_settings['banned_limit']) {
                return $this->jsonExit(202, $im_settings['banned_tips'], [
                    'time' => $im_settings['banned_time'],
                    'sweet_coin' => $price
                ]);
            }
            if (!empty($cont)) {
                \App\Jobs\imChat::dispatch($user_id, $target_user_id, $cont, $this->sex)->onQueue('im');
            }
            return $this->jsonExit(200, 'OK', ['price' => $price]);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
        return $this->jsonExit(200, 'OK');
    }

    /**基础信息设置**/
    public function baseInfoSet(Request $request)
    {
        $data = $request->all();
        //性别限定不能修改
        $hasSex = $request->has('sex');
        if ($this->user->sex != 0 && $hasSex) {
            return $this->jsonExit(201, '性别不能更改');
        }
        if ($this->user->sex == 0 && !$hasSex) {
            return $this->jsonExit(203, '性别暂未设定');
        }
        try {
            DB::beginTransaction();
            //完善身高职业扩展信息
            $extInfo = $userInfo = [];
            if (isset($data['qq']) && !empty($data['qq'])) {
                //判断QQ/微信号是否含有中文
                if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $data['qq'], $match)) {
                    return $this->jsonExit(203, 'QQ号码不应含有中文');
                }
                if (strlen($data['qq']) < 6) {
                    return $this->jsonExit(203, 'QQ号码长度错误');
                }
                $profile = UsersProfileModel::getUserInfo($this->uid);
                //记录变动日志
                $crypt_qq = H::encrypt($data['qq']);
                if ($profile->qq != $crypt_qq) {
                    LogChangeModel::gainLog($this->uid, 'contact_qq', $data['qq']);
                    $extInfo['qq'] = $crypt_qq;
                    $extInfo['illegal_qq'] = 0;
                }
            }
            if (isset($data['wechat']) && !empty($data['wechat'])) {
                if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $data['wechat'], $match)) {
                    return $this->jsonExit(203, '微信号码不应含有中文');
                }
                if (strlen($data['wechat']) < 5) {
                    return $this->jsonExit(203, '微信号码长度错误');
                }
                $profile = UsersProfileModel::getUserInfo($this->uid);
                $crypt_wechat = H::encrypt($data['wechat']);
                if ($profile->wechat != $crypt_wechat) {
                    //记录变动日志
                    LogChangeModel::gainLog($this->uid, 'contact_wechat', $data['wechat']);
                    $extInfo['wechat'] = $crypt_wechat;
                    $extInfo['illegal_wechat'] = 0;
                }
            }
            //更新es联系方式填写状态
            if ((isset($data['wechat']) && !empty($data['wechat'])) || (isset($data['qq']) && !empty($data['qq']))) {
                try {
                    $esVipArr[] = [
                        'id' => $this->uid,
                        'contact' => 1,
                    ];
                    (new ESearch('users:users'))->updateSingle($esVipArr); //更新es缓存
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }

            //昵称
            if (isset($data['nick']) && !empty($data['nick'])) {
                $user = UsersModel::find($this->uid);
                if ($user->nick != $data['nick']) {
                    $nickLen = mb_strlen($data['nick']);
                    if ($nickLen < 2 || $nickLen > 10) {
                        return $this->jsonExit(207, '昵称需在2-10个字之间');
                    }
                    //这里添加昵称修改权限的校验
                    $settings = UsersSettingsModel::getUserInfo($this->uid);
                    if ($settings->nick_modify == 0) {
                        return $this->jsonExit(201, '暂不允许修改昵称');
                    }
                    //文本检测
                    $res = (new AliyunCloud())->GreenScanText($data['nick']);
                    if ($res != 'pass') {
                        UsersSettingsModel::setViolation($this->uid, 'violation_bio');
                        return $this->jsonExit(204, '存在非法词汇，请检查');
                    }
                    try {
                        RongCloud::userRefresh($this->uid, $data['nick'], $user->avatar);
                    } catch (\Exception $e) {
                        MessageModel::gainLog($e, __FILE__, __LINE__);
                    }
                    $userInfo['nick'] = $data['nick'];
                    //记录变动日志
                    LogChangeModel::gainLog($this->uid, 'nick', $data['nick']);
                }
            }
            //生日
            if (isset($data['birthday']) && !empty($data['birthday'])) {
                $userInfo['birthday'] = $data['birthday'];
                $age = H::getAgeByBirthday($data['birthday']);
                if ($age < 19) {
                    return $this->jsonExit(205, '年龄不能小于18岁');
                }
                $userInfo['constellation'] = H::getConstellationByBirthday($data['birthday']);
            }
            //头像
            if (isset($data['avatar']) && !empty($data['avatar'])) {
                //针对安卓如果变动了就更新 如果提交内容无变动则不更新
                $user = UsersModel::find($this->uid);
                //只有变动了才记录
                if (str_replace('!sm', '', $data['avatar']) != str_replace('!sm', '', $user->avatar)) {
                    if (strlen($data['avatar']) < 10) {
                        return $this->jsonExit(206, '头像url错误');
                    }
                    //检测开关
                    $avatar_upload = UsersSettingsModel::getSingleUserSettings($this->uid, 'avatar_upload');
                    if ($avatar_upload == 0) {
                        return $this->jsonExit(203, '头像编辑暂未开放');
                    }
                    //头像同步检测--这里是因为异步检测的话只检测了用户的相册，没有头像所以需要同步检测下
                    //检测---START----
                    $res = (new AliyunCloud())->GreenScanImage($data['avatar']);
                    if ($res != 'pass') {
                        UsersSettingsModel::setViolation($this->uid, 'violation_avatar');
                        return $this->jsonExit(204, '您的头像包含违规内容请检查');
                    }
                    //检测---END----
                    $userProfile = UsersProfileModel::where('user_id', $this->uid)->first();
                    //如果用户已经真人认证则进行二次核验
                    if ($userProfile->real_is == 1 && !empty($userProfile->auth_pic)) {
                        $res = (new GraphCompare())->faceCheck($userProfile->auth_pic, $data['avatar']);
                        if (intval($res) < 75) {
                            return $this->jsonExit(204, '您提交的头像和认证头像相差过大，请重新提交');
                        }
                    }

                    $userInfo['avatar'] = $data['avatar'];
                    try {
                        RongCloud::userRefresh($this->uid, $user->nick, $data['avatar']);
                    } catch (\Exception $e) {
                        MessageModel::gainLog($e, __FILE__, __LINE__);
                    }
                    //对于不推荐的用户在用户更新了头像后进行推荐
                    $esVipArr[] = [
                        'id' => $this->uid,
                        'under_line' => 1,
                    ];
                    HR::delUnderLineId($user->id);
                    (new ESearch('users:users'))->updateSingle($esVipArr); //更新es缓存
                    $user->under_line = 1;
                    $user->save();
                    //初始化违规状态
                    $userInfo['avatar_illegal'] = 0;
                    //发放设置头像的奖励
                    UsersRewardModel::userRewardSet($this->uid, 'touxiang', $user);
                    //记录变动日志
                    LogChangeModel::gainLog($this->uid, 'avatar', $data['avatar']);
                }
            }

            //文字签名
            if (isset($data['bio']) && !empty($data['bio'])) {
                //检测开关
                $bio_add = UsersSettingsModel::getSingleUserSettings($this->uid, 'bio_add');
                if ($bio_add == 0) {
                    return $this->jsonExit(203, '自定义签名暂未开放');
                }
                //判断签名是不是来子系统
                $lib = LibBioTextModel::where('sign', md5($data['bio']))->first();
                //文本检测
                if (!$lib) {
                    $res = (new AliyunCloud())->GreenScanText($data['bio']);
                    if ($res != 'pass') {
                        UsersSettingsModel::setViolation($this->uid, 'violation_bio');
                        return $this->jsonExit(204, '存在非法词汇，请检查');
                    }
                    //记录变动日志
                    LogChangeModel::gainLog($this->uid, 'bio', $data['bio']);
                }
                $extInfo['bio'] = $data['bio'];
                //初始化违规状态
                $extInfo['illegal_bio'] = 0;
            }
            //语音签名
            if (isset($data['sound']) && !empty($data['sound'])) {
                $profile = UsersProfileModel::getUserInfo($this->uid);
                //判断对比
                $edit = true;
                if (!empty($profile->sound)) {
                    $soundUrl = $profile->sound['url'];
                    if ($soundUrl == json_decode($data['sound'], 1)['url']) {
                        $edit = false;
                    }
                }
                if (!empty($profile->sound_pending)) {
                    $soundUrl = $profile->sound_pending['url'];
                    if ($soundUrl == json_decode($data['sound'], 1)['url']) {
                        $edit = false;
                    }
                }
                if (empty(json_decode($data['sound'], 1)['url'])) {
                    $edit = false;
                }
                if ($edit) {
                    //检测开关
                    $bio_add_sound = UsersSettingsModel::getSingleUserSettings($this->uid, 'bio_add_sound');
                    if ($bio_add_sound == 0) {
                        return $this->jsonExit(205, '自定义语音签名暂未开放');
                    }
                    $extInfo['sound_pending'] = $data['sound'];
                    $extInfo['sound_status'] = 3;
                    //判断签名是不是没有保存
//                    if (empty($data['sound']['url'])) {
//                        return $this->jsonExit(206, '语音签名录制完成后需要点击保存，然后在提交哦');
//                    }
                    //语音同步检测--这里是因为异步检测的话只检测了用户的相册，没有检测语音，所以需要在提交的时候同步检测
                    //检测---START----
                    $setting = config('settings.scan_type');
                    if ($setting == 'async' && isset($data['sound']['url'])) {
                        $res = (new AliyunCloud())->GreenScanAudio($data['sound']['url']);
                        if (empty($res['text'])) {
                            return $this->jsonExit(205, '您好像没说话哦');
                        }
                        if ($res != 'pass') {
                            UsersSettingsModel::setViolation($this->uid, 'violation_audio');
                            return $this->jsonExit(204, '存在非法词汇，请检查');
                        }
                    }
                    //检测----END----
                    //记录变动日志
                    //file_put_contents('/tmp/xyz.log', print_r([$data], 1) . PHP_EOL, 8);
                    LogChangeModel::gainLog($this->uid, 'sound_bio', $data['sound']);
                    //初始化违规状态
                    $extInfo['illegal_sound'] = 0;
                    //录制录音并提交就下放奖励
                    UsersRewardModel::userRewardSet($this->uid, 'yuyinqianming');
                }
            }
            if (isset($data['hometown']) && !empty($data['hometown'])) {
                $extInfo['hometown'] = $data['hometown'];
            }
            if (isset($data['profession']) && !empty($data['profession'])) {
                $extInfo['profession'] = $data['profession'];
            }
            if (isset($data['stature']) && !empty($data['stature'])) {
                $extInfo['stature'] = $data['stature'];
            }
            if (isset($data['weight']) && !empty($data['weight'])) {
                $extInfo['weight'] = $data['weight'];
            }
            if (isset($data['somatotype']) && !empty($data['somatotype'])) {
                $extInfo['somatotype'] = $data['somatotype'];
            }
            if (isset($data['charm']) && !empty($data['charm'])) {
                $extInfo['charm'] = $data['charm'];
            }
            if (isset($data['salary']) && !empty($data['salary'])) {
                $extInfo['salary'] = $data['salary'];
            }
            if (isset($data['degree']) && !empty($data['degree'])) {
                $extInfo['degree'] = $data['degree'];
            }
            if (isset($data['school']) && !empty($data['school'])) {
                $extInfo['school'] = $data['school'];
            }
            if (isset($data['marriage']) && !empty($data['marriage'])) {
                $extInfo['marriage'] = $data['marriage'];
            }
            if (isset($data['house']) && !empty($data['house'])) {
                $extInfo['house'] = $data['house'];
            }
            if (isset($data['cohabitation']) && !empty($data['cohabitation'])) {
                $extInfo['cohabitation'] = $data['cohabitation'];
            }
            if (isset($data['dating']) && !empty($data['dating'])) {
                $extInfo['dating'] = $data['dating'];
            }
            if (isset($data['purchase_house']) && !empty($data['purchase_house'])) {
                $extInfo['purchase_house'] = $data['purchase_house'];
            }
            if (isset($data['purchase_car']) && !empty($data['purchase_car'])) {
                $extInfo['purchase_car'] = $data['purchase_car'];
            }
            if (isset($data['drink']) && !empty($data['drink'])) {
                $extInfo['drink'] = $data['drink'];
            }
            if (isset($data['smoke']) && !empty($data['smoke'])) {
                $extInfo['smoke'] = $data['smoke'];
            }
            if (isset($data['cook']) && !empty($data['cook'])) {
                $extInfo['cook'] = $data['cook'];
            }
            if (isset($data['relationship']) && !empty($data['relationship'])) {
                $extInfo['relationship'] = $data['relationship'];
            }
            if (isset($data['expect_stature']) && !empty($data['expect_stature'])) {
                $extInfo['expect_stature'] = $data['expect_stature'];
            }
            if (isset($data['expect_age']) && !empty($data['expect_age'])) {
                $extInfo['expect_age'] = $data['expect_age'];
            }
            if (isset($data['expect_degree']) && !empty($data['expect_degree'])) {
                $extInfo['expect_degree'] = $data['expect_degree'];
            }
            if (isset($data['expect_salary']) && !empty($data['expect_salary'])) {
                $extInfo['expect_salary'] = $data['expect_salary'];
            }
            if (isset($data['expect_hometown']) && !empty($data['expect_hometown'])) {
                $extInfo['expect_hometown'] = $data['expect_hometown'];
            }
            if (isset($data['expect_live_addr']) && !empty($data['expect_live_addr'])) {
                $extInfo['expect_live_addr'] = $data['expect_live_addr'];
            }
            if (isset($data['tags']) && !empty($data['tags'])) {
                $extInfo['tags'] = json_encode($data['tags']);
            }
            if (isset($data['hobby_sport']) && !empty($data['hobby_sport'])) {
                $extInfo['hobby_sport'] = json_encode($data['hobby_sport']);
            }
            if (isset($data['hobby_music']) && !empty($data['hobby_music'])) {
                $extInfo['hobby_music'] = json_encode($data['hobby_music']);
            }
            if (isset($data['hobby_food']) && !empty($data['hobby_food'])) {
                $extInfo['hobby_food'] = json_encode($data['hobby_food']);
            }
            if (isset($data['hobby_movie']) && !empty($data['hobby_movie'])) {
                $extInfo['hobby_movie'] = json_encode($data['hobby_movie']);
            }
            if (isset($data['hobby_book']) && !empty($data['hobby_book'])) {
                $extInfo['hobby_book'] = json_encode($data['hobby_book']);
            }
            if (isset($data['hobby_footprint']) && !empty($data['hobby_footprint'])) {
                $extInfo['hobby_footprint'] = json_encode($data['hobby_footprint']);
            }
            if (!empty($extInfo)) UsersProfileModel::where('user_id', $this->uid)->update($extInfo);
            if (!empty($userInfo)) UsersModel::where('id', $this->uid)->update($userInfo);
            DB::commit();
            //同步es信息
            EsDataModel::syncEs('users', 'users', $this->uid, $this->uid);
            //查询资料完善程度下发奖励
            UsersRewardModel::userRewardSetExt($this->uid, 'ziliao');
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    /**
     * 获取基础信息详情页面的数据
     */
    public function baseInfoGet(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        if ($user_id == 0) {
            $user_id = $this->uid;
        }
        $self = $user_id == $this->uid;
        $columns = [
            'mobile', 'qq', 'wechat', 'bio', 'live_addr', 'hometown', 'profession', 'stature', 'weight', 'somatotype', 'charm',
            'salary', 'degree', 'school', 'marriage', 'house', 'cohabitation', 'dating', 'purchase_house', 'purchase_car',
            'drink', 'smoke', 'cook', 'relationship', 'expect_stature', 'expect_age', 'expect_degree', 'expect_salary', 'expect_hometown',
            'expect_live_addr', 'tags', 'hobby_sport', 'hobby_music', 'hobby_food', 'hobby_movie', 'hobby_book', 'hobby_footprint',
            'sound', 'sound_pending', 'sound_status', 'real_is', 'identity_is', 'goddess_is'
        ];
        $user = UsersModel::find($user_id);
        if (!$user) {
            return $this->jsonExit(202, '用户信息不存在');
        }
        if ($user->status != 1) {
            return $this->jsonExit(203, '用户状态异常');
        }
        $profile = UsersProfileModel::select($columns)->where('user_id', $user_id)->first();
        $setting = UsersSettingsModel::where('user_id', $user_id)->first();
        $option = config('self.options');
        $profession = [
            'profession' => config('self.profession'),
            'max' => 1,
            'min' => 1,
        ];
        $show = true;
        $sound = new \stdClass();
        if ($self) {
            //如果是自己分类上传过和上传待审
            if ($profile->sound_status == 2 || $profile->sound_status == 3) {
                $profile->sound = $profile->sound_pending;
            }
            $sound_url = empty($profile->sound) ? '' : $profile->sound['url'];
            $sound_second = empty($profile->sound) ? 0 : $profile->sound['second'];
            $sound = [
                'column' => 'sound',
                'key' => '语音签名',
                'value' => $sound_url,
                'second' => $sound_second,
                'sound_statue' => $profile->sound_status,
                'show' => $self && $setting->bio_add_sound == 1
            ];
        }
        $bio = [
            'column' => 'bio',
            'key' => '个性签名',
            'value' => $profile->bio,
            'show' => $show,
            'edit' => $self,
            'map' => !$self ? [] : ['max' => 35, 'min' => 5,],
        ];
        $base = $contact = [];
        if ($self) {
            //头像违规
            if ($user->avatar_illegal == 1) {
                $user->avatar = H::errUrl('avatar');
            }
            $base[] = [
                'column' => 'avatar',
                'key' => '头像',
                'value' => $user->avatar,
                'show' => $show,
                'edit' => $self,
            ];
        }
        $base[] = [
            'column' => 'nick',
            'key' => '昵称',
            'value' => $user->nick,
            'show' => $show,
            'edit' => $self,
            'map' => !$self ? [] : ['max' => 10, 'min' => 2,],
        ];
        $base[] = [
            'key' => '性别',
            'value' => $user->sex == 1 ? '女' : '男',
            'show' => $show,
            'edit' => false,
        ];
        $base[] = [
            'key' => '实人认证',
            'value' => $profile->real_is == 1 ? '已认证' : '未认证',
            'show' => $show,
            'edit' => $profile->real_is == 0 && $self,
        ];
        $base[] = [
            'key' => '实名认证',
            'value' => $profile->identity_is == 1 ? '已认证' : '未认证',
            'show' => $show,
            'edit' => $profile->identity_is == 0 && $self,
        ];
        if ($self || (!$self && $profile->qq)) {
            $contact[] = [
                'column' => 'qq',
                'key' => 'qq号码',
                'value' => $profile->qq ? H::decrypt($profile->qq) : '',
                'show' => $show,
                'edit' => $self,
            ];
        }
        if ($self || (!$self && $profile->wechat)) {
            $contact[] = [
                'column' => 'wechat',
                'key' => '微信号',
                'value' => $profile->wechat ? H::decrypt($profile->wechat) : '',
                'show' => $show,
                'edit' => $self,
            ];
        }
        $base[] = [
            'column' => 'birthday',
            'key' => '生日',
            'value' => $user->birthday,
            'show' => $show,
            'edit' => $self,
        ];
        if ($self || (!$self && $profile->stature)) {
            $base[] = [
                'column' => 'stature',
                'key' => '身高',
                'value' => $profile->stature ? $profile->stature : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['stature'] : [],
            ];
        }
        if ($self || (!$self && $profile->weight)) {
            $base[] = [
                'column' => 'weight',
                'key' => '体重',
                'value' => $profile->weight ? $profile->weight : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['weight'] : [],
            ];
        }
        if ($self || (!$self && $profile->somatotype)) {
            $base[] = [
                'column' => 'somatotype',
                'key' => '体型',
                'value' => $profile->somatotype ? $profile->somatotype : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['somatotype'] : [],
            ];
        }
        if ($self || (!$self && $profile->charm)) {
            $base[] = [
                'column' => 'charm',
                'key' => '魅力部位',
                'value' => $profile->charm ? $profile->charm : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['charm'] : [],
            ];
        }
        $base[] = [
            'column' => 'live_addr',
            'key' => '常驻城市',
            'value' => $profile->live_addr ? $profile->live_addr : '',
            'show' => $show,
            'edit' => false,
        ];
        if ($self || (!$self && $profile->hometown)) {
            $base[] = [
                'column' => 'hometown',
                'key' => '家乡',
                'value' => $profile->hometown ? $profile->hometown : '',
                'show' => $show,
                'edit' => $self,
            ];
        }
        if ($self || (!$self && $profile->profession)) {
            $base[] = [
                'column' => 'profession',
                'key' => '职业',
                'value' => $profile->profession ? $profile->profession : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $profession : [],
            ];
        }
        if ($self || (!$self && $profile->salary)) {
            $base[] = [
                'column' => 'salary',
                'key' => '收入水平',
                'value' => $profile->salary ? $profile->salary : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['salary'] : [],
            ];
        }
        if ($self || (!$self && $profile->degree)) {
            $base[] = [
                'column' => 'degree',
                'key' => '学历',
                'value' => $profile->degree ? $profile->degree : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['degree'] : [],
            ];
        }
        if ($self || (!$self && $profile->marriage)) {
            $base[] = [
                'column' => 'marriage',
                'key' => '情感状态',
                'value' => $profile->marriage ? $profile->marriage : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['marriage'] : [],
            ];
        }
        if ($self || (!$self && $profile->dating)) {
            $base[] = [
                'column' => 'dating',
                'key' => '接受约会',
                'value' => $profile->dating ? $profile->dating : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['dating'] : [],
            ];
        }
        if ($self || (!$self && $profile->cohabitation)) {
            $base[] = [
                'column' => 'cohabitation',
                'key' => '婚前同居',
                'value' => $profile->cohabitation ? $profile->cohabitation : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['cohabitation'] : [],
            ];
        }
        if ($self || (!$self && $profile->house)) {
            $base[] = [
                'column' => 'house',
                'key' => '住房状况',
                'value' => $profile->house ? $profile->house : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['house'] : [],
            ];
        }
        if ($self || (!$self && $profile->purchase_house)) {
            $base[] = [
                'column' => 'purchase_house',
                'key' => '购房状况',
                'value' => $profile->purchase_house ? $profile->purchase_house : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['purchase_house'] : [],
            ];
        }
        if ($self || (!$self && $profile->purchase_car)) {
            $base[] = [
                'column' => 'purchase_car',
                'key' => '购车状况',
                'value' => $profile->purchase_car ? $profile->purchase_car : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['purchase_car'] : [],
            ];
        }
        if ($self || (!$self && $profile->drink)) {
            $base[] = [
                'column' => 'drink',
                'key' => '是否饮酒',
                'value' => $profile->drink ? $profile->drink : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['drink'] : [],
            ];
        }
        if ($self || (!$self && $profile->smoke)) {
            $base[] = [
                'column' => 'smoke',
                'key' => '是否抽烟',
                'value' => $profile->smoke ? $profile->smoke : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['smoke'] : [],
            ];
        }
        if ($self || (!$self && $profile->cook)) {
            $base[] = [
                'column' => 'cook',
                'key' => '厨艺水平',
                'value' => $profile->cook ? $profile->cook : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['cook'] : [],
            ];
        }
        if ($self || (!$self && $profile->relationship)) {
            $base[] = [
                'column' => 'relationship',
                'key' => '期待关系',
                'value' => $profile->relationship ? $profile->relationship : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['relationship'] : [],
            ];
        }

        $expect = [];
        if ($self || (!$self && $profile->expect_age)) {
            $expect[] = [
                'column' => 'expect_age',
                'key' => '年龄',
                'value' => $profile->expect_age ? $profile->expect_age : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['expect_age'] : [],
            ];
        }
        if ($self || (!$self && $profile->expect_stature)) {
            $expect[] = [
                'column' => 'expect_stature',
                'key' => '身高',
                'value' => $profile->expect_stature ? $profile->expect_stature : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['expect_stature'] : [],
            ];
        }
        if ($self || (!$self && $profile->expect_degree)) {
            $expect[] = [
                'column' => 'expect_degree',
                'key' => '学历',
                'value' => $profile->expect_degree ? $profile->expect_degree : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['expect_degree'] : [],
            ];
        }
        if ($self || (!$self && $profile->expect_salary)) {
            $expect[] = [
                'column' => 'expect_salary',
                'key' => '收入',
                'value' => $profile->expect_salary ? $profile->expect_salary : '',
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['expect_salary'] : [],
            ];
        }
        if ($self || (!$self && $profile->expect_live_addr)) {
            $expect[] = [
                'column' => 'expect_live_addr',
                'key' => '所在地',
                'value' => $profile->expect_live_addr ? $profile->expect_live_addr : '',
                'show' => $show,
                'edit' => $self,
            ];
        }
        if ($self || (!$self && $profile->expect_hometown)) {
            $expect[] = [
                'column' => 'expect_hometown',
                'key' => '家乡',
                'value' => $profile->expect_hometown ? $profile->expect_hometown : '',
                'show' => $show,
                'edit' => $self,
            ];
        }
        //tag 标签专享处理
        $tagArr = [];
        if ($profile->tags) {
            foreach ($profile->tags as $key => $Arr) {
                $tagArr[] = [
                    'value' => $Arr,
                    'color' => '#f8f2d2',
                    'text_color' => '#dbb256',
                ];
            }
        }
        $tag = [
            'column' => 'tags',
            'key' => '我的标签',
            'value' => $tagArr,
            'show' => $show,
            'edit' => $self,
            'map' => $self ? $option['tags'] : [],
        ];
        $hobby = [
            'key' => '兴趣爱好',
        ];
        if ($self || (!$self && !empty($profile->hobby_sport))) {
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
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['hobby_sport'] : [],
            ];
            $hobby['value'][] = $arrData;
        }
        if ($self || (!$self && !empty($profile->hobby_food))) {
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
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['hobby_food'] : [],
            ];
            $hobby['value'][] = $arrData;
        }
        if ($self || (!$self && !empty($profile->hobby_music))) {
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
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['hobby_music'] : [],
            ];
            $hobby['value'][] = $arrData;
        }

        if ($self || (!$self && !empty($profile->hobby_movie))) {
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
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['hobby_movie'] : [],
            ];
            $hobby['value'][] = $arrData;
        }
        if ($self || (!$self && !empty($profile->hobby_book))) {
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
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['hobby_book'] : [],
            ];
            $hobby['value'][] = $arrData;
        }
        if ($self || (!$self && !empty($profile->hobby_footprint))) {
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
                'show' => $show,
                'edit' => $self,
                'map' => $self ? $option['hobby_footprint'] : [],
            ];
            $hobby['value'][] = $arrData;
        }
        $mobile = [];
        if ($self) {
            $mobile = [
                'key' => '手机号',
                'value' => H::hideStr(H::decrypt($user->mobile), 3, 4),
                'show' => $self,
                'edit' => false,
                'map' => [],
            ];
        }
        $data['sound'] = $sound;
        $data['bio'] = $bio;
        $data['base'] = $base;
        $data['contact'] = $contact;
        $data['expect'] = $expect;
        $data['tag'] = $tag;
        $data['hobby'] = $hobby;
        $data['mobile'] = $mobile;

        return $this->jsonExit(200, 'OK', $data);
    }

    /*---- 关注喜欢的人 ----*/
    public function storeFollow(StoreFollowRequest $request)
    {
        $follows = $request->input('follows', []);
        if (empty($follows)) {
            return $this->jsonExit(202, '参数不能为空');
        }
        if (is_array($follows) && count($follows) < 1) {
            return $this->jsonExit(201, '参数错误');
        }
        try {
            UsersFollowModel::batchIntoFollow($this->uid, $follows, $this->user->nick);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    public function soundZan(Request $request)
    {
        $sound = $request->input('sound', []);
        if (empty($sound)) {
            return $this->jsonExit(202, '参数不能为空');
        }
        if (is_array($sound) && count($sound) < 1) {
            return $this->jsonExit(201, '参数错误');
        }
        try {
            LogSoundLikeModel::saveSoundLike($this->uid, $sound);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    //我喜欢的用户列表
    public function meFollowList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        if (!empty($q) && mb_strlen($q) < 2) {
            return $this->jsonExit(202, '关键词不能小于2个字');
        }
        $data = UsersFollowModel::getMeFollowPageData($this->uid, $page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function followMeList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        if (!empty($q) && mb_strlen($q) < 2) {
            return $this->jsonExit(202, '关键词不能小于2个字');
        }
        $data = UsersFollowModel::getFollowMePageData($this->uid, $page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    //好友列表
    public function friendList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        if (!empty($q) && mb_strlen($q) < 2) {
            return $this->jsonExit(202, '关键词不能小于2个字');
        }
        $data = UsersFollowModel::getFriendPageData($this->uid, $page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    //拉黑用户
    public function storeBlock(StoreBlockRequest $request)
    {
        $user_id = $request->input('user_id');
        $status = $request->input('status');
        if ($user_id == $this->uid) {
            return $this->jsonExit(202, '您不能拉黑您自己');
        }
        $find = UsersModel::find($user_id);
        if (!$find) {
            return $this->jsonExit(203, '该用户不存在');
        }
        UsersBlackListModel::updateOrCreate([
            'user_id' => $this->uid,
            'black_id' => $user_id
        ], [
            'user_id' => $this->uid,
            'black_id' => $user_id,
            'status' => $status
        ]);
        $status == 1 ? HR::setUserBlackList($this->uid, $user_id) : HR::delUserBlackList($this->uid, $user_id);
        //增加全局的更新
        JobsModel::InsertNewJob(3, json_encode(['user_id' => $this->uid, 'black_id' => $user_id]));
        $block = UsersBlackListModel::where([['user_id', $this->uid], ['status', 1]])->count();
        $be_block = UsersBlackListModel::where([['black_id', $user_id], ['status', 1]])->count();
        //更新拉黑人数统计
        UsersProfileModel::where('user_id', $user_id)->update(['be_block_num' => $be_block]);
        UsersProfileModel::where('user_id', $this->uid)->update(['block_num' => $block]);
        return $this->jsonExit(200, 'OK');
    }

    //我拉黑的用户列表
    public function usersBlockList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $data = UsersBlackListModel::getBlackListPageData($this->uid, $page, $size);
        return $this->jsonExit(200, 'OK', $data);
    }

    //我的浏览记录
    public function browseMe(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        try {
            $data = LogBrowseModel::browseMe($this->uid, $page, $size);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit('201', '服务错误');
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    //浏览我的记录
    public function meBrowse(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $data = LogBrowseModel::meBrowse($this->uid, $page, $size);
        return $this->jsonExit(200, 'OK', $data);
    }

    /*** 用户搭讪 * 发送给单个人的 ***/
    public function sayHi(Request $request)
    {
        $userStr = $request->input('say_hi');
        $from = $request->input('from', 'profile');
        $from_id = $request->input('from_id', 0);
        $smsSetting = SettingsModel::getSigConf('sms');
        if (!isset($smsSetting['say_hi']) || $smsSetting['say_hi'] != 1) {
            return $this->jsonExit(202, '此功能暂未开启，不能打招呼');
        }
        $say_hi = UsersSettingsModel::getSingleUserSettings($this->uid, 'say_hi');
        if ($say_hi == 0) {
            return $this->jsonExit(203, '搭讪功能暂未开放');
        }
        $arr = json_decode($userStr, 1);
        if (count($arr) < 2) {
            return $this->jsonExit(203, '数据传递错误');
        }
        //查看是否还有友币发送消
        $user_id = $arr['user_id'];
        $get_user = UsersModel::find($user_id);
        if (!$get_user) {
            return $this->jsonExit(201, '用户不存在');
        }
        //如果一定时间内打过招呼了再次打招呼则直接返回
        if (HR::existUniqueNum($this->uid, $user_id, 'say-hi-num') == 1) {
            return $this->jsonExit(200, 'OK');
        }
        $cont = $arr['cont'];
        if (empty($cont)) {
            //获取打招呼的内容
            $cont = LibChatModel::where('type', 2)->orderBy(DB::raw('RAND()'))->first()->advice;
        }
        //step 2 在这里使用队列进行打招呼并送出礼物
        $gift = LibGiftModel::where('type_id', 100)->orderBy(DB::raw('RAND()'))->first();
        \App\Jobs\sayHi::dispatch($this->uid, $user_id, $cont, $gift, $from, $from_id)->onQueue('im');
        //step 3 扣除纸条或友币 并添加记录 友币收费
        $say_hi_price = UsersSettingsModel::getSingleUserSettings($user_id, 'say_hi_price');
        if ($say_hi_price != 0) {
            if ($this->sweet_coin <= 0) {
                return $this->jsonExit(201, '友币不足，请充值');
            }
            //添加友币表动记录
            $desc = "搭讪用户{$user_id}，赠送礼物{$gift->name}1个";
            $remark = "用户{$this->uid}主动搭讪用户{$user_id}，获赠礼物{$gift->name}1个";
            $after = $this->sweet_coin - $gift->price;
            LogBalanceModel::gainLogBalance($this->uid, $this->sweet_coin, $gift->price, $after, 'accost', $desc, $remark);
            UsersModel::where('id', $this->uid)->update(['sweet_coin' => $after]);
        }
        //每日首次搭讪下方奖励
        UsersRewardModel::userDailyRewardSet($this->uid, 'meiridashan');
        return $this->jsonExit(200, 'OK');
    }

    //批量打招呼获取
    public function batchGet(Request $request)
    {
        //反性别推送推荐
        $sex = $this->sex == 1 ? 2 : 1;
        $randRes = UsersModel::getRandUsers($this->uid, $sex);
        return $this->jsonExit(200, 'OK', $randRes);
    }

    //**-------批量打招呼-----向随机的人员发送问候消息--*/
    public function batchSayHi(Request $request)
    {
        $users = $request->input('users');
        if (empty($users) || !is_array($users)) {
            return $this->jsonExit(203, '参数错误');
        }
        $userIdArr = [];
        foreach ($users as $user) {
            $json = json_decode($user, 1);
            if (isset($json['id']) && $json['id'] > 0) {
                $userIdArr[] = $json['id'];
            }
        }
        try {
            $user = UsersModel::whereIn('id', $userIdArr)->where('status', 1)->pluck('id')->toArray();
            if (!empty($user)) {
                $cont = LibChatModel::where('type', 2)->orderBy(DB::raw('RAND()'))->first()->advice;
                //获取批量打招呼内容
                $content = [
                    'content' => $cont,
                    'extra' => ""
                ];
                try {
                    RongCloud::messagePrivatePublish($this->uid, $user, 'RC:TxtMsg', json_encode($content));
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    //隐私设置获取
    public function getPrivate(Request $request)
    {
        $setting = UsersSettingsModel::getUserSettings($this->uid);
        $set = [];
        //浏览推送
        $set[] = [
            'name' => '被浏览信息推送',
            'desc' => '开启后新的访客将不会被通知',
            'column' => 'hide_browse_push',
            'status' => $setting['hide_browse_push'],
            'vip' => 0
        ];
        //关注推送
        $set[] = [
            'name' => '被关注信息推送',
            'desc' => '开启后新的关注信息将不会被通知',
            'column' => 'hide_follow_push',
            'status' => $setting['hide_follow_push'],
            'vip' => 0,
        ];
        //联系方式解锁推送
        $set[] = [
            'name' => '联系方式被解锁推送',
            'desc' => '开启后联系方式被解锁将不会通知',
            'column' => 'hide_unlock_push',
            'status' => $setting['hide_unlock_push'],
            'vip' => 0,
        ];
        //拒绝配对
        $set[] = [
            'name' => '定向推送',
            'desc' => '关闭后您将不会收到推送消息',
            'column' => 'reject_match',
            'status' => $setting['reject_match'],
            'vip' => 0,
        ];
        //销户
        $set[] = [
            'name' => $setting['close_account'] == 1 ? '取消注销' : '普通销户',
            'desc' => $setting['close_account'] == 0 ? '3日内注销您在平台的所有信息' : '取消您已经提交的注销申请',
            'column' => 'close_account',
            'status' => $setting['close_account'],
            'vip' => 0,
        ];
        return $this->jsonExit(200, 'OK', $set);
    }

    //设置隐身模式
    public function setPrivate($col, Request $request)
    {
        $set = $request->input('set', 0);
        $settings = UsersSettingsModel::getUserInfo($this->uid);
        if (!$settings) {
            return $this->jsonExit(201, '用户不存在');
        }
        $colArr = ['hide_browse_push', 'hide_follow_push', 'hide_unlock_push', 'reject_match', 'close_account'];
        if (!in_array($col, $colArr) || $settings->$col == $set) {
            return $this->jsonExit(201, '设置错误');
        }
        //添加注销单独处理
        try {
            if ($col == 'close_account') {
                $profile = UsersProfileModel::getUserInfo($this->uid);
                //添加异步注销定时任务
                $start_time = $profile->vip_is == 1 ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', time() + 86400 * 3);
                if ($set == 1) {
                    CronCloseModel::gainCron($this->uid, 'close_account', $this->uid . ' 销户', $start_time);
                } else {
                    CronCloseModel::where('user_id', $this->uid)->delete();
                }
            }
            $settings->$col = $set;
            $settings->save();
            //更新redis 中的设置信息
            UsersSettingsModel::refreshUserSettings($this->uid, $col, $set);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    //vip 权益设置
    public function getVipSettings(Request $request)
    {
        $setting = UsersSettingsModel::getUserSettings($this->uid);
        $set = [];
        //隐身模式
        $set[] = [
            'name' => '隐身模式',
            'desc' => '全平台信息不可见',
            'column' => 'hide_model',
            'status' => $setting['hide_model'],
            'vip' => 1,
        ];
        //隐身浏览
        $set[] = [
            'name' => '无痕浏览',
            'desc' => '不留下访客记录，查看消息不显示已读',
            'column' => 'hide_browse',
            'status' => $setting['hide_browse'],
            'vip' => 1,
        ];
        //隐藏距离
        $set[] = [
            'name' => '隐藏距离',
            'desc' => '对方看不到他与我的距离',
            'column' => 'hide_distance',
            'status' => $setting['hide_distance'],
            'vip' => 1,
        ];
        //排行榜隐身
        $set[] = [
            'name' => '排行榜匿名',
            'desc' => '除活动榜单外不上榜',
            'column' => 'hide_rank',
            'status' => $setting['hide_rank'],
            'vip' => 1,
        ];
        //隐身守护
        $set[] = [
            'name' => '隐身守护',
            'desc' => '默默守护心中心仪的Ta',
            'column' => 'hide_guard',
            'status' => $setting['hide_guard'],
            'vip' => 1,
        ];
        //在线状态隐藏
        $set[] = [
            'name' => '在线状态隐藏',
            'desc' => '开启后他人无法看到您的在线状态',
            'column' => 'hide_online',
            'status' => $setting['hide_online'],
            'vip' => 1,
        ];
        //公开联系方式
        $set[] = [
            'name' => '隐藏联系方式',
            'desc' => '开启后他人将无法查看您的联系方式',
            'column' => 'hide_contact',
            'status' => $setting['hide_contact'],
            'vip' => 1,
        ];
        return $this->jsonExit(200, 'OK', $set);
    }

    //vip 权益设置
    public function setVipSettings($col, Request $request)
    {
        $set = $request->input('set', 0);
        $settings = UsersSettingsModel::getUserInfo($this->uid);
        $profile = UsersProfileModel::getUserInfo($this->uid);
        if ($profile->vip_is != 1) {
            return $this->jsonExit(204, '该权益仅限vip设置');
        }
        if (!$settings) {
            return $this->jsonExit(201, '用户不存在');
        }
        $colArr = ['hide_model', 'hide_browse', 'hide_distance', 'hide_rank', 'hide_guard', 'hide_contact', 'hide_online', 'close_account'];
        if (!in_array($col, $colArr) || $settings->$col == $set) {
            return $this->jsonExit(203, '设置错误');
        }
        //添加注销单独处理
        if ($col == 'close_account') {
            $start_time = date('Y-m-d H:i:s', time() + 500);//添加异步注销定时任务
            if ($set == 1) {
                CronCloseModel::gainCron($this->uid, 'close_account', $this->uid . ' 销户', $start_time);
            } else {
                CronCloseModel::where('user_id', $this->uid)->delete();
            }
            return $this->jsonExit(200, 'OK');
        }
        try {
            $settings->$col = $set;
            $settings->save();
            //更新es 【隐身模式】
            if ($col == 'hide_model') {
                EsDataModel::updateEsUser([
                    'id' => $this->uid,
                    'hide_model' => $set,
                ]);
                $set == 1 ? HR::setHideModelId($this->uid) : HR::delHideModelId($this->uid);
                //增加全局更新任务
                JobsModel::InsertNewJob(2);
            }
            if ($col == 'hide_distance') {
                EsDataModel::updateEsUser([
                    'id' => $this->uid,
                    'hide_distance' => $set,
                ]);
            }
            if ($col == 'hide_online') {
                EsDataModel::updateEsUser([
                    'id' => $this->uid,
                    'hide_online' => $set,
                ]);
            }
            //更新redis 中的设置信息
            UsersSettingsModel::refreshUserSettings($this->uid, $col, $set);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    //价格设置
    public function getPrice(Request $request)
    {
        $setting = UsersSettingsModel::getUserSettings($this->uid);
        $set = [];
        //隐身模式
        $im_chat_sex = config('settings.im_chat_sex');
        $chat_rule = config('self.chat_rule');
        //隐私相册设置
        $set[] = [
            [
                'name' => '相册设置',
                'column' => 'album_private',
                'status' => $setting['album_private'],
                'vip' => 0,
                'options' => [
                    [
                        'key' => '完全公开',
                        'val' => 0,
                    ], [
                        'key' => '不公开',
                        'val' => 1,
                    ], [
                        'key' => '40友币解锁',
                        'val' => 40,
                    ], [
                        'key' => '60友币解锁',
                        'val' => 60,
                    ], [
                        'key' => '100友币解锁',
                        'val' => 100,
                    ], [
                        'key' => '200友币解锁',
                        'val' => 200,
                    ]
                ],
            ]
        ];
        //可设置部分刚好和收费部分相反
        if (($this->sex == 1 && in_array($im_chat_sex, [2, 3])) || ($this->sex == 2 && in_array($im_chat_sex, [1, 3]))) {  //女 男
            $set[] = [
                [
                    'name' => '消息价格',
                    'column' => 'msg_price',
                    'status' => $setting['msg_price'],
                    'vip' => 0,
                    'options' => [
//                        [
//                            'key' => '免费',
//                            'val' => 0,
//                        ],
                        [
                            'key' => '1友币/条',
                            'val' => 1,
                        ], [
                            'key' => '2友币/条',
                            'val' => 2,
                        ], [
                            'key' => '3友币/条',
                            'val' => 3,
                        ], [
                            'key' => '5友币/条',
                            'val' => 5,
                        ], [
                            'key' => '7友币/条',
                            'val' => 7,
                        ]
                    ],
                ]
            ];
        }
        //        $call = [
        //            [
        //                'name' => '语音接听',
        //                'column' => 'call_answer',
        //                'status' => $setting['call_answer'],
        //                'vip' => 0,
        //            ]
        //        ];
        //        //价格
        //        if (($this->sex == 1 && in_array($im_chat_sex, [2, 3])) || ($this->sex == 2 && in_array($im_chat_sex, [1, 3]))) {  //女 男
        //            //语音接听
        //            $call[] = [
        //                'name' => '语音价格',
        //                'column' => 'call_price',
        //                'status' => $setting['call_price'],
        //                'vip' => 0,
        //                'options' => [
        //                    [
        //                        'key' => '免费',
        //                        'val' => 0,
        //                    ], [
        //                        'key' => '10友币/分钟',
        //                        'val' => 10,
        //                    ], [
        //                        'key' => '20友币/分钟',
        //                        'val' => 20,
        //                    ], [
        //                        'key' => '30友币/分钟',
        //                        'val' => 30,
        //                    ], [
        //                        'key' => '50友币/分钟',
        //                        'val' => 50,
        //                    ]
        //                ],
        //            ];
        //        }
        //$set[] = $call;
        //视频接听
//        $video = [
//            [
//                'name' => '视频接听',
//                'column' => 'video_answer',
//                'status' => $setting['video_answer'],
//                'vip' => 0,
//            ]
//        ];
        //价格
//        if (($this->sex == 1 && in_array($im_chat_sex, [2, 3])) || ($this->sex == 2 && in_array($im_chat_sex, [1, 3]))) {  //女 男
//            $video[] = [
//                'name' => '视频接听',
//                'column' => 'video_price',
//                'status' => $setting['video_price'],
//                'vip' => 0,
//                'options' => [
//                    [
//                        'key' => '免费',
//                        'val' => 0,
//                    ], [
//                        'key' => '50友币/分钟',
//                        'val' => 50,
//                    ], [
//                        'key' => '60友币/分钟',
//                        'val' => 60,
//                    ], [
//                        'key' => '80友币/分钟',
//                        'val' => 80,
//                    ], [
//                        'key' => '100友币/分钟',
//                        'val' => 100,
//                    ]
//                ],
//            ];
//        }
//        $set[] = $video;
        //在线状态隐藏
        $set[] = [
            [
                'name' => '丘比特推荐',
                'column' => 'recommend_on',
                'status' => $setting['recommend_on'],
                'vip' => 0,
            ]
        ];

        return $this->jsonExit(200, 'OK', ['set' => $set, 'rule' => $chat_rule]);
    }

    //收费设置
    public function setPrice($col, Request $request)
    {
        $set = $request->input('set', 0);
        $settings = UsersSettingsModel::getUserInfo($this->uid);
        if (!$settings) {
            return $this->jsonExit(201, '用户不存在');
        }
        $column = ['album_private', 'msg_price', 'call_answer', 'call_price', 'video_answer', 'video_price', 'recommend_on'];
        if (!in_array($col, $column)) {
            return $this->jsonExit(203, '设置错误');
        }
        try {
            $profile = UsersProfileModel::getUserInfo($this->uid);
            if ($profile->real_is == 0 && $col == 'album_private' && !in_array($set, [0, 1])) {
                // 2 实名认证 6 真人认证
                $jump = UsersMsgModel::schemeUrl('', 6, '认证中心', 0, '立即前往');
                return $this->jsonExit(203, '付费相册仅对认证用户开放，请完成认证后重试', ['jump' => $jump]);
            }
//            if ($profile->identity_is == 0 && $col == 'album_private' && !in_array($set, [0, 1])) {
//                // 2 实名认证 6 真人认证
//                $jump = UsersMsgModel::schemeUrl('', 2, '认证中心', 0, '立即前往');
//                return $this->jsonExit(203, '付费相册仅对认证用户开放，请完成认证后重试', ['jump' => $jump]);
//            }
            $settings->$col = $set;
            $settings->save();
            //更新es
            if (in_array($col, ['call_answer', 'call_price'])) {
                EsDataModel::updateEsUser([
                    'id' => $this->uid,
                    'call_answer' => $profile->call_answer,
                    'call_price' => $profile->call_price,
                ]);
            }
            //更新redis 中的设置信息
            UsersSettingsModel::refreshUserSettings($this->uid, $col, $set);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    //系统通知消息获取与删除
    public function sysInfoCount(Request $request)
    {
        $msg = UsersMsgNoticeModel::getCountInfo($this->uid);
        return $this->jsonExit(200, 'OK', $msg);
    }


    //推送信息删除
    public function sysInfoDelete(Request $request)
    {
        $type = $request->input('type');
        if (!in_array($type, ['zan', 'comment', 'love_me', 'me_love', 'browse_me', 'site_notice', 'sound_zan'])) {
            return $this->jsonExit(201, '传值错误');
        }
        $userLog = UsersMsgNoticeModel::where('user_id', $this->uid)->first();
        if ($userLog) {
            $userLog->$type = 0;
            $userLog->save();
        }
        return $this->jsonExit(200, 'OK');
    }

    //昵称备注设置
    public function nickSet(Request $request)
    {
        $nick = $request->input('name', '');
        $user_id = $request->input('user_id', 0);
        if ($user_id <= 0) {
            return $this->jsonExit(202, '用户id不能为空');
        }
        if (mb_strlen($nick) < 2 || mb_strlen($nick) > 6) {
            return $this->jsonExit(201, '昵称备注需要在2-6个字之前');
        }
        LIbNickSetModel::updateOrCreate([
            'user_id' => $this->uid,
            'target_user_id' => $user_id,
        ], [
            'name' => $nick,
            'user_id' => $this->uid,
            'target_user_id' => $user_id,
            'status' => 1,
        ]);
        //更新昵称
        return $this->jsonExit(200, 'OK');
    }

    //背景设置
    public function backgroundSet(Request $request)
    {
        $img_url = $request->input('img_url', '');
        $user_id = $request->input('user_id', 0);
        if ($user_id <= 0) {
            return $this->jsonExit(202, '用户id不能为空');
        }
        //在这里进行图片违规检测【同步检测在上传环节已经检测过了】
        //检测---START----
        $setting = config('settings.scan_type');
        if ($setting == 'async') {
            $res = (new AliyunCloud())->GreenScanImage($img_url);
            if ($res != 'pass') {
                UsersSettingsModel::setViolation($this->uid, 'violation_avatar');
                return $this->jsonExit(204, '图片包含违规内容请检查');
            }
        }
        //检测---END----
        LibBackgroundSetModel::updateOrCreate([
            'user_id' => $this->uid,
            'target_user_id' => $user_id,
        ], [
            'img_url' => $img_url,
            'user_id' => $this->uid,
            'target_user_id' => $user_id,
            'status' => 1,
        ]);
        //更新昵称
        return $this->jsonExit(200, 'OK');
    }

    /*----------------签到---------------*/
    public function signSet(Request $request)
    {
        $sign = LogSignModel::where('user_id', $this->uid)->first();
        if (!$sign) {
            //首次签到
            LogSignModel::create([
                'user_id' => $this->uid,
                'serial' => 1,
                'last_date' => date('Y-m-d'),
            ]);
            $day = 1;
        } else {
            //连续签到不连续
            $spacer = strtotime(date('Y-m-d') . ' 00:00:00') - strtotime($sign->last_date . ' 00:00:00');
            if ($sign->last_date == date('Y-m-d')) {
                return $this->jsonExit(201, '今日已签到');
            }
            if ($spacer > 86400) {
                $sign->serial = 1;
                $day = 1;
            } else {
                //签到连续
                $sign->serial += 1;
                $day = $sign->serial;
            }
            if ($day > 7) {
                return $this->jsonExit(202, '签到任务已完成');
            }
            $sign->last_date = date('Y-m-d');
            $sign->save();
        }
        //下放奖励
        $reward = UsersRewardModel::signReward($this->uid, $day);
        return $this->jsonExit(200, 'OK', $reward);
    }

    public function signGet(Request $request)
    {
        $signMap = config('subscribe.sign');
        $sign = LogSignModel::where('user_id', $this->uid)->first();
        $title = '签到送友币';
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
        $res['sign_remind'] = UsersSettingsModel::getSingleUserSettings($this->uid, 'sign_remind');
        $res['sign'] = $signMap;

        return $this->jsonExit(200, 'OK', $res);
    }

    public function signRemindSet(Request $request)
    {
        $column = $request->input('column', 'sign_remind');
        $status = $request->input('status', 0);
        $settings = UsersSettingsModel::where('user_id', $this->uid)->first();
        if (!$settings) {
            return $this->jsonExit(201, '服务错误');
        }
        if ($settings->$column == $status) {
            return $this->jsonExit(202, '无需重复设置');
        }
        $settings->$column = $status;
        $settings->save();
        UsersSettingsModel::refreshUserSettings($this->uid, $column, $status);
        return $this->jsonExit(200, 'OK');
    }

}
