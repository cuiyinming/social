<?php

namespace App\Http\Models\Users;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Models\Discover\DiscoverModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsersMsgModel extends Model
{
    protected $guarded = [];
    protected $table = 'user_msg';

    //添加记录
    public static function gainUserMsg($uid, $discover, $event = 'discover_zan', $status = 1, $cmt = '')
    {
        \App\Jobs\discoverNotice::dispatch($discover, $uid, $event, $status, $cmt)->onQueue('discover');
    }


    public static function clearData($uid)
    {
        return self::where('user_id', $uid)->update(['delete' => 1]);
    }

    //规范化scheme 跳转字典
    public static function schemeUrl($jump_url, $jump_scheme, $title, $jump_scheme_id = 0, $button = '立即查看'): array
    {
        $res = [
            'jump' => !empty($jump_url) || !empty($jump_scheme),
            'jump_title' => $title,
            'jump_url' => !empty($jump_url) ? $jump_url : '',
            'jump_scheme' => !empty($jump_scheme) ? $jump_scheme : '',//跳转的scheme的字典
            'jump_scheme_id' => $jump_scheme_id, //scheme 附带的用户id,详情id 等信息用于直接到目的页面
        ];
        if (!empty($button)) $res['jump_tip'] = $button;
        return $res;
    }

    //获取用户的分页信息
    public static function getUserMsgPageData($uid, $type, $page, $size)
    {
        $builder = self::select(['id', 'trigger_id', 'event_id', 'cont', 'created_at'])->where([['user_id', $uid], ['type', $type], ['delete', 0]])->orderBy('id', 'desc');
        $count = $builder->count();
        $datas = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            $idArr = $disArr = [];
            foreach ($datas as &$data) {
                $data->time_str = H::exchangeDateStr($data->created_at);
                unset($data->created_at);
                $idArr[] = $data->id;
                $disArr[] = $data->event_id;
                $userModel = UsersModel::select(['id', 'nick', 'avatar'])->where('id', $data->trigger_id)->first();
                $data->user_info = $userModel ? ['user_id' => $userModel->id, 'nick' => $userModel->nick, 'avatar' => $userModel->avatar] : [];
            }
            self::whereIn('id', $idArr)->update(['read' => 1, 'read_at' => CORE_TIME]);
            //渲染event
            $discover = DiscoverModel::getDiscoverCoverImg($disArr, 'single');
            foreach ($datas as &$item) {
                $item->album = isset($discover[$item->event_id]) ? $discover[$item->event_id] : '';
            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }

}
