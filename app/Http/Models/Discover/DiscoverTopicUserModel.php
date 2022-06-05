<?php

namespace App\Http\Models\Discover;

use App\Http\Helpers\R;
use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;

class DiscoverTopicUserModel extends Model
{
    protected $guarded = [];
    protected $table = 'discover_topic_user';
    protected $hidden = ['created_at', 'updated_at', 'date'];

    //判断一个话题是否关注过
    public static function getUserTopicIdArr($user_id, $topic = '')
    {
        $res = [];
        $builder = self::where([['status', 1], ['user_id', $user_id]]);
        if (!empty($topic)) {
            $builder->where('topic_id', $topic);
        }
        $topic = $builder->pluck('topic_id');
        if ($topic) {
            $res = $topic->toArray();
        }
        return $res;
    }


    public static function getUserTopic($uid, $size = 9, $page = 1)
    {
        $res = $ret = [];
        $builder = self::where([['status', 1], ['user_id', $uid]])->orderBy('updated_at', 'desc');
        $topicIdArr = $builder->skip(($page - 1) * $size)->take($size)->pluck('topic_id')->toArray();
        $topic = DiscoverTopicModel::whereIn('stid', $topicIdArr)->get();
        foreach ($topic as $item) {
            $res[$item->stid] = [
                'stid' => $item->stid,
                'title' => $item->title,
                'total' => $item->total,
                'total_desc' => "{$item->total} 条动态",
            ];
        }
        if ($topicIdArr) {
            foreach ($topicIdArr as $topicId) {
                $ret[] = $res[$topicId];
            }
        }
        return $ret;

    }

    public static function batchIntoFollow($uid, $stid, $status)
    {
        try {
            //添加关注记录
            self::updateOrCreate([
                'user_id' => $uid,
                'topic_id' => $stid,
            ], [
                'user_id' => $uid,
                'date' => date('Y-m-d'),
                'topic_id' => $stid,
                'status' => $status
            ]);
            if ($status == 1) {
                DiscoverTopicModel::where([['stid', $stid], ['status', 1]])->increment('followed_num');
            } else {
                DiscoverTopicModel::where([['stid', $stid], ['status', 1]])->decrement('followed_num');
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            throw new \Exception($e->getMessage());
        }
    }

}
