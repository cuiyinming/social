<?php
//过审使用接口==正能量
Route::post('get-positive-energy', 'LibController@positiveEnergy'); //正能量列表
//前端接口
Route::group(['middleware' => ['signAuth']], function () {
    Route::post('get-sms', 'CommonController@getSmsCode'); //获取验证码
    Route::post('user-exist-check', 'CommonController@mobileExistCheck'); //验证用户存不存在
    Route::post('sms-check', 'CommonController@smsCheck');  //验证码正确性判断
    Route::post('invite-check', 'CommonController@checkInviteCode');  //邀请码正确性判断

    Route::group(['prefix' => 'lib'], function () {
        Route::any('countries', 'LibController@getCountries');
        Route::post('chat-advice', 'LibController@chatAdvice');
        Route::post('cities', 'LibController@cities');
        Route::group(['middleware' => ['jwtAuth']], function () {
            Route::post('profession', 'LibController@profession');
            Route::post('options', 'LibController@options');
            Route::post('sound-bio', 'LibController@sounds');
            Route::post('text-bio', 'LibController@textBio');
            Route::post('chat-advice-add', 'LibController@chatAdviceAdd');
            Route::post('chat-advice-delete', 'LibController@chatAdviceDelete');
            //广告
            Route::post('banner', 'LibController@bannerList');
            //消息通知
            Route::post('sys-notice', 'LibController@sysNotice');
            Route::post('gov-notice', 'LibController@govNotice');
        });
    });

    //用户 & 注册登录相关
    Route::group(['prefix' => 'user'], function () {
        Route::post('register', 'Auth\RegisterController@register');
        Route::post('fast-register', 'Auth\RegisterController@fastRegister');
        Route::post('forget-password', 'Auth\ForgotPasswordController@forget');
        Route::any('refresh-token', 'Auth\AuthenticateController@refreshToken');
        Route::post('login', 'Auth\LoginController@authenticate');
        Route::post('suggest', 'Auth\RegisterController@suggest');

        //文件上传
        Route::post('avatar/{dir?}', 'Users\UsersAlbumController@uploadImg');

        //首页数据获取
        Route::post('nearby', 'EsController@nearbyRecommend');
        Route::post('profile-view-limit', 'EsController@profileInfoViewLimit');

        Route::group(['middleware' => ['jwtAuth', 'jwtSignalAuth']], function () {
            Route::post('nearby-focus', 'EsController@nearbyFocus');
        });
        Route::group(['namespace' => 'Users', 'middleware' => ['jwtAuth', 'jwtSignalAuth']], function () { //验证单点登录
            Route::post('login-out', 'UsersProfileController@loginOut');
            //之所以把接口放在外面是因为会出现找回密码的时候开启单点登陆提示其他设备登陆的问题，拿到外面则不会有这个提示了
            Route::post('password-set', 'UsersSettingController@passwordSet'); //登陆后设置密码
            Route::post('base-info-set', 'UsersSettingController@baseInfoSet'); //基础信息完善
            Route::post('base-info-get', 'UsersSettingController@baseInfoGet'); //基础信息获取

            Route::post('simple-info-get', 'UsersSettingController@simpleInfoGet'); //简配版基础信息获取
            Route::post('im-info-list', 'UsersSettingController@imInfoList'); //简配版基础IM信息获取
            Route::post('im-info-detail', 'UsersSettingController@imInfoDetail'); //简配版基础IM信息获取
            Route::post('im-chat', 'UsersSettingController@imChat'); //用户聊天上报
            Route::post('contact-exchange', 'UsersSettingController@contactExchange'); //联系方式交换
            Route::post('sweet-info', 'UsersSettingController@sweetInfo'); //聊天亲密度信息
            Route::post('super-show-get', 'UsersSettingController@superShowGet'); //超级曝光
            Route::post('super-show-set', 'UsersSettingController@superShowSet'); //超级曝光
            Route::post('rank-list', 'UsersSettingController@rankList'); //各种排行榜
            Route::post('honey', 'UsersSettingController@honey'); //甜蜜
            Route::post('fast-match-get', 'UsersSettingController@fastMatchGet'); //同城速配配置获取
            Route::post('fast-match-set', 'UsersSettingController@fastMatchSet'); //同城速配提交
            Route::post('blind-date-park', 'UsersSettingController@blindDatePark'); //相亲广场
            Route::post('voice-match-get', 'UsersSettingController@voiceMatchGet'); //语音速配
            //上传文件
            Route::post('upload/{dir?}', 'UsersAlbumController@uploadImg');
            //获取他人详情页面
            Route::post('profile-info', 'UsersProfileController@profileInfo');
            //设置昵称,聊天背景设置
            Route::post('background-set', 'UsersSettingController@backgroundSet');
            Route::post('nick-set', 'UsersSettingController@nickSet');
            //相册相关 & 视频
            Route::post('album-get', 'UsersAlbumController@albumGet');
            Route::post('album-video-get', 'UsersAlbumController@albumVideoGet');
            Route::post('album-edit', 'UsersAlbumController@albumEdit');
            Route::post('album-video-edit', 'UsersAlbumController@albumVideoEdit');
            Route::post('user-album-delete', 'UsersAlbumController@userAlbumDelete'); //删
            Route::post('album-video-delete', 'UsersAlbumController@userVideoDelete'); //删

            Route::post('user-album-complete', 'UsersAlbumController@userAlbumComplete');  //增
            Route::post('album-fire-edit', 'UsersAlbumController@albumFireEdit'); //设置隐私
            Route::post('album-view', 'UsersAlbumController@albumView'); //查看
            Route::post('album-private-buy', 'UsersAlbumController@albumPrivateBuy'); //相册购买

            //关注 & 拉黑 & 语音点赞
            Route::post('sound-zan', 'UsersSettingController@soundZan'); //关注
            Route::post('follow', 'UsersSettingController@storeFollow'); //关注
            Route::post('follow-list', 'UsersSettingController@meFollowList'); //关注列表
            Route::post('followed-list', 'UsersSettingController@followMeList'); //被关注列表
            Route::post('friend-list', 'UsersSettingController@friendList'); //好友列表
            Route::post('browse-me', 'UsersSettingController@browseMe'); //浏览我的列表
            Route::post('me-browse', 'UsersSettingController@meBrowse'); //我的浏览列表
            Route::post('block', 'UsersSettingController@storeBlock'); //拉黑
            Route::post('block-list', 'UsersSettingController@usersBlockList'); //拉黑列表

            //搭讪
            Route::post('say-hi', 'UsersSettingController@sayHi');
            //批量打招呼
            Route::post('batch-user-get', 'UsersSettingController@batchGet');
            Route::post('batch-say-hi', 'UsersSettingController@batchSayHi');

            //设置  [隐私 + vip收费  + 收费设置]
            Route::post('private-set/{col}', 'UsersSettingController@setPrivate'); //隐私设置
            Route::post('private-get', 'UsersSettingController@getPrivate'); //隐私设置获取

            Route::post('vip-settings-set/{col}', 'UsersSettingController@setVipSettings'); //VIP设置
            Route::post('vip-settings-get', 'UsersSettingController@getVipSettings'); //VIP设置获取

            Route::post('price-settings-set/{col}', 'UsersSettingController@setPrice'); //价格设置
            Route::post('price-settings-get', 'UsersSettingController@getPrice'); //价格设置获取


            //公共消息获取
            Route::post('sys-info-count', 'UsersSettingController@sysInfoCount');
            Route::post('sys-info-delete', 'UsersSettingController@sysInfoDelete');
        });
    });

    //发现相关的接口
    Route::group(['prefix' => 'discover', 'namespace' => 'Discover', 'middleware' => ['jwtAuth']], function () {
        Route::post('topic-list', 'TopicController@topicList'); //话题列表
        Route::post('topic-follow', 'TopicController@topicFollow'); //话题关注
        Route::post('user-topic', 'TopicController@userTopic'); //用户关注话题
        Route::post('discover-voice', 'DiscoverController@discoverVoice'); //语音播放器
        //动态相关
        Route::post('discover-msg-get', 'DiscoverController@discoverMsgGet'); //动态消息
        Route::post('discover-msg-clear', 'DiscoverController@discoverMsgClear'); //动态清空
        Route::post('discover-publish', 'DiscoverController@discoverPublish'); //发布
        Route::post('discover-unlock', 'DiscoverController@discoverUnlock'); //发布权限获取
        Route::post('discover-delete', 'DiscoverController@discoverDelete'); //删除
        Route::post('discover-share', 'DiscoverController@discoverShare'); //分享
        Route::post('discover-zan', 'DiscoverController@discoverZan'); //赞
        Route::post('discover-ignore', 'DiscoverController@discoverIgnore'); //不再推荐
        Route::post('comment-publish', 'DiscoverController@commentPublish'); //发布评论
        Route::post('comment-zan', 'DiscoverController@commentZan'); //评论点赞
        Route::post('comment-delete', 'DiscoverController@commentDelete'); //评论删除
        Route::post('user-discover', 'DiscoverController@userDiscover'); //单个用户的动态
        Route::post('topic-discover', 'DiscoverController@topicDiscover'); //单个话题的动态
        Route::post('discover-detail', 'DiscoverController@discoverDetail'); //单个话题的详细信息
        Route::post('discover-recommend', 'DiscoverController@discoverRecommend'); //关注&附近&推荐 ---推荐
        Route::post('discover-nearby', 'DiscoverController@discoverNearby'); //关注&附近&推荐 ---附近
        Route::post('discover-follow', 'DiscoverController@discoverFollow'); //关注&附近&推荐 ---关注
    });
    //邀请相关
    Route::group(['prefix' => 'invite', 'namespace' => 'Users', 'middleware' => ['jwtAuth']], function () {
        Route::post('invite-rank-list', 'UsersInviteController@inviteRankList'); //邀请首页数据
        Route::post('draw-ask', 'UsersInviteController@drawAsk'); //收益提现
        Route::post('draw-info', 'UsersInviteController@drawInfo'); //提现信息
        Route::post('send-sms', 'UsersInviteController@sendSmsCode'); //发送短信
        Route::post('account-bind', 'UsersInviteController@accountBind'); //账号绑定
    });
    //系统相关
    Route::group(['prefix' => 'sys', 'namespace' => 'Users'], function () {
        Route::post('global-settings-sign', 'UsersSubscribeController@globalSettingsSign'); //全局的基础配置无需登录
        Route::group(['middleware' => ['jwtAuth']], function () {
            Route::post('vip-list', 'UsersSubscribeController@vipList'); //vip 档位
            Route::post('recharge-list', 'UsersSubscribeController@rechargeList');  //充值档位
            Route::post('self-right', 'UsersSubscribeController@selfRight');  //我的权益列表
            Route::post('fast-pay', 'UsersSubscribeController@fastPay');  //充值弹窗
            Route::post('vip-buy', 'UsersSubscribeController@appleBuy');   //订阅&内购购买
            Route::post('order-make', 'UsersSubscribeController@orderMake'); //生成订单号
            Route::post('order-status', 'UsersSubscribeController@orderStatus');  //查询订单状态
            Route::post('order-fail', 'UsersSubscribeController@orderFail');  //查询失败订单
            Route::post('payment-order-make', 'UsersSubscribeController@createOrder'); //支付宝微信订单生成
            Route::post('balance-log', 'UsersSubscribeController@userBalanceLog'); //用户友币变动记录
            /*------系统级的配置信息获取--启动屏幕获取一次即可-----*/
            Route::post('global-settings', 'UsersSubscribeController@globalSettings'); //全局的基础配置

            Route::post('unlock-contact', 'UsersSubscribeController@unlockContact'); //解锁联系方式
            Route::post('buy-contact-num', 'UsersSubscribeController@buyContactNum'); //购买解锁权限

            Route::post('unlock-chat', 'UsersSubscribeController@unlockChat'); //解锁私信方式
            Route::post('buy-chat-num', 'UsersSubscribeController@buyChatNum'); //购买私信权限

            Route::post('unlock-im', 'UsersSubscribeController@unlockImContact'); //解锁im联系方式
            Route::post('buy-im-num', 'UsersSubscribeController@buyImContact'); //购买im解锁权限


            Route::post('send-invite', 'UsersSubscribeController@sendInvite'); //发送邀请短信
            /*-------融云系统消息发送----------*/
            Route::post('incomplete-info-get', 'UsersSubscribeController@incompleteInfoGet'); //未完善资料获取
            Route::post('sys-msg', 'UsersSubscribeController@sysMsg'); //系统级融云消息模板
            /*------获取完善资料获取积分的相关政策-----*/
            Route::post('sys-reward', 'UsersSubscribeController@sysReward'); //积分奖励列表
            /*------签到设置相关-----*/
            Route::post('sign-remind-set', 'UsersSettingController@signRemindSet');
            Route::post('sign-set', 'UsersSettingController@signSet');
            Route::post('sign-get', 'UsersSettingController@signGet');
        });
    });
    //礼物及道具
    Route::group(['prefix' => 'gift', 'middleware' => ['jwtAuth']], function () {
        Route::post('gift-list', 'GiftController@giftList');  //礼物列表
        Route::post('gift-more', 'GiftController@giftMore');  //更多礼物列表
        Route::post('gift-send', 'GiftController@giftSend');  //赠送礼物
        Route::post('user-gift-receive', 'GiftController@userGiftReceive');  //用户守护排行榜
    });

    //问题反馈及Q&A
    Route::group(['prefix' => 'feedback', 'middleware' => ['jwtAuth']], function () {
        Route::post('feedback', 'FeedbackController@feedback'); //反馈
        Route::post('question', 'FeedbackController@question'); //常见问题
    });

    //版本升级
    Route::group(['prefix' => 'update', 'middleware' => ['jwtAuth']], function () {
        Route::post('info', 'FeedbackController@updateInfo'); //升级信息获取
        Route::post('set', 'FeedbackController@updateSet'); //升级信息回执
    });

    //认证相关操作
    Route::group(['prefix' => 'auth', 'namespace' => 'Users', 'middleware' => ['jwtAuth']], function () {
        Route::post('real-auth', 'UsersAuthController@realAuth'); //真人认证
        Route::post('identity-auth', 'UsersAuthController@identityAuth'); //身份及手机号认证
        Route::post('goddess-auth', 'UsersAuthController@goddessAuth'); //女神认证
    });

    //设备事件
    Route::group(['prefix' => 'event'], function () {
        Route::post('event', 'EventController@event'); //事件上报
    });
});


//充值档位
Route::post('h5-vip-list', 'H5Controller@vipList'); //vip 档位
Route::post('h5-recharge-list', 'H5Controller@rechargeList');  //充值档位
Route::post('h5-payment-create', 'H5Controller@askH5Order');  //创建支付订单
Route::get('h5-wechat-pub-pay', 'H5Controller@wechatPub');  //创建支付订单
Route::get('h5-wechat-pub-pay-two', 'H5Controller@wechatPubTwo');  //创建支付订单
Route::get('h5-wechat-pub-user-info', 'H5Controller@wechatPubUserInfo');  //获取用户信息
//异步通知
Route::group(['prefix' => 'notify'], function () {
    Route::any('video-chat', 'CommonController@videoChat');  //语音通话
    Route::post('notify-apple', 'CommonController@notifyApple');  //支付通知
    Route::post('notify-alipay', 'PaymentController@notifyAlipay');  //支付宝支付通知
    Route::post('notify-wechat', 'PaymentController@notifyWechat');  //微信支付通知
    Route::get('ticket-check', 'CommonController@ticketCheck');  //票据测试
});
//数据迁移
Route::group(['prefix' => 'recover'], function () {
    Route::post('sms-send', 'RecoverController@smsSend');  //验证码获取
    Route::post('login-in', 'RecoverController@loginIn');  //登陆
    Route::post('recover-move', 'RecoverController@recoverMove');  //迁移
});
//web端接口
//web 需要的数据api
Route::group(['prefix' => 'web', 'namespace' => 'Discover'], function () {
    Route::post('discover', 'DiscoverController@webDiscoverGet');
    Route::post('discover-detail/{id}', 'DiscoverController@webDiscoverDetail');
    Route::post('user-info/{id}', 'DiscoverController@webUserInfo');
    Route::post('banner-info/{id}', 'BannerController@getBanner');
    Route::post('gov-info/{id}', 'BannerController@getGov');
});
//分销代理需要的接口
Route::group(['prefix' => 'client', 'namespace' => 'Client'], function () {
    //登陆不用验证信息
    Route::post('login', 'AuthController@login');
    Route::post('logout', 'AuthController@logout');
    Route::post('register', 'AuthController@register');
    Route::post('forget', 'AuthController@forget');
    Route::post('sms-check', 'AuthController@checkCode');
    Route::post('get-sms/{type?}', 'AuthController@getcheckcode');//获取验证码
    Route::get('uv', 'DbCollectController@dataUpload');//数据上报
});
Route::group(['prefix' => 'ucenter', 'namespace' => 'Client', 'middleware' => ['clientAuth']], function () {
    Route::post('user-base-info', 'UcenterController@userBaseInfo');
    Route::post('user-messages', 'UcenterController@getUserMessage');
    Route::post('user-messages-read/{id?}', 'UcenterController@userMessageRead');
    Route::post('user-min-baseinfo', 'UcenterController@userMinBaseInfo');
    Route::post('user-pwd-modify', 'UcenterController@modifyLoginPwd');
    Route::post('user-avatar-upload', 'UcenterController@changeHeadImg');
    Route::post('user-login-log', 'UcenterController@getPageLog');
    Route::post('user-balance-log', 'UcenterController@getBalancePageLog');
    Route::post('user-profit-log', 'UcenterController@getProfitPageLog');
    Route::post('user-client-user', 'UcenterController@getClientUser');
    Route::post('user-client-agent', 'UcenterController@getClientAgent');
    //资料编辑
    Route::post('base-info-save', 'UcenterController@baseInfoSave');
    Route::post('base-info-get', 'UcenterController@baseInfoGet');
    Route::post('invoiceinfo-save', 'UcenterController@invoiceSave');
    //图片上传
    Route::post('img-upload/{dir?}', 'UcenterController@uploadImg');
    //发送登陆后的验证码
    Route::post('profile-sms-send/{type?}', 'UcenterController@profileSmsSend');
    //申请提现
    Route::post('draw-info-get', 'DrawController@invoiceInfo');
    Route::post('draw-ask', 'DrawController@askInvoice'); //申请
    Route::post('draw-list', 'DrawController@getInvoiceList');//记录
    Route::post('draw-detail/{id?}', 'DrawController@getInfoById');//详情
    //推广连接生成或转换
    Route::post('promote-url-get', 'PromoteController@promoteUrlGet');
    Route::post('promote-url-short', 'PromoteController@promoteUrlShort');
    Route::post('promote-url-create', 'PromoteController@promoteUrlGain');
    Route::post('promote-video', 'PromoteController@promoteVideo');
    Route::post('promote-data-get', 'PromoteController@getPromoteData');
    Route::post('promote-data-chart', 'PromoteController@getPromoteChartData');
    Route::post('promote-report-get', 'UcenterController@getReportPageLog');
});
//管理员
Route::group(['namespace' => 'Admin'], function () {
    Route::post('login', 'AdminLoginController@authenticate');
    Route::group(['middleware' => ['adminAuth']], function () {
        Route::post('login-out', 'AdminLoginController@loginOut');

        Route::group(['prefix' => 'admin-lib', 'middleware' => ['signalAuth'],], function () {
            Route::post('lib-chat-list', 'LibController@libChatList');
            Route::post('lib-chat-add', 'LibController@libChatAdd');
            Route::post('lib-chat-delete', 'LibController@libChatDelete');

            Route::post('lib-bio-list', 'LibController@libBioList');
            Route::post('lib-bio-add', 'LibController@libBioAdd');
            Route::post('lib-bio-delete', 'LibController@libBioDelete');

            Route::post('lib-nick-list', 'LibController@libNickList');
            Route::post('lib-nick-add', 'LibController@libNickAdd');
            Route::post('lib-nick-delete', 'LibController@libNickDelete');

            Route::post('lib-question-list', 'LibController@libQuestionList');
            Route::post('lib-question-add', 'LibController@libQuestionAdd');
            Route::post('lib-question-delete', 'LibController@libQuestionDelete');

            Route::post('lib-banner-list', 'LibController@libBannerList');
            Route::post('lib-banner-add', 'LibController@libBannerAdd');
            Route::post('lib-banner-delete', 'LibController@libBannerDelete');

            Route::post('lib-gift-list', 'LibController@libGiftList');
            Route::post('lib-gift-update', 'LibController@libGiftUpdate');
            Route::post('lib-gift-delete', 'LibController@libGiftDelete');
            //会员兑换码
            Route::post('lib-code-list', 'LibController@libCodeList');
            Route::post('lib-code-gain', 'LibController@libCodeGain');
            //资源管理
            Route::post('list-upload', 'ResourceController@listUpload');
            Route::post('list-album', 'ResourceController@listAlbum');
            Route::post('list-avatar', 'ResourceController@listAvatar');
            Route::post('resource-update', 'ResourceController@resourceUpdate');
        });
        //日志类
        Route::group(['prefix' => 'admin-log', 'middleware' => ['signalAuth'],], function () {
            Route::post('log-change-list', 'LogController@logChangeList');
            Route::post('log-change-update/{type}', 'LogController@logChangeUpdate');
            Route::post('log-users-action', 'LogController@logActionList');
            Route::post('log-user-log', 'LogController@logOperateList');
            Route::post('log-sys-err', 'LogController@logSysErrList');
            Route::post('log-jobs', 'LogController@logJobsList');
            Route::post('log-sys-err-del', 'LogController@logSysErrDel');
            Route::post('log-im-chat', 'LogController@logImChat');
            Route::post('log-im-chat-del', 'LogController@logImChatDel');
            Route::post('log-auth', 'LogController@logAuth');
            Route::post('log-auth-update', 'LogController@logAuthUpdate');

            Route::post('log-risk', 'LogController@logRisk');
            Route::post('log-risk-update', 'LogController@logRiskUpdate');

            Route::post('log-user-login', 'LogController@logLoginList');
            Route::post('log-user-login-err', 'LogController@logLoginErrList');
            Route::post('log-sms-list', 'LogController@LogSmsList');
            Route::post('log-push-list', 'LogController@logPushList');
            Route::post('apple-iap-list', 'LogController@AppleIapList');   //苹果日志-iap
            Route::post('apple-iap-in-app-list', 'LogController@AppleIapInAppList');//苹果日志-iap-in-app
            Route::post('apple-iap-latest-receipt-list', 'LogController@AppleIapLatestReceiptInfo');//苹果日志-iap-latest
            Route::post('apple-iap-pending-renewal-list', 'LogController@AppleIapPendingRenewalInfo');//苹果日志-iap-renew
            Route::post('log-callback-alipay', 'LogController@LogCallbackAlipay'); //阿里回调
            Route::post('log-callback-wechat', 'LogController@LogCallbackWechat'); //微信回调
            //资金友币钱包变动
            Route::post('log-balance', 'LogController@logBalance');
        });

        //动态管理
        Route::group(['prefix' => 'admin-discover', 'middleware' => ['signalAuth']], function () {
            Route::post('discover-list', 'DiscoverController@discoverList');
            Route::post('topic-list', 'DiscoverController@topicList');
            Route::post('topic-update', 'DiscoverController@topicUpdate');
            Route::post('discover-update', 'DiscoverController@discoverUpdate');
            Route::post('discover-zan', 'DiscoverController@discoverZan');
            Route::post('discover-cmt', 'DiscoverController@discoverCmt');
            Route::post('discover-cmt-update', 'DiscoverController@discoverUpdateCmt');
        });

        Route::group(['prefix' => 'admin', 'middleware' => ['signalAuth']], function () {
            Route::post('daily-report', 'ReportController@dailyReport');
            //需要验证单点登录
            Route::post('user-min-base-info', 'AdminController@userMinBaseInfo');

            Route::post('admin-role-permission', 'AdminController@adminPermission');
            Route::post('site-settings-get/{option?}', 'AdminController@settingsGet');
            Route::post('site-settings-save/{option?}', 'AdminController@settingsSave');
            //ip黑名单管理
            Route::post('black-ip-list', 'AdminController@blackIpList');
            Route::post('black-ip-add', 'AdminController@blackIpAdd');
            Route::post('black-ip-update', 'AdminController@blackIpUpdate');

            //管理员管理
            Route::post('admin-list', 'AdminController@adminList');
            Route::post('admin-delete', 'AdminController@adminDelete');
            Route::post('admin-update/{type?}', 'AdminController@adminUpdate');
            Route::post('admin-add', 'AdminController@adminAdd');

            //角色管理
            Route::post('admin-role-list', 'AdminController@roleList');
            Route::post('admin-role-add', 'AdminController@roleAdd');
            Route::post('admin-role-delete', 'AdminController@roleDelete');
            Route::post('admin-node-list', 'AdminController@nodeList');
            Route::post('admin-node-add', 'AdminController@nodeAdd');
            Route::post('admin-node-delete', 'AdminController@nodeDelete');
            Route::post('admin-role-node-delete', 'AdminController@roleNodeDelete');
            Route::post('admin-role-node-list', 'AdminController@roleNodeList');
            Route::post('admin-role-node-add', 'AdminController@roleNodeAdd');
            Route::post('admin-role-update', 'AdminController@adminRoleUpdate');
            //消息管理 & 聊天消息管理
            Route::post('users-message-log', 'SysController@userMessageLog');
            Route::post('users-message-add', 'SysController@userMessageAdd');
            Route::post('users-message-delete', 'SysController@userMessageDelete');
            //用户登录激活日志
            Route::post('user-pwd-modify', 'AdminSettingController@modifyAllPwd');
            //点击获取经纬度对应的地点
            Route::post('exchange-point', 'UsersController@exchangePoint');
            Route::post('users-info-update', 'UsersController@userInfoUpdate');
            Route::post('users-push-set', 'UsersController@userPushSet');
            Route::post('users-push-get', 'UsersController@pushSuggest');
            Route::post('users-contact-update', 'UsersController@userContactUpdate');
            //获取客服信息和用户的头像等信息
            Route::post('server-info-get', 'AdminController@serverInfoGet');
            Route::post('server-user-list', 'AdminController@serverUserList');
            //上传
            Route::post('img-upload/{dir?}', 'AdminController@uploadImg');
            //用户管理
            Route::post('users-list', 'UsersController@userList');
            Route::post('users-update', 'UsersController@updateUsers');
            Route::post('user-nick-get', 'UsersController@userNickGet');
            Route::post('user-avatar-refresh', 'UsersController@userAvatarRefresh');
            Route::post('users-set-new-pwd', 'UsersController@usersInfoSet');
            Route::post('user-balance-set', 'UsersController@userBalanceSet');
            Route::post('users-level-update', 'UsersController@userLevelSet');
            Route::post('user-add', 'UsersController@userAdd');
            Route::post('user-delete', 'UsersController@userDelete');
            Route::post('users-sync-es', 'UsersController@usersSyncEs');
            Route::post('topic-sync-es', 'UsersController@topicSyncEs');
            //订单 补单
            Route::post('order-list', 'OrderController@orderList');
            Route::post('order-list-payment', 'OrderController@orderListPayment');
            Route::post('order-retry', 'OrderController@orderRetry');
            Route::post('order-sync-vip', 'OrderController@usersVipSync');
            Route::post('order-sync-check', 'OrderController@orderSyncCheck');
            Route::post('order-delete', 'OrderController@orderDelete');
            //提现管理
            Route::post('draw-list', 'OrderController@drawList');
            Route::post('draw-update', 'OrderController@drawUpdate');
            //意见反馈
            Route::post('feedback-list', 'UsersController@feedbackList');
            Route::post('feedback-update', 'UsersController@feedbackUpdate');
            Route::post('feedback-delete', 'UsersController@feedbackDelete');
            //浏览记录&点赞&拉黑
            Route::post('list-browser', 'BrowseController@listBrowse');
            Route::post('list-contact', 'BrowseController@listContact');
            Route::post('list-zan-discover', 'BrowseController@listZanDiscover');
            Route::post('list-black-user', 'BrowseController@listBlackUser');
            Route::post('list-zan-cmt', 'BrowseController@listZanCmt');

            //首页数据
            Route::post('user-dashboard-order', 'DashboardController@userDashboardOrder');
            Route::post('user-dashboard', 'DashboardController@userDashboard');
            Route::post('users-dashboard-report', 'DashboardController@userDashboardReport');
            Route::post('channel-dashboard-report', 'DashboardController@channelDashboardReport');

            //更像相册非法性
            Route::post('user-album-illegal', 'UsersController@updateUserAlbumIllegal');
            Route::post('user-post-illegal', 'DiscoverController@setDiscoverIllegal');
            //支付【阿里云 & 微信】
            Route::post('payment-order-list', 'OrderController@orderPaymentList');
        });

        //代理需要的全部接口
        Route::group(['prefix' => 'client', 'middleware' => ['signalAuth']], function () {
            Route::post('client-list', 'ClientController@userList');
            Route::post('client-set-new-pwd', 'ClientController@usersInfoSet');
            Route::post('client-balance-set', 'ClientController@userBalanceSet');
            Route::post('client-info-update', 'ClientController@userInfoUpdate');
            Route::post('client-delete', 'ClientController@userDelete');
            Route::post('client-report', 'ClientController@userReport');
            Route::post('client-promote-log', 'ClientController@clientPromoteLog');
            Route::post('client-promote-log-del', 'ClientController@clientPromoteLogDel');
            Route::post('client-draw-list', 'ClientController@clientDrawList');
            Route::post('client-draw-update', 'ClientController@clientDrawUpdate');
            Route::post('client-profit-list', 'ClientController@clientProfitList');
            Route::post('client-profit-update', 'ClientController@clientProfitUpdate');
        });
    });
});

