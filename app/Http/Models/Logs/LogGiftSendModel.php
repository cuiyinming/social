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

class LogGiftSendModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_gift_send';

    /*----此处的gift 是 model-----*/
    public static function gainLog($from_user_id, $to_user_id, $gift, $num = 1, $chat = '')
    {
        self::create([
            'user_id' => $from_user_id,
            'user_id_receive' => $to_user_id,
            'gift_id' => $gift->gift_id,
            'price' => intval($gift->price * $num),
            'gift_name' => $gift->name,
            'type_id' => $gift->type_id,
            'type_name' => $gift->type_name,
            'num' => $num,
            'chat' => $chat
        ]);
    }
}
