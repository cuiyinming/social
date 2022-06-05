<?php

namespace App\Jobs;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Models\Client\ClientProfitModel;
use App\Http\Models\Client\ClientUsersModel;
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

/*---------------------------  代理结算 【费用清算】 ---------------------------------
  **********************************  代理费用结算 ************************************
  ** ------------------------------------------------------------------------------**/

class clientLiquidation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $tab;

    public function __construct($order, $tab)
    {


        $this->order = $order;
        $this->tab = $tab;
    }

    public function handle()
    {
        try {
            $action_user = UsersModel::where([['status', 1], ['id', $this->order->user_id]])->first();
            if ($action_user && (!empty($action_user->client_code) || !empty($action_user->invited))) {
                $first_level = !empty($action_user->client_code);
                $invite = !empty($action_user->client_code) ? $action_user->client_code : $action_user->invited;
                $type = $this->order->type == 0 ? 'vip' : 'recharge';
                $title = $type == 'recharge' ? '内购' : '购买会员';
                $level = $type == 'recharge' ? config('settings.client')['user']['recharge'] : config('settings.client')['user']['vip'];//开始分润
                //针对单个会员的特殊汇率，所以优先级高于通用汇率 ==== S
                $first = ClientUsersModel::where([['status', 1], ['invite_code', $invite]])->first();
                if ($first && isset(config('settings.special_rate')[$first->id])) {
                    $new_level = config('settings.special_rate')[$first->id];
                    $level = $type == 'recharge' ? $new_level['recharge'] : $new_level['vip'];
                }
                $amount = $this->order->amount;
                //针对单个会员的特殊汇率，所以优先级高于通用汇率 ==== E
                $amount = $this->order->platform == 'android' ? $amount : $amount * 0.7; //这里的区分是主要是苹果要扣30% 手续费
                $level_1 = $level['level_1'];
                $level_2 = $level['level_2'];
                $level_3 = $level['level_3'];

                //第一层
                if ($first_level && $first) {
                    //查到第一级 [根据用户的邀请码查询代理并分红]
                    $profit_a = round($amount * $level_1, 2);
                    //分润先进入审核
                    $desc = '邀请的【一级会员】' . $title . '分成' . $profit_a . ' 元';
                    $remark = '邀请的【一级会员】[' . $title . $amount . '] 元，执行比例：' . ($level_1 * 100) . '%，分成' . $profit_a . ' 元';
                    ClientProfitModel::gainLogProfit($first->id, $profit_a, $amount, $type, $desc, $remark);
                } else {
                    //如果不是第一层
                    $parent = UsersModel::where([['status', 1], ['uinvite_code', $invite]])->first();
                    if ($parent && !empty($parent->client_code)) {
                        $level_parent = ClientUsersModel::where([['status', 1], ['invite_code', $parent->client_code]])->first();
                        if ($level_parent) {
                            $profit_b = round($amount * $level_2, 2);
                            //分润先进入审核
                            $desc = '邀请的【二级会员】' . $title . '分成' . $profit_b . ' 元';
                            $remark = '邀请的【二级会员】[' . $title . $amount . '] 元，执行比例：' . ($level_2 * 100) . '%，分成' . $profit_b . ' 元';
                            ClientProfitModel::gainLogProfit($level_parent->id, $profit_b, $amount, $type, $desc, $remark);
                        }
                    }
                    //往三级推
                    if ($parent && !empty($parent->invited)) {
                        //网上推一级查推荐
                        $third = UsersModel::where([['status', 1], ['uinvite_code', $parent->invited]])->first();
                        if ($third && !empty($third->client_code)) {
                            $level_third = ClientUsersModel::where([['status', 1], ['invite_code', $third->client_code]])->first();
                            if ($level_third) {
                                $profit_c = round($amount * $level_3, 2);
                                //分润先进入审核
                                $desc = '邀请的【三级会员】' . $title . '分成' . $profit_c . ' 元';
                                $remark = '邀请的【三级会员】[' . $title . $amount . '] 元，执行比例：' . ($level_3 * 100) . '%，分成' . $profit_c . ' 元';
                                ClientProfitModel::gainLogProfit($level_third->id, $profit_c, $amount, $type, $desc, $remark);
                            }
                        }
                    }
                }

            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
