<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Helpers\S;
use App\Http\Libraries\Crypt\Rsa;
use App\Http\Libraries\Sms\RongIm;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Libraries\Tools\RecoverInfo;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Lib\LibBannersModel;
use App\Http\Models\Logs\LogRecommendModel;
use App\Http\Models\Logs\LogRecoverModel;
use App\Http\Models\Logs\LogSignModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\OrderModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgGovModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Models\Users\UsersSettingsModel;
use Curl\Curl;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RongCloud;

class TestController extends AuthController
{
    public function test(Request $request)
    {
//        $order = OrderModel::where('id', 1119)->first();
//        \App\Jobs\applePay::dispatch($order);
//        $productId = S::getVipPriceById('quzhi201', 'id_num');
//        $productPrice = S::getVipPriceById('quzhi201');
//        dd($productId,$productPrice);

//        $proId = 'quzhi11';
//        $exp_time = null;
//        $time = 0;
//        $itemInfo = [];
//        $productMaps = array_values(config('subscribe.vip_list'));
//        foreach ($productMaps as $productMap) {
//            foreach ($productMap as $key => $item) {
//                if ($item['id'] == $proId) {
//                    $itemInfo = $item;
//                    if (in_array($item['id_num'], [1, 4, 7, 10])) $time = 30 * 86400;
//                    if (in_array($item['id_num'], [2, 5, 8, 11])) $time = time() + 90 * 86400;
//                    if (in_array($item['id_num'], [3, 6, 9, 12])) $time = time() + 365 * 86400;
//                }
//            }
//        }
//        if ($exp_time && $exp_time >= date('Y-m-d H:i:s')) {
//            $last_time = strtotime($exp_time) + $time;
//        } else {
//            $last_time = time() + $time;
//        }
//        $res = [
//            'price' => $itemInfo['price'] ?? 0,
//            'level' => $itemInfo['id_num'] ?? 0,
//            'time' => date('Y-m-d H:i:s', $last_time),
//        ];
//        dd($res);
//        return $res;

//        $builder = ClientUsersModel::select(['id'])->where([['invited', '871314'], ['status', 1]])->orderBy('id', 'desc')->pluck('id')->toArray();
//        dd($builder);
//        $id = $request->input('id');
//        $curl = new Curl();
//        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
//        $curl->setOpt(CURLOPT_TIMECONDITION, 5);
//        $header = [
//            'authority: ppapi.lalajwb.cn:22345',
//            'pragma: no-cache',
//            'cache-control: no-cache',
//            'accept: */*',
//            'access-control-request-headers: token',
//            'origin: https://neko.quwenming.cn',
//            'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36',
//            'sec-fetch-mode: cors',
//            'sec-fetch-site: cross-site',
//            'sec-fetch-dest: empty',
//            'referer: https://neko.quwenming.cn/',
//            'accept-language: zh-CN,zh;q=0.9,en;q=0.8',
//            'token: eyJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE2MTc3MTc0MjYsInNlc3Npb24iOiJ7XCJhcHBpZFwiOlwiMTAxMDFcIixcImNoYW5uZWxcIjpcIjAwOGN6XCIsXCJncm91cElkXCI6MyxcImlkXCI6ODU1NzgsXCJzdWJDaGFubmVsXCI6MTh9IiwiZXhwIjoxNjE3NzIxMDI2fQ.v3BVY7VZ9UPtoYb0O2SqnVgpvOI7y08gXMQhR7hy6eY'
//        ];
//        $base = '';
//        $curl->setOpt(CURLOPT_HTTPHEADER, $header);
////        for ($i = $id; $i < $id + 10; $i++) {
////            echo $i;
////        sleep(1);
//        $url = 'https://ppapi.lalajwb.cn:22345/api/link-code?url=http://baidu.com&name=' . $id;
//        $res = $curl->get($url);
////        dd($res);
//        $base .= '<img alt="' . $id . '" src="data:image/jpeg;base64,' . $res->data . '"/>';
////        }
//        echo $base;
//        dd($res);
//        $res = H::object2array($res);
//        $str = 'lcouAPWiSZNG6yysPZB1Xw==';
//        dd(base64_decode($str), H::deciphering(base64_decode($str), 'abcdefghijklmnop', 'abcdefghijklmnop'));
//        $str = 'helloapple';
//        $res = (new CryptAES(128, "abcdefghijklmnop", "abcdefghijklmnop", 'cbc'))->encrypt($str);
//        dd($res);
//        dd(H::randstr(16, 'LOWER'));
//        dd(Hash::make('123456'));
//        $openssl_data = openssl_encrypt($str, "AES-128-CBC", "abcdefghijklmnop", 0, "0123456789012345");
//        $content = openssl_decrypt($openssl_data, "AES-128-CBC", "abcdefghijklmnop", 0, "0123456789012345");
//        dd($openssl_data, $content);
//
//
//        $str = Rsa::privDecrypt(base64_decode($str), 1);
//        dd($str);
//        $dec = Rsa::privDecrypt($str, 1);
//        dd($str, $dec);
//        公钥加密
//        $user = UsersModel::where('id', 102)->increment('sweet_coin');
//        dd($user);
//        $user_id = $request->input('user_id', 186692);
//        $pushMsg = [
//            "alert" => [
//                'title' => '测试标题',
//                'body' => '测试内容',
//            ],
//            'badge' => '+1',
//            'extras' => [
//                'jump' => UsersMsgModel::schemeUrl('', 24, '动态详情', 141093, '立即前往'),
//            ],
//
//            'content-available' => false,
//            'sound' => 'default',
//        ];
//        $res = (AuroraPush::getInstance())->aliasPush($user_id, $pushMsg);
//        dd($res, $pushMsg);
//
//
//        \App\Jobs\userRecommend::dispatch($user_id);
//        $res = RongCloud::userBlock(141432, 43200); //默认禁言1个月
//        RongCloud::userUnBlock(141432);
//        dd($res);
//        $userId = $request->input('userId', 0);
//        $roomId = $request->input('roomId', 0);
//
//        $info = [
//            'userId' => $userId,
//            'roomId' => $roomId,
//        ];
//        (new RongIm())->kickUser($info);
//
//        $channel = $request->input('channel', 0);
//        $title = $request->input('title', '金币奖励');
//        $cont = $request->input('title', '完善资料奖励5心友币');
//        $reward = $request->input('reward', 5);
//        UsersRewardModel::sendImMsg($user_id, $title, $cont, $reward, $channel, $this->sex);
//        return $this->jsonExit(200, 'OK');
//        UsersSettingsModel::refreshUserSettings(141093, 'guanzhu', 1);
//        $settings = UsersSettingsModel::getUserSettings(141093);
//        dd($settings);
//        $amount = S::getTaskReward('zhenrenrenzheng');
//        dd($amount);
//        $rong1 = RongCloud::getToken(141440, '🍃洒脱的君子🍐', 'http://static.hfriend.cn/ava/2-71.jpg!sm');
//        dd($rong1);
//        $productId = S::getDiamondPriceById('xinyou8', 'diamond');
//        $amount = S::getDiamondPriceById('xinyou8');
//        dd($productId, $amount);
//        $rong2 = RongCloud::getToken(110, '用户推荐', 'http://static.hfriend.cn/vips/server.png');
//        dd($rong2);
//        $notice = [
//            'content' => '测试',
//            'extra' => [
//                'discover' => 1,
//            ],
//        ];
//        $res = RongCloud::messageSystemPublish(109, [$user_id], 'RC:TxtMsg', json_encode($notice));
//        dd($res);
//
//        try {
//            $url = (new ImageBlur())->createBlurNick("利索的鱿小鱼1234");
//            dd($url);
//        } catch (\Exception $e) {
//            dd($e->getMessage());
//        }
//        $city = (new BaiduCloud())->getCityByPoint('31.16723211938952,121.3945536510435');
//        dd($city);
//        $rong2 = RongCloud::getToken(104, '资料完善通知', 'http://static.zzlia.com/vips/active.png');
//        dd($rong2);
//        dd(S::getPro(2));
//        try {
//            $pass = true;
//            $res = (new AliyunCloud())->GreenScanImage('http://static.zzlia.com/album/2020/1205/5fcac925e8cbb.jpeg');
//            dd($res);
//            if (isset($res['Data']['Results'][0]['SubResults'])) {
//                $subRes = $res['Data']['Results'][0]['SubResults'];
//                if (count($subRes) > 0) {
//                    foreach ($subRes as $sub) {
//                        if ($sub['Label'] !== 'normal' && $sub['Scene'] != 'porn') {
//                            $pass = false;
//                        }
//                        if ($sub['Scene'] == 'porn' && (($sub['Label'] == 'sexy' && $sub['Rate'] > 70) || $sub['Label'] == 'porn')) {
//                            $pass = false;
//                        }
//                    }
//                }
//            }
//            dd($pass, $res);
//        } catch (\Exception $e) {
//            MessageModel::gainLog($e, __FILE__, __LINE__);
//        }
//        dd($res);
//        文本审核
//        $res = (new AliyunCloud())->GreenScanAudio('https://static.hfriend.cn/sound/2020/1227/5fe7629125ed1.m4a');
//        dd($res);
//
//        $rong1 = RongCloud::getToken(102, '官方客服', 'http://static.hfriend.cn/avatar/2021/0312/604b2033be8fe.png');
//        dd($rong1);
//        $rong2 = RongCloud::getToken(101, '系统通知', 'http://static.zzlia.com/vips/active.png');
//
//
//
//        $msg = [
//            'message' => '请在聊天中注意人身财产安全',
//            'extra' => ''
//        ];
//        RongCloud::messagePrivatePublish($this->uid, $toUserIds, 'RC:InfoNtf', json_encode($msg));
//
//        $msg = [
//            "title" => "标题",
//            "content" => "消息描述",
//            "imageUri" => "http://pic136.huitu.com/res/20191220/2365630_20191220191533387080_1.jpg",
//            "url" => "http://www.rongcloud.cn",
//            "extra" => ""
//        ];
//        RongCloud::messagePrivatePublish($this->uid, $toUserIds, 'RC:ImgTextMsg', json_encode($msg));
//
//        $msg = [
//            "title" => "标题",
//            "content" => "消息描述",
//            "imageUri" => "http://pic136.huitu.com/res/20191220/2365630_20191220191533387080_1.jpg",
//            "url" => "http://www.rongcloud.cn",
//            "extra" => ""
//        ];
//        RongCloud::messageSystemPublish($this->uid, $toUserIds, 'RC:ImgTextMsg', json_encode($msg));
    }


    public function imPushTest(Request $request)
    {
        $sys_uid = $request->input('sys_uid', 100);
        $uid = $request->input('to_uid', 141093);
        $toUserIds = [$uid];
        if ($sys_uid == 100) {
            $title = 'VIP订单处理完成';
            $cont = '您的VIP购买订单已经处理完成，请注意VIP权益变化。';
            $sysMsg = ['content' => $title, 'title' => $cont, 'extra' => ""];
            $res = RongCloud::messageSystemPublish(100, $toUserIds, 'RC:TxtMsg', json_encode($sysMsg));
            dd($res);
        }
        if ($sys_uid == 101) {
            $title = 'VIP订单处理完成';
            $cont = '您的VIP购买订单已经处理完成，请注意VIP权益变化。';
            $sysMsg = ['content' => $title, 'title' => $cont, 'extra' => ""];
            $res = RongCloud::messageSystemPublish(101, $toUserIds, 'RC:TxtMsg', json_encode($sysMsg));
            dd($res);
        }

        if ($sys_uid == 103) {
            $notice = ['content' => '消息推送', "title" => "消息推送", 'extra' => ['discover' => 1]];
            //100 代表点赞+评论的红点推送  签到 一键咋呼 引导完善资料
            $res = RongCloud::messageSystemPublish(103, $toUserIds, 'RC:TxtMsg', json_encode($notice));
            dd($res);
        }

        if ($sys_uid == 104) {
            $cont = '完善资料赚心友币，您有23个友币待领取';
            $title = $reward = '23个友币';
            $notice = [
                'title' => $title,
                'content' => $cont,
                'extra' => [
                    'reward' => $reward,
                    'title_str' => $title,
                    'cont_str' => $cont,
                ],
            ];
            $res = RongCloud::messageSystemPublish(104, $toUserIds, 'RC:TxtMsg', json_encode($notice));
            dd($res);
        }

        if ($sys_uid == 105) {
            $sex = 1;
            $randRes = UsersModel::getRandUsers($uid, $sex);
            $notice = [
                'title' => '批量打招呼',
                'content' => '批量打招呼',
                'extra' => json_encode($randRes),
            ];
            $res = RongCloud::messageSystemPublish(105, $toUserIds, 'RC:TxtMsg', json_encode($notice));
            dd($res);
        }

        if ($sys_uid == 106) {
            $signMap = config('subscribe.sign');
            $sign = LogSignModel::where('user_id', $uid)->first();
            $title = '签到领友币';
            if ($sign) {
                $spacer = strtotime(date('Y-m-d') . ' 00:00:00') - strtotime($sign->last_date . ' 00:00:00');
                if ($spacer <= 86400) {
                    $title = '已连续签到 ' . $sign->serial . ' 天';
                    foreach ($signMap as &$item) {
                        if ($sign->last_date == date('Y-m-d') || $item['day'] <= $sign->serial) $item['tips'] = '';
                        if ($item['day'] == $sign->serial + 1 && $sign->last_date != date('Y-m-d')) $item['tips'] = '今日可签';
                        if ($item['day'] <= $sign->serial) $item['signed'] = true;
                        if ($item['day'] == $sign->serial + 1 && $sign->last_date != date('Y-m-d')) $item['day_str'] = '今天';
                    }
                }
            }
            foreach ($signMap as &$item) {
                unset($item['reward_int']);
                unset($item['day']);
            }
            $res['title'] = $title;
            $res['sign_remind'] = UsersSettingsModel::getSingleUserSettings($uid, 'sign_remind');
            $res['sign'] = $signMap;
            $notice = [
                'title' => '签到推送',
                'content' => '签到推送',
                'extra' => json_encode($res),
            ];
            $res = RongCloud::messageSystemPublish(106, $toUserIds, 'RC:TxtMsg', json_encode($notice));
            dd($res);
        }

        //107
        if ($sys_uid == 107) {
            $cont = '首充奖励VIP 2 天';
            $reward = 10;
            $desc = '首充奖励';
            $notice = [
                'title' => $desc,
                'content' => $cont,
                'extra' => [
                    'reward' => $reward,
                    'title_str' => '任务奖励友币' . $reward . '个',
                    'cont_str' => $cont,
                ],
            ];
            $res = RongCloud::messageSystemPublish(107, $toUserIds, 'RC:TxtMsg', json_encode($notice));
            dd($res);
        }

        //109
        if ($sys_uid == 109) {
            $notice = ['content' => '联系方式推送', 'extra' => []];
            $res = RongCloud::messageSystemPublish(109, $toUserIds, 'RC:TxtMsg', json_encode($notice));
            dd($res);
        }

        if ($sys_uid == 110) {
            $column = [
                'users.id', 'users.nick', 'users.avatar', 'users.sex', 'users.birthday', 'users.constellation', 'users.last_location',
                'users_profile.stature', 'users_profile.vip_is', 'users_profile.profession', 'users.online', 'users_profile.vip_level',
                'users_profile.identity_is', 'users_profile.real_is', 'users.live_time_latest'
            ];
            unset($item);
            $item = UsersModel::select($column)->leftjoin('users_profile', 'users.id', '=', 'users_profile.user_id')
                ->where('users.status', 1)->where('users.avatar', 'like', '%' . '/avatar/' . '%')->orderBy(DB::Raw('RAND()'))->first();
            $rand = rand(60, 99);
            $sex_str = $item->sex == 1 ? '女' : '男';
            $age = H::getAgeByBirthday($item->birthday);
            $base_str = $item->last_location . ' | ' . $sex_str . '•' . $age;
            if (!empty($item->stature)) $base_str .= ' | ' . $item->stature;
            if (!empty($item->constellation)) $base_str .= ' | ' . $item->constellation;
            if (!empty($item->profession)) $base_str .= ' | ' . $item->profession;
            $res = [
                'user_id' => $item->id,
                'avatar' => $item->avatar,
                'say_hi' => 1,
                'match' => $rand,
                'nick' => $item->nick,
                'vip_is' => $item->vip_is,
                'vip_level' => $item->vip_level,
                'online' => $item->online,
                'identity_is' => $item->identity_is,
                'real_is' => $item->real_is,
                'time_str' => H::exchangeDate($item->live_time_latest),
                'base_str' => $base_str,
            ];
            $notice = [
                'title' => '优质用户推荐',
                'content' => '用户推荐',
                'extra' => json_encode($res),
            ];
            $res = RongCloud::messageSystemPublish(110, $toUserIds, 'RC:TxtMsg', json_encode($notice));
        }
    }


    public function JpushTest(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $tar_user_id = $request->input('tar_user_id', 0);
        $msg = $request->input('msg', 0);

        $res = JpushModel::JpushCheck($user_id, '可爱的萝卜', 0, $msg, $tar_user_id);
        dd($res);
    }

}
