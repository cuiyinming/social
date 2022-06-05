<?php

namespace App\Console\Commands;

use App\Components\ESearch\ESearch;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Libraries\Tools\GraphCompare;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogSignModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\R;
use Illuminate\Support\Facades\Log;

class processTenMinute extends Command
{

    protected $signature = 'process:tenMin {type?}';
    protected $description = '每十分钟执行一次的定时任务';


    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        $type = $this->argument('type') ?: 0;
        //每十分钟更新用户的活跃坐标信息
        if (in_array($type, [0, 1])) {
//            if (in_array(date('H'), [12, 23]) && in_array(date('i'), [30, 40])) {
            $this->_usersLiveCoordinate();
//            }
        }
        //每十分钟更新下所有用户的活跃时间
        if (in_array($type, [0, 2])) {
            $this->_usersActiveTimeUpdate();
        }
        if (in_array($type, [0, 3])) {
            $this->_usersOrderStatusCheck();
        }
        //超级曝光队列更新
        if (in_array($type, [0, 5])) {
            $this->_superQueueSet();
        }
    }

    //超级曝光队列处理
    private function _superQueueSet()
    {
        //先下线已经到期用户
        UsersModel::where([['super_show', 1], ['super_show_exp_time', '<=', date('Y-m-d H:i:s')]])->orWhere('super_show', 0)->update(['super_show' => 0, 'super_show_exp_time' => null]);
        $super = config('settings.super');
        if (isset($super['super_show_on']) && $super['super_show_on']) {
            // 1女2男
            /*********************************************************************
             ****************************女生部分逻辑******************************
             *********************************************************************/
            $users = UsersModel::where([['status', 1], ['super_show', 1], ['sex', 1]])->whereNull('unlock_time')->get();
            $keyFemale = 'super_auto_queue_female';
            if (!$users->isEmpty()) {
                $userArr = [];
                foreach ($users as $user) {
                    $userArr[] = $user->id;
                    $exit = HR::valueIsExists($keyFemale, $user->id);
                    if (!$exit) {
                        HR::pushQueue($keyFemale, $user->id); //如果是新来的用户就放在队尾
                        $msg = 'A[女]-新增用户【' . $user->id . '】到队列尾部' . PHP_EOL;
                        if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);
                    }
                }
                //还要另外一个情况是队列里面有但是已经下线了的，需要剔出队列
                $queue_users = HR::getAllQueue($keyFemale);
                foreach ($queue_users as $queue_user) {
                    if (!in_array($queue_user, $userArr)) {
                        //剔出队列
                        HR::delQueue($keyFemale, $queue_user);
                        $msg = 'B[女]-队列中的用户不在符合条件的用户群【' . $queue_user . '】剔出队列' . PHP_EOL;
                        if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);
                    }
                }
            } else {
                R::dredis($keyFemale);
                $msg = 'C[女]-符合条件的用户群为空【清空队列】' . PHP_EOL;
                if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);
            }
            //最后输出队列的队形
            $queue_users = HR::getAllQueue($keyFemale);
            $msg = 'D[女]-当前的队列队形：' . join('-', $queue_users) . PHP_EOL;
            if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);

            /*********************************************************************
             ****************************男生部分逻辑******************************
             *********************************************************************/

            $maleUsers = UsersModel::where([['status', 1], ['super_show', 1], ['sex', 2]])->whereNull('unlock_time')->get();
            $keyMale = 'super_auto_queue_male';
            if (!$maleUsers->isEmpty()) {
                $userMaleArr = [];
                foreach ($maleUsers as $male) {
                    $userMaleArr[] = $male->id;
                    $exit = HR::valueIsExists($keyMale, $male->id);
                    if (!$exit) {
                        HR::pushQueue($keyMale, $male->id); //如果是新来的用户就放在队尾
                        $msg = 'A[男]-新增用户【' . $male->id . '】到队列尾部' . PHP_EOL;
                        if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);
                    }
                }
                //还要另外一个情况是队列里面有但是已经下线了的，需要剔出队列
                $queueMaleUsers = HR::getAllQueue($keyMale);
                foreach ($queueMaleUsers as $queueMaleUser) {
                    if (!in_array($queueMaleUser, $userMaleArr)) {
                        //剔出队列
                        HR::delQueue($keyMale, $queueMaleUser);
                        $msg = 'B[男]-队列中的用户不在符合条件的用户群【' . $queueMaleUser . '】剔出队列' . PHP_EOL;
                        if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);
                    }
                }
            } else {
                R::dredis($keyMale);
                $msg = 'C[男]-符合条件的用户群为空【清空队列】' . PHP_EOL;
                if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);
            }
            //最后输出队列的队形
            $queueMaleUsers = HR::getAllQueue($keyMale);
            $msg = 'D[男]-当前的队列队形：' . join('-', $queueMaleUsers) . PHP_EOL;
            if (env('CORN_LOG', false)) Log::channel('cron')->info($msg);
        }
    }

    private function _usersLiveCoordinate()
    {
        $redis = R::connect('coordinate');
        $users = $redis->keys('users-active-coordinate-*');
        if ($users) {
            $liveCoordinateArr = $esCoordinateArr = [];
            //获取全部的系统人员
            foreach ($users as $user) {
                $user_id = str_replace('users-active-coordinate-', '', $user);
                $coordinate = $redis->get($user);
                if (empty($coordinate)) continue;
                $cooArr = [];
                $liveArr = explode(',', $coordinate);
                if (isset($liveArr[1])) $cooArr[0] = floatval($liveArr[1]);
                if (isset($liveArr[0])) $cooArr[1] = floatval($liveArr[0]);
                $city = (new BaiduCloud())->getCityByPoint($coordinate);
                $esCoordinateArr[] = [
                    'id' => $user_id,
                    'live_coordinates' => $cooArr,
                    'live_location' => $city,
                ];
                $liveCoordinateArr[] = [
                    'id' => $user_id,
                    'live_coordinates' => $coordinate,
                    'live_location' => $city,
                ];
            }
            if (count($liveCoordinateArr) > 0) {
                UsersModel::batchIntoCoordinates($liveCoordinateArr);
                //更新es
                (new ESearch('users:users'))->updateSingle($esCoordinateArr);
            }
        }
    }

    private function _usersActiveTimeUpdate()
    {
        //更新用户在线状态
        $redis = R::connect('active');
        $users = $redis->keys('users-last-active-*');
        if ($users) {
            $liveTime = [];
            foreach ($users as $user) {
                $user_id = str_replace('users-last-active-', '', $user);
                $time = $redis->get($user);
                if (empty($time)) continue;
                if (intval($time) > 0) {
                    //更新用户活跃时间
                    $liveTime[] = [
                        'id' => $user_id,
                        'live_time_latest' => date('Y-m-d H:i:s', $time),
                    ];
                }
            }
            if (count($liveTime) > 0) {
                UsersModel::batchIntoActive($liveTime);
            }
        }
    }

    //每十分钟核对订单掉单情况
    private function _usersOrderStatusCheck()
    {
        $stime = date('Y-m-d H:i:s', time() - 3600);
        $etime = date('Y-m-d H:i:s');
        $orders = OrderModel::where([['check_status', 0], ['status', 1], ['type', 0], ['updated_at', '>=', $stime], ['updated_at', '<=', $etime]])->get();
        if (!$orders->isEmpty()) {
            foreach ($orders as $order) {
                $user = UsersProfileModel::where('user_id', $order->user_id)->first();
                if ($user && $user->vip_is == 1) {
                    $order->check_status = 1;
                    $order->save();
                }
            }
        }
    }

}
