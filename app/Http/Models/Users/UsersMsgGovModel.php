<?php

namespace App\Http\Models\Users;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsersMsgGovModel extends Model
{

    protected $guarded = [];
    protected $table = 'user_msg_gov';

    //获取用户的分页信息
    public static function getUserMsgPageData($page, $size = 20)
    {
        $builder = self::select(['type', 'title', 'cont', 'cover', 'jump_url', 'jump_scheme', 'jump_scheme_id', 'created_at'])->where('status', 1)->orderBy('id', 'desc');
        $count = $builder->count();
        $datas = $builder->skip(($page - 1) * $size)->take($size)->get();
        $items = [];
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {
                $items[] = [
                    'type' => $data->type,
                    'title' => $data->title,
                    'cont' => $data->cont,
                    'cover' => $data->cover,
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
