<?php

namespace App\Http\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ActiveLogModel extends Model
{
    protected $table = 'active_log';
    protected $guarded = [];

    public static function getPageItems($page = 1, $size = 20, $uid = 0, $type = 0)
    {
        $builder = self::where([['uid', $uid], ['type', $type]])->orderBy('id', 'desc');
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

    public static function getPageAdminItems($page = 1, $size = 20, $q = 0, $type = 0)
    {
        $builder = self::where('type', $type)->orderBy('id', 'desc');
        if ($q != '') {
            $builder->where('uid', $q);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }


}
