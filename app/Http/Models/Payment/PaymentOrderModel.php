<?php

namespace App\Http\Models\Payment;

use Illuminate\Database\Eloquent\Model;

class PaymentOrderModel extends Model
{
    protected $guarded = [];
    protected $table = 'payment_order';


    public static function getDataByPage($page, $size, $q, $status)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($status)) {
            $orders->where('status', $status);
        }
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('user_id', $q)->orWhere('order_no', $q)->orWhere('log_sn', $q)->orWhere('relate_id', $q);
                } else {
                    $query->where('qr_str', 'like', '%' . $q . '%')->orWhere('body', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {

            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
