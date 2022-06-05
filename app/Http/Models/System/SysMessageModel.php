<?php

namespace App\Http\Models\System;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Logs\LogTokenModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RongCloud;
use JWTAuth;


class SysMessageModel extends Model
{
    protected $guarded = [];
    protected $table = 'message_sys';

    public static function getOneUnread($id = 0)
    {
        return self::find($id);
    }

    public static function getAdminPageItems($page, $size, $q, $type, $msg_type)
    {
        $builder = self::where('delete', 0)->orderBy('id', 'desc');
        if ($type != 100) {
            $builder->where('type', $type);
        }
        if (!is_null($msg_type)) {
            $builder->where('msg_type', $msg_type);
        }
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('title', 'like', '%' . $q . '%')->orWhere('cont', 'like', '%' . $q . '%');
            });
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }

}
