<?php

namespace App\Components\ESearch\Model;

class tagsItemsData
{
    protected $params = [];

    public function __construct($params)
    {
        $this->params = $params;
    }


    public function format(array $sources)
    {

        $result = $items = [];
        foreach ($sources as $source) {
            $items[$source::TYPE] = $source->getDataFromEs($this->params);
        }
        //$sourcesKeyArr = $sources[Items::TYPE]->getAggregationsKey(); //这句是用来统计聚合的
        if (isset($items[$source::TYPE])) {
            $this->ff($items[$source::TYPE], $result);
        }
        return $result;
    }

    //格式化数据
    private function ff($source, &$result)
    {
        $dataArr = [];
        if (intval($source['hits']['total']) > 0) {
            $result['time'] = $source['took'];
            $result['count'] = $source['hits']['total'];
            foreach ($source['hits']['hits'] as $hits => $hitArr) {
                //高亮字段
                if (isset($hitArr['_source']['created_at'])) {
                    unset($hitArr['_source']['created_at']);
                }
                $hitArr['_source']['total_desc'] = $hitArr['_source']['total'] . " 条动态";
                $hitArr['_source']['topic_follow_is'] = 0;
                $dataArr[] = $hitArr['_source'];
            }
            $result['items'] = $dataArr;
        }
    }

}
