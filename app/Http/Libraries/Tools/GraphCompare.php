<?php

namespace App\Http\Libraries\Tools;

use App\Http\Models\MessageModel;
use Curl\Curl;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Log;

/***********************************************************************
 * *************************不同照片相似度 对比 **************************
 ***********************************************************************/
class GraphCompare
{
    private $curlBuilder = null;
    protected $channel = 'aliyun';

    public function __construct()
    {
        $this->curlBuilder = new Curl();
        $this->curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
    }

    /******照片对比*****/
    public function faceCheck($sourceImg, $destImg)
    {
        $sourceImg = H::ossPath($sourceImg);
        $destImg = H::ossPath($destImg);
        try {
            //file_put_contents('/tmp/xx.log', print_r([$sourceImg, $destImg], 1), FILE_APPEND);
            if ($this->channel == 'aliyun') {
                //300元/QPS/月  人脸1:1比对   人脸检测定位
                $res = $this->_aliyun($sourceImg, $destImg);
//                $res = $this->_aligreen($sourceImg, $destImg);
            }
            if ($this->channel == 'baidu') {
                $res = $this->_baidu($sourceImg, $destImg);
            }
            return $res;
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            throw new \Exception($e->getMessage());
        }
    }

    //阿里绿网
    private function _aligreen($sourceImg, $destImg)
    {
        return (new AliyunCloud())->GreenFaceCompare($sourceImg, $destImg);
    }

    //阿里云
    private function _aliyun($sourceImg, $destImg)
    {
        $resArr = (new AliyunCloud())->faceCompare($sourceImg, $destImg);
        //file_put_contents('/tmp/compare.log', print_r([$resArr, $sourceImg, $destImg], 1), FILE_APPEND);
        return $resArr['Data']['SimilarityScore'] ?? 0;
    }

    //百度
    private function _baidu($sourceImg, $destImg)
    {
        $resArr = (new BaiduCloud())->compareFace($sourceImg, $destImg);
        //file_put_contents('/tmp/xx.log', print_r([$resArr], 1), FILE_APPEND);
        return $resArr['score'] ?? 0;
    }

    public function __destruct()
    {
        $this->curlBuilder->close();
    }

}
