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

class LogGiftReceiveModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_gift_receive';

    public static function gainLog($user_id, $gift, $num = 1)
    {
        $giftReceive = self::where([['user_id', $user_id], ['gift_id', $gift->gift_id]])->first();
        if ($giftReceive) {
            $giftReceive->increment('num');
        } else {
            self::create([
                'user_id' => $user_id,
                'gift_id' => $gift->gift_id,
                'gift_name' => $gift->name,
                'type_id' => $gift->type_id,
                'type_name' => $gift->type_name,
                'num' => $num
            ]);
        }
    }

    //获取指定的人获取的礼物列表
    public static function getReceivedGiftByUserId($user_id)
    {
        $res = [];
        $gifts = self::select(['gift_id', 'gift_name', 'num', 'type_id', 'type_name'])->where([['status', 1], ['user_id', $user_id]])->get();
        if (!$gifts->isEmpty()) {
            foreach ($gifts as $gift) {
                $res[] = [
                    'gift_id' => $gift->gift_id,
                    'gift_name' => $gift->gift_name,
                    'num' => $gift->num,
                    'type_id' => $gift->type_id,
                    'type_name' => $gift->type_name,
                    'path' => H::path(str_replace('gift', 'gift/', $gift->gift_id) . '.png'),
                ];
            }
        }
        return $res;
    }
}
