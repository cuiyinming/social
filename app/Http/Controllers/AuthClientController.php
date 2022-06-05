<?php

namespace App\Http\Controllers;

class AuthClientController extends Controller
{

    protected $user = null;
    protected $uid = 0;
    protected $invite_code = 0;
    protected $role = 'client';

    public function __construct()
    {
        try {
            $this->user = $user = auth()->guard('client')->userOrFail();
            if ($this->user == null) {
                return $this->jsonExit(406, '登录信息失效');
            }
            $this->user->role = 'client';
        } catch (\Exception $e) {
            return $this->jsonExit(403, '系统错误');
        }
        $this->uid = $this->user->id;
        $this->invite_code = $this->user->invite_code;
    }
}
