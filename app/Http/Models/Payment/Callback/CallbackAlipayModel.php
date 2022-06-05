<?php

namespace App\Http\Models\Payment\Callback;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;

class CallbackAlipayModel extends Model
{

    protected $guarded = [];
    protected $table = 'callback_alipay';

    public static function getAlipayCallBack(array $request)
    {
        return self::firstOrCreate([
            "out_trade_no" => $request['out_trade_no'],
            "type" => 'alipay',
        ], [
            "gmt_create" => $request['gmt_create'],
            "charset" => $request['charset'],
            "gmt_payment" => $request['gmt_payment'] ?? '',
            "notify_time" => $request['notify_time'],
            "subject" => $request['subject'],
            "buyer_id" => $request['buyer_id'],
            "invoice_amount" => $request['invoice_amount'] ?? 0,
            "version" => $request['version'] ?? 0,
            "body" => $request['body'] ?? '',
            "notify_id" => $request['notify_id'],
            "fund_bill_list" => json_encode($request['fund_bill_list']),
            "notify_type" => $request['notify_type'],
            "total_amount" => $request['total_amount'],
            "trade_status" => $request['trade_status'],
            "trade_no" => $request['trade_no'],
            "auth_app_id" => $request['auth_app_id'],
            "receipt_amount" => $request['receipt_amount'],
            "point_amount" => $request['point_amount'] ?? 0,
            "app_id" => $request['app_id'],
            "buyer_pay_amount" => $request['buyer_pay_amount'] ?? 0,
            "seller_id" => $request['seller_id'] ?? 0,
        ]);
    }


    public static function getAdminPageItems($page, $size, $q, $date)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($date) && count($date) > 1) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        if (!is_null($q)) {
            $builder->where('body', 'like', '%' . $q . '%')->orWhere('out_trade_no', 'like', '%' . $q . '%')->orWhere('trade_no', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $logs ?: []
        ];
    }
}
