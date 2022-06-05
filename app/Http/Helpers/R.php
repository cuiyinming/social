<?php

namespace App\Http\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class R
{
    /**
     *默认传值生成key
     */
    public static function __key($method, $args)
    {
        return last(explode("\\", $method)) . ':' . join(':', $args);
    }

    /**
     * 存取redis的通用方法
     */
    public static function sredis($data = [], $key = '', $expire = 864000)
    {
        $key = $key == '' ? self::gkey() : $key;
        $data = json_encode($data, 1);
        if ($expire == 0) {
            Redis::set($key, $data);
        } else {
            Redis::setex($key, $expire, $data); //加入缓存
        }
    }

    public static function gredis($key = '')
    {
        if (!env('REDIS_CACHE', true)) {
            return [];
        }
        $key = $key == '' ? self::gkey() : $key;
        $data = Redis::get($key);
        if ($data) {
            $data_arr = json_decode($data, 1);
        } else {
            $data_arr = [];
        }
        return $data_arr;

    }

    /**
     * @return string
     * 按照规则生成唯一的key
     */
    private static function gkey()
    {
        if ($key_arr = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]) {
            return last(explode("\\", $key_arr['class'])) . '::' . $key_arr['function'];
        }
        return '';
    }

    //删除redis
    public static function dredis($key = '')
    {
        $key = $key == '' ? self::gkey() : $key;
        return Redis::del($key);
    }

    //添加无序列表
    public static function sadd($key, $val)
    {
        return Redis::sadd($key, $val);
    }

    public static function scard($key)
    {
        return Redis::scard($key);
    }

    public static function keys($key)
    {
        return Redis::keys($key);
    }

    /**
     * 指定key后放入指定的数据到队列
     * 保障队列的值的唯一性
     */
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

    /**
     * 获取队列的值
     */
    public static function popQueue($key = '', $db = 0)
    {
        if ($db !== 0) {
            Redis::select($db);
        }
        return Redis::rpop($key);
    }

    /**
     * 判断队列是否存在某一直
     */
    public static function valueIsExists($key, $val)
    {
        if (empty($val) || is_null($val)) return true;
        $len = Redis::llen($key);
        $valArrs = Redis::lrange($key, 0, $len);
        return in_array($val, $valArrs) ? true : false;
    }

    /**
     * 删除队列中指定的值
     */
    public static function delQueue($key, $val)
    {
        return Redis::lrem($key, 0, $val);
    }

    /**
     * 获取咧咧中所有的值
     */
    public static function getAllQueue($key)
    {
        $len = Redis::llen($key);
        $arr = Redis::lrange($key, 0, $len);
        return $arr;
    }

    public static function connect($name = 'default')
    {
        return Redis::connection($name);
    }

    /**选择数据库**/
    public static function select($db)
    {
        return Redis::select($db);
    }

    /**
     * hset
     */
    public static function hset($key, $file, $val)
    {
        return Redis::hset($key, $file, $val);
    }

    public static function hget($key, $file)
    {
        return Redis::hget($key, $file);
    }

    public static function hkeys($key)
    {
        return Redis::hkeys($key);
    }

    public static function hdel($key, $file)
    {
        return Redis::hdel($key, $file);
    }

    public static function sismember($key, $file)
    {
        return Redis::SISMEMBER($key, $file);
    }

    /**
     * pfcount
     */
    public static function pfadd($key = '', $value = '', $db = 0)
    {
        if ($db !== 0) {
            Redis::select($db);
        }
        $exist = Redis::exists($key);
        Redis::pfadd($key, $value);
        if ($exist == 0) {
            Redis::expire($key, 86400 * 5);
        }
    }

    /**
     * redis自增操作
     */
    public static function incr($key, $num = 1)
    {
        return Redis::incrby($key, $num);
    }

    public static function set($key, $val)
    {
        return Redis::set($key, $val);
    }

    public static function get($key)
    {
        return Redis::get($key);
    }

}
