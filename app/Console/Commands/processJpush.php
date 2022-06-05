<?php

namespace App\Console\Commands;

use App\Components\ESearch\ESearch;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Models\CommonModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogSignModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{R, HR, H};

class processJpush extends Command
{
    protected $signature = 'process:jpush {type?}';
    protected $description = '定时进行极光推送,每分钟执行';


    public function __construct()
    {
        parent::__construct();
        if (!defined('CORE_TIME')) {
            define('CORE_TIME', date('Y-m-d H:i:s'));
        }
    }


    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        //对打开了签到提醒的用户推送通知 20:10 或者 11:10
        if (in_array($type, [0, 4])) {
            if (date('H') == 11 && date('i') == 40) {
                $this->_signNotice();
            }
        }
        if (in_array($type, [0, 1])) {
            //每日定时批量
            $this->_jpushDaily();
        }
        if (in_array($type, [0, 2])) {
            //定向推送 [早十点到晚上12点之间,整点推送]
            if (date('H') >= 10 && date('H') <= 24 && date('H') % 5 == 0 && date('i') == '01') {
                //$this->_targetedPush();
            }
        }
    }


    //批量推
    private function _jpushDaily()
    {
        CommonModel::JPushBatch();
    }

    //签到提醒
    private function _signNotice()
    {
        $users = UsersSettingsModel::where('sign_remind', 1)->pluck('user_id')->toArray();
        if ($users) {
            foreach ($users as $user) {
                $sign = LogSignModel::where([['user_id', $user], ['last_date', date('Y-m-d')]])->first();
                if (!$sign) {
                    JpushModel::JpushCheck($user, '', 0, 22, 0);
                }
            }
        }
    }

    //定向推
    private function _targetedPush()
    {
        //一到两天及以上不打开App，则会在早10点至晚12点之间每隔两个小时推送一条激活唤醒通知*（诱导点击类）
        $startDate = date('Y-m-d H:i:s', time() - 86400 * 1);
        $endDate = date('Y-m-d H:i:s', time() - 86400 * 5);
        $userIdArrs = UsersModel::where([['status', 1], ['live_time_latest', '>=', $endDate], ['live_time_latest', '<=', $startDate]])->pluck('id')->toArray();
        if (count($userIdArrs) > 0) {
            foreach ($userIdArrs as $userIdArr) {
                CommonModel::JPushNoticeNewVersion($userIdArr);
            }
        }

        //定向短信
        $startDate = date('Y-m-d H:i:s', time() - 86400 * 6);
        $endDate = date('Y-m-d H:i:s', time() - 86400 * 10);
        $userIdArrs = UsersModel::where([['status', 1], ['live_time_latest', '>=', $endDate], ['live_time_latest', '<=', $startDate]])->pluck('id')->toArray();
        if (count($userIdArrs) > 0) {
            foreach ($userIdArrs as $user) {
                $userModel = UsersModel::where('id', $user->id)->first();
                LogSmsModel::sendMsg(H::decrypt($userModel->mobile), 'notice');
            }
        }
    }
}
