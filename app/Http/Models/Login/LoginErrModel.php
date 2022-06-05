<?php

namespace App\Http\Models\Login;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;

class LoginErrModel extends Model
{
    protected $table = 'login_err';
    protected $guarded = [];

    public static function gainLog($user_id, $mobile, $err_code, $remark = '', $type = 0)
    {
        self::create([
            'user_id' => $user_id,
            'login_time' => CORE_TIME,
            'ip' => IP,
            'mobile' => isset($mobile) ? $mobile : '',
            'channel' => CHANNEL,
            'device' => DEVICE,
            'err_code' => $err_code,
            'remark' => $remark,
            'type' => $type,
        ]);
    }

    public static function getAdminPageLog($page = 1, $size = 20, $q = '', $type = 0)
    {
        $builder = self::where('type', $type)->orderBy('id', 'desc');
        if ($q != '') {
            $builder->where('user_id', $q);
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$logs->isEmpty()) {
            foreach ($logs as &$log) {
                if (stripos($log->mobile, '!!&c') !== false) {
                    $log->mobile = H::decrypt($log->mobile);
                }
            }
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
