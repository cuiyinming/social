<?php

namespace App\Http\Models;

use App\Http\Helpers\{H, R};
use Illuminate\Database\Eloquent\Model;

class SettingsModel extends Model
{
    protected $table = 'settings';
    protected $guarded = [];

    public static function getAllConf()
    {
        return self::where('status', 1)->get();
    }

    public static function getSigConf($key = 'base')
    {
        $skey = 'settings_' . $key;
        $redis_data = R::gredis($skey);
        if (empty($redis_data)) {
            $datas = self::select(['key', 'value'])->where([['option', $key], ['status', 1]])->get();
            if (!$datas) {
                return [];
            } else {
                $ret = [];
                foreach ($datas as $data) {
                    if ($data->value === 'true') {
                        $data->value = true;
                    }
                    if ($data->value === 'false') {
                        $data->value = false;
                    }
                    $ret[$data->key] = $data->value;
                }
                R::sredis($ret, $skey, 86400 * 30);
                $redis_data = $ret;
            }
        }
        return $redis_data;
    }

    //创建更新配置
    public static function updateInsert($options, $params = [])
    {
        foreach ($params as $key => $param) {
            self::updateOrCreate([
                'option' => $options,
                'key' => $key
            ], [
                'value' => $param,
                'option' => $options,
                'key' => $key
            ]);
        }

    }
}
