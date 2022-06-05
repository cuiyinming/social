<?php

namespace App\Http\Models;

use App\Http\Models\System\BlackDeviceModel;
use App\Http\Models\System\BlackIpModel;
use App\Http\Models\System\BlackMobileModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\{H, HR, R, S};

class JobsModel extends Model
{
    protected $table = 'jobs_self';
    protected $guarded = [];

    public static function getJobByCodeArr($job_code = [], $type = 0)
    {
        return self::where([['status', 0], ['job_type', $type]])->whereIn('job_code', $job_code)->get();
    }

    public static function getAllJob()
    {
        return self::orderBy('id', 'desc')->get();
    }

    public static function InsertNewJob($type = 1, $ext = '')
    {
        if ($type == 1) self::_syncSysBlock($ext);
        if ($type == 2) self::_syncHideModel($ext);
        if ($type == 3) self::_syncUserBlack($ext);
        if ($type == 4) self::_syncTags($ext);
        //同步封禁信息到数据库及es
        if ($type == 5) self::_syncSysBlockIp($ext);
        if ($type == 6) self::_syncSysBlockDevice($ext);
        if ($type == 7) self::_syncSysBlockMobile($ext);

    }

    private static function _syncSysBlock($ext)
    {
        self::_createSync('sync-sys-block', 0, '同步封禁账号', '同步系统全部已经封禁的账号信息到REDIS中');
    }

    private static function _syncHideModel($ext)
    {
        self::_createSync('sync-sys-hide-model', 0, '同步隐身账号', '同步系统全部设置了隐身的账号信息到REDIS中');
    }

    private static function _syncUserBlack($ext)
    {
        self::_createSync('sync-user-black', 0, '同步用户设置的黑名单', '同步用户 ' . $ext . ' 设置的黑名单信息', $ext);
    }

    private static function _syncTags($ext)
    {
        self::_createSync('sync-sys-tags', 0, '同步新建的标签', '同步系统全部标签到ES中');
    }

    private static function _syncSysBlockIp($ext)
    {
        self::_createSync('sync-sys-block-ip', 0, '同步封禁IP', '同步系统全部已经封禁的IP信息到REDIS中');
    }

    private static function _syncSysBlockDevice($ext)
    {
        self::_createSync('sync-sys-block-device', 0, '同步封禁设备号', '同步系统全部已经封禁的设备号信息到REDIS中');
    }

    private static function _syncSysBlockMobile($ext)
    {
        self::_createSync('sync-sys-block-mobile', 0, '同步封禁电话号', '同步系统全部已经封禁的电话号信息到REDIS中');
    }

    private static function _createSync($job_code, $job_type, $job_name, $job_desc, $ext = '')
    {
        self::updateOrcreate([
            'job_code' => $job_code,
            'job_type' => $job_type,
            'status' => 0
        ], [
            'job_name' => $job_name,
            'job_code' => $job_code,
            'job_type' => $job_type,
            'job_desc' => $job_desc,
            'ext' => $ext,
            'status' => 0
        ]);
    }


    public static function syncItemInfo($job)
    {
        //系统的全部封禁账号
        if ($job->job_code == 'sync-sys-block') {
            $blockIds = UsersModel::where('status', 0)->pluck('id')->toArray();
            $users = HR::getLockedId();
            $in_redis = array_diff($users, $blockIds);
            if (!empty($in_redis)) {
                foreach ($in_redis as $in_red) {
                    HR::delLockedId($in_red);
                }
            }
            $in_dbs = array_diff($blockIds, $users);
            if (!empty($in_dbs)) {
                foreach ($in_dbs as $in_db) {
                    HR::setLockedId($in_db);
                }
            }
        }
        //封禁账号对应的ip + 电话 + 设备号
        if ($job->job_code == 'sync-sys-block-mobile') {
            $blockUes = BlackMobileModel::pluck('mobile')->toArray();
            $lockedUes = HR::getLockedMobile();
            $in_redis = array_diff($lockedUes, $blockUes);
            if (!empty($in_redis)) {
                foreach ($in_redis as $in_red) {
                    HR::delLockedMobile($in_red);
                }
            }
            $in_dbs = array_diff($blockUes, $lockedUes);
            if (!empty($in_dbs)) {
                foreach ($in_dbs as $in_db) {
                    HR::setLockedMobile($in_db);
                }
            }
        }
        //封禁设备号
        if ($job->job_code == 'sync-sys-block-device') {
            $blockDev = BlackDeviceModel::where('status', 1)->pluck('device')->toArray();
            $lockedDev = HR::getLockedDevice();
            $in_redis = array_diff($lockedDev, $blockDev);
            if (!empty($in_redis)) {
                foreach ($in_redis as $in_red) {
                    HR::delLockedDevice($in_red);
                }
            }
            $in_dbs = array_diff($blockDev, $lockedDev);
            if (!empty($in_dbs)) {
                foreach ($in_dbs as $in_db) {
                    HR::setLockedDevice($in_db);
                }
            }
        }
        //封禁ip
        if ($job->job_code == 'sync-sys-block-ip') {
            $items = BlackIpModel::select(['ip1', 'ip2', 'ip3', 'ip4'])->get();
            if ($items->isEmpty()) {
                HR::delAllLockedIp();
            } else {
                $ret = [];
                foreach ($items as $data) {
                    //对匹配做通配
                    if ($data->ip3 == '*' && $data->ip4 == '*') {
                        for ($i = 0; $i <= 255; $i++) {
                            for ($j = 0; $j <= 255; $j++) {
                                $ret[] = $data->ip1 . '.' . $data->ip2 . '.' . $i . '.' . $j;
                            }
                        }
                    }
                    if ($data->ip3 == '*' && $data->ip4 != '*') {
                        for ($i = 0; $i <= 255; $i++) {
                            $ret[] = $data->ip1 . '.' . $data->ip2 . '.' . $i . '.' . $data->ip4;
                        }
                    }
                    if ($data->ip3 != '*' && $data->ip4 == '*') {
                        for ($j = 0; $j <= 255; $j++) {
                            $ret[] = $data->ip1 . '.' . $data->ip2 . '.' . $data->ip3 . '.' . $j;
                        }
                    }
                    if ($data->ip3 != '*' && $data->ip4 != '*') {
                        $ret[] = $data->ip1 . '.' . $data->ip2 . '.' . $data->ip3 . '.' . $data->ip4;
                    }
                }
                $blockIp = array_unique($ret);
                $lockedIp = HR::getLockedIp();
                $in_redis = array_diff($lockedIp, $blockIp);
                if (!empty($in_redis)) {
                    foreach ($in_redis as $in_red) {
                        HR::delLockedIp($in_red);
                    }
                }
                $in_dbs = array_diff($blockIp, $lockedIp);
                if (!empty($in_dbs)) {
                    foreach ($in_dbs as $in_db) {
                        HR::setLockedIp($in_db);
                    }
                }
            }
        }

        if ($job->job_code == 'sync-sys-hide-model') {
            $hideIds = UsersSettingsModel::where('hide_model', 1)->pluck('user_id')->toArray();
            $users = HR::getHideModelId();
            $in_redis = array_diff($users, $hideIds);
            if (!empty($in_redis)) {
                foreach ($in_redis as $in_red) {
                    HR::delHideModelId($in_red);
                }
            }
            $in_dbs = array_diff($hideIds, $users);
            if (!empty($in_dbs)) {
                foreach ($in_dbs as $in_db) {
                    HR::setHideModelId($in_db);
                }
            }
        }
        if ($job->job_code == 'sync-user-black') {
            $extArr = json_decode($job->ext, 1);
            $blacks = UsersBlackListModel::where([['user_id', $extArr['user_id']], ['black_id', $extArr['black_id']]])->get();
            if (!$blacks->isEmpty()) {
                foreach ($blacks as $black) {
                    if ($black->status == 1) {
                        $res = HR::setUserBlackList($black->user_id, $black->black_id);
                    } else {
                        $res = HR::delUserBlackList($black->user_id, $black->black_id);
                    }
                }
            }
        }
        if ($job->job_code == 'sync-sys-tags') {
            EsDataModel::syncEs('tags', 'tags');
        }
    }

    /*-------管理页面基础信息------*/
    public static function getAdminPageAction($page, $size, $status, $q, $date)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($date) && count($date) > 1) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        if (!is_null($status)) {
            $builder->where('status', $status);
        }
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('job_name', 'like', '%' . $q . '%')->orWhere('job_code', 'like', '%' . $q . '%')->orWhere('job_desc', 'like', '%' . $q . '%');
            });
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($logs) {
            //foreach ($logs as &$log) {}
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
