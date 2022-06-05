<?php

namespace App\Console\Commands\Process;

use App\Http\Libraries\Crypt\Decrypt;
use App\Http\Libraries\Crypt\Encrypt;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\EsDataModel;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Models\JobsModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Lib\LibBioTextModel;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Logs\LogBrowseModel;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\CommonModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{H, R, HR};
use RongCloud;

class autoActive extends Command
{

    protected $signature = 'auto:active {type=0}';
    protected $description = '同步数据到我的数据库';
    protected $user_id = 0;

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        set_time_limit(0);
        $type = $this->argument('type');
        if (in_array($type, [0, 1])) $this->active();
    }

    private function active()
    {
        //$this->_activeOnline();
        //关闭动态浏览自定义查看
        //$this->_activeView();
        //定时发送个人动态 [每十分钟]
        $hour = intval(date('H'));
        $min = intval(date('i'));
        if ($hour > 9 && $hour < 22) {
            if ($min % 15 == 0) {
                $this->_discoverPublish();
            }
        }
        if (($hour > 6 && $hour <= 9) || $hour > 22 || $hour <= 1) {
            if ($min % 30 == 0) {
                $this->_discoverPublish();
            }
        }
    }

    private function _discoverPublish()
    {
        $column = ['users.id', 'users.sex', 'users.discover', 'users.fake', 'users.live_time_latest', 'users_profile.real_is', 'users_profile.album', 'users_profile.register_channel'];
        $users = UsersModel::select($column)->where([
            ['users.id', '>=', 187945],
            ['users.id', '<=', 240451],
            ['users.discover', 1],
            ['users.sex', 1],
            ['users.fake', '>', 0],
            ['users.live_time_latest', '<', '2021-10-26 00:00:00'],
            ['users_profile.real_is', 0]
        ])->leftjoin('users_profile', 'users.id', '=', 'users_profile.user_id')->limit(1)->get();
        if (!$users->isEmpty()) {
            foreach ($users as $user) {
                $newAlbum = json_decode($user->album, 1);
                $user->discover = 2;
                $user->save();
                if (count($newAlbum) > 0) {
                    $insertData = [];
                    $insertData['user_id'] = $user->id;
                    $insertData['sex'] = $user->sex;
                    $insertData['cont'] = '我刚刚更新了相册,快来看看吧~';
                    $insertData['cmt_on'] = 1;
                    $insertData['show_on'] = 0;
                    $insertData['status'] = 1;
                    $insertData['private'] = 0;
                    $insertData['location'] = null;
                    $insertData['lat'] = null;
                    $insertData['lng'] = null;
                    $insertData['tags'] = null;
                    $insertData['album'] = $newAlbum;
                    $insertData['sound'] = null;
                    $insertData['num_cmt'] = 0;
                    $insertData['num_zan'] = 0;
                    $insertData['num_view'] = 5;
                    $insertData['num_share'] = 0;
                    $insertData['num_say_hi'] = 0;
                    $insertData['post_at'] = date('Y-m-d H:i:s');
                    $insertData['online'] = 0;
                    $insertData['type'] = 1;
                    $insertData['channel'] = strtolower($user->register_channel);
                    $insertData['created_at'] = date('Y-m-d H:i:s');
                    $insertData['updated_at'] = date('Y-m-d H:i:s');
                    DiscoverModel::where([
                        ['type', 1],
                        ['user_id', $user->id],
                        ['created_at', '>', date('Y-m-d 00:00:00')],
                        ['created_at', '<', date('Y-m-d 23:59:59')]
                    ])->delete();
                    DiscoverModel::create($insertData);
                }
            }
        }
    }


    private function _activeView()
    {
        //浏览记录触发
        //第二步 查出来指定时间段的注册用户
        $registers = UsersModel::where([['created_at', '>=', date('Y-m-d H:i:s', time() - 2400)], ['status', 1]])->get();
        if (!$registers->isEmpty()) {
            foreach ($registers as $register) {
                $profileModel = UsersProfileModel::getUserInfo($register->id);
                try {
                    //第一步随机出来8个人来浏览这个用不
                    $sex = $register->sex == 1 ? 2 : 1;
                    $users = UsersModel::where([['id', '>=', 141491], ['id', '<=', 186690], ['online', 1], ['sex', $sex]])->orderBy(DB::raw('RAND()'))->limit(2)->get();
                    foreach ($users as $user) {
                        $selfSetting = UsersSettingsModel::getUserSettings($user->id);
                        if ($selfSetting['hide_browse'] == 0) {
                            $profileModel->increment('browse_num');
                            //添加8个人访问的时间间隔逻辑 [ 通过队列的处理达到不同时间访问的目的 ]
                            $minute = rand(5, 59);
                            \App\Jobs\viewNotice::dispatch($register->id, $user->id)->delay(now()->addMinutes($minute))->onQueue('register');
                        }
                    }
                    //添加对方的被浏览数
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
        }
    }

    private function _activeOnline()
    {
        //20 个活跃每次  [半个小时一次触发]
        $num = 80;
        $users = UsersModel::where([['id', '>=', 141491], ['id', '<=', 186690], ['online', 0]])->orderBy(DB::raw('RAND()'))->limit($num)->get();
        if (!$users->isEmpty()) {
            foreach ($users as $user) {
                HR::updateActiveTime($user->id);
            }
        }
    }
}
