<?php

namespace App\Http\Models\System;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;

class FeedbackModel extends Model
{

    protected $casts = [
        'shoots' => 'json', // 声明json类型
    ];
    protected $guarded = [];
    protected $table = 'feedback';

    public static function getDataByPage($page, $size, $q, $cate)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('type_id', $q)->orWhere('user_id', $q);
                } else {
                    $query->where('cont', 'like', '%' . $q . '%');
                }
            });
        }
        if (!is_null($cate)) {
            $orders->where('cate', $cate);
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
