<?php

namespace App\Console\Commands;

use App\Http\Models\Client\ClientLogModel;
use App\Http\Models\Logs\LogContactUnlockModel;
use App\Http\Models\Logs\LogRecommendModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\MessageModel;
use App\Http\Models\CommonModel;
use App\Http\Models\StatisticLogModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{R, HR};

class processDaily extends Command
{
    protected $signature = 'process:daily {type?}';
    protected $description = '每天只执行一次的定时任务';

    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        if (in_array($type, [0, 2])) {
            $this->_delSmsLog();
        }
    }

    private function _delSmsLog()
    {
        LogSmsModel::where('created_at', '<', date('Y-m-d H:i:s', strtotime('-7 day')))->delete();
        //静态日志也只保留14天
        StatisticLogModel::where('created_at', '<', date('Y-m-d H:i:s', strtotime('-7 day')))->delete();
        //删除推荐日志
        LogRecommendModel::where('created_at', '<', date('Y-m-d H:i:s', strtotime('-1 day')))->delete();
        //删除uv 日报
        ClientLogModel::where('created_at', '<', date('Y-m-d H:i:s', strtotime('-2 day')))->delete();
        //删除解锁记录，实现两天后已经解锁的联系方式不能被查看功能
        LogContactUnlockModel::where([['channel', 0], ['date', '<=', date('Y-m-d H:i:s', strtotime('-2 day'))]])->delete();
    }

}
