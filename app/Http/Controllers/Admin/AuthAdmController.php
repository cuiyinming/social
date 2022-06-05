<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Models\MessageModel;
use JWTAuth;

class AuthAdmController extends Controller
{

    protected $user = null;
    protected $uid = 0;
    protected $role = 'manager';

    public function __construct()
    {
        try {
            $this->user = auth()->guard('admin')->userOrFail();
            $this->user->role = 'manager';
            $this->uid = $this->user->id;
        } catch (\Exception $e) {
            MessageModel::gainLog($e,__FILE__, __LINE__);
        }
    }

}
