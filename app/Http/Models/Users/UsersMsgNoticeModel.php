<?php

namespace App\Http\Models\Users;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;

class UsersMsgNoticeModel extends Model
{
    protected $guarded = [];
    protected $table = 'user_msg_notice';

    /**
     * 1加2减
     */
    public static function gainNoticeLog($user_id, $col = 'zan', $incr = 1, $opt = 1)
    {
        if (!in_array($col, ['zan', 'comment', 'love_me', 'me_love', 'browse_me', 'site_notice', 'sound_zan', ''])) {
            return false;
        }
        $logModel = self::where('user_id', $user_id)->first();
        if (!$logModel) {
            $logModel = self::create([
                'user_id' => $user_id,
                'zan' => $col == 'zan' ? $incr : 0,
                'comment' => $col == 'comment' ? $incr : 0,
                'love_me' => $col == 'love_me' ? $incr : 0,
                'me_love' => $col == 'me_love' ? $incr : 0,
                'browse_me' => $col == 'browse_me' ? $incr : 0,
                'sound_zan' => $col == 'sound_zan' ? $incr : 0,
                'site_notice' => $col == 'site_notice' ? $incr : 0,
            ]);
        } else {
            if (empty($col)) return false;
            if ($opt == 1) {
                $logModel->$col += $incr;
                if ($logModel->$col > 99) {
                    $logModel->$col = 99;
                }
            } else {
                $logModel->$col -= $incr;
                if ($logModel->$col < 0) {
                    $logModel->$col = 0;
                }
            }
            $logModel->save();
        }
        return $logModel;
    }

    public static function getCountInfo(int $uid): array
    {
        $logModel = self::where('user_id', $uid)->first();
        if (!$logModel) {
            $logModel = self::gainNoticeLog($uid, '');
        }
        // 0 不喜欢 1 喜欢
        $res = [
            'zan' => $logModel->zan,
            'comment' => $logModel->comment,
            'love_me' => $logModel->love_me,
            'me_love' => $logModel->me_love,
            'sound_zan' => $logModel->sound_zan,
            'site_notice' => $logModel->site_notice,
        ];
        $res['total'] = intval(array_sum($res));
        $res['browse_me'] = $logModel->browse_me;
        //追加我喜欢喜欢我和好友数量
        $followExt = UsersFollowModel::followInfoCounter($uid);
        $res = array_merge($res, $followExt);
        return $res;
    }
}
