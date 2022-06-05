<?php

namespace App\Http\Models\Logs;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;

class LogPushModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_push';

    public static function storeToDb($user_id, $msg, $type = 1, $msg_id = 0)
    {
        //推送类型 1批量 2诱导 3定向 4消息
        $data = [
            'user_id' => $user_id,
            'msg' => $msg['cont'] ?? '',
            'title' => $msg['title'] ?? '',
            'msg_id' => $msg_id,
            'type' => $type,
            'created_at' => CORE_TIME,
            'updated_at' => CORE_TIME,
        ];
        self::insert($data);
    }


    public static function getPageAdminItems($page, $size, $q, $type)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder->where('user_id', $q)->orWhere('msg', 'like', '%' . $q . '%');
        }
        if (!is_null($type) && $type != 0) {
            $builder->where('type', $type);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get()->map(function ($item) {
            $typeMap = [
                1 => '批量推送',
                2 => '诱导类',
                3 => '定向通知',
                4 => '消息'
            ];
            $item->type_name = isset($typeMap[$item->type]) ? $typeMap[$item->type] : '';
            return $item;
        });
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }
}
