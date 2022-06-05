<?php

namespace App\Http\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdmRoleModel extends Model
{
    protected $table = 'adm_role';
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

    public static function getRoleRightByUserId($uid): bool
    {
        $roleUser = AdmRoleUserModel::where('user_id', $uid)->first();
        if (!$roleUser) return false;
        $roleStr = AdmRoleListModel::where('id', $roleUser->role_id)->first();
        if (empty($roleStr)) return false;
        $roleStr = $roleStr->str;
        if (in_array($roleStr, ['admin'])) {
            return true;
        } else {
            return false;
        }
    }
}
