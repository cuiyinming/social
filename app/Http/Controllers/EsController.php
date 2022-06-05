<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Models\EsDataModel;
use App\Http\Models\Logs\LogSuperShowOnModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use JWTAuth;
use PhpParser\Node\Expr\Cast\Object_;

class EsController extends Controller
{
    //这个页面可以登录页也可以不登录
    protected $uid = 0;
    protected $sex = 0;
    //protected $live_location;

    //在最开始判断下是否是登陆后获取的
    public function __construct()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (isset($user->id) && $user->id > 0) {
                $this->uid = $user->id;
                $this->sex = $user->sex;
                //$this->live_location = $user->live_location;
                //使用redis 记录用户的最后一次活动时间及坐标
                HR::updateActiveTime($user->id);
                HR::updateActiveCoordinate($user->id);
            }
        } catch (\Exception $e) {
            //MessageModel::gainLog($e,__FILE__, __LINE__);
        }
    }

    public function nearbyRecommend(Request $request)
    {
        //推荐算法 1距离 2活跃 3喜欢数 4推荐频次 5用户的喜欢标签
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        if ($size > 20) $size = 20;
        $new = $request->input('new', 0);  //最新注册排序
        $active = $request->input('active', 0);  //活跃度排序
        $location = $request->input('coordinates', '');//经纬度筛选
        $map = 1;
        if (empty($location)) {
            $location = COORDINATES;
            $map = 0;
        }
        $local = $request->input('local', 0);   //同城筛选
        $online = $request->input('online', 0);   //在线筛选
        $goddess = $request->input('goddess', 0);   //女神筛选
        $constellation = $request->input('constellation', '不限');   //星座筛选
        $distance = $request->input('distance', 2000);   //距离
        $s_age = $request->input('s_age', 0); //开始年龄
        $e_age = $request->input('e_age', 0); //结束年龄
        $city = $request->input('city', ''); //城市过滤
        $e_age = $e_age > 100 ? 100 : $e_age;
        $q = $request->input('q', '');
        $real_is = $request->input('real_is', 2); //真人认证
        $vip_is = $request->input('vip_is', 2); //VIP 会员

        $sortArr = [];
        $sortArr['created_at'] = intval($new); //0不排序 1倒序 2正序
        $sortArr['live_time_latest'] = intval($active); //0不排序 1倒序 2正序
        $sort = $this->getSort($sortArr);

        if (!empty($constellation) && !in_array($constellation, ['白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座', '水瓶座', '双鱼座'])) {
            $constellation = '';
        }
        $sourceArr = explode(',', $location);
        $exclusion = [];
        $followIdArr = UsersFollowModel::getFollowIdArr($this->uid);
        if ($this->uid > 0) {
            //黑名单的人不做推荐
            $blackIdArr = UsersFollowModel::_exclude($this->uid);
            $merge = array_merge($blackIdArr, $followIdArr);
            $merge[] = $this->uid;
            $exclusion = array_unique($merge);
        }
        //登陆了
        if ($this->uid > 0 && $this->sex > 0) {
            $sex = $this->sex == 1 ? 2 : 1;
        }
        //未登录 就混合推荐男女
        if ($this->uid == 0) {
            $sex = 1;
            if ($page >= 2) {
                return $this->jsonExit(50000, '请登陆');
            }
        }
        if ($page > 100) {
            return $this->jsonExit(201, '今日查看内容过多，试试别的频道吧');
        }
        $real_is = in_array($real_is, [0, 2]) ? 2 : 1;
        $params = [
            'age_start' => $s_age,
            'age_end' => $e_age,
            'real_is' => $real_is,
            'vip_is' => $vip_is,
            'map' => $map,
            'constellation' => $constellation,
            'sex' => $sex,
            'local' => $local,
            'online' => $online,
            'goddess' => $goddess,
            'page' => $page,
            'exclusion' => $exclusion,
            'distance' => $distance,
            'size' => $size,
            'location' => $location,
            'sort' => $sort,
            'q' => $q,
            'city' => $city,
            //'live_location' => $this->live_location,
            'from' => 'index',
        ];
        //测试es
        try {
            $users = EsDataModel::getEsData($params, $sourceArr, $followIdArr);
            //超级曝光S
            $users = LogSuperShowOnModel::superShowUserGet($this->uid, $this->sex, $exclusion, $users);
            //循环渲染下是否打过招呼
            if (!empty($users['items'])) {
                foreach ($users['items'] as $k => $v) {
                    $users['items'][$k]['say_hi'] = $this->uid > 0 && HR::existUniqueNum($this->uid, $v['user_id'], 'say-hi-num') != 1;
                    //添加用户图片数
                    $users['items'][$k]['album_num'] = HR::userAlbumNumUpdate($v['user_id']);
                }
            }
            //添加完善资料推送【每日首次请求】
            if ($this->uid > 0 && HR::getUniqueNum($this->uid, 'user-first-req') <= 0) {
                HR::updateUniqueNum($this->uid, time(), 'user-first-req', true, 7200);
                //推送完善资料信息[104 完善资料]
                \App\Jobs\rewardSet::dispatch($this->uid, 104)->delay(now()->addSeconds(2))->onQueue('im');
            }
            if ($this->uid > 0 && HR::getUniqueNum($this->uid, 'user-first-say-hi') <= 0) {//添加批量打招呼[注册前2天]
                HR::updateUniqueNum($this->uid, time(), 'user-first-say-hi');
                \App\Jobs\rewardSet::dispatch($this->uid, 105, '', '', 0, $this->sex)->delay(now()->addSeconds(20))->onQueue('im');
            }
            //暂时取消每日签到推送
            if ($this->uid > 0 && HR::getUniqueNum($this->uid, 'user-sign') <= 0) {//每日签到弹窗【注册2天后】
                HR::updateUniqueNum($this->uid, time(), 'user-sign');
                \App\Jobs\rewardSet::dispatch($this->uid, 106)->delay(now()->addSeconds(40))->onQueue('im');
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK', $users);
    }

    //关注
    public function nearbyFocus(Request $request)
    {
        //推荐算法 1距离 2活跃 3喜欢数 4推荐频次 5用户的喜欢标签
        $size = 20;
        $page = $request->input('page', 1);
        $goddess = $request->input('goddess', 0);   //女神筛选
        $location = $request->input('location', COORDINATES);
        $sourceArr = explode(',', $location);
        $exclusion = $blackIdArr = [];
        $followIdArr = UsersFollowModel::getFollowIdArr($this->uid);
        if ($this->uid > 0) {
            //黑名单的人不做推荐
            $blackIdArr = UsersFollowModel::_exclude($this->uid);
            $merge = array_merge($blackIdArr, $followIdArr);
            $merge[] = $this->uid;
            $exclusion = array_unique($merge);
        }
        //登陆了
        if ($this->uid > 0 && $this->sex > 0) {
            $sex = $this->sex == 1 ? 2 : 1;
        }
        //未登录 就混合推荐男女
        if ($this->uid == 0) {
            $sex = 0;
        }
        if ($page > 20) {
            return $this->jsonExit(201, '今日查看内容过多，试试别的频道吧');
        }
        //测试es
        try {
            $resData = [];
            //******START*******************第一步筛选出活跃的*************************
            $sortArr = [];
            $sortArr['live_time_latest'] = 2; //0不排序 1倒序 2正序
            $sort = $this->getSort($sortArr);
            $params = [
                'sex' => $sex,
                'goddess' => $goddess,
                'page' => 1,
                'exclusion' => $exclusion,
                'distance' => 5000,
                'size' => $size,
                'location' => $location,
                'sort' => $sort,
                'from' => 'index',
            ];
            $users = EsDataModel::getEsData($params, $sourceArr, $followIdArr);
            $users = !empty($users) ? $users : [];
            //超级曝光S
            $users = LogSuperShowOnModel::superShowUserGet($this->uid, $this->sex, $exclusion, $users);
            $resData['active_users'] = $users;
            //******END*******************第一步筛选出活跃的*************************
            //******START*******************第二步筛选出新人的*****为避免重复推荐，新人会剔除活跃已经推荐的人********************
            $activeIdArr = [];
            if (isset($users['items']) && !empty($users['items'])) $activeIdArr = array_column($users['items'], 'user_id');
            $exclusion = array_unique(array_merge($exclusion, $activeIdArr));
            $sortArr = [];
            $sortArr['created_at'] = 2; //0不排序 1倒序 2正序
            $sort = $this->getSort($sortArr);
            $params = [
                'sex' => $sex,
                'goddess' => $goddess,
                'page' => 1,
                'exclusion' => $exclusion,
                'distance' => 5000,
                'size' => $size,
                'location' => $location,
                'sort' => $sort,
                'from' => 'index',
            ];
            $users = EsDataModel::getEsData($params, $sourceArr, $followIdArr);
            $users = !empty($users) ? $users : [];
            $resData['new_users'] = $users;
            //第三步追加我关注的人 === 这里需要注意，拉黑用户时需要检测改用不是否是关注的人，如果是则直接删除关注
            $sortArr = [];
            $sort = $this->getSort($sortArr);
            $params = [
                'sex' => $sex,
                'goddess' => $goddess,
                'page' => $page,
                'exclusion' => [],
                'must_have' => $followIdArr,
                'distance' => 5000,
                'size' => $size,
                'location' => $location,
                'sort' => $sort,
                'from' => 'focus',
            ];
            $users = !empty($followIdArr) ? EsDataModel::getEsData($params, $sourceArr, $followIdArr) : [];
            if (!empty($users)) {
                $resData['focus_users'] = $users;
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK', $resData);
    }

    //限制用户浏览详情次数
    public function profileInfoViewLimit(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', 'index');
        if ($this->uid == 0) {
            return $this->jsonExit(50000, '请登陆');
        }
        $data = [
            'show' => false,
            'title' => '今日免费浏览次数已用完',
            'sub_title' => '升级VIP会员可解锁更多浏览权限',
            'btn_str' => '升级VIP权益'
        ];
        if ($type == '') {
            return $this->jsonExit(200, 'OK', $data);
        }
        //每日限制浏览详情次数 30次 1女2男 index 首页  discover,  动态  IM  聊天  Map
        $profile = UsersProfileModel::getUserInfo($this->uid);
//        $time_limit = 400;
//        $map_limit = 400;
        if ($profile->vip_is == 1) {
            $time_limit = $this->sex == 1 ? 100 : 200;
            $map_limit = $this->sex == 1 ? 30 : 20;
        } else {
            $time_limit = $this->sex == 1 ? 50 : 30;
            $map_limit = $this->sex == 1 ? 3 : 2;
        }

        if (in_array($type, ['index'])) {
            $exist = HR::existUniqueNum($this->uid, $user_id, 'users-view-detail-num');
            if ($exist || $this->uid == $user_id) {
                //兼容处理
                return $this->jsonExit(200, 'OK', $data);
            }
            $scanNum = HR::getUniqueNum($this->uid, 'users-view-detail-num');
            if ($scanNum >= $time_limit && CHANNEL == 'ios') {
                return $this->jsonExit(201, '权限使用完毕', $data);
            }
            if ($scanNum >= $time_limit && CHANNEL == 'android') {
                $data['show'] = true;
                return $this->jsonExit(200, '权限使用完毕', $data);
            }
            HR::updateUniqueNum($this->uid, $user_id, 'users-view-detail-num');
        }
        //地图限制
        if ($type == 'map') {
            $scanNum = HR::getUniqueNum($this->uid, 'users-view-map-num');
            if ($scanNum >= $map_limit && CHANNEL == 'ios') {
                $data = [
                    'title' => '今日免费地图筛选已用完',
                    'sub_title' => '升级VIP会员可解锁地图筛选功能',
                    'btn_str' => '升级VIP解锁'
                ];
                return $this->jsonExit(201, '权限使用完毕', $data);
            }
            if ($scanNum >= $map_limit && CHANNEL == 'android') {
                $data = [
                    'show' => true,
                    'title' => '今日免费地图筛选已用完',
                    'sub_title' => '升级VIP会员可解锁地图筛选功能',
                    'btn_str' => '升级VIP解锁'
                ];
                return $this->jsonExit(200, '权限使用完毕', $data);
            }
            HR::updateUniqueNum($this->uid, H::gainStrId(), 'users-view-map-num');
        }

        return $this->jsonExit(200, 'OK', $data);
    }


}
