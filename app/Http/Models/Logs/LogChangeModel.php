<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\R;
use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;

class LogChangeModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_change';

    public static function gainLog($user_id, $action, $value, $event = 0)
    {
        if (empty($value)) return false;
        $model = self::create([
            'user_id' => $user_id,
            'action' => $action,
            'value' => $value,
            'event_id' => $event
        ]);
        if ($event == 0) self::where([['user_id', $user_id], ['action', $action], ['id', '!=', $model->id]])->update(['status' => 0]);
        return $model;
    }

    public static function getDataByPage($page, $size, $q, $type, $status, $date)
    {
        $actionStr = function ($actionStr) {
            $actionMap = [
                'album' => '更改相册',
                'bio' => '更换签名',
                'nick' => '昵称修改',
                'contact_wechat' => '填写微信',
                'contact_qq' => '填写QQ',
                'avatar' => '更换头像',
                'video' => '更换视频',
                'sound_bio' => '语音签名',
                'discover_comment' => '动态评论',
                'discover_publish' => '动态发布',
                'real_auth' => '真人认证',
            ];
            return $actionMap[$actionStr] ?? '';
        };
        $orders = self::where('value', '!=', 'null')->orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('user_id', $q);
                } else {
                    $query->where('value', 'like', '%' . $q . '%');
                }
            });
        }
        if (!is_null($type)) {
            $orders->where('action', $type);
        }
        if (!is_null($status)) {
            $orders->where('status', $status);
        }
        if (!is_null($date) && count($date) > 1) {
            $orders->whereBetween('created_at', [$date[0], $date[1]]);
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {
                $data->action_name = $actionStr($data->action);
                if ($data->action == 'album') {
                    $dataArr = json_decode($data->value, 1);
                    //同步用户的相册
                    $userModel = UsersModel::where('id', $data->user_id)->first();
                    $userAlbumArr = !empty($userModel->album) ? $userModel->album : [];
                    $newArr = [];
                    if (count($userAlbumArr) > 0) {
                        foreach ($userAlbumArr as $userAlbum) {
                            $newArr[$userAlbum['id']] = $userAlbum['is_illegal'];
                        }
                    }
                    if (count($dataArr) > 0) {
                        foreach ($dataArr as $k => $item) {
                            $dataArr[$k]['is_illegal'] = isset($newArr[$item['id']]) && $newArr[$item['id']] == 1;
                        }
                    }
                    $data->value = $dataArr;
                }
                if ($data->action == 'video') {
                    $dataArr = json_decode($data->value, 1);
                    //同步用户的相册
                    $userModel = UsersModel::where('id', $data->user_id)->first();
                    $userAlbumArr = !empty($userModel->album_video) ? (is_array($userModel->album_video) ? $userModel->album_video : json_decode($userModel->album_video, 1)) : [];
                    $newArr = [];
                    if (count($userAlbumArr) > 0) {
                        foreach ($userAlbumArr as $userAlbum) {
                            $newArr[$userAlbum['id']] = $userAlbum['is_illegal'];
                        }
                    }
                    if (count($dataArr) > 0) {
                        foreach ($dataArr as $k => $item) {
                            $dataArr[$k]['is_illegal'] = isset($newArr[$item['id']]) && $newArr[$item['id']] == 1;
                            $dataArr[$k]['img_url'] = $item['img_url'] . '?x-oss-process=video/snapshot,t_1000,m_fast';
                            $dataArr[$k]['img_url_video'] = $item['img_url'];
                        }
                    }
                    $data->value = $dataArr;
                }
                if ($data->action == 'discover_publish') {
                    $publish = json_decode($data->value, 1);
                    if (is_null($publish['album'])) {
                        $publish['album'] = [];
                    }
                    if (is_null($publish['sound'])) {
                        $publish['sound'] = new \stdClass();
                    }
                    $data->value = $publish;
                }
                if ($data->action == 'sound_bio') {
                    $data->value = json_decode($data->value, 1);
                }
            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
