<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LibAvatarModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_avatar';
    protected $hidden = ['created_at', 'updated_at'];
}
