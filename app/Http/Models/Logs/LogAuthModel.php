<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LogAuthModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_auth';

    public static function getAdminPageAction($page, $size, $status, $q, $date, $id)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($date) && count($date) > 1) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        if (!is_null($id) && $id > 0) {
            $builder->where('user_id', $id);
            $builder->where('user_id', $id);
        }
        if (!is_null($q)) {
            if (is_numeric($q)) {
                $builder->where('user_id', $q);
            } else {
                $builder->where('name', 'like', '%' . $q . '%');
            }
        }
        if (!is_null($status)) {
            $builder->where('status', $status);
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($logs) {
            foreach ($logs as &$log) {
                $log->mobile = H::decrypt($log->mobile);
                $log->idcard = H::decrypt($log->idcard);
                try {
                    $log->sex = substr($log->idcard, 17, 1) % 2 == 0; //奇男偶女
                } catch (\Exception $e) {

                }
                $log->user_sex = $log->user_sex == 1 ? '女' : '男';
            }
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
