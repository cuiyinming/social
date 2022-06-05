<?php

namespace App\Http\Models\Login;

use App\Http\Helpers\H;
use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoginLogModel extends Model
{
    protected $table = 'login_log';
    protected $guarded = [];

    public static function gainLoginLog($user_id, $remark = '', $type = 0, $os = '', $broswer = '')
    {
        self::create([
            'user_id' => $user_id,
            'login_time' => CORE_TIME,
            'ip' => IP,
            'channel' => CHANNEL,
            'last_city' => H::Ip2City(IP),
            'remark' => $remark,
            'device' => DEVICE,
            'os' => $os,
            'broswer' => $broswer,
            'coordinates' => COORDINATES,
            'type' => $type
        ]);
    }

    /*-----分类统计登陆设备的总数目-----*/
    public static function getDeviceNum()
    {
        $res = [];
        $sums = self::select(DB::raw('count(*) as total, user_id'))->whereNotNull('device')->where('user_id', '>', 0)->groupBy('user_id')->get();
        if (!$sums->isEmpty()) {
            foreach ($sums as $sum) {
                if ($sum->total > 1) {
                    $res[] = [
                        'user_id' => $sum->user_id,
                        'total' => $sum->total,
                    ];
                }
            }
        }
        return $res;
    }

    public static function updateDeviceNum()
    {
        $res = self::getDeviceNum();
        if ($res) {
            foreach ($res as $val) {
                UsersModel::where('id', $val['user_id'])->update(['device_num' => $val['total']]);
            }
        }
    }


    /**
     * 获取上一次登陆信息
     */
    public static function getLastLoginInfo($uid = 0, $type = 0)
    {
        $records = self::where([['user_id', $uid], ['type', $type]])->orderBy('id', 'desc')->take(2)->get();
        if ($records->isEmpty()) {
            return [];
        } else {
            $arr = $records->toArray();
            if (count($arr) == 1) {
                return $arr[0];
            }
            if (count($arr) == 2) {
                return $arr[1];
            }
            return [];
        }
    }

    /**
     * 获取用户的分页登陆日志
     */
    public static function getPageLog($page = 1, $size = 20, $uid = 0, $type = 0)
    {
        $builder = self::where([['user_id', $uid], ['type', $type]])->orderBy('id', 'desc');
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        return [
            'count' => $count,
            'logs' => $logs ? $logs : []
        ];
    }

    /**
     * @param int $page
     * @param int $size
     * @param int $uid
     * @param int $type
     * @return array
     * 管理员
     */
    public static function getAdminPageLog($page = 1, $size = 20, $q = '', $type = 0)
    {
        $builder = self::where('type', $type)->orderBy('id', 'desc');
        if ($q != '') {
            $builder->where('user_id', $q)->orWhere('ip', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$logs->isEmpty()) {

        }
        return [
            'count' => $count,
            'logs' => $logs ? $logs : []
        ];
    }
}
