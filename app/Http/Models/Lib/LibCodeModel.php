<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\H;
use Illuminate\Database\Eloquent\Model;

class  LibCodeModel extends Model
{

    protected $guarded = [];
    protected $table = 'lib_code';

    public static function getPageAdminItems($page, $size, $q)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder->where('code', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

    public static function gain($level)
    {
        $code = H::randstr(12);
        self::updateOrCreate([
            'code' => $code,
            'vip_level' => $level
        ], [
            'code' => $code,
            'vip_level' => $level
        ]);
    }
}
