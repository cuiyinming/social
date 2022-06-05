<?php

return [
    //vip 权益
    'vip_list' => [
        'swordsman' => [  //剑士
            [
                'id' => '',
                'id_name' => 'swordsman_month',
                'id_num' => 1,
                'type' => '自动续费订阅',
                'type_name' => '连续包周',
                'price' => 83.00,
                'origin_price' => 108.00,
                'option' => 1,
                'name' => '连续包周',
                'gear' => '周',
            ], [
                'id' => '',
                'id_name' => 'swordsman_quarter',
                'id_num' => 2,
                'type' => '自动续费订阅',
                'type_name' => '一周会员',
                'price' => 88.00,
                'origin_price' => 198.00,
                'option' => 0,
                'name' => '一周会员',
                'gear' => '周',
            ],
//            [
//                'id' => '',
//                'id_name' => 'swordsman_year',
//                'id_num' => 3,
//                'type' => '自动续费订阅',
//                'type_name' => '连续包年',
//                'price' => 288.00,
//                'origin_price' => 368.00,
//                'name' => '剑士包年',
//                'gear' => '年',
//            ]
        ],
        'knight' => [    //骑士
            [
                'id' => '',
                'id_name' => 'knight_month',
                'id_num' => 4,
                'type' => '自动续费订阅',
                'type_name' => '连续包月',
                'price' => 168.00,
                'origin_price' => 266.00,
                'option' => 1,
                'name' => '连续包月',
                'gear' => '月',
            ], [
                'id' => '',
                'id_name' => 'knight_quarter',
                'id_num' => 5,
                'type' => '自动续费订阅',
                'type_name' => '一个月会员',
                'price' => 198.00,
                'origin_price' => 298.00,
                'option' => 0,
                'name' => '体验包月',
                'gear' => '月',
            ],
//            [
//                'id' => '',
//                'id_name' => 'knight_year',
//                'id_num' => 6,
//                'type' => '自动续费订阅',
//                'type_name' => '连续包年',
//                'price' => 768.00,
//                'origin_price' => 868.00,
//                'name' => '骑士包年',
//                'gear' => '年',
//            ]
        ],

        'suzerain' => [   //领主
            [
                'id' => '',
                'id_name' => 'suzerain_month',
                'id_num' => 7,
                'type' => '自动续费订阅',
                'type_name' => '连续包季',
                'price' => 448.00,
                'origin_price' => 594.00,
                'option' => 1,
                'name' => '连续包季',
                'gear' => '季',
            ], [
                'id' => '',
                'id_name' => 'suzerain_quarter',
                'id_num' => 8,
                'type' => '自动续费订阅',
                'type_name' => '三个月会员',
                'price' => 488.00,
                'origin_price' => 624.00,
                'option' => 0,
                'name' => '体验包季',
                'gear' => '季',
            ],
//            [
//                'id' => '',
//                'id_name' => 'suzerain_year',
//                'id_num' => 9,
//                'type' => '自动续费订阅',
//                'type_name' => '连续包年',
//                'price' => 1448.00,
//                'origin_price' => 1688.00,
//                'name' => '领主包年',
//                'gear' => '年',
//            ]
        ],
        'lord' => [    //勋爵
            [
                'id' => '',
                'id_name' => 'lord_month',
                'id_num' => 10,
                'type' => '自动续费订阅',
                'type_name' => '连续包年',
                'price' => 1398.00,
                'origin_price' => 2376.00,
                'option' => 1,
                'name' => '连续包年',
                'gear' => '年',
            ], [
                'id' => '',
                'id_name' => 'lord_quarter',
                'id_num' => 11,
                'type' => '自动续费订阅',
                'type_name' => '一年会员',
                'price' => 1648.00,
                'origin_price' => 2496.00,
                'option' => 0,
                'name' => '体验包年',
                'gear' => '年',
            ],
//            [
//                'id' => '',
//                'id_name' => 'lord_year',
//                'id_num' => 12,
//                'type' => '自动续费订阅',
//                'type_name' => '连续包年',
//                'price' => 2698.00,
//                'origin_price' => 3168.00,
//                'name' => '勋爵包年',
//                'gear' => '年',
//            ]
        ],
    ],
    //苹果的价格
    'vip_list_ver' => [
        [
            'id' => '',
            'id_num' => 1,
            'price' => 83.00,
            'origin_price' => 108.00,
            'option' => 1,
            'name' => '连续包周',
            'gear' => '周',
        ], [
            'id' => '',
            'id_num' => 4,
            'price' => 168.00,
            'origin_price' => 198.00,
            'option' => 1,
            'name' => '连续包月',
            'gear' => '月',
        ], [
            'id' => '',
            'id_num' => 2,
            'price' => 88.00,
            'origin_price' => 198.00,
            'option' => 0,
            'name' => '一周包周',
            'gear' => '周',
        ],
        [
            'id' => '',
            'id_num' => 5,
            'price' => 198.00,
            'origin_price' => 298.00,
            'option' => 0,
            'name' => '一月会员',
            'gear' => '月',
        ],
        [
            'id' => '',
            'id_num' => 8,
            'price' => 488.00,
            'origin_price' => 624.00,
            'option' => 0,
            'name' => '一季会员',
            'gear' => '季',
        ],
        [
            'id' => '',
            'id_num' => 11,
            'price' => 1648.00,
            'origin_price' => 2496.00,
            'option' => 0,
            'name' => '一年会员',
            'gear' => '年',
        ],
    ],
    //安卓的价格
    'vip_list_ver_android' => [
        [
            'id' => '',
            'id_num' => 2,
            'price' => 88.00,
            'origin_price' => 198.00,
            'option' => 0,
            'name' => '一周包周',
            'gear' => '周',
        ],
        [
            'id' => '',
            'id_num' => 5,
            'price' => 128.00,
            'origin_price' => 198.00,
            'option' => 0,
            'name' => '一月会员',
            'gear' => '月',
        ],
        [
            'id' => '',
            'id_num' => 8,
            'price' => 238.00,
            'origin_price' => 424.00,
            'option' => 0,
            'name' => '一季会员',
            'gear' => '季',
        ],
        [
            'id' => '',
            'id_num' => 11,
            'price' => 398.00,
            'origin_price' => 1496.00,
            'option' => 0,
            'name' => '一年会员',
            'gear' => '年',
        ],
    ],

    //充值金豆对应关系
    'recharge_list' => [
//        [
//            'id' => '',
//            'product_id' => '',
//            'price' => 3.00,
//            'diamond' => 33,
//            'desc' => '首充特惠！',
//            'status' => 1
//        ],
//        [
//            'id' => '',
//            'product_id' => '',
//            'price' => 8.00,
//            'diamond' => 85,
//            'desc' => '首充特惠',
//            'status' => 1
//        ],
        [
            'id' => '',
            'product_id' => '',
            'price' => 30.00,
            'diamond' => 335,
            'desc' => '首充特惠',
            'status' => 1
        ], [
            'id' => '',
            'product_id' => '',
            'price' => 68.00,
            'diamond' => 720,
            'desc' => '',
            'status' => 1
        ], [
            'id' => '',
            'product_id' => '',
            'price' => 98.00,
            'diamond' => 1080,
            'desc' => 'hot',
            'status' => 1
        ], [
            'id' => '',
            'product_id' => '',
            'price' => 168.00,
            'diamond' => 1790,
            'desc' => '',
            'status' => 1
        ], [
            'id' => '',
            'product_id' => '',
            'price' => 298.00,
            'diamond' => 3160,
            'desc' => '',
            'status' => 1
        ], [
            'id' => '',
            'product_id' => '',
            'price' => 588.00,
            'diamond' => 6190,
            'desc' => '',
            'status' => 1
        ], [
            'id' => '',
            'product_id' => '',
            'price' => 1198.00,
            'diamond' => 12680,
            'desc' => '',
            'status' => 1
        ],
    ],
    //签到奖励
    'sign' => [
        [
            'day' => 1,
            'day_str' => '第1天',
            'reward' => '+10',
            'reward_int' => 10,
            'signed' => false,
            'tips' => '今日可签',
        ],
        [
            'day' => 2,
            'day_str' => '第2天',
            'reward' => '+13',
            'reward_int' => 13,
            'signed' => false,
            'tips' => '',
        ],
        [
            'day' => 3,
            'day_str' => '第3天',
            'reward' => '+15',
            'reward_int' => 15,
            'signed' => false,
            'tips' => '',
        ],
        [
            'day' => 4,
            'day_str' => '第4天',
            'reward' => '+18',
            'reward_int' => 18,
            'signed' => false,
            'tips' => '',
        ],
        [
            'day' => 5,
            'day_str' => '第5天',
            'reward' => '+20',
            'reward_int' => 20,
            'signed' => false,
            'tips' => '',
        ],
        [
            'day' => 6,
            'day_str' => '第6天',
            'reward' => '+22',
            'reward_int' => 22,
            'signed' => false,
            'tips' => '',
        ],
        [
            'day' => 7,
            'day_str' => '第7天',
            'reward' => '+25',
            'reward_int' => 25,
            'signed' => false,
            'tips' => '',
        ],
    ],

    'alipay' => [
        'use_sandbox' => false, // 是否使用沙盒模式
        'sign_type' => 'RSA2', // RSA  RSA2

        'app_id' => '',  //甜信
        'ali_public_key' => '',
        'rsa_private_key' => '',
        'notify_url' => '',
        'return_url' => '',
        'limit_pay' => [], // 用户不可用指定渠道支付当有多个渠道时用“,”分隔
    ],
    'wechat' => [
        'use_sandbox' => false, // 是否使用 微信支付仿真测试系统
        'app_id' => '',  // 公众账号ID
        'mch_id' => '', // 商户id
        'md5_key' => '', // md5 秘钥
        'app_cert_pem' => '',
        'app_key_pem' => '',
        'notify_url' => '',
        'redirect_url' => '',
        'sign_type' => 'MD5', // MD5  HMAC-SHA256
        'limit_pay' => [],
        'fee_type' => 'CNY', // 货币类型  当前仅支持该字段
    ],
    'wechat_pub' => [
        'appid' => '',
        'secret' => '',
    ],
    //极光推送快捷登陆公私钥
    'fast_login' => [
        'pub_key' => '-----BEGIN PUBLIC KEY-----

-----END PUBLIC KEY-----',
        'pri_key' => '-----BEGIN RSA PRIVATE KEY-----

-----END RSA PRIVATE KEY-----'
    ],
    //苹果
    'api_rsa' => [
        'pri_key' => '-----BEGIN PRIVATE KEY-----

-----END PRIVATE KEY-----',
        'pub_key' => '-----BEGIN PUBLIC KEY-----

-----END PUBLIC KEY-----'
    ],
];
