<?php

namespace App\Jobs;

use App\Components\ESearch\ESearch;
use App\Http\Helpers\HR;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Models\EsDataModel;
use App\Http\Models\Lib\LibBioTextModel;
use App\Http\Models\Lib\LibChatModel;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Logs\LogSayHiModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use RongCloud;

class authJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    public function __construct(UsersModel $user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        try {
            $user = $this->user;
            //下发VIP奖励
            if (config('settings.invite_on')) {
                $step = config('settings.invite_reward_vip');
                if (!empty($user->invited)) {
                    $father = UsersModel::where('uinvite_code', $user->invited)->first();
                    if ($father && $step > 0) {
                        $father->increment('vip_reward', $step);
                        //增加用户vip
                        $father_profile = UsersProfileModel::getUserInfo($father->id);
                        $father_profile->vip_is = 1;
                        $father_profile->vip_handle = 3;
                        $father_profile->vip_level = 2;
                        $father_profile->vip_level_last = 2;
                        $base_time = time();
                        if ((!empty($father_profile->vip_exp_time) && $father_profile->vip_exp_time >= date('Y-m-d H:i:s'))) {
                            $base_time = strtotime($father_profile->vip_exp_time);
                        }
                        $last_time = $base_time + 86400 * $step;
                        $father_profile->vip_exp_time = date('Y-m-d H:i:s', $last_time);
                        $father_profile->save();
                        //更新es
                        $esVipArr = [
                            [
                                'id' => $father->id,
                                'vip_is' => 1,
                                'vip_level' => 2,
                            ]
                        ];
                        (new ESearch('users:users'))->updateSingle($esVipArr);
                        //添加奖励记录
                        $before = $father->sweet_coin;
                        $desc = "邀请好友奖励VIP {$step} 天";
                        $remark = "邀请好友 [{$user->nick}-{$user->id}] 邀请好友奖励VIP {$step} 天";
                        $type_tag = 'invite_friend';
                        LogBalanceModel::gainLogBalance($father->id, $before, 0, $before, $type_tag, $desc, $remark);
                        //如果邀请码是8888 则本人立马赠送5天VIP
                        if (in_array($user->invited, [8888, 9999, 6666, 0000, 1111, 2222, 3333, 4444, 5555, 7777])) {
                            $step_vip = 2;
                            $profile = UsersProfileModel::getUserInfo($user->id);
                            $profile->vip_is = 1;
                            $profile->vip_handle = 3;
                            $profile->vip_level = 2;
                            $profile->vip_experience = 1;
                            $profile->vip_level_last = 2;
                            $last_time = time() + 86400 * ($step_vip - 1);
                            $profile->vip_exp_time = date('Y-m-d 23:50:00', $last_time);
                            $profile->save();
                            //更新es
                            $esVipArr = [
                                [
                                    'id' => $user->id,
                                    'vip_is' => 1,
                                    'vip_level' => 2,
                                ]
                            ];
                            (new ESearch('users:users'))->updateSingle($esVipArr);
                            //添加奖励记录
                            $before = $user->sweet_coin;
                            $desc = "通过专属邀请注册奖励VIP {$step_vip} 天";
                            $remark = "通过专属邀请码注册，奖励VIP {$step_vip} 天";
                            $type_tag = 'invite_friend';
                            LogBalanceModel::gainLogBalance($user->id, $before, 0, $before, $type_tag, $desc, $remark);
                        }
                    }
                }
            }
            //记录变动日志
            $ava = LibNickModel::where('sign', md5($user->nick))->first();
            if (!$ava) {
                LogChangeModel::gainLog($user->id, 'nick', $user->nick);
            }
            if (stripos($user->avatar, '/ava/') === false) {
                LogChangeModel::gainLog($user->id, 'avatar', $user->avatar);
                //直接检测头像违规问题
                $avatar = str_replace('!sm', '', $user->avatar);
                $res = (new AliyunCloud())->GreenScanImage($avatar);
                if ($res != 'pass') {
                    $user->avatar_illegal = 1;
                    UsersSettingsModel::setViolation($user->id, 'violation_avatar');
                }
            }
            //最后记录下redis的活跃时间
            HR::updateActiveTime($user->id);
            //更新表中的信息
            $profile = UsersProfileModel::getUserInfo($user->id);
            //$profile->bio = LibBioTextModel::getRandTextBio(1);  //自动填写签名
            //更新注册城市等信息
            if (!empty($profile->register_coordinates)) {
                HR::updateActiveCoordinate($user->id, $profile->register_coordinates);
                $profile->register_location = $user->last_location = $user->live_location = (new BaiduCloud())->getCityByPoint($profile->register_coordinates);
            }

            //处理发送系统通知的逻辑
            $title = '欢迎您注册心友平台';
            $cont = '恭喜您注册成功，我们是一个绿色文明的交友社区，请文明使用，文明发言，如遇不法内容欢迎举报';
            $sysMsgData = [
                'user_id' => $user->id,
                'event_id' => 0,
                'event' => 'register',
                'title' => $title,
                'cont' => $cont,
            ];
            UsersMsgSysModel::create($sysMsgData);


            //私信打招呼
            $check = SettingsModel::getSigConf('check');
            $obToSay = 0;
            if (isset($check['register_say_hi']) && $check['register_say_hi'] == 1) {

                if ($user->sex == 1) {
                    $obToSay = $check['be_to_say_female'] ?? 0;
                } else {
                    $obToSay = $check['be_to_say_male'] ?? 0;
                }
                $user->say_hi_left = $obToSay;
            }
            $user->save();
            $profile->save();

            //第二步
            if (isset($check['register_say_hi']) && $check['register_say_hi'] == 1) {
                $sex = $user->sex == 1 ? 2 : 1;
                //对数目进行下限制
                $sayHis = UsersModel::where([
                    ['status', 1],
                    ['id', '<', $user->id],
                    ['sex', $sex],
                    ['say_hi_left', '>', 0],
                    ['created_at', '>', date('Y-m-d H:i:s', time() - 21600)]
                ])->orderBy('created_at', 'desc')->limit($obToSay)->get();
                //过滤目标对象
                if (!$sayHis->isEmpty()) {
                    $adv = $log = [];
                    $times = strtotime($user->created_at);
                    foreach ($sayHis as $k => $say) {
                        $adv[] = $say->id;
                        $times += rand(70, 200);
                        $advice = LibChatModel::where('type', 2)->orderBy(DB::raw('RAND()'))->first()->advice;  //计算打招呼时间
                        LogSayHiModel::create([
                            'from_uid' => $say->id,
                            'from_sex' => $say->sex,
                            'from_reg' => $say->created_at,
                            'to_uid' => $user->id,
                            'to_sex' => $user->sex,
                            'to_reg' => $user->created_at,
                            'to_say' => $advice,
                            'send_at' => date('Y-m-d H:i:s', $times),
                        ]);
                        //$log[] = $say->id . '==' . $say->sex;
                    }
                    //file_put_contents('/tmp/sa_hi.log', "对象列表:" . join(',', $log) . PHP_EOL, 8);
                    UsersModel::whereIn('id', $adv)->decrement('say_hi_left');
                }
            }

            //推送融云系统消息
            $sysMsg = ['content' => $title, 'title' => $cont, 'extra' => ""];
            RongCloud::messageSystemPublish(101, [$user->id], 'RC:TxtMsg', json_encode($sysMsg));
            //未读消息更新
            UsersMsgNoticeModel::gainNoticeLog($user->id, 'site_notice', 1);
            //注册登陆同步es
            EsDataModel::syncEs('users', 'users', $user->id, $user->id);
            //推送打招呼通知
            if (config('settings.register_say_hi')) {
                $sayNum = config('settings.register_say_num');
                $sex = $user->sex == 1 ? 2 : 1;
                $idArr = [];
                //优先注册人附近的最新注册的 [由于是最新注册所以排除是空]
                $sortArr['created_at'] = 0; //0不排序 1倒序 2正序
                $sort = UsersModel::getSort($sortArr);
                $params = [
                    'real_is' => 0,
                    'sex' => $sex,
                    'page' => 1,
                    'exclusion' => [],
                    'distance' => 500,  //500km 内
                    'size' => 10,
                    'sort' => $sort,
                    'location' => $user->last_coordinates,
                ];
                $sourceArr = explode(',', $user->last_coordinates);
                $users = EsDataModel::getEsData($params, $sourceArr, []);
                //如果有超级曝光则优先推荐超级曝光
                if (isset($users['count']) && $users['count'] > 0) {
                    $idArr = array_column($users['items'], 'user_id');
                }
                if (count($idArr) > 3) {
                    $sayHis = UsersModel::where([['status', 1], ['id', '!=', $user->id], ['sex', $sex]])->whereIn('id', $idArr)->orderBy('created_at', 'desc')->get();
                } else {
                    $sayHis = UsersModel::where([['status', 1], ['id', '!=', $user->id], ['sex', $sex]])->orderBy('created_at', 'desc')->limit($sayNum)->get();
                }
                if (!$sayHis->isEmpty()) {
                    foreach ($sayHis as $say) {
                        $idArr[] = $say->id;
                        //记录给我打过招呼的人，避免重复
                        HR::updateUniqueNum($user->id, $say->id, 'say-hi-num');
                    }
                    $cont = LibChatModel::where('type', 2)->orderBy(DB::raw('RAND()'))->first()->advice;
                    $content = json_encode(["content" => $cont]);
                    //注册完成别人开始给我打招呼
                    if (count($idArr) > 0) {
                        RongCloud::messagePrivatePublish($user->id, $idArr, 'RC:TxtMsg', $content);
                        //file_put_contents('/tmp/say_hi.log', print_r([$user->id, $idArr, $content], 1) . PHP_EOL, FILE_APPEND);
                    }
                }
            }


        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
