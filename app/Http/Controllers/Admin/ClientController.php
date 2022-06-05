<?php

namespace App\Http\Controllers\Admin;


use App\Components\ESearch\ESearch;
use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Models\Admin\AdmRoleModel;
use App\Http\Models\Client\ClientBalanceModel;
use App\Http\Models\Client\ClientDrawModel;
use App\Http\Models\Client\ClientLogModel;
use App\Http\Models\Client\ClientMessageModel;
use App\Http\Models\Client\ClientProfitModel;
use App\Http\Models\Client\ClientReportModel;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\Lib\LibBannersModel;
use App\Http\Models\Lib\LibBioModel;
use App\Http\Models\Lib\LibChatModel;
use App\Http\Models\Lib\LibCodeModel;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Lib\LibQuestionsModel;
use App\Http\Models\Logs\LogActionModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Logs\LogDrawModel;
use App\Http\Models\Logs\LogPushModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\CommonModel;
use App\Http\Models\SettingsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ClientController extends AuthAdmController
{
    public function userList(Request $request)
    {
        $status = $request->input('status');
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $id = $request->input('id');
        $unlock_time = $request->input('unlock_time');
        $invited = $request->input('invite');
        $date = $request->input('dates', []);
        $clients = ClientUsersModel::getDataByPage($q, $status, $unlock_time, $date, $page, $size, $id, $invited);
        return $this->jsonExit(200, 'OK', $clients);
    }

    public function usersInfoSet(Request $request)
    {
        $id = $request->input('id', 0);
        $password = $request->input('password', '');
        $userInfo = ClientUsersModel::find($id);
        if (!$userInfo) {
            return $this->jsonExit(205, '用户信息不存在');
        }
        if (!empty($password)) $userInfo->password = Hash::make(trim($password));
        $userInfo->save();
        //记录管理员日志日志
        LogUserModel::gainLog($this->uid, '后台修改代理密码', ' ******', ' ******', '后台修改代理密码，修改人：' . $this->user->username, 1, 2);
        return $this->jsonExit(200, 'OK');
    }

    public function userBalanceSet(Request $request)
    {
        $id = $request->input('id', 0);
        $opt = $request->input('opt', 0); //2增加 1减少
        $money = $request->input('money', 0);
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
        try {
            DB::beginTransaction();
            //查询用户
            $user = ClientUsersModel::where('id', $id)->lockForUpdate()->first();
            if (!$user) {
                DB::rollback();
                return $this->jsonExit(204, '用户不存在');
            }
            $before_amount = $user->amount;
            if ($opt == 1 && $before_amount < $money) {
                DB::rollback();
                return $this->jsonExit(205, '操作数小于可用数目，请核对');
            }
            if ($opt == 1) $user->amount -= $money;
            if ($opt == 2) $user->amount += $money;
            $after = $user->amount;
            $user->save();
            //记录日志
            $desc = "平台人工操作{$money}元";
            $remark = "管理员({$this->uid})后台手动操作：{$money}元,备注：{$remark}";
            $table = 'client_balance';
            LogBalanceModel::gainLogBalance($id, $before_amount, $money, $after, 'admin_add', $desc, $remark, $this->uid, $table);
            DB::commit();
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(209, $e->getMessage());
        }
    }

    public function userInfoUpdate(Request $request)
    {
        try {
            DB::beginTransaction();
            $id = $request->input('id', 0);
            $table = $request->input('table', 'user');
            $column = $request->input('column', 'chat_add');
            $val = $request->input('val', 0);
            $user = ClientUsersModel::find($id);
            if (!$user) {
                return $this->jsonExit(201, '记录不存在，请检查');
            }
            if ($table == 'users') {
                if ($column == 'unlock_time') {
                    //默认是锁定30分钟
                    $user->unlock_time = $val ? date('Y-m-d H:i:s', time() + 1800) : null;
                    $user->login_try_time = 0;
                }
                if ($column == 'status') {
                    $user->locked_at = $val ? date('Y-m-d H:i:s') : null;
                    $user->$column = $val ? 0 : 1;
                }
                $user->save();
                DB::commit();
                return $this->jsonExit(200, 'OK');
            }
            DB::commit();
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            DB::rollBack();
            return $this->jsonExit(209, $e->getMessage());
        }
    }

    public function userDelete(Request $request)
    {
        try {
            $uid = $request->input('user_id', 0);
            $user = ClientUsersModel::find($uid);
            if (!$user) {
                return $this->jsonExit(201, '会员不存在');
            }
            if ($user->status != 0) {
                return $this->jsonExit(202, '请先将用户账号禁用，然后再删除');
            }
            ClientUsersModel::where('id', $uid)->delete();
            ClientLogModel::where('invited', $user->invite_code)->delete();
            ClientReportModel::where('user_id', $uid)->delete();
            ClientProfitModel::where('user_id', $uid)->delete();
            ClientMessageModel::where('user_id', $uid)->delete();
            ClientDrawModel::where('user_id', $uid)->delete();
            ClientBalanceModel::where('user_id', $uid)->delete();
            //更新邀请关系
            ClientUsersModel::where('invited', $user->invite_code)->update(['invited' => 0]);
            UsersModel::where('client_code', $user->invite_code)->update(['client_code' => 0]);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }


    public function userReport(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $id = $request->input('id', 0);
        $date = $request->input('date', []);
        $data = ClientReportModel::getPageItems($page, $size, $date, $id);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function clientPromoteLog(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q', '');
        $id = $request->input('id', 0);
        $date = $request->input('date', []);
        $data = ClientLogModel::getPageItems($page, $size, $date, $id,$q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function clientPromoteLogDel(Request $request)
    {
        $ids = $request->input('ids', []);
        ClientLogModel::whereIn('id', $ids)->delete();
        return $this->jsonExit(200, 'OK');
    }

    public function clientDrawList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $data = ClientDrawModel::getDataByPage($page, $size, $q, $status);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function clientDrawUpdate(Request $request)
    {
        //val => 1 同意 2拒绝
        $id = $request->input('id', 0);
        $act = $request->input('act', '');
        $val = $request->input('value', '');
        $data = ClientDrawModel::where('id', $id)->first();
        if (!$data) {
            return $this->jsonExit(202, '记录不存在');
        }
        if ($data->status == 1) {
            return $this->jsonExit(201, '订单已经处理过，无需重复操作');
        }
        if ($act == 'status') {
            try {
                DB::beginTransaction();
                $data->status = $val;
                $data->end_at = CORE_TIME;
                $data->amount_checked = $data->amount;
                $data->save();
                $user = ClientUsersModel::where('id', $data->user_id)->first();
                if ($data->amount > $user->amount_frozen) {
                    return $this->jsonExit(201, '提现金额大于冻结金额，系统错误');
                }
                if ($user) {
                    //同意处理完毕
                    if ($val == 1) {
                        $user->amount_frozen -= $data->amount;
                        $user->total_acount += $data->amount;
                        $user->save();
                        //添加变动记录
                        $before = $after = $user->amount;
                        $desc = '提现成功：' . $data->amount . ' 元' . '，剩余冻结：' . $user->amount_frozen;
                        $remark = '提现成功：' . $data->amount . ' 元';
                        LogBalanceModel::gainLogBalance($data->user_id, $before, 0, $after, 'draw', $desc, $remark, 0, 'client_balance');
                        //添加分润逻辑【添加代理的分润逻辑】 ===== 目前为止这部分逻辑已经不需要了，只做代理拉新会员的分成，没有代理拉代理的分成计算部分了
//                        if (!empty($user->invited)) {
//                            //查到第一级
//                            $level = config('settings.client')['client'];//开始分润
//                            $first = ClientUsersModel::where([['status', 1], ['invite_code', $user->invited]])->first();
//                            if ($first) {
//                                $profit_a = round($data->amount_checked * $level['level_1'], 2);
//                                //分润先进入审核
//                                $desc = '邀请的代理提现分成' . $profit_a . ' 元，【一级代理】';
//                                $remark = '邀请的代理提现' . $data->amount_checked . '元，执行比例：' . ($level['level_1'] * 100) . '%，分成' . $profit_a . ' 元，【一级代理】';
//                                ClientProfitModel::gainLogProfit($first->id, $profit_a, $data->amount_checked, 'client_profit', $desc, $remark);
//
//                                //查询二级分销
//                                $second = ClientUsersModel::where([['status', 1], ['invite_code', $first->invited]])->first();
//                                if ($second) {
//                                    $profit_b = round($data->amount_checked * $level['level_2'], 2);
//                                    //分润先进入审核
//                                    $desc = '邀请的代理提现分成' . $profit_b . ' 元，【一级代理】';
//                                    $remark = '邀请的代理提现' . $data->amount_checked . '元，执行比例：' . ($level['level_2'] * 100) . '%，分成' . $profit_b . ' 元，【二级代理】';
//                                    ClientProfitModel::gainLogProfit($second->id, $profit_b, $data->amount_checked, 'client_profit', $desc, $remark);
//
//                                    //查询三级
//                                    $third = ClientUsersModel::where([['status', 1], ['invite_code', $second->invited]])->first();
//                                    if ($third) {
//                                        $profit_c = round($data->amount_checked * $level['level_3'], 2);
//                                        //分润先进入审核
//                                        $desc = '邀请的代理提现分成' . $profit_c . ' 元，【三级代理】';
//                                        $remark = '邀请的代理提现' . $data->amount_checked . '元，执行比例：' . ($level['level_3'] * 100) . '%，分成' . $profit_c . ' 元，【三级代理】';
//                                        ClientProfitModel::gainLogProfit($third->id, $profit_c, $data->amount_checked, 'client_profit', $desc, $remark);
//
//                                    }
//                                }
//                            }
//                        }
                    }
                    //拒绝
                    if ($val == 2) {
                        $before = $user->amount;
                        $user->amount_frozen -= $data->amount;
                        $user->amount += $data->amount;
                        $user->save();
                        //添加变动记录
                        $desc = '提现被拒绝，提现冻结原路退回：' . $data->amount . ' 元，' . '剩余冻结：' . $user->amount_frozen;
                        $remark = '提现拒绝：' . $data->amount . ' 元';
                        LogBalanceModel::gainLogBalance($data->user_id, $before, $data->amount, $user->amount, 'draw', $desc, $remark, 0, 'client_balance');
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                MessageModel::gainLog($e, __FILE__, __LINE__);
                return $this->jsonExit(202, '服务错误');
            }
        }
        return $this->jsonExit(200, 'OK');
    }

    public function clientProfitList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $data = ClientProfitModel::getDataByPage($page, $size, $q, $status);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function clientProfitUpdate(Request $request)
    {
        //val => 1 同意 2拒绝
        $id = $request->input('id', 0);
        $act = $request->input('act', '');
        $val = $request->input('value', '');
        $remark = $request->input('remark', '');
        $data = ClientProfitModel::where('id', $id)->first();
        if (!$data) {
            return $this->jsonExit(202, '记录不存在');
        }
        if ($data->status == 1) {
            return $this->jsonExit(201, '订单已经处理过，无需重复操作');
        }
        if ($act == 'status') {
            try {
                DB::beginTransaction();
                $data->status = $val;
                $data->checked_at = CORE_TIME;
                $data->save();
                $user = ClientUsersModel::where('id', $data->user_id)->first();
                if ($user) {
                    //同意处理完毕
                    if ($val == 1) {
                        $before = $user->amount;
                        $user->amount += $data->amount;
                        $user->save();
                        //添加变动记录
                        $after = $user->amount;
                        $remark = '审核成功';
                        LogBalanceModel::gainLogBalance($data->user_id, $before, $data->amount, $after, $data->type, $data->desc, $remark, 0, 'client_balance');
                    }
                    //拒绝
                    if ($val == 2) {
                        $data->remark = $remark;
                        $data->save();
                    }
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                MessageModel::gainLog($e, __FILE__, __LINE__);
                return $this->jsonExit(202, '服务错误');
            }
        }
        return $this->jsonExit(200, 'OK');
    }


}
