<?php

namespace App\Console\Commands\Report;


use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Report\ProcessPushModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Console\Command;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Helpers\H;
use App\Http\Libraries\Logins\Baidu;

class reportBaidu extends Command
{
    protected $signature = 'report:baidu {type?}';
    protected $description = '百度统计相关数据推送及拉取';
    protected $domain = 'http://hfriend.cn';//基础网址
    protected $mdomain = 'http://m.hfriend.cn';//基础手机网址

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        if (in_array($type, [0, 1])) {
            $this->_pushDiscover();
        }
        if (in_array($type, [0, 2])) {
            $this->_pushUser();
        }
    }

    //百度要求每日最多提供10万条有价值的信息
    private function _pushDiscover()
    {
        $process = ProcessPushModel::where([['channel', 'baidu'], ['table', 'discover']])->first();
        DiscoverModel::where([['status', 1], ['private', '!=', 1], ['id', '>', $process->process_id]])->chunk(500, function ($items) use ($process) {
            $maxId = $process->process_id;
            $urls = [];
            foreach ($items as $item) {
                $urls[] = $this->domain . '/discover/' . $item->id;
                if ($item->id > $maxId) $maxId = $item->id;
            }
            $this->pushBaidu($urls);
            $process->process_id = $maxId;
            $process->save();
        });
    }

    private function _pushUser()
    {
        $process = ProcessPushModel::where([['channel', 'baidu'], ['table', 'user']])->first();
        UsersModel::where([['status', 1], ['id', '>', $process->process_id]])->chunk(500, function ($items) use ($process) {
            $maxId = $process->process_id;
            $urls = [];
            foreach ($items as $item) {
                $urls[] = $this->domain . '/user/' . $item->id;
                if ($item->id > $maxId) $maxId = $item->id;
            }
            $this->pushBaidu($urls);
            $process->process_id = $maxId;
            $process->save();
        });
    }


    # [push 推送信息到百度]
    private function pushBaidu($urls)
    {
        $log[] = date("Y-m-d H:i:s") . ' 本次共计提交网址（' . count($urls) . '）个';
        $api = 'http://data.zz.baidu.com/urls?site=zfriend.cn&token=2vpc02VIJCKg1x1b';
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $api,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => implode("\n", $urls),
            CURLOPT_HTTPHEADER => ['Content-Type: text/plain'],
        ];
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        echo $result . PHP_EOL;
        $log = array_merge($urls, $log);
        $remain = isset(json_decode($result, true)['remain']) ? json_decode($result, true)['remain'] : 0;
        $log[] = date("Y-m-d H:i:s") . ' 提交网址成功,今日剩余（' . $remain . '）个';
        echo join("\r\n", $log) . PHP_EOL . PHP_EOL;
    }

}
