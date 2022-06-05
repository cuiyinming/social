<?php

namespace App\Http\Models\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class AdminModel extends Authenticatable implements JWTSubject
{
    protected $table = 'admins';
    protected $guarded = [];
    use Notifiable;

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'safepassword'
    ];

    #获取列表信息
    public static function getUserInfo($id = 0)
    {
        return self::find($id);
    }

    /**
     * @param $uid
     * 获取用户资料完成度
     */
    public static function infoComplete($uid)
    {

    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return ['role' => 'admin'];
    }


    public static function getPageAdminItems($page = 1, $size = 20, $status = '', $q = '', $uid = 0): array
    {
        $builder = self::where('delete', 0)->orderBy('id', 'desc');
        if ($status != '') {
            $builder->where('status', $status);
        }
        if ($q != '') {
            $builder->where('username', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        $roleArr = [];
        $rolelists = AdmRoleListModel::get();
        if (!$rolelists->isEmpty()) {
            foreach ($rolelists as $rolelist) {
                $roleArr[$rolelist->id] = $rolelist->name;
            }
        }
        if ($items) {
            foreach ($items as $key => $item) {
                $items[$key]['username'] = $item['id'] == $uid ? $item['username'] : '********';
                $items[$key]['status'] = $item['status'] == 1;
                $items[$key]['supper'] = $item['supper'] == 1;
                $role_id = 0;
                $rolesUser = AdmRoleUserModel::where('user_id', $item->id)->first();
                if ($rolesUser) {
                    $role_id = $rolesUser->role_id;
                }
                $items[$key]['role_name'] = $roleArr[$role_id] ?? '';
            }
        }
        return [
            'count' => $count,
            'items' => $items ?: []
        ];
    }


}
