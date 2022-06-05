<?php

namespace App\Components\ESearch\Model;

class usersItemsData
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
        //file_put_contents('/tmp/testss.log', print_r([$this->params, $source->getDataFromEs($this->params)], 1) . PHP_EOL, FILE_APPEND);
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
