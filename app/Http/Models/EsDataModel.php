<?php

namespace App\Http\Models;

use App\Components\ESearch\ESearch;
use App\Components\ESearch\Model\bioItemsData;
use App\Components\ESearch\Model\discoverItemsData;
use App\Components\ESearch\Model\usersItemsData;
use App\Components\ESearch\Model\tagsItemsData;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Helpers\R;
use App\Http\Models\Discover\DiscoverTopicUserModel;
use App\Http\Models\Users\UsersFollowModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class EsDataModel extends Model
{
    //-------更新单个用户的信息---------
    public static function updateEsUser(array $params)
    {
        $hideModelArr[] = $params;
        //更新es
        (new ESearch('users:users'))->updateSingle($hideModelArr);
    }


    public static function getEsUsers($params = [])
    {
        $itemsModel = new usersItemsData($params);
        $data = (new ESearch('users:users'))->search($itemsModel);
        return $data;
    }

    public static function getEsDiscovers($params = [])
    {
        $discover = new discoverItemsData($params);
        $data = (new ESearch('discover:discover'))->search($discover);
        return $data;
    }

    public static function getEsTopicSearch($params = [])
    {
        $topic = new tagsItemsData($params);
        $data = (new ESearch('tags:tags'))->search($topic);
        return $data;
    }

    public static function getEsBioSearch($params = [])
    {
        $bio = new bioItemsData($params);
        $data = (new ESearch('bio:bio'))->search($bio);
        return $data;
    }

    //-----------获取单个用户信息-------------S----------
    public static function getEsUserById($params = [])
    {
        $data = (new ESearch('users:users'))->getSingleDoc($params);
        if (isset($data['birthday'])) $data['age'] = H::getAgeByBirthday($data['birthday']);
        //头像违规处理
        if (isset($data['avatar_illegal']) && $data['avatar_illegal'] == 1) $data['avatar'] = H::errUrl('avatar');
        if (isset($data['id'])) $data['user_id'] = $data['id'];
        return $data;
    }

    //搜索得到用户的id 数组，一般是搜索昵称
    public static function getUserIdArr($q)
    {
        $res = [];
        $sourceArr = explode(',', COORDINATES);
        $params = [
            'q' => $q,
            'page' => 1,
            'size' => 50,
        ];
        $data = self::getEsData($params, $sourceArr);
        if ($data) {
            $res = array_column($data['items'], 'user_id');
        }
        return $res;
    }

    //在需要的地方统一获取用户的基础信息
    public static function getEsBaseInfo($params = [])
    {
        $userInfo = self::getEsUserById($params);
        //头像违规处理
        $data = [
            'user_id' => $params['id'],
            'base_str' => $userInfo['base_str'],
            'avatar' => $userInfo['avatar'],
            'vip_is' => $userInfo['vip_is'],
            'sex' => $userInfo['sex'],
            'vip_level' => $userInfo['vip_level'],
            'real_is' => $userInfo['real_is'],
            'goddess_is' => $userInfo['goddess_is'],
            'identity_is' => $userInfo['identity_is'],
            'online' => HR::getOnlineStatus($params['id'], $userInfo['hide_online']),
            'nick' => $userInfo['nick'],
            'sound' => $userInfo['sound'],
            'sound_second' => $userInfo['sound_second'],
            'location' => $userInfo['live_location'],
            'nick_color' => H::nickColor($userInfo['vip_level']),
        ];
        return $data;
    }

    //-----------获取单个用户信息-------------E----------
    public static function mgetEsUserByIds($params = []): array
    {
        if (isset($params['ids'])) {
            $params['ids'] = array_values($params['ids']);
        }
        $res = [];
        $datas = (new ESearch('users:users'))->getManyDoc($params);
        $sourceArr = explode(',', COORDINATES);
        foreach ($datas['docs'] as $data) {
            if (!isset($data['_source'])) continue;
            if (isset($data['_source']['birthday'])) $data['_source']['age'] = H::getAgeByBirthday($data['_source']['birthday']);
            $data['_source']['online'] = HR::getOnlineStatus($data['_id'], $data['_source']['hide_online']);
            $data['_source']['user_id'] = intval($data['_id']);
            $data['_source']['distance_str'] = (count($sourceArr) > 1 && count($data['_source']['live_coordinates']) > 1 && $data['_source']['hide_distance'] == 0) ? H::getDistance($sourceArr[1], $sourceArr[0], $data['_source']['live_coordinates'][0], $data['_source']['live_coordinates'][1]) : '';
            if (isset($data['_source']['live_coordinates'])) unset($data['_source']['live_coordinates']);
            $data['_source']['nick_color'] = H::nickColor($data['_source']['vip_level']);
            //头像违规安排
            if (isset($data['_source']['avatar_illegal']) && $data['_source']['avatar_illegal'] == 1) {
                $data['_source']['avatar'] = H::errUrl('avatar');
            }
            if (isset($data['_source']['mobile'])) unset($data['_source']['mobile']);
            if (isset($data['_source']['constellation'])) unset($data['_source']['constellation']);
            if (isset($data['_source']['live_coordinates'])) unset($data['_source']['live_coordinates']);
            if (isset($data['_source']['platform_id'])) unset($data['_source']['platform_id']);
            if (isset($data['_source']['birthday'])) unset($data['_source']['birthday']);
            if (isset($data['_source']['created_at'])) unset($data['_source']['created_at']);
            if (isset($data['_source']['platform_id'])) unset($data['_source']['platform_id']);
            if (isset($data['_source']['id'])) unset($data['_source']['id']);
            if (isset($data['_source']['hide_model'])) unset($data['_source']['hide_model']);
            if (isset($data['_source']['hide_online'])) unset($data['_source']['hide_online']);
            if (isset($data['_source']['avatar_illegal'])) unset($data['_source']['avatar_illegal']);
            if (isset($data['_source']['ava'])) unset($data['_source']['ava']);
            if (isset($data['_source']['status'])) unset($data['_source']['status']);
            if (isset($data['_source']['live_time_latest'])) unset($data['_source']['live_time_latest']);
            $res[$data['_id']] = $data['_source'];
        }
        return $res;
    }

    //规整ES 数据
    public static function getEsData($params, $sourceArr, $followIdArr = [])
    {
        $users = self::getEsUsers($params);
        if (isset($users['count']) && $users['count'] > 0) {
            foreach ($users['items'] as $k => $item) {
                //头像违规
                if (isset($item['avatar_illegal']) && $item['avatar_illegal'] == 1) {
                    $users['items'][$k]['avatar'] = H::errUrl('avatar');
                }
                //卸载融云的链接信息
                $age = H::getAgeByBirthday($item['birthday']);
                $base_str = $item['live_location'];
                if ($item['sex'] != 0) $base_str .= ' | ' . ($item['sex'] == 1 ? '女' : '男') . '•' . $age;
                $stature = $item['stature'] ?? '160cm';
                if (stripos($stature, 'cm') === false) $stature = $stature . 'cm';
                if ($stature == 'cm') $stature = $item['sex'] == 1 ? '165cm' : '172cm';
                if (!empty($stature)) $base_str .= ' | ' . $stature;
                if (!empty($item['profession'])) $base_str .= ' | ' . $item['profession'];
                //昵称颜色
                $users['items'][$k]['nick_color'] = H::nickColor($item['vip_level']);
                $users['items'][$k]['sub_bio'] = mb_strlen($item['bio']) > 15 ? mb_substr($item['bio'], 0, 15) . '...' : $item['bio'];
                $users['items'][$k]['sex_str'] = $item['sex'] == 1 ? '女' : '男';
                $users['items'][$k]['is_like'] = in_array($item['id'], $followIdArr) ? 1 : 0;    //是否喜欢
                $users['items'][$k]['age'] = $age;
                $users['items'][$k]['active_str'] = H::exchangeDate($item['live_time_latest']);
                $users['items'][$k]['base_str'] = $base_str;
                $users['items'][$k]['user_id'] = $item['id'];
                //在线状态
                $users['items'][$k]['online'] = HR::getOnlineStatus($item['id'], $item['hide_online']);
                //首页显示是否填写了联系方式

                unset($users['items'][$k]['id']);
                //当用户设置了隐藏距离则不显示距离
                $users['items'][$k]['distance_str'] = (count($sourceArr) > 1 && count($item['live_coordinates']) > 1 && $item['hide_distance'] == 0) ? H::getDistance($sourceArr[1], $sourceArr[0], $item['live_coordinates'][0], $item['live_coordinates'][1]) : '';
                unset($users['items'][$k]['live_coordinates']);
                unset($users['items'][$k]['platform_id']);
                unset($users['items'][$k]['birthday']);
                unset($users['items'][$k]['live_time_latest']);
                unset($users['items'][$k]['created_at']);
                unset($users['items'][$k]['mobile']);
                unset($users['items'][$k]['status']);
                unset($users['items'][$k]['hide_model']);
                unset($users['items'][$k]['hide_online']);
                unset($users['items'][$k]['avatar_illegal']);
                unset($users['items'][$k]['ava']);
                //显示微信图标处理[oppo 专门处理]
                if (!defined('PLATFORM')) define('PLATFORM', 'all');
                if (PLATFORM == 'oppo' && (intval(date('H')) >= 7 && intval(date('H')) <= 23)) {
                    $users['items'][$k]['contact'] = 0;
                }
            }
            //在这里调整位置 & 调整位置 & 卸载重复用户
            $users['items'] = array_values($users['items']);
        }
        return $users;
    }

    /*-------同步es指定索引的指定数据-----------*/
    //更新es
    public static function syncEs($index, $type, $start = 0, $end = 0)
    {
        if ($start != 0) $start = $start - 1;
        if ($end != 0) $end = $end + 1;
        Artisan::call('sync:es', [
            'index' => $index,
            'type' => $type,
            'action' => 'single',
            'start' => $start,
            'end' => $end,
            'print' => 0
        ]);

    }

    /*-------------------------------------发现es数据获取-------------------------*/
    public static function getEsDiscoverByUserId($user_id, $uid, $sex, $sort, $page = 1, $size = 20)
    {
        $self = $uid == $user_id;
        $params = [
            //'sort' => $sort,
            'page' => $page,
            'size' => $size,
            'user_id' => $user_id,
            'self' => $self,
        ];
        //如果不是自己
        if (!$self) {
            //过滤仅自己可见部分
            $params['private'] = 0;
            $params['sex'] = $sex == 1 ? 2 : 1;
            $friendsFollow = UsersFollowModel::getFriendAndFollow($user_id);
            //对隐私性进行过滤 & 过滤仅自己可见部分-----S----过滤好友及关注我的,我查看的是别人，获取我的关注和好友  我能看到的只有关注人公开的
            if (in_array($uid, $friendsFollow)) {
                $params['private_exclude'] = 2;
            }
        }
        $discover = self::getEsDiscovers($params);
        return $discover;

    }


    /*-------------------------------------话题es数据获取-------------------------*/
    public static function getEsTopic($q, $sort, $page = 1, $size = 20, $uid = 0)
    {
        $params = [
            'sort' => $sort,
            'page' => $page,
            'size' => $size,
            'q' => $q,
        ];
        $topic = self::getEsTopicSearch($params);
        if (isset($topic['time'])) unset($topic['time']);

        $topicArr = [];
        if (isset($topic['count']) && $topic['count'] > 0) {
            $topicArr = array_column($topic['items'], 'stid');
        }
        //二次渲染
        $user_topic = DiscoverTopicUserModel::where([['user_id', $uid], ['status', 1]])->whereIn('topic_id', $topicArr)->pluck('topic_id')->toArray();
        if (count($user_topic) > 0) {
            foreach ($topic['items'] as $k => $top) {
                $topic['items'][$k]['topic_follow_is'] = in_array($top['stid'], $user_topic) ? 1 : 0;
            }
        }
        return $topic;
    }

    /*-------------------------------获取es的签名数据-------------------------*/
    public static function getEsBio($size = 1, $q = '', $cat = '')
    {
        $params = [
            'page' => 1,
            'size' => $size,
            'cat' => $cat,
            'q' => $q,
        ];
        $bio = self::getEsBioSearch($params);
        if (isset($bio['time'])) unset($bio['time']);
        $res = null;
        if ($size == 1 && isset($bio['count']) && $bio['count'] > 0) {
            $res = $bio['items'][0]['content'];
        }
        if ($size > 1 && isset($bio['count']) && $bio['count'] > 0) {
            $res = array_column($bio['items'], 'content');
        }
        if (isset($bio['count'])) unset($bio['count']);
        return $res;
    }
}
