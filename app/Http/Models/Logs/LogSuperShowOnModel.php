<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\HR;
use App\Http\Models\EsDataModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LogSuperShowOnModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_super_show_on';

    public static function superShowUserGet($uid = 0, $sex = 0, $exclusion = [], $users = [])
    {
        $super = config('settings.super');
        if (!$super['super_show_on'] || $uid == 0) return $users;
        //超级曝光队列逻辑
        $selected = 0;
        $key = $sex == 1 ? 'super_auto_queue_male' : 'super_auto_queue_female';
        $queue_users = HR::getAllQueue($key);
        if (is_array($queue_users) && count($queue_users) > 0) {
            foreach (array_reverse($queue_users) as $queue_user) {
                if (empty($queue_user)) continue;
                if (is_array($exclusion) && count($exclusion) > 0 && in_array($queue_user, $exclusion)) {
                    continue;
                }
                $selected = intval($queue_user);
                HR::delQueue($key, $queue_user); //从队列里面移除
                HR::pushQueue($key, $queue_user); //放到队列的最后面
                break;
            }
        }
        if ($selected > 0) {   //得到超级曝光的人
            //入库展示次数
            //LogSuperShowModel::create([
            //    'user_id' => $uid,
            //    'super_user_id' => $selected,
            //]);
            //增加当前用户的展示次数
            UsersModel::where('id', $selected)->increment('super_show_num');
            $sourceArr = explode(',', COORDINATES);
            $super = EsDataModel::getEsData(['must_have' => [$selected], 'page' => 1, 'size' => 5], $sourceArr, []);
            //追加超级曝光的人
            if (isset($super['items'][0])) {
                $super['items'][0]['super_show'] = 1;
                $users['items'][] = $super['items'][0];
            }
        }
        return $users;

    }
}
