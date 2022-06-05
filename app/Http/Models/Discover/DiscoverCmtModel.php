<?php

namespace App\Http\Models\Discover;

use App\Http\Helpers\R;
use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;

class DiscoverCmtModel extends Model
{
    protected $guarded = [];
    protected $table = 'discover_cmt';
    protected $hidden = ['updated_at', 'sign'];

    public static function getDataByPage($page, $size, $status, $q, $itemid, $id = 0)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($itemid)) {
            $orders->where('discover_id', $itemid);
        }
        if (!is_null($status)) {
            $orders->where('status', $status);
        }
        if (!is_null($id) && $id > 0) {
            $orders->where('id', $id);
        }
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('item_id', $q);
                } else {
                    $query->where('comment', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {
                //渲染逻辑
            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
