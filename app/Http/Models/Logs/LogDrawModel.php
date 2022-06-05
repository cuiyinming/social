<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\R;
use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;

class LogDrawModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_draw';

    public static function getDataByPage($page, $size, $q, $status): array
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($status)) {
            $orders->where('status', $status);
        }
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('user_id', $q)->orWhere('order_sn', $q);
                } else {
                    $query->where('account', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {
                $data->channel = 'alipay';
            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
