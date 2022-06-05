<?php

namespace App\Http\Controllers\Auth;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Crypt\Rsa;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\Lib\LibBioTextModel;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Logs\ApiLeftModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Logs\LogTokenModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use RongCloud;
use JWTAuth;

class RegisterController extends Controller
{
    public function register(RegisterRequest $request)
    {
        //file_put_contents('/tmp/header.log', print_r($request->all(), 1) . PHP_EOL, FILE_APPEND);
        $data = $request->only('mobile', 'nick', 'code', 'avatar', 'sex', 'birthday', 'invited', 'city', 'stature', 'weight', 'wechat');
        $token_id = $request->input('token_id', 0);
        $fast = $request->input('channel', '');
        $nick = $request->input('nick', '');
        if (mb_strlen($nick) < 2 || mb_strlen($nick) > 8) {
            return $this->jsonExit(421, '昵称需在2-8个字符之间');
        }
        //添加注册开发判断
        $user_invite = SettingsModel::getSigConf('base');
        if ($user_invite['user_reg_open'] != 1) {
            return $this->jsonExit(401, '暂未开放注册');
        }
        //快捷登陆部分
        if ($fast == 'fast' && $token_id > 0) {
            $logToken = LogTokenModel::where([['status', 1], ['id', $token_id]])->first();
            if (!$logToken) {
                return $this->jsonExit(403, '系统错误');
            }
            $data['mobile'] = $logToken->token;
        } else {
            if (empty($data['mobile'])) {
                return $this->jsonExit(403, '手机号码不能为空');
            }
            if (!H::checkPhoneNum($data['mobile'])) {
                return $this->jsonExit(402, '手机号码错误');
            }
        }
        $sex = $data['sex'];
        if (!in_array($sex, [1, 2])) {
            return $this->jsonExit(406, '性别选择错误');
        }
        //女性用户必填信息更多一些
        if ($sex == 1) {
            if (empty($data['stature']) || empty($data['weight'])) {
                return $this->jsonExit(413, '身高体重不能为空');
            }
            if (empty($data['wechat'])) {
                return $this->jsonExit(414, '请填写您的微信号码');
            }
            //微信号中不能含有中文
            if (preg_match("/([\x81-\xfe][\x40-\xfe])/", $data['wechat'], $match)) {
                return $this->jsonExit(414, '微信号不应含有中文');
            }
        }
        $nick = $data['nick'] ?? '';
        $nick = str_replace(' ', '', $nick);
        if (empty($nick)) {
            return $this->jsonExit(415, '请填写您的昵称哦');
        }
        $nickLen = mb_strlen($nick);
        if ($nickLen < 2 || $nickLen > 10) {
            return $this->jsonExit('407', '昵称需在2-10个字之间');
        }
        $res = (new AliyunCloud())->GreenScanText($nick);
        if ($res != 'pass') {
            return $this->jsonExit(204, '昵称存在非法词汇，请检查');
        }
        $encryptMobile = H::encrypt($data['mobile']);
        //查询用户是否已经注册
        $userModel = UsersModel::where('mobile', $encryptMobile)->first();
        if ($userModel) {
            return $this->jsonExit(405, '账号已存在，请直接登陆');
        }
        //如果存在但是状态不正常
        if ($userModel && $userModel->status != 1) {
            return $this->jsonExit(404, '您的账号已被封禁，请联系客服处理');
        }
        //推荐码
        $invite = $client_invite = $request->input('invite', '');
        if (!empty($invite)) {
            $invite_check = UsersModel::where('uinvite_code', $invite)->first();
            if (!$invite_check) {
                //查询代理的邀请码
                $invite_check = ClientUsersModel::where('invite_code', $invite)->first();
                if (!$invite_check) {
                    return $this->jsonExit(406, '邀请码错误');
                } else {
                    $invite = '';
                }
            } else {
                $client_invite = '';
            }
            $invite_check->increment('invited_num');
        }
        //必须参数缺失
        $age = H::getAgeByBirthday($data['birthday']);
        if ($age < 18) {
            return $this->jsonExit(407, '您的年龄小于18岁，暂未开放该年龄段注册');
        }
        //过滤本地图片地址bug
        $avatar = $data['avatar'] ?? '';
        if (stripos($avatar, 'storage') !== false) {
            return $this->jsonExit(408, '图片地址错误');
        }
        $salt = H::randstr(6, 'ALL');
        //获取城市
        $city = empty($data['city']) ? H::getCityByCoor() : $data['city'];
        try {
            DB::beginTransaction();
            $user = UsersModel::create([
                'mobile' => $encryptMobile,
                'password' => Hash::make($data['mobile'] . $salt),
                'sweet_coin' => 0,
                'last_ip' => IP,
                'last_login' => CORE_TIME,
                'last_location' => $city,
                'last_coordinates' => COORDINATES,
                'live_coordinates' => COORDINATES,
                'live_location' => $city,
                'live_time_latest' => CORE_TIME,
                'nick' => $data['nick'],
                'avatar' => $avatar,
                'sex' => $sex,
                'birthday' => $data['birthday'],
                'constellation' => H::getConstellationByBirthday($data['birthday']),
                'salt' => $salt,
                'invited' => $invite,
                'client_code' => $client_invite,
                'online' => 1,
                'status' => 1,
                'device' => DEVICE,
                'device_lock' => 0,
            ]);
            //生成用户的id
            $user->platform_id = H::getPlatformId($user->id);
            $user->uinvite_code = H::createInviteCodeById($user->id);
            //获取融云用户id
            try {
                $rongToken = RongCloud::getToken($user->id, $data['nick'], $data['avatar']);
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }
            $user->rong_token = $rongToken['token'] ?? '';
            $user->save();
            //补充了快捷登陆的部分逻辑
            if ($token_id > 0) {
                LogTokenModel::where('id', $token_id)->update(['user_id' => $user->id]);
            }
            //创建扩展信息
            $stature = empty($data['stature']) ? '' : $data['stature'];
            $weight = empty($data['weight']) ? '' : $data['weight'];
            $wechat = empty($data['wechat']) ? '' : H::encrypt($data['wechat']);
            UsersProfileModel::create([
                'user_id' => $user->id,
                'register_coordinates' => COORDINATES,
                'register_location' => $city,
                'register_ip' => IP,
                'register_platform' => PLATFORM,
                'register_date' => CORE_TIME,
                'register_channel' => CHANNEL,
                'register_device' => DEVICE,
                'register_ver' => VER,
                'live_addr' => $city,
                'mobile' => $encryptMobile,
                'bio' => '',
                'stature' => $stature,
                'weight' => $weight,
                'wechat' => $wechat,
            ]);
            //创建设置信息裱
            UsersSettingsModel::create([
                'user_id' => $user->id,
                'hide_model' => 0,
                'hide_browse_push' => 1,
            ]);
            //还原手机号
            $user->mobile = $data['mobile'];
            LoginLogModel::gainLoginLog($user->id, '正常登陆');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }
        $user->user_id = $user->id;
        //拼接用户的base_str
        $user->base_str = $city . ' | ' . ($sex == 1 ? '女' : '男') . '•' . $age;
        /**   新的产生token 的方式  S**/
        $loginData = [];
        $loginData['mobile'] = $encryptMobile;
        $loginData['password'] = $data['mobile'] . $salt;
        $token = JWTAuth::attempt($loginData);
        //单点登录
        $base = SettingsModel::getSigConf('base');
        if (isset($base['user_unique']) && $base['user_unique'] == 1) {
            HR::signLogin($user->id, $token);
        }
        //发送队列[处理可以异步处理的信息]
        \App\Jobs\authJob::dispatch($user)->onQueue('register');
        unset($user->id);
        unset($user->password);
        unset($user->salt);
        unset($user->last_ip);
        unset($user->last_login);
        unset($user->live_coordinates);
        unset($user->last_coordinates);
        unset($user->last_location);
        unset($user->live_location);
        unset($user->live_time_latest);
        /**   新的产生token 的方式  E**/
        return $this->jsonExit(200, 'OK', ['user' => $user, 'token' => $token, 'password_set' => 0]);
    }

    public function fastRegister(Request $request)
    {
        $channel = $request->input('type', 'apple');
        $verify_str = $request->input('verify_str', '');
        if (!in_array($channel, ['apple', 'wechat', 'qq', 'fast'])) {
            return $this->jsonExit(201, '渠道错误');
        }
        try {
            if ($channel == 'fast') {
                $res = (AuroraPush::getInstance())->getLoginToken($verify_str);
                $verify_str = Rsa::privDecrypt($res->phone);
                if ($verify_str) {
                    ApiLeftModel::where('type', 'fast_login')->decrement('left_num');
                }
            }
            if (empty($verify_str)) {
                return $this->jsonExit(201, 'token错误');
            }
            $verify_sign = md5($verify_str);
            $token = LogTokenModel::updateOrCreate([
                'sign' => $verify_sign,
                'channel' => $channel,
            ], [
                'sign' => $verify_sign,
                'channel' => $channel,
                'token' => $verify_str,
                'status' => 1,
            ]);
            //如果是以往绑定过的用户
            $auth_token = '';
            $user_id = $id = $password_set = 0;
            $userInfo = [];
            $jump = false;
            //快捷登陆部分中的一键登录需要单独处理下
            if ($channel == 'fast') {
                $encrypt = H::encrypt($verify_str);
                $user = UsersModel::where('mobile', $encrypt)->first();
                if ($user) {
                    $auth_token = auth()->tokenById($user->id);
                    $user_id = $user->id;
                    $password_set = $user->password_set;
                    $token->user_id = $user->id;
                    $token->save();
                    $userInfo = $this->_loginInfo($user, $auth_token);
                } else {
                    $id = $token->id;
                    $jump = true;
                }
            }
            if (in_array($channel, ['apple', 'wechat', 'qq'])) {
                if (!$token->wasRecentlyCreated && $token->user_id > 0) {
                    $user = UsersModel::find($token->user_id);
                    if (!$user) {  //如果用户不存在，则新创建
                        return $this->jsonExit(202, '账号不存在');
                    }
                    if ($user->status != 1) {
                        return $this->jsonExit(201, '账号已被封禁');
                    }
                    if ($user->unlock_time > date('Y-m-d H:i:s')) {
                        return $this->jsonExit(211, trans('您的账号临时锁定30分钟，请稍后再试'));
                    }
                    $password_set = $user->password_set;
                    //查询用户并生成token
                    $auth_token = auth()->tokenById($token->user_id);
                    $user_id = $token->user_id;
                    $userInfo = $this->_loginInfo($user, $auth_token);
                } else {
                    $id = $token->id;
                    $jump = true;
                    //审核通过后注释掉的逻辑=======S
                    //$user = UsersModel::fakeUser();
                    //if ($user) {
                    //    $jump = false;
                    //    $token->user_id = $user->id;
                    //    $token->save();
                    //    $auth_token = auth()->tokenById($token->user_id);
                    //    $userInfo = $this->_loginInfo($user, $auth_token);
                    //}
                    //审核通过后注释掉的逻辑=======E
                }
            }
            $ret = [
                'res' => [
                    'user_id' => $user_id,
                    'token_id' => $id,
                    'jump' => $jump
                ],
                'user' => $userInfo,
                'token' => $auth_token,
                'password_set' => $password_set
            ];
            return $this->jsonExit(200, 'OK', $ret);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    private function _loginInfo($user, $token)
    {
        //查询上次登录时间 0 是用户
        $lastModel = LoginLogModel::where([['type', 0], ['user_id', $user->id], ['remark', '正常登陆']])->orderBy('id', 'desc')->first();
        if ($lastModel) {
            $user->unlock_time = null;
            $user->login_try_time = 0;
            $user->last_ip = $lastModel->ip;
            $user->last_location = $lastModel->last_city;
            $user->last_login = $lastModel->login_time;
            $user->last_coordinates = $lastModel->coordinates;
        }
        $city = H::getCityByCoor();
        //更新活跃时间
        $user->online = 1;
        $user->invited = is_null($user->invited) ? '' : $user->invited;
        //最后活跃时间
        $user->live_location = $city;
        $user->live_time_latest = CORE_TIME;
        //登陆强制更新实时坐标
        $user->live_coordinates = COORDINATES;
        $user->save();
        //单点登录
        $base = SettingsModel::getSigConf('base');
        if (isset($base['user_unique']) && $base['user_unique'] == 1) {
            HR::signLogin($user->id, $token);
        }
        LoginLogModel::gainLoginLog($user->id, '正常快捷登陆');   //type 默认0为用户
        //最后记录下redis的活跃时间
        HR::updateActiveTime($user->id);
        HR::updateActiveCoordinate($user->id);
        $user->user_id = $user->id;
        //拼接用户的base_str
        $profile = UsersProfileModel::where('user_id', $user->user_id)->first();
        $user->base_str = $city . ' | ' . ($user->sex == 1 ? '女' : '男') . '•' . H::getAgeByBirthday($user->birthday);
        if ($profile->stature != 0 && !empty($profile->stature)) {
            $user->base_str .= ' | ' . $profile->stature;
        }
        if ($profile->profession) {
            $user->base_str .= ' | ' . $profile->profession;
        }
        unset($user->id);
        unset($user->salt);
        unset($user->login_try_time);
        unset($user->last_ip);
        unset($user->last_login);
        unset($user->last_location);
        unset($user->last_coordinates);
        unset($user->live_time_latest);
        unset($user->live_coordinates);
        unset($user->unlock_time);
        //还原手机号
        $user->mobile = H::decrypt($user->mobile);
        return $user;
    }

    //推荐及随机昵称获取和刷新
    public function suggest(Request $request)
    {
        $res = [];
        $avatar = $request->input('avatar');
        $nick = $request->input('nick');
        $sex = $request->input('sex', 0);
        //返回提示
        $res['back'] = [
            'title' => '亲，完善资料后可获得：',
            'tips' => '现在注册可获得免费搭讪次数或现金红包奖励',
            'topical' => config('app.url') . '/imgs/award/award.png',
        ];
        //上传token 下发
        $token = H::encryption($request->ip() . '_' . time());
        $res['img_token'] = $token;
        if (!empty($avatar)) {
            $arr = [];
            for ($i = 1; $i <= 75; $i++) {
                $arr[] = $i;
            }
            $rands = array_values(array_rand($arr, 10));
            foreach ($rands as $rand) {
                $res['avatar']['male'][] = config('app.cdn_url') . '/ava/2-' . $rand . '.jpg';
                $res['avatar']['female'][] = config('app.cdn_url') . '/ava/1-' . $rand . '.jpg';
            }
            if ($sex == 1) unset($res['avatar']['male']);
            if ($sex == 2) unset($res['avatar']['female']);
        }
        if (!empty($nick)) {
            if ($sex == 0) {
                $nicks = LibNickModel::orderBy(DB::Raw('RAND()'))->limit(10)->pluck('nick')->toArray();
            }
            if ($sex == 1) {
                $nicks = LibNickModel::where('gender', 1)->orderBy(DB::Raw('RAND()'))->limit(10)->pluck('nick')->toArray();
            }
            if ($sex == 2) {
                $nicks = LibNickModel::where('gender', 2)->orderBy(DB::Raw('RAND()'))->limit(10)->pluck('nick')->toArray();
            }
            $res['nick'] = $nicks;
        }
        return $this->jsonExit(200, 'OK', $res);
    }
}
