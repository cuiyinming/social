<?php

namespace App\Http\Models\Logs;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;

class LogSendInviteModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_send_invite';
}
