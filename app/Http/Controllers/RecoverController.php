<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Libraries\Tools\RecoverInfo;
use App\Http\Models\Lib\LibBannersModel;
use App\Http\Models\Logs\LogRecoverModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;

class RecoverController extends Controller
{
    public function smsSend(Request $request)
    {
        $mobile = $request->input('account');
        $channel = $request->input('channel', 'tangguo');
        if (!H::checkPhoneNum($mobile)) {
            return $this->jsonExit(201, '手机号码错误');
        }
        if ($channel == 'tangguo') {
            $res = (new RecoverInfo())->tangGuoSend($mobile);
        }
        return $this->jsonExit(200, 'OK');
    }

    public function loginIn(Request $request)
    {
        $mobile = $request->input('account');
        $mcode = $request->input('mcode');
        $user_id = $request->input('user_id', 0);
        $channel = $request->input('channel', 'tangguo');
        if (!H::checkPhoneNum($mobile)) {
            return $this->jsonExit(201, '手机号码错误');
        }
        $user = UsersModel::getUserInfo($user_id);
        if ($user_id < 1000 || !$user) {
            return $this->jsonExit(203, '用户错误');
        }
        if ($channel == 'tangguo') {
            $res = (new RecoverInfo())->tangGuoLogin($mobile, $mcode);
            $msg = $res['msg'];
            if (isset($res['status']) && $res['status'] == 0) {
                $data = $res['data'];
                $exist = LogRecoverModel::where([['user_id', $user_id], ['status', 1], ['channel', $channel]])->first();
                if ($exist) {
                    return $this->jsonExit(201, '该平台数据已经迁移，无需重复操作');
                }
                LogRecoverModel::updateOrCreate([
                    'channel' => 'tangguo',
                    'user_id' => $user_id,
                ], [
                    'uid' => $data['uid'],
                    'account' => $mobile,
                    'password' => $mcode,
                    'token' => $data['token'],
                    'im_token' => $data['im_token'],
                    'ext' => json_encode($data),
                    'device' => $res['device'],
                ]);
                return $this->jsonExit(200, 'OK');
            } else {
                return $this->jsonExit(201, $msg);
            }
        }
        return $this->jsonExit(200, 'OK');
    }


    public function recoverMove(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $channel = $request->input('channel', 'tangguo');
        $exist = LogRecoverModel::where([['user_id', $user_id], ['status', 0], ['channel', $channel]])->first();
        if (!$exist) {
            return $this->jsonExit(201, '请先登陆，然后再迁移');
        }
        $exist->status = 1;
        $info = (new RecoverInfo())->getInfo($exist->token, $exist->device);
        if (count($info) > 0) {
            $profile = UsersProfileModel::getUserInfo($user_id);
            $album = $profile->album;
            $pathArr = array_column($album, 'img_url');
            foreach ($info as $item) {
                if (!in_array($item, $pathArr)) {
                    $user_id += 1;
                    $album[] = [
                        'id' => $user_id,
                        'img_url' => $item,
                        'price' => 0, //价格在非免费时生效
                        'is_real' => 0,
                        'is_private' => 0,  //隐私做模糊处理
                        'is_free' => 0, //是否免费相册
                        'is_video' => 0, //是否视频
                        'is_illegal' => 0, //是否非法
                    ];
                }
            }
            $profile->album = $album;
            $profile->save();
        }
        $exist->save();

        return $this->jsonExit(200, 'OK');
    }
}
