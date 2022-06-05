<?php

namespace App\Http\Models;

use App\Http\Helpers\H;
use Illuminate\Database\Eloquent\Model;

class MessageModel extends Model
{
    protected $guarded = [];
    protected $table = 'message';

    public static function gainLog($e, $file = '', $line = 0, $ext = '')
    {
        $data = [
            'action' => $e->getFile(),
            'exception' => $e->getMessage() . $ext,
            'ip' => defined('IP') ? IP : '',
            'line' => $e->getLine(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'file_name' => $file,
            'file_line' => $line,
        ];
        self::create($data);
    }

    public static function getAdminPageAction($page, $size, $q, $date)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($date) && count($date) > 1) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        if (!is_null($q)) {
            $builder->where('action', 'like', '%' . $q . '%')->orWhere('file_name', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($logs) {
            foreach ($logs as &$log) {
                $log->action = str_replace('/www/wwwroot/api/moshi/', '', $log->action);
                $log->file_name = str_replace('/www/wwwroot/api/moshi/', '', $log->file_name);
            }
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
