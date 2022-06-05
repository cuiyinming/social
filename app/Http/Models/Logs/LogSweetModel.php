<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Models\JpushModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Database\Eloquent\Model;


class LogSweetModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_sweet';

}
