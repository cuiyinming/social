<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Models\EsDataModel;
use App\Http\Models\JpushModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Database\Eloquent\Model;

/**
 * 大体为打赏到100元即为亲密度最高【分别为6级】
 *亲密度算法：1000分为满分，0-80，80-150，150-260，260-400，400-700，700-1000
 * @package App\Http\Models\Logs
 */
class LogSweetUniqueModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_sweet_unique';


    public static function sweetGet($uid, $user_id): array
    {
        $res = [
            'sweet_level' => 0,
            'next_unit' => 80,
            'sweet' => 0,
            'level_percent' => '0%',
            'heart_bg' => 0,   //heart 一共有4个
        ];
        $sweet = self::where([['user_both', $uid], ['both_user', $user_id], ['status', 1]])->orWhere([['both_user', $uid], ['user_both', $user_id], ['status', 1]])->first();
        if ($sweet) {
            $res = self::_getLevel($sweet->sweet * 10);
            $res['sweet'] = $sweet->sweet * 10;
            $res['heart_bg'] = self::_getHeart($sweet->sweet * 10);
            $res['level_percent'] = $sweet->sweet >= 100 ? '100%' : number_format($sweet->sweet, 1) . '%';
        }
        return $res;
    }

    private static function _getHeart($sweet = 0): int
    {
        $level = 0;
        if ($sweet > 0 && $sweet < 200) $level = 1;
        if ($sweet >= 200 && $sweet < 500) $level = 2;
        if ($sweet >= 500 && $sweet <= 1000) $level = 3;
        return $level;
    }

    //获取距离下一等级的金币数 和当前的等级
    private static function _getLevel($sweet = 0): array
    {
        $level = 0;
        $base = 80;
        if ($sweet >= 0 && $sweet < 80) {
            $level = 0;
            $base = 80;
        }
        if ($sweet >= 80 && $sweet < 150) {
            $level = 1;
            $base = 150;
        }
        if ($sweet >= 150 && $sweet < 260) {
            $level = 2;
            $base = 260;
        }
        if ($sweet >= 260 && $sweet < 400) {
            $level = 3;
            $base = 400;
        }
        if ($sweet >= 400 && $sweet < 700) {
            $level = 4;
            $base = 700;
        }
        if ($sweet >= 900) {
            $level = 5;
            $base = $sweet;
        }
        return ['sweet_level' => $level, 'next_unit' => $base - $sweet];
    }

    //'luck', 'charm', 'wealth'
    public static function getRankList($type, $sex = 0)
    {
        $res = $userIds = $rankRes = [];
        //缘分  [甜蜜值倒序]
        $hide_rank_ids = UsersSettingsModel::where('hide_rank', 1)->pluck('user_id')->toArray();   //去除vip 设置隐藏排行榜的部分人
        if ($type == 'luck') {
            $ranks = self::where('status', 1)->whereNotIn('user_both', $hide_rank_ids)->whereNotIn('both_user', $hide_rank_ids)->orderBy('sweet', 'desc')->limit(20)->get();
            if (!$ranks->isEmpty()) {
                foreach ($ranks as $k => $rank) {
                    $userIds[] = $rank->both_user;
                    $userIds[] = $rank->user_both;
                }
                $userIds = array_unique($userIds);
                $esUsers = EsDataModel::mgetEsUserByIds(['ids' => $userIds]);

                foreach ($ranks as $item) {
                    $rankRes[] = [
                        'base_str' => '甜蜜值 | ' . $item->sweet . 'K',
                        'users' => [
                            [
                                'user_id' => $item->both_user,
                                'avatar' => isset($esUsers[$item->both_user]) ? $esUsers[$item->both_user]['avatar'] : '',
                                'nick' => isset($esUsers[$item->both_user]) ? $esUsers[$item->both_user]['nick'] : '',
                            ], [
                                'user_id' => $item->user_both,
                                'avatar' => isset($esUsers[$item->user_both]) ? $esUsers[$item->user_both]['avatar'] : '',
                                'nick' => isset($esUsers[$item->user_both]) ? $esUsers[$item->user_both]['nick'] : '',
                            ]
                        ]
                    ];
                }
            }
            $res['day'] = $rankRes;
            $res['week'] = $rankRes;
            $res['all'] = $rankRes;
        }
        //需要反查性别
        if (in_array($type, ['charm', 'wealth'])) {
            $sex = $sex == 1 ? 2 : 1;
            $builder = UsersModel::select('user_id', 'charm_num', 'send_num')->whereNotIn('users.id', $hide_rank_ids)->where('sex', $sex)->leftJoin('users_profile', 'users_profile.user_id', '=', 'users.id');
            if ($type == 'charm') {
                $builder->orderBy('charm_num', 'desc');
            }
            if ($type == 'wealth') {
                $builder->orderBy('send_num', 'desc');
            }
            $ranks = $builder->limit(20)->get();
            foreach ($ranks as $k => $rank) {
                $userIds[] = $rank->user_id;
            }
            $userIds = array_unique($userIds);
            $esUsers = EsDataModel::mgetEsUserByIds(['ids' => $userIds]);
            foreach ($ranks as $item) {
                $base_str = $type == 'charm' ? '魅力值 | ' . $item->charm_num . 'K' : '财富值 | ' . $item->send_num . 'K';
                $rankRes[] = [
                    'base_str' => $base_str,
                    'user_info' => [
                        'user_id' => $item->user_id,
                        'avatar' => isset($esUsers[$item->user_id]) ? $esUsers[$item->user_id]['avatar'] : '',
                        'nick' => isset($esUsers[$item->user_id]) ? $esUsers[$item->user_id]['nick'] : '',
                        'base_str' => isset($esUsers[$item->user_id]) ? $esUsers[$item->user_id]['base_str'] : '',
                    ]
                ];
            }
            $res['day'] = $rankRes;
            $res['week'] = $rankRes;
            $res['all'] = $rankRes;
        }
        return $res;
    }

    public static function getRankRecommend($uid)
    {
        $user = UsersModel::getUserInfo($uid);
        $res = [
            'title_str' => '您还没有缘分对象哦',
            'sweet' => '甜蜜值 | 0',
            'tips_str' => '暂未上榜',
            'users' => [
                [
                    'user_id' => $uid,
                    'avatar' => $user->avatar,
                ], [
                    'user_id' => 0,
                    'avatar' => 'http://static.hfriend.cn/vips/sweet/dfava.png'
                ]
            ]
        ];
        $rank = self::where([['user_both', $uid], ['status', 1]])->orWhere([['both_user', $uid], ['status', 1]])->orderBy('sweet', 'desc')->first();
        if ($rank) {
            $res['title_str'] = '您的缘分对象暂未上榜';
            $res['sweet'] = '甜蜜值 | ' . $rank->sweet;
            $res['tips_str'] = '您已上榜';
            $user_id = $rank->user_both == $uid ? $rank->both_user : $rank->user_both;
            $res['users'][1]['user_id'] = $user_id;
            $res['users'][1]['avatar'] = UsersModel::getUserInfo($user_id)->avatar;
        }
        return $res;
    }
}
