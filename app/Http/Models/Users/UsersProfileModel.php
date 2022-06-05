<?php

namespace App\Http\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Http\Helpers\H;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UsersProfileModel extends Authenticatable implements JWTSubject
{
    protected $table = 'users_profile';
    protected $guarded = [];
    use Notifiable;

    protected $hidden = [
        'created_at', 'updated_at'
    ];

    protected $casts = [
        'tags' => 'json', // 声明json类型
        'hobby_sport' => 'json', // 声明json类型
        'hobby_music' => 'json',
        'hobby_food' => 'json',
        'hobby_movie' => 'json',
        'hobby_book' => 'json',
        'hobby_footprint' => 'json',
        'album' => 'json',
        'album_video' => 'json',
        'sound' => 'json',
        'sound_pending' => 'json',
    ];

    public static function getUserInfo($id = 0)
    {
        return self::where('user_id', $id)->first();
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return ['role' => 'user'];
    }

    public static function share($id, $user)
    {
        $share['url'] = [
            'qq' => 'https://bqimu8.jgmlink.cn/AAyG?channel=user&id=' . $id,
            'wechat' => 'https://bqimu8.jgmlink.cn/AAyG?channel=user&id=' . $id,
        ];
        $share['title'] = '介绍' . $user->nick . '给你，看看你喜欢吗？';
        $share['text'] = '来心友遇到心仪的他，心友找对象，一个字就是快！';
        $share['avatar'] = $user->avatar;
        return $share;
    }

}
