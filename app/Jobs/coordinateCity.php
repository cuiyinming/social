<?php

namespace App\Jobs;

use App\Http\Libraries\Tools\BaiduCloud;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class coordinateCity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    protected $table;
    protected $coordinates;

    //根据传递的经纬度更新表中的城市信息，更加准确的
    public function __construct($id, $table, $coordinates)
    {
        $this->id = $id;
        $this->table = $table;
        $this->coordinates = $coordinates;
    }

    public function handle()
    {
        if ($this->table == 'profile') {
            (new BaiduCloud())->getCityByPoint($this->coordinates);
        }
    }
}
