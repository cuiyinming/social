<?php

namespace App\Http\Helpers;

use App\Http\Libraries\Tools\IpLocation;
use App\Http\Models\System\BlackIpModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HR
{
    //更新用户的活跃时间
    public static function updateActiveTime($uid)
    {
        $redis = R::connect('active');
        $ttl = config('settings.active');
        $redis->setex('users-last-active-' . $uid, $ttl, time());
    }

    public static function delActiveTime($uid)
    {
        $redis = R::connect('active');
        $redis->del('users-last-active-' . $uid);
    }

    public static function getOnlineStatus($uid, $hide_online = 0)
    {
        if ($hide_online == 1) return 0;  //如果对方设置了隐藏在线状态则直接不在线
        $redis = R::connect('active');
        $time = $redis->get('users-last-active-' . $uid);
        return is_null($time) ? 0 : 1;
    }

    //更新用户的实时活跃坐标
    public static function updateActiveCoordinate($uid, $coordinates = '')
    {
        $ttl = config('settings.coordinate');
        $redis = R::connect('coordinate');
        if (!empty($coordinates) && $coordinates != '0.00,0.00') {
            $redis->setex('users-active-coordinate-' . $uid, $ttl, $coordinates);
        } else {
            if (COORDINATES == '0.0,0.0' || empty(COORDINATES)) return false;
            $redis->setex('users-active-coordinate-' . $uid, $ttl, COORDINATES);
        }
    }

    public static function delActiveCoordinate($uid)
    {
        $redis = R::connect('coordinate');
        $redis->del('users-active-coordinate-' . $uid);
    }

    //自增某一key 用于统计
    public static function incr($uid, $key = 'im-chat-num-counter'): int
    {
        $redis = R::connect('two');
        return $redis->incr($key . '-' . $uid);
    }


    //用户&管理员单点登录
    public static function signLogin($uid, $token, $key = 'user_sign_login')
    {
        $redis = R::connect('three');
        $redis->hset($key, $uid, $token);
    }

    public static function signLoginGet($uid, $key = 'user_sign_login'): ?string
    {
        $redis = R::connect('three');
        return $redis->hget($key, $uid);
    }

    public static function signLoginDel($uid, $key = 'user_sign_login'): int
    {
        $redis = R::connect('three');
        return $redis->hdel($key, $uid);
    }

    /****************************************************
     **--------------------手工重复队列------------------**
     ****************************************************/
    public static function getAllQueue($key)
    {
        $len = Redis::llen($key);
        $arr = Redis::lrange($key, 0, $len);
        return $arr;
    }

    public static function pushQueue($key = '', $value = '', $db = 0)
    {
        if ($db !== 0) {
            Redis::select($db);
        }
        $exit = self::valueIsExists($key, $value);
        if (!$exit) {
            return Redis::lpush($key, $value);
        } else {
            return false;
        }

    }

    public static function delQueue($key, $val)
    {
        return Redis::lrem($key, 0, $val);
    }

    public static function valueIsExists($key, $val)
    {
        if (empty($val)) return true;
        $len = Redis::llen($key);
        $valArrs = Redis::lrange($key, 0, $len);
        return in_array($val, $valArrs);
    }

    /*-------+ 身份证三要素 + 实名认证 + 女神认证次数 ++++++++
    每日观看妹子联系方式次数 [此方法特点是每日自动删除,需要设置] +
    用户被浏览统计不过期 +
    /*-------** 更新每日打招呼人数显示设置【默认每天只能打一次招呼】****/
    public static function updateUniqueNum($uid, $unique_str = '', $prefix = 'users-identity-num', $expire = true, $time = 0)
    {
        if (empty($unique_str)) return false;
        $redis = R::connect('two');
        $res = $redis->sadd($prefix . $uid, $unique_str);
        if ($time == 0 && $expire) $redis->expire($prefix . $uid, H::leftTime());
        if ($time > 0 && $expire) $redis->expire($prefix . $uid, $time);
        return $res;
    }

    public static function getUniqueNum($uid, $prefix = 'users-identity-num'): int
    {
        $redis = R::connect('two');
        return $redis->scard($prefix . $uid);
    }

    public static function existUniqueNum($uid, $unique_str = '', $prefix = 'users-chat-num')
    {
        if (empty($unique_str)) return false;
        $redis = R::connect('two');
        return $redis->sismember($prefix . $uid, $unique_str);
    }


    public static function clearUniqueNum($uid, $prefix = 'users-be-viewed')
    {
        $redis = R::connect('two');
        $redis->del($prefix . $uid);
    }

    public static function getUniqueMembers($uid, $prefix = 'users-identity-num'): array
    {
        $redis = R::connect('two');
        return $redis->smembers($prefix . $uid);
    }

    //每12个小时更新下基于ip获取到的定位
    public static function getLocationByIp($ip, $set = false, $point = '0.00,0.00')
    {
        $redis = R::connect('ip-pointer');
        $key = 'ip-point-' . $ip;
        if ($set) {
            $redis->set($key, $point);
            $redis->expire($key, 43200);
        } else {
            return $redis->get($key);
        }
    }

    //设置用户相册照片数目
    public static function userAlbumNumUpdate($uid, $num = 0, $set = false)
    {
        $redis = R::connect('album-num');
        $key = 'album-num-' . $uid;
        if ($set) {
            $redis->set($key, $num);
            //$redis->expire($key, 43200);
        } else {
            return (int)$redis->get($key) ?: 0;
        }
    }
    /*----数据库四主要负责一些隐身会员动态数据 + 黑名单 + 锁定用户的动态更新及获取------*/
    //不在推荐的人
    public static function setUnderLineId($uid): int
    {
        $redis = R::connect('four');
        return $redis->sadd('sys-under-line-users', $uid);
    }

    public static function getUnderLineId(): array
    {
        $redis = R::connect('four');
        return $redis->smembers('sys-under-line-users');
    }

    public static function delUnderLineId($user_id): int
    {
        $redis = R::connect('four');
        return $redis->srem('sys-under-line-users', $user_id);
    }

    //黑名单
    public static function setLockedId($uid): int
    {
        $redis = R::connect('four');
        return $redis->sadd('sys-locked-users', $uid);
    }

    public static function getLockedId(): array
    {
        $redis = R::connect('four');
        return $redis->smembers('sys-locked-users');
    }

    public static function delLockedId($user_id): int
    {
        $redis = R::connect('four');
        return $redis->srem('sys-locked-users', $user_id);
    }

    public static function existLockedId($uid): int
    {
        $redis = R::connect('four');
        return $redis->sismember('sys-locked-users', $uid);
    }

    //封禁设备信息
    public static function setLockedDevice($device): int
    {
        $redis = R::connect('four');
        return $redis->sadd('sys-locked-device', $device);
    }

    public static function getLockedDevice(): array
    {
        $redis = R::connect('four');
        return $redis->smembers('sys-locked-device');
    }

    public static function existLockedDevice($device): int
    {
        $redis = R::connect('four');
        return $redis->sismember('sys-locked-device', $device);
    }

    public static function delLockedDevice($device): int
    {
        $redis = R::connect('four');
        return $redis->srem('sys-locked-device', $device);
    }

    //封禁手机号
    public static function setLockedMobile($mobile): int
    {
        $redis = R::connect('four');
        return $redis->sadd('sys-locked-mobile', $mobile);
    }

    public static function getLockedMobile(): array
    {
        $redis = R::connect('four');
        return $redis->smembers('sys-locked-mobile');
    }

    public static function existLockedMobile($mobile): int
    {
        $redis = R::connect('four');
        return $redis->sismember('sys-locked-mobile', $mobile);
    }

    public static function delLockedMobile($mobile): int
    {
        $redis = R::connect('four');
        return $redis->srem('sys-locked-mobile', $mobile);
    }

    //封禁的ip
    public static function setLockedIp($ip): int
    {
        $redis = R::connect('four');
        return $redis->sadd('sys-locked-ip', $ip);
    }

    public static function getLockedIp(): array
    {
        $redis = R::connect('four');
        return $redis->smembers('sys-locked-ip');
    }

    public static function existLockedIp($ip): int
    {
        $redis = R::connect('four');
        return $redis->sismember('sys-locked-ip', $ip);
    }

    public static function delLockedIp($ip): int
    {
        $redis = R::connect('four');
        return $redis->srem('sys-locked-ip', $ip);
    }

    public static function delAllLockedIp()
    {
        $redis = R::connect('four');
        return $redis->del('sys-locked-ip');
    }

    //隐身会员
    public static function setHideModelId($uid): int
    {
        $redis = R::connect('four');
        return $redis->sadd('sys-hide-model-users', $uid);
    }

    public static function getHideModelId()
    {
        $redis = R::connect('four');
        return $redis->smembers('sys-hide-model-users');
    }

    public static function delHideModelId($user_id)
    {
        $redis = R::connect('four');
        return $redis->srem('sys-hide-model-users', $user_id);
    }

    public static function existHideModelId($uid)
    {
        $redis = R::connect('four');
        return $redis->sismember('sys-hide-model-users', $uid);
    }

    //用户设置的黑名单人员
    public static function setUserBlackList($uid, $user_id)
    {
        $redis = R::connect('four');
        return $redis->sadd('users-black-list-' . $uid, $user_id);
    }

    public static function delUserBlackList($uid, $user_id)
    {
        $redis = R::connect('four');
        return $redis->srem('users-black-list-' . $uid, $user_id);
    }

    public static function getUserBlackList($uid)
    {
        $redis = R::connect('four');
        return $redis->smembers('users-black-list-' . $uid);
    }

    public static function exitUserBlackList($uid, $user_id)
    {
        $redis = R::connect('four');
        return $redis->sismember('users-black-list-' . $uid, $user_id);
    }

    //获取黑名单+锁定用户+用户黑名单的交集
    public static function getExcludeIdArr($uid)
    {
        $redis = R::connect('four');
        return $redis->sunion(['sys-locked-users', 'sys-hide-model-users', 'sys-under-line-users', 'users-black-list-' . $uid]);
    }

    //版本升级提示管理
    public static function setVerUpdate($uid, $val = 1, $channel = 'ios', $ttl = 86400)
    {
        $redis = R::connect('version');
        $redis->setex('version-' . strtolower($channel) . '-' . $uid, $ttl, $val);
    }

    //获取用户版本
    public static function getVerUpdate($uid, $channel = 'ios')
    {
        $redis = R::connect('version');
        return $redis->get('version-' . strtolower($channel) . '-' . $uid);
    }
}
