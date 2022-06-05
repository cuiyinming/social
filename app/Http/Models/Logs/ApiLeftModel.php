<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;

class ApiLeftModel extends Model
{

    protected $guarded = [];
    protected $table = 'api_left';
}
