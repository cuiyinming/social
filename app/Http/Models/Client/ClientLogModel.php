<?php

namespace App\Http\Models\Client;

use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClientLogModel extends Model
{
    protected $guarded = [];
    protected $table = 'client_log';


    public static function getGroupData($invite_code)
    {
        $res = [];
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        $city = self::select(DB::raw('count(*) as total, city'))->where([['invited', $invite_code], ['created_at', '>', $start], ['created_at', '<=', $end]])->groupBy('city')->get();
        if (!$city->isEmpty()) {
            foreach ($city as $re) {
                $res[$re->city]['name'] = $re->city;
                $res[$re->city]['value'] = $re->total;
            }
        }
        $city = self::select(DB::raw('count(distinct(`ip`)) as total, city'))->where([['invited', $invite_code], ['created_at', '>', $start], ['created_at', '<=', $end]])->groupBy('city')->get();
        if (!$city->isEmpty()) {
            foreach ($city as $re) {
                $res[$re->city]['perf'] = $re->total;
            }
        }
        return array_values($res);
    }

    public static function getGroupChartData($invite_code, $by = 'qq')
    {
        $start = date('Y-m-d 00:00:00');
        $end = date('Y-m-d 23:59:59');
        $legend = $series = $res = $pv = $uv = [];

        $builder = self::select(DB::raw('count(*) as total,count(distinct(`ip`)) as ip, hour'))->where([['invited', $invite_code], ['created_at', '>', $start], ['created_at', '<=', $end]])->groupBy('hour');
        if ($by == 'android') $builder->where('os', 'android');
        if ($by == 'ios') $builder->where('os', 'ios');
        if ($by == 'windows') $builder->where('os', 'windows');
        if ($by == 'mac') $builder->where('os', 'mac');
        if ($by == 'osother') $builder->where('os', 'other');

        if ($by == 'wechat') $builder->where('ua', 'wechat');
        if ($by == 'qq') $builder->where('ua', 'qq');
        if ($by == 'browser') $builder->where('ua', 'browser');
        if ($by == 'uaother') $builder->where('ua', 'other');

        $city = $builder->get();
        if (!$city->isEmpty()) {
            $pvs = $uvs = [];
            foreach ($city as $re) {
                $pvs[$re->hour] = $re->total;
                $uvs[$re->hour] = $re->ip;
            }
        }
        $xdata = ['0-2???', '2-4???', '4-6???', '6-8???', '8-10???', '10-12???', '12-14???', '14-16???', '16-18???', '18-20???', '20-22???', '22-24???'];
        //??????pv uv ??????
        foreach ($xdata as $xda) {
            $pv[$xda] = isset($pvs[$xda]) ? $pvs[$xda] : 0;
            $uv[$xda] = isset($uvs[$xda]) ? $uvs[$xda] : 0;
        }
        $legend = ['pv', 'uv'];
        $series = [
            [
                'name' => 'pv',
                'type' => 'line',
                'stack' => '???????????????',
                'data' => array_values($pv),
            ], [
                'name' => 'uv',
                'type' => 'line',
                'stack' => '???????????????',
                'data' => array_values($uv),
            ]
        ];
        return [
            'legend' => $legend,
            'series' => $series,
            'xdata' => $xdata,
            'by' => $by
        ];
    }


    public static function getPageItems($page = 1, $size = 20, $dates = [], $uid = 0, $q): array
    {
        $builder = self::orderBy('id', 'desc');
        if ($uid > 0) {
            $builder->where('invited', $uid);
        }
        if (!empty($q) && !is_null($q)) {
            $builder->where('invited', 'like', '%' . $q . '%')->orWhere('event', $q)->orWhere('os', $q)->orWhere('ua', $q);
        }
        if (!empty($dates)) {
            $builder->whereBetween('created_at', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
//        if (!$items->isEmpty()) {
//            ??????
//            foreach ($items as &$item) {
//            }
//        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

}
