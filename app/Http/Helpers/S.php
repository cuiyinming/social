<?php

namespace App\Http\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Http\Libraries\Crypt\Encrypt;
use App\Http\Libraries\Crypt\Decrypt;

class S
{
    public static function getVipPriceById($productId, $column = 'price'): string
    {
        $itemInfo = [];
        $productMaps = array_values(config('subscribe.vip_list'));
        foreach ($productMaps as $productMap) {
            foreach ($productMap as $key => $item) {
                if ($item['id'] == $productId) {
                    $itemInfo = $item;
                }
            }
        }
        return $itemInfo[$column] ?? 0;
    }

    public static function getDiamondPriceById($productId, $column = 'price'): string
    {
        $itemInfo = [];
        $productMaps = config('subscribe.recharge_list');
        foreach ($productMaps as $key => $item) {
            if ($item['id'] == $productId) {
                $itemInfo = $item;
            }
        }
        return $itemInfo[$column] ?? 0;
    }

    //获取签到的奖励设置
    public static function getSignRewardByDay($day, $column = 'reward_int'): int
    {
        $itemInfo = [];
        $signs = config('subscribe.sign');
        foreach ($signs as $key => $item) {
            if ($item['day'] == $day) {
                $itemInfo = $item;
            }
        }
        return $itemInfo[$column] ?? 0;
    }

    //获取每日任务的奖励设置
    public static function getTaskReward($name = 'guanzhu', $map = 'new'): int
    {
        $rwd = 0;
        $reward = config('self.reward_list');
        $tasks = $map == 'new' ? $reward['new'] : $reward['normal'];
        foreach ($tasks as $task) {
            if ($task['name'] != $name) continue;
            $rwd = $task['reward'];
        }
        return $rwd;
    }

    public static function getVipNameByLevelId($id = 0, $column = 'name'): string
    {
        $itemInfo = [];
        if ($id > 0) {
            $productMaps = array_values(config('subscribe.vip_list'));
            foreach ($productMaps as $productMap) {
                foreach ($productMap as $key => $item) {
                    if ($item['id_num'] == $id) {
                        $itemInfo = $item;
                    }
                }
            }
        }
        return isset($itemInfo[$column]) ? $itemInfo[$column] : '';
    }

    public static function getPro($type = 1)  //0 订阅 1内购 2全部
    {
        $items = [];
        if (in_array($type, [1, 2])) {
            $productMaps = config('subscribe.recharge_list');
            $items = Arr::pluck($productMaps, 'id');
        }
        if (in_array($type, [0, 2])) {
            $productMaps = array_values(config('subscribe.vip_list'));
            foreach ($productMaps as $product) {
                foreach ($product as $pro) {
                    $items[] = $pro['id'];
                }
            }
            //新版苹果订阅
            $productMaps = config('subscribe.vip_list_ver');
            foreach ($productMaps as $pro) {
                $items[] = $pro['id'];
            }
            //新版安卓合规价格
            $productMaps = config('subscribe.vip_list_ver_android');
            foreach ($productMaps as $pro) {
                $items[] = $pro['id'];
            }
        }
        return $items;
    }

    //获取系统全部合法的价格
    public static function getProPriceList($type = 2)  //0 订阅 1内购 2全部
    {
        $items = [];
        if (in_array($type, [1, 2])) {
            $productMaps = config('subscribe.recharge_list');
            $items = Arr::pluck($productMaps, 'price');
        }
        if (in_array($type, [0, 2])) {
            //原始订阅
            $productMaps = array_values(config('subscribe.vip_list'));
            foreach ($productMaps as $product) {
                foreach ($product as $pro) {
                    $items[] = $pro['price'];
                }
            }
            //新版苹果订阅
            $productMaps = config('subscribe.vip_list_ver');
            foreach ($productMaps as $pro) {
                $items[] = $pro['price'];
            }
            //新版安卓合规价格
            $productMaps = config('subscribe.vip_list_ver_android');
            foreach ($productMaps as $pro) {
                $items[] = $pro['price'];
            }
        }
        return $items;
    }

    /*----im 信息轻量化接口处理*-----*/
    public static function imInfoSet($user)
    {
        $redis = R::connect('one');
        $profile = $user->profile;
        $info = [
            'user_id' => $user->id,
            'avatar' => $user->avatar,
            'avatar_illegal' => $user->avatar_illegal,
            'nick' => $user->nick,
            'location' => $profile->live_addr ? $profile->live_addr : '来自火星',
            'real_is' => $profile->real_is,
            'identity_is' => $profile->identity_is,
            'goddess_is' => $profile->goddess_is,
            'vip_is' => $profile->vip_is,
            'vip_level' => $profile->vip_level,
        ];
        $redis->hset('user_im_info', $user->id, json_encode($info));
    }

    public static function imInfoGet(array $keys)
    {
        $redis = R::connect('one');
        return $redis->hmget('user_im_info', $keys);
    }

    public static function imInfoGetOne($key)
    {
        $redis = R::connect('one');
        return $redis->hget('user_im_info', $key);
    }

    //更新redis信息
    public static function imInfoUpdate($item)
    {
        $redis = R::connect('one');
        if (isset($item['id']) && $item['id'] > 0) {
            $old = $redis->hget('user_im_info', $item['id']);
            if (!is_null($old) && $old = json_decode($old, 1)) {
                foreach ($old as $k => $v) {
                    if (array_key_exists($k, $item)) {
                        $old[$k] = $item[$k];
                    }
                }
                $redis->hset('user_im_info', $item['id'], json_encode($old));
            }
        }
    }


    //针对异步接口专用的查询
    public static function getPriceAndTime($proId = 'quzhi11', $exp_time = null, $platform = 'ios'): array
    {
        $time = 0;
        $itemInfo = [];
        if ($platform == 'ios') {
            $productMaps = array_values(config('subscribe.vip_list'));
            foreach ($productMaps as $productMap) {
                foreach ($productMap as $item) {
                    if ($item['id'] == $proId) {
                        $itemInfo = $item;
                        if (in_array($item['id_num'], [1, 2])) $time = 7 * 86400;
                        if (in_array($item['id_num'], [4, 5])) $time = 30 * 86400;
                        if (in_array($item['id_num'], [7, 8])) $time = 90 * 86400;
                        if (in_array($item['id_num'], [10, 11])) $time = 365 * 86400;
                    }
                }
            }
        }
        if ($platform == 'android') {
            $productMaps = config('subscribe.vip_list_ver_android');
            foreach ($productMaps as $item) {
                if ($item['id'] == $proId) {
                    $itemInfo = $item;
                    if (in_array($item['id_num'], [1, 2])) $time = 7 * 86400;
                    if (in_array($item['id_num'], [4, 5])) $time = 30 * 86400;
                    if (in_array($item['id_num'], [7, 8])) $time = 90 * 86400;
                    if (in_array($item['id_num'], [10, 11])) $time = 365 * 86400;
                }
            }
        }

        if ($exp_time && $exp_time >= date('Y-m-d H:i:s')) {
            $last_time = strtotime($exp_time) + $time;
        } else {
            $last_time = time() + $time;
        }
        $res = [
            'price' => $itemInfo['price'] ?? 0,
            'level' => $itemInfo['id_num'] ?? 0,
            'time' => date('Y-m-d H:i:s', $last_time),
        ];
        return $res;
    }


    public static function getPriceAndTimeLast($proId = 'quzhi11', $exp_time = null)
    {
        $time = 0;
        $productMaps = array_values(config('subscribe.vip_list'));
        foreach ($productMaps as $productMap) {
            foreach ($productMap as $key => $item) {
                if ($item['id'] == $proId) {
                    if (in_array($item['id_num'], [1, 2])) $time = 7 * 86400;
                    if (in_array($item['id_num'], [4, 5])) $time = 30 * 86400;
                    if (in_array($item['id_num'], [7, 8])) $time = 90 * 86400;
                    if (in_array($item['id_num'], [10, 11])) $time = 365 * 86400;
                }
            }
        }
        if ($exp_time) {
            $last_time = $exp_time + $time;
        } else {
            $last_time = time() + $time;
        }
        return $last_time;
    }

    public static function getPriceAndCoin($proId = 'xinyou3'): array
    {
        $res = [
            'price' => 0,
            'diamond' => 0,
        ];
        $productMaps = config('subscribe.recharge_list');
        foreach ($productMaps as $item) {
            if ($item['product_id'] == $proId) {
                $res = [
                    'price' => $item['price'] ?? 0,
                    'diamond' => $item['diamond'] ?? 0,
                ];
            }
        }
        return $res;
    }
}
