<?php

namespace App\Http\Models\Users;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RongCloud;

class UsersMsgSysModel extends Model
{

    protected $guarded = [];
    protected $table = 'user_msg_sys';

    public static function storeMsg($user_id, $msg, $event_id = 0, $jump_scheme = null)
    {
        // 1 完善资料页面， 2 实名认证, 3 更换头像,  4 语音签名,5 指定话题列表, 6 真人认证, 7 跳转VIP页面,8 关注 -> 首页列表,9 动态评论 -> 动态首页,
        // 10 完善相册 -> 相册编辑页面,11 录音签名 -> 语音签名页面, 12 首冲奖励 -> 充值页面,13 每日动态奖励 -> 发动态, 14 私信聊天 -> 首页列表, 15 语音通话 ->  首页列表
        // 16 女神认证, 17 每日签到 --> 任务列表, 18 跳转完善QQ,19 跳转完善微信
        if (in_array($msg['type'], ['avatar_check', 'avatar_cmp', 'sound_check', 'check_qq', 'check_wechat', 'video_check', 'contact_check', 'album_lock', 'invite_auth', 'invite_contact', 'first_recharge', 'first_vip'])) {
            if (in_array($msg['type'], ['avatar_check', 'avatar_cmp'])) $jump_scheme = 3;
            if (in_array($msg['type'], ['sound_check'])) $jump_scheme = 11;
            if (in_array($msg['type'], ['check_qq'])) $jump_scheme = 18;
            if (in_array($msg['type'], ['check_wechat', 'invite_contact'])) $jump_scheme = 19;
            if (in_array($msg['type'], ['contact_check'])) $jump_scheme = 3;
            if (in_array($msg['type'], ['album_lock'])) $jump_scheme = 10;
            if (in_array($msg['type'], ['invite_auth'])) $jump_scheme = 6;
            if (in_array($msg['type'], ['first_recharge'])) $jump_scheme = 7;
            if (in_array($msg['type'], ['first_vip'])) $jump_scheme = 12;
            //未读消息更新[站内信不在单独通知]
            //$sysMsg = ['content' => $msg['cont'], 'title' => $msg['title'], 'extra' => ""];
            //RongCloud::messageSystemPublish(101, [$user_id], 'RC:TxtMsg', json_encode($sysMsg));
        }
        $sysMsgData = [
            'user_id' => $user_id,
            'event_id' => $event_id,
            'title' => $msg['title'],
            'cont' => $msg['cont'],
            'event' => $msg['type'],
            'jump_scheme' => $jump_scheme,
        ];
        self::create($sysMsgData);
        UsersMsgNoticeModel::gainNoticeLog($user_id, 'site_notice', 1);
    }


    //获取用户的分页信息
    public static function getUserMsgPageData($page, $size = 20): array
    {
        $uid = Auth::user()->id;
        $builder = self::select(['event', 'title', 'cont', 'created_at', 'jump_url', 'jump_scheme', 'jump_scheme_id'])->where([['user_id', $uid], ['status', 1]])->orderBy('id', 'desc');
        $count = $builder->count();
        $datas = $builder->skip(($page - 1) * $size)->take($size)->get();
        $items = [];
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {
                $items[] = [
                    'event' => $data->event,
                    'title' => $data->title,
                    'cont' => $data->cont,
                    'created_at' => $data->created_at,
                    'time_str' => H::exchangeDateStr($data->created_at->format('Y-m-d H:i:s')),
                    'jump' => UsersMsgModel::schemeUrl($data->jump_url, $data->jump_scheme, $data->title, $data->jump_scheme_id),
                ];
            }
        }
        return [
            'items' => $items ? array_reverse($items) : [],
            'count' => $count,
        ];
    }

}
