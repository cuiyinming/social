<?php

namespace App\Components\ESearch\Business;

use App\Components\ESearch\achieveInterface;
use App\Components\ESearch\esInterface;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Models\Discover\DiscoverTopicModel;
use App\Http\Models\Lib\LibBioTextModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Discover\DiscoverModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Helpers\H;

class bioItems implements achieveInterface
{
    protected $search;
    const INDEX = 'bio';
    const TYPE = 'bio';
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
        if ($action == 'sync') {
            //第一步创建索引
            try {
                $this->putMapping();
            } catch (\Exception $e) {
                MessageModel::gainLog($e, __FILE__, __LINE__);
                $msgTitle = "ES 更新错误";
                $msg = "### ES 更新错误提示[MOSHI-BIO] \n > " . $e->getMessage();
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
        $arr['desc'] = ['type' => 'keyword'];
        $arr['main'] = ['type' => 'keyword'];
        $arr['content'] = ['type' => 'text', 'fielddata' => true, 'analyzer' => 'ik_max_word', 'search_analyzer' => 'ik_smart'];
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
        $builder = LibBioTextModel::orderBy('id', 'desc')->select([
            'id',
            'desc',
            'main',
            'content',
            'created_at',
        ]);
        if ($idRange['s'] > 0 && $idRange['e'] > 0) {
            $builder->where([['id', '>=', $idRange['s']], ['id', '<=', $idRange['e']]]);
        }
        $builder->chunk(500, function ($result) use ($table_id) {
            $params = [];
            foreach ($result as $item) {
                $arr = [];
                $arr['id'] = $item->id ? $item->id : 0;
                $arr['desc'] = $item->desc ? $item->desc : '';
                $arr['main'] = $item->main ? $item->main : '';
                $arr['content'] = $item->content ? $item->content : '';
                $arr['created_at'] = $item->created_at && !is_null($item->created_at) ? $item->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
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
            $params && $this->search->bulk($params);
        });
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
            'desc',
            'main',
            'content',
            'created_at',
        ];
        //签名
        if (isset($params['q']) && !empty($params['q'])) {
            $item['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $params['q'],
                    'fields' => ['content'],
                    'type' => 'best_fields',
                ],
            ];
        }
        //筛选分类
        if (isset($params['cat']) && !empty($params['cat'])) {
            $item['body']['query']['bool']['must'][] = ['term' => ['desc' => ['value' => $params['cat']]]];
        }
        //如果是带排序的则加上排序并按照权重进行倒序
        $params['sort'][0] = [
            '_script' => [
                "script" => "Math.random()",//随机排序
                "type" => "number",
                "order" => "asc"
            ]
        ];
        $item['body']['sort'] = $params['sort'];
        return $item;
    }
}
