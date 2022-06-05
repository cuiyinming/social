<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StatisticLogModel extends Model
{

    protected $guarded = [];
    protected $table = 'statistic_log';


    public static function showPersonByUrl()
    {
        $startDay = date('Y-m-d 00:00:00',strtotime('-3 days'));


        $obj = static::select(DB::raw("DATE_FORMAT(DATE_ADD(created_at,INTERVAL 8 HOUR),'%Y-%m-%d') AS showDay,url,COUNT(token) AS times,COUNT(DISTINCT token) AS persons"))
            ->groupBy(DB::raw("url,DATE_FORMAT(DATE_ADD(created_at,INTERVAL 8 HOUR),'%Y-%m-%d')"))
            ->orderBy("showDay","DESC")
            ->orderBy("times","DESC")
            ->where('created_at','>=',$startDay);


        $list = $obj->get();

        return $list;
    }


    public static function getTotal()
    {
        $list = static::select(DB::raw('url,COUNT(token) AS times,COUNT(DISTINCT token) AS persons'))
            ->get()
            ->first();

        return $list;
    }
}
