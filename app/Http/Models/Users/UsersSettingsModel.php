<?php

namespace App\Http\Models\Users;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Http\Helpers\H;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UsersSettingsModel extends Authenticatable implements JWTSubject
{
    protected $table = 'users_settings';
    protected $guarded = [];
    use Notifiable;

    protected $hidden = [
        'created_at', 'updated_at'
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['role' => 'user'];
    }

    //获取全部的已经设置了隐身模式的人员数组
    public static function getHideModelIdArr()
    {
        return self::where('hide_model', 1)->pluck('id')->toArray();
    }

    //用不的配置信息
    public static function getUserSettings($uid = 0)
    {
        $redis_data = R::hget('user_settings', $uid);
        if (empty($redis_data)) {
            $settings = self::where('user_id', $uid)->first();
            if (!$settings) {
                return [];
            } else {
                $ret = [];
                foreach ($settings->toArray() as $key => $data) {
                    if (in_array($key, ['id', 'user_id'])) continue;
                    $ret[$key] = $data;
                }
                R::hset('user_settings', $uid, json_encode($ret));
                return $ret;
            }
        }
        return json_decode($redis_data, 1);
    }

    //获取用户指定的单一配置
    public static function getSingleUserSettings($uid, $col = 'discover_publish'): int
    {
        $settings = self::getUserSettings($uid);
        return $settings[$col] ?? 0;
    }

    public static function getUserInfo($id = 0)
    {
        return self::where('user_id', $id)->first();
    }

    //刷新用户的配置
    public static function refreshUserSettings($uid, $key = 'guanzhu', $val = 1)
    {
        $settings = self::getUserSettings($uid);
        $settings[$key] = $val;
        R::hset('user_settings', $uid, json_encode($settings));
    }

    public static function setViolation($uid, $action = 'violation_image')
    {
        self::where('user_id', $uid)->increment($action);
    }

}
