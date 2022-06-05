<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Libraries\Tools\ImageBlur;
use App\Http\Models\EsDataModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Database\Eloquent\Model;

class LogBrowseModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_browse';

    //获取浏览我的人的数据信息  [累计及后续新增]
    public static function browseMeCounter($uid = 0)
    {
        $be_viewed_num = self::where('user_id_viewed', $uid)->count();
        $new_view = HR::getUniqueNum($uid, 'users-be-viewed');
        $res['be_viewed_num'] = $be_viewed_num;
        $res['new_viewed_num'] = $new_view;
        $view_str = '';
        if ($be_viewed_num > 0 && $new_view > 0 && $be_viewed_num != $new_view) {
            $view_str = '访客新增' . $new_view . '人';
        }
        if ($be_viewed_num > 0 && $new_view > 0 && $be_viewed_num == $new_view) {
            $view_str = $be_viewed_num . '人看过你';
        }
        $res['view_str'] = $view_str;
        return $res;
    }

    //浏览我的
    public static function browseMe($user_id, $page, $size)
    {
        $res = $rank = [];
        //隐身 & 黑名单剔除 &隐身过滤
        $exclude = UsersFollowModel::_exclude($user_id);
        //查询是否为vip
        $profile = UsersProfileModel::getUserInfo($user_id);
        $is_vip = $profile->vip_is;
        //添加之前的浏览记录删除  【如果不是vip 就不删除】
        if ($is_vip != 0) {
            HR::clearUniqueNum($user_id);
        }
        //我的粉丝
        $itemsBuilder = self::where([['user_id_viewed', $user_id], ['user_id', '!=', $user_id]])->whereNotIn('user_id', $exclude);
        $builder = self::where([['user_id_viewed', $user_id], ['user_id', '!=', $user_id]])->whereNotIn('user_id', $exclude);
        $count = $builder->count();
        //追加了榜单
        $show = ($is_vip == 0 && $page > 1) ? false : true; //用于限定非vip 只能查看第一页
        $browseMe = $randList = [];
        //排行榜
        $ranks = $builder->orderBy('num', 'desc')->limit(3)->get();
        if (!$ranks->isEmpty()) {
            foreach ($ranks as $ran) {
                $randList[] = $ran->user_id;
            }
        }
        $items = $itemsBuilder->orderBy('updated_at', 'desc')->skip(($page - 1) * $size)->take($size)->get();
        if (!$items->isEmpty()) {
            foreach ($items as $item) {
                $browseMe[] = $item->user_id;
            }
        }
        $mergeIds = array_unique(array_merge($browseMe, $randList));
        $format = count($mergeIds) > 0 ? EsDataModel::mgetEsUserByIds(['ids' => $mergeIds]) : [];
        if (!empty($browseMe)) {
            foreach ($items as $item) {
                if (!isset($format[$item->user_id])) continue;
                $res[$item->user_id] = $format[$item->user_id];
                //昵称模糊处理
                if ($is_vip == 0) {
                    $res[$item->user_id]['nick_blur'] = H::getBlurNick($item->user_id);
                }
                $res[$item->user_id]['time_str'] = H::exchangeDate($item->updated_at);
                $res[$item->user_id]['num'] = $item->num;
            }
        }
        if (!empty($randList)) {
            foreach ($ranks as $item) {
                if (!isset($format[$item->user_id])) continue;
                $rank[$item->user_id] = $format[$item->user_id];
                //昵称模糊处理
                if ($is_vip == 0) {
                    $rank[$item->user_id]['nick_blur'] = H::getBlurNick($item->user_id);
                }
                $rank[$item->user_id]['time_str'] = '';
                $rank[$item->user_id]['num'] = $item->num;
            }
        }
        return [
            'rank' => $rank && $show ? array_values($rank) : [],
            'items' => $res && $show ? array_values($res) : [],
            'count' => $count,
            'show_unlock' => $is_vip == 0,
            'info_unlock' => [
                [
                    'title' => '查看访客方式1',
                    'cont' => '开通vip即可查看',
                    'scheme' => 1,
                    'jump' => UsersMsgModel::schemeUrl('', 7, '开通vip特权', 0, ''),
                    'button' => '立即开通',
                ], [
                    'title' => '查看访客方式2',
                    'cont' => '完成真人认证即可查看',
                    'jump' => UsersMsgModel::schemeUrl('', 6, '真人认证', 0, ''),
                    'button' => '立即认证',
                ]
            ]
        ];
    }

    //我的浏览
    public static function meBrowse($user_id, $page, $size)
    {
        $res = [];
        //隐身 & 黑名单剔除 &隐身过滤
        $exclude = UsersFollowModel::_exclude($user_id);
        //我的粉丝
        $builder = self::where([['user_id', $user_id], ['user_id_viewed', '!=', $user_id]])->whereNotIn('user_id_viewed', $exclude)->orderBy('updated_at', 'desc');
        $count = $builder->count();
        $meBrowse = [];
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$items->isEmpty()) {
            foreach ($items as $item) {
                $meBrowse[] = $item->user_id_viewed;
            }
        }
        if (!empty($meBrowse)) {
            $format = EsDataModel::mgetEsUserByIds(['ids' => $meBrowse]);
            foreach ($items as $item) {
                if (!isset($format[$item->user_id_viewed])) continue;
                $res[$item->user_id_viewed] = $format[$item->user_id_viewed];
                $res[$item->user_id_viewed]['time_str'] = H::timeStr($item->updated_at);
            }
        }
        return [
            'items' => $res ? array_values($res) : [],
            'count' => $count,
        ];
    }


    //管理后台列表
    public static function getDataByPage($page, $size, $q)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('user_id', $q)->orWhere('user_id_viewed', $q);
                } else {
                    $query->where('ip', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {

            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
