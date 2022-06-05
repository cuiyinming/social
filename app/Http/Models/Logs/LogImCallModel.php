<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\RongIm;
use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;

class LogImCallModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_im_call';

    public static function kickUserCall($log)
    {
        $info = [
            'userId' => $log->call_inviter,
            'roomId' => $log->room_id,
        ];
        $kick = (new RongIm())->kickUser($info);
        if ($kick) {
            $log->call_end_at = date('Y-m-d H:i:s');
            $log->status = 1;
            $log->end_type = 0;
            $log->save();
            //强制下线扣费---此处一定是扣费逻辑 结束扣金币
            $call_price = config('settings.im_call_price');  //单位是分钟价格
            $minute = ceil($log->call_duration / 60);
            $change = $call_price * $minute;
            $desc = '语音通话 ' . $minute . '分钟，花费' . $change . '友币';
            $remark = '与用户 ' . $log->call_invitee . ' 语音通话 ' . $minute . '分钟，花费' . $change . '友币';
            $user = UsersModel::where('id', $log->call_inviter)->first();
            if (!$user) {
                throw new \Exception('语音剔除用户' . $log->call_inviter . '不存在');
            }
            $before = $user->sweet_coin;
            if ($before > 0) {
                $after = $before - $change;
                if ($after <= 0) $after = 0;
                $user->sweet_coin = $after;
                $user->save();
                LogBalanceModel::gainLogBalance($log->call_inviter, $before, $change, $after, 'im_call', $desc, $remark);
            }
        }
    }
}
