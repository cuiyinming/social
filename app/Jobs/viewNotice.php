<?php

namespace App\Jobs;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogBrowseModel;
use App\Http\Models\Logs\LogImChatModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RongCloud;

//用户被浏览添加浏览记录及激光推送等信息
class viewNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user_id;  //被浏览的人
    protected $uid;  //浏览的人

    public function __construct($user_id, $uid)
    {
        $this->user_id = $user_id;
        $this->uid = $uid;
    }


    public function handle()
    {
        try {
            $selfSetting = UsersSettingsModel::getUserSettings($this->uid);
            $userSettingModel = UsersSettingsModel::getUserSettings($this->user_id);
            if ($selfSetting['hide_browse'] == 0) {
                //添加对方的被浏览数
                HR::updateUniqueNum($this->user_id, $this->uid, 'users-be-viewed', false);
                $logBrowse = LogBrowseModel::where([['user_id', $this->uid], ['user_id_viewed', $this->user_id]])->first();
                if ($logBrowse) {
                    $logBrowse->date = date('Y-m-d H:i:s');
                    $logBrowse->num += 1;
                    $logBrowse->save();
                } else {
                    LogBrowseModel::create([
                        'user_id' => $this->uid,
                        'user_id_viewed' => $this->user_id,
                        'num' => 1,
                        'date' => date('Y-m-d H:i:s'),
                    ]);
                    //极光推送
                    if ($userSettingModel['hide_browse_push'] == 0) {
                        $user = UsersModel::getUserInfo($this->uid);
                        if ($user) {
                            $nick = $user->nick;
                            JpushModel::JpushCheck($this->user_id, $nick, 0, 2, $this->uid);
                            //未读消息更新
                            UsersMsgNoticeModel::gainNoticeLog($this->user_id, 'browse_me');
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
