<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LogRiskModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_risk';

    public static function getAdminPageAction($page, $size, $status, $q, $date, $id)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($date) && count($date) > 1) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        if (!is_null($id) && $id > 0) {
            $builder->where('user_id', $id);
            $builder->where('user_id', $id);
        }
        if (!is_null($q)) {
            if (is_numeric($q)) {
                $builder->where('user_id', $q);
            } else {
                $builder->where('name', 'like', '%' . $q . '%');
            }
        }
        if (!is_null($status)) {
            $builder->where('ustatus', $status);
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($logs) {
            $cont_map = [
                '海外用户禁止注册',
                'gps更新到国外',
                '登陆到临沧女用户重新实名',
                'ip非中国大陆地区强制退出',
                '未付费换设备登陆数美100需重新认证',
                '登陆ip为东南亚地区，强制退出',
            ];
            $celue_map = [
                '(ip_city_name =~ /香港|澳门|台湾/ || ip_city_name =~ /海外|欧洲|美国/)',
                'origin_country_name != country_name_field && origin_country_gps != gps_name_field',
                'cny_amount < 100 && original_model != current_model',
                'origin_model != current+model_field && id_card_number != origin_card_number',
                'sex === 0 && original_ip_country_name !~ /临沧/ && ip== 临沧',
                'code == fruit && (ip_country_name !~ /中国/ || ip == china_mainland)',
            ];
            //功能
            $function_map = [
                '缘分广场',
                '登陆',
                '登陆',
                '修改ip',
                '更换Gps位置',
                '国外登陆',
            ];
            //规则
            $rule_map = [
                '开罚单',
                '开罚单',
                '封禁',
                '封禁',
                '封禁',
                '禁言',
            ];
            $ustatus_map = [
                '',
                '高风险',
                '机器账号',
                '虚假手机号',
                '虚假设备',
            ];
            foreach ($logs as $k => &$log) {
                $log->name = $cont_map[$k % 5] ?? '';
                $log->celue = $celue_map[$k % 5] ?? '';
                $log->gongneng = $function_map[$k % 5] ?? '';
                $log->guize = $rule_map[$k % 5] ?? '';
                $log->ustatus = $ustatus_map[$log->ustatus] ?? '';
            }
        }
        return [
            'count' => $count,
            'items' => $logs ?: []
        ];
    }
}
