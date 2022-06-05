<?php

namespace App\Http\Controllers\Discover;

use App\Components\ESearch\ESearch;
use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Models\CommonModel;
use App\Http\Models\Discover\DiscoverCmtModel;
use App\Http\Models\Discover\DiscoverIgnoreModel;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Discover\DiscoverTopicModel;
use App\Http\Models\Discover\DiscoverTopicUserModel;
use App\Http\Models\Discover\DiscoverZanCmtModel;
use App\Http\Models\Discover\DiscoverZanModel;
use App\Http\Models\JobsModel;
use App\Http\Models\JpushModel;
use App\Http\Models\Lib\LibCountriesModel;
use App\Http\Models\Lib\LibRegionModel;
use App\Http\Controllers\AuthController;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Logs\LogShareModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Payment\SubscribeModel;
use App\Http\Models\Resource\ResourceModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersBlackListModel;
use App\Http\Models\Users\UsersFollowModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersRewardModel;
use App\Http\Models\Users\UsersSettingsModel;
use App\Http\Libraries\Tools\{AliyunCloud, ApplePay, BaiduCloud};
use App\Http\Models\EsDataModel;
use App\Http\Models\Resource\UploadModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DiscoverController extends AuthController
{
    //发布
    public function discoverPublish(Request $request)
    {
        $tags = $request->input('tags');
        $sound = $request->input('sound');
        $location = $request->input('location');
        $private = $request->input('private', 0);
        $comment_on = $request->input('comment_on', 1);
        $show_on = $request->input('show_on', 0);
        $album = $request->input('album');
        $cont = $request->input('cont', '');
        //唯一签名合成
        $sign = md5($this->uid . $sound . $album . $cont);
        $album = json_decode($album, 1);
        $sound = json_decode($sound, 1);
        $tags = json_decode($tags, 1);
        $location = json_decode($location, 1);
        //权限判断
        $discover_publish = UsersSettingsModel::getUserInfo($this->uid);
        if ($discover_publish->discover_publish == 0) {
            return $this->jsonExit(201, '动态发布功能暂未开放');
        }
        $profile = UsersProfileModel::getUserInfo($this->uid);
        //动态判断 && 敏感词过滤
        if (empty($cont)) {
            return $this->jsonExit(201, '动态内容不能为空');
        }
        if (mb_strlen($cont) < 2) {
            return $this->jsonExit(202, '动态内容不能少于2个字');
        }
        if (stripos($cont, '有偿') !== false || stripos($cont, '滴') !== false || stripos($cont, '嘀') !== false || stripos($cont, 'dd') !== false || stripos($cont, '茶') !== false) {
            return $this->jsonExit(202, '内容含有不被允许的关键词，请修改');
        }
        $res = (new AliyunCloud())->GreenScanText($cont);
        if ($res != 'pass') {
            return $this->jsonExit(203, '您的动态包含敏感词');
        }
        //相册
        if (empty($album)) $album = null;
        if (!empty($album) && !is_array($album)) {
            return $this->jsonExit(203, '动态配图格式错误');
        }
        if (!empty($album) && count($album) > 9) {
            return $this->jsonExit(204, '动态配图不能超过9张');
        }
        //语音 [最大只能传一个音频]
        if (empty($sound)) $sound = null;
        if (!empty($sound) && (count($sound) > 2 || !is_array($sound))) {
            return $this->jsonExit(205, '动态语音数据错误');
        }
        //位置坐标
        if (empty($location)) $location = null;
        if (!empty($location) && (count($location) != 2 || !is_array($location))) {
            return $this->jsonExit(205, '位置信息错误');
        }
        $lat = $lng = null;
        if (!empty($location) && count($location) == 2 && is_array($location)) {
            $coorArr = explode(',', $location['coordinate']);
            $lat = trim($coorArr[0]);
            $lng = trim($coorArr[1]);
        }
        //标签处理
        if (empty($tags)) $tags = null;
        if (!empty($tags) && !is_array($tags)) {
            return $this->jsonExit(203, '话题标签错误');
        }
        if (!empty($tags) && count($tags) > 3) {
            return $this->jsonExit(204, '话题标签不能超过3个');
        }
        if (!empty($tags)) {
            //处理标签
            $tagTid = [];
            $creatJob = false;
            foreach ($tags as &$tag) {
                $tagTid[] = $tag['stid'];
                if (!empty($tag['stid'])) continue;
                $disTopic = DiscoverTopicModel::createByRow($tag['tag'], $this->uid);
                $tag['stid'] = $disTopic->stid;
                //新增同步es
                EsDataModel::syncEs('tags', 'tags', $disTopic->id, $disTopic->id);
                $creatJob = true;
            }
            if ($creatJob) {
                JobsModel::InsertNewJob(4);
            }
            DiscoverTopicModel::whereIn('stid', $tagTid)->increment('total');
        }
        //数据处理开始-------规范相关数据
        //相册
        if (!empty($album)) {
            $albumData = [];
            //扩展信息一起存入防止重复获取图片情况，审核时候单独取出替换 {相册}
            $albumArrs = UploadModel::select(['id', 'path', 'location', 'usefor', 'is_illegal'])->where('user_id', $this->uid)->whereIn('id', $album)->get();
            if (!$albumArrs->isEmpty()) {
                foreach ($albumArrs as $key => $albumArr) {
                    $albumData[] = [
                        'id' => $albumArr->id,
                        'img_url' => H::path($albumArr->path),
                        'is_illegal' => $albumArr->is_illegal,
                    ];
                }
                $insertData['album'] = $albumData;
            } else {
                $insertData['album'] = null;
            }
        } else {
            $insertData['album'] = $album;
        }
        //录音
        $insertData['sound'] = $sound;
        //位置
        $insertData['location'] = $location;
        $insertData['lat'] = $lat;
        $insertData['lng'] = $lng;
        //标签
        $insertData['tags'] = $tags;
        //内容
        $insertData['cont'] = $cont;
        //配置
        $insertData['private'] = $private;
        $insertData['cmt_on'] = $comment_on;
        $insertData['show_on'] = $show_on;
        $insertData['user_id'] = $this->uid;
        $insertData['sex'] = $this->sex;
        $insertData['post_at'] = CORE_TIME;
        $insertData['channel'] = CHANNEL;
        $insertData['sign'] = $sign;
        $insertData['num_view'] = 2200;  //发布动态默认出来就是2200次
        //验证重复
        $discover = DiscoverModel::where('sign', $sign)->first();
        if ($discover) {
            return $this->jsonExit(202, '请勿重复发送动态');
        }
        //入库
        try {
            DB::beginTransaction();
            //这里进行扣费操作 【只有男性非vip才扣费】
            $version = intval(str_replace('.', '', VER));
            if (config('settings.publish_limit_on') && $version >= 300) {
                if ($this->sex == 1) {
                    if ($profile->vip_is == 0 && $profile->real_is == 0) {
                        return $this->jsonExit(201, '真人认证后才能发布动态哟~');
                    }
                }
                //if ($this->sex == 2) {
                //    if ($profile->vip_is == 0) {
                //        return $this->jsonExit(201, '动态认证仅对VIP会员开放~');
                //    }
                //}
                if ($this->sex == 2 && $profile->vip_is == 0) {
                    $price = config('settings.publish_price');
                    $self_user = UsersModel::getUserInfo($this->uid);
                    $before = $self_user->sweet_coin;
                    if ($before < $price) {
                        return $this->jsonExit(209, '友币不足请充值后操作');
                    }
                    $amount = $self_user->sweet_coin - $price;
                    $desc = "付费发布动态";
                    $remark = "付费发布动态，消耗友币{$price}个";
                    $type_tag = 'buy_discover';
                    LogBalanceModel::gainLogBalance($this->uid, $before, $price, $amount, $type_tag, $desc, $remark);
                    $self_user->sweet_coin = $amount;
                    $self_user->save();
                }
            }

            $insert = DiscoverModel::create($insertData);
            //发布的同时同步到es
//            try {
//                EsDataModel::syncEs('discover', 'discover', $insert->id, $insert->id);
//            } catch (\Exception $e) {
//                MessageModel::gainLog($e, __FILE__, __LINE__);
//            }
            //进入机器审核异步进程[照片审核延时15S进行]
            $setting = config('settings.scan_type');
            if ($setting == 'async') {
                \App\Jobs\greenScan::dispatch($insert, 'discover')->delay(now()->addSeconds(15))->onQueue('register');
            }
            //记录发布日志
            $changeData = [
                'sound' => $sound,
                'cont' => $cont,
                'album' => $insertData['album'],
            ];
            LogChangeModel::gainLog($this->uid, 'discover_publish', json_encode($changeData, JSON_UNESCAPED_UNICODE), $insert->id);
            DB::commit();
            //下发每日动态奖励
            UsersRewardModel::userDailyRewardSet($this->uid, 'meiridongtai');
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }
        return $this->jsonExit(200, 'OK');
    }

    public function discoverUnlock(Request $request)
    {
        //男性必须开通会员才可以发布，或者要求98金币发布一条， 没有就弹出充值金币，或者开通会员
        //女性必须要真人认证才可以发布动态
        $profile = UsersProfileModel::getUserInfo($this->uid);
        $self = UsersModel::getUserInfo($this->uid);
        $price = config('settings.publish_price');
        $base = [
            'publish' => false,
            'publish_price' => $price,
            'balance' => $self->sweet_coin,
            'enough' => $self->sweet_coin >= $price,
            'tips_str' => '会员免费，非会员需支付' . $price . '心币',
            'btn' => [
                'vip_str' => '开通VIP免费发布',
                'change_str' => '立即充值'
            ]
        ];
        //男
        if ($this->sex == 2) {
            if ($profile->vip_is == 1) {
                $base['publish'] = true;
                $base['tips_str'] = '';
            } else {
                if ($self->sweet_coin >= $price) {
                    $base['publish'] = true;
                    $base['btn']['change_str'] = '立即付费发布';
                } else {
                    $base['btn']['change_str'] = '充值友币';
                }
            }
        }
        //女
        if ($this->sex == 1) {
            if (($profile->vip_is == 0 && $profile->real_is == 1) || $profile->vip_is == 1) {
                $base['publish'] = true;
                $base['tips_str'] = '';
            } else {
                $base['publish_price'] = 0;
                $base['tips_str'] = '真人认证或VIP会员免费发布';
                $base['btn']['change_str'] = '立即认证';
            }
        }
        //如果设置为不收费
        if (!config('settings.publish_limit_on')) {
            $base['publish'] = true;
        }
        return $this->jsonExit(200, 'OK', $base);
    }


    //删除
    public function discoverDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $discover = DiscoverModel::find($id);
        if (!$discover) {
            return $this->jsonExit(201, '动态不存在');
        }
        if ($discover->user_id != $this->uid) {
            return $this->jsonExit(202, '操作越权');
        }
        try {
            //删除OSS资源
            ResourceModel::deleteResource($discover, 'discover');
            $discover->delete();
            //删除相关的点赞记录
            DiscoverZanCmtModel::where('discover_id', $id)->delete();
            //删除相关评论信息
            DiscoverZanModel::where('discover_id', $id)->delete();
            //删除评论相关的点赞记录
            DiscoverCmtModel::where('discover_id', $id)->delete();
            //删除es的文档 [错误单独捕获]
//            try {
//                (new ESearch('discover:discover'))->deleteSingle([['id' => $id]]);
//            } catch (\Exception $e) {
//                MessageModel::gainLog($e, __FILE__, __LINE__);
//            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }
        return $this->jsonExit(200, 'OK');
    }

    //赞
    public function discoverZan(Request $request)
    {
        //发布评论
        $discover_id = $request->input('id', 0);
        $status = $request->input('status', 1);
        $discover = DiscoverModel::find($discover_id);
        if (!$discover) {
            return $this->jsonExit(203, '动态不存在');
        }
        if ($discover->status !== 1) {
            return $this->jsonExit(202, '违规动态，禁止评论点赞');
        }
        $discover_zan = UsersSettingsModel::getSingleUserSettings($this->uid, 'discover_zan');
        if ($discover_zan == 0) {
            return $this->jsonExit(203, '动态点赞暂未开放');
        }
        //入库
        try {
            DB::beginTransaction();
            $dis = DiscoverZanModel::where([['user_id', $this->uid], ['discover_id', $discover_id]])->first();
            if ($dis && $dis->status == $status) {
                return $this->jsonExit(200, 'OK');
            }
            $self = $discover->user_id == $this->uid;
            DiscoverZanModel::updateOrCreate([
                'user_id' => $this->uid,
                'discover_id' => $discover_id,
                'discover_user_id' => $discover->user_id
            ], [
                'status' => $status,
                'user_id' => $this->uid,
                'discover_id' => $discover_id,
                'channel' => CHANNEL,
                'discover_user_id' => $discover->user_id
            ]);
            if ($status == 1) {
                $discover->increment('num_zan');
            }
            if ($discover->num_zan > 0 && $status == 0) {
                $discover->decrement('num_zan');
            }
            //未读消息更新并添加消息记录
            if (!$self) {
                UsersMsgModel::gainUserMsg($this->uid, $discover, 'discover_zan', $status);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }
        return $this->jsonExit(200, 'OK');
    }

    //发布评论
    public function commentPublish(Request $request)
    {
        //发布评论
        $discover_id = $request->input('id', 0);
        $comment = $request->input('comment');
        if (mb_strlen($comment) < 2) {
            return $this->jsonExit(204, '评论内容不能少于2个字');
        }
        if (mb_strlen($comment) > 100) {
            return $this->jsonExit(205, '评论内容不能多余100个字');
        }
        if (stripos($comment, 'yue') !== false) {
            return $this->jsonExit(206, '评论含有违规内容，请修改');
        }
        if (stripos($comment, '约') !== false) {
            return $this->jsonExit(206, '评论含有违规内容，请修改');
        }
        //动态评论
        $sms = SettingsModel::getSigConf('sms');
        $discover_cmt = isset($sms['discover_cmt']) && $sms['discover_cmt'] == 0;
        if ($discover_cmt) {
            return $this->jsonExit(207, '动态评论功能暂未开放');
        }
        $discover = DiscoverModel::find($discover_id);
        if (!$discover) {
            return $this->jsonExit(201, '动态不存在');
        }
        $self = $discover->user_id == $this->uid;
        if ($discover->cmt_on == 0 && !$self) {
            return $this->jsonExit(202, '动态发布者关闭了该动态评论功能');
        }
        if ($discover->status !== 1) {
            return $this->jsonExit(202, '违规动态，禁止评论');
        }
        $discover_cmt = UsersSettingsModel::getSingleUserSettings($this->uid, 'discover_cmt');
        if ($discover_cmt == 0) {
            return $this->jsonExit(203, '动态评论暂未开放');
        }
        //先对用户自己进行限制，用户自己只能评论一条
        if ($discover->user_id == $this->uid) {
            $cmt_counter = DiscoverCmtModel::where([['status', 1], ['user_id', $this->uid], ['discover_id', $discover_id]])->count();
            if ($cmt_counter > 1) {
                return $this->jsonExit(203, '最多只能评论一条自己的动态');
            }
        }
        //敏感词检测
        $res = (new AliyunCloud($this->uid))->GreenScanText($comment);
        if ($res != 'pass') {
            return $this->jsonExit(204, '动态包含违禁词语，请检查');
        }
        $commentData = [
            'user_id' => $this->uid,
            'comment' => $comment,
            'discover_id' => $discover_id,

        ];
        $commentData['sign'] = $sign = md5(json_encode($commentData));
        $exist = DiscoverCmtModel::where([['sign', $sign], ['user_id', $this->uid]])->first();
        if ($exist) {
            return $this->jsonExit(204, '请勿重复发布评论');
        }
        $cmtCount = DiscoverCmtModel::where([['user_id', $this->uid], ['discover_id', $discover_id]])->count();
        if ($cmtCount > 2) {
            return $this->jsonExit(206, '评论过多，请稍后再试');
        }
        //入库
        try {
            DB::beginTransaction();
            $commentData = $commentData + [
                    'comment_at' => CORE_TIME,
                    'discover_user_id' => $discover->user_id,
                    'channel' => CHANNEL,
                ];
            $cmt = DiscoverCmtModel::create($commentData);
            //评论数增加1
            $discover->increment('num_cmt');
            //添加系统的评论通知动态消息
            if (!$self) {
                UsersMsgModel::gainUserMsg($this->uid, $discover, 'discover_cmt', 1, $comment);
            }
            DB::commit();
            //奖励 评论三条动态 [处理奖励问题]
            $cmt_counter = DiscoverCmtModel::where([['user_id', $this->uid], ['status', 1]])->count();
            if ($cmt_counter >= 3) {
                UsersRewardModel::userRewardSet($this->uid, 'pinglun');
            }

        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }
        $userInfo = EsDataModel::mgetEsUserByIds(['ids' => [$this->uid]]);
        $cmt->is_zan_cmt = 0;
        $cmt->cmt_at_str = H::exchangeDateStr($cmt->comment_at);
        unset($cmt->status);
        unset($cmt->discover_user_id);
        unset($cmt->discover_id);
        $cmt->user_info = $userInfo[$cmt->user_id] ?? [];
        //记录评论内容
        LogChangeModel::gainLog($this->uid, 'discover_comment', $comment, $cmt->id);
        return $this->jsonExit(200, 'OK', $cmt);

    }

    //分享
    public function discoverShare(Request $request)
    {
        $discover_id = $request->input('id', 0);
        $type = $request->input('type', 'discover');
        $channel = $request->input('channel', 'qq');
        if ($type == 'discover') {
            $discover = DiscoverModel::find($discover_id);
            if (!$discover) {
                return $this->jsonExit(203, '动态不存在');
            }
            $discover->increment('num_share');
        }
        LogShareModel::create([
            'user_id' => $this->uid,
            'type' => $type,
            'type_id' => $discover_id,
            'channel' => $channel,
            'device' => DEVICE,
            'device_type' => CHANNEL,
        ]);
        return $this->jsonExit(200, 'OK');
    }

    //评论点赞
    public function commentZan(Request $request)
    {
        $cmt_id = $request->input('id', 0);
        $status = $request->input('status', 1);
        $cmt = DiscoverCmtModel::find($cmt_id);
        if (!$cmt) {
            return $this->jsonExit(203, '评论不存在');
        }
        if ($cmt->status !== 1) {
            return $this->jsonExit(202, '评论异常，操作失败');
        }
        $discover_cmt_zan = UsersSettingsModel::getSingleUserSettings($this->uid, 'discover_cmt_zan');
        if ($discover_cmt_zan == 0) {
            return $this->jsonExit(203, '评论点赞暂未开放');
        }
        try {
            DB::beginTransaction();
            $dis = DiscoverZanCmtModel::where([['user_id', $this->uid], ['cmt_id', $cmt_id]])->first();
            if ($dis && $dis->status == $status) {
                return $this->jsonExit(200, 'OK');
            }
            DiscoverZanCmtModel::updateOrCreate([
                'user_id' => $this->uid,
                'cmt_id' => $cmt_id,
            ], [
                'status' => $status,
                'user_id' => $this->uid,
                'discover_id' => $cmt->discover_id,
                'discover_user_id' => $cmt->discover_user_id,
                'cmt_id' => $cmt_id,
                'channel' => CHANNEL,
            ]);
            $status == 1 ? $cmt->increment('num_zan') : $cmt->decrement('num_zan');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }
        return $this->jsonExit(200, 'OK');

    }

    //评论删除
    public function commentDelete(Request $request)
    {
        $cmt_id = $request->input('id', 0);
        $cmt = DiscoverCmtModel::find($cmt_id);
        if (!$cmt) {
            return $this->jsonExit(201, '评论不存在');
        }
        if ($cmt->status == 0 || $cmt->status == 2) {
            return $this->jsonExit(202, '评论已经删除');
        }
        if ($cmt->user_id != $this->uid) {
            return $this->jsonExit(203, '操作越权');
        }
        try {
            DB::beginTransaction();
            $discover = DiscoverModel::find($cmt->discover_id);
            if (!$discover) {
                return $this->jsonExit(201, '动态不存在');
            }
            $cmt->status = 2;
            $cmt->save();
            $discover->decrement('num_cmt');
            //物理删除评论的点赞记录
            DiscoverZanCmtModel::where([['user_id', $this->uid], ['cmt_id', $cmt_id]])->update(['status' => 0]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, '服务异常');
        }
        return $this->jsonExit(200, 'OK');
    }

    //单个用户的动态
    public function userDiscover(Request $request)
    {
        $res = [];
        $user_id = $request->input('user_id', 0);
        $sort = $request->input('sort', 'new');  //new recommend
        $page = $request->input('page', 1);
        $size = 20;
        $user = UsersModel::find($user_id);
        if (!$user) {
            return $this->jsonExit(201, '用户不存在');
        }
        if ($user->status != 1) {
            return $this->jsonExit(202, '用户状态异常');
        }
        $self = $user_id == $this->uid;
        $hide_model = UsersSettingsModel::getSingleUserSettings($user_id, 'hide_model');
        if (!$self && $hide_model == 1) {
            return $this->jsonExit(203, '用户开启了隐身模式，资料及动态不能被查看');
        }
        //黑名单
        $res['self'] = $self;
        $blackArr = UsersBlackListModel::getBlackIdArr($this->uid);
        if (!$self && in_array($user_id, $blackArr)) {
            return $this->jsonExit(206, '黑名单用户不能被查看');
        }
        //从es 中取出指定文档j基础信息[这里采用了批量获取的方式]
        $userInfo = EsDataModel::getEsBaseInfo(['id' => $user_id]);
        $res['user_info'] = $userInfo;
        $sex = $user->sex == 1 ? 2 : 1;
        $from_es = false;
        if ($from_es) {
            //如果从es中获取数据
            $sortArr = [];
            if ($sort == 'new') {
                $sortArr['created_at'] = 1; //0不排序 1倒序 2正序
            }
            if ($sort == 'recommend') {
                $sortArr['num_recommend'] = 1; //0不排序 1倒序 2正序
            }
            $sortEs = $this->getSort($sortArr);
            $discover = EsDataModel::getEsDiscoverByUserId($user_id, $this->uid, $sex, $sortEs, $page, $size);
            dd($discover);
        } else {
            $builder = DiscoverModel::where([['user_id', $user_id], ['status', 1]])->orderBy('id', 'desc');
            //对隐私性的动态进行过滤
            if (!$self) {
                //过滤仅自己可见部分
                $builder->where('private', '!=', 1);
                //过滤不对同性展示的部分
                $builder->where(function ($query) use ($sex) {
                    $query->where('show_on', 1)->orWhere([['show_on', 0], ['sex', '!=', $sex]]);
                });
                //过滤好友及关注我的,我查看的是别人，获取别人的关注和好友
                $builder->where(function ($private) use ($user_id) {
                    //对隐私性进行过滤 & 过滤仅自己可见部分-----S----过滤好友及关注我的,我查看的是别人，获取我的关注和好友  我能看到的只有关注人公开的
                    $friendsFollow = UsersFollowModel::getFriendAndFollow($user_id);
                    if (in_array($this->uid, $friendsFollow)) {
                        $private->where('private', '!=', 2);
                    }
                });
            }
            $count = $builder->count();
            $discover = $builder->skip(($page - 1) * $size)->take($size)->get();
            if (!$discover->isEmpty()) {
                $discover = DiscoverModel::processDiscover($this->uid, $discover, $userInfo, 'single');
            }
            $res['discover_list']['items'] = !$discover->isEmpty() ? $discover : [];
            $res['discover_list']['count'] = !$discover->isEmpty() ? $count : 0;
        }
        return $this->jsonExit(200, 'OK', $res);
    }

    //单个话题的动态
    public function topicDiscover(Request $request)
    {
        //先对tags进行初步筛选
        $topic = $request->input('topic', '');
        if (empty($topic)) {
            return $this->jsonExit(201, '话题标签不能为空');
        }
        $page = $request->input('page', 1);
        $order = $request->input('order', 'new');
        $size = 20;
        //追加话题是否关注过
        $is_follow = DiscoverTopicUserModel::getUserTopicIdArr($this->uid, $topic);
        $res['topic_follow_is'] = count($is_follow) > 0 ? 1 : 0;
        //追加话题信息
        $topic = DiscoverTopicModel::select(['title', 'subtitle', 'image', 'total', 'recommend', 'followed_num'])->where('stid', $topic)->first();
        if ($topic) {
            $topic->total_str = H::getNumStr($topic->total) . '条动态';
            $topic->followed_num_str = "共 " . H::getNumStr($topic->followed_num) . "人关注此话题";
        }
        $res['discover_topic'] = $topic ? $topic : new \stdClass();
        //追加话题列表 & 对隐私性的动态进行过滤
        $builder = DiscoverModel::getDiscovers($this->uid, $this->sex)->whereNotNull('tags');
        $builder->where(DB::Raw("tags->'$[0].stid' = '{$topic}'"))->orWhere(DB::Raw("tags->'$[1].stid' = '{$topic}'"))->orWhere(DB::Raw("tags->'$[2].stid' = '{$topic}'"));
        $order != 'new' ? $builder->orderBy('num_view', 'desc') : $builder->orderBy('post_at', 'desc');
        //隐私过滤---------E---------
        $count = $builder->count();
        $discover = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$discover->isEmpty()) {
            //动态信息
            foreach ($discover as $disc) {
                $userIdArr[] = $disc->user_id;
            }
            //对用户整体数据进行渲染  || 获取es的相关用户数据
            $userInfo = EsDataModel::mgetEsUserByIds(['ids' => $userIdArr]);
            $discover = DiscoverModel::processDiscover($this->uid, $discover, $userInfo, 'list');
        }
        $res['discover_list']['items'] = !$discover->isEmpty() ? $discover : [];
        $res['discover_list']['count'] = !$discover->isEmpty() ? $count : 0;
        return $this->jsonExit(200, 'OK', $res);
    }

    //单个动态详情信息
    public function discoverDetail(Request $request)
    {
        $id = $request->input('discover_id', 0);
        $page = $request->input('page', 1);
        $order = $request->input('order', 'new');
        $size = 20;
        $discover = DiscoverModel::find($id);
        if (!$discover) {
            return $this->jsonExit(205, '动态不存在');
        }
        $exclude = UsersModel::getExcludeIdArr($this->uid);
        if (in_array($discover->user_id, $exclude)) {
            return $this->jsonExit(209, '动态信息异常');
        }
        $self = $discover->user_id == $this->uid;
        if (!$self && $discover->status != 1) {
            return $this->jsonExit(201, '动态状态异常');
        }
        if (!$self && $discover->private == 1) {
            return $this->jsonExit(202, '该动态暂不公开');
        }
        if (!$self && $discover->show_on == 0 && $discover->sex == $this->sex) {
            return $this->jsonExit(203, '该动态暂不对同性公开');
        }
        //好友查看 过滤仅对陌生人可见
        if (!$self && $discover->private == 2) {
            $friendsFollow = UsersFollowModel::getFriendAndFollow($discover->user_id);
            if (in_array($this->uid, $friendsFollow)) {
                return $this->jsonExit(204, '该动态仅对陌生人公开');
            }
        }
        DiscoverModel::where('id', $id)->increment('num_view');
        //发布动态的用户信息 & 从es 中取出指定文档j基础信息
        $userInfo = EsDataModel::getEsBaseInfo(['id' => $discover->user_id]);
        $followArr = UsersFollowModel::getFollowIdArr($this->uid);
        $userInfo['is_like'] = in_array($discover->user_id, $followArr) ? 1 : 0;
        $res['user_info'] = $userInfo;
        //判断当前动态
        $zan = DiscoverZanModel::where([['user_id', $this->uid], ['status', 1], ['discover_id', $id]])->first();

        DiscoverModel::tagAndAlbum($discover);

        $res['discover_info'] = [
            'user_id' => $discover->user_id,
            'location' => $discover->location,
            'cont' => $discover->cont,
            'album' => $discover->album,
            'sound' => $discover->sound,
            'tags' => $discover->tags,
            'is_zan' => $zan ? 1 : 0,
            'num_cmt' => $discover->num_cmt,
            'num_zan' => $discover->num_zan,
            'num_view' => $discover->num_view,
            'num_view_str' => H::getNumStr($discover->num_view) . ' 次浏览',
            'num_share' => $discover->num_share,
            'num_say_hi' => $discover->num_say_hi,
            'post_at' => $discover->post_at,
            'post_at_str' => H::exchangeDate($discover->post_at),
            'say_hi' => HR::existUniqueNum($this->uid, $discover->user_id, 'say-hi-num') != 1 && $this->uid != $discover->user_id,
        ];
        //分享
        $res['share'] = DiscoverModel::getDiscoverShareInfo($id, $userInfo, $discover);
        //评论列表
        $builder = DiscoverCmtModel::where([['discover_id', $id], ['status', 1]]);
        if ($order == 'new') {
            $builder->orderBy('id', 'desc');
        } else {
            $builder->orderBy('num_zan', 'desc');
        }
        $count = $builder->count();
        $cmt = $builder->skip(($page - 1) * $size)->take($size)->get();
        $cmtArr = $cmtIds = [];
        if (!$cmt->isEmpty()) {
            foreach ($cmt as $item) {
                $cmtArr[] = $item->user_id;
                $cmtIds[] = $item->id;
            }
            //获取评论存在的id
            $cmtZans = DiscoverZanCmtModel::getZanDiscover($this->uid, $cmtIds);
            //对用户整体数据进行渲染  || 获取es的相关用户数据
            $userInfo = EsDataModel::mgetEsUserByIds(['ids' => $cmtArr]);
            foreach ($cmt as &$ite) {
                $ite->is_zan_cmt = in_array($ite->id, $cmtZans) ? 1 : 0;
                $ite->cmt_at_str = H::exchangeDateStr($ite->comment_at);
                unset($ite->status);
                unset($ite->discover_user_id);
                unset($ite->discover_id);
                $ite->user_info = $userInfo[$ite->user_id] ?? [];
            }
        }
        //评论信息本人
        $res['cmt_list']['items'] = !$cmt->isEmpty() ? $cmt : [];
        $res['cmt_list']['count'] = !$cmt->isEmpty() ? $count : 0;
        return $this->jsonExit(200, 'OK', $res);
    }

    //关注&附近&推荐-----推荐 【目前推荐的都是最新的】
    public function discoverRecommend(Request $request)
    {
        $page = $request->input('page', 1);
        $order = $request->input('order', 'new');
        $size = 20;
        try {
            $res = [];
            //获取推荐话题
            $topic = DiscoverTopicModel::getLimitTopicData(3, 'total', $this->uid);
            $res['recommend_topic'] = $topic;
            //话题列表
            $builder = DiscoverModel::getDiscovers($this->uid, $this->sex);
            //过滤不在推荐的人
            $ignore = DiscoverIgnoreModel::getIgnoreIdArr($this->uid);
            $builder->whereNotIn('user_id', $ignore);
            if ($order == 'view') {
                $builder->orderBy('num_view', 'desc');
            }
            if ($order == 'new') {
                $builder->orderBy('post_at', 'desc');
            } else {
                $builder->orderBy(DB::Raw('RAND()'));
            }
            $count = $builder->count();
            $discover = $builder->skip(($page - 1) * $size)->take($size)->get();
            //渲染动态
            if (!$discover->isEmpty()) {
                //动态信息
                $userIdArr = $idArr = [];
                foreach ($discover as $disc) {
                    $userIdArr[] = $disc->user_id;
                }
                //对用户整体数据进行渲染  || 获取es的相关用户数据
                $userInfo = EsDataModel::mgetEsUserByIds(['ids' => $userIdArr]);
                $discover = DiscoverModel::processDiscover($this->uid, $discover, $userInfo, 'list');
            }
            $res['discover_list']['items'] = !$discover->isEmpty() ? $discover : [];
            $res['discover_list']['count'] = !$discover->isEmpty() ? $count : 0;
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    //附近 只看异性 当前在线 我离最近
    public function discoverNearby(Request $request)
    {
        $page = $request->input('page', 1);
        $order = $request->input('order', 'view');
        $sex = $request->input('sex', 0);
        $online = $request->input('online', 0);
        $nearby = $request->input('nearby', 1);
        $size = 20;
        try {
            $res = [];
            //话题列表
            $builder = DiscoverModel::getDiscovers($this->uid, $this->sex);
            //过滤不在推荐的人
            $ignore = DiscoverIgnoreModel::getIgnoreIdArr($this->uid);
            $builder->whereNotIn('user_id', $ignore);
            // 按照距离排序 【默认按距离】
            $coordinates = explode(',', COORDINATES);
            $lat = $coordinates[0];
            $lng = $coordinates[1];
            $builder->whereNotNull('location')->addSelect(DB::raw("ROUND(
                    6378.138 * 2 * ASIN(
                        SQRT(
                            POW(
                                SIN(
                                    (
                                        {$lat} * PI() / 180-lat * PI() / 180
                                    ) / 2
                                ),
                                2
                                ) + COS( {$lat} * PI() / 180 ) * COS( lat * PI() / 180 ) * POW(
                                SIN(
                                    (
                                        {$lng} * PI() / 180-lng * PI() / 180
                                    ) / 2
                                ),
                                2
                            )
                        )
                    ) * 1000
                ) AS distance"))
                ->orderBy('distance', 'asc');

            if ($sex == 1) { //只看异性
                $filterSex = $this->sex == 1 ? 2 : 1;
                $builder->where('sex', $filterSex);
            }
            if ($online == 1) {
                //筛选在线
                $builder->where('online', 1);
            }
            if ($order == 'view') {
                $builder->orderBy('num_view', 'desc');
            } else {
                $builder->orderBy('post_at', 'desc');
            }
            //计算下经纬度具体的距离
            $count = $builder->count();
            $discover = $builder->skip(($page - 1) * $size)->take($size)->get();
            //渲染动态
            if (!$discover->isEmpty()) {
                //动态信息
                $userIdArr = [];
                foreach ($discover as $disc) {
                    $userIdArr[] = $disc->user_id;
                }
                //对用户整体数据进行渲染  || 获取es的相关用户数据
                $userInfo = EsDataModel::mgetEsUserByIds(['ids' => $userIdArr]);
                $discover = DiscoverModel::processDiscover($this->uid, $discover, $userInfo, 'list');
            }
            $res['discover_list']['items'] = !$discover->isEmpty() ? $discover : [];
            $res['discover_list']['count'] = !$discover->isEmpty() ? $count : 0;
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    public function discoverFollow(Request $request)
    {
        $page = $request->input('page', 1);
        $order = $request->input('order', 'view');
        $size = 20;
        try {
            $res = [];
            //获取推荐话题
            $topic = DiscoverTopicModel::getLimitTopicData(3, 'total', $this->uid, true);
            $res['follow_topic'] = $topic;
            //关注人员的状态信息 & 对用户整体数据进行渲染  || 获取es的相关用户数据
            $followIds = UsersFollowModel::getFollowIdArr($this->uid);
            $pre = [];
            if (!empty($followIds)) {
                $followUsers = EsDataModel::mgetEsUserByIds(['ids' => $followIds]);
                foreach ($followUsers as $k => $followUser) {
                    $pre[] = [
                        'user_id' => $followUser['user_id'],
                        'avatar' => $followUser['avatar'],
                        'online' => $followUser['online'],
                        'nick' => $followUser['nick'],
                    ];
                    if (count($pre) > 20) continue;
                }
            }
            $res['follow_users'] = $pre;
            //话题列表
            $builder = DiscoverModel::getDiscovers($this->uid, $this->sex);
            $builder->where(function ($query) {
                //进一步过滤---我关注的人
                $followIdArr = UsersFollowModel::getFollowIdArr($this->uid);
                $query->whereIn('user_id', $followIdArr)->orWhere(function ($item) {
                    //第二步过滤 我关注的话题
                    $topicArr = DiscoverTopicUserModel::getUserTopicIdArr($this->uid);
                    $item->whereIn("tags->'$[0].stid'", $topicArr)->orWhereIn("tags->'$[1].stid'", $topicArr)->orWhereIn("tags->'$[2].stid'", $topicArr);
                });
            });
            if ($order == 'view') {
                $builder->orderBy('num_view', 'desc');
            } else {
                $builder->orderBy('post_at', 'desc');
            }
            $count = $builder->count();
            $discover = $builder->skip(($page - 1) * $size)->take($size)->get();
            //渲染动态
            if (!$discover->isEmpty()) {
                //动态信息
                $userIdArr = [];
                foreach ($discover as $disc) {
                    $userIdArr[] = $disc->user_id;
                }
                //对用户整体数据进行渲染  || 获取es的相关用户数据
                $userInfo = EsDataModel::mgetEsUserByIds(['ids' => $userIdArr]);
                $discover = DiscoverModel::processDiscover($this->uid, $discover, $userInfo, 'list');
            }
            $res['discover_list']['items'] = !$discover->isEmpty() ? $discover : [];
            $res['discover_list']['count'] = !$discover->isEmpty() ? $count : 0;
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }

    /*-------动态忽略--------*/
    public function discoverIgnore(Request $request)
    {
        $discover_id = $request->input('discover_id', 0);
        $ignore_user_id = $request->input('user_id', 0);
        $discover = DiscoverModel::find($discover_id);
        if (!$discover) {
            return $this->jsonExit(201, '动态不存在');
        }
        $user = UsersModel::find($ignore_user_id);
        if (!$user) {
            return $this->jsonExit(202, '用户不存在');
        }
        $status = $request->input('status', 1);
        DiscoverIgnoreModel::updateOrCreate([
            'user_id' => $this->uid,
            'ignore_user_id' => $ignore_user_id,
        ], [
            'user_id' => $this->uid,
            'ignore_user_id' => $ignore_user_id,
            'discover_id' => $discover_id,
            'status' => $status
        ]);
        return $this->jsonExit(200, 'OK');
    }

    /*---------语音动态---------*/
    public function discoverVoice(Request $request)
    {
        $page = $request->input('page', 1);
        $size = 20;
        $builder = DiscoverModel::getDiscovers($this->uid, $this->sex);
        //过滤不在推荐的人
        $ignore = DiscoverIgnoreModel::getIgnoreIdArr($this->uid);
        $builder->whereNotIn('user_id', $ignore);
        $builder->whereNotNull('sound');
        //$builder->orderBy(DB::Raw("RAND()"));
        $builder->select(['id', 'user_id', 'sound'])->orderBy('id', 'desc');
        $count = $builder->count();
        $discover = $builder->skip(($page - 1) * $size)->take($size)->get();
        //渲染动态
        $res = [];
        if (!$discover->isEmpty()) {
            $userIdArr = $idArr = [];
            foreach ($discover as $disc) {
                $userIdArr[] = $disc->user_id;
                $idArr[] = $disc->id;
            }
            //获取动态点赞
            $zan = DiscoverZanModel::getZanDiscover($this->uid, $idArr);
            $userInfo = EsDataModel::mgetEsUserByIds(['ids' => $userIdArr]);
            foreach ($discover as &$dis) {
                $dis->is_zan = in_array($dis->id, $zan) ? 1 : 0;
                $dis->user_info = isset($userInfo[$dis->user_id]) ? $userInfo[$dis->user_id] : [];
            }
            DiscoverModel::whereIn('id', $idArr)->increment('num_view');
        }
        $res['items'] = !$discover->isEmpty() ? $discover : [];
        $res['count'] = !$discover->isEmpty() ? $count : 0;
        return $this->jsonExit(200, 'OK', $res);
    }

    public function discoverMsgGet(Request $request)
    {
        $page = $request->input('page', 1);
        $size = 20;
        $data = UsersMsgModel::getUserMsgPageData($this->uid, 'notice', $page, $size);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function discoverMsgClear(Request $request)
    {
        UsersMsgModel::clearData($this->uid);
        return $this->jsonExit(200, 'OK');
    }


    //电脑及小程序端单独显示，所以进行单独处理
    public function webDiscoverGet()
    {
        $page = 1;
        $size = 30;
        try {
            $res = [];
            //话题列表
            $builder = DiscoverModel::select(['id', 'cont', 'user_id', 'post_at', 'album', 'num_view', 'num_zan'])->where([['status', 1], ['private', '!=', 1]])->whereNotNull('album')->orderBy('post_at', 'desc');
            $count = $builder->count();
            $discover = $builder->skip(($page - 1) * $size)->take($size)->get();
            //渲染动态
            if (!$discover->isEmpty()) {
                //动态信息
                $userIdArr = $idArr = [];
                foreach ($discover as $disc) {
                    $userIdArr[] = $disc->user_id;
                }
                //对用户整体数据进行渲染  || 获取es的相关用户数据
                $userInfo = EsDataModel::mgetEsUserByIds(['ids' => $userIdArr]);
                foreach ($discover as &$dis) {

                    $dis->date_str = H::exchangeDateStr($dis->post_at);
                    $dis->num_view = H::getNumStr($dis->num_view);
                    DiscoverModel::tagAndAlbum($dis);
                    $dis->cover = count($dis->album) > 0 ? $dis->album[0]['img_url'] : 'http://static.hfriend.cn/web/nophoto.png';
                    unset($dis->album);
                    $user_info = [];
                    if (isset($userInfo[$dis->user_id])) {
                        $user_info = [
                            'user_id' => $userInfo[$dis->user_id]['user_id'],
                            'avatar' => $userInfo[$dis->user_id]['avatar'],
                            'nick' => $userInfo[$dis->user_id]['nick'],
                        ];
                    }
                    $dis->user_info = $user_info;
                    $idArr[] = $dis->id;
                }
                DiscoverModel::whereIn('id', $idArr)->increment('num_view');
            }
            $res['items'] = !$discover->isEmpty() ? $discover : [];
            $res['count'] = !$discover->isEmpty() ? $count : 0;
            return $this->jsonExit(200, 'OK', $res);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
    }


    public function webDiscoverDetail($id, Request $request)
    {
        $discover = DiscoverModel::select(['id', 'cont', 'user_id', 'post_at', 'album', 'num_cmt', 'num_view', 'num_zan'])->whereNotNull('album')->where([['status', 1], ['private', '!=', 1]])->find($id);
        if (!$discover) {
            return $this->jsonExit(201, '记录不存在');
        }
        DiscoverModel::where('id', $id)->increment('num_view');
        $limit = DiscoverModel::where([['status', 1], ['private', '!=', 1]])->whereNotNull('album')->orderBy('id', 'desc')->skip(35)->first();
        if ($limit && $limit->id > $id) {
            return $this->jsonExit(202, '记录不存在');
        }
        //发布动态的用户信息 & 从es 中取出指定文档j基础信息
        $userInfo = EsDataModel::getEsBaseInfo(['id' => $discover->user_id]);
        //追加动态数
        $count = DiscoverModel::where([['user_id', $discover->user_id], ['status', 1]])->count();
        $userInfo['count'] = $count;
        $res['user_info'] = $userInfo;
        //查询关注信息
        $follow = UsersFollowModel::followInfoCounter($discover->user_id);
        $res['follow'] = $follow;
        DiscoverModel::tagAndAlbum($discover);
        $res['discover_info'] = [
            'user_id' => $discover->user_id,
            'cont' => $discover->cont,
            'album' => $discover->album,
            'tags' => $discover->tags,
            'num_cmt' => $discover->num_cmt,
            'num_zan' => $discover->num_zan,
            'num_view' => $discover->num_view,
            'num_view_str' => H::getNumStr($discover->num_view) . ' 次浏览',
            'post_at' => $discover->post_at,
            'post_at_str' => H::exchangeDate($discover->post_at),
        ];
        //追加随机的热门话题
        $topic = DiscoverTopicModel::select(['title', 'total'])->where('status', 1)->limit(5)->orderBy(DB::Raw('RAND()'))->get();
        $res['topic'] = $topic;
        //追加关联动态
        $relate = DiscoverModel::select(['id', 'cont', 'post_at', 'album', 'num_view', 'num_zan'])->whereNotNull('album')->where([['user_id', $discover->user_id], ['private', '!=', 1], ['status', 1], ['id', '!=', $id]])->orderBy('id', 'desc')->limit(5)->get();
        if (!$relate->isEmpty()) {
            foreach ($relate as &$item) {
                DiscoverModel::tagAndAlbum($item);
                $item->cover = count($item->album) > 0 ? $item->album[0]['img_url'] : 'http://static.hfriend.cn/web/nophoto.png';
                unset($item->album);
            }
        }
        $res['relate'] = $relate;
        return $this->jsonExit(200, 'OK', $res);
    }


    public function webUserInfo($id, Request $request)
    {
        DiscoverModel::where('id', $id)->increment('num_view');
        $limit = DiscoverModel::where([['status', 1], ['private', '!=', 1]])->whereNotNull('album')->orderBy('id', 'desc')->skip(35)->first();
        if ($limit && $limit->id > $id) {
            return $this->jsonExit(202, '记录不存在');
        }
        //发布动态的用户信息 & 从es 中取出指定文档j基础信息
        $userInfo = EsDataModel::getEsBaseInfo(['id' => $id]);
        //追加动态数
        $count = DiscoverModel::where([['user_id', $id], ['status', 1]])->count();
        $userInfo['count'] = $count;
        $profile = UsersProfileModel::where('user_id', $id)->first();
        //签名
        $userInfo['bio'] = $profile->bio;
        $userInfo['album'] = $profile->album ? $profile->album : [];
        $res['user_info'] = $userInfo;
        //查询关注信息
        $follow = UsersFollowModel::followInfoCounter($id);
        $res['follow'] = $follow;
        //追加随机的热门话题
        $topic = DiscoverTopicModel::select(['title', 'total'])->where('status', 1)->limit(5)->orderBy(DB::Raw('RAND()'))->get();
        $res['topic'] = $topic;
        //追加关联动态
        $relate = DiscoverModel::select(['id', 'cont', 'post_at', 'album', 'num_view', 'num_zan'])->whereNotNull('album')->where([['user_id', $id], ['private', '!=', 1], ['status', 1]])->orderBy('id', 'desc')->limit(10)->get();
        if (!$relate->isEmpty()) {
            $idArr = [];
            foreach ($relate as &$item) {
                DiscoverModel::tagAndAlbum($item);
                $item->cover = count($item->album) > 0 ? $item->album[0]['img_url'] : 'http://static.hfriend.cn/web/nophoto.png';
                unset($item->album);
                $idArr[] = $item->id;
            }
            DiscoverModel::whereIn('id', $idArr)->increment('num_view');
        }
        $res['relate'] = $relate;
        return $this->jsonExit(200, 'OK', $res);
    }
}
