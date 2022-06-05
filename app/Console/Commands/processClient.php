<?php

namespace App\Console\Commands;

use App\Http\Middleware\StaticLog;
use App\Http\Models\Client\ClientLogModel;
use App\Http\Models\Client\ClientProfitModel;
use App\Http\Models\Client\ClientReportModel;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\Logs\LogRecommendModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\MessageModel;
use App\Http\Models\CommonModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{R, HR};

class processClient extends Command
{
    protected $signature = 'process:client {type?} {date?}';
    protected $description = '出代理的数据报表';
    protected $date = null;
    protected $start = null;
    protected $end = null;

    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $this->date = $this->argument('date') ?: date('Y-m-d');
        $this->start = date('Y-m-d 00:00:00', strtotime($this->date));
        $this->end = date('Y-m-d 23:59:59', strtotime($this->date));
        $type = $this->argument('type') ?: 0;
        if (in_array($type, [0, 2])) {
            $this->_getMapData();
        }
    }

    private function _getMapData()
    {
        //pv uv
        $start = $this->start;
        $end = $this->end;
        $puv = $reg = $cli = $prof = $data = [];
        //获取区段的uv ip
        $pvs = ClientLogModel::select(DB::Raw('count(*) as pv,count(distinct(`ip`)) as ip, invited'))->where([['created_at', '>', $start], ['created_at', '<', $end]])->groupBy('invited')->get();
        if (!$pvs->isEmpty()) {
            foreach ($pvs as $pv) {
                $puv[$pv->invited]['pv'] = $pv->pv;
                $puv[$pv->invited]['uv'] = $pv->ip;
            }
        }
        //注册人数
        $registers = UsersModel::select(DB::Raw('count(*) as register, client_code'))->where([['created_at', '>', $start], ['created_at', '<', $end], ['client_code', '>', 0]])->groupBy('client_code')->get();
        if (!$registers->isEmpty()) {
            foreach ($registers as $register) {
                $reg[$register->client_code] = $register->register;
            }
        }
        //注册代理
        $clients = ClientUsersModel::select(DB::Raw('count(*) as register, invited'))->where([['created_at', '>', $start], ['created_at', '<', $end], ['invited', '>', 0]])->groupBy('invited')->get();
        if (!$clients->isEmpty()) {
            foreach ($clients as $client) {
                $cli[$client->invited] = $client->register;
            }
        }

        //订单收益 [分为结果大类目，draw | vip | recharge | client]
        $profits = ClientProfitModel::select(DB::Raw('sum(`amount`) as amount, sum(`origin_amount`) as origin_amount, type, user_id'))
            ->where([['created_at', '>', $start], ['created_at', '<', $end], ['user_id', '>', 0]])
            ->whereIn('type', ['vip', 'recharge', 'client'])
            ->groupBy('user_id', 'type')->get();
        if (!$profits->isEmpty()) {
            foreach ($profits as $profit) {
                $prof[$profit->user_id][$profit->type]['amount'] = $profit->amount;
                $prof[$profit->user_id][$profit->type]['origin_amount'] = $profit->origin_amount;
            }
        }

        $clientLists = ClientUsersModel::where('status', 1)->get();
        if (!$clientLists->isEmpty()) {
            foreach ($clientLists as $list) {
                $_data = [
                    'user_id' => $list->id,
                    'pv' => $puv[$list->invite_code]['pv'] ?? 0,
                    'ip' => $puv[$list->invite_code]['uv'] ?? 0,
                    'register' => $reg[$list->invite_code] ?? 0,
                    'register_client' => $cli[$list->invite_code] ?? 0,
                    'vip_amount' => $prof[$list->id]['vip']['origin_amount'] ?? 0,
                    'vip_profit' => $prof[$list->id]['vip']['amount'] ?? 0,
                    'recharge_amount' => $prof[$list->id]['recharge']['amount'] ?? 0,
                    'recharge_profit' => $prof[$list->id]['recharge']['origin_amount'] ?? 0,
                    'client_profit' => $prof[$list->id]['client']['amount'] ?? 0,
                ];
                ClientReportModel::updateOrCreate([
                    'date' => $this->date,
                    'user_id' => $list->id,
                ], $_data);
            }
        }
    }

}
