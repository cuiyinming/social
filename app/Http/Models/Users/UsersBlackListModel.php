<?php

namespace App\Http\Models\Users;

use App\Http\Models\EsDataModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;

class UsersBlackListModel extends Model
{
    protected $guarded = [];
    protected $table = 'user_blacklist';

    public static function getBlackIdArr($uid, $status = 1): array
    {
        $redis = config('common.redis_cache');
        if ($redis) {
            if ($status == 0) throw new \Exception('status 传值错误');
            $blackArr = HR::getUserBlackList($uid);
        } else {
            $pluck = self::where([['user_id', $uid], ['status', $status]])->pluck('black_id');
            $blackArr = $pluck ? $pluck->toArray() : [];
        }
        return $blackArr;
    }


    public static function getBlackListPageData($uid, $page, $size)
    {
        $res = [];
        $builder = self::where([['user_id', $uid], ['status', 1]])->orderBy('updated_at', 'desc');
        $count = $builder->count();
        $blackArr = $builder->skip(($page - 1) * $size)->take($size)->pluck('black_id')->toArray();
        if (!empty($blackArr)) {
            $format = EsDataModel::mgetEsUserByIds(['ids' => $blackArr]);
            foreach ($blackArr as $black) {
                if (!isset($format[$black])) continue;
                $res[$black] = $format[$black];
            }
        }
        return [
            'items' => $res ? array_values($res) : [],
            'count' => $count,
        ];
    }


    public static function getDataByPage($page, $size, $q, $status)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('black_id', $q)->orWhere('user_id', $q);
                } else {
                    $query->where('product_id', 'like', '%' . $q . '%');
                }
            });
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
