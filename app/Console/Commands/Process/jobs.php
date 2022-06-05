<?php

namespace App\Console\Commands\Process;

use App\Http\Models\EsDataModel;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Models\JobsModel;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\CommonModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{R, HR};

class jobs extends Command
{

    protected $signature = 'jobs {type=0}';
    protected $description = '每分钟巡查未完成的任务';
    protected $user_id = 0;

    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        set_time_limit(0);
        $type = $this->argument('type');
        if (in_array($type, [0, 1])) $this->_handleProcess();
    }


    /*-------处理更新过期会员--------**/
    private function _handleProcess()
    {
        try {
            $s = microtime(1);
            echo '同步处理开始' . PHP_EOL;
            $jobs = JobsModel::getJobByCodeArr(['sync-sys-block', 'sync-sys-block-ip', 'sync-sys-block-mobile', 'sync-sys-block-device', 'sync-sys-hide-model', 'sync-user-black', 'sync-sys-tags']);
            if (!$jobs->isEmpty()) {
                foreach ($jobs as $job) {
                    $st = microtime(1);
                    echo date('Y-m-d H:i:s') . ' <=> ' . $job->job_code . ' 开始同步' . PHP_EOL;
                    $job->started_at = date('Y-m-d H:i:s');
                    JobsModel::syncItemInfo($job);
                    $job->ended_at = date('Y-m-d H:i:s');
                    $job->status = 1;
                    $job->save();
                    echo date('Y-m-d H:i:s') . ' <=> ' . $job->job_code . '开始结束，用时：' . round(microtime(1) - $st, 2) . ' S' . PHP_EOL;
                }
            }
            echo date('Y-m-d H:i:s') . ' <=>  全部结束，用时：' . round(microtime(1) - $s, 2) . ' S' . PHP_EOL;
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
