<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;

use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogGiftReceiveModel;
use App\Http\Models\Logs\LogGiftSendModel;
use App\Http\Models\Logs\LogSweetModel;
use App\Http\Models\Logs\LogSweetUniqueModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RongCloud;

class GiftController extends AuthController
{
    //消息聊天
    public function giftList(Request $request)
    {
        $res = LibGiftModel::getGift('list');
        return $this->jsonExit(200, 'OK', $res);
    }

    //更多礼物
    public function giftMore(Request $request)
    {
        $res = LibGiftModel::getGift('more');
        return $this->jsonExit(200, 'OK', $res);
    }

    //赠送礼物
    public function giftSend(Request $request)
    {
        $gift_id = $request->input('gift_id');
        $user_id = $request->input('user_id', 0);
        $num = $request->input('num', 1);
        if ($num < 1) {
            return $this->jsonExit(201, '赠送礼物数目错误');
        }
        $user = UsersModel::find($user_id);
        $self_user = UsersModel::find($this->uid);
        $sweet_coin = $self_user->sweet_coin;
        if (!$user || $user->status != 1) {
            return $this->jsonExit(201, '用户异常');
        }
        $profile = $user->profile;
        $lib_gift = LibGiftModel::where([['gift_id', $gift_id], ['status', 1]])->first();
        if (!$lib_gift) {
            return $this->jsonExit(203, '礼物已经下线或不存在');
        }
        $total_price = $lib_gift->price * $num;
        if ($sweet_coin <= 0 || ($sweet_coin - $total_price) < 0) {
            return $this->jsonExit(201, '友币不足，请充值');
        }
        //-------开始执行送礼物操作
        try {
            DB::beginTransaction();
            /*--step1 赠礼物---*/
            LogGiftReceiveModel::gainLog($user_id, $lib_gift, $num);
            LogGiftSendModel::gainLog($this->uid, $user_id, $lib_gift, $num, '');

            //step 2 扣除纸条或友币 并添加记录 添加友币表动记录
            $desc = "向用户{$user_id}，赠送礼物{$lib_gift->name} {$num}个";
            $remark = "用户{$this->uid}向用户{$user_id}，赠送礼物{$lib_gift->name} {$num}个";
            $after = $sweet_coin - $total_price;
            LogBalanceModel::gainLogBalance($this->uid, $sweet_coin, $total_price, $after, 'send_gift', $desc, $remark);
            $self_user->sweet_coin = $after;
            $self_user->save();
            //step 3 更新魅力值和富豪值
            $self_profile = $self_user->profile;
            $profile->charm_num += $total_price; //收-魅力值
            $self_profile->send_num += $total_price;  //发送-富豪值
            $self_profile->save();
            $profile->save();
            //step 4 更新彼此之间的关系热度 【带方向的亲密度，有两条记录】
            $sweet = LogSweetModel::where([['user_id', $this->uid], ['user_id_receive', $user_id]])->first();
            if ($sweet) {
                $sweet->num += $num;
                $sweet->sweet += $lib_gift->friendly * $num;
                $sweet->save();
            } else {
                LogSweetModel::create([
                    'user_id' => $this->uid,
                    'user_id_receive' => $user_id,
                    'num' => $num,
                    'sweet' => $lib_gift->friendly * $num
                ]);
            }
            //step 5 更新彼此之间的关系热度唯一记录
            $sweet_unique = LogSweetUniqueModel::where([['user_both', $this->uid], ['both_user', $user_id]])->orWhere([['both_user', $this->uid], ['user_both', $user_id]])->first();
            if ($sweet_unique) {
                $sweet_unique->num += $num;
                $sweet_unique->sweet += $lib_gift->friendly * $num;
                $sweet_unique->save();
            } else {
                LogSweetUniqueModel::create([
                    'user_both' => $this->uid,
                    'both_user' => $user_id,
                    'num' => $num,
                    'sweet' => $lib_gift->friendly * $num
                ]);
            }
            //step 6 发放礼物的奖励分成 【这里不管用户的友币是怎么来的】
            $settings = config('settings.benefit_share');
            // 0 都不结算 1只结算女生  2只结算男生 3全部结算
            $authed = $profile->real_is == 1 && $profile->identity_is == 1;
            $rate = $authed ? $settings['gift_rate'] : $settings['gift_rate_unverified'];
            $get_price = floor($total_price * $rate);
            if (($user->sex == 1 && in_array($settings['gift_sex'], [1, 3])) || ($user->sex == 2 && in_array($settings['gift_sex'], [2, 3]))) {  //女 男
                $desc = "收到 {$this->uid} 赠送的礼物{$lib_gift->name} {$num}个";
                $remark = "收到 {$this->uid} 赠送的礼物{$lib_gift->name} {$num}个，";
                if (!$authed) {
                    $remark .= "【未完成认证】";
                }
                $remark .= " 奖励{$get_price}心钻";
                $before = $user->jifen;
                $user->jifen += $get_price;
                $user->save();
                LogBalanceModel::gainLogBalance($user_id, $before, $get_price, $user->jifen, 'gift_receive', $desc, $remark, 0, 'log_jifen');
            }
            //这里进行礼物的邀请分佣
//            if (config('settings.invite_on') && config('settings.gift_benefit')) {
//                $benefit_rate = config('settings.rate_benefit'); //分成比例
//                $father = $user->invited;
//                if (!empty($father)) {
//                    $fatherUser = UsersModel::where([['status', 1], ['uinvite_code', $father]])->first();
//                    if ($fatherUser) {
//                        //分成
//                        $before = $fatherUser->jifen;
//                        $change = floor($get_price * $benefit_rate);
//                        $fatherUser->jifen += $change;
//                        $fatherUser->save();
//                        $desc = '邀请的好友（' . $user->id . '）收到礼物分成' . $change . '心钻';
//                        $remark = '邀请的好友（' . $user->id . '）收到礼物分成' . $change . '心钻';
//                        LogBalanceModel::gainLogBalance($fatherUser->id, $before, $change, $fatherUser->jifen, 'invite_gift_benefit', $desc, $remark, 0, 'log_jifen');
//                    }
//                }
//            }
            DB::commit();
            //step 2 发送融云礼物消息
            //$path = storage_path('app/public/') . $lib_gift->path;
            //$web_path = config('app.url') . '/storage/' . $lib_gift->path;
            //try {
            //    $giftMsg = [
            //        'content' => '',
            //        'extra' => [
            //            'gift_num' => 1,
            //            'git_name' => $lib_gift->name,
            //            'git_img' => $web_path,
            //            'git_img_base64' => base64_encode($path),
            //        ],
            //    ];
            //    $toUserIds = [$user_id];
            //    RongCloud::messagePrivatePublish($this->uid, $toUserIds, 'RC:TxtMsg', json_encode($giftMsg));
            //} catch (\Exception $e) {
            //    MessageModel::gainLog($e, __FILE__, __LINE__);
            //}
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(200, 'OK', $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    public function userGiftReceive(Request $request)
    {
        $user_id = $request->input('user_id');
        $user = UsersModel::find($user_id);
        if (!$user) {
            return $this->jsonExit(201, '用户不存在');
        }
        if ($user->status != 1) {
            return $this->jsonExit(202, '用户状态异常');
        }
        //获取全部送礼物的人
        $res = $users = [];
        $logs = LogGiftSendModel::select(['user_id', 'num', 'price', 'gift_id', 'gift_name'])->where([['user_id_receive', $user_id], ['status', 1]])->orderBy('price', 'desc')->get();
        if (!$logs->isEmpty()) {
            foreach ($logs as $log) {
                $res[$log->user_id]['user_id'] = $log->user_id;
                $res[$log->user_id]['gifts'][] = [
                    'gift_id' => $log->gift_id,
                    'gift_name' => $log->gift_name,
                    'gift_path' => H::path(str_replace('gift', 'gift/', $log->gift_id) . '.png'),
                    'num' => $log->num,
                ];
                //累计贡献的贡献值
                if (!isset($res[$log->user_id]['contribution'])) {
                    $res[$log->user_id]['contribution'] = $log->price;
                } else {
                    $res[$log->user_id]['contribution'] += $log->price;
                }
                $users[] = $log->user_id;
            }
            //获取es 中人员信息
            $userInfo = EsDataModel::mgetEsUserByIds(['ids' => array_unique($users)]);
            foreach ($res as $user_id => $re) {
                $res[$user_id]['user_info'] = isset($userInfo[$user_id]) ? $userInfo[$user_id] : [];
            }
        }
        return $this->jsonExit(200, 'OK', array_values($res));
    }

}
