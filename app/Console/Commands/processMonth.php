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

class processMonth extends Command
{
    protected $signature = 'process:month {type?}';
    protected $description = '每月执行一次的定时任务';


    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        //每分钟更新在线及活跃时间
        if (in_array($type, [0, 1])) {
            $this->_superShow();
        }
    }

    //没对给vip 会员赠送超级曝光
    private function _superShow()
    {
        $vips = UsersProfileModel::where('vip_is', 1)->get();
        if (!$vips->isEmpty()) {
            foreach ($vips as $vip) {
                $sub = SubscribeModel::getRightTimes($vip->vip_level);
                $super_time = $sub['super_show'];
                UsersModel::where('id', $vip->user_id)->update(['super_show_left' => $super_time]);
            }

        }
    }
}
