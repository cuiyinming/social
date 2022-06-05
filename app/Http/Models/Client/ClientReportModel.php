<?php

namespace App\Http\Models\Client;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use App\Http\Models\Model;

class ClientReportModel extends Model
{
    protected $guarded = [];
    protected $table = 'client_report';

    public static function getPageItems($page = 1, $size = 20, $dates = [], $uid = 0): array
    {
        $builder = self::select(['user_id', 'date', 'pv', 'ip', 'register', 'register_client', 'vip_amount', 'vip_profit', 'recharge_amount', 'recharge_profit', 'client_profit', 'created_at'])->orderBy('id', 'desc');
        if ($uid > 0) {
            $builder->where('user_id', $uid);
        }
        if (!empty($dates)) {
            $builder->whereBetween('created_at', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
//        if (!$items->isEmpty()) {
//            渲染
//            foreach ($items as &$item) {
//            }
//        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }
}
