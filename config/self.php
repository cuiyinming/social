<?php
// 1 完善资料页面，
// 2 实名认证
// 3 更换头像
// 4 语音签名
// 5 指定话题列表
// 6 真人认证
// 7 跳转VIP页面
// 8 关注 -> 首页列表
// 9 动态评论 -> 动态首页
// 10 完善相册 -> 相册编辑页面
// 11 录音签名 -> 语音签名页面
// 12 首冲奖励 -> 充值页面
// 13 每日动态奖励 -> 发动态
// 14 私信聊天 -> 首页列表
// 15 语音通话 ->  首页列表
// 16 女神认证
// 17 每日签到 --> 任务列表
// 18 跳转完善QQ
// 19 跳转完善微信
// 20 邀请好友
// 21 跳转的动态详情 需要传递id
// 22 跳转他人主页详情 需要传递id
// 23 跳转话题专题详情 需要传递id
// 24 消息列表页 如果传id则跳转详情页
return [
    'profession' => [
        [
            'perfix' => '销',
            'color' => '#FF1493',
            'profess' => [
                'main' => '销售/业务',
                'sub' => [
                    '销售/业务',
                    '销售',
                    '业务员',
                    '业务跟单',
                    '市场拓展',
                    '销售管理'
                ],
            ],
        ],
        [
            'perfix' => '人',
            'color' => '#FF1493',
            'profess' => [
                'main' => '人事/行政',
                'sub' => [
                    '客服',
                    '前台',
                    '文员',
                    '秘书',
                    '行政助理',
                    '人事行政',
                    '行政管理'
                ],
            ],
        ],
        [
            'perfix' => '生',
            'color' => '#FF1493',
            'profess' => [
                'main' => '生产贸易',
                'sub' => [
                    '普工',
                    '技工',
                    '质检',
                    '物料管',
                    '生产管理',
                    '副厂长',
                    '厂长',
                    '外贸',
                    '跨境电商',
                    '采购',
                    '贸易',
                    '批发商',
                ],
            ],
        ],
        [
            'perfix' => '物',
            'color' => '#FF1493',
            'profess' => [
                'main' => '物流运输',
                'sub' => [
                    '快递员',
                    '送货员',
                    '司机',
                    '物流专员',
                    '仓储管理',
                    '物流管理',
                    '驾校教练',
                    '乘务员',
                    '空乘',
                    '飞行员',
                    '长途货运',
                    '海运',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '服',
            'color' => '#FF1493',
            'profess' => [
                'main' => '服务业',
                'sub' => [
                    '服务员',
                    '收银员',
                    '送餐员',
                    '餐饮管理',
                    '酒店管理',
                    '厨师',
                    '厨工',
                    '咖啡师',
                    '店员',
                    '导购',
                    '销售员',
                    '店长',
                    '导游',
                    '美容师',
                    '化妆师',
                    '美甲师',
                    '美发师',
                    '健身教练',
                    '技师',
                    '家政服务',
                    '婚庆服务',
                    '其他'
                ],
            ],
        ],
        [
            'perfix' => '个',
            'color' => '#FF1493',
            'profess' => [
                'main' => '个体经营',
                'sub' => [
                    '个体经营',
                    '私营企业主',
                    '经销商',
                    '网店店主',
                    '其他'
                ],
            ],
        ],
        [
            'perfix' => '高',
            'color' => '#FF1493',
            'profess' => [
                'main' => '高级管理',
                'sub' => [
                    '总经理',
                    '副总经理',
                    '中层管理',
                    '合伙人',
                    '企业高管',
                    '公司董事',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '金',
            'color' => '#FF1493',
            'profess' => [
                'main' => '金融/投资/保险',
                'sub' => [
                    '投资人',
                    '股票基金',
                    '金融证券',
                    '期货外汇',
                    '保险从业',
                    '银行从业',
                    '拍卖担保',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '建',
            'color' => '#FF1493',
            'profess' => [
                'main' => '建筑/房产',
                'sub' => [
                    '装修装潢',
                    '房产经纪人',
                    '置业顾问',
                    '建筑师',
                    '房产开发',
                    '房产策划',
                    '工程管理',
                    '工程承包',
                    '工程造价',
                    '物业管理',
                    '物业招商',
                    '物业保安',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '影',
            'color' => '#FF1493',
            'profess' => [
                'main' => '影视/传媒',
                'sub' => [
                    '演员',
                    '模特',
                    '经纪人',
                    '主持人',
                    '主播',
                    '导演',
                    '摄影师',
                    '影视制作',
                    '制片',
                    '影视从业者',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '广',
            'color' => '#FF1493',
            'profess' => [
                'main' => '广告/公关',
                'sub' => [
                    '市场营销',
                    '市场推广',
                    '公关媒介',
                    '品牌策划',
                    '设计师',
                    '动效师',
                    '广告从业者',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '艺',
            'color' => '#FF1493',
            'profess' => [
                'main' => '艺术/媒体',
                'sub' => [
                    '主编',
                    '作家',
                    '撰稿人',
                    '文案',
                    '记着',
                    '出版发行',
                    '音乐',
                    '舞蹈',
                    '绘画',
                    '艺术收藏',
                    '传媒从业者',
                ],
            ],
        ],
        [
            'perfix' => '医',
            'color' => '#FF1493',
            'profess' => [
                'main' => '医疗/生物/制药',
                'sub' => [
                    '医生',
                    '护士',
                    '药剂师',
                    '营养师',
                    '兽医',
                    '医疗管理',
                    '医疗器械',
                    '生物工程',
                    '医疗从业者',
                    '制药从业者',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '法',
            'color' => '#FF1493',
            'profess' => [
                'main' => '法律/财务/咨询',
                'sub' => [
                    '财务',
                    '会计师',
                    '审计',
                    '律师',
                    '法务',
                    '翻译',
                    '心理咨询师',
                    '顾问',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '通',
            'color' => '#FF1493',
            'profess' => [
                'main' => '通讯/互联网',
                'sub' => [
                    'IT工程师',
                    '程序员',
                    '测试工程师',
                    '运维工程师',
                    '互联网运营',
                    'IT技术管理',
                    '项目管理',
                    'UI设计',
                    '产品经理',
                    '网络推广',
                    '通讯工程师',
                    '通信运维',
                    '电子工程师',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '教',
            'color' => '#FF1493',
            'profess' => [
                'main' => '教育/培训',
                'sub' => [
                    '教师',
                    '幼师',
                    '培训师',
                    '讲师',
                    '助教',
                    '教务管理',
                    '大学教授',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '政',
            'color' => '#FF1493',
            'profess' => [
                'main' => '政企/单位',
                'sub' => [
                    '公务员',
                    '军人',
                    '警察',
                    '单位管理',
                    '政企从业者',
                    '科研管理',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '校',
            'color' => '#FF1493',
            'profess' => [
                'main' => '在校学生',
                'sub' => [
                    '在校学生',
                    '其他',
                ],
            ],
        ],
        [
            'perfix' => '农',
            'color' => '#FF1493',
            'profess' => [
                'main' => '农林牧渔',
                'sub' => [
                    '花艺师',
                    '园艺师',
                    '种植业',
                    '养殖业',
                    '林木业',
                    '渔业',
                    '农场主',
                    '农民',
                    '其他',
                ],
            ],
        ]
    ],
    'options' => [
        'stature' => [
            'key' => '身高',
            'list' => ['150cm', '151cm', '152cm', '153cm', '154cm', '155cm', '156cm', '157cm', '158cm', '159cm', '160cm', '161cm', '162cm', '163cm', '164cm', '165cm', '166cm', '167cm', '168cm', '169cm', '170cm', '171cm', '172cm', '173cm', '174cm', '175cm', '176cm', '177cm', '178cm', '179cm', '180cm', '181cm', '182cm', '183cm', '184cm', '185cm', '186cm', '187cm', '188cm', '189cm', '190cm', '191cm', '192cm', '193cm', '194cm', '195cm', '196cm', '197cm', '198cm', '199cm', '200cm', '201cm', '202cm', '203cm', '204cm', '205cm', '206cm', '207cm', '208cm', '209cm', '210cm'],
            'max' => 1,
            'min' => 1,
        ],
        'weight' => [
            'key' => '体重',
            'list' => ["35kg以下", "36kg", "37kg", "38kg", "39kg", "40kg", "41kg", "42kg", "43kg", "44kg", "45kg", "46kg", "47kg", "48kg", "49kg", "50kg", "51kg", "52kg", "53kg", "54kg", "55kg", "56kg", "57kg", "58kg", "59kg", "60kg", "61kg", "62kg", "63kg", "64kg", "65kg", "66kg", "67kg", "68kg", "69kg", "70kg", "71kg", "72kg", "73kg", "74kg", "75kg", "76kg", "77kg", "78kg", "79kg", "80kg", "81kg", "82kg", "83kg", "84kg", "85kg", "86kg", "87kg", "88kg", "89kg", "90kg", "91kg", "92kg", "93kg", "94kg", "95kg", "96kg", "97kg", "98kg", "99kg", "100kg", "100kg以上"],
            'max' => 1,
            'min' => 1,
        ],
        'somatotype' => [
            'key' => '体型',
            'list' => ["保密", "匀称", "瘦长", "运动员型", "较胖", "魁梧"],
            'max' => 1,
            'min' => 1,
        ],
        'charm' => [
            'key' => '魅力部位',
            'list' => ["保密", "笑容", "眼睛", "头发", "鼻子", "嘴唇", "牙齿", "颈部", "耳朵", "手", "胳膊", "胸部", "腰部", "臀部", "腿部", "脚"],
            'max' => 1,
            'min' => 1,
        ],
        'salary' => [
            'key' => '收入水平',
            'list' => ["保密", "5万以下", "5万~10万", "10万~20万", "20万~30万", "30万~50万", "50万~100万", "100万以上"],
            'max' => 1,
            'min' => 1,
        ],
        'marriage' => [
            'key' => '情感状态',
            'list' => ["单身", "恋爱中", "离异", "丧偶", "已婚", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'degree' => [
            'key' => '学历',
            'list' => ["初中", "中专", "高中", "大专", "本科", "双学士", "硕士", "博士"],
            'max' => 1,
            'min' => 1,
        ],
        'house' => [
            'key' => '住房状况',
            'list' => ["自购房", "租房", "宿舍", "合租", "父母同住", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'cohabitation' => [
            'key' => '婚前同居',
            'list' => ["是", "否", "不确定", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'dating' => [
            'key' => '接受约会',
            'list' => ["是", "否", "不确定", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'purchase_house' => [
            'key' => '购房状况',
            'list' => ["已购房", "暂未购房", "计划中", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'purchase_car' => [
            'key' => '购车状况',
            'list' => ["已购车（豪华型）", "已购车（中档）", "已购车（经济型）", "暂未购车", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'drink' => [
            'key' => '是否饮酒',
            'list' => ["从不", "偶尔", "经常", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'smoke' => [
            'key' => '是否抽烟',
            'list' => ["从不", "偶尔", "经常", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'cook' => [
            'key' => '厨艺水平',
            'list' => ["从不", "偶尔", "经常", "保密"],
            'max' => 1,
            'min' => 1,
        ],
        'relationship' => [
            'key' => '期待关系',
            'list' => ["短期", "长期", "结婚", "好友"],
            'max' => 1,
            'min' => 1,
        ],
        'expect_stature' => [
            'key' => '身高',
            'list' => ['150cm及以上', '160cm及以上', '170cm及以上', '180cm及以上', '190cm及以上', '不限'],
            'max' => 1,
            'min' => 1,
        ],
        'expect_age' => [
            'key' => '年龄',
            'list' => ['18-25岁', '20-25岁', '25-30岁', '30-40岁', '40-50岁', '50-60岁', '60岁以上', '不限'],
            'max' => 1,
            'min' => 1,
        ],
        'expect_degree' => [
            'key' => '学历',
            'list' => ["不限", "初中及以上", "中专及以上", "高中及以上", "大专及以上", "本科及以上", "双学士及以上", "硕士及以上", "博士"],
            'max' => 1,
            'min' => 1,
        ],
        'expect_salary' => [
            'key' => '收入',
            'list' => ["不限", "5万以上", "10万以上", "20万以上", "30万以上", "50万以上", "100万以上"],
            'max' => 1,
            'min' => 1,
        ],
        'tags' => [
            'key' => '我的标签',
            'list' => ["萌萌哒", "强迫症", "拖延症", "极品吃货", "叫我逗比", "双重人格", "喜欢简单", "敢爱敢恨", "选择恐惧症", "宅", "文艺", "靠谱", "局气", "厚道", "有面儿", "讲义气", "女友永远是对的", "马甲线", "安静", "健谈", "随性", "叛逆", "热血", "理想主义", "吃货", "隔壁老王", "呆", "小鲜肉", "追风少年", "逗比", "老实孩子", "乐观主义", "吹牛", "爱哭", "酒鬼", "坚强", "嘴碎", "神道", "卖萌", "冒险王", "匪夷所思", "本命年", "泡吧", "奋斗", "颜控", "喵控", "声控", "学霸", "咸鱼", "工具人", "柠檬精", "沙雕", "海王", "社会人士", "精神小伙", "外冷内热", "有责任心", "斯文内敛", "温和", "闷骚", "爱照顾人", "细节控", "知书达理", "直率", "慢热", "人来疯", "开朗积极", "独立", "傲娇", "完美主义", "善解人意", "爽快", "偏保守", "段子手", "比较乖", "待人热情", "善良温柔", "敏感", "朴素憨厚", "真诚靠谱", "谦虚自律", "稳重厚实", "大方直爽", "乐观自信", "严谨细心", "表里如一", "心灵手巧", "心直口快", "调皮可爱", "佛系", "没心机", "处事洒脱", "有耐心", "有爱心", "心很大", "务实靠谱", "气质型"],
            'default_color' => '#eeeeee',
            'select_color' => '#e0c5fa',
            'max' => 6,
            'min' => 2
        ],
        'hobby_sport' => [
            'key' => '喜欢的运动',
            'list' => ["游泳", "跑步", "单车", "瑜伽", "篮球", "足球", "滑板", "乒乓球", "羽毛球", "网球", "高尔夫", "台球", "舞蹈", "街舞", "健身房", "射箭", "击剑", "拳击", "跆拳道", "爬山", "棒球", "自行车", "武术", "电子竞技"],
            'default_color' => '#eeeeee',
            'select_color' => '#74c6f6',
            'max' => 6,
            'min' => 2,
        ],
        'hobby_music' => [
            'key' => '喜欢的音乐',
            'list' => ["欧美", "日韩", "流行", "摇滚", "电子", "嘻哈", "爵士", "布鲁斯", "金属", "轻音乐", "古典", "乡村", "校园民谣", "六十年代经典", "八十年代经典", "王菲", "周杰伦", "陈奕迅", "王力宏", "萧敬腾", "五月天", "苏打绿", "华晨宇", "蔡徐坤"],
            'default_color' => '#eeeeee',
            'select_color' => '#dafcfd',
            'max' => 6,
            'min' => 2,
        ],
        'hobby_food' => [
            'key' => '喜欢的美食',
            'list' => ["北京烤鸭", "港式早茶", "火锅", "烤串", "麻辣香锅", "麻小", "生煎包", "卤肉饭", "寿司", "生鱼片", "日本拉面", "日式铁板烧", "石锅拌饭", "泰国菜", "牛排", "意大利面", "披萨", "汉堡", "薯条", "美式炸鸡", "土耳其烤肉", "素食", "提拉米苏", "慕斯蛋糕", "奶酪", "巧克力", "冰淇淋"],
            'default_color' => '#eeeeee',
            'select_color' => '#edfde0',
            'max' => 6,
            'min' => 2,
        ],
        'hobby_movie' => [
            'key' => '喜欢的电影',
            'list' => ["动作电影", "奇幻电影", "喜剧电影", "恐怖电影", "冒险电影", "爱情电影", "警匪电影", "科幻电影", "战争电影", "灾难电影", "温情电影", "史诗电影", "实验电影", "微电影", "微动画电影", "悬疑电影", "音乐电影", "黑帮电影", "纪录电影", "公路电影", "意识流电影", "动画电影", "惊悚电影", "西部电影", "人物电影", "飞车电影", "家庭电影", "超级英雄电影"],
            'default_color' => '#eeeeee',
            'select_color' => '#db81f3',
            'max' => 6,
            'min' => 2,
        ],
        'hobby_book' => [
            'key' => '喜欢的阅读',
            'list' => ["小说", "散文", "诗歌", "青春文学", "传记", "书法", "摄影", "绘画", "音乐", "艺术", "励志与成功", "管理", "经济", "金融与投资", "旅游", "烹饪", "美妆", "家居", "国产动漫", "日韩动漫", "欧美动漫", "迪士尼", "漫威", "宫崎骏", "新海诚", "剧场动画"],
            'default_color' => '#eeeeee',
            'select_color' => '#ccb7fa',
            'max' => 6,
            'min' => 2,
        ],
        'hobby_footprint' => [
            'key' => '喜欢城市',
            'list' => ["成都", "桂林", "三亚", "丽江", "大理", "香格里拉", "西藏", "鼓浪屿", "张家界", "九寨沟", "台湾", "意大利", "西班牙", "荷兰", "比利时", "瑞士", "丹麦", "芬兰", "捷克", "古巴", "阿根延", "日本", "北海道", "韩国", "巴厘岛", "普吉岛", "长滩岛", "塞班岛", "新加坡", "马来西亚", "泰国", "菲律宾", "俄罗斯", "埃及", "印度尼西亚", "印度", "越南", "尼泊尔", "迪拜", "土耳其", "希腊", "美国", "加拿大", "澳大利亚", "英国", "法国"],
            'default_color' => '#eeeeee',
            'select_color' => '#7b72cf',
            'max' => 6,
            'min' => 2,
        ],
    ],
    'sounds' => [
        'max_time' => 60,
        'min_time' => 5,
        'bottom_slogan' => '录制一段5~60s的录音',
        'slogans' => [],
    ],
    'check_list' => [
        1 => [
            'title' => '',
            'cont' => '',
            'type' => 'notice',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        2 => [
            'title' => "有人浏览了您的主页",
            'cont' => "（ %s ）刚刚浏览了您的主页，快和他聊聊吧！",
            'type' => 'browse_notice',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 22, // 22 跳转他人主页详情 需要传递id
            ],

        ],
        3 => [
            'title' => "有人关注了您",
            'cont' => "您有1个新关注：( %s ) 刚刚关注了您哦。",
            'type' => 'follow_notice',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 22, // 22 跳转他人主页详情 需要传递id
            ],
        ],
        4 => [
            'title' => "有人点赞了您的动态",
            'cont' => "（ %s ）刚刚点赞了您的动态",
            'type' => 'zan_notice',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 21, // 21 跳转的动态详情 需要传递id
            ],
        ],
        5 => [
            'title' => "有人评论了您的动态",
            'cont' => "（ %s ）刚刚评论了您的动态",
            'type' => 'cmt_notice',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 21, // 21 跳转的动态详情 需要传递id
            ],
        ],
        6 => [
            'title' => "头像审核通知",
            'cont' => "抱歉，您的头像审核未通过，请修改后重新提交",
            'type' => 'avatar_check',
            'scheme' => [
                'title' => '头像设置',
                'button' => '立即更改',
                'scheme' => 3, // 3 更换头像
            ],
        ],
        7 => [
            'title' => "视频审核通知",
            'cont' => "抱歉，您的视频审核未通过，请修改后重新提交",
            'type' => 'video_check',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        8 => [
            'title' => "联系方式审核通知",
            'cont' => "抱歉，您的微信或QQ审核未通过，请修改后重新提交",
            'type' => 'contact_check',
            'scheme' => [
                'title' => '联系方式设置',
                'button' => '立即设置',
                'scheme' => 19, // 19 跳转完善微信
            ],
        ],
        9 => [
            'title' => "相册审核通知",
            'cont' => "抱歉，您的相册照片审核未通过，请修改后重新提交",
            'type' => 'album_lock',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 10,
            ],
        ],
        10 => [
            'title' => "封号通知",
            'cont' => "抱歉，您的账号因违规已被禁用",
            'type' => 'account_lock',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        11 => [
            'title' => "签名违规通知",
            'cont' => "抱歉，您的签名因存在违规内容，审核未通过，请修改后重新提交",
            'type' => 'bio_check',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 1,
            ],
        ],
        12 => [
            'title' => "女神认证通知",
            'cont' => "恭喜，您的女神认证已经通过",
            'type' => 'goddess_check_success',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        13 => [
            'title' => "联系方式被解锁通知",
            'cont' => "（ %s ）解锁了您的联系方式，快去查看下吧",
            'type' => 'contact_unlock',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 22, // 22 跳转他人主页详情 需要传递id
            ],
        ],
        14 => [
            'title' => "女神认证通知",
            'cont' => "抱歉，您的女神认证未通过",
            'type' => 'goddess_check_fail',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 16,
            ],
        ],
        15 => [
            'title' => "语音签名审核通知",
            'cont' => "抱歉，您的语音签名审核未能通过，请修改后重新提交",
            'type' => 'sound_check',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 4, // 4 语音签名
            ],
        ],
        16 => [
            'title' => "VIP订单已提交",
            'cont' => "您的VIP购买订单已经成功提交，系统正在处理，请稍等...",
            'type' => 'vip_buy_start',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        17 => [
            'title' => "VIP订单处理完成",
            'cont' => "您的VIP购买订单已经处理完成，请注意VIP权益变化。",
            'type' => 'vip_buy_end',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        18 => [
            'title' => "友币购买订单已提交",
            'cont' => "您的友币购买订单已经成功提交，系统正在处理，请稍等...",
            'type' => 'inner_buy_start',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        19 => [
            'title' => "友币购买订单处理完成",
            'cont' => "您购买的友币%s颗已经充值成功，请注意友币数量变化。",
            'type' => 'inner_buy_end',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 0,
            ],
        ],
        20 => [
            'title' => "QQ号码审核失败通知",
            'cont' => "抱歉！您填写的QQ联系方式错误，请修改后重新提交。",
            'type' => 'check_qq',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 18, // 18 跳转完善QQ
            ],
        ],
        21 => [
            'title' => "微信号审核失败通知",
            'cont' => "抱歉！您填写的微信号码存在错误，无法被查找或添加好友，请修改后重新提交",
            'type' => 'check_wechat',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 19, // 19 跳转完善微信
            ],
        ],
        22 => [
            'title' => "签到啦",
            'cont' => "每日签到获取金币、免费解锁小姐姐社交联系方式！",
            'type' => 'sign_notice',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 17, // 17 每日签到 --> 任务列表
            ],
        ],
        23 => [
            'title' => "设置个人头像",
            'cont' => "设置您的真实头像，有助于我们向更多人推荐您的资料信息，曝光率平均增加200%，让您更快找到心仪对象。",
            'type' => 'avatar_cmp',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 3,  // 3 更换头像
            ],
        ],
        24 => [
            'title' => "真人认证邀请",
            'cont' => "您的好友 %s 邀请您完成真人认证，认证后预计曝光及好友数量将提高200%%。",
            'type' => 'invite_auth',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 6, // 6 真人认证
            ],
        ],
        25 => [
            'title' => "联系方式完善邀请",
            'cont' => "您的好友 %s 邀请您完善下您的联系方式，他人解锁联系方式您可得到友币及现金奖励。",
            'type' => 'invite_contact',
            'scheme' => [
                'title' => '立即查看',
                'button' => '立即查看',
                'scheme' => 1, // 1 完善资料页面
            ],
        ],
    ],
    'color_list' => [
        '#000000',
        '#A123021',
        '#3F23021',
        '#01F3021',
        '#712F021',
        '#51230F1',
        '#912302F',
        '#212D021',
        '#11D3021',
        '#B123A21',
        '#01C3021',
        '#01A3021',
        '#CC2A021',
    ],
    'reward_list' => [
        'new' => [
            [
                'name' => 'guanzhu',
                'jump_scheme' => 8,
                'title' => '关注',
                'desc' => '关注3个感兴趣的人',
                'reward' => 10,
                'reward_str' => '+10友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/gztr.png'
            ], [
                'name' => 'pinglun',
                'jump_scheme' => 9,
                'title' => '动态评论',
                'desc' => '给3个好友动态评论',
                'reward' => 10,
                'reward_str' => '+10友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/dtpl.png'
            ], [
                'name' => 'touxiang',
                'jump_scheme' => 1,
                'title' => '上传头像',
                'desc' => '把你最美的照片用作头像吧',
                'reward' => 10,
                'reward_str' => '+10友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/sctx.png'
            ], [
                'name' => 'xiangce',
                'jump_scheme' => 10,
                'title' => '完善相册',
                'desc' => '上传至少4张你的魅力照片吧',
                'reward' => 15,
                'reward_str' => '+15友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/wsxc.png'
            ], [
                'name' => 'ziliao',
                'jump_scheme' => 1,
                'title' => '完善资料',
                'desc' => '完善个人资料，标签及爱好',
                'reward' => 15,
                'reward_str' => '+15友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/wszl.png'
            ], [
                'name' => 'yuyinqianming',
                'jump_scheme' => 4,
                'title' => '语音签名',
                'desc' => '用心录制一段你的语音签名吧',
                'reward' => 15,
                'reward_str' => '+15友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/lyqm.png'
            ], [
                'name' => 'zhenrenrenzheng',
                'jump_scheme' => 6,
                'title' => '真人认证',
                'desc' => '官方认证，获取更多展示特权',
                'reward' => 30,
                'reward_str' => '+30友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/zrrz.png'
            ], [
                'name' => 'shimingrenzheng',
                'jump_scheme' => 2,
                'title' => '实名认证',
                'desc' => '官方认证，获取更多展示特权',
                'reward' => 30,
                'reward_str' => '+30友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/smrz1.png'
            ],
//            [
//                'name' => 'nvshenrenzheng',
//                'jump_scheme' => 16,
//                'title' => '女神认证',
//                'desc' => '获取女神认证，让更多的人找到你',
//                'reward' => 5,
//                'reward_str' => '+5友币',
//                'finish' => false,
//                'icon_url' => 'http://static.hfriend.cn/vips/task/nsrz1.png'
//            ],
            [
                'name' => 'shouhunvshen',
                'jump_scheme' => 14,
                'title' => '守护女生',
                'desc' => '守护一个你心仪的女生',
                'reward' => 30,
                'reward_str' => '+30友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/shns.png'
            ],
//            [
//                'name' => 'yuyinsupei',
//                'jump_scheme' => 14,
//                'title' => '语音速配',
//                'desc' => '与缘分小姐姐语音聊天',
//                'reward' => 5,
//                'reward_str' => '+10友币',
//                'finish' => false,
//                'icon_url' => 'http://static.hfriend.cn/vips/task/yysp.png'
//            ],
            //[
            //    'name' => 'shipinsupei',
            //    'jump_scheme' => 14,
            //    'title' => '视频速配',
            //    'desc' => '与缘分小姐姐视频聊天',
            //    'reward' => 10,
            //    'reward_str' => '+10友币',
            //    'finish' => false,
            //    'icon_url' => 'http://static.hfriend.cn/vips/task/spth.png'
            //],
//            [
//                'name' => 'shouchongjiangli',
//                'jump_scheme' => 12,
//                'title' => '首充奖励',
//                'desc' => '首充8元，奖励骑士VIP2天',
//                'reward' => 0,
//                'reward_str' => 'vip2天试用',
//                'finish' => false,
//                'icon_url' => 'http://static.hfriend.cn/vips/task/scjl.png'
//            ],
        ],
        'normal' => [
            [
                'name' => 'meiriqiandao',
                'jump_scheme' => 17,
                'title' => '每日签到',
                'desc' => '签到得友币，并有神秘礼物随机赠送',
                'reward' => 0,
                'reward_str' => '最高得155友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/mrqd.png'
            ], [
                'name' => 'meiridongtai',
                'jump_scheme' => 13,
                'title' => '每日动态',
                'desc' => '每日发动态，奖励随机红包',
                'reward' => 10,
                'reward_str' => '+10友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/fbdt.png'
            ], [
                'name' => 'meiridashan',
                'jump_scheme' => 14,
                'title' => '每日搭讪',
                'desc' => '每日搭讪自己心仪的人，获取不定额友币',
                'reward' => 5,
                'reward_str' => '+5友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/dsmn.png'
            ], [
                'name' => 'sixinliaotian',
                'jump_scheme' => 14,
                'title' => '私信聊天',
                'desc' => '文字私信自己喜欢的人，获取聊天红包奖励',
                'reward' => 5,
                'reward_str' => '最高138友币',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/sxlt.png'
            ], [
                'name' => 'yaoqinghaoyou',
                'jump_scheme' => 20,
                'title' => '邀请好友',
                'desc' => '邀请好友注册，享用户充值长期收益',
                'reward' => 0,
                'reward_str' => '最高月收3.6万元',
                'finish' => false,
                'icon_url' => 'http://static.hfriend.cn/vips/task/yqhy.png'
            ],
//            [
//                'name' => 'yuyintonghua',
//                'jump_scheme' => 15,
//                'title' => '语音通话',
//                'desc' => '与喜欢的人语音通话，每日享通话红包',
//                'reward' => 8,
//                'reward_str' => '最高58友币',
//                'finish' => false,
//                'icon_url' => 'http://static.hfriend.cn/vips/task/yysp.png'
//            ],
            //[
            //    'name' => 'shipintonghua',
            //    'jump_scheme' => 15,
            //    'title' => '视频通话',
            //    'desc' => '与喜欢的人视频，每日享视频红包',
            //    'reward' => '视频红包',
            //    'reward_str' => '最高138友币',
            //    'finish' => false,
            //    'icon_url' => 'http://static.hfriend.cn/vips/task/spth.png'
            //],
        ],
    ],
    'sweet_list' => [
        [
            'name' => '等级0:初见',
            'cont' => '亲密度',
            'unlock' => true,
            'img_unlock' => 'http://static.hfriend.cn/vips/sweet/xh5.png',
            'img_lock' => 'http://static.hfriend.cn/vips/sweet/xh5.png',
        ], [
            'name' => '等级1:相遇',
            'cont' => '图片消息',
            'unlock' => false,
            'img_unlock' => 'http://static.hfriend.cn/vips/sweet/tpxx5.png',
            'img_lock' => 'http://static.hfriend.cn/vips/sweet/tpxx0.png',
        ], [
            'name' => '等级2:相识',
            'cont' => '语音消息',
            'unlock' => false,
            'img_unlock' => 'http://static.hfriend.cn/vips/sweet/yyxx5.png',
            'img_lock' => 'http://static.hfriend.cn/vips/sweet/yyxx0.png',
        ], [
            'name' => '等级3:相伴',
            'cont' => '语音通话',
            'unlock' => false,
            'img_unlock' => 'http://static.hfriend.cn/vips/sweet/yyth5.png',
            'img_lock' => 'http://static.hfriend.cn/vips/sweet/yyth0.png',
        ], [
            'name' => '等级4:相惜',
            'cont' => '交换微信',
            'unlock' => false,
            'img_unlock' => 'http://static.hfriend.cn/vips/sweet/weixin5.png',
            'img_lock' => 'http://static.hfriend.cn/vips/sweet/weixin0.png',
        ], [
            'name' => '等级5:相知',
            'cont' => '邀约见面',
            'unlock' => false,
            'img_unlock' => 'http://static.hfriend.cn/vips/sweet/yyjm5.png',
            'img_lock' => 'http://static.hfriend.cn/vips/sweet/yyjm0.png',
        ]
    ],
    'invite_base' => [
        'title' => '每邀请一位新用户认证用户可赚',
        'base_reward' => 10,
        'extra_reward' => '8%',
    ],
    'invite_rule' => [
        [
            [
                'text' => '1.邀请好友注册，对方提现或者是充值30元，均可获得奖励10元',
                'color' => '#FF3967',
                'font' => 12,
            ], [
            'text' => '2.邀请好友注册，对方提现金额，可获得8%的提成，一劳永逸',
            'color' => '#FF3967',
            'font' => 12,
        ]
        ], [
            [
                'text' => '现金奖励举例',
                'color' => '#191919',
                'font' => 14,
            ]
        ], [
            [
                'text' => '你邀请的好友提现了30元',
                'color' => '#191919',
                'font' => 11,
            ], [
                'text' => '你获得10元奖励',
                'color' => '#FF3967',
                'font' => 11,
            ], [
                'text' => '你邀请的好友充值了30元',
                'color' => '#191919',
                'font' => 11,
            ], [
                'text' => '你获得10元奖励',
                'color' => '#FF3967',
                'font' => 11,
            ], [
                'text' => '你邀请的好友收到100000友币的私信礼物并提现3400元',
                'color' => '#191919',
                'font' => 11,
            ], [
                'text' => '你获得272奖励可提现',
                'color' => '#FF3967',
                'font' => 11,
            ],
        ], [
            [
                'text' => '特殊说明：提现类型为互动奖励提现，所有现金奖励系统会以心钻形式发放到您的账户，您可以在收到后进行提现',
                'color' => '#999999',
                'font' => 12,
            ]
        ],
    ],
    'reward_tips' => [
        'gift_tips' => [
            'title' => '社区礼物：',
            'desc' => '对方赠送礼物的固定比例%s进行结算',
        ],
        'send_msg' => [
            'title' => '守护礼物：',
            'desc' => '当用户收到守护礼物（即搭讪/付费消息/消息红包等）时，可获得该部分付费消息友币价值的%s作为奖励，本奖励仅限女生领取。',
        ],
//        'contact' => [
//            'title' => '联系方式解锁奖励：',
//            'desc' => '获得联系方式解锁价格%s的心钻收益',
//        ],
        'contact' => [
            'title' => '邀请好友不定期奖励：',
            'desc' => '邀请好友不定期获取随机时长VIP奖励',
        ],
        'ext_tips' => [
            'title' => '特殊说明：',
            'desc' => '所有结算均以心钻形式发放。
未进行真人认证的用户，付费消息/社区礼物/联系方式解锁可获得的互动奖励只有原来的一半（即按既定比例的50%执行），即对应比例的50%。真人认证后的按原来比例执行。我们鼓励您进行真人认证交友。',
        ],
    ],
    'chat_rule' => [
        '收费设置说明：',
//        '1:对方主动给您发送消息，语音呼叫，视频呼叫是否收费，是否可以对您进行呼叫，按照多少友币或者友币/分钟收费，都依据于您在本页设置的价格信息。',
//        '2:互相关注好友则私聊发送消息免费，守护成为对方的天使则语音，视频通话免费。',
        '1:随着魅力值的提高您所拥有的权限也会不断得到的提升。',
        '2:设置收费相册，当用户付费解锁您的相册后，您可获得解锁价格40%作为奖励，付费相册仅限认证会员设置'
    ],
];
