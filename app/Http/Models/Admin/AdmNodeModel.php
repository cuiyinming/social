<?php

namespace App\Http\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdmNodeModel extends Model
{
    protected $table = 'adm_node';
    protected $guarded = [];

    public static function getPageAdminItems($page = 1, $size = 20, $role = 0)
    {
        $builder = self::orderBy('sort_order', 'desc');
        if ($role > 0) {
            $nodeArr = AdmRoleModel::where('role_id', $role)->pluck('node_id');
            $builder->whereIn('id', $nodeArr);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

    //获取可选的节点
    public static function getAvilableNodes($role = 0)
    {
        $builder = self::orderBy('sort_order', 'desc');
        if ($role > 0) {
            $nodeArr = OpenAdmRoleModel::where('role_id', $role)->pluck('node_id');
            $builder->whereNotIn('id', $nodeArr);
        }
        $items = $builder->get();
        return [
            'items' => $items ? $items : []
        ];
    }
}
