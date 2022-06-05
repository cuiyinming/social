<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LibChatModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_chat';
    protected $hidden = ['created_at', 'updated_at'];

    public static function getAdminPageItems($page, $size, $q, $type, $sex, $period,$var)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($period)) {
            $builder->where('period', $period);
        }
        if (!is_null($type)) {
            $builder->where('type', $type);
        }
        if (!is_null($var)) {
            $builder->where('var', $var);
        }
        if (!is_null($sex)) {
            $builder->where('sex', $sex);
        }
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('user_id', 'like', '%' . $q . '%')->orWhere('advice', 'like', '%' . $q . '%');
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
