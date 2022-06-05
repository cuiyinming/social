<?php

namespace App\Console\Commands\Process;

use App\Http\Libraries\Crypt\Decrypt;
use App\Http\Libraries\Crypt\Encrypt;
use App\Http\Libraries\Tools\AliyunOss;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Models\EsDataModel;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Models\JobsModel;
use App\Http\Models\Lib\LibBioTextModel;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\CommonModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{H, R, HR};
use RongCloud;

class sync extends Command
{

    protected $signature = 'sync {type=0}';
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

//        if (in_array($type, [0, 1])) $this->_handleProcess();
//        if (in_array($type, [0, 2])) $this->_del();
//        if (in_array($type, [0, 3])) $this->_setNick();
//        if (in_array($type, [0, 3])) $this->_Token();
//        if (in_array($type, [0, 5])) $this->_UpdateImg();
//        if (in_array($type, [0, 6])) $this->_uploadOss();
//        if (in_array($type, [0, 7])) $this->_merge();
        if (in_array($type, [0, 8])) $this->_setAlbumNum();
//        if (in_array($type, [0, 9])) $this->_setUserInfo();
    }

    private function _setUserInfo()
    {
        UsersModel::where('id', '>', 0)->chunk(100, function ($items) {
            foreach ($items as $item) {
                if ($item->live_location && stripos($item->live_location, '省') !== false) {
                    $item->live_location = explode('省', $item->live_location)[1];
                }
                if ($item->last_location && stripos($item->last_location, '省') !== false) {
                    $item->last_location = explode('省', $item->last_location)[1];
                }
                $item->save();
            }
        });
    }


    private function _setAlbumNum()
    {
        UsersProfileModel::where('id', '>', 0)->chunk(100, function ($items) {
            foreach ($items as $item) {
                $num = empty($item->album) ? 0 : count($item->album);
                HR::userAlbumNumUpdate($item->user_id, $num, true);
            }
        });
    }


    private function _uploadOss()
    {
        UsersModel::where('status', 1)->chunk(100, function ($items) {
            foreach ($items as $item) {
                if (stripos($item->avatar, 'seetpark') === false) continue;
                $item->avatar = str_replace('!sm', '', $item->avatar);
                $aliyunBuket = str_replace('http://static.seetpark.com/', '', $item->avatar);
                $location = "/tmp/" . $aliyunBuket;
                if (H::downfile($item->avatar, $location)) {
                    $localRule = (AliyunOss::getInstance())->uploadToOss($aliyunBuket, $location);
                    if (stripos($localRule, 'zfriend.oss-cn-hangzhou') !== false) {
                        unlink($location);
                        //更新本地地址
                        $tar_url = "http://static.hfriend.cn/" . $aliyunBuket . '!mid';
                        $item->avatar = $tar_url;
                        $item->save();
                        file_put_contents("/tmp/xxx.log",
                            print_r([$localRule, $item->avatar, $aliyunBuket, $item->id, $location, $tar_url], 1) . PHP_EOL,
                            FILE_APPEND);
                    }
                }
            }
        });

    }

    private function _merge()
    {
        UsersProfileModel::chunk(100, function ($items) {
            foreach ($items as $item) {
                //判断重复
                if (!empty($item->album)) {
                    $album = $item->album;
                    $albumRet = $item->album;
                    $yes = false;
                    foreach ($album as $k => $alb) {
                        if (stripos($alb['img_url'], 'zfriend') !== false) continue;
                        $yes = true;
                        $aliyunBuket = str_replace('http://static.seetpark.com/', '', $alb['img_url']);
                        $aliyunBuket = str_replace('http://static.tianmiapp.com/', '', $aliyunBuket);
                        $location = "/tmp/" . $aliyunBuket;
                        if (H::downfile($alb['img_url'], $location)) {
                            $localRule = (AliyunOss::getInstance())->uploadToOss($aliyunBuket, $location);
                            if (stripos($localRule, 'zfriend.oss-cn-hangzhou') !== false) {
                                unlink($location);
                                //更新本地地址
                                $tar_url = "http://static.hfriend.cn/" . $aliyunBuket;
                                $album[$k]['img_url'] = $tar_url;
                            }
                        }
                    }
                    $item->album = $album;
                    $item->save();
                    if ($yes) {
                        //dd($item->user_id, $item->album, $albumRet);
                        echo $item->user_id . PHP_EOL;
                    }
                }


                //去重
//                if ($item->album_bak != 1) continue;
//                $alm = [];
//                if (!empty($item->album)) {
//                    foreach ($item->album as $it) {
//                        $alm[$it['id']] = $it;
//                    }å
//                    $item->album = array_values($alm);
//                    $item->album_bak = null;
//                    $item->save();
//                }
            }
        });
    }


    private function _handleProcess()
    {
        $i = 0;
        DB::connection('sweet_park')->table('users_profile')
            ->select(['users.album', 'users.avatar', 'users.id'])
            ->leftJoin('users', 'users.id', '=', 'users_profile.user_id')
            ->where('users.avatar', '!=', '')->whereNotNull('users.avatar')
            ->where("users.album", "!=", '')->whereNotNull('users.album')
            ->orderBy('id', 'desc')->chunk(100, function ($items) use (&$i) {
                foreach ($items as $item) {
                    $user = UsersModel::where('avatar', 'like', '%' . $item->avatar . '%')->first();
                    if ($user) {
                        try {
                            UsersProfileModel::where('user_id', $user->id)->update(['album_bak' => $item->album]);
                            file_put_contents("/tmp/xxx.log", print_r([$user->id, $item], 1) . PHP_EOL, FILE_APPEND) . PHP_EOL;
                        } catch (\Exception $e) {
                            echo $e->getMessage() . PHP_EOL;
                        }

                    }
                }
            });
    }


    private function _UpdateImg()
    {
        UsersModel::where('fake', '>', 0)->chunk(100, function ($items) use (&$i) {
            foreach ($items as $item) {
                $profile = UsersProfileModel::getUserInfo($item->id);
                $album = [];
                if ($profile->album) {
                    foreach ($profile->album as $img) {
                        if (stripos($img['img_url'], 'seetpark') === false) {
                            $album[] = $img;
                        }
                    }
                }
                $profile->album = $album;
                $profile->save();
            }

        });
    }

    private function _Token()
    {
        $users = UsersModel::where([['id', '>=', 141491], ['id', '<=', 186690]])->get();
        if (!$users->isEmpty()) {
            foreach ($users as $user) {
                $rong = RongCloud::getToken($user->id, $user->nick, $user->avatar);
                $user->rong_token = $rong['token'];
                $user->save();
            }
        }
    }

    private function _setNewNick()
    {
        $users = UsersModel::where([['id', '>=', 141491], ['id', '<', 186690]])->get();
        foreach ($users as $user) {
            $user->nick = str_replace('😆', '', $user->nick);
            $user->nick = str_replace('😆', '', $user->nick);
            $user->nick = str_replace('⭐️', '', $user->nick);
            $user->nick = str_replace('☘️', '', $user->nick);
            $user->nick = str_replace('❄️️', '', $user->nick);
            $user->nick = str_replace('☂️️', '', $user->nick);
            $user->nick = str_replace('☀️️️', '', $user->nick);
            $user->nick = str_replace('⛅️️️', '', $user->nick);
            $user->nick = str_replace('⛄️️️️', '', $user->nick);
            $user->nick = str_replace('☔️️️️', '', $user->nick);
            $user->nick = str_replace('⚡️️️', '', $user->nick);
            $user->save();
        }

    }

    private function _setNick()
    {
        $users = UsersModel::where([['id', '>=', 141491], ['id', '<', 186690]])->where('sex', 2)->get();
        $libs = LibNickModel::where('gender', 2)->get();
        $nicks = [];
        foreach ($libs as $lib) {
            $nicks[] = $lib->nick;
        }
        if (!$users->isEmpty()) {
            foreach ($users as $user) {
                $user->nick = $nicks[rand(0, count($nicks) - 1)];
                $user->save();
            }
        }
    }

    private function _setBio()
    {
        $users = UsersProfileModel::where('bio', '')->get();
        if (!$users->isEmpty()) {
            foreach ($users as $user) {
                $rand = rand(1, 146033);
                $libs = LibBioTextModel::where('id', $rand)->first();
                $user->bio = $libs->content;
                $user->save();
            }
        }
    }

    private function _coor()
    {
        $users = UsersModel::where('fake', '>', 0)->get();
        foreach ($users as $user) {
            $profile = UsersProfileModel::getUserInfo($user->id);
            $coor = $user->live_coordinates;
            if (strlen($coor) < 10) {
                $coor = $user->last_coordinates;
            }
            if (strlen($coor) < 10) {
                $coor = $user->register_coordinates;
            }
            if (strlen($coor) < 10) {
                continue;
            }
            if (mb_strlen($user->last_location) > 8) {
                $user->last_location = $user->live_location = (new BaiduCloud())->getCityByPoint($coor);
                echo $user->last_location . PHP_EOL;
                $user->save();
                $profile->register_location = $user->last_location;
                $profile->save();
            }
        }
    }

    private function _insertDb($item)
    {
        $item->mobile = H::encrypt(self::decrypt($item->mobile));
        $item->wechat = H::encrypt(self::decrypt($item->wechat));
        try {
            DB::beginTransaction();
            $user = UsersModel::create([
                'mobile' => $item->mobile,
                'password' => $item->password,
                'sweet_coin' => 0,
                'last_ip' => $item->last_ip,
                'last_login' => $item->last_login,
                'last_location' => $item->last_location,
                'last_coordinates' => $item->last_coordinates,
                'live_coordinates' => $item->live_coordinates,
                'live_location' => $item->last_location,
                'live_time_latest' => $item->live_time_latest,
                'nick' => $item->nick,
                'avatar' => $item->avatar,
                'sex' => $item->sex,
                'birthday' => $item->birthday,
                'constellation' => $item->constellation,
                'salt' => $item->salt,
                'invited' => '',
                'online' => 1,
                'status' => 1,
                'device' => '',
                'device_lock' => 0,
                'fake' => $item->user_id,
            ]);
            //生成用户的id
            $user->platform_id = H::getPlatformId($user->id);
            $user->uinvite_code = H::createInviteCodeById($user->id);
            //获取融云用户id
//            try {
//                $rongToken = RongCloud::getToken($user->id, $data['nick'], $data['avatar']);
//            } catch (\Exception $e) {
//                MessageModel::gainLog($e, __FILE__, __LINE__);
//            }
//            $user->rong_token = isset($rongToken['token']) ? $rongToken['token'] : '';
            $user->rong_token = null;
            $user->save();
            //创建扩展信息
            UsersProfileModel::create([
                'user_id' => $user->id,
                'register_coordinates' => $item->register_coordinates,
                'register_location' => $item->register_location,
                'register_ip' => $item->register_ip,
                'register_date' => $item->register_date,
                'register_channel' => $item->register_channel,
                'register_device' => '',
                'live_addr' => $item->live_addr,
                'mobile' => $item->mobile,
                'bio' => $item->bio ? $item->bio : '',
                'wechat' => $item->wechat,
                'real_is' => $item->real_is,
                'real_at' => $item->real_at,
                'auth_pic' => $item->auth_pic,
                'profession' => $item->profession,
                'album' => !is_array($item->album) ? json_decode($item->album, 1) : $item->album,
                'stature' => $item->stature > 0 ? $item->stature . 'cm' : 0,
            ]);
            //创建设置信息裱
            UsersSettingsModel::create([
                'user_id' => $user->id,
                'hide_model' => 0,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return null;
        }
    }

    //删除微信为？的用户
    private static function _del()
    {
        $profile = UsersProfileModel::where('wechat', '!!&c$%^#":[!&!!!"!!!"2')->get();
        if (!$profile->isEmpty()) {
            foreach ($profile as $pro) {
                try {
                    UsersSettingsModel::where('user_id', $pro->user_id)->delete();
                    UsersProfileModel::where('user_id', $pro->user_id)->delete();
                    UsersModel::where('id', $pro->user_id)->delete();
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
        }
    }

    //加解密
    public static function encrypt($str)
    {
        $encrypt = new Encrypt();
        return $encrypt->encrypt($str, 'sa-qin-mi-gong-yuan', 1, 2);
    }

    public static function decrypt($str)
    {
        if (stripos($str, '!!&c$%') === false) {
            return $str;
        }
        $decrypt = new Decrypt();
        return $decrypt->decrypt($str, 'sa-qin-mi-gong-yuan');
    }
}
