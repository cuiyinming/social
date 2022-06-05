<?php

namespace App\Http\Models\Report;

use App\Http\Helpers\H;
use App\Http\Models\Logs\ApiLeftModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReportDailyModel extends Model
{

    protected $guarded = [];
    protected $table = 'report_daily';


    public static function getAdminPageItems($page, $size, $dates)
    {
        $builder = self::orderBy('id', 'desc');
        if (count($dates) > 1) {
            $builder->whereBetween('date', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $report = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$report->isEmpty()) {
            $report = $report->toArray();
            foreach ($report as $k => $data) {
                $report[$k]['created_at'] = date('m-d H:i', strtotime($data['created_at']));
                $report[$k]['updated_at'] = date('H:i', strtotime($data['updated_at']));
            }
        }

        $summary = self::_summary($dates);
        return [
            'items' => $report ? $report : [],
            'count' => $count,
            'summary' => $summary,
            'leftinfo' => self::_apiLeftInfo(),
        ];
    }

    //汇总
    private static function _summary($dates): array
    {
        //开始区分性别和渠道
        $summary = $sexReg = [];
        $builder = new self();
        if (count($dates) > 1) {
            $builder->whereBetween('date', [$dates[0], $dates[1]]);
        }
        $summary['register'] = $builder->sum('register');
        $summary['register_female'] = $builder->sum('register_female');
        $summary['register_male'] = $builder->sum('register_male');
        $summary['register_ios'] = $builder->sum('register_ios');
        $summary['register_android'] = $builder->sum('register_android');

        $summary['authed'] = $builder->sum('authed');
        $summary['authed_android'] = $builder->sum('authed_android');
        $summary['authed_ios'] = $builder->sum('authed_ios');

        $summary['vip_num'] = $builder->sum('vip_num');
        $summary['identity'] = $builder->sum('identity');
        $summary['identity_android'] = $builder->sum('identity_android');
        $summary['identity_ios'] = $builder->sum('identity_ios');

        $summary['goddess'] = $builder->sum('goddess');

        $summary['recharge'] = $builder->sum('recharge');
        $summary['recharge_ios'] = $builder->sum('recharge_ios');
        $summary['recharge_android'] = $builder->sum('recharge_android');
        $summary['recharge_android_alipay'] = $builder->sum('recharge_android_alipay');
        $summary['recharge_android_wechat'] = $builder->sum('recharge_android_wechat');
        $summary['recharge_male'] = $builder->sum('recharge_male');
        $summary['recharge_female'] = $builder->sum('recharge_female');

        $summary['inner_buy'] = $builder->sum('inner_buy');
        $summary['inner_buy_ios'] = $builder->sum('inner_buy_ios');
        $summary['inner_buy_android'] = $builder->sum('inner_buy_android');
        $summary['inner_buy_android_alipay'] = $builder->sum('inner_buy_android_alipay');
        $summary['inner_buy_android_wechat'] = $builder->sum('inner_buy_android_wechat');
        $summary['inner_male'] = $builder->sum('inner_male');
        $summary['inner_female'] = $builder->sum('inner_female');

        $summary['recharge_amount'] = $builder->sum('recharge_amount');
        $summary['recharge_amount_android'] = $builder->sum('recharge_amount_android');
        $summary['recharge_amount_ios'] = $builder->sum('recharge_amount_ios');
        $summary['recharge_amount_male'] = $builder->sum('recharge_amount_male');
        $summary['recharge_amount_female'] = $builder->sum('recharge_amount_female');

        $summary['inner_amount'] = $builder->sum('inner_amount');
        $summary['inner_amount_android'] = $builder->sum('inner_amount_android');
        $summary['inner_amount_ios'] = $builder->sum('inner_amount_ios');
        $summary['inner_amount_male'] = $builder->sum('inner_amount_male');
        $summary['inner_amount_female'] = $builder->sum('inner_amount_female');

        $summary['total_amount'] = $builder->sum('total_amount');
        $summary['total_amount_ios'] = $builder->sum('total_amount_ios');
        $summary['total_amount_male'] = $builder->sum('total_amount_male');
        $summary['total_amount_female'] = $builder->sum('total_amount_female');
        $summary['total_amount_android'] = $builder->sum('total_amount_android');
        $summary['total_amount_android_alipay'] = $builder->sum('total_amount_android_alipay');
        $summary['total_amount_android_wechat'] = $builder->sum('total_amount_android_wechat');

        $summary['locked'] = $builder->sum('locked');
        $summary['locked_android'] = $builder->sum('locked_android');
        $summary['locked_ios'] = $builder->sum('locked_ios');
        return $summary;
    }

    private static function _apiLeftInfo()
    {
        $res = [];
        $logs = ApiLeftModel::get();
        if (!$logs->isEmpty()) {
            foreach ($logs as $log) {
                if ($log->type == 'sms') $res[] = [
                    'key' => '短信余量',
                    'val' => $log->left_num
                ];
                if ($log->type == 'fast_login') $res[] = [
                    'key' => '快捷登陆',
                    'val' => $log->left_num
                ];
                if ($log->type == 'identity') $res[] = [
                    'key' => '认证余量',
                    'val' => $log->left_num
                ];
            }
        }
        return $res;
    }
}
