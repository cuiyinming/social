<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use Illuminate\Database\Eloquent\Model;

class LogContactUnlockModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_contact_unlock';

    public static function getUserContact($uid, $profile, $settings)
    {
        $contact = new \stdClass();
        if (!empty($profile->qq) || !empty($profile->wechat) && $settings['hide_contact'] == 0) {
            $unlocked = self::where([['user_id', $uid], ['user_id_viewed', $profile->user_id]])->first();
            $qq = $wechat = '';
            if (!empty($profile->qq)) {
                $qq = H::decrypt($profile->qq);
                $qq = !$unlocked ? H::hideStr($qq, 1, 1) : $qq;
            }
            if (!empty($profile->wechat)) {
                $wechat = H::decrypt($profile->wechat);
                $wechat = !$unlocked ? H::hideStr($wechat, 1, 1) : $wechat;
            }

            $contact = [
                'qq' => $qq,
                'wechat' => $wechat,
                'unlock' => (bool)$unlocked,
                'blur' => 'http://static.hfriend.cn/nick/2101/5.png!blur',
            ];
            //针对oppo 专门做处理
            if (PLATFORM == 'oppo' || PLATFORM == 'vivo') {
                $contact = [
                    'qq' => '',
                    'wechat' => '',
                    'unlock' => false,
                    'blur' => 'http://static.hfriend.cn/nick/2101/5.png!blur',
                ];
            }
        }
        return $contact;
    }


    public static function getDataByPage($page, $size, $q)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('user_id', $q)->orWhere('user_id_viewed', $q);
                } else {
                    $query->where('ip', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {

            }
        }
        return [
            'items' => $datas ?: [],
            'count' => $count,
        ];
    }
}
