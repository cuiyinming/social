<?php

namespace App\Http\Models\Users;

use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Logs\LogRecommendModel;
use App\Http\Models\Logs\LogTokenModel;
use App\Http\Models\MessageModel;
use App\Http\Requests\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Http\Helpers\{H, HR, S};
use Illuminate\Foundation\Auth\User as Authenticatable;
use RongCloud;

class UsersModel extends Authenticatable implements JWTSubject
{
    protected $table = 'users';
    protected $guarded = [];
    use Notifiable;

    protected $hidden = [
        'password', 'remember_token', 'updated_at'
    ];

    #获取列表信息

    /**
     * @var mixed
     */

    public static function getUserInfo($id = 0)
    {
        return self::find($id);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['role' => 'user'];
    }

    public static function getLockedIdArr()
    {
        return self::where('status', 0)->pluck('id')->toArray();
    }

    public static function getUnderLineIdArr()
    {
        return self::where([['status', 1], ['under_line', 0]])->pluck('id')->toArray();
    }

    //获取排除的用户列表
    public static function getExcludeIdArr($user_id = 0)
    {
        $redis = config('common.redis_cache');
        if ($redis) {
            return HR::getExcludeIdArr($user_id);
        } else {
            $lockedArr = self::getLockedIdArr();
            $hideArr = UsersSettingsModel::getHideModelIdArr();
            $blackArr = UsersBlackListModel::getBlackIdArr($user_id);
            $underLine = self::getUnderLineIdArr();
            return array_unique(array_merge($lockedArr, $hideArr, $blackArr, $underLine));
        }
    }

    //一个用户关联一个扩展信息
    public function profile()
    {
        return $this->hasOne('App\Http\Models\Users\UsersProfileModel', 'user_id');
    }

    public function settings()
    {
        return $this->hasOne('App\Http\Models\Users\UsersSettingsModel', 'user_id');
    }

    public static function batchIntoOnline(array $albums)
    {
        $sql = 'INSERT INTO `soul_users` (`id`,`online`) VALUES';
        $params = [];
        foreach ($albums as $i => $val) {
            $sql_arr[] = "(:id{$i}, :online{$i})";
            $params[':id' . $i] = $val['id'];
            $params[':online' . $i] = $val['online'];
        }
        DB::statement($sql . implode(',', $sql_arr) . " ON DUPLICATE KEY UPDATE `online` = VALUES(`online`) ", $params);
    }

    public static function batchIntoActive(array $albums)
    {
        $sql = 'INSERT INTO `soul_users` (`id`,`live_time_latest`) VALUES';
        $params = [];
        foreach ($albums as $i => $val) {
            $sql_arr[] = "(:id{$i}, :live_time_latest{$i})";
            $params[':id' . $i] = $val['id'];
            $params[':live_time_latest' . $i] = $val['live_time_latest'];
        }
        DB::statement($sql . implode(',', $sql_arr) . " ON DUPLICATE KEY UPDATE `live_time_latest` = VALUES(`live_time_latest`) ", $params);
    }

    public static function formatUserData($userIdArr = [])
    {
        $res = [];
        $itemsData = self::select(['id', 'platform_id', 'nick', 'avatar', 'sex', 'status', 'birthday'])->whereIn('id', $userIdArr)->get();
        if (!$itemsData->isEmpty()) {
            foreach ($itemsData as $key => $data) {
                $data['user_id'] = $data['id'];
                $data['age'] = H::getAgeByBirthday($data->birthday);
                $data['sex_str'] = $data->sex == 1 ? '女' : '男';
                $res[$data->id] = $data;
            }
        }
        return $res;
    }


    //获取最活跃的几个人
    public static function getRandUsers($uid, $sex)
    {
        $res = $selectArr = [];
        $alternativeArr = self::where([['status', 1], ['sex', $sex], ['id', '!=', $uid]])->whereNull('unlock_time')->orderBy('live_time_latest', 'desc')->limit(500)->pluck('id')->toArray();
        if ($alternativeArr && count($alternativeArr) > 10) {
            $randomArrs = array_rand($alternativeArr, 6);
            foreach ($randomArrs as $randomAr) {
                if (isset($alternativeArr[$randomAr])) {
                    $selectArr[] = $alternativeArr[$randomAr];
                }
            }
        } else {
            //如果不够9个就去最新注册的9个人
            $selectArr = self::where([['status', 1], ['sex', $sex], ['id', '!=', $uid]])->whereNull('unlock_time')->orderBy('created_at', 'desc')->limit(6)->pluck('id')->toArray();
        }
        $userGets = self::whereIn('id', $selectArr)->get();
        if (!$userGets->isEmpty()) {
            foreach ($userGets as $userGet) {
                $res[] = [
                    'user_id' => $userGet->id,
                    'nick' => $userGet->nick,
                    'avatar' => $userGet->avatar,
                ];
            }
        }
        return $res;
    }

    //获取最活跃的几个人
    public static function getRandUsersByDistance($uid, $sex)
    {
        $user = UsersModel::getUserInfo($uid);
        $res = $selectArr = [];
        $idArr = [];
        //优先注册人附近的最新注册的 [由于是最新注册所以排除是空]
        $sortArr['created_at'] = 0; //0不排序 1倒序 2正序
        $sort = UsersModel::getSort($sortArr);
        $params = [
            'real_is' => 0,
            'sex' => $sex,
            'page' => 1,
            'exclusion' => [$uid],
            'distance' => 500,  //500km 内
            'size' => 10,
            'sort' => $sort,
            'local' => 0,
            'location' => $user->last_coordinates,
        ];
        $sourceArr = explode(',', $user->last_coordinates);
        $users = EsDataModel::getEsData($params, $sourceArr, []);
        //如果有超级曝光则优先推荐超级曝光
        if (isset($users['count']) && $users['count'] > 0) {
            $idArr = array_column($users['items'], 'user_id');
        }

        if ($idArr && count($idArr) > 6) {
            $randomArrs = array_rand($idArr, 6);
            foreach ($randomArrs as $randomAr) {
                if (isset($idArr[$randomAr])) {
                    $selectArr[] = $idArr[$randomAr];
                }
            }
        } else {
            //如果不够9个就去最新注册的9个人
            $selectArr = self::where([['status', 1], ['sex', $sex], ['id', '!=', $uid]])->whereNull('unlock_time')->orderBy('created_at', 'desc')->limit(6)->pluck('id')->toArray();
        }
        $userGets = self::whereIn('id', $selectArr)->get();
        if (!$userGets->isEmpty()) {
            foreach ($userGets as $userGet) {
                $res[] = [
                    'user_id' => $userGet->id,
                    'nick' => $userGet->nick,
                    'avatar' => $userGet->avatar,
                ];
            }
        }
        return $res;
    }

    //批量更新用户实时地理坐标信息
    public static function batchIntoCoordinates(array $albums)
    {
        $sql = 'INSERT INTO `soul_users` (`id`,`live_coordinates`,`live_location`) VALUES';
        $params = [];
        foreach ($albums as $i => $val) {
            $sql_arr[] = "(:id{$i}, :live_coordinates{$i}, :live_location{$i})";
            $params[':id' . $i] = $val['id'];
            $params[':live_coordinates' . $i] = $val['live_coordinates'];
            $params[':live_location' . $i] = $val['live_location'];
        }
        DB::statement($sql . implode(',', $sql_arr) . " ON DUPLICATE KEY UPDATE `live_coordinates` = VALUES(`live_coordinates`),`live_location` = VALUES(`live_location`) ", $params);
    }


    //汇总信息
    public static function getSummary()
    {
        $stime = date('Y-m-d 00:00:00');
        $etime = date('Y-m-d 23:59:59');
        $todayNum = self::where([['created_at', '>', $stime], ['created_at', '<', $etime]])->count();
        $online = self::where('online', 1)->count();
        return [
            'today' => $todayNum,
            'online' => $online,
            'check' => UsersProfileModel::where('goddess_handle', 1)->count(),
            'sound' => UsersProfileModel::where('sound_status', 3)->count(),
        ];
    }

    public static function getDataByPage($goddess_is, $identity_is, $q, $status, $unlock_time, $album, $album_video, $hide_model, $goddess_handle, $date, $vip_is, $real_is, $sex, $vip_level, $avatar_illegal, $online, $page, $size, $id, $invited, $contact)
    {
        $builder = self::select(DB::Raw("*,soul_users.id as user_id"));
        if (!is_null($q)) {
            $builder = $builder->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    //判断手机号
                    if (H::checkPhoneNum($q)) {
                        $q = H::encrypt($q);
                    }
                    $query->where('users.mobile', 'like', "%{$q}%")->orWhere('users.id', $q)->orWhere('users.platform_id', $q);
                } else {
                    $query->where('nick', 'like', "%{$q}%")->orWhere('uinvite_code', $q);
                }
            });
        }
        if (!is_null($sex)) {
            $builder->where('sex', $sex);
        }
        if (!is_null($invited)) {
            $builder->where('invited', $invited)->orWhere('client_code', $invited);
        }
        if (!is_null($id) && $id != 0) {
            $builder->where('users.id', $id);
        }
        if (!is_null($online)) {
            $builder->where('online', $online);
        }
        if (!is_null($status)) {
            $builder->where('status', $status);
        }
        if (!is_null($avatar_illegal)) {
            $builder->where('avatar_illegal', $avatar_illegal);
        }
        if (!is_null($unlock_time)) {
            if ($unlock_time == 0) {
                $builder->whereNull('unlock_time');
            } else {
                $builder->whereNotNull('unlock_time');
            }
        }
        if (!is_null($hide_model)) {
            $builder = $builder->leftjoin('users_settings', function ($join) use ($hide_model) {
                $join->on('users.id', '=', 'users_settings.user_id');
            });
            $builder->where('hide_model', $hide_model);
        }
        if (!is_null($goddess_handle) || !is_null($goddess_is) || !is_null($contact) || !is_null($identity_is) || !is_null($real_is) || !is_null($vip_level) || !is_null($vip_is) || !is_null($album) || !is_null($album_video)) {
            $builder = $builder->leftjoin('users_profile', function ($join) use ($goddess_handle, $goddess_is, $identity_is, $real_is, $vip_level, $vip_is, $album, $album_video, $contact) {
                $join->on('users.id', '=', 'users_profile.user_id');
            });
            if (!is_null($contact)) {
                $builder->where('wechat', H::encrypt($contact))->orWhere('qq', H::encrypt($contact));
            }
            if (!is_null($goddess_handle)) {
                $builder->where('goddess_handle', $goddess_handle);
            }
            if (!is_null($goddess_is)) {
                $builder->where('goddess_is', $goddess_is);
            }
            if (!is_null($identity_is)) {
                $builder->where('identity_is', $identity_is);
            }
            if (!is_null($real_is)) {
                $builder->where('real_is', $real_is);
            }
            if (!is_null($vip_level)) {
                $builder->where('vip_level', $vip_level);
            }
            if (!is_null($vip_is)) {
                $builder->where('vip_is', $vip_is);
            }
            if (!is_null($album)) {
                if ($album == 0) {
                    $builder->on('users.id', '=', 'users_profile.user_id')->whereNull('album');
                } else {
                    $builder->on('users.id', '=', 'users_profile.user_id')->whereNotNull('album');
                }
            }
            if (!is_null($album_video)) {
                if ($album_video == 0) {
                    $builder->on('users.id', '=', 'users_profile.user_id')->whereNull('album_video');
                } else {
                    $builder->on('users.id', '=', 'users_profile.user_id')->whereNotNull('album_video');
                }
            }
        }

        if (count($date) > 0) {
            $builder->whereBetween('users.created_at', [$date[0], $date[1]]);
        }
        $count = $builder->count();
        $datas = $builder->skip(($page - 1) * $size)->take($size)->orderBy('users.id', 'DESC')->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$item) {
                $item->profile = UsersProfileModel::where('user_id', $item->user_id)->first();
                if (!is_null($item->profile->album) && is_array($item->profile->album) && count($item->profile->album) > 0) {
                    $albums = $item->profile->album;
                    foreach ($albums as $k => $album) {
                        $albums[$k]['is_illegal'] = isset($album['is_illegal']) && $album['is_illegal'] == 1;
                        $albums[$k]['img_url_video'] = $album['img_url'];
                    }
                    $item->profile->album = $albums;
                }
                $item->profile->album_video = $item->profile->album_video ?: [];
                if (is_array($item->profile->album_video) && count($item->profile->album_video) > 0) {
                    $videos = $item->profile->album_video;
                    foreach ($videos as $ke => $video) {
                        $videos[$ke]['is_illegal'] = isset($video['is_illegal']) && $video['is_illegal'] == 1;
                        $videos[$ke]['img_url_video'] = $video['img_url'];
                    }
                    $item->profile->album_video = $videos;
                }
                $item->id = $item->user_id;
                $item->settings = UsersSettingsModel::where('user_id', $item->user_id)->first();
                $item->settings->chat_add = $item->settings->chat_add == 1;
                $item->settings->discover_publish = $item->settings->discover_publish == 1;
                $item->settings->nick_modify = $item->settings->nick_modify == 1;
                $item->settings->discover_zan = $item->settings->discover_zan == 1;
                $item->settings->discover_cmt = $item->settings->discover_cmt == 1;
                $item->settings->discover_cmt_zan = $item->settings->discover_cmt_zan == 1;
                $item->settings->bio_add_sound = $item->settings->bio_add_sound == 1;
                $item->settings->bio_add = $item->settings->bio_add == 1;
                $item->settings->chat_im = $item->settings->chat_im == 1;
                $item->settings->avatar_upload = $item->settings->avatar_upload == 1;
                $item->settings->say_hi = $item->settings->say_hi == 1;

                $item->profile->identity_card = H::decrypt($item->profile->identity_card);
                $item->profile->identity_name = H::decrypt($item->profile->identity_name);
                $item->profile->identity_mobile = H::decrypt($item->profile->identity_mobile);
                $item->profile->wechat = H::decrypt($item->profile->wechat);
                $item->profile->qq = H::decrypt($item->profile->qq);
                $item->profile->mobile = H::decrypt($item->profile->mobile);
                $item->profile->sound_has = !empty($item->profile->sound);

                $item->profile->goddess_ext = !empty($item->profile->goddess_ext) ? json_decode($item->profile->goddess_ext, 1) : null;
                $item->profile->sound_pending_has = !empty($item->profile->sound_pending);

                if ($item->profile->vip_level > 0 && $item->profile->vip_handle != 2 && $item->profile->vip_handle != 3) {
                    $item->profile->vip_level_name = S::getVipNameByLevelId($item->profile->vip_level);
                } else if ($item->profile->vip_level > 0 && $item->profile->vip_handle == 3) {
                    $item->profile->vip_level_name = '邀请奖励';
                } else if ($item->profile->vip_level > 0 && $item->profile->vip_handle == 2) {
                    $item->profile->vip_level_name = '赠送体验';
                } else if ($item->profile->vip_level == 0 && is_null($item->profile->vip_exp_time)) {
                    $item->profile->vip_level_name = '未开通';
                } else {
                    $item->profile->vip_level_name = '已过期';
                }

                $item->profile->album_length = $item->profile->album ? count($item->profile->album) : 0;
                $item->profile->video_length = $item->profile->album_video ? count($item->profile->album_video) : 0;
                $item->mobile = H::decrypt($item->mobile);
                $item->sex = $item->sex == 0 ? '未知' : ($item->sex == 1 ? '女' : '男');
                $item->age = H::getAgeByBirthday($item->birthday);

                $item->status = !($item->status == 1);
                $item->device_lock = !($item->device_lock == 0);
                $item->unlock = !is_null($item->unlock_time);
            }
        }
        $summary = self::getSummary();
        return [
            'data' => $datas ? $datas : [],
            'count' => $count,
            'summary' => $summary,
        ];
    }


    public static function fakeUser()
    {
        $mobile = '137' . rand(10000000, 99999999);
        $sex = rand(1, 2);
        $nick = '游客' . rand(1, 100);
        $data = [
            'mobile' => $mobile,
            'avatar' => $sex == 1 ? 'http://static.hfriend.cn/ava/1-64.jpg' : 'http://static.hfriend.cn/ava/2-67.jpg',
            'birthday' => '2000-' . rand(1, 12) . '-' . rand(1, 28),
            'nick' => $nick
        ];

        $encryptMobile = H::encrypt($mobile);
        $salt = H::randstr(6);
        try {
            DB::beginTransaction();
            $user = UsersModel::create([
                'mobile' => $encryptMobile,
                'password' => Hash::make($data['mobile'] . $salt),
                'sweet_coin' => 0,
                'last_ip' => IP,
                'last_login' => CORE_TIME,
                'last_location' => CITY,
                'last_coordinates' => COORDINATES,
                'live_coordinates' => COORDINATES,
                'live_location' => CITY,
                'live_time_latest' => CORE_TIME,
                'nick' => $data['nick'],
                'avatar' => $data['avatar'],
                'sex' => $sex,
                'birthday' => $data['birthday'],
                'constellation' => H::getConstellationByBirthday($data['birthday']),
                'salt' => $salt,
                'invited' => '',
                'online' => 1,
                'status' => 1,
                'device' => DEVICE,
                'device_lock' => 0,
            ]);
            //生成用户的id
            $user->platform_id = H::getPlatformId($user->id);
            $user->uinvite_code = H::createInviteCodeById($user->id);
            //获取融云用户id
            try {
                $rongToken = RongCloud::getToken($user->id, $data['nick'], $data['avatar']);
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }
            $user->rong_token = isset($rongToken['token']) ? $rongToken['token'] : '';
            $user->save();
            //创建扩展信息
            UsersProfileModel::create([
                'user_id' => $user->id,
                'register_coordinates' => COORDINATES,
                'register_location' => CITY,
                'register_ip' => IP,
                'register_date' => CORE_TIME,
                'register_channel' => CHANNEL,
                'register_device' => DEVICE,
                'live_addr' => CITY,
                'mobile' => $encryptMobile,
                'bio' => '',
            ]);
            //创建设置信息裱
            UsersSettingsModel::create([
                'user_id' => $user->id,
                'hide_model' => 0,
            ]);
            //还原手机号
            $user->mobile = $data['mobile'];
            DB::commit();
            return $user;
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return null;
        }
    }

    //亲密关系推荐
    public static function honeyRecommend($page, $uid, $size = 10): array
    {
        $res = [];
        try {
            $followIdArr = UsersFollowModel::getFollowIdArr($uid);
            $exclusion = self::exclusion($uid, $followIdArr);
            $user = UsersModel::where('id', $uid)->first();
            $sex = $user->sex == 1 ? 2 : 1;
            //首先推荐在线 [不包含头像未完善的]
            $online = false;
            $builder = UsersModel::where([['id', '>', 1000], ['status', 1], ['sex', $sex]]);
            if ($online) {
                $builder->where('online', 1);
            }
            $items = $builder->where('avatar', 'like', '%' . '/avatar/' . '%')
                ->whereNotIn('id', $exclusion)->skip(($page - 1) * $size)->take($size)->orderBy(DB::Raw('RAND()'))->get();
            //测试
            //$items = UsersModel::where('id', 141093)->orderBy(DB::Raw('RAND()'))->get();
            if ($items->isEmpty()) {
                //如果没有在线的则推荐同城的
                $self_hometown = UsersProfileModel::where('user_id', $uid)->first()->hometown;
                if (!empty($self_hometown)) {
                    $items = UsersModel::select(['users.id', 'users.avatar', 'users.sex'])->leftjoin('users_profile', 'users.id', '=', 'users_profile.user_id')->where([['users.status', 1], ['users.sex', $sex], ['users_profile.hometown', $self_hometown]])
                        ->where('users.avatar', 'like', '%' . '/avatar/' . '%')->whereNotIn('users.id', $exclusion)->skip(($page - 1) * $size)->take($size)->orderBy(DB::Raw('RAND()'))->get();
                }
            }
            //如果没有则推荐我的坐标方圆50km内的异性用户
            if ($items->isEmpty()) {
                $sortArr['created_at'] = 0; //0不排序 1倒序 2正序
                $sort = self::getSort($sortArr);
                $params = [
                    'real_is' => 1,
                    'sex' => $sex,
                    'page' => 1,
                    'exclusion' => $exclusion,
                    'distance' => 500,  //50km 内
                    'size' => $size,
                    'sort' => $sort,
                    'location' => $user->last_coordinates,
                ];
                $sourceArr = explode(',', $user->last_coordinates);
                $users = EsDataModel::getEsData($params, $sourceArr, $followIdArr);
                //如果有超级曝光则优先推荐超级曝光
                if (isset($users['count']) && $users['count'] > 0) {
                    $userIdArr = array_column($users['items'], 'user_id');
                    $items = UsersModel::where('status', 1)->whereIn('id', $userIdArr)->get();
                }
            }

            if (!$items->isEmpty()) {
                $insert = $uidArr = [];
                foreach ($items as $item) {
                    $uidArr[] = $item->id;
                    $rand = rand(60, 99);
                    $insert[] = [
                        'user_id' => $uid,
                        'user_sex' => $user->sex,
                        'user_id_rec' => $item->id,
                        'user_sex_rec' => $item->sex,
                        'recommend_at' => date('Y-m-d H:i:s'),
                        'match' => $rand,
                        'created_at' => CORE_TIME,
                        'updated_at' => CORE_TIME,
                    ];
                }
                LogRecommendModel::insert($insert);
                //返回数据规整 【二次查询es返回对应规整数据】
                $res = EsDataModel::mgetEsUserByIds(['ids' => $uidArr]);
                if (count($res) > 0) {
                    //在这里追加用户动态信息
                    foreach ($res as $userId => $userInfo) {
                        $res[$userId]['say_hi'] = HR::existUniqueNum($uid, $userId, 'say-hi-num') != 1;
                        $res[$userId]['match'] = rand(60, 99);
                        $discover = DiscoverModel::where([['user_id', $userId], ['status', 1], ['private', '!=', 1]])->orderBy('album', 'desc')->first();
                        $res[$userId]['discover'] = $discover ? [
                            'cont' => $discover->cont,
                            'album' => $discover->album,
                        ] : [];
                    }
                }
                $res = array_values($res);
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
        //在这里规整数据
        return $res;
    }

    public static function getSort(array $params): array
    {
        //规定所有的0代表不排序 1代表倒序 2代表正序
        $sort = [];
        foreach ($params as $key => $param) {
            if ($params[$key] !== 0) {
                $sort[] = [
                    $key => [
                        'order' => $params[$key] == 1 ? 'desc' : 'asc',
                    ]
                ];
            }
        }
        return $sort;
    }

    public static function exclusion($uid, $followIdArr): array
    {
        //黑名单的人不做推荐
        $blackIdArr = UsersFollowModel::_exclude($uid);
        $merge = array_merge($blackIdArr, $followIdArr);
        $merge[] = $uid;
        //已经推荐的不在推荐 [限定三天内推荐的不在二次推荐]
        $beforeTime = time() - config('settings.recommend') * 3600;
        $recommendArr = LogRecommendModel::where([['user_id', $uid], ['created_at', '>=', date('Y-m-d H:i:s', $beforeTime)], ['created_at', '<=', date('Y-m-d H:i:s')]])->pluck('user_id_rec')->toArray();
        if ($recommendArr) {
            $merge = array_merge($merge, $recommendArr);
        }
        return array_values(array_unique($merge));
    }

    //代理拉新会员 【需要延伸层级】
    public static function getClientUser($page = 1, $size = 20, $client_code = 'abc'): array
    {
        $base_data = [
            'count' => 0,
            'items' => []
        ];
        $levela = $levelb = $levelc = $invitea = $inviteb = $invitec = [];
        $first = self::where([['client_code', $client_code], ['status', 1]])->get();
        $idArr = [];
        if (!$first->isEmpty()) {
            foreach ($first as $fir) {
                $levela[] = $fir->id;
                $invitea[] = $fir->uinvite_code;
            }
            if (count($invitea) > 0) {
                //第二层
                $second = self::where('status', 1)->whereIn('invited', $invitea)->get();
                if (!$second->isEmpty()) {
                    foreach ($second as $sec) {
                        $levelb[] = $sec->id;
                        $inviteb[] = $sec->uinvite_code;
                    }
                    if (count($inviteb) > 0) {
                        //第三层
                        $third = self::where('status', 1)->whereIn('invited', $inviteb)->get();
                        if (!$third->isEmpty()) {
                            foreach ($third as $thi) {
                                $levelc[] = $thi->id;
                            }
                        }
                    }
                }
            }

            $idArr = array_unique(array_merge($levela, $levelb, $levelc));
            $builder = self::select(['id', 'nick', 'mobile', 'sex', 'last_login', 'created_at'])->whereIn('id', $idArr)->orderBy('id', 'desc');
            $items = $builder->skip(($page - 1) * $size)->take($size)->get();
            if (!$items->isEmpty()) {
                foreach ($items as $item) {
                    $item->mobile = H::hideStr(H::decrypt($item->mobile), 3, 3);
                    $item->sex = $item->sex == 1 ? '女' : '男';
                    //昵称打码
                    $item->nick = H::hideNick($item->nick);
                    if (in_array($item->id, $levela)) {
                        $item->level = '一级';
                    }
                    if (in_array($item->id, $levelb)) {
                        $item->level = '二级';
                    }
                    if (in_array($item->id, $levelc)) {
                        $item->level = '三级';
                    }
                }
            }
            $base_data = [
                'count' => count($idArr),
                'items' => $items ? $items : []
            ];
        }
        return $base_data;

    }
}
