<?php

namespace App\Http\Models\Discover;

use App\Http\Helpers\R;
use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;

class DiscoverZanCmtModel extends Model
{
    protected $guarded = [];
    protected $table = 'discover_zan_cmt';

    public static function getZanDiscover($user_id, array $idArr)
    {
        $builder = self::where([['user_id', $user_id], ['status', 1]])->whereIn('cmt_id', $idArr)->pluck('cmt_id');
        if ($builder) {
            return $builder->toArray();
        }
        return [];
    }


    public static function getDataByPage($page, $size, $itemid, $status)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($itemid)) {
            $orders->where('discover_id', $itemid)->orWhere('user_id', $itemid)->orWhere('discover_user_id', $itemid);
        }
        if (!is_null($status)) {
            $orders->where('status', $status);
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
