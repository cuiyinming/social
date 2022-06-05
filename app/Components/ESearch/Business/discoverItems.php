<?php

namespace App\Components\ESearch\Business;

use App\Components\ESearch\achieveInterface;
use App\Components\ESearch\esInterface;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Models\MessageModel;
use App\Http\Models\Discover\DiscoverModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\H;

class discoverItems implements achieveInterface
{
    protected $search;
    const INDEX = 'discover';
    const TYPE = 'discover';
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
        //第一步创建索引
        if ($action == 'sync') {
            try {
                $this->putMapping();
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
                $msgTitle = "ES 更新错误";
                $msg = "### ES 更新错误提示[MOSHI-DISCOVER] \n > " . $e->getMessage();
                (new DingTalk(env('ES_PUSH')))->sendMdMessage($msgTitle, $msg);
            }
        }

        $this->push($idRange);
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
        $arr['user_id'] = ['type' => 'long'];
        $arr['sex'] = ['type' => 'byte'];
        $arr['cont'] = ['type' => 'keyword'];
        $arr['cmt_on'] = ['type' => 'byte'];
        $arr['show_on'] = ['type' => 'byte'];
        $arr['status'] = ['type' => 'byte'];


        $arr['private'] = ['type' => 'byte'];
        $arr['location'] = ['type' => 'geo_point'];
        $arr['location_str'] = ['type' => 'keyword'];

        $arr['tags'] = ['type' => 'keyword'];
        $arr['album'] = ['type' => 'Array'];
        $arr['sound'] = ['type' => 'Array'];

        $arr['num_cmt'] = ['type' => 'keyword'];
        $arr['num_zan'] = ['type' => 'keyword'];
        $arr['num_view'] = ['type' => 'keyword'];

        $arr['num_recommend'] = ['type' => 'keyword'];

        $arr['num_share'] = ['type' => 'keyword'];
        $arr['num_say_hi'] = ['type' => 'keyword'];

        $arr['post_at'] = ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||epoch_millis'];
        $arr['online'] = ['type' => 'byte'];
        $arr['channel'] = ['type' => 'keyword'];
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
    private function push($idRange, $table_id = 0)
    {
        $builder = DiscoverModel::orderBy('id', 'desc')->select([
            'id',
            'user_id',
            'sex',
            'cont',
            'cmt_on',
            'show_on',
            'status',
            'private',
            'location',
            'tags',
            'album',
            'sound',
            'num_cmt',
            'num_zan',
            'num_view',
            'num_share',
            'num_say_hi',
            'post_at',
            'online',
            'channel',
            'created_at',

        ]);
        if ($idRange['s'] > 0 && $idRange['e'] > 0) {
            $builder->where([['id', '>=', $idRange['s']], ['id', '<=', $idRange['e']]]);
        }
        $builder->chunk(500, function ($result) use ($table_id) {
            $params = [];
            foreach ($result as $item) {
                $cooArr = [0.0, 0.0];
                $location_str = '';
                if (!empty($item->location)) {
                    $coordinatesArr = explode(',', $item->location['coordinate']);
                    if (isset($coordinatesArr[0])) $cooArr[1] = floatval($coordinatesArr[0]);
                    if (isset($coordinatesArr[1])) $cooArr[0] = floatval($coordinatesArr[1]);
                }
                if (!empty($item->location)) {
                    $location_str = $item->location['location'];
                }
                $arr = [];
                $arr['id'] = $item->id;
                $arr['user_id'] = $item->user_id;
                $arr['sex'] = $item->sex ? $item->sex : 1;
                $arr['cont'] = $item->cont ? $item->cont : '';
                $arr['cmt_on'] = $item->cmt_on ? $item->cmt_on : 0;
                $arr['show_on'] = $item->show_on ? $item->show_on : 0;

                $arr['status'] = $item->status ? $item->status : 1;
                $arr['private'] = $item->private ? $item->private : 0;

                $arr['location'] = $cooArr;
                $arr['location_str'] = $location_str;

                $arr['tags'] = $item->tags ? $item->tags : null;

                $arr['album'] = $item->album ? $item->album : [];
                $arr['sound'] = $item->sound ? $item->sound : [];
                $arr['num_cmt'] = $item->num_cmt ? $item->num_cmt : 0;
                $arr['num_zan'] = $item->num_zan ? $item->num_zan : 0;
                $arr['num_view'] = $item->num_view ? $item->num_view : 0;
                $arr['num_share'] = $item->num_share ? $item->num_share : 0;
                $arr['num_recommend'] = $item->num_cmt + $item->num_zan + $item->num_share;
                $arr['num_say_hi'] = $item->num_say_hi;
                $arr['online'] = $item->online;
                $arr['channel'] = $item->channel;
                $arr['created_at'] = $item->created_at && !is_null($item->created_at) ? $item->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
                $arr['post_at'] = $item->post_at && !is_null($item->post_at) ? $item->post_at : date('Y-m-d H:i:s');
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
            }
//            Log::channel('debug')->info($params);
            try {
                $params && $res = $this->search->bulk($params);
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }

        });
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
            'user_id',
            'sex',
            'cont',
            'cmt_on',
            'show_on',
            'status',
            'private',
            'location',
            'tags',
            'album',
            'sound',
            'num_cmt',
            'num_zan',
            'num_view',
            'num_share',
            'num_say_hi',
            'post_at',
            'online',
            'channel',
            'created_at',
            'num_recommend',
        ];
        $self = $params['self'];
        //过滤掉状态不正常的
        $item['body']['query']['bool']['should']['bool']['must'][] = ['term' => ['status' => ['value' => 1]]];
        //过滤单个用户
        if (isset($params['user_id']) && $params['user_id'] > 0) {
            $item['body']['query']['bool']['should']['bool']['must'][] = ['term' => ['user_id' => ['value' => $params['user_id']]]];
        }
        //过滤隐私信息
        if (isset($params['private']) && !$self) {
            $item['body']['query']['bool']['should']['bool']['must'][] = ['term' => ['private' => ['value' => $params['private']]]];
        }
        //性别过滤
        if (!$self) {
            $item['body']['query']['bool']['should']['bool']['must'][] = [
                'bool' => [
                    'should' => [
                        ['term' => ['show_on' => 1]],
                        ['bool' => ['must' => [['term' => ['show_on' => 0]], ['term' => ['sex' => $params['sex']]]]]]
                    ]
                ],
            ];
        }
        if (isset($params['private_exclude']) && !$self) {
            $item['body']['query']['bool']['should']['bool']['must'][]['bool']['must_not'][] = ['term' => ['private' => $params['private_exclude']]];
        }

        //这里添加自定义排序
        if ((isset($params['sort']) && count($params['sort']) > 0)) {
            //如果是带排序的则加上排序并按照权重进行倒序
            $params['sort'][] = [
                '_score' => [
                    'order' => 'desc'
                ]
            ];
            //进行排序
            $item['body']['sort'] = $params['sort'];
        }
        if (env('DEBUG_ES_LOG', false)) Log::channel('debug')->info($item);
        return $item;
    }


}
