<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LibBannersModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_banners';
    protected $hidden = ['updated_at'];

    public static function getAdminPageItems($page, $size, $q, $position)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($position)) {
            $builder->where('position', $position);
        }
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('title', 'like', '%' . $q . '%')->orWhere('cont', 'like', '%' . $q . '%');
            });
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        foreach ($logs as &$log) {
            $log->status = $log->status == 1;
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
