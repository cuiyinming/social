<?php

namespace App\Http\Models\Client;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use App\Http\Models\Model;

class ClientBalanceModel extends Model
{
    protected $guarded = [];
    protected $table = 'client_balance';

    public static function getPageItems($page = 1, $size = 20, $dates = [], $type = '', $operate = '', $q = '', $uid = 0): array
    {
        $builder = self::where('user_id', $uid)->orderBy('id', 'desc');
        if ($q !== '' && !is_null($q)) {
            $builder->where('order_sn', 'like', '%' . $q . '%');
        }
        if ($type != '' && !is_null($type)) {
            $builder->where('type', $type);
        }
        if ($operate != '' && !is_null($operate)) {
            $builder->where('operate', $operate);
        }
        if (!empty($dates)) {
            $builder->whereBetween('created_at', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($items) {
            //渲染
            foreach ($items as &$item) {
                $item->type_name = $item->type == 'draw' ? '提现' : '结算';
            }
        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

    public static function getPageAdminItems($table, $page, $size, $dates, $type, $operate, $q)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder->where('order_sn', 'like', '%' . $q . '%')->orWhere('user_id', $q);
        }
        if (!is_null($type)) {
            $builder->where('type', $type);
        }
        if (!is_null($operate)) {
            $builder->where('operate', $operate);
        }
        if (!empty($dates)) {
            $builder->whereBetween('created_at', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($items) {
            //渲染银行
            foreach ($items as &$item) {
                $item->type_name = '';
            }
        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }
}
