<?php

namespace App\Http\Libraries\Tools;

use Curl\Curl;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Log;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;
use AlibabaCloud\Green\Green as Green;
use Mrgoon\AliyunSmsSdk\DefaultAcsClient;
use Mrgoon\AliyunSmsSdk\Profile\DefaultProfile;

/**
 * 不支持本地图片的相对路径或绝对路径。
 * 单张图片大小请控制在2M内，避免算法拉取超时。
 * 图片中人脸区域的大小至少64*64像素。
 * 单个请求的Body有8M的大小限制，请计算好请求中所有图片和其他信息的大小，不要超限。
 */
class AliyunCloud
{
    private $curlBuilder = null;
    private $isTest = true;
    private $uid = 0;
    private $accessKey = '';
    private $secret = '';

    //字符串长度
    public function __construct($uid = 0)
    {
        $this->uid = $uid;
        $this->curlBuilder = new Curl();
        $this->curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
        $this->curlBuilder->setOpt(CURLOPT_CONNECTTIMEOUT, 300);
        AlibabaCloud::accessKeyClient($this->accessKey, $this->secret)->regionId('cn-hangzhou')->asDefaultClient();
    }

    //生成token 信息
    public function gainToken($faceImgUrl, $bzid)
    {
        try {
            $builder = AlibabaCloud::rpc()
                ->product('Cloudauth')
                // ->scheme('https') // https | http
                ->version('2019-03-07')
                ->action('DescribeVerifyToken')
                ->method('POST')
                ->host('cloudauth.aliyuncs.com');
            $result = $builder->options([
                'query' => [
                    'RegionId' => "cn-hangzhou",
                    'BizId' => $bzid,
                    'BizType' => 'live-check',
                    'FaceRetainedImageUrl' => $faceImgUrl,
                ],
            ])->request()->toArray();
            return $result;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    //获取真人验证的结果
    public function getVerifyRes($bzid)
    {
        try {
            $builder = AlibabaCloud::rpc()
                ->product('Cloudauth')
                // ->scheme('https') // https | http
                ->version('2019-03-07')
                ->action('DescribeVerifyResult')
                ->method('POST')
                ->host('cloudauth.aliyuncs.com');
            $result = $builder->options([
                'query' => [
                    'RegionId' => "cn-hangzhou",
                    'BizId' => $bzid,
                    'BizType' => 'live-check'
                ],
            ])->request()->toArray();
            return $result;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    /**人脸对比 1:1 对比**/
    public function faceCompare($sourceUrl, $targetUrl)
    {
        try {
            $builder = AlibabaCloud::rpc()
                ->product('Cloudauth')
                ->scheme('https') // https | http
                ->version('2019-03-07')
                ->action('CompareFaces')
                ->method('POST')
                ->host('cloudauth.aliyuncs.com');
            $result = $builder->options([
                'query' => [
                    'RegionId' => "cn-hangzhou",
                    'TargetImageType' => 'FacePic',
                    'SourceImageType' => 'FacePic',
                    'SourceImageValue' => $sourceUrl,
                    'TargetImageValue' => $targetUrl,
                ],
            ])->request()->toArray();
            return $result;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    /*---人脸数量检测---*/
    public function faceCounter($targetUrl)
    {
        try {
            $builder = AlibabaCloud::rpc()
                ->product('facebody')
//                ->scheme('https') // https | http
                ->version('2019-12-30')
                ->action('DetectFace')
                ->method('POST')
                ->host('facebody.cn-shanghai.aliyuncs.com');
            $result = $builder->options([
                'query' => [
                    'RegionId' => "cn-shanghai",
                    'ImageURL' => $targetUrl,
                ],
            ])->request()->toArray();
            return $result;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }


    /*-----文本内容检测---
        spam：文字垃圾内容识别
        politics：文字涉政内容识别
        abuse：文字辱骂内容识别
        terrorism：文字涉恐内容识别
        porn：文字鉴黄内容识别
        flood：文字灌水内容识别
        contraband：文字违禁内容识别
        ad：文字广告内容识别
    --*/
    public function scanText($text = '')
    {
        try {
            $result = AlibabaCloud::rpc()
                ->product('imageaudit')
                // ->scheme('https') // https | http
                ->version('2019-12-30')
                ->action('ScanText')
                ->method('POST')
                ->host('imageaudit.cn-shanghai.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-shanghai",
                        'Tasks.1.Content' => $text,
                        'Labels.1.Label' => "spam",
                        'Labels.2.Label' => "politics",
                        'Labels.3.Label' => "abuse",
                        'Labels.4.Label' => "terrorism",
                        'Labels.5.Label' => "porn",
                        'Labels.6.Label' => "flood",
                        'Labels.7.Label' => "contraband",
                        'Labels.8.Label' => "ad",
                    ],
                ])
                ->request();
            return $result->toArray();
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    /*-----图片垃圾检测内容检测----
        porn：图片智能鉴黄
        terrorism：图片涉恐涉政识别/图片风险人物识别
        ad：图片垃圾广告识别
        live：图片不良场景识别
        logo：图片logo识别
    -*/
    public function scanImage($url = '')
    {
        try {
            $result = AlibabaCloud::rpc()
                ->product('imageaudit')
                // ->scheme('https') // https | http
                ->version('2019-12-30')
                ->action('ScanImage')
                ->method('POST')
                ->host('imageaudit.cn-shanghai.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-shanghai",
                        'Task.1.DataId' => uniqid('scan-image-' . $this->uid . '-'),
                        'Task.1.ImageURL' => $url,
                        'Scene.1' => "porn",
                        'Scene.2' => "terrorism",
                        'Scene.3' => "ad",
                        //'Scene.4' => "live",
                        //'Scene.5' => "logo",
                    ],
                ])
                ->request();
            return $result->toArray();
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    /*-**-----------------------*******审核视频文件*-内容安全审核***------------*****
    *---下面的均是内容安全提供的检测方法，上面的是有达摩院ai分析提供的，他们之间是不同的接口--*
    *----------------------------------------------------------------------****/
    public function GreenScanVideo($url = 'https://south-shanghai.oss-cn-shanghai.aliyuncs.com/63375.mp4')
    {
        try {
            $option = [
                'scenes' => [
                    'porn',
                ],
                'tasks' => [
                    'dataId' => uniqid('scan-video-' . $this->uid . '-'),
                    'framePrefix' => $url,
                    'frames' => [
                        ['url' => '?x-oss-process=video/snapshot,t_1000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_3000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_4000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_5000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_6000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_7000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_8000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_9000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_10000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_15000,m_fast'],
                        ['url' => '?x-oss-process=video/snapshot,t_20000,m_fast'],
                    ],
                ],
            ];
            $pass = 'pass';
            $res = Green::v20180509()->videoSyncScan(['body' => json_encode($option)])->request()->toArray();//同步
            if (isset($res['code']) && $res['code'] == 200 && isset($res['data'][0]['code']) && $res['data'][0]['code'] == 200) {
                $pass = $res['data'][0]['results'][0]['suggestion'];
            }
            return $pass;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    public function GreenScanImage($url)
    {
        try {
            $option = [
                'scenes' => [
                    "porn",
                    "ad",
                    "qrcode",
                    "logo"
                    //"live",
                    //"terrorism",
                ],
                'tasks' => [
                    'dataId' => uniqid('scan-image-' . $this->uid . '-'),
                    'url' => $url,
                ],
            ];
            $pass = 'pass';
            $res = Green::v20180509()->imageSyncScan(['body' => json_encode($option)])->request()->toArray();//同步处理
            if (isset($res['code']) && $res['code'] == 200 && isset($res['data'][0]['code']) && $res['data'][0]['code'] == 200) {
                $passRes = $res['data'][0]['results'];
                foreach ($passRes as $pas) {
                    if ($pas['suggestion'] != 'pass') {
                        $pass = 'block';
                    }
                }
            }
            // Log::channel('ali')->info([$this->uid, date('Y-m-d H:i:s'), $res, $pass]);
            //dd($res, $pass);
            return $pass;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    public function GreenFaceCompare($url, $tarUrl)
    {
        try {
            $option = [
                'scenes' => [
                    "sface-1",
                ],
                'tasks' => [
                    'dataId' => uniqid('scan-face-' . $this->uid . '-'),
                    'url' => $url,
                    'extras' => ['faceUrl' => $tarUrl],
                ],
            ];
            $res = Green::v20180509()->imageSyncScan(['body' => json_encode($option)])->request()->toArray();//同步处理
            $score = 0;
            if (isset($res['code']) && $res['code'] == 200 && isset($res['data'][0]['code']) && $res['data'][0]['code'] == 200) {
                $score = $res['data'][0]['results'][0]['rate'];
            }
            //dd($res, $pass);
            return $score;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    public function GreenScanText($cont)
    {
        try {
            $option = [
                'scenes' => [
                    "antispam",
                ],
                'tasks' => [
                    'dataId' => uniqid('scan-text-' . $this->uid . '-'),
                    'content' => $cont,
                ],
            ];
            $pass = 'pass';
            //return $pass;
            $res = Green::v20180509()->textScan(['body' => json_encode($option)])->request()->toArray();
            if (isset($res['code']) && $res['code'] == 200 && isset($res['data'][0]['code']) && $res['data'][0]['code'] == 200) {
                $passRes = $res['data'][0]['results'];
                foreach ($passRes as $pas) {
                    if ($pas['suggestion'] != 'pass') {
                        $pass = 'block';
                    }
                }
            }
            return $pass;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }

    public function GreenScanAudio($url)
    {
        $url = H::ossPath($url, 0);
        try {
            $option = [
                'scenes' => [
                    "antispam",
                ],
                'tasks' => [
                    'dataId' => uniqid('scan-audio-' . $this->uid . '-'),
                    'url' => $url,
                ],
            ];
            $voiceRes = [
                'pass' => 'block',
                'text' => '测试语音'
            ];
            $res = Green::v20180509()->voiceSyncScan(['body' => json_encode($option)])->request()->toArray();
            if (isset($res['code']) && $res['code'] == 200 && isset($res['data'][0]['code']) && $res['data'][0]['code'] == 200) {
                $passRes = $res['data'][0]['results'];
                foreach ($passRes as $pas) {
                    $voiceRes['text'] = $pas['details'][0]['text'] ?? '';
                    if ($pas['suggestion'] == 'pass') {
                        $voiceRes['pass'] = 'pass';
                    }
                }
            }
            return $voiceRes;
        } catch (ClientException $e) {
            throw new \Exception($e->getErrorMessage());
        } catch (ServerException $e) {
            throw new \Exception($e->getErrorMessage());
        }
    }


    public function __destruct()
    {
        $this->curlBuilder->close();
    }

}
