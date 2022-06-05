<?php

namespace App\Http\Models\Logs;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;

class LogUserModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_user';

    public static function gainLog($user_id, $action_type = '', $old_value = '', $new_value = '', $remark = '', $success = 1, $type = 0)
    {
        self::create([
            'user_id' => $user_id,
            'action_type' => $action_type,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'success' => $success,
            'ip' => IP,
            'channel' => CHANNEL,
            'description' => $remark,
            'type' => $type
        ]);
    }

    /**
     * 获取用户的分页登陆日志
     */
    public static function getPageLog($page = 1, $size = 20, $q = '', $type = 0)
    {
        $builder = self::where('type', $type)->orderBy('id', 'desc');
        if ($q != '') {
            $builder->where('action_type', 'like', '%' . $q . '%')->orWhere('user_id', trim($q))->orWhere('ip', 'like', '%' . $q . '%')->orWhere('description', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$logs->isEmpty()) {
            foreach ($logs as $log) {

            }
        }
        return [
            'count' => $count,
            'logs' => $logs ? $logs : []
        ];
    }
}
