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

class LogSoundLikeModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_sound_like';


    public static function getSoundLikeIdArr($uid, $status = 1): array
    {
        $blackArr = [];
        $pluck = self::where([['user_id', $uid], ['status', $status]])->pluck('sound_user_id');
        if ($pluck) {
            $blackArr = $pluck->toArray();
        }
        return $blackArr;
    }

    //给语音点赞
    public static function saveSoundLike($uid, $albums)
    {
        foreach ((array)$albums as $i => $item) {
            $baseInfo = json_decode($item, 1);
            $status = $baseInfo['status'];
            $user_id = $baseInfo['user_id'];
            $exist = self::where([['user_id', $uid], ['status', $status], ['sound_user_id', $user_id]])->first();
            if ($exist) continue;
            try {
                //判断用户存不存在
                $userModel = UsersModel::find($user_id);
                if (!$userModel) {
                    throw new \Exception('该用户不存在');
                }
                //添加关注记录
                self::updateOrCreate([
                    'user_id' => $uid,
                    'sound_user_id' => $user_id,
                ], [
                    'user_id' => $uid,
                    'date' => date('Y-m-d'),
                    'sound_user_id' => $user_id,
                    'status' => $status
                ]);
                $like_count = self::where([['sound_user_id', $user_id], ['status', 1]])->count();
                //未读消息更新
                $opt = $status == 1 ? 1 : 0;
                UsersMsgNoticeModel::gainNoticeLog($user_id, 'sound_zan', 1, $opt);
                //更新被关注用户的关注人数
                UsersProfileModel::where('user_id', $user_id)->update(['sound_like' => $like_count]);
            } catch (\Exception $e) {
                MessageModel::gainLog($e,__FILE__, __LINE__);
                throw new \Exception($e->getMessage());
            }
        }
    }
}
