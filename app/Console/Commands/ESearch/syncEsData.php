<?php

namespace App\Console\Commands\ESearch;

use App\Components\ESearch\ESearch;
use App\Components\ESearch\SearchClient;
use Illuminate\Console\Command;

class syncEsData extends Command
{
    protected $signature = 'sync:es {index?} {type?} {action?} {start?} {end?} {print=1}';
    protected $description = '同步数据表数据到es 参数包含： {index?} {type?} {action?} {start?} {end?} 其中的start 和 end 是user_id';
    protected $start;
    protected $end;
    protected $action;
    protected $index;
    protected $type;
    protected $print;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        ini_set('memory_limit', '3072M');
        set_time_limit(0);
        $this->print = $this->argument('print');
        //默认有 mmm/items 为items 的价格信息
        if ($this->print == 1) echo date('Y-m-d H:i:s'), ' start', PHP_EOL;
        $this->start = $this->argument('start') ?: 0;
        $this->end = $this->argument('end') ?: 0;
        $this->action = $this->argument('action') ?: 'sync';
        $this->index = $this->argument('index');
        $this->type = $this->argument('type');
        if (is_null($this->type)) {
            if ($this->print == 1) echo 'you need assign the argument type' . PHP_EOL;
            exit;
        }
        if (is_null($this->index)) {
            if ($this->print == 1) echo 'you need assign the argument index' . PHP_EOL;
            exit;
        }
        if ($this->action === 'delete') {
            $this->_detele();
        }
        $this->_sync();
    }


    //同步信息
    private function _sync()
    {
        $st = microtime(1);
        $idRange = [
            's' => $this->start,
            'e' => $this->end,
        ];
        if ($this->print == 1) echo ' 开始同步, ' . date('Y-m-d H:i:s') . PHP_EOL;
        (new ESearch($this->index . ':' . $this->type))->sync($idRange, $this->action);
        if ($this->print == 1) echo ' 结束同步, ' . date('Y-m-d H:i:s') . '用时：' . round(microtime(1) - $st, 2) . ' S' . PHP_EOL;
        if ($this->print == 1) $msg = date('Y-m-d H:i:s') . ' end...  ' . round(microtime(1) - $st, 2) . ' S' . PHP_EOL;
        if ($this->print == 1) die($msg);
    }

    private function _detele()
    {
        $client = SearchClient::getInstance();
        $client->deleteIndices(['index' => $this->index]);
        if ($this->print == 1) die('...deleted...over...' . PHP_EOL);
    }
}
