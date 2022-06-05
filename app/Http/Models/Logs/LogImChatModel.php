<?php

namespace App\Http\Models\Logs;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;

class LogImChatModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_im_chat';

    public static function getAdminPageAction($page, $size, $err, $q, $date)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($date) && count($date) > 1) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        if (!is_null($q)) {
            if (is_numeric($q)) {
                $builder->where('user_id', $q)->orWhere('target_user_id', $q);
            } else if (stripos($q, '-') !== false) {
                $qArr = explode('-', $q);
                $builder->where([['user_id', $qArr[0]], ['target_user_id', $qArr[1]]])->orWhere([['user_id', $qArr[1]], ['target_user_id', $qArr[0]]]);
            } else {
                $builder->where('cont', 'like', '%' . $q . '%');
            }
        }
        if (!is_null($err)) {
            $builder->where('err', $err);
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($logs) {
            foreach ($logs as &$log) {
                $log->isImg = stripos($log->cont, 'rongcloud-image') !== false || stripos($log->cont, 'rongcloud-picture') !== false;
            }
        }
        return [
            'count' => $count,
            'items' => $logs ?: []
        ];
    }
}
