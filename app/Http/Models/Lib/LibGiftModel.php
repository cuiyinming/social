<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LibGiftModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_gift';
    protected $hidden = ['updated_at'];

    public static function getGift($type = 'list')
    {
        $key = 'gift_' . $type;
        $res = R::gredis($key);
        if (empty($res)) {
            $res = [];
            if ($type == 'list') {
                $gifts = LibGiftModel::orderBy('price', 'asc')->whereIn('type_id', [4, 3])->where('status', 1)->get();
            } else {
                $gifts = LibGiftModel::orderBy('price', 'asc')->whereNotIn('type_id', [3, 4, 100])->where('status', 1)->get();
            }
            foreach ($gifts as $gift) {
                if ($gift->type_id == 3) {
                    $gift->type_name = '专属';
                }
                $res[$gift->type_id]['type_id'] = $gift->type_id;
                $res[$gift->type_id]['name'] = $gift->type_name;
                $res[$gift->type_id]['gifts'][] = [
                    'gift_id' => $gift->gift_id,
                    'name' => $gift->name,
                    'tips' => $gift->tips,
                    'path' => H::path($gift->path),
                    'price' => $gift->price,
                ];
            }
            array_multisort(array_column($res, 'type_id'), SORT_DESC, $res);
            $res = array_values($res);
            R::sredis($res, $key, 86400 * 90);
        }
        return $res;
    }

    public static function getAdminPageItems($page, $size, $q, $type, $jifen)
    {
        $map = self::getTypeMap();
        $builder = self::orderBy('id', 'desc');
        if (!is_null($type)) {
            $builder->where('type_id', $type);
        }
        if (!is_null($jifen)) {
            $jifen > 0 ? $builder->where('jifen', '>', 0) : $builder->where('jifen', 0);
        }
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%');
            });
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        foreach ($logs as &$log) {
            $log->status = $log->status == 1;
            $log->path = H::path($log->path);
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : [],
            'map' => $map,
        ];
    }

    public static function getTypeMap()
    {
        $map = self::select(DB::Raw('count(*) as num,`type_id`,`type_name`'))->groupBy('type_id', 'type_name')->get();
        return $map->isEmpty() ? [] : $map->toArray();
    }
}
