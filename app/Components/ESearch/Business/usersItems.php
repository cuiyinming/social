<?php

namespace App\Components\ESearch\Business;

use App\Components\ESearch\achieveInterface;
use App\Components\ESearch\esInterface;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\{H, HR, S};

class usersItems implements achieveInterface
{
    protected $search;
    const INDEX = 'users';
    const TYPE = 'users';
    protected $sortArr = [];

    public function __construct(esInterface $es)
    {
        $this->search = $es;
    }

    public function getAggregationsKey()
    {
        return $this->sortArr;
    }

    public function sync(array $idRange, string $action)
    {
        //先清理多余部分数据
        if ($idRange['s'] == 0 && $idRange['e'] == 0) {
            $this->_delSync();
        }
        //第一步创建索引
        if ($action == 'sync') {
            try {
                $this->putMapping();
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
                $msgTitle = "ES 更新错误";
                $msg = "### ES 更新错误提示[MOSHI-USERS] \n > " . $e->getMessage();
                (new DingTalk(env('ES_PUSH')))->sendMdMessage($msgTitle, $msg);
            }
        }
        $this->push($idRange, $action);
    }

    //同步无用的数据
    private function _delSync()
    {
        $user = UsersModel::count();
        $profile = UsersProfileModel::count();
        if ($user != $profile) {
            $arr = [];
            $userArrs = DB::select("select `id`  from soul_users where id not in (select user_id from soul_users_profile)");
            foreach ($userArrs as $userArr) {
                $arr[] = $userArr->id;
                //删除坐标和时间redis 信息
                //清理redis
                HR::delActiveTime($userArr->id);
                HR::delActiveCoordinate($userArr->id);
            }
            UsersModel::whereIn('id', $arr)->delete();
            echo '存在差异' . PHP_EOL;
        }
    }

    /**
     * 此方法主要是建立索引筛选字段
     * 并创建一个新的索引
     * keyword 用于搜索的   kkeyword 和 text 的区别是text 会进行分词
     * es 数据类型
     * byte  有符号的8位整数, 范围: [-128 ~ 127]
     * short    有符号的16位整数, 范围: [-32768 ~ 32767]
     * integer    有符号的32位整数, 范围: [−231 ~ 231-1]
     * long    有符号的64位整数, 范围: [−263 ~ 263-1]
     * float    32位单精度浮点数
     * double    64位双精度浮点数
     * half_float    16位半精度IEEE 754浮点类型
     * scaled_float    缩放类型的的浮点数, 比如price字段只需精确到分, 57.34缩放因子为100, 存储结果为5734
     */
    private function putMapping()
    {
        $arr = [];
        $arr['id'] = ['type' => 'long'];
        $arr['platform_id'] = ['type' => 'long'];
        $arr['nick'] = ['type' => 'text', 'fielddata' => true, 'analyzer' => 'ik_max_word', 'search_analyzer' => 'ik_smart'];
        $arr['ava'] = ['type' => 'byte'];
        $arr['avatar'] = ['type' => 'keyword'];
        $arr['avatar_illegal'] = ['type' => 'byte'];
        $arr['sex'] = ['type' => 'byte'];
        $arr['contact'] = ['type' => 'byte'];

        $arr['constellation'] = ['type' => 'keyword'];
        $arr['mobile'] = ['type' => 'keyword'];
        $arr['birthday'] = ['type' => 'keyword'];
        $arr['profession'] = ['type' => 'keyword'];
        $arr['stature'] = ['type' => 'keyword'];


        $arr['status'] = ['type' => 'byte'];

        $arr['live_time_latest'] = ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis'];
        $arr['live_coordinates'] = ['type' => 'geo_point'];
        $arr['live_location'] = ['type' => 'keyword'];
//        $arr['live_city'] = ['type' => 'keyword'];

        $arr['live_city'] = ['type' => 'text', 'fielddata' => true, 'analyzer' => 'ik_max_word', 'search_analyzer' => 'ik_smart'];
        $arr['vip_is'] = ['type' => 'byte'];
        $arr['vip_level'] = ['type' => 'byte'];
        $arr['real_is'] = ['type' => 'byte'];
        $arr['goddess_is'] = ['type' => 'byte'];
        $arr['identity_is'] = ['type' => 'byte'];
        $arr['super_show'] = ['type' => 'byte'];
        $arr['sound'] = ['type' => 'keyword'];
        $arr['sound_second'] = ['type' => 'byte'];
        $arr['bio'] = ['type' => 'keyword'];
        $arr['base_str'] = ['type' => 'keyword'];

        $arr['call_price'] = ['type' => 'long'];
        $arr['call_answer'] = ['type' => 'byte'];
        $arr['hide_model'] = ['type' => 'byte'];
        $arr['hide_distance'] = ['type' => 'byte'];
        $arr['hide_online'] = ['type' => 'byte'];
        $arr['under_line'] = ['type' => 'byte'];

        $arr['online'] = ['type' => 'byte'];
        $arr['created_at'] = ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis'];

        $params = [];
        $params['index'] = self::INDEX;
        if ($this->search->exists($params)) {
            $params['type'] = self::TYPE;
            $params['body'][self::TYPE]['properties'] = $arr;
            $this->search->putMapping($params);
        } else {
            $params['body']['mappings'][self::TYPE]['properties'] = $arr;
            $params['body']['settings']['max_result_window'] = self::MAX_RESULT_WINDOW;
            $this->search->createIndices($params);
        }
    }

    /**
     * @param $date
     * 推送到es上
     */
    private function push($idRange, $action)
    {
        $builder = UsersModel::orderBy('id', 'desc')->select([
            'id',
            'platform_id',
            'nick',
            'avatar',
            'avatar_illegal',

            'sex',
            'constellation',
            'mobile',
            'birthday',

            'status',
            'live_time_latest',
            'live_coordinates',
            'live_location',
            'super_show',
            'under_line',
            'online',
            'created_at',

        ]);
        if ($idRange['s'] > 0 && $idRange['e'] > 0) {
            $builder->where([['id', '>=', $idRange['s']], ['id', '<=', $idRange['e']]]);
        }
        if ($action == 'sync') {
            $builder->chunk(500, function ($result) {
                $this->_pushToEs($result);
            });
        }
        if ($action == 'single') {
            $result = $builder->get();
            foreach ($result as $user) {
                $this->_pushToEs([$user]);
            }
        }
    }

    private function _pushToEs($result)
    {
        $params = [];
        foreach ($result as $item) {
            $cooArr = $liveCooArr = [0.0, 0.0];
            $coordinates = $item->last_coordinates;
            if (!empty($coordinates)) {
                $coordinatesArr = explode(',', $coordinates);
                if (isset($coordinatesArr[0])) $cooArr[1] = floatval($coordinatesArr[0]);
                if (isset($coordinatesArr[1])) $cooArr[0] = floatval($coordinatesArr[1]);
            }
            $live_coordinates = $item->live_coordinates;
            if (!empty($live_coordinates)) {
                $liveCoordinateArr = explode(',', $live_coordinates);
                if (isset($liveCoordinateArr[0])) $liveCooArr[1] = floatval($liveCoordinateArr[0]);
                if (isset($liveCoordinateArr[1])) $liveCooArr[0] = floatval($liveCoordinateArr[1]);
            }
            $live_city = '';
            if (!empty($item->live_location)) {
                $cityArr = explode('•', $item->live_location);
                $live_city = $cityArr[0] ?? '';
            }
            $profile = $item->profile;
            $settings = $item->settings;
            $age = H::getAgeByBirthday($item->birthday);
            $base_str = $item->live_location;
            if ($item->sex != 0) $base_str .= ' | ' . ($item->sex == 1 ? '女' : '男') . '•' . $age;
            if (!empty($profile->stature)) $base_str .= ' | ' . $profile->stature;
            if (!empty($profile->profession)) $base_str .= ' | ' . $profile->profession;
            $ava = stripos($item->avatar, '/ava/') !== false ? 0 : 1;
            $arr = [];
            $arr['id'] = $item->id;
            $arr['platform_id'] = $item->platform_id;
            $arr['nick'] = $item->nick ?: '';
            $arr['ava'] = $ava;
            $arr['avatar'] = $item->avatar ?: '';
            $arr['avatar_illegal'] = $item->avatar_illegal ?: 0;
            $arr['under_line'] = $item->under_line;
            $arr['super_show'] = $item->super_show ?? 0;

            $arr['sex'] = $item->sex ?: 0;
            $arr['contact'] = (empty($profile->wechat) && empty($profile->qq)) ? 0 : 1;
            $arr['age'] = $item->birthday ? H::getAgeByBirthday($item->birthday) : 18;
            $arr['constellation'] = $item->constellation ?: '';
            $arr['mobile'] = $item->mobile ?: '';
            $arr['birthday'] = $item->birthday ?: '';

            $arr['profession'] = $profile->profession ?: '';
            $arr['stature'] = $profile->stature ?: '';

            $arr['status'] = $item->status ?: 0;

            $arr['live_time_latest'] = ($item->live_time_latest && !is_null($item->live_time_latest)) ? $item->live_time_latest : date('Y-m-d H:i:s');
            $arr['live_coordinates'] = $liveCooArr;
            $arr['live_location'] = $item->live_location ?: '';
            $arr['live_city'] = $live_city;
            $arr['vip_is'] = $profile->vip_is;
            $arr['vip_level'] = $profile->vip_level;
            $arr['real_is'] = $profile->real_is;
            $arr['goddess_is'] = $profile->goddess_is;
            $arr['identity_is'] = $profile->identity_is;
            $arr['call_price'] = $settings->call_price;
            $arr['call_answer'] = $settings->call_answer;

            $arr['hide_model'] = $settings->hide_model;
            $arr['hide_distance'] = $settings->hide_distance;
            $arr['hide_online'] = $settings->hide_online;
            $arr['sound'] = $profile->sound ? $profile->sound['url'] : '';
            $arr['sound_second'] = $profile->sound ? $profile->sound['second'] : 0;
            $arr['bio'] = $profile->bio;
            $arr['base_str'] = $base_str;

            $arr['online'] = $item->online ?: 0;
            $arr['created_at'] = !empty($item->created_at) ? $item->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            $index = [  //index 做的是全量替换操作  create 是添加操作
                '_index' => self::INDEX,
                '_type' => self::TYPE,
                '_id' => $item->id,
            ];
            /************新增******** S *****/
            $params['body'][] = ['index' => $index];
            $params['body'][] = $arr;
            /************新增******** E *****/
            /************更新******** S *****/
            //示例更新
            //$params['body'][] = ['update' => $index];
            //$params['body'][] = ['doc' => $arr];
            /************更新******** S *****/
            //在这里添加redis im 轻量化信息
            S::imInfoSet($item);
        }
        //Log::channel('debug')->info($params);
        try {
            $params && $this->search->bulk($params);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }

    //更新es数据
    public function updateBase(array $data)
    {
        //更新内容
        foreach ($data as $item) {
            //更新
            $index = [
                '_index' => self::INDEX,
                '_type' => self::TYPE,
                '_id' => $item['id'],
            ];
            $params['body'][] = ['update' => $index];
            $params['body'][] = ['doc' => $item];
            S::imInfoUpdate($item);
        }
        $params && $this->search->bulk($params);
    }

    //获取指定id 的文档
    public function getBaseById(array $item)
    {
        $index = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'id' => $item['id'],
        ];
        return $this->search->getSource($index);
    }

    //批量获取文档
    public function mgetDocById(array $item)
    {
        $index = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'body' => ['ids' => $item['ids']],
        ];
        return $this->search->mget($index);
    }

    //删除自定文档
    public function deleteDocById(array $data)
    {
        //更新内容
        foreach ($data as $item) {
            //更新
            $index = [
                'index' => self::INDEX,
                'type' => self::TYPE,
                'id' => $item['id'],
            ];
            $this->search->delete($index);
        }
    }

    public function getUserIdArr(array $params)
    {

    }

    /**
     * @param array $params
     * @return mixed
     * 拼装请求并从es中获取数据
     */
    public function getDataFromEs(array $params)
    {
        $item = $this->searchBase($params);
        return $this->search->search($item);
    }

    /**
     * @param array $params
     * @return mixed
     */
    private function searchBase(array $params)
    {
        $item['index'] = self::INDEX;
        $item['type'] = self::TYPE;
        $item['body'] = [];
        $item['body']['from'] = (($params['page'] - 1) * $params['size']);
        $item['body']['size'] = $params['size'];
        // 请求指定的字段
        $item['_source'] = [
            'id',
            'platform_id',
            'nick',
            'avatar',
            'ava',
            'avatar_illegal',
            'sex',
            'contact',

            'age',
            'constellation',
            'mobile',
            'birthday',
            'profession',

            'status',
            'stature',
            'super_show',

            'live_time_latest',
            'live_coordinates',
            'live_location',

            'vip_is',
            'vip_level',
            'real_is',
            'goddess_is',
            'identity_is',

            'sound',
            'sound_second',
            'bio',
            'call_answer',
            'call_price',
            'hide_distance',
            'hide_online',
            'online',
            'created_at',

        ];
        /***
         *   前置匹配，用于匹配前置文本，一般用于下拉框
         *   $item['body']['query']['match_phrase_prefix'] = ['title' => $params['q']];
         *   $item['body']['query']['match'] = ['title' => $params['q']];
         ***/
        //昵称搜索
        if (isset($params['q']) && !empty($params['q'])) {
            $item['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $params['q'],
                    'fields' => ['nick'],
                    'type' => 'best_fields',
                ],
//                'match_phrase' => [
//                    'title' => [
//                        'query' => $params['q'],
//                        'slop' => 1
//                    ]
//                ],
//                'wildcard' => [
//                    'title' => [
//                        'value' => '*' . $params['q'] . '*'
//                    ]
//                ],
            ];
        }

        //筛选距离
        $cooArr = [];
        if (isset($params['location']) && !empty($params['location'])) {
            $cooArr = explode(',', $params['location']);
            if (isset($cooArr[0])) $cooArr[0] = floatval($cooArr[0]);
            if (isset($cooArr[1])) $cooArr[1] = floatval($cooArr[1]);
        }

        //审核通过后删除
//        if (isset($params['sex']) && intval($params['sex']) == 1) {
//            $item['body']['query']['bool']['must'][] = ['terms' => ['id' => [187891, 187889, 187737, 187670, 187576, 187546, 187505, 187498, 187491, 187442, 187437, 187397, 187367, 187309, 187319, 187314, 187257, 187218, 187186, 187166, 187135, 187123, 187081, 187079, 187052, 187040, 187012, 187016, 186958, 186920, 186917, 186902, 186898, 186864, 186865, 186844, 186854, 186815, 186802, 186775, 186785, 186746, 186732, 186727, 186714, 186687, 186691, 186687, 186652, 186623, 186609, 186577, 186567, 186530, 186270, 186002]]];
//        } else {
//        }
        //筛选性别 0不限制
        if (isset($params['sex']) && intval($params['sex']) != 0) {
            $item['body']['query']['bool']['must'][] = ['term' => ['sex' => ['value' => $params['sex']]]];
        }
        //筛选在线
        if (isset($params['online']) && intval($params['online']) > 0) {
            $item['body']['query']['bool']['must'][] = ['term' => ['online' => ['value' => 1]]];
        }
        //筛选女神
        if (isset($params['goddess']) && intval($params['goddess']) > 0) {
            $item['body']['query']['bool']['must'][] = ['term' => ['goddess_is' => ['value' => 1]]];
        }
        //筛选同城  [因为反应说附近一直不变所以改为了同城一直可以变化的]
        if (isset($params['map']) && $params['map'] == 0) {
            if (isset($params['local']) && in_array($params['local'], [0, 1])) {
                if (isset($params['city']) && !empty($params['city'])) {
                    $city = $params['city'];
                } else {
                    $city = H::getCityByCoor();
                }
                $item['body']['query']['bool']['must'][] = [
                    'multi_match' => [
                        'query' => $city,
                        'fields' => ['live_city'],
                        'type' => 'best_fields',
                    ],
                ];
                //$item['body']['query']['bool']['must'][] = ['term' => ['live_city' => ['value' => $city]]];
            }
            //file_put_contents('/tmp/xxxxxx.log', print_r($params, 1) . PHP_EOL, 8);
        }


        //筛选vip人员
        if (isset($params['vip_is']) && intval($params['vip_is']) != 2) {
            $item['body']['query']['bool']['must'][] = ['term' => ['vip_is' => ['value' => $params['vip_is']]]];
        }
        //筛选实名认证人员
        if (isset($params['identity_is']) && intval($params['identity_is']) != 2) {
            $item['body']['query']['bool']['must'][] = ['term' => ['identity_is' => ['value' => $params['identity_is']]]];
        }
        //真人
        if (isset($params['real_is']) && intval($params['real_is']) != 2) {
            $item['body']['query']['bool']['must'][] = ['term' => ['real_is' => ['value' => $params['real_is']]]];
        }
        if (isset($params['constellation']) && !empty($params['constellation'])) {
            $item['body']['query']['bool']['must'][] = ['term' => ['constellation' => ['value' => $params['constellation']]]];
        }
        //排除用户
        if (isset($params['exclusion']) && !empty($params['exclusion'])) {
            $item['body']['query']['bool']['must_not'][] = ['terms' => ['id' => $params['exclusion']]];
        }
        //必须包含用户
        if (isset($params['must_have']) && !empty($params['must_have'])) {
            $item['body']['query']['bool']['must'][] = ['terms' => ['id' => $params['must_have']]];
        }


        //隐身用户强制隐藏 & 资料未完善强制隐藏 & 相册为空隐藏  & 头像不完善不推荐
        if (isset($params['from']) && $params['from'] != 'focus') {
            $item['body']['query']['bool']['must'][] = ['term' => ['ava' => ['value' => 1]]];
        }
        $item['body']['query']['bool']['must'][] = ['term' => ['status' => ['value' => 1]]];
        $item['body']['query']['bool']['must'][] = ['term' => ['hide_model' => ['value' => 0]]];
        $item['body']['query']['bool']['must'][] = ['term' => ['avatar_illegal' => ['value' => 0]]];
        //筛选首页不做推荐的人
        if (isset($params['from']) && $params['from'] == 'index') {
            $item['body']['query']['bool']['must'][] = ['term' => ['under_line' => ['value' => 1]]];
        }
        if (isset($params['from']) && $params['from'] == 'date') {
            $item['body']['query']['bool']['filter'][0]['range']['call_price']['gte'] = 10;
            $item['body']['query']['bool']['must'][] = ['term' => ['call_answer' => ['value' => 1]]];
        }
        //筛选年龄区间
        if (isset($params['age_start']) && intval($params['age_start']) >= 18) {
            $item['body']['query']['bool']['filter'][0]['range']['age']['gte'] = $params['age_start'];
        }
        if (isset($params['age_end']) && intval($params['age_end']) > 0) {
            $item['body']['query']['bool']['filter'][0]['range']['age']['lte'] = $params['age_end'];
        }
        if (isset($params['distance']) && intval($params['distance']) > 0 && count($cooArr) > 1) {
            //距离倒序搜索 [实时坐标位置]
            $item['body']['query']['bool']['filter'][] = [
                'geo_distance' => [
                    'distance' => $params['distance'] . 'km',
                    'live_coordinates' => [
                        'lat' => $cooArr[0],
                        'lon' => $cooArr[1],
                    ]
                ]
            ];
        }

        //这里添加自定义排序
        if ((isset($params['sort']) && count($params['sort']) > 0) || (isset($params['distance']) && intval($params['distance']) > 0)) {
            //距离排序 [实时坐标] 当同城为0时才按照距离排序
            if (count($cooArr) > 1 && isset($params['local']) && $params['local'] == 0) {
//                //这里强制加上了距离排序默认吧女神放前面---S
//                $params['sort'][] = [
//                    'goddess' => [
//                        'order' => 'desc',
//                    ]
//                ];
                //这里强制加上了距离排序默认吧女神放前面---E

                $params['sort'][] = [
                    '_geo_distance' => [
                        'live_coordinates' => [
                            'lat' => $cooArr[0],
                            'lon' => $cooArr[1]
                        ],
                        'order' => 'asc',
                        'unit' => 'km',
                        'distance_type' => 'plane'
                    ]
                ];
            }
            //档位同城时进行随机返回同城数据
            if (isset($params['local']) && (int)$params['local'] == 1) {
                //如果是带排序的则加上排序并按照权重进行倒序
                $params['sort'][] = [
                    '_score' => [
                        'order' => 'desc'
                    ]
                ];
                $params['sort'][] = [
                    '_script' => [
                        "script" => "Math.random()",//随机排序
                        "type" => "number",
                        "order" => "asc"
                    ]
                ];
            }
            //进行排序
            $item['body']['sort'] = $params['sort'];
        }
//        file_put_contents('/tmp/testss.log', print_r($item, 1) . PHP_EOL, FILE_APPEND);
        return $item;
    }


}
