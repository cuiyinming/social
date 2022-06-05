<?php

namespace App\Http\Models\Client;

use Illuminate\Database\Eloquent\Model;

class ClientDrawModel extends Model
{
    protected $guarded = [];
    protected $table = 'client_draw';

    public static function getLastRow($uid)
    {
        return self::where('user_id', $uid)->orderBy('id', 'desc')->first();
    }


    public static function getPageRow($uid, $page = 1, $size = 20)
    {
        $count = self::count();
        $data = self::where('user_id', $uid)->orderBy('id', 'desc')->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'invoices' => $data,
        ];
    }

    public static function getRow($id)
    {
        return self::find($id);
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
                    $query->where('account', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            $userArr = $qrs = [];
            foreach ($datas as $val) {
                $userArr[] = $val->user_id;
            }
            $accounts = ClientUsersModel::whereIn('id', $userArr)->get();
            if (!$accounts->isEmpty()) {
                foreach ($accounts as $account) {
                    $qrs[$account->id] = [
                        'wechat_qr' => $account->wechat_qr,
                        'alipay_qr' => $account->alipay_qr,
                    ];
                }
            }
            foreach ($datas as &$data) {
                $channel = '';
                if ($data->account == 'alipay_qr') $channel = '支付宝';
                if ($data->account == 'wechat_qr') $channel = '微信';
                $data->channel = $channel;
                $data->account = isset($qrs[$data->user_id][$data->account]) ? $qrs[$data->user_id][$data->account] : '';
            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
