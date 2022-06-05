<?php

namespace App\Http\Models\Logs;

use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\DB;

class LogRecommendModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_recommend';

    //批量入库信息
    public static function batchInsert($items)
    {

    }
}
