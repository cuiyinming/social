<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LIbNickSetModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_nick_set';
    protected $hidden = ['updated_at'];

    public static function getAdminPageItems($page, $size, $q, $sex)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($sex)) {
            $builder->where('gender', $sex);
        }
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('nick', 'like', '%' . $q . '%');
            });
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
