<?php

namespace App\Http\Controllers\Users;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Tools\AliyunOss;
use App\Http\Libraries\Tools\BaiduCloud;
use App\Http\Libraries\Tools\GraphCompare;
use App\Http\Controllers\AuthController;
use App\Http\Libraries\Tools\IdentityAuth;
use App\Http\Models\SettingsModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Resource\{AlbumModel, UploadModel, AvatarModel};
use App\Http\Models\{EsDataModel,
    Logs\ApiLeftModel,
    Logs\LogAlbumViewModel,
    Logs\LogAuthModel,
    MessageModel,
    Users\UsersModel,
    Users\UsersRewardModel
};
use App\Http\Models\Logs\LogChangeModel;
use Illuminate\Http\Request;

class UsersAuthController extends AuthController
{
    //这里的真人认证我们使用全路径进行对比，主要是因为可以少一次数据库的查询
    public function realAuth(Request $request)
    {
        $img_id = $request->input('img_id');
        if (empty($img_id)) {
            return $this->jsonExit(201, '认证照片不存在');
        }
        $avaRow = AvatarModel::where([['id', $img_id], ['user_id', $this->uid], ['usefor', 'auth']])->first();
        if (!$avaRow || !$avaRow->path) {
            return $this->jsonExit(202, '认证照片错误');
        }
        $realNum = HR::getUniqueNum($this->uid, 'users-real-num');
        if ($realNum > 3) {  //累计进行的是三次
            return $this->jsonExit(205, '当日认证次数过多，明天再试吧');
        }
        $user = UsersModel::where('id', $this->uid)->first();
        $profile = $user->profile;
        if ($profile->real_is == 1) {
            return $this->jsonExit(206, '您已认证成功，无需重复认证');
        }
        $avatar = $user->avatar;
        $auth_pic = H::path($avaRow->path);
        try {
            $score = (new GraphCompare())->faceCheck($avatar, $auth_pic);
            HR::updateUniqueNum($this->uid, H::gainStrId(), 'users-real-num');
            if (intval($score) > 80) {  //大于85 判定为本人
                //处理认证逻辑&更新数据库
                $profile->auth_pic = $auth_pic;
                $profile->real_is = 1;
                $profile->real_at = CORE_TIME;
                $profile->save();
                //更新资源信息
                $avaRow->used = 1;
                $avaRow->processed = 1;
                $avaRow->save();
                //更新es
                EsDataModel::updateEsUser([
                    'id' => $this->uid,
                    'real_is' => 1,
                ]);
                $res = ['judge' => 1, 'msg' => '认证成功', 'score' => $score];
                //下发真人认证奖励
                UsersRewardModel::userRewardSet($this->uid, 'zhenrenrenzheng', $user);
                //记录认证结果
                LogChangeModel::gainLog($this->uid, 'real_auth', $auth_pic);
            } else {
                $res = ['judge' => 2, 'msg' => '非同一个人', 'score' => $score];
            }
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            $res = ['judge' => 3, 'msg' => '未找到人脸', 'score' => 0];
            return $this->jsonExit(200, 'OK', $res);
        }
    }

    //身份证三要素
    public function identityAuth(Request $request)
    {
        $name = $request->input('name');
        $idcard = $request->input('idcard');
        $mobile = $request->input('mobile');
        if (empty($name) || empty($idcard) || empty($mobile)) {
            return $this->jsonExit(201, '身份证，姓名，手机号均不能为空');
        }
        if (!H::isValidCard($idcard)) {
            return $this->jsonExit(202, '身份证号码错误');
        }
        if (!H::checkPhoneNum($mobile)) {
            return $this->jsonExit(203, '手机号码错误');
        }
        if (mb_strlen($name) < 2) {
            return $this->jsonExit(204, '姓名长度错误');
        }
        //先判断认证信息是否已经使用过
        $used = LogAuthModel::where([['name', $name], ['idcard', H::encrypt($idcard)], ['mobile', H::encrypt($mobile)], ['status', 1]])->first();
        if ($used) {
            return $this->jsonExit(205, '认证信息已存在，请检查');
        }
        $sum = HR::getUniqueNum($this->uid, 'users-identity-num');
        if ($sum > 3) {
            return $this->jsonExit(205, '当日认证次数过多，明天再试吧');
        }
        $profile = UsersProfileModel::where('user_id', $this->uid)->first();
        if ($profile->identity_is == 1) {
            return $this->jsonExit(206, '您已认证成功，无需重复认证');
        }
        $res = false;
        try {
            $res = (new IdentityAuth())->getAuth([
                'id_number' => $idcard,
                'name' => $name,
                'phone_number' => $mobile,
            ]);
            //记录认证日志
            $insertData = [
                'name' => $name,
                'idcard' => H::encrypt($idcard),
                'mobile' => H::encrypt($mobile),
                'user_id' => $this->uid,
                'user_sex' => $this->sex,
                'status' => $res ? 1 : 0,
            ];
            LogAuthModel::create($insertData);
            //使用次数加1
            ApiLeftModel::where('type', 'identity')->decrement('left_num');
            //个人认证次数+1
            $profile->increment('identity_time');
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
        //增加认证次数
        HR::updateUniqueNum($this->uid, uniqid());
        if ($res) {
            //更新认证信息到数据库
            $upData = [
                'identity_name' => $name,
                'identity_card' => H::encrypt($idcard),
                'identity_mobile' => H::encrypt($mobile),
                'identity_at' => CORE_TIME,
                'identity_ended_at' => CORE_TIME,
                'identity_is' => 1,
            ];
            UsersProfileModel::where('user_id', $this->uid)->update($upData);
            //更新认证信息到es
            EsDataModel::updateEsUser([
                'id' => $this->uid,
                'identity_is' => 1,
            ]);
            //下发实人认证奖励
            UsersRewardModel::userRewardSet($this->uid, 'shimingrenzheng');
            return $this->jsonExit(200, '认证成功');
        } else {
            return $this->jsonExit(204, '认证失败');
        }
    }

    //女神认证，接入
    public function goddessAuth(Request $request)
    {
        $img_id = $request->input('img_id');
        if (empty($img_id)) {
            return $this->jsonExit(201, '认证照片不存在');
        }
        $avaRow = AvatarModel::where([['id', $img_id], ['user_id', $this->uid], ['usefor', 'auth']])->first();
        if (!$avaRow || !$avaRow->path) {
            return $this->jsonExit(202, '认证照片错误');
        }
        $goddessNum = HR::getUniqueNum($this->uid, 'users-goddess-num');
        if ($goddessNum > 3) {  //累计进行的是三次
            return $this->jsonExit(205, '当日认证次数过多，明天再试吧');
        }
        $auth_pic = H::path($avaRow->path);
        $profile = UsersProfileModel::where('user_id', $this->uid)->first();
        if ($profile->goddess_is == 1) {
            return $this->jsonExit(203, '您已经认证过女神，无需重复操作');
        }
        if ($profile->real_is == 0) {
            return $this->jsonExit(203, '请先进行真人认证，然后再认真女神');
        }
        $profile->goddess_at = CORE_TIME;
        try {
            $score = (new GraphCompare())->faceCheck($auth_pic, $profile->auth_pic);
            if ($score < 85) {
                HR::updateUniqueNum($this->uid, H::gainStrId(), 'users-goddess-num');
                return $this->jsonExit(203, '女神认证照片与真人认证差别过大，请重新认证');
            }
            $res = (new BaiduCloud())->getFaceBaseInfo($auth_pic, $this->sex);
            if (intval($res) >= 67) {   //大于70分就认为是女神
                $profile->goddess_is = 1;
                $profile->goddess_score = $res;
                $profile->goddess_score_compare = $score;
                $profile->goddess_end_at = CORE_TIME;
                $profile->goddess_pic = $auth_pic;
                $profile->save();
                //更新资源信息
                $avaRow->used = 1;
                $avaRow->processed = 1;
                $avaRow->save();
                //更新es
                EsDataModel::updateEsUser([
                    'id' => $this->uid,
                    'goddess_is' => 1,
                ]);
                //下发女神认证奖励
                UsersRewardModel::userRewardSet($this->uid, 'nvshenrenzheng');
                return $this->jsonExit(200, '认证成功');
            } else {
                return $this->jsonExit(206, '认证失败');
            }
        } catch (\Exception $e) {
            HR::updateUniqueNum($this->uid, H::gainStrId(), 'users-goddess-num');
            return $this->jsonExit(208, $e->getMessage());
        }
    }
}
