<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;

class LogActionModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_action';


    public static function getAdminPageAction($page, $size, $q)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder->where('user_id', 'like', '%' . $q . '%')->orWhere('path', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($logs) {
            foreach ($logs as &$log) {
                $log->addr = H::Ip2City($log->ip);
            }
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
