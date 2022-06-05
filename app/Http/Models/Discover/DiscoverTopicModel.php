<?php

namespace App\Http\Models\Discover;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class DiscoverTopicModel extends Model
{
    protected $guarded = [];
    protected $table = 'discover_topic';
    protected $hidden = ['updated_at', 'collect', 'tid'];

    public function topic()
    {
        return $this->hasOne('App\Http\Models\Discover\DiscoverTopicUserModel', 'topic_id');
    }

    public static function createByRow($tag, $user_id = 0)
    {
        $sign = md5(trim($tag));
        $exist = self::where('sign', $sign)->first();
        if ($exist) {
            return $exist;
        }
        return self::create([
            'user_id' => $user_id,
            'status' => 1,
            'recommend' => 0,
            'collect' => 1,
            'category' => 'topic',
            'sign' => $sign,
            'title' => $tag,
            'tid' => 0,
            'stid' => H::gainStrId(),
        ]);
    }

    public static function getPageItems($page, $size, $q, $uid = 0)
    {
        $builder = self::where('status', 1)->orderBy('total', 'desc');
        if (!is_null($q)) {
            $builder->where('title', 'like', '%' . $q . '%');
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$items->isEmpty()) {
            //渲染
            $topicArr = [];
            foreach ($items as &$item) {
                $item['total_desc'] = "{$item->total} 条动态";
                $item['topic_follow_is'] = 1;
                unset($item['sign']);
                unset($item['id']);
                unset($item['created_at']);
                unset($item['weight']);
                $topicArr[] = $item->topic_id;
            }
            //二次渲染
            $topic = DiscoverTopicUserModel::where([['user_id', $uid], ['status', 1]])->whereIn('topic_id', $topicArr)->pluck('topic_id')->toArray();
            if (count($topic) > 0) {
                foreach ($items as &$top) {
                    $top['topic_follow_is'] = in_array($top->topic_id, $topic) ? 1 : 0;
                }
            }
        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

    //获取推荐的话题
    public static function getLimitTopicData($limit, $order = 'recommend', $user_id = 0, $followed = false)
    {
        $userTopic = [];
        if ($user_id > 0) {
            $userTopic = DiscoverTopicUserModel::getUserTopicIdArr($user_id);
        }
        $builder = self::select(['stid', 'title', 'total', 'recommend', 'created_at'])->where('status', 1);
        if ($followed) {
            $builder->whereIn('stid', $userTopic);
        }
        if ($order == 'recommend') {
            $builder->orderBy('recommend', 'desc');
        } else if ($order == 'new') {
            $builder->orderBy('created_at', 'desc');
        } else {
            $builder->orderBy('total', 'desc');
        }
        $items = $builder->limit($limit)->get();
        if (!$items->isEmpty()) {
            //渲染
            foreach ($items as &$item) {
                $item['follow'] = in_array($item->stid, $userTopic) ? 1 : 0;
                $item['total_desc'] = "{$item->total} 条动态";
                unset($item['sign']);
                unset($item['id']);
                unset($item['created_at']);
            }
        }
        return $items;
    }


    //管理端
    public static function getDataByPage($q, $status, $date = [], $page = 1, $size = 20)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder = $builder->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('tid', $q)->orWhere('user_id', $q);
                } else {
                    $query->where('title', 'like', '%' . $q . '%')->orWhere('subtitle', 'like', '%' . $q . '%')->orWhere('stid', 'like', '%' . $q . '%');
                }
            });
        }
        if (!is_null($status)) {
            $builder->where('status', $status);
        }
        if (count($date) > 0) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$items->isEmpty()) {
            foreach ($items as &$data) {

            }
        }
        return [
            'items' => $items ? $items : [],
            'count' => $count
        ];
    }
}
