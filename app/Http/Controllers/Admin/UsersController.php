<?php

namespace App\Http\Controllers\Admin;


use App\Components\ESearch\ESearch;
use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Models\Admin\AdmRoleModel;
use App\Http\Models\Discover\DiscoverCmtModel;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Discover\DiscoverZanModel;
use App\Http\Models\JobsModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Logs\CronCloseModel;
use App\Http\Models\Logs\LogAlbumViewModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogContactUnlockModel;
use App\Http\Models\Logs\LogSoundLikeModel;
use App\Http\Models\Logs\LogTokenModel;
use App\Http\Models\MessageModel;
use App\Http\Models\System\BlackIpModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersFollowSoundModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use mobile\push\Jpush;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\CommonModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\System\FeedbackModel;
use App\Http\Models\Admin\ActiveLogModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Resource\{AlbumModel, ResourceModel, UploadModel, AvatarModel};
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RongCloud;
use Illuminate\Support\Facades\Artisan;

class UsersController extends AuthAdmController
{
    /************用户管理************/
    public function userList(Request $request)
    {
        $status = $request->input('status');
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $album = $request->input('album');
        $album_video = $request->input('album_video');
        $goddess_handle = $request->input('goddess_handle');
        $q = $request->input('q');
        $contact = $request->input('contact');
        $id = $request->input('id');
        $hide_model = $request->input('hide_model');
        $unlock_time = $request->input('unlock_time');
        $invited = $request->input('invite');
        $vip_is = $request->input('vip_is');
        $real_is = $request->input('real_is');
        $goddess_is = $request->input('goddess_is');
        $identity_is = $request->input('identity_is');
        $sex = $request->input('sex');
        $online = $request->input('online');
        $vip_level = $request->input('vip_level');
        $avatar_illegal = $request->input('avatar_illegal');
        $date = $request->input('dates', []);
        //对运营做限制
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote && $page != 1) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        $clients = UsersModel::getDataByPage($goddess_is, $identity_is, $q, $status, $unlock_time, $album, $album_video, $hide_model, $goddess_handle, $date, $vip_is, $real_is, $sex, $vip_level, $avatar_illegal, $online, $page, $size, $id, $invited, $contact);
        return $this->jsonExit(200, 'OK', $clients);
    }

    //设置用户密码
    public function usersInfoSet(Request $request)
    {
        $id = $request->input('id', 0);
        $password = $request->input('password', '');
        if (!$userInfo = UsersModel::find($id)) {
            return $this->jsonExit(205, '用户信息不存在');
        }
        $salt = H::randstr(6, 'ALL');
        if (!empty($password)) $userInfo->password = Hash::make(trim($password) . $salt);
        $userInfo->salt = $salt;
        $userInfo->save();
        //记录管理员日志日志
        LogUserModel::gainLog($this->uid, '后台修改密码', ' ******', ' ******', '后台修改密码，修改人：' . $this->user->username, 1, 1);
        //发送短信
        $mobile = H::decrypt($userInfo->mobile);
        if (empty($mobile) || !H::checkPhoneNum($mobile)) {
            return $this->jsonExit(202, '用户手机号码未完善或错误');
        }
        //判断指定时间发送数量
        $max_setting = config('common.max_sms_time');
        $type = 'modify_user_pwd';
        $has_send_time = LogSmsModel::geSmsNum($mobile, $type);
        if ($has_send_time >= $max_setting) {
            return $this->jsonExit(203, '您的操作过于频繁，请稍后重试');
        }
        $sendResult = LogSmsModel::sendMsg($mobile, $type, $password);
        if ($sendResult) {
            return $this->jsonExit(200, 'OK');
        } else {
            return $this->jsonExit(202, '发送修改短信给客户失败');
        }
    }

    //修改用户状态
    public function updateUsers(Request $request)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        try {
            DB::beginTransaction();
            $id = $request->input('id', 0);
            $table = $request->input('table', 'user');
            $column = $request->input('column', 'chat_add');
            $val = $request->input('val', 0);
            $user = UsersModel::find($id);
            if (!$user) {
                return $this->jsonExit(201, '记录不存在，请检查');
            }
            if ($table == 'profile') {
                $profile = $user->profile;
                if ($column == 'sound_pending') {
                    //添加音频审核逻辑
                    if ($val == 1) {
                        $profile->sound = $profile->sound_pending;
                        $profile->sound_pending = null;
                        $profile->sound_status = 1;
                    } else {
                        if ($profile->sound_status !== 3) {
                            return $this->jsonExit(204, '已经审核过');
                        }
                        $profile->sound_pending = null;
                        JpushModel::JpushCheck($id, '', 0, 15);
                        $profile->sound_status = 2;
                    }
                    $profile->save();
                    DB::commit();
                    return $this->jsonExit(200, 'OK');
                }
                if ($column == 'goddess_handle' && $val == 0) {
                    //认证通过极光推送
                    JpushModel::JpushCheck($id, '', 0, 12);
                    $profile->goddess_is = 1;
                    $profile->goddess_end_at = date('Y-m-d H:i:s');
                }
                if ($column == 'illegal_bio' && $val == 1) {
                    $profile->bio = '';
                    $profile->save();
                    JpushModel::JpushCheck($id, '', 0, 11);
                    UsersSettingsModel::setViolation($id, 'violation_bio');
                }
                if (in_array($column, ['illegal_wechat', 'illegal_qq', 'illegal_mobile']) && $val == 1) {
                    JpushModel::JpushCheck($id, '', 0, 8);
                    $profile->$column = $val;
                    if ($column == 'illegal_wechat') {
                        $profile->wechat = '';
                    }
                    if ($column == 'illegal_qq') {
                        $profile->qq = '';
                    }
                    //更新联系方式状态
                    if (empty($profile->wechat) && empty($profile->qq)) {
                        $esVipArr[] = ['id' => $id, 'contact' => 0];
                    } else {
                        $esVipArr[] = ['id' => $id, 'contact' => 1];
                    }
                    try {
                        (new ESearch('users:users'))->updateSingle($esVipArr); //更新es缓存
                    } catch (\Exception $e) {
                        MessageModel::gainLog($e, __FILE__, __LINE__);
                    }
                }
                $profile->$column = $val;
                $profile->save();
                DB::commit();
                return $this->jsonExit(200, 'OK');
            }
            if ($table == 'settings') {
                try {
                    if ($column == 'chat_im' && $val == 0) RongCloud::userBlock($id, 43200); //默认禁言1个月
                    if ($column == 'chat_im' && $val == 1) RongCloud::userUnBlock($id);
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
                $settings = $user->settings;
                $settings->$column = $val;
                $settings->save();
                UsersSettingsModel::refreshUserSettings($id, $column, $val);
                DB::commit();
                return $this->jsonExit(200, 'OK');
            }
            if ($table == 'users') {
                if ($column == 'unlock_time') {
                    //默认是锁定30分钟
                    $user->unlock_time = $val ? date('Y-m-d H:i:s', time() + 60 * 30) : null;
                    $user->login_try_time = 0;
                }
                if ($column == 'device_lock') {
                    //默认是锁定30分钟
                    $user->device_lock = $val ? 1 : 0;
                    //同步封禁信息到数据库
                    BlackIpModel::syncBlockInfoBase($user, $val, 'device');
                    JobsModel::InsertNewJob(6);
                }
                //更新昵称
                if ($column == 'status') {
                    $esVipArr[] = [
                        'id' => $id,
                        'status' => $val ? 0 : 1,
                    ];
                    //更新es
                    (new ESearch('users:users'))->updateSingle($esVipArr);
                    $user->status = $val ? 0 : 1;
                    $user->locked_at = $val ? CORE_TIME : null;
                    $val ? HR::setLockedId($user->id) : HR::delLockedId($user->id);
                    //同步封禁信息到数据库 + 增加全局的更新
                    BlackIpModel::syncBlockInfoBase($user, $val);
                    JobsModel::InsertNewJob(1);
                    JobsModel::InsertNewJob(5);
                    JobsModel::InsertNewJob(6);
                    JobsModel::InsertNewJob(7);
                    //如果封禁账号就极光推送 [暂时不做通知]
                    if ($val) JpushModel::JpushCheck($id, '', 0, 10);
                }
                if (in_array($column, ['avatar_illegal'])) {
                    $esVipArr[] = [
                        'id' => $id,
                        'avatar_illegal' => $val,
                    ];
                    //更新es
                    (new ESearch('users:users'))->updateSingle($esVipArr);
                    //如果头像审核不通过就极光推送
                    if ($val == 1) {
                        JpushModel::JpushCheck($id, '', 0, 6);
                        UsersSettingsModel::setViolation($id, 'violation_avatar');
                    }
                    $user->$column = $val;
                }
                if (in_array($column, ['under_line'])) {
                    $esVipArr[] = [
                        'id' => $id,
                        'under_line' => $val,
                    ];
                    //更新es缓存
                    $val == 0 ? HR::setUnderLineId($user->id) : HR::delUnderLineId($user->id);
                    //更新es
                    (new ESearch('users:users'))->updateSingle($esVipArr);
                    $user->$column = $val;
                }
                $user->save();
                DB::commit();
                return $this->jsonExit(200, 'OK');
            }

//            if ($type == 'goddess') {
//                if ($model->sex == 2) {
//                    return $this->jsonExit(202, '男生不能进行女神相关操作');
//                }
//                $esVipArr[] = [
//                    'id' => $data['id'],
//                    'goddess' => $data[$type],
//                ];
//                //更新es
//                (new ESearch('soul:users'))->updateSingle($esVipArr);
//                $model->goddess_end_at = CORE_TIME;
//                //如果封禁账号就极光推送
//                if ($data[$type] == 1) {
//                    CommonModel::JpushCheck($model->id, '', 0, 12);
//                    //增加友币
//                    $check = SettingsModel::getSigConf('check');
//                    if (isset($check['goddess_add_sweet_on']) && $check['goddess_add_sweet_on'] == 1) {
//                        $before_amount = $model->sweet_coin;
//                        $model->sweet_coin += $check['goddess_add_sweet'];
//                        $sweet = $check['goddess_add_sweet'];
//                        //添加友币增加记录
//                        $inviteUserInfo = [
//                            'amount' => $model->sweet_coin,
//                            'before_amount' => $before_amount,
//                            'change_amount' => $sweet,
//                            'order_sn' => H::genOrderSn(5),
//                            'adm_id' => 0,
//                            'user_id' => $this->uid,
//                            'desc' => '女神认证奖励友币' . $sweet . '个',
//                            'type' => 3,
//                            'operate' => '+',
//                            'remark' => '女神认证奖励友币' . $sweet . '个',
//                            'created_at' => CORE_TIME,
//                            'updated_at' => CORE_TIME
//                        ];
//                        BalanceLogModel::insert($inviteUserInfo);
//                    }
//                    $model->save();
//                } else {
//                    CommonModel::JpushCheck($model->id, '', 0, 14);
//                }
//            }
            DB::commit();
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            DB::rollBack();
            return $this->jsonExit(209, $e->getMessage());
        }
    }

    //修改用户的友币数量
    public function userBalanceSet(Request $request)
    {
        $id = $request->input('id', 0);
        $opt = $request->input('opt', 0); //2增加 1减少
        $money = $request->input('money', 0);
        $types = $request->input('types', 1);
        $remark = $request->input('remark', '');
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        if (!in_array($opt, [1, 2])) {
            return $this->jsonExit(201, '操作类型不正确');
        }
        if ($money < 1) {
            return $this->jsonExit(202, '金额错误');
        }
        if (empty($remark)) {
            return $this->jsonExit(203, '备注不能为空');
        }
        if ($this->user->supper != 1) {
            return $this->jsonExit(206, '只有超级管理员才能修改用户金额');
        }
        if (!in_array($types, [1, 2, 3])) {
            return $this->jsonExit(207, '操作目标错误');
        }
        try {
            DB::beginTransaction();
            //查询用户
            $user = UsersModel::where('id', $id)->lockForUpdate()->first();
            if (!$user) {
                DB::rollback();
                return $this->jsonExit(204, '用户不存在');
            }
            if ($types == 1) $before_amount = $user->sweet_coin;
            if ($types == 2) $before_amount = $user->jifen;
            if ($types == 3) $before_amount = $user->wallet;
            if ($opt == 1 && $before_amount < $money) {
                DB::rollback();
                return $this->jsonExit(205, '操作数小于可用数目，请核对');
            }
            if ($opt == 1 && $types == 1) $user->sweet_coin -= $money;
            if ($opt == 2 && $types == 1) $user->sweet_coin += $money;
            if ($opt == 1 && $types == 2) $user->jifen -= $money;
            if ($opt == 2 && $types == 2) $user->jifen += $money;
            if ($opt == 1 && $types == 3) $user->wallet -= $money;
            if ($opt == 2 && $types == 3) $user->wallet += $money;
            if ($types == 1) $after = $user->sweet_coin;
            if ($types == 2) $after = $user->jifen;
            if ($types == 3) $after = $user->wallet;
            $user->save();
            //记录日志
            if ($types == 1) {
                $desc = "平台人工下发{$money}个友币";
                $remark = "管理员({$this->uid})后台手动操作：{$money}个友币,备注：{$remark}";
            }
            if ($types == 2) {
                $desc = "平台人工下发{$money}个积分";
                $remark = "管理员({$this->uid})后台手动操作：{$money} 个积分,备注：{$remark}";
            }
            if ($types == 3) {
                $desc = "平台人工下发{$money}元";
                $remark = "管理员({$this->uid})后台手动操作：钱包 {$money} 元,备注：{$remark}";
            }
            if ($types == 1) $table = 'log_balance';
            if ($types == 2) $table = 'log_jifen';
            if ($types == 3) $table = 'log_wallet';
            LogBalanceModel::gainLogBalance($id, $before_amount, $money, $after, 'admin_add', $desc, $remark, $this->uid, $table);
            DB::commit();
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(209, $e->getMessage());
        }
    }

    //手动调整用户会员
    public function userLevelSet(Request $request)
    {
        $uid = $request->input('id');
        $data = $request->all();
        $user = UsersModel::find($uid);
        $profile = $user->profile;
        if ($user->status != 1) {
            return $this->jsonExit(201, '用户状态异常');
        }
        $extInfo = function ($uid, $user) {
            return '会员ID:' . $uid
                . ' 会员:' . ($user->vip_is == 1 ? '是' : '否')
                . ' 会员等级：' . $user->vip_level
                . ' 过期时间：' . $user->vip_exp_time
                . ' 真人：' . ($user->real_is == 1 ? '是' : '否')
                . ' 实名：' . ($user->identity_is == 1 ? '是' : '否');
        };
        $oldVal = $extInfo($uid, $profile);
        $esVipArr = [];
        //开通VIP
        try {
            $level = $data['vip_level'] ?? 0;
            if (in_array($level, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 100, 101])) {
                $profile->vip_is = $level == 100 ? 0 : 1;

                if ($level == 101) {
                    $profile->vip_level = 1;
                } else if ($level == 100) {
                    $profile->vip_level = 0;
                } else {
                    $profile->vip_level = $level;
                }

                if ($level == 101) {
                    $profile->vip_handle = 2;
                } else if ($level == 100) {
                    $profile->vip_handle = 0;
                } else {
                    $profile->vip_handle = 1;
                }

                //过期时间计算
                $exp_time = 0;
                if (in_array($level, [1, 2])) {
                    $exp_time = time() + 7 * 86400;
                }
                if (in_array($level, [4, 5])) {
                    $exp_time = time() + 31 * 86400;
                }
                if (in_array($level, [7, 8])) {
                    $exp_time = time() + 93 * 86400;
                }
                if (in_array($level, [10, 11])) {
                    $exp_time = time() + 365 * 86400;
                }
                if (in_array($level, [101])) {
                    $donate = config('settings.donate_vip');
                    $exp_time = time() + $donate * 86400;
                }
                $profile->vip_exp_time = $level == 100 ? CORE_TIME : date('Y-m-d H:i:s', $exp_time);
                if (empty($profile->vip_at)) $profile->vip_at = CORE_TIME;
                //更新es 数据
                if ($level == 101) {
                    $level = 1;
                    $profile->vip_experience = 1;
                }
                $esVipArr[0]['id'] = $uid;
                $esVipArr[0]['vip_is'] = $level == 100 ? 0 : 1;
                $esVipArr[0]['vip_level'] = $level == 100 ? 0 : $level;
            }
            //真人
            if (isset($data['real_is']) && in_array($data['real_is'], [0, 1])) {
                $profile->real_is = $data['real_is'];
                $esVipArr[0]['id'] = $uid;
                $esVipArr[0]['real_is'] = $data['real_is'];
            }
            //实名
            if (isset($data['identity_is']) && in_array($data['identity_is'], [0, 1])) {
                $profile->identity_is = $data['identity_is'];
                $esVipArr[0]['id'] = $uid;
                $esVipArr[0]['identity_is'] = $data['identity_is'];
            }
            $profile->save();
            //更新es
            (new ESearch('users:users'))->updateSingle($esVipArr);
            //记录管理员日志日志
            $newVal = $extInfo($uid, $profile);
            LogUserModel::gainLog($this->uid, '后台修改会员等级&核验', $oldVal, $newVal, '后台修改会员VIP&核验信息，修改人：' . $this->user->username, 1, 1);
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    public function userInfoUpdate(Request $request)
    {
        $id = $request->input('id', 0);
        $nick = $request->input('nick', '');
        if (empty($nick) || mb_strlen($nick) < 2 || mb_strlen($nick) > 10) {
            return $this->jsonExit(201, '昵称需要在2-10个字之间');
        }
        $user = UsersModel::find($id);
        $user->nick = $nick;
        $user->save();
        //更新es
        $esVipArr[] = [
            'id' => $id,
            'nick' => $nick,
        ];
        //更新es
        (new ESearch('users:users'))->updateSingle($esVipArr);
        return $this->jsonExit(200, 'OK');
    }


    public function userContactUpdate(Request $request)
    {
        $id = $request->input('id', 0);
        $type = $request->input('type', '');
        $contact = $request->input('contact', '');
        if (empty($type) || !in_array($type, ['qq', 'wechat'])) {
            return $this->jsonExit(201, '联系方式类型错误');
        }
        $user = UsersProfileModel::where('user_id', $id)->first();
        $user->$type = H::encrypt($contact);
        $user->save();
        return $this->jsonExit(200, 'OK');
    }

    public function userPushSet(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $type = $request->input('type', '');
        $title = $request->input('title', '');
        $cont = $request->input('cont', '');
        $jump = $request->input('jump', '');
        if (empty($type) || !in_array($type, ['sys', 'push', 'all'])) {
            return $this->jsonExit(201, '推送类型错误');
        }

        JpushModel::senderMaster($user_id, $title, $cont, 'super_push', $type, $jump);
        return $this->jsonExit(200, 'OK');
    }


    //添加会员
    public function userAdd(Request $request)
    {
        $data = $request->all();
        if (!H::checkPhoneNum($data['mobile'])) {
            return $this->jsonExit(401, '手机号错误');
        }
        //头像必传
        if (!isset($data['avatar']) || empty($data['avatar'])) {
            return $this->jsonExit(402, '头像不能为空');
        }
        //生日
        if (!isset($data['birthday']) || empty($data['birthday'])) {
            return $this->jsonExit(403, '生日不能为空');
        }
        $age = H::getAgeByBirthday($data['birthday']);
        if ($age < 18) {
            return $this->jsonExit(405, '年龄不能小于18岁');
        }
        $album = $request->input('album', []);
        //查询用户是否已经注册
        $encryptMobile = H::encrypt($data['mobile']);
        if (UsersModel::where('mobile', $encryptMobile)->first()) {
            return $this->jsonExit(204, '该用户已经注册，请直接登陆');
        }
        if (empty($data['stature']) || !is_numeric($data['stature']) || $data['stature'] < 100) {
            return $this->jsonExit(204, '身高不能为空，必须为数字且不能小于100CM');
        }
        //微信号
        if (empty($data['wechat'])) {
            return $this->jsonExit(403, '微信号码不能为空');
        }
        if (empty($data['nick']) || mb_strlen($data['nick']) < 2 || mb_strlen($data['nick']) > 10) {
            return $this->jsonExit(403, '昵称需要在2-10个字之间');
        }
        $encryptWechat = H::encrypt($data['wechat']);
        //密码一致性
        if (strlen(trim($data['password'])) < 6) {
            return $this->jsonExit(209, '密码不能小于6位');
        }
        if (isset($data['invite']) && !empty($data['invite'])) {
            //查询邀请码是否存在
            $checkInvite = UsersModel::where('uinvite_code', $data['invite'])->first();
            if (!$checkInvite) {
                return $this->jsonExit(210, '邀请码不存在');
            }
        }
        $bio = $request->input('bio', '');
        try {
            DB::beginTransaction();
            $aratar = H::path($data['avatar']);
            $salt = H::randstr(6, 'ALL');
            $user = UsersModel::create([
                'nick' => $data['nick'],
                'sweet_coin' => 0,
                'mobile' => $encryptMobile,
                'avatar' => $aratar,
                'avatar_bg' => $aratar,
                'password' => Hash::make($data['password'] . $salt),
                'birthday' => $data['birthday'],
                'age' => $age,

                'last_ip' => IP,
                'last_login' => CORE_TIME,
                'last_location' => H::Ip2City(IP),
                'last_coordinates' => '0.00,0.00',

                'live_time_latest' => CORE_TIME,
                'album' => [],
                'sex' => $data['sex'],
                'constellation' => H::getConstellationByBirthday($data['birthday']),
                'status' => 1,
                'complete' => 1,
                'salt' => $salt,
                'invited' => isset($checkInvite) ? $checkInvite->uinvite_code : '',
            ]);
            //生成用户的id
            $user->platform_id = H::getPlatformId($user->id);
            $user->uinvite_code = H::createInviteCodeById($user->id);
            //获取融云用户id
            try {
                $rongToken = RongCloud::getToken($user->platform_id, $data['nick'], $aratar);
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }
            $user->rong_token = isset($rongToken['token']) ? $rongToken['token'] : '';
            //创建扩展信息
            UsersProfileModel::create([
                'user_id' => $user->id,
                'register_coordinates' => COORDINATES,
                'register_location' => H::Ip2City(IP),
                'register_ip' => IP,
                'register_date' => CORE_TIME,
                'bio' => $bio,
                'stature' => $data['stature'],
                'wechat' => $encryptWechat,
                'profession' => $data['profession'][1],
                'live_addr' => $data['live_addr'],
                'hobby' => join(',', $data['hobby']),
                'expect' => join(',', $data['expect']),
            ]);
            //更新头像
            AlbumModel::where('path', $data['avatar'])->update(['user_id' => $user->id]);
            //头像图片管理
            $albumUser = [];
            if (count($album) > 0) {
                $albumArr = AlbumModel::whereIn('id', $album)->get();
                foreach ($albumArr as $albums) {
                    $albumUser[] = [
                        'id' => $albums->id,
                        'img_url' => H::path($albums->path),
                        'is_real' => $albums->is_real,
                        'is_private' => $albums->is_private,  //隐私做模糊处理
                        'price' => $albums->price, //价格在非免费时生效
                        'is_free' => $albums->is_free, //是否免费相册
                        'is_video' => $albums->is_video, //是否视频
                        'is_illegal' => $albums->is_illegal,  //是否非法
                    ];
                    $albums->user_id = $user->id;
                    $albums->save();
                }

            }
            $user->album = $albumUser;
            $user->save();
            DB::commit();
            LogUserModel::gainLog($this->uid, '后台管理员添加会员', '会员ID' . $user->id, '', '管理员添加会员，添加人：' . $this->user->username, 1, 1);
            return $this->jsonExit(200, '会员添加成功');
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    //同步会员数据到Es服务
    public function usersSyncEs(Request $request)
    {
        try {
            $st = microtime(1);
            $idRange = [
                's' => 0,
                'e' => 0,
            ];
            (new ESearch('users:users'))->sync($idRange, 'sync');
            $msg = '累计用时：' . round(microtime(1) - $st, 2) . ' S';
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, $msg);
    }

    public function topicSyncEs(Request $request)
    {
        try {
            $st = microtime(1);
            $idRange = [
                's' => 0,
                'e' => 0,
            ];
            (new ESearch('tags:tags'))->sync($idRange, 'sync');
            $msg = '累计用时：' . round(microtime(1) - $st, 2) . ' S';
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, $msg);
    }

    //彻底删除用户
    public function userDelete(Request $request)
    {
        try {
            $uid = $request->input('user_id', 0);
            $user = UsersModel::find($uid);
            if (!$user) {
                return $this->jsonExit(201, '会员不存在');
            }
            if ($user->status != 0) {
//                return $this->jsonExit(202, '请先将用户账号禁用，然后再删除');
            }
            if (is_null($user->unlock_time)) {
//                return $this->jsonExit(203, '请先将用户临时锁定，然后再删除');
            }
            CronCloseModel::delUser($uid);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    //意见反馈
    public function feedbackList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $cate = $request->input('cate');
        $data = FeedbackModel::getDataByPage($page, $size, $q, $cate);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function feedbackDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $feedback = FeedbackModel::where('id', $id)->first();
        if (!$feedback) {
            return $this->jsonExit(201, '记录不存在');
        }
        if ($feedback->status != 1) {
            return $this->jsonExit(202, '未处理的反馈不能删除');
        }
        $feedback->delete();
        return $this->jsonExit(200, 'OK');
    }

    public function feedbackUpdate(Request $request)
    {
        $id = $request->input('id', 0);
        $cont = $request->input('cont', '');
        $feed = FeedbackModel::where('id', $id)->first();
        if (!$feed) {
            return $this->jsonExit(201, '记录不存在');
        }
        $feed->status = 1;
        $feed->save();
        //发送系统消息
        $title = '问题反馈处理通知';
        $cont = !empty($cont) ? $cont : '您的反馈已收到，我们已根据相关法律规定和反馈规则对您反馈的问题进行了处理，感谢您对本平台的支持';
        JpushModel::senderMaster($feed->user_id, $title, $cont, 'feed_back');
        return $this->jsonExit(200, 'OK');
    }

    //更新用户的相册非法性判断
    public function updateUserAlbumIllegal(Request $request)
    {
        $userId = $request->input('user_id');
        $albumId = $request->input('album_id');
        $video = $request->input('video');
        $status = $request->input('status');
        $action = $request->input('action', 'block');
        $user = UsersModel::find($userId);
        $profile = $user->profile;
        if (!$user) {
            return $this->jsonExit(201, '用户不存在');
        }
//        $albumModel = AlbumModel::find($albumId);
//        if (!$albumModel) {
//            return $this->jsonExit(202, '图片不存在');
//        }
//        $albumModel->is_illegal = $status == true ? 1 : 0;
//        $albumModel->save();

        //如果照片违规就推送极光通知
        if ($status && $action == 'block') {
            $video == 0 ? JpushModel::JpushCheck($userId, '', 0, 9) : JpushModel::JpushCheck($userId, '', 0, 7);
        }
        $albums = $video == 0 ? $profile->album : $profile->album_video;
        $albums = $albums ? $albums : [];
        if ($albums) {
            foreach ($albums as $key => $album) {
                if ($action == 'block') {
                    if ($album['id'] == $albumId) {
                        $albums[$key]['is_illegal'] = $status == true ? 1 : 0;
                        if ($status) UsersSettingsModel::setViolation($userId, 'violation_image');
                    }
                }
                if ($action == 'delete') {
                    if ($album['id'] == $albumId) {
                        unset($albums[$key]);
                    }
                }
            }
            //入库
            $video == 0 ? $profile->album = array_values($albums) : $profile->album_video = array_values($albums);
            $profile->save();
            //二次渲染
            foreach ($albums as $k => $item) {
                $albums[$k]['is_illegal'] = isset($item['is_illegal']) && $item['is_illegal'] == 1;
                if ($video == 1) {
                    $albums[$k]['img_url'] = $item['img_url'] . '?x-oss-process=video/snapshot,t_1000,m_fast';
                }
            }
        }
        return $this->jsonExit(200, 'OK', $albums);
    }

    //根据经纬度获取具体地址
    public function exchangePoint(Request $request)
    {
        $point = $request->input('point');
        $res = (new BaiduCloud())->getAddrByPoint($point);
        return $this->jsonExit(200, 'OK', $res);
    }

    public function userNickGet(Request $request)
    {
        $id = $request->input('id', 0);
        $user = UsersModel::getUserInfo($id);
        $nick = '';
        if ($user) {
            $lib = LibNickModel::where('gender', $user->sex)->orderBy(DB::Raw('RAND()'))->first();
            $nick = $lib->nick;
        }
        return $this->jsonExit(200, 'OK', $nick);
    }

    //获取随机头像
    public function userAvatarRefresh(Request $request)
    {
        //刷新是通过哪里获取的，list 是列表，刷新的是默认头像 此时的id是用户id  album 刷新的是相册的头像[此时的id 是图片id]
        $from = $request->input('from', 'list');
        $id = $request->input('id', 0);
        $nick = '';
        if ($from == 'list') {
            $user = UsersModel::getUserInfo($id);
            if ($user) {
                $user->avatar = config('app.cdn_url') . '/ava/' . $user->sex . '-' . rand(1, 75) . '.jpg';
                $user->save();
            }
        }
        //如果从相册选取作为头像
        if ($from == 'album') {
            $album = AlbumModel::where('id', $id)->first();
            $user = UsersModel::getUserInfo($album->user_id);
            if ($user) {
                $user->avatar = $album->path;
                $user->save();
            }
        }
        //更新es
        $esVipArr[] = ['id' => $user->id, 'avatar' => $user->avatar];
        (new ESearch('users:users'))->updateSingle($esVipArr);

        return $this->jsonExit(200, 'OK', $nick);
    }

    // 1 完善资料页面，  2 实名认证  3 更换头像  4 语音签名  5 指定话题列表  6 真人认证  7 跳转VIP页面  8 关注 -> 首页列表  9 动态评论 -> 动态首页
    // 10 完善相册 -> 相册编辑页面  11 录音签名 -> 语音签名页面  12 首冲奖励 -> 充值页面  13 每日动态奖励 -> 发动态  14 私信聊天 -> 首页列表  15 语音通话 ->  首页列表
    // 16 女神认证  17 每日签到 --> 任务列表  18 跳转完善QQ 19 跳转完善微信  20 邀请好友  21 跳转的动态详情 需要传递id  22 跳转他人主页详情 需要传递id
    //  23 跳转话题专题详情 需要传递id 24 消息列表页 如果传id则跳转详情页
    public function pushSuggest()
    {
        $map = [
            1 => [
                'desc' => '资料',
                'title' => '完善您的个人资料，曝光度提升300%',
                'cont' => '完善您的个人资料，便于我们把您推荐给更多用户，您的曝光度有望提升300%，让更多优质用户看到您',
                'scheme' => 1,
            ],
            2 => [
                'desc' => '实名',
                'title' => '完成实名认证，曝光度提升100%',
                'cont' => '完成您的实名认证，便于我们把您推荐给更多用户，您的曝光度有望提升100%',
                'scheme' => 2,
            ],
            3 => [
                'desc' => '头像',
                'title' => '完善头像信息，才能获得展示机会哦',
                'cont' => '完善您的头像信息，便于我们把您推荐给更多用户，只有完善了真实头像的用户才能获得首页展示机会哟。',
                'scheme' => 3,
            ],
            4 => [
                'desc' => '语音',
                'title' => '完善您的语音签名，让更多人听到您的声音',
                'cont' => '完善您的语音签名，让更多人听到您的声音，您的曝光度有望提升120%',
                'scheme' => 4,
            ],
            9 => [
                'desc' => '违规',
                'title' => '请勿发布带有明显违规字眼的信息哦',
                'cont' => '我们鼓励您积极发布动态并分享给身边的朋友，但是请勿发布 无意义或带有 滴滴，dd,喝茶等明显违规的字眼的动态，违规的动态可能被删除哦~',
                'scheme' => 9,
            ],
//            12 => [
//                'desc' => '首充',
//                'title' => '新用户参与首充奖励，免费得VIP体验时长，快来体验吧',
//                'cont' => '新用户首充奖励，即可得3天VIP免费体验，含有VIP所有权益特权，时间有限，快来参与吧，先到先得哦',
//                'scheme' => 12,
//            ],
            19 => [
                'desc' => '微信',
                'title' => '您的微信号无法添加或存在异常，请修改',
                'cont' => '您填写的微信号存在账号异常或无法添加的情况，请更改您的微信号，并重新提交哟',
                'scheme' => 19,
            ],
            18 => [
                'desc' => 'QQ',
                'title' => '您的QQ号无法添加或存在异常，请修改',
                'cont' => '您填写的QQ号存在账号异常或无法添加的情况，请更改您的QQ号码，并重新提交哟',
                'scheme' => 18,
            ],
            20 => [
                'desc' => '邀请',
                'title' => '邀请好友一起来注册，轻松拿200元现金',
                'cont' => '邀请好友一起来注册，最高拿200元现金，他的聊天收入您还有机会一起分成，快去邀请您的好友一起参与吧~',
                'scheme' => 20,
            ],
        ];
        return $this->jsonExit(200, 'OK', $map);
    }
}
