<?php

namespace App\Jobs;

use App\Http\Libraries\Sms\RongIm;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogImCallModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersRewardModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

//语音房间处理剔除下线并奖励
class kickUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $roomId;

    public function __construct($roomId)
    {
        $this->roomId = $roomId;
    }


    public function handle()
    {
        try {
            $log = LogImCallModel::where([['status', 0], ['room_id', $this->roomId]])->first();
            if ($log) {
                LogImCallModel::kickUserCall($log);
                //房间销毁后触发奖励
                UsersRewardModel::userDailyRewardSet($log->call_inviter, 'yuyintonghua');
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e,__FILE__, __LINE__);
        }
    }
}
