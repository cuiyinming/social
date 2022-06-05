<?php

namespace App\Http\Models\Payment;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;

class OrderModel extends Model
{

    protected $guarded = [];
    protected $table = 'order';

    public static function getDataByPage($page, $size, $q, $status, $type, $order)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($status)) {
            $orders->where('status', $status);
        }
        if (!is_null($type)) {
            $orders->where('type', $type);
        }
        if (!is_null($order) && $order > 0) {
            $orders->where('original_transaction_id', $order)->orWhere('transaction_id', $order);
        }
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('user_id', $q)->orWhere('transaction_id', $q)->orWhere('original_transaction_id', $q);
                } else {
                    $query->where('receipt', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {
                switch ($data->create_type) {
                    case 0:
                        $data->create_type_name = '前台';
                        $data->create_type_color = 'green';
                        break;
                    case 1:
                        $data->create_type_name = '异步';
                        $data->create_type_color = 'yellow';
                        break;
                    default:
                        $data->create_type_name = '补登';
                        $data->create_type_color = 'red';
                }
            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
