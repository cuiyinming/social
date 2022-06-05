<?php

namespace App\Http\Models\Discover;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use App\Http\Helpers\S;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\HR;

class DiscoverModel extends Model
{
    protected $guarded = [];
    protected $table = 'discover';
    protected $casts = [
        'tags' => 'json', // 声明json类型
        'album' => 'json',
        'sound' => 'json',
        'location' => 'json'
    ];
    protected $hidden = ['updated_at', 'sign', 'lat', 'lng'];

    // 排除的人  我关注的人和好友 当前登陆用户id
    public static function getDiscovers($user_id, $sex)
    {
        $exclude = UsersModel::getExcludeIdArr($user_id);
        $builder = DiscoverModel::select('*')->where('status', 1)->whereNotIn('user_id', $exclude);
        $builder->where(function ($query) use ($sex, $user_id) {
            $query->where('user_id', $user_id)->orWhere(function ($qry) use ($user_id, $sex) {
                $qry->where(function ($item) use ($sex) {
                    //过滤不对同性展示的部分
                    $item->where('show_on', 1)->orWhere([['show_on', 0], ['sex', '!=', $sex]]);
                })->where(function ($private) use ($user_id) {
                    //对隐私性进行过滤 & 过滤仅自己可见部分-----S----
                    $private->where('private', 0)->orWhere(function ($privateTwo) use ($user_id) {
                        //过滤好友及关注我的,我查看的是别人，获取我的关注和好友  我能看到的只有关注人公开的
                        $friendsFollow = UsersFollowModel::getFriendAndFollow($user_id);
                        $privateTwo->where('private', 2)->whereNotIn('user_id', $friendsFollow);
                    });
                });
            });
        });
        return $builder;
    }

    // 获取用户的动态快照
    public static function getSnapshotById($user_id = 0, $sex = 1, $self = false)
    {
        $res = [];
        if ($self) {
            $builder = self::where([['user_id', $user_id], ['status', 1]])->whereNotNull('album')->orderBy('id', 'desc');
        } else {
            //如果不是自己需要过滤不应该显示的内容
            //dd($user_id,$sex,$self);
            $builder = self::where([['user_id', $user_id], ['status', 1]])->whereNotNull('album')->orderBy('id', 'desc')->where(function ($query) use ($sex) {
                $query->where('show_on', 1)->orWhere([['show_on', 0], ['sex', '!=', $sex]]);
            })->where(function ($private) use ($user_id) {
                //对隐私性进行过滤 & 过滤仅自己可见部分-----S----
                $private->where('private', 0)->orWhere(function ($privateTwo) use ($user_id) {
                    //过滤好友及关注我的,我查看的是别人，获取我的关注和好友  我能看到的只有关注人公开的
                    $friendsFollow = UsersFollowModel::getFriendAndFollow($user_id);
                    $privateTwo->where('private', 2)->whereNotIn('user_id', $friendsFollow);
                });
            });
        }
        $snapshot = $builder->limit(8)->get();
        $count = $builder->count();
        if (!$snapshot->isEmpty()) {
            foreach ($snapshot as $snap) {
                if (count($snap->album) > 0) {
                    $album = $snap->album[0];
                    if (isset($album['is_illegal']) && $album['is_illegal'] == 1) {
                        $album['img_url'] = H::errUrl('album');
                    }
                    $res[] = [
                        'discover_id' => $snap->id,
                        'snapshot' => $album
                    ];
                }
            }
        }
        return ['items' => $res, 'count' => $count];
    }

    public static function getDataByPage($q, $status, $comment_on, $show_on, $date, $type, $sex, $private, $page = 1, $size = 20, $id = 0)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder = $builder->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('id', $q)->orWhere('user_id', $q);
                } else {
                    $query->where('cont', 'like', '%' . $q . '%');
                }
            });
        }
        if (!is_null($id) && $id > 0) {
            $builder->where('id', $id);
        }
        if (!is_null($sex)) {
            $builder->where('sex', $sex);
        }
        if (!is_null($private)) {
            $builder->where('private', $private);
        }
        if (!is_null($comment_on)) {
            $builder->where('cmt_on', $comment_on);
        }
        if (!is_null($show_on)) {
            $builder->where('show_on', $show_on);
        }
        if (!is_null($type)) {
            $builder->where('type', $type);
        }
        if (!is_null($status)) {
            $builder->where('status', $status);
        }
        if (!is_null($date) && count($date) > 0) {
            $builder->whereBetween('post_at', [$date[0], $date[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$items->isEmpty()) {
            foreach ($items as &$data) {
                //二次渲染
                $albumArr = $data->album ? $data->album : [];
                if (is_array($albumArr) && count($albumArr) > 0) {
                    foreach ($albumArr as $k => $album) {
                        $albumArr[$k]['is_illegal'] = isset($album['is_illegal']) && $album['is_illegal'] == 1;
                    }
                }
                $data->location = $data->location ? $data->location : [];
                $data->status = $data->status == 1;
                $data->album_length = $data->album ? count($data->album) : 0;
                $data->album = $albumArr;
                $data->private_str = $data->private == 0 ? '公开' : ($data->private == 1 ? '仅自己可见' : '仅陌生人可见');
            }
        }
        return [
            'items' => $items ? $items : [],
            'count' => $count
        ];
    }

    //对获取到的discovers 进行渲染处理
    public static function processDiscover($uid, $discover, $userInfo, $channel = 'single')
    {
        //动态信息
        $idArr = [];
        foreach ($discover as &$dis) {
            $dis->date_str = H::exchangeDateStr($dis->post_at);
            $dis->num_view_str = H::getNumStr($dis->num_view) . ' 次浏览';
            if ($dis->hide == 1) {
                $dis->cont = '动态内容审核中...';
            }

            if ($channel == 'single') {
                $dis->share = self::getDiscoverShareInfo($dis->id, $userInfo, $dis);
            } else {
                $dis->user_info = $userInfo[$dis->user_id] ?? [];
                $dis->share = self::getDiscoverShareInfo($dis->id, $dis->user_info, $dis);
            }
            self::tagAndAlbum($dis);
            $idArr[] = $dis->id;
            //在这里补充打招呼显示逻辑
            $dis->say_hi = HR::existUniqueNum($uid, $dis->user_id, 'say-hi-num') != 1 && $uid != $dis->user_id;
        }
        $hasZan = DiscoverZanModel::getZanDiscover($uid, $idArr);
        foreach ($discover as &$disc) {
            $disc->is_zan = in_array($disc->id, $hasZan) ? 1 : 0;
        }
        self::whereIn('id', $idArr)->increment('num_view');
        return $discover;
    }

    public static function tagAndAlbum(&$dis)
    {
        if ($dis->tags) {
            $tags = [];
            foreach ($dis->tags as $k => $tag) {
                $tag['color'] = '#eeeeee';
                $tag['text_color'] = '#191919';
                $tag['tag'] = '#' . $tag['tag'];
                $tags[$k] = $tag;
            }
            $dis->tags = $tags;
        }
        //相册图片违规
        $album = $dis->album;
        if (!empty($album)) {
            foreach ($album as $ks => $pic) {
                if ($pic['is_illegal'] == 1) $album[$ks]['img_url'] = H::errUrl('img');
            }
            $dis->album = $album;
        }
        //违规语音处理
        $sound = $dis->sound;
        if (!empty($sound) && isset($sound['is_illegal']) && $sound['is_illegal'] == 1) {
            $sound['url'] = H::errUrl('sound');
            $dis->sound = $sound;
        }
    }

    public static function getDiscoverShareInfo($id, $user, $discover)
    {
        $share['url'] = [
            'qq' => 'https://bqimu8.jgmlink.cn/AAyG?channel=discover&id=' . $id,
            'wechat' => 'https://bqimu8.jgmlink.cn/AAyG?channel=discover&id=' . $id,
        ];
        try {
            $avatar = $user['avatar'] ?? '';
            $nick = $user['nick'] ?? '';
            $album = (isset($discover->album) && !empty($discover->album)) ? $discover->album[0]['img_url'] : $avatar;
            $share['title'] = '我的好友' . $nick . '发布了新的动态，快看看他说了些什么吧！';
            $share['text'] = $discover->cont ?: '';
            $share['avatar'] = $album;
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            //file_put_contents('/tmp/err.log', print_r([$id, $user, $discover], 1) . PHP_EOL, FILE_APPEND);
        }
        return $share;
    }

    //获取discover 的封面图
    public static function getDiscoverCoverImg($id, $type = 'all'): array
    {
        $res = [];
        if (is_array($id)) {
            $discover = self::whereIn('id', $id)->where('status', 1)->get();
        } else {
            $discover = self::where([['id', $id], ['status', 1]])->get();
        }
        if (!$discover->isEmpty()) {
            foreach ($discover as $dis) {
                $album = $dis->album;
                if ($album && count($album) > 0) {
                    $res[$dis->id] = $type == 'all' ? $dis->album : $dis->album[0]['img_url'];
                }
            }
        }
        return $res;
    }
}


