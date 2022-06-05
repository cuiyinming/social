<?php

namespace App\Http\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdmRoleUserModel extends Model
{
    protected $table = 'adm_role_user';
    protected $guarded = [];

    public static function getPageAdminItems($page = 1, $size = 20)
    {
        $builder = self::orderBy('id', 'desc');
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }
}
