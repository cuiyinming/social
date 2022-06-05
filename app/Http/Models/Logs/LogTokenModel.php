<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LogTokenModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_token';
}
