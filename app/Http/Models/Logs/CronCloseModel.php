<?php

namespace App\Http\Models\Logs;

use App\Components\ESearch\ESearch;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Models\Admin\ActiveLogModel;
use App\Http\Models\Discover\DiscoverCmtModel;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Discover\DiscoverZanModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\Resource\AlbumModel;
use App\Http\Models\Resource\AvatarModel;
use App\Http\Models\Resource\ResourceModel;
use App\Http\Models\Resource\UploadModel;
use App\Http\Models\System\FeedbackModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersFollowSoundModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CronCloseModel extends Model
{
    //操作定时注销用户信息的定时任务
    protected $guarded = [];
    protected $table = 'cron_close';

    public static function gainCron($uid, $job_code, $desc = '', $start_time = null, $info = '')
    {
        $start_time = is_null($start_time) ? date('Y-m-d H:i:s') : $start_time;
        self::create([
            'user_id' => $uid,
            'job_code' => $job_code,
            'plan_time' => $start_time,
            'desc' => $desc,
            'status' => 0,
            'info' => $info,
        ]);
    }

    public static function closeAccount($uid)
    {
        try {
            $user = UsersModel::where('id', $uid)->get();
            if (!$user->isEmpty()) {
                DB::connection('close')->table('users')->insert($user->toArray());
            }
            $profile = UsersProfileModel::where('user_id', $uid)->get();
            if (!$profile->isEmpty()) {
                $col = ['tags', 'hobby_sport', 'hobby_music', 'hobby_food', 'hobby_movie', 'hobby_book', 'hobby_footprint', 'album', 'album_video', 'sound', 'sound_pending'];
                foreach ($profile as &$pro) {
                    foreach ($col as $item) {
                        $pro->$item = json_encode($pro->$item);
                    }
                }
                DB::connection('close')->table('users_profile')->insert($profile->toArray());
            }
            $settings = UsersSettingsModel::where('user_id', $uid)->get();
            if (!$settings->isEmpty()) {
                DB::connection('close')->table('users_settings')->insert($settings->toArray());
            }
            self::delUser($uid);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }


    public static function delUser($uid)
    {
        //资源信息 & 删文件 & 删除本地图片资源
        $uploads = UploadModel::where('user_id', $uid)->get();
        $albums = AlbumModel::where('user_id', $uid)->get();
        $avatars = AvatarModel::where('user_id', $uid)->get();
        if (!$avatars->isEmpty()) {
            foreach ($avatars as $avatar) {
                ResourceModel::deleteResource($avatar);
            }
        }
        if (!$uploads->isEmpty()) {
            foreach ($uploads as $upload) {
                ResourceModel::deleteResource($upload);
            }
        }
        if (!$albums->isEmpty()) {
            foreach ($albums as $album) {
                ResourceModel::deleteResource($album);
            }
        }
        UploadModel::where('user_id', $uid)->delete();
        AlbumModel::where('user_id', $uid)->delete();
        AvatarModel::where('user_id', $uid)->delete();
        //关注信息
        LogContactUnlockModel::where('user_id', $uid)->delete();
        LogAlbumViewModel::where('user_id', $uid)->delete();
        DiscoverZanModel::where('user_id', $uid)->delete();
        UsersSettingsModel::where('user_id', $uid)->delete();
        LogAuthModel::where('user_id', $uid)->delete();
        UsersFollowModel::where('user_id', $uid)->orWhere('follow_id', $uid)->delete();
        UsersFollowSoundModel::where('user_id', $uid)->delete();
        LogSoundLikeModel::where('user_id', $uid)->orWhere('sound_user_id', $uid)->delete();
        //黑名单
        UsersBlackListModel::where('user_id', $uid)->delete();
        //亲密度及相关
        LogSweetModel::where('user_id', $uid)->orWhere('user_id_receive', $uid)->delete();
        LogSweetUniqueModel::where('user_both', $uid)->orWhere('both_user', $uid)->delete();
        LogGiftReceiveModel::where('user_id', $uid)->delete();
        LogGiftSendModel::where('user_id', $uid)->delete();
        //操作日志 & 日志
        LoginErrModel::where('user_id', $uid)->delete();
        LoginLogModel::where([['user_id', $uid], ['type', 0]])->delete();
        FeedbackModel::where('user_id', $uid)->delete();
        LogBalanceModel::where('user_id', $uid)->delete();
        ActiveLogModel::where([['uid', $uid], ['type', 0]])->delete();
        //订单信息
        OrderModel::where('user_id', $uid)->delete();
        //删除用户
        UsersProfileModel::where('user_id', $uid)->delete();
        LogTokenModel::where('user_id', $uid)->delete();
        UsersModel::where('id', $uid)->delete();
        //删除聊天记录
        LogImChatModel::where('user_id', $uid)->orWhere('target_user_id', $uid)->delete();
        //删除消息
        UsersMsgModel::where('user_id', $uid)->orWhere('trigger_id', $uid)->delete();
        UsersMsgNoticeModel::where('user_id', $uid)->delete();
        UsersMsgSysModel::where('user_id', $uid)->delete();
        //清理redis
        HR::delActiveTime($uid);
        HR::delActiveCoordinate($uid);
        //清理单点登录的缓存
        HR::signLoginDel($uid);
        //清理ES 数据
        (new ESearch('users:users'))->deleteSingle([['id' => $uid]]);
        //开始清除用户数据
        DiscoverZanModel::where('user_id', $uid)->delete();
        DiscoverCmtModel::where('user_id', $uid)->delete();
        //点赞信息
        $discover = DiscoverModel::where('user_id', $uid)->get();
        if (!$discover->isEmpty()) {
            foreach ($discover as $dis) {
                $dis->delete();
//                try {
//                    (new ESearch('discover:discover'))->deleteSingle([['id' => $dis->id]]);
//                } catch (\Exception $e) {
//                    MessageModel::gainLog($e, __FILE__, __LINE__);
//                }
            }
        }
    }
}
