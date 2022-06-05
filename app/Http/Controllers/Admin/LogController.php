<?php

namespace App\Http\Controllers\Admin;


use App\Components\ESearch\ESearch;
use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Models\Discover\DiscoverCmtModel;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\JobsModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogActionModel;
use App\Http\Models\Logs\LogAuthModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Logs\LogImChatModel;
use App\Http\Models\Logs\LogPushModel;
use App\Http\Models\Logs\LogRiskModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\AppleLog\AppleIapInAppModel;
use App\Http\Models\Payment\AppleLog\AppleIapLatestReceiptInfoModel;
use App\Http\Models\Payment\AppleLog\AppleIapModel;
use App\Http\Models\Payment\AppleLog\AppleIapPendingRenewalInfoModel;
use App\Http\Models\Payment\Callback\CallbackAlipayModel;
use App\Http\Models\Payment\Callback\CallbackWechatModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\CommonModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Admin\ActiveLogModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Resource\{AlbumModel, AvatarModel, UploadModel};
use App\Http\Models\SettingsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RongCloud;

class LogController extends AuthAdmController
{
    public function logChangeList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $type = $request->input('type');
        $status = $request->input('status');
        $date = $request->input('date', []);
        $data = LogChangeModel::getDataByPage($page, $size, $q, $type, $status, $date);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function logSysErrList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $date = $request->input('date', []);
        $data = MessageModel::getAdminPageAction($page, $size, $q, $date);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function logSysErrDel(Request $request)
    {
        $ids = $request->input('ids', []);
        MessageModel::whereIn('id', $ids)->delete();
        return $this->jsonExit(200, 'OK');
    }


    public function logImChat(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $err = $request->input('err');
        $date = $request->input('date', []);
        $data = LogImChatModel::getAdminPageAction($page, $size, $err, $q, $date);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function logImChatDel(Request $request)
    {
        $ids = $request->input('ids', []);
        LogImChatModel::whereIn('id', $ids)->delete();
        return $this->jsonExit(200, 'OK');
    }

    public function logAuth(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $id = $request->input('id', 0);
        $status = $request->input('status');
        $date = $request->input('date', []);
        $data = LogAuthModel::getAdminPageAction($page, $size, $status, $q, $date, $id);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function logAuthUpdate(Request $request)
    {
        $id = $request->input('id', []);
        $column = $request->input('act', '');
        $val = $request->input('value', 1);
        $log = LogAuthModel::where('id', $id)->first();
        if (!$log) {
            return $this->jsonExit(201, '记录不存在');
        }
        if ($column == 'delete') {
            $log->delete();
            return $this->jsonExit(200, 'OK');
        }
        //操作认证债状态
        if ($column == 'status') {
            $profile = UsersProfileModel::getUserInfo($log->user_id);
            //这里加个预判断
            if (($profile->identity_is == 0 && $val == 0) || ($profile->identity_is == 1 && $val == 1)) {
                return $this->jsonExit(201, '认证状态与手动要修改为的状态一致，无需操作');
            }
            $profile->identity_is = $val == 1 ? 1 : 0;
            $profile->identity_at = $val == 1 ? CORE_TIME : null;
            $profile->identity_ended_at = $val == 1 ? CORE_TIME : null;
            $profile->identity_mobile = $val == 1 ? $log->mobile : null;
            $profile->identity_card = $val == 1 ? $log->idcard : null;
            $profile->identity_name = $val == 1 ? $log->name : null;
            $profile->save();
            //更新认证信息到es
            EsDataModel::updateEsUser([
                'id' => $log->user_id,
                'identity_is' => $val,
            ]);
        }
        $log->$column = $val;
        $log->save();
        return $this->jsonExit(200, 'OK');
    }


    //数美风控模拟数据
    public function logRisk(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $id = $request->input('id', 0);
        $status = $request->input('status');
        $date = $request->input('date', []);
        $data = LogRiskModel::getAdminPageAction($page, $size, $status, $q, $date, $id);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function logRiskUpdate(Request $request)
    {
        $id = $request->input('id', []);
        $column = $request->input('act', '');
        $val = $request->input('value', 1);
        $log = LogRiskModel::where('id', $id)->first();
        if (!$log) {
            return $this->jsonExit(201, '记录不存在');
        }
        if ($column == 'delete') {
            $log->delete();
            return $this->jsonExit(200, 'OK');
        }
        //操作认证债状态
        if ($column == 'status') {
            $profile = UsersProfileModel::getUserInfo($log->user_id);
            //这里加个预判断
            if (($profile->identity_is == 0 && $val == 0) || ($profile->identity_is == 1 && $val == 1)) {
                return $this->jsonExit(201, '认证状态与手动要修改为的状态一致，无需操作');
            }
            $profile->identity_is = $val == 1 ? 1 : 0;
            $profile->identity_at = $val == 1 ? CORE_TIME : null;
            $profile->identity_ended_at = $val == 1 ? CORE_TIME : null;
            $profile->identity_mobile = $val == 1 ? $log->mobile : null;
            $profile->identity_card = $val == 1 ? $log->idcard : null;
            $profile->identity_name = $val == 1 ? $log->name : null;
            $profile->save();
        }
        $log->$column = $val;
        $log->save();
        return $this->jsonExit(200, 'OK');
    }


    public function logJobsList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $date = $request->input('date', []);
        $data = JobsModel::getAdminPageAction($page, $size, $status, $q, $date);
        return $this->jsonExit(200, 'OK', $data);
    }


    /*---0拒绝1通过----*/
    public function logChangeUpdate($type, Request $request)
    {
        $val = $request->input('value');
        $id = $request->input('id');
        $action = $request->input('action');
        $change = LogChangeModel::find($id);
        if (!$change) {
            return $this->jsonExit(201, '记录不存在');
        }
        if ($type == 'delete') {
            $change->delete();
            return $this->jsonExit(200, 'OK');
        }
        if ($action == 'avatar') {
            UsersModel::where('id', $change->user_id)->update(['avatar_illegal' => $val == 0 ? 1 : 0]);
            if ($val == 0) UsersSettingsModel::setViolation($change->user_id, 'violation_avatar');
        }
        if ($action == 'bio' && $val == 0) {
            UsersProfileModel::where('user_id', $change->user_id)->update(['illegal_bio' => 1, 'bio' => '']);
            UsersSettingsModel::setViolation($change->user_id, 'violation_bio');
        }
        if ($action == 'contact_wechat' && $val == 0) {
            UsersProfileModel::where('user_id', $change->user_id)->update(['illegal_wechat' => 1, 'wechat' => null]);
            //极光推送
            JpushModel::JpushCheck($change->user_id, '', 0, 21);
        }
        if ($action == 'contact_qq' && $val == 0) {
            UsersProfileModel::where('user_id', $change->user_id)->update(['illegal_qq' => 1, 'qq' => null]);
            //极光推送
            JpushModel::JpushCheck($change->user_id, '', 0, 20);
        }
        if ($action == 'sound_bio' && $val == 0) {
            UsersProfileModel::where('user_id', $change->user_id)->update(['illegal_sound' => 1, 'sound' => []]);
        }
        if ($action == 'discover_comment' && $val == 0) {
            //删除对应的信息
            DiscoverCmtModel::where('id', $change->event_id)->delete();
            UsersSettingsModel::setViolation($change->user_id, 'violation_cmt');
        }
        if ($action == 'discover_publish' && $val == 0) {
            DiscoverModel::where('id', $change->event_id)->delete();
            UsersSettingsModel::setViolation($change->user_id, 'violation_discover');
        }
        $change->$type = $val;
        $change->save();
        return $this->jsonExit(200, 'OK');
    }

    /*---*行为日志*---*/
    public function logActionList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = LogActionModel::getAdminPageAction($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    /*----*操作日志*---*/
    public function logOperateList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type', 20);
        $q = $request->input('q', '');
        $loginlogs = LogUserModel::getPageLog($page, $size, $q, $type);
        return $this->jsonExit(200, 'OK', $loginlogs);
    }

    /*----*登陆日志*---*/
    public function logLoginList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type', 20);
        $q = $request->input('q', '');
        $loginlogs = LoginLogModel::getAdminPageLog($page, $size, $q, $type);
        return $this->jsonExit(200, 'OK', $loginlogs);
    }

    /*----*登陆错误日志*---*/
    public function logLoginErrList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type', 20);
        $q = $request->input('q', '');
        $loginlogs = LoginErrModel::getAdminPageLog($page, $size, $q, $type);
        return $this->jsonExit(200, 'OK', $loginlogs);
    }

    /*----* * 短信日志 ----*/
    public function LogSmsList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type', 20);
        $q = $request->input('q', '');
        $data = LogSmsModel::getAdminPageSms($page, $size, $q, $type);
        return $this->jsonExit(200, 'OK', $data);
    }

    /*----* * 用激活日志 ----***/
    public function logPushList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('mtype');
        $q = $request->input('q');
        $data = LogPushModel::getPageAdminItems($page, $size, $q, $type);
        return $this->jsonExit(200, 'OK', $data);
    }

    /*-------------****苹果支付日志***------------*/
    public function AppleIapList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = AppleIapModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function AppleIapInAppList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = AppleIapInAppModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function AppleIapLatestReceiptInfo(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = AppleIapLatestReceiptInfoModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function AppleIapPendingRenewalInfo(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = AppleIapPendingRenewalInfoModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }


    public function LogCallbackAlipay(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $date = $request->input('dates');
        $data = CallbackAlipayModel::getAdminPageItems($page, $size, $q, $date);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function LogCallbackWechat(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $date = $request->input('dates');
        $data = CallbackWechatModel::getAdminPageItems($page, $size, $q, $date);
        return $this->jsonExit(200, 'OK', $data);
    }

    /*--------资金变动变化记录----------*/
    public function logBalance(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $dates = $request->input('dates', []);
        $q = $request->input('q', '');
        $id = $request->input('id', '');
        $operate = $request->input('operate', '');
        $type = $request->input('type');
        $table = $request->input('table', 'log_balance');
        $data = LogBalanceModel::getPageAdminItems($table, $page, $size, $dates, $type, $operate,$id, $q);
        return $this->jsonExit(200, 'OK', $data);
    }
}
