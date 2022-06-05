<?php

namespace App\Http\Models\Client;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use App\Http\Models\Model;

class ClientProfitModel extends Model
{
    protected $guarded = [];
    protected $table = 'client_profit';

    //添加变动记录
    public static function gainLogProfit($uid, $amount, $origin_amount, $type, $desc, $remark, $admin = 0, $order_sn = null)
    {
        $inviteUserInfo = [
            'user_id' => $uid,
            'amount' => $amount,
            'origin_amount' => $origin_amount,
            'order_sn' => is_null($order_sn) ? H::genOrderSn(5) : $order_sn,
            'desc' => $desc,
            'type' => $type,
            'adm_id' => $admin,
            'status' => 0,
            'checked_at' => null,
            'remark' => $remark,
            'created_at' => CORE_TIME,
            'updated_at' => CORE_TIME
        ];
        self::insert($inviteUserInfo);
    }

    public static function getPageItems($page = 1, $size = 20, $dates = [], $type = '', $q = '', $uid = 0): array
    {
        $builder = self::where('user_id', $uid)->orderBy('id', 'desc');
        if ($q !== '' && !is_null($q)) {
            $builder->where('order_sn', 'like', '%' . $q . '%');
        }
        if ($type != '' && !is_null($type)) {
            $builder->where('type', $type);
        }
        if (!empty($dates)) {
            $builder->whereBetween('created_at', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($items) {
            //渲染
            foreach ($items as &$item) {
                $item->type_name = $item->type == 'inner' ? '内购' : ($item->type == 'client_profit' ? '代理分润' : '订阅');
                $item->status_name = $item->status == 0 ? '待结算' : ($item->status == 1 ? '已结算' : '结算失败');
                $item->remark = empty($item->remark) ? '结算状态' : $item->remark;
                $item->checked_at = empty($item->checked_at) ? '---' : $item->checked_at;
            }
        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }


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
                    $query->where('desc', 'like', '%' . $q . '%')->orWhere('remark', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $items = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$items->isEmpty()) {
            foreach ($items as &$item) {
                $item->type_name = $item->type == 'inner' ? '内购' : ($item->type == 'client_profit' ? '代理分润' : '订阅');
                $item->status_name = $item->status == 0 ? '待结算' : ($item->status == 1 ? '已结算' : '结算失败');
                $item->remark = empty($item->remark) ? '结算状态' : $item->remark;
                $item->checked_at = empty($item->checked_at) ? '---' : $item->checked_at;
            }
        }
        return [
            'items' => $items ? $items : [],
            'count' => $count,
        ];
    }

}
