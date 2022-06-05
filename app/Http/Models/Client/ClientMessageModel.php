<?php

namespace App\Http\Models\Client;

use Illuminate\Database\Eloquent\Model;

class ClientMessageModel extends Model
{
    protected $guarded = [];
    protected $table = 'client_message';

    /**
     * 获取用户的分页消息
     **/
    public static function getUserMessage($uid = 0, $status = 0, $page = 1, $size = 20)
    {
        $builder = self::where('user_id', $uid)->orderBy('id', 'desc');
        if ($status > 0) {
            $builder->where('read', $status);
        }
        $count = $builder->count();
        $data = $builder->skip(($page - 1) * $size)->take($size)->get();
        $data = !$data->isEmpty() ? $data->toArray() : [];
        $unread = self::getOneUnread($uid, 0);
        //追加固定置顶消息
        $append = [
            [
                'user_id' => 0,
                'title' => '平台升级通知',
                'cont' => '即日起平台将进行功能升级，为期15天左右，期间结算功能将暂时不能使用，请悉知',
                'read' => 1,
                'auth' => '平台通知',
                'created_at' => '2021-03-22 12:00:39'
            ], [
                'user_id' => 0,
                'title' => '平台升级完成',
                'cont' => '即日升级已经完成，所有功能均以开发使用，请大家放心使用',
                'read' => 1,
                'auth' => '平台通知',
                'created_at' => '2021-04-06 12:00:30'
            ]
        ];
        $data = array_merge($append, $data);
        foreach ($data as &$val) {
            $val['created_at'] = substr($val['created_at'], 5, 11);;
        }
        return [
            'data' => $data,
            'count' => $count,
            'unread' => $unread ? true : false
        ];
    }


    public static function getOneUnread($uid = 0, $status = 1)
    {
        return self::where([['read', $status], ['user_id', $uid]])->first();
    }


}
