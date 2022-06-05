<?php

namespace App\Console\Commands;

use App\Http\Libraries\Sms\RongIm;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Logs\CronCloseModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogImCallModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Helpers\{H, HR, R};
use App\Http\Models\Users\UsersModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class processMinute extends Command
{
    protected $signature = 'process:min {type?}';
    protected $description = '每分钟执行一次的定时任务';

    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        //每分钟更新在线及活跃时间
        if (in_array($type, [0, 1])) {
            $this->_usersOnlineStatus();
        }
        if (in_array($type, [0, 2])) {
            $this->_cron();
        }
        //强制踢下线余额不足的语音通话用户
        if (in_array($type, [0, 3])) {
            $this->_kick();
        }
    }

    private function _usersOnlineStatus()
    {
        //更新用户在线状态
        $redis = R::connect('active');
        $users = $redis->keys('users-last-active-*');
        if ($users) {
            $onlieArr = $offArr = [];
            foreach ($users as $user) {
                $user_id = str_replace('users-last-active-', '', $user);
                $onlieArr[] = [
                    'id' => $user_id,
                    'online' => 1,
                ];
                $offArr[] = $user_id;
            }
            //处理下线的
            UsersModel::where('online', 1)->whereNotIn('id', $offArr)->update(['online' => 0]);
            UsersModel::batchIntoOnline($onlieArr);
        } else {
            //如果没就全部标记为下线
            UsersModel::where('online', 1)->update(['online' => 0]);
            DiscoverModel::where('online', 1)->update(['online' => 0]);
        }
    }

    // 处理用户的定时任务
    private function _cron($job = 'close_account')
    {
        $cron = CronCloseModel::where([['job_code', $job], ['status', 0], ['plan_time', '<', date('Y-m-d H:i:s')]])->get();
        if (!$cron->isEmpty()) {
            foreach ($cron as $item) {
                $item->start_at = date('Y-m-d H:i:s');
                $item->end_at = date('Y-m-d H:i:s');
                CronCloseModel::closeAccount($item->user_id);
                $item->status = 1;
                $item->save();
            }
        }
    }

    //把月不足依然在通话的用户踢下线
    private function _kick()
    {
        $logs = LogImCallModel::where([['status', 0], ['plan_end_at', '<=', date('Y-m-d H:i:s')]])->get();
        if (!$logs->isEmpty()) {
            foreach ($logs as $log) {
                try {
                    LogImCallModel::kickUserCall($log);
                } catch (\Exception $e) {
                    MessageModel::gainLog($e,__FILE__, __LINE__);
                }
            }
        }
    }

}
