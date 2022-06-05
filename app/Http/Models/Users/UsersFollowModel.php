<?php

namespace App\Http\Models\Users;

use App\Http\Helpers\R;
use App\Http\Helpers\S;
use App\Http\Models\EsDataModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class UsersFollowModel extends Model
{

    protected $guarded = [];
    protected $table = 'user_follow';

    //获取我喜欢的人的列表idArr
    public static function getFollowIdArr($uid, $status = 1): array
    {
        $blackArr = [];
        $exclude = self::_exclude($uid);
        $pluck = self::where([['user_id', $uid], ['status', $status]])->whereNotIn('follow_id', $exclude)->pluck('follow_id');
        if ($pluck) {
            $blackArr = $pluck->toArray();
        }
        return $blackArr;
    }

    //判断是否关注了
    public static function judgeFollow($uid, $user_id = 0)
    {
        $follow = self::where([['status', 1], ['user_id', $uid], ['follow_id', $user_id]])->first();
        return $follow ? 1 : 0;
    }

    //获取关注我的人的idArr
    public static function getFollowMeIdArr($uid, $status = 1): array
    {
        $blackArr = [];
        $exclude = self::_exclude($uid);
        $pluck = self::where([['follow_id', $uid], ['status', $status]])->whereNotIn('follow_id', $exclude)->pluck('user_id');
        if ($pluck) {
            $blackArr = $pluck->toArray();
        }
        return $blackArr;
    }

    public static function _exclude($uid)
    {
        $blackIdArr = UsersBlackListModel::getBlackIdArr($uid);
        $hideModelIdArr = UsersSettingsModel::getHideModelIdArr();
        return array_unique(array_merge($blackIdArr, $hideModelIdArr));
    }

    public static function batchIntoFollow($uid, $albums, $nick = '')
    {
        foreach ((array)$albums as $i => $item) {
            $baseInfo = json_decode($item, 1);
            $status = $baseInfo['status'];
            $user_id = $baseInfo['user_id'];
            $exist = self::where([['user_id', $uid], ['status', $status], ['follow_id', $user_id]])->first();
            if ($exist) continue;
            try {
                //判断用户存不存在
                $user = UsersModel::find($user_id);
                if (!$user) {
                    throw new \Exception('该用户不存在');
                }
                //添加关注记录
                self::updateOrCreate([
                    'user_id' => $uid,
                    'follow_id' => $user_id,
                ], [
                    'user_id' => $uid,
                    'date' => date('Y-m-d'),
                    'follow_id' => $user_id,
                    'status' => $status
                ]);
                $follow_count = self::where([['follow_id', $user_id], ['status', 1]])->count();
                $followed_count = self::where([['user_id', $uid], ['status', 1]])->count(); //我关注的人
                if ($status == 1) {
                    $userSetting = UsersSettingsModel::getUserSettings($user_id);
                    if ($userSetting['hide_follow_push'] == 0) {
                        //极光推送关注信息
                        JpushModel::JpushCheck($user_id, $nick, 0, 3);
                        //未读消息更新
                        UsersMsgNoticeModel::gainNoticeLog($user_id, 'love_me', 1);
                    }
                } else {
                    UsersMsgNoticeModel::gainNoticeLog($user_id, 'love_me', 1, 0);
                }
                //更新被关注用户的关注人数
                UsersProfileModel::where('user_id', $user_id)->update(['follow_num' => $follow_count]);
                UsersProfileModel::where('user_id', $uid)->update(['followed_num' => $followed_count]);
                //关注超过三个人发放奖励
                if ($followed_count >= 3) {
                    UsersRewardModel::userRewardSet($uid, 'guanzhu');
                }
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
                throw new \Exception($e->getMessage());
            }
        }
    }


    //获取我喜欢的人
    public static function getMeFollowPageData($uid, $page, $size, $q = null)
    {
        $res = [];
        //隐身 & 黑名单剔除 & 隐身过滤
        $exclude = self::_exclude($uid);
        // 0 不喜欢 1 喜欢
        $builder = self::select(['user_follow.*', 'users.nick'])->leftjoin('users', 'users.id', '=', 'user_follow.follow_id')
            ->where([['user_follow.user_id', $uid], ['user_follow.status', 1], ['user_follow.follow_id', '!=', $uid]])
            ->whereNotIn('user_follow.follow_id', $exclude);
        if (!is_null($q)) {
            $builder->where('users.nick', 'like', '%' . $q . '%');
        }
        $builder->orderBy('user_follow.updated_at', 'desc');
        $count = $builder->count();
        $followArr = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$followArr->isEmpty()) {
            //通过ES 获取数据
            $ids = [];
            foreach ($followArr as $item) {
                $ids[] = $item->follow_id;
            }
            if (count($ids) > 0) {
                $format = EsDataModel::mgetEsUserByIds(['ids' => $ids]);
                if (!empty($format)) {
                    foreach ($ids as $follow) {
                        if (!isset($format[$follow])) continue;
                        $res[$follow] = $format[$follow];
                    }
                }
            }
        }

        return [
            'items' => $res ? array_values($res) : [],
            'count' => $count,
        ];
    }

    //获取喜欢我的人
    public static function getFollowMePageData($uid, $page, $size, $q = null)
    {
        $res = [];
        //隐身 & 黑名单剔除 &隐身过滤
        $exclude = self::_exclude($uid);
        //剔除我喜欢的
        //$meFollow = self::getFollowIdArr($uid);
        // 0 不喜欢 1 喜欢

        $builder = self::select(['user_follow.*', 'users.nick'])->leftjoin('users', 'users.id', '=', 'user_follow.user_id')
            ->where([['user_follow.follow_id', $uid], ['user_follow.status', 1], ['user_follow.user_id', '!=', $uid]])
            ->whereNotIn('user_follow.user_id', $exclude);
        if (!is_null($q)) {
            $builder->where('users.nick', 'like', '%' . $q . '%');
        }
        $builder->orderBy('user_follow.updated_at', 'desc');
        $count = $builder->count();
        $followMeArr = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$followMeArr->isEmpty()) {
            $ids = [];

            foreach ($followMeArr as $item) {
                $ids[] = $item->user_id;
            }
            $format = EsDataModel::mgetEsUserByIds(['ids' => $ids]);
            foreach ($ids as $follow) {
                if (!isset($format[$follow])) continue;
                $res[$follow] = $format[$follow];
            }
        }
        return [
            'items' => $res ? array_values($res) : [],
            'count' => $count,
        ];
    }


    //我的好友用户列表
    public static function getFriendPageData($uid, $page, $size, $q = null)
    {
        $res = [];
        //隐身 & 黑名单剔除 &隐身过滤
        $exclude = self::_exclude($uid);
        //剔除我喜欢的
        $meFollow = self::getFollowIdArr($uid);
        //我的粉丝
        $builder = self::select(['user_follow.*', 'users.nick'])->leftjoin('users', 'users.id', '=', 'user_follow.user_id')
            ->where([['user_follow.follow_id', $uid], ['user_follow.status', 1], ['user_follow.user_id', '!=', $uid]])
            ->whereIn('user_follow.user_id', $meFollow)
            ->whereNotIn('user_follow.user_id', $exclude);
        if (!is_null($q)) {
            $builder->where('users.nick', 'like', '%' . $q . '%');
        }
        $builder->orderBy('user_follow.updated_at', 'desc');
        $count = $builder->count();
        $followMeArr = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$followMeArr->isEmpty()) {
            $ids = [];
            foreach ($followMeArr as $item) {
                $ids[] = $item->user_id;
            }
            $format = EsDataModel::mgetEsUserByIds(['ids' => $ids]);
            foreach ($ids as $follow) {
                if (!isset($format[$follow])) continue;
                $res[$follow] = $format[$follow];
            }
        }
        return [
            'items' => $res ? array_values($res) : [],
            'count' => $count,
        ];
    }

    /**获取我关注的和好友列表***/
    public static function getFriendAndFollow($uid)
    {
        //我关注的人
        $meFollow = self::getFollowIdArr($uid);
        //关注我的人粉丝
        $followMe = self::getFollowMeIdArr($uid);
        //获取数组的交集
        $friends = array_intersect($meFollow, $followMe);
        return array_unique(array_merge($friends, $followMe));
    }

    //分别统计我关注的，关注我的，我的好友人数
    public static function followInfoCounter($uid): array
    {
        $exclude = UsersFollowModel::_exclude($uid);
        $builder = UsersFollowModel::where([['user_id', $uid], ['status', 1], ['follow_id', '!=', $uid]])->whereNotIn('follow_id', $exclude);
        $total_me_love = $builder->count();
        $meFollowIdArr = $builder->pluck('follow_id')->toArray();
        $total_love_me = UsersFollowModel::where([['follow_id', $uid], ['status', 1], ['user_id', '!=', $uid]])->whereNotIn('user_id', $exclude)->count();
        $total_friend = UsersFollowModel::where([['follow_id', $uid], ['status', 1], ['user_id', '!=', $uid]])->whereNotIn('user_id', $exclude)->whereIn('user_id', $meFollowIdArr)->count();
        $res['total_love_me'] = $total_love_me;
        $res['total_me_love'] = $total_me_love;
        $res['total_friend'] = $total_friend;
        return $res;
    }
}
