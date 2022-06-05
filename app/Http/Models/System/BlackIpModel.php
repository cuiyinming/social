<?php

namespace App\Http\Models\System;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class BlackIpModel extends Model
{
    protected $guarded = [];
    protected $table = 'black_ip';

    public static function getAdminPageItems($page, $size)
    {
        $builder = self::orderBy('id', 'desc');
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

    //同步封禁信息到数据库  process 0 解封  1封禁
    public static function syncBlockInfoBase($user, $process, $type = 'all')
    {
        //封禁
        if ($process) {
            $profile = $user->profile;
            //封禁注册ip和最后登陆ip
            if (in_array($type, ['ip', 'all']) && stripos($user->last_ip, '127.0.0') === false && stripos($user->last_ip, '172.0.0') === false && !empty($user->last_ip)) {
                $ip = explode('.', $user->last_ip);
                BlackIpModel::create([
                    'ip1' => $ip[0],
                    'ip2' => $ip[1],
                    'ip3' => $ip[2],
                    'ip4' => $ip[3],
                    'desc' => '封禁用户' . $user->id,
                ]);
            }
            if (in_array($type, ['ip', 'all']) && stripos($profile->register_ip, '127.0.0') === false && stripos($profile->register_ip, '172.0.0') === false && !empty($profile->register_ip)) {
                $ip = explode('.', $profile->register_ip);
                BlackIpModel::create([
                    'ip1' => $ip[0],
                    'ip2' => $ip[1],
                    'ip3' => $ip[2],
                    'ip4' => $ip[3],
                    'desc' => '封禁用户' . $user->id,
                ]);
            }
            //更新设备

            if (in_array($type, ['device', 'all']) && !empty($profile->register_device)) {
                BlackDeviceModel::create([
                    'user_id' => $user->id,
                    'device_type' => $profile->register_channel,
                    'device' => $profile->register_device,
                    'status' => 1,
                ]);
            }
            //更新手机号
            if (in_array($type, ['mobile', 'all'])) {
                BlackMobileModel::create([
                    'user_id' => $user->id,
                    'mobile' => H::decrypt($user->mobile),
                ]);
            }

        } else {
            //解封
            $profile = $user->profile;
            //封禁注册ip和最后登陆ip
            if (in_array($type, ['ip', 'all']) && !empty($user->last_ip)) {
                $ip = explode('.', $user->last_ip);
                BlackIpModel::where([
                    ['ip1', $ip[0]],
                    ['ip2', $ip[1]],
                    ['ip3', $ip[2]],
                    ['ip4', $ip[3]],
                ])->delete();
            }
            if (in_array($type, ['ip', 'all']) && !empty($profile->register_ip)) {
                $ip = explode('.', $profile->register_ip);
                BlackIpModel::where([
                    ['ip1', $ip[0]],
                    ['ip2', $ip[1]],
                    ['ip3', $ip[2]],
                    ['ip4', $ip[3]],
                ])->delete();
            }
            //更新设备
            if (in_array($type, ['device', 'all']) && !empty($profile->register_device)) {
                BlackDeviceModel::where([
                    ['device', $profile->register_device],
                ])->delete();
            }
            //更新手机号
            if (in_array($type, ['mobile', 'all'])) {
                BlackMobileModel::where('mobile', H::decrypt($user->mobile))->delete();
            }
        }
    }


}
