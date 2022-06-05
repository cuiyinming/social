<?php

namespace App\Http\Controllers;

use App\Http\Models\MessageModel;
use JWTAuth;
use App\Http\Helpers\{H, HR};

class AuthController extends Controller
{

    protected $user = null;
    protected $uid = 0;
    protected $sex = 0;
    protected $sweet_coin = 0;
    protected $role = 'users';

    public function __construct()
    {
        try {
            $this->user = JWTAuth::parseToken()->authenticate();
            $this->uid = $this->user->id;
            $this->sweet_coin = $this->user->sweet_coin;
            $this->sex = $this->user->sex == 0 ? 1 : $this->user->sex;
            unset($this->user->salt);
            unset($this->user->id);
            if ($this->uid > 0) {
                //使用redis 记录用户的最后一次活动时间
                HR::updateActiveTime($this->uid);
                HR::updateActiveCoordinate($this->uid);
            }
            $this->role = 'users';
        } catch (\Exception $e) {
//            MessageModel::gainLog($e,__FILE__, __LINE__);
        }
    }
}
