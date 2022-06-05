<?php
//定义融云推送信息
// 100 官方消息
// 101 系统通知
// 102 客服id
// 103 点赞等消息的推送  【原来是10000】
// 104 底部完善资料通知
// 105 批量打招呼通知
// 106 签到弹窗
// 107 心友币奖励弹窗
// 108 任务奖励弹窗
return [
    'active' => 7200, //定义在线时长
    'coordinate' => 86400, //更新
    'donate_vip' => 3, //体验会员赠送时长
    'scan' => [
        'image_on' => true,
        'video_on' => true,
        'audio_on' => true,
    ],
    'scan_type' => 'sync',  //async 异步  sync 同步   检测图片违规
    'upload_oss' => 'sync',  //async 异步  sync 同步  传图到oss服务
    'im_call_price' => 10,  //语音通话每分钟需要的币
    'im_chat_price' => 1,   //聊天费用，每三句话一个扣费频次 0表示免费
    'im_chat_sex' => 3,  // 1 女收费  2男收费 3全部
    //注册自动打招呼【false关true开】
    'register_say_hi' => true,
    'register_say_num' => 10,  //注册打招呼人数
    //联系方式
    'contact_price' => 98,  //解锁一个联系方式需要的币
    'contact_view_limit_on' => true,  //是否开启收费查看联系方式
    //收费私信
    'chat_price' => 98,  //解锁一个私信需要的币
    'chat_limit_on' => true,  //是否开启收费私信
    //发布动态
    'im_price' => 98,  //解锁列表im人员
    'im_limit_on' => true,  //是否开启列表im人员收费

    //发布动态
    'publish_price' => 80,  //发布动态扣币
    'publish_limit_on' => true,  //发布动态收费开关


    //超级曝光
    'super' => [
        'super_show_on' => true,  //是否开启超级曝光
        'super_show_price' => 60,  //超级曝光价格
        'super_show_duration' => 10800, //没次超级曝光时长  单位S
        'give_free' => 8,  //免费赠送次数最多8次
    ],
    //推荐
    'recommend' => 3,  //3个小时内推荐过的就不再推荐第二次
    //邀请好友
    'invite_on' => true,  //邀请好友开关
    'invite_reward_vip' => 1, //邀请好友注册成功赠送VIP天数
    'invite_reward_son_vip' => 1, //邀请的好友开通VIP后赠送的VIP

    'rate_benefit' => 0.5, //分成执行比例 8%
    'invite_reward' => 0, //邀请好友奖励
    'gift_benefit' => false,  //礼物分佣
    'chat_benefit' => false,   //聊天分佣
    //相册
    'album_max' => 15,  //相册最大照片数
    //兑换比例
    'points_rate' => 100,  //100积分兑换1元钱
    'min_draw' => 5, //提现门槛 元
    //分成操作开始
    'benefit_share' => [
        'gift_sex' => 1,  // 0 都不结算 1只结算女生  2只结算男生 3全部结算
        'gift_rate' => 0.4,  // 40%
        'gift_rate_unverified' => 0.2, //20%

        'msg_sex' => 1,  // 0 都不结算 1只结算女生  2只结算男生 3全部结算
        'msg_rate' => 0.34, //34%
        'msg_rate_unverified' => 0.17, //17%

        'contact_unlock' => 0.4, // 40%
        'contact_unlock_unverified' => 0.2, //20%
    ],
    //解锁付费相册分成
    'album_private_benefit' => 0.4,
    //阅后即焚设置
    'burn_limit' => false,   //是否限定必须实名认证才能设置阅后即焚
    'burn' => [
        'burn' => true,
        'time_limit' => 3,
        'time_limit_vip' => 10,
    ],
    //同城速配价格设置
    'match_price' => [
        'price' => 10,
    ],
    //限制im 禁言时间和次数
    'banned' => [
        'banned_limit' => 5,  //违规次数超过五次
        'banned_time' => 300,  //禁言时间为5分钟
        'banned_tips' => '您的发言违规次数过多，被系统禁言5分钟，请稍后再试，文明的发言环境需要你我共同营造，感谢您的配合。',  //禁言提示语
    ],

    //代理分销分成比例 【vip+内购】
    'client' => [
        'user' => [
            'vip' => [
                'level_1' => 0.5,
                'level_2' => 0.05,
                'level_3' => 0.01,
            ],
            'recharge' => [
                'level_1' => 0.3,
                'level_2' => 0.05,
                'level_3' => 0.01,
            ]
        ],
        //由于取消了代理分成的部分逻辑，所以这部分的分成计算已经取消了| 暂时注释掉
        //        'client' => [
        //            'level_1' => 0.3,
        //            'level_2' => 0.05,
        //            'level_3' => 0.02,
        //        ],
    ],
    //针对某一会员单独设置分成比例 【这部分是针对某些会员的特殊分成比例进行计算的】 === 优先级高于通用分成比例
    //需要注意设置的值不能大于1 vip的由于有苹果的30%抽成，所以总值不能大于0.7否则就需要贴钱
    'special_rate' => [
        22 => [
            'vip' => [
                'level_1' => 0.4,
                'level_2' => 0.02,
                'level_3' => 0.15,
            ],
            'recharge' => [
                'level_1' => 0.2,
                'level_2' => 0.15,
                'level_3' => 0.08,
            ]
        ],
    ],

];
