<?php

namespace App\Console\Commands;

use App\Http\Libraries\Sms\RongIm;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Logs\CronCloseModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogImCallModel;
use App\Http\Models\Logs\LogSayHiModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RongCloud;

class processSayHi extends Command
{
    protected $signature = 'process:say-hi {type?}';
    protected $description = '自动打招呼';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        //每分钟更新在线及活跃时间
        if (in_array($type, [0, 1])) {
            $this->_sayHi();
        }
    }

    private function _sayHi()
    {
        $rows = LogSayHiModel::where([['del', 0], ['send_at', '<', date('Y-m-d H:i:s')]])->get();
        if (!$rows->isEmpty()) {
            foreach ($rows as $row) {
                try {
                    $content = json_encode(["content" => $row->to_say]);
                    //过滤目标对象
                    RongCloud::messagePrivatePublish($row->from_uid, [$row->to_uid], 'RC:TxtMsg', $content);
                    $row->del = 1;
                    $row->del_at = date('Y-m-d H:i:s');
                    $row->save();
                } catch (\Exception $e) {
                    echo $e->getMessage() . PHP_EOL;
                }
            }
        }

    }

}
