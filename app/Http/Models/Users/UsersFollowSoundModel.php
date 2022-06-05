<?php

namespace App\Http\Models\Users;

use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class UsersFollowSoundModel extends Model
{

    protected $guarded = [];
    protected $table = 'user_follow_sound';
}
