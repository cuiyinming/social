<?php

namespace App\Http\Models\Payment\Callback;

use Illuminate\Database\Eloquent\Model;

class CallbackWechatModel extends Model
{

    protected $guarded = [];
    protected $table = 'callback_wechat';

    /**
     * 微信充值回调
     * @param array $requestData
     */
    public static function getWechatCallBack(array $request)
    {
        return self::firstOrCreate([
            "out_trade_no" => $request['out_trade_no'],
            "type" => 'wechat',
        ],
            [
                "appid" => $request['appid'],
                "bank_type" => $request['bank_type'],
                "cash_fee" => $request['cash_fee'],
                "fee_type" => $request['fee_type'],
                "is_subscribe" => $request['is_subscribe'],
                "mch_id" => $request['mch_id'],
                "nonce_str" => $request['nonce_str'],
                "openid" => $request['openid'],
                "result_code" => $request['result_code'],
                "return_code" => $request['return_code'],
                "sign" => $request['sign'],
                "time_end" => $request['time_end'],
                "total_fee" => $request['total_fee'],
                "trade_type" => $request['trade_type'],
                "transaction_id" => $request['transaction_id'],
                'ip' => IP,
                'notify_time' => CORE_TIME,
            ]);
    }

    public static function getAdminPageItems($page, $size, $q, $date)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($date) && count($date) > 1) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        if (!is_null($q)) {
            $builder->where('nonce_str', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
