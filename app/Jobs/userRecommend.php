<?php

namespace App\Jobs;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use RongCloud;
use App\Http\Models\EsDataModel;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogGiftReceiveModel;
use App\Http\Models\Logs\LogGiftSendModel;
use App\Http\Models\Logs\LogRecommendModel;
use App\Http\Models\Logs\LogSweetModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

//推荐给指定用户对应的用户【传递需要推荐的用户的id】
class userRecommend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user_id;

    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    public function handle()
    {
        try {
            $followIdArr = UsersFollowModel::getFollowIdArr($this->user_id);
            $exclusion = UsersModel::exclusion($this->user_id, $followIdArr);
            $user = UsersModel::where('id', $this->user_id)->first();
            $sex = $user->sex == 1 ? 2 : 1;
            $column = [
                'users.id', 'users.nick', 'users.avatar', 'users.sex', 'users.birthday', 'users.constellation', 'users.last_location',
                'users_profile.stature', 'users_profile.vip_is', 'users_profile.profession', 'users.online', 'users_profile.vip_level',
                'users_profile.identity_is', 'users_profile.real_is', 'users.live_time_latest'
            ];
            //首先推荐在线 [不包含头像未完善的]
            $item = UsersModel::select($column)->leftjoin('users_profile', 'users.id', '=', 'users_profile.user_id')
                ->where([['users.online', 1], ['users.status', 1], ['users.sex', $sex]])->where('users.avatar', 'like', '%' . '/avatar/' . '%')
                ->whereNotIn('users.id', $exclusion)->orderBy(DB::Raw('RAND()'))->first();
            if (!$item) {
                //如果没有在线的则推荐同城的
                $hometown = UsersProfileModel::where('user_id', $this->user_id)->first()->hometown;
                if (!empty($hometown)) {
                    $item = UsersModel::select($column)->leftjoin('users_profile', 'users.id', '=', 'users_profile.user_id')
                        ->where([['users.status', 1], ['users.sex', $sex], ['users_profile.hometown', $hometown]])->where('users.avatar', 'like', '%' . '/avatar/' . '%')
                        ->whereNotIn('users.id', $exclusion)->orderBy(DB::Raw('RAND()'))->first();
                }
                //如果没有则推荐我的坐标方圆50km内的异性用户
                if (!$item) {
                    $sortArr['created_at'] = 0; //0不排序 1倒序 2正序
                    $sort = UsersModel::getSort($sortArr);
                    $params = [
                        'real_is' => 1,
                        'sex' => $sex,
                        'page' => 1,
                        'exclusion' => $exclusion,
                        'distance' => 500,  //50km 内
                        'size' => 10,
                        'sort' => $sort,
                        'location' => $user->last_coordinates,
                    ];
                    $sourceArr = explode(',', $user->last_coordinates);
                    $users = EsDataModel::getEsData($params, $sourceArr, $followIdArr);
                    //如果有超级曝光则优先推荐超级曝光
                    if (isset($users['count']) && $users['count'] > 0) {
                        $user_id = $users['items'][0]['user_id'];
                        $item = UsersModel::select($column)->leftjoin('users_profile', 'users.id', '=', 'users_profile.user_id')
                            ->where([['users.status', 1], ['users.id', $user_id]])->first();
                    }
                }
            }
            if ($item) {
                $rand = rand(60, 99);
                $sex_str = $item->sex == 1 ? '女' : '男';
                $age = H::getAgeByBirthday($item->birthday);
                $base_str = $item->last_location . ' | ' . $sex_str . '•' . $age;
                if (!empty($item->stature)) $base_str .= ' | ' . $item->stature;
                if (!empty($item->constellation)) $base_str .= ' | ' . $item->constellation;
                if (!empty($item->profession)) $base_str .= ' | ' . $item->profession;
                $res = [
                    'user_id' => $item->id,
                    'avatar' => $item->avatar,
                    'say_hi' => HR::existUniqueNum($this->user_id, $item->id, 'say-hi-num') != 1,
                    'match' => $rand,
                    'nick' => $item->nick,
                    'vip_is' => $item->vip_is,
                    'vip_level' => $item->vip_level,
                    'online' => $item->online,
                    'identity_is' => $item->identity_is,
                    'real_is' => $item->real_is,
                    'time_str' => H::exchangeDate($item->live_time_latest),
                    'base_str' => $base_str,
                ];
                $notice = [
                    'title' => '优质用户推荐',
                    'content' => '用户推荐',
                    'extra' => json_encode($res),
                ];
                LogRecommendModel::create([
                    'user_id' => $this->user_id,
                    'user_sex' => $user->sex,
                    'user_id_rec' => $item->id,
                    'user_sex_rec' => $item->sex,
                    'recommend_at' => date('Y-m-d H:i:s'),
                    'match' => $rand,
                ]);
                //file_put_contents('/tmp/rec.log', print_r($res, 1) . PHP_EOL, FILE_APPEND);
                $res = RongCloud::messageSystemPublish(110, [$this->user_id], 'RC:TxtMsg', json_encode($notice));
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e,__FILE__, __LINE__);
        }
    }


}
