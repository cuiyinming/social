<?php

namespace App\Console\Commands;

use App\Http\Libraries\Sms\RongIm;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Logs\CronCloseModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogImCallModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\SubscribeModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Helpers\{H, HR, R};
use App\Http\Models\Users\UsersModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class processHalfHour extends Command
{
    protected $signature = 'process:half {type?}';
    protected $description = '每半个小时执行一次的定时任务';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        //每分钟更新在线及活跃时间
        if (in_array($type, [0, 1])) {
            $this->_inviteSync();
        }
    }

    //每半个小时同步一次邀请数据
    private function _inviteSync()
    {
        $users = UsersModel::select(DB::raw('count(*) as total, invited'))->where('invited', '>', 0)->groupBy('invited')->get();
        if (!$users->isEmpty()) {
            foreach ($users as $user) {
                UsersModel::where('uinvite_code', $user->invited)->update(['invited_num' => $user->total]);
            }
        }
    }
}
