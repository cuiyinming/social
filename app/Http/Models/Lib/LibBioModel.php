<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LibBioModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_bio';
    protected $hidden = ['updated_at'];

    public static function getAdminPageItems($page, $size, $q)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('title', 'like', '%' . $q . '%')->orWhere('content', 'like', '%' . $q . '%');
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
