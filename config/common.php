<?php

return [
    //短信模板
    'msg_template' => [
        'verify_code' => '您好，您的验证码是%s,有效时间五分钟，请尽快查收',
        'verify_code_template' => 'SMS_205616705',
//        'verify_code_template' => 'SMS_206480125',

        'ask_draw' => '您好，您的验证码是%s,有效时间五分钟，请尽快查收',
        'ask_draw_template' => 'SMS_205616705',
//        'ask_draw_template' => 'SMS_206480125',

        'profile_code' => '您好，您的验证码是%s,有效时间五分钟，请尽快查收',
        'profile_code_template' => 'SMS_205616705',
//      'profile_code_template' => 'SMS_206480125',

        'find_password' => '您好，您找回密码操作的验证码是：%s，有效时间五分钟，请尽快查收',
        'find_password_template' => 'SMS_205621737',
//        'find_password_template' => 'SMS_206485066',

        'modify_user_pwd' => '您好，您的密码已经修改，新密码为%s,请妥善保存',
        'modify_user_pwd_template' => 'SMS_205616706',
//        'modify_user_pwd_template' => 'SMS_206420619',

        'login_notice' => '您的账号刚刚在异地登陆了，登陆IP:%s，，密码可能已经泄露,请注意密码保存',
        'login_notice_template' => 'SMS_205404814',

        'notice' => '刚刚有%s个人关注你了  点击登录查看 %s，回TD退订',
        'notice_template' => 'SMS_205404814',

        'reg_notice' => '您的心友账号已经注册成功，去完成真人认证可以更加吸引异性哦！%s',
        'reg_notice_template' => 'SMS_205404814',

        'invite_auth' => '您的好友%s邀请您真人认证，认证地址:moshi://auth,认证后预计曝光次数将提升200点',
        'invite_auth_template' => 'SMS_205404814',

        'invite_contact' => '您的好友%s邀请您完善联系方式，完善地址:moshi://contact,他人解锁您的联系方式您可得到友币及现金奖励',
        'invite_contact_template' => 'SMS_205404814',

        'awaken' => '距离您500米内有位女士对您有好感（%s），与您匹配度极高，点击 %s 马上认识ta，回TD退订',  //(34,165cm,家乡上海)
        'awaken_template' => 'SMS_205404814',
    ],
    //5分钟内最大接收短息次数
    'max_sms_time' => 6,

    //数据库查询缓存redis
    'redis_cache' => env('REDIS_CACHE', true),
    #服务器ip
    'server_ip' => '139.224.14.237',

    //身份证三要素
    'identity_check' => [
        'app_key' => '',
        'app_secret' => '',
        'app_code' => ''
    ],
    //钉钉错误通知
    'error_handler' => [
        'token' => env('ERROR_HANDLER', ''),
        'open' => env('ERROR_DING', true),
        'env' => 'local',
    ],
];
