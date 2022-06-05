<?php

namespace App\Console\Commands\Process;

use App\Http\Models\EsDataModel;
use App\Http\Libraries\Tools\ApplePay;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\CommonModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{R, HR};

class vip extends Command
{

    protected $signature = 'vip:daily {type?} {user_id?}';
    protected $description = '每日执行vip 用户的退款核查等基础工作';
    protected $user_id = 0;

    public function __construct()
    {
        parent::__construct();
    }


    public function handle()
    {
        set_time_limit(0);
        $s = time();
        $type = $this->argument('type') ?: 0;
        $this->user_id = $this->argument('user_id') ?: 0;
        if ($this->user_id == 0) echo date('Y-m-d H:i:s') . PHP_EOL;
        //处理更新过期会员
        if (in_array($type, [0, 1])) {
            $this->_handExp();
        }
        //每日凌晨校验 参与订阅续订信息
        if (in_array($type, [0, 2])) {
            $this->_subscribe();
        }
        //单独处理退款的逻辑
        if (in_array($type, [0, 3])) {
            $this->_cancel();
        }
        //处理有票据没上分的
        if (in_array($type, [0, 4])) {
            $this->_retry();
        }
        if ($this->user_id == 0) echo date('Y-m-d H:i:s') . PHP_EOL;
        if ($this->user_id == 0) echo '共计用时：' . (time() - $s) . 'S' . PHP_EOL;
    }

    /*-------处理更新过期会员--------**/
    private function _handExp()
    {
        try {
            //这里会有个问题，如果是手动添加的则因为没票据会被自动冲掉
            $userIdArr = UsersProfileModel::where([['vip_is', 1], ['vip_exp_time', '<', date('Y-m-d H:i:s')]])->pluck('user_id')->toArray();
            //去掉给这些人赠送的超级曝光
            UsersModel::whereIn('id', $userIdArr)->update(['super_show' => 0, 'super_show_left' => 0, 'super_show_exp_time' => null]);
            //更新数据库的数据
            UsersProfileModel::whereIn('user_id', $userIdArr)->update(['vip_is' => 0, 'vip_level' => 0, 'vip_handle' => 0]);
            //更新es中的数据
            $this->syncEsVip($userIdArr);
            //还原VIP才能有的特殊配置
            UsersSettingsModel::whereIn('user_id', $userIdArr)->update(['hide_model' => 0, 'hide_browse' => 0, 'hide_distance' => 0, 'hide_rank' => 0, 'hide_guard' => 0, 'hide_online' => 0, 'hide_contact' => 0]);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }

    /*----------处理那些自动续订的用户----对过期五天的用户进行校验-----*/
    private function _subscribe()
    {
        try {
            //多过期5天的用户进行二次校验
            $where = [
                ['vip_exp_time', '>', date('Y-m-d H:i:s', time() - 86400 * 3)],
                ['vip_is', 0],
                ['receipt', '!=', '']
            ];
            $builder = UsersProfileModel::where($where)->whereNotNull('receipt');
            if ($this->user_id > 0) {
                $builder->where('user_id', $this->user_id);
            }
            $exp = $builder->get();
            if (!$exp->isEmpty()) {
//                dd($expSevenDayIds);
                foreach ($exp as $user) {
                    try {
                        //验证票据
                        $applePay = new ApplePay();
                        $res = $applePay->validateApplePay($user->receipt);
                        //Log::channel('receipt')->info($res);
                        CommonModel::storeReceipt($user, $res);
                        if ($this->user_id == 0) sleep(90);
                    } catch (\Exception $e) {
                        MessageModel::gainLog($e, __FILE__, __LINE__);
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage() . PHP_EOL;
        }
    }

    /*----处理有凭证缺上分失败的人-----**/
    private function _retry()
    {
        $retries = OrderModel::where([['status', 0], ['user_id', '>', 0], ['retry', '<', 3]])->whereNotNull('receipt')->get();
        if (!$retries->isEmpty()) {
            foreach ($retries as $retry) {
                try {
                    //如果已经是vip 就跳过
                    $profile = UsersProfileModel::where('user_id', $retry->user_id)->first();
                    if ($profile->vip_is == 1) continue;
                    //存储解析内容
                    $applePay = new ApplePay();
                    $res = $applePay->validateApplePay($retry->receipt);
                    $retry->retry += 1;
                    CommonModel::storeReceipt($retry->receipt, $res);
                    if (isset($res['success']) && $res['success']) {
                        if (!isset($res['data']['latest_receipt_info'])) continue;
                        $last = end($res['data']['latest_receipt_info']);
                        //处理重复报错的问题[由于唯一键导致的更新唯一键冲突] == S
                        $check = OrderModel::where([['transaction_id', $last['transaction_id']], ['original_transaction_id', $last['original_transaction_id']]])->first();
                        if (!$check) {
                            $retry->original_transaction_id = $last['original_transaction_id'];
                            $retry->transaction_id = $last['transaction_id'];
                        }
                        //处理重复报错的问题[由于唯一键导致的更新唯一键冲突] == E
                        $retry->status = 1;
                    }
                    $retry->save();
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
        }
    }


    /*----*对中途取消并进行退款的用户进行处理-------*/
    public function _cancel()
    {
        try {
            //先校验已经是vip 的用户
            $nexp = UsersProfileModel::where([['vip_is', 1], ['vip_exp_time', '>', date('Y-m-d H:i:s')], ['receipt', '!=', '']])->whereNotNull('receipt')->get();
            if (!$nexp->isEmpty()) {
                foreach ($nexp as $vipUser) {
                    //重试失败定单需要过滤掉手动添加的订单 【主要针对首次手动续费了，二次为手动的情况】
                    if (in_array($vipUser->vip_handle, [1, 2])) continue;
                    try {
                        //验证票据
                        $applePay = new ApplePay();
                        $res = $applePay->validateApplePay($vipUser->receipt);
                        //找到票据
                        CommonModel::storeReceipt($vipUser->receipt, $res, false);
                    } catch (\Exception $e) {
                        MessageModel::gainLog($e, __FILE__, __LINE__);
                        continue;
                    }
                }
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            echo $e->getMessage() . PHP_EOL;
        }
    }

    private function syncEsVip(array $userIdArr)
    {
        if ($userIdArr) {
            foreach ($userIdArr as $user_id) {
                EsDataModel::updateEsUser([
                    'id' => $user_id,
                    'vip_is' => 0,
                    'vip_level' => 0,
                    'hide_online' => 0,
                    'hide_distance' => 0,
                    'hide_model' => 0,
                ]);
                //更新缓存中的配置
                UsersSettingsModel::refreshUserSettings($user_id, 'hide_online', 0);
                UsersSettingsModel::refreshUserSettings($user_id, 'hide_distance', 0);
                UsersSettingsModel::refreshUserSettings($user_id, 'hide_model', 0);
            }
        }
    }

}
