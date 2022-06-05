<?php

namespace App\Components\ESearch\Model;

class discoverItemsData
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
                $dataArr[] = $hitArr['_source'];
            }
            $result['items'] = $dataArr;
        }
    }
}
