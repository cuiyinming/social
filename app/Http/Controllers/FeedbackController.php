<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Models\Lib\LibQuestionsModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\System\FeedbackModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\System\FeedbackRequest;

class FeedbackController extends AuthController
{
    //反馈的内容包含 comment|discover|user |feedback
    public function feedback(FeedbackRequest $request)
    {
        try {
            $data = $request->all();
            $data['user_id'] = $this->uid;
            $sign = md5(json_encode($data));
            $data['sign'] = $sign;
            $exist = FeedbackModel::where('sign', $sign)->first();
            if ($exist) {
                return $this->jsonExit(202, '重复内容请勿反复提交');
            }
            //数据相差校验
            if ($data['type'] == 'feedback' && $data['type_id'] != 0) {
                return $this->jsonExit(201, '问题反馈无需传递内容id');
            }
            if (!isset($data['shoots']) || empty($data['shoots'])) {
                $data['shoots'] = null;
            }
            FeedbackModel::create($data);
            if (in_array($data['type'], ['comment', 'discover', 'user'])) {
                UsersProfileModel::where('user_id', $this->uid)->increment('report_num');
            }
            if ($data['type'] == 'user') { //如果是用户的话就增加被举报用户的被举报次数
                $profile = UsersProfileModel::where('user_id', $data['type_id'])->first();
                if ($profile) {
                    $profile->be_report_num += 1;
                    $profile->save();
                }
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    /**
     * 常见问题反馈
     */
    public function question(Request $request)
    {
        $data = LibQuestionsModel::getAllData();
        return $this->jsonExit(200, 'OK', $data);
    }

    //添加升级信息回执
    public function updateInfo(Request $request)
    {
        $sms = SettingsModel::getSigConf('sms');
        $version = '1.0';
        if (CHANNEL == 'ios' && isset($sms['update_ver'])) {
            $version = $sms['update_ver'];
        }
        if (CHANNEL == 'android' && isset($sms['update_ver_android'])) {
            $version = $sms['update_ver_android'];
        }
        $data = [
            'update_on' => false,
            'update_info' => $sms['update_info'] ?? '',
            'update_ver' => $version,
            'user_ver' => VER,
        ];
        $now_version = intval(str_replace('.', '', VER));
        $new_version = intval(str_replace('.', '', $data['update_ver']));
        if ($now_version < $new_version) {
            if (isset($sms['update_on']) && $sms['update_on'] == 1 && CHANNEL == 'ios') {
                $data['update_on'] = true;
            }
            if (isset($sms['update_on_android']) && $sms['update_on_android'] == 1 && CHANNEL == 'android') {
                $data['update_on'] = true;
            }
        }
        $ignore = HR::getVerUpdate($this->uid, CHANNEL);
        if ($ignore == 1) {
            $data['update_on'] = false;
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    //添加升级信息获取
    public function updateSet(Request $request)
    {
        $set = $request->input('set', 0);
        $ttl = 86400;
        if ($set == 1) {
            HR::setVerUpdate($this->uid, $set, CHANNEL, $ttl);
        }
        return $this->jsonExit(200, 'OK');
    }
}
