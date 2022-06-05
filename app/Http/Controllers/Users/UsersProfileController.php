<?php

namespace App\Http\Controllers\Users;

use App\Http\Libraries\Sms\DingTalk;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Models\CommonModel;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogBrowseModel;
use App\Http\Models\Logs\LogAlbumViewModel;
use App\Http\Models\Logs\LogContactUnlockModel;
use App\Http\Models\Logs\LogGiftReceiveModel;
use App\Http\Models\Logs\LogSoundLikeModel;
use App\Http\Models\Logs\LogSweetModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersSettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Helpers\{H, HR};
use App\Http\Controllers\AuthController;
use App\Http\Models\SettingsModel;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UsersProfileController extends AuthController
{
    public function profileInfo(Request $request)
    {
        $location = $request->input('location', '');
        $user_id = $request->input('user_id', 0);
        /***********公共模型部分**********/
        if ($user_id == 0) {
            $user_id = $this->uid;
        }
        //黑名单判断
        $blackIdArr = UsersBlackListModel::getBlackIdArr($this->uid);
        if ($this->uid != $user_id && in_array($user_id, $blackIdArr)) {
            return $this->jsonExit(208, '黑名单用户不能被查看');
        }
        //基础信息
        $res = $album = $baseData = $discoverArr = $commentArr = $zanArr = [];
        $profileModel = UsersProfileModel::where('user_id', $user_id)->first();
        $userModel = UsersModel::find($user_id);
        $userSettingModel = UsersSettingsModel::getUserSettings($user_id);
        if (!$profileModel || !$userModel || !$userSettingModel) {
            return $this->jsonExit(207, '用户不存在');
        }
        //隐身用户不能被查看
        if ($userSettingModel['hide_model'] == 1 && $this->uid != $user_id) {
            return $this->jsonExit(208, '该用户已设置隐身，不能被查看');
        }
        try {
            //入库浏览记录 [自己浏览自己不入库]
            if ($this->uid != $user_id) {
                /*--增肌浏览次数并记录浏览记录*异步操作开始**/
                $profileModel->increment('browse_num');
                \App\Jobs\viewNotice::dispatch($user_id, $this->uid)->onQueue('register');
                /*--增肌浏览次数并记录浏览记录*异步操作结束**/
            }
            //动态距离处理
            $sourceArr = empty($location) ? explode(',', COORDINATES) : explode(',', $location);
            $live_coordinates = !empty($userModel->live_coordinates) ? explode(',', $userModel->live_coordinates) : ['0.00', '0.00'];
            $live_cooArr[0] = $live_coordinates[1] ?? '0.00';
            $live_cooArr[1] = $live_coordinates[0] ?? '0.00';
            /**音频是否点赞过**/
            $soundLikeIdArr = LogSoundLikeModel::getSoundLikeIdArr($this->uid);
            $browse_num_str = H::exchangeNumStr($profileModel->browse_num);
            $age = $userModel->birthday ? H::getAgeByBirthday($userModel->birthday) : 18;
            $sex_str = $userModel->sex == 1 ? '女' : '男';
            $base_str = $userModel->live_location . ' | ' . $sex_str . '•' . $age;
            if (!empty($profileModel->stature)) $base_str .= ' | ' . $profileModel->stature;
            if (!empty($profileModel->profession)) $base_str .= ' | ' . $profileModel->profession;
            //昵称颜色
            $base = [
                [
                    'key' => '账号',
                    'value' => $userModel->platform_id,
                ], [
                    'key' => '性别',
                    'value' => $sex_str,
                ], [
                    'key' => '实人认证',
                    'value' => $profileModel->real_is == 1 ? '已认证' : '未认证',
                    'show' => $profileModel->real_is == 0,
                    'show_str' => '去认证',
                ], [
                    'key' => '实名认证',
                    'value' => $profileModel->identity_is == 1 ? '已认证' : '未认证',
                    'show' => $profileModel->identity_is == 0,
                    'show_str' => '去实名',
                ], [
                    'key' => '年龄',
                    'value' => $age . '（岁）',
                ]
            ];
            if (!empty($profileModel->stature)) {
                if (stripos($profileModel->stature, 'cm') === false) {
                    $profileModel->stature .= 'cm';
                }
                $base[] = [
                    'key' => '身高',
                    'value' => $profileModel->stature ?: '',
                ];
            }
            if (!empty($profileModel->weight)) {
                if (stripos($profileModel->weight, 'cm') !== false) {
                    $profileModel->weight = str_replace('cm', 'kg', $profileModel->weight);
                }
                $base[] = [
                    'key' => '体重',
                    'value' => $profileModel->weight ?: '',
                ];
            }
            $base[] = [
                'key' => '星座',
                'value' => $userModel->constellation,
            ];
            $base[] = [
                'key' => '常驻地',
                'value' => $profileModel->live_addr,
            ];
            $base[] = [
                'key' => '职业',
                'value' => !empty($profileModel->profession) ? $profileModel->profession : '-',
            ];
            if (!empty($profileModel->hometown)) {
                $base[] = [
                    'key' => '家乡',
                    'value' => $profileModel->hometown ?: '-',
                ];
            }
            if (!empty($profileModel->degree)) {
                $base[] = [
                    'key' => '学历',
                    'value' => $profileModel->degree ?: '-',
                ];
            }
            if (!empty($profileModel->marriage)) {
                $base[] = [
                    'key' => '感情现状',
                    'value' => $profileModel->marriage ?: '',
                ];
            }
            $self_profile = UsersProfileModel::getUserInfo($this->uid);
            $album_private = $userSettingModel['album_private'] ?? 0;
            $albumUnlock = HR::getUniqueMembers($this->uid, 'album-private-unlock');
            if (in_array($user_id, $albumUnlock) || $self_profile->vip_is == 1) {
                $album_private = 0;
            }
            $burn = config('settings.burn');
            $baseData = [
                'user_id' => $user_id,
                'self_vip_is' => $self_profile->vip_is == 1 ? 1 : 0,
                'sound' => $profileModel->sound ?: [],
                'sound_like_num' => H::getNumStr($profileModel->sound_like),
                'sound_liked' => in_array($user_id, $soundLikeIdArr) ? 1 : 0,

                'browse_num' => $profileModel->browse_num,
                'browse_num_str' => $browse_num_str,
                'browse_num_desc' => '累计访客 ' . $browse_num_str,

                'avatar' => $userModel->avatar,    //判断头像背景
                'nick' => $userModel->nick,
                'nick_color' => H::nickColor($profileModel->vip_level),
                'sex' => $userModel->sex,
                'sex_str' => $sex_str,
                'age' => $age,
                'base_str' => $base_str,

                'vip_is' => $profileModel->vip_is,
                'vip_level' => $profileModel->vip_level,

                'real_is' => $profileModel->real_is,
                'identity_is' => $profileModel->identity_is,
                'goddess_is' => $profileModel->goddess_is,
                //基础信息部分
                'hide_model' => $userSettingModel['hide_model'],
                'online' => $userModel->online,
                'album_private' => $album_private,
                'view_limit_vip' => $burn['time_limit_vip'],
                'distance_str' => (count($sourceArr) > 1 && count($live_coordinates) > 1 && $userSettingModel['hide_distance'] == 0) ? H::getDistance($sourceArr[1], $sourceArr[0], $live_coordinates[1], $live_coordinates[0]) : '来自火星',
                'active_str' => H::exchangeDate($userModel->live_time_latest),
                'bio' => $profileModel->bio ?: '',
            ];
            //期望对象展示
            $expect = [];
            $expect[] = [
                'key' => '期望身高',
                'value' => !empty($profileModel->expect_stature) ? $profileModel->expect_stature : '-',
            ];
            $expect[] = [
                'key' => '期望年龄',
                'value' => !empty($profileModel->expect_age) ? $profileModel->expect_age : '-',
            ];
            $expect[] = [
                'key' => '期望学历',
                'value' => !empty($profileModel->expect_degree) ? $profileModel->expect_degree : '-',
            ];
            $expect[] = [
                'key' => '期望薪资',
                'value' => !empty($profileModel->expect_salary) ? $profileModel->expect_salary : '-',
            ];
            if (!empty($profileModel->expect_hometown)) {
                $expect[] = [
                    'key' => '期望家乡',
                    'value' => $profileModel->expect_hometown ?: '-',
                ];
            }
            if (!empty($profileModel->expect_live_addr)) {
                $expect[] = [
                    'key' => '常住地',
                    'value' => $profileModel->expect_live_addr ?: '-',
                ];
            }
            //标签
            $tags = [
                'key' => '我的标签',
                'value' => [],
            ];
            if (!empty($profileModel->tags)) {
                foreach ($profileModel->tags as $key => $Arr) {
                    $tags['value'][] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
            }
            //兴趣爱好
            $hobby = [
                'key' => '兴趣爱好',
                'value' => [],
            ];
            if (!empty($profileModel->hobby_sport)) {
                $arr = [];
                foreach ($profileModel->hobby_sport as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
                $arrData = [
                    'key' => '喜欢运动',
                    'value' => $arr
                ];
                $hobby['value'][] = $arrData;
            }
            if (!empty($profileModel->hobby_music)) {
                $arr = [];
                foreach ($profileModel->hobby_music as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
                $arrData = [
                    'key' => '喜欢音乐',
                    'value' => $arr
                ];
                $hobby['value'][] = $arrData;
            }
            if (!empty($profileModel->hobby_food)) {
                $arr = [];
                foreach ($profileModel->hobby_food as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
                $arrData = [
                    'key' => '喜欢美食',
                    'value' => $arr
                ];
                $hobby['value'][] = $arrData;
            }
            if (!empty($profileModel->hobby_movie)) {
                $arr = [];
                foreach ($profileModel->hobby_movie as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
                $arrData = [
                    'key' => '喜欢电影',
                    'value' => $arr
                ];
                $hobby['value'][] = $arrData;
            }
            if (!empty($profileModel->hobby_book)) {
                $arr = [];
                foreach ($profileModel->hobby_book as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
                $arrData = [
                    'key' => '喜欢书籍',
                    'value' => $arr
                ];
                $hobby['value'][] = $arrData;
            }
            if (!empty($profileModel->hobby_footprint)) {
                $arr = [];
                foreach ($profileModel->hobby_footprint as $key => $Arr) {
                    $arr[] = [
                        'value' => $Arr,
                        'color' => '#f7f1d1',
                        'text_color' => '#dbb256',
                    ];
                }
                $arrData = [
                    'key' => '喜欢城市',
                    'value' => $arr
                ];
                $hobby['value'][] = $arrData;
            }
            //联系方式展示[如果填写且公开就打* 且添加解锁按钮，未填写或未公开就写保密]
            $contact = LogContactUnlockModel::getUserContact($this->uid, $profileModel, $userSettingModel);
            $self = $user_id == $this->uid;
            //是否点击过喜欢
            $isFollow = UsersFollowModel::where([['user_id', $this->uid], ['follow_id', $user_id], ['status', 1]])->first();
            $res['is_like'] = (bool)$isFollow;
            $res['is_self'] = $self;
            $res['base_info'] = $baseData;
            $res['basic'] = $base;
            $res['expect'] = $expect;
            $res['tags'] = $tags;
            $res['hobby'] = $hobby;
            $res['contact'] = $contact;
            $res['chat'] = LogContactUnlockModel::where([['user_id', $this->uid], ['user_id_viewed', $user_id], ['channel', 1]])->first() ? 1 : 0;
            //魅力值
            $res['wealth'] = [
                'charm' => [
                    'key' => '魅力值',
                    'value' => $profileModel->charm_num,
                ],
                'wealth' => [
                    'key' => '财富值',
                    'value' => $profileModel->send_num,
                ],
            ];
            //相册 & 验证相册图片是否查看过
            $res['album'] = $res['album_video'] = $albumDefault = [];
            $albumDefault[] = [
                'id' => 0,
                'price' => 0,
                'img_url' => $userModel->avatar_illegal == 1 ? H::errUrl('avatar') : $userModel->avatar,
                'is_free' => 1,
                'is_real' => 0,
                'is_video' => 0,
                'is_illegal' => 0,
                'is_private' => 0,
            ];
            $profileModel->album = $profileModel->album ?: $albumDefault;
            if (!empty($profileModel->album) || !empty($profileModel->album_video)) {
                $payedArr = [];
                $viewsArr = HR::getUniqueMembers($this->uid, 'album-view-ids');
                //附加逻辑用户的查看时长
                $time_limit = $burn['time_limit'];  //限定查看时长 默认0不限
                if ($burn['burn'] && !$self) {
                    $selfProfile = UsersProfileModel::where('user_id', $this->uid)->first();
                    if ($selfProfile->vip_is == 1) {
                        $time_limit = $burn['time_limit_vip'];
                    }
                }
                //视频及相册公开性渲染  [图片违规渲染]
                if (!empty($profileModel->album)) {
                    $album = $profileModel->album;
                    foreach ($album as $k => $items) {
                        if ($items['is_illegal'] == 1) $album[$k]['img_url'] = H::errUrl('album');
                    }
                    $profileModel->album = $album;
                }
                if (!empty($profileModel->album_video)) {
                    $album = $profileModel->album_video;
                    foreach ($album as $ks => $items) {
                        if ($items['is_illegal'] == 1) $album[$ks]['img_url'] = H::errUrl('album');
                    }
                    $profileModel->album_video = $album;
                }
                $resource = [
                    'album' => $profileModel->album,
                    'album_video' => $profileModel->album_video ?: [],
                ];
                foreach ($resource as $item => $arr) {
                    foreach ($arr as $k => $val) {
                        //查看状态 自己标记为查看过 [这部分逻辑为 当is_private 代表阅后即焚的相册，is_free 代表非付费相册，如果是付费相册且用户购买了则可以走阅后即焚逻辑]
                        if (!in_array($val['id'], $viewsArr) && !$self && $val['id'] > 0) {
                            $arr[$k]['viewed'] = 0;
                        } else {
                            $arr[$k]['viewed'] = 1;
                        }
                        //模糊状态 对自己公开 对别人依然隐藏  阅后即焚
                        //$arr[$k]['is_private'] = ($self || $val['is_private'] == 0) ? 0 : 1;
                        $arr[$k]['is_private'] = $val['is_private'] == 0 ? 0 : 1;
                        //付费相片 自己放开 非自己付费了才能看 [对自己和付过费用的小哥哥开放]
                        $arr[$k]['is_free'] = ($self || in_array($val['id'], $payedArr)) ? 1 : 0;
                        //追加相片查看时长
                        $arr[$k]['view_limit'] = $time_limit;
                    }
                    $res[$item] = $arr;
                }
            }

            //基础动态
            $sex = $self ? $this->sex : $userModel->sex;
            $sex = $sex == 1 ? 2 : 1;
            $discoverArr = DiscoverModel::getSnapshotById($user_id, $sex, $self);
            $res['discover'] = $discoverArr;
            //收到的礼物
            $receiveGift = [];
            $gifts = LogGiftReceiveModel::select(['id', 'num', 'gift_id', 'gift_name'])->where([['user_id', $user_id], ['status', 1]])->orderBy('id', 'desc')->get();
            if (!$gifts->isEmpty()) {
                foreach ($gifts as $gift) {
                    $receiveGift[] = [
                        'gift_id' => $gift->gift_id,
                        'gift_name' => $gift->gift_name,
                        'gift_path' => H::path(str_replace('gift', 'gift/', $gift->gift_id) . '.png'),
                        'num' => $gift->num,
                    ];
                }
            }
            $res['receive_gift'] = $receiveGift;
            //守护内容 [超过188个币就算是守护]  ==== 包含守护排行 ====
            $guard = [
                'show_guarder' => true,
                'has_guarder' => false,
                'gift_price' => 128,
            ];
            $guard_rank = [];
            $sweet = LogSweetModel::where([['user_id_receive', $user_id], ['status', 1], ['sweet', '>', 12.8]])->orderBy('sweet', 'desc')->limit(10)->get();
            if (!$sweet->isEmpty()) {
                try {
                    //获取人员数组
                    $idArr = [];
                    foreach ($sweet as $swt) {
                        $idArr[] = $swt->user_id;
                    }
                    $esInfo = EsDataModel::mgetEsUserByIds(['ids' => $idArr]);
                    //整合数据
                    foreach ($sweet as $k => $item) {
                        $guardInfo = $esInfo[$item->user_id] ?? [];
                        $user_settings = UsersSettingsModel::getUserSettings($item->user_id);
                        //附加逻辑 【如果用户设置了隐身守护，则守护榜直接过滤掉这个用户】
                        if ($k == 0) {  //更新守护
                            if ($guardInfo && isset($user_settings['hide_guard']) && $user_settings['hide_guard'] == 0) {
                                $guard = [
                                    'show_guarder' => true,
                                    'has_guarder' => true,
                                    'gift_price' => $item->sweet * 10,
                                    'user_info' => [
                                        'user_id' => $guardInfo['user_id'],
                                        'nick' => $guardInfo['nick'],
                                        'avatar' => $guardInfo['avatar'],
                                    ],
                                ];
                            }
                        }
                        if ($guardInfo && isset($user_settings['hide_guard']) && $user_settings['hide_guard'] == 0) {
                            $guard_rank[] = [
                                'user_id' => $guardInfo['user_id'],
                                'avatar' => $guardInfo['avatar'],
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
            $res['guard'] = $guard;
            $res['guard_rank'] = $guard_rank;
            //分享
            $res['share'] = UsersProfileModel::share($user_id, $userModel);
            //评论信息本人
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }

    }


    public function loginOut(Request $request)
    {
        auth()->logout();
        auth()->invalidate(true);
        HR::signLoginDel($this->uid);
        return $this->jsonExit(200, 'OK');
    }

}
