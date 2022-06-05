<?php

namespace App\Http\Controllers\Client;


use App\Http\Libraries\Tools\Yam;
use App\Http\Models\Client\ClientDrawModel;
use App\Http\Models\Client\ClientLogModel;
use App\Http\Models\Client\ClientUsersModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogUserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthClientController;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\{T, H};
use Wuchuheng\QrMerge\QrMerge;

class PromoteController extends AuthClientController
{
    public function promoteUrlGet(Request $request)
    {
        $channel = $request->input('channel', 'wap');//wap qq wx
        $land = $request->input('land', '');
        $pic = $request->input('pic', 0);
        if ($pic == 0) $pic = rand(1, 225);
        //拼装url
        $url = 'http://hfriend.cn/dnd/index.html?id=' . $this->invite_code . '&channel=invite';  //固定推广连接
        if ($channel == 'wx') {
            $url = 'http://apfscat.org/wp-content/themes/planer/go.php?' . $url; //指定动态固定跳转
        }
        if ($channel == 'qq') {
            $url = 'https://www.jianshu.com/go-wild?ac=2&url=' . $url; //动态
        }
        //落地页
        $res['base64'] = T::mergePic($pic, $url);
        $res['info'] = [
            'url' => $url,
            'channel' => $channel,
            'pic' => $pic,
        ];
        $res['query'] = [
            'channel' => $channel,
            'pic' => $pic
        ];
        return $this->jsonExit(200, 'OK', $res);
    }

    public function promoteUrlShort(Request $request)
    {
        $url = $request->input('url', '');
        $channel = $request->input('channel', '');
        $pic = $request->input('pic', 0);
        if (stripos($url, 'y2e.cn') !== false) {
            return $this->jsonExit(201, '短网址已经生成，无法重复操作');
        }
        $shortUrl = T::shortUrl($url);
        //重新生成图片
        $data = [
            'shortUrl' => $shortUrl,
            'base64' => T::mergePic($pic, $shortUrl)
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function promoteUrlGain(Request $request)
    {
        $url = $request->input('url', '');
        //如果只传递了url 则直接对url 进行转换
        $channel = $request->input('channel', '');
        $item = $request->input('item', '');
        $act = $request->input('act', 'normal');
        if (!empty($url)) {
            if (stripos($url, 'd.hfriend.cn') !== false) {
                return $this->jsonExit(201, '短网址已经生成，无法重复操作');
            }
            $pro_url = T::shortUrl($url);
        } elseif (!empty($item) && !empty($channel)) {
            if (!in_array($item, ['linka', 'linkb', 'linkc', 'linkd', 'linke', 'linkf', 'linkg', 'linkh', 'linki', 'linkj', 'linkk'])) {
                return $this->jsonExit(201, 'item 错误');
            }
            $land_map = [
                'linka' => 'http://hfriend.cn/dnd/index.html?id=' . $this->invite_code . '&channel=invite', //默认落地页
                'linkb' => 'http://hfriend.cn/promote/a/index.html?id=' . $this->invite_code . '&channel=invite',
                'linkc' => 'http://hfriend.cn/promote/b/index.html?id=' . $this->invite_code . '&channel=invite',
                'linkd' => 'http://hfriend.cn/promote/c/index.html?id=' . $this->invite_code . '&channel=invite',
                'linke' => 'http://hfriend.cn/promote/d/index.html?id=' . $this->invite_code . '&channel=invite',
                'linkf' => 'http://hfriend.cn/promote/e/index.html?id=' . $this->invite_code . '&channel=invite',
                'linkg' => 'http://hfriend.cn/promote/f/index.html?id=' . $this->invite_code . '&channel=invite',
                'linkh' => 'http://hfriend.cn/promote/g/index.html?id=' . $this->invite_code . '&channel=invite',
                'linki' => 'http://hfriend.cn/promote/h/index.html?id=' . $this->invite_code . '&channel=invite',
                'linkj' => 'http://hfriend.cn/promote/i/index.html?id=' . $this->invite_code . '&channel=invite',
                'linkk' => 'http://hfriend.cn/promote/j/index.html?id=' . $this->invite_code . '&channel=invite',
            ];
            $pro_url = $land_map[$item];
            if ($channel == 'wx') {
                $pro_url = 'http://apfscat.org/wp-content/themes/planer/go.php?' . $pro_url;
            }
            if ($channel == 'qq') {
                //利用简书跳转
                $pro_url = 'https://www.jianshu.com/go-wild?ac=2&url=' . $pro_url;
            }
            if ($channel == 'lianxin') {
                $pro_url = (new Yam())->_shortUrl($pro_url);
            }
            if ($channel == 'douyin') {
                //利用知乎跳转
                $pro_url = 'https://link.zhihu.com/?target=' . $pro_url;
            }
            if ($act == 'short') {
                $pro_url = T::shortUrl($pro_url);
            }
        }
        $base_str = <<<EOL
最新脱单神器，真人小姐姐在线交友！
一键查看附近的人[色]

下载【App】

离你462米有3位小姐姐已经注册！点击下载⬇
「{$pro_url}」
EOL;

        //重新生成图片
        $data = [
            'shortUrl' => $pro_url,
            //文案
            'text' => $base_str,
            //二维码
            'base64' => T::mergePic(0, $pro_url)
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function promoteVideo(Request $request)
    {
        for ($i = 1; $i <= 7; $i++) {
            $base[] = [
                'url' => 'http://static.hfriend.cn/zip/0' . $i . '.mp4',
                'cover' => 'http://static.hfriend.cn/zip/0' . $i . '.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '8.9M',
                'download' => 'http://static.hfriend.cn/zip/0' . $i . '.mp4.zip',
                'pic' => '0张',
            ];
        }
        $data = [
            [
                'url' => 'http://static.hfriend.cn/zip/909419765video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/909419765video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '19.8M',
                'download' => 'http://static.hfriend.cn/zip/909419765.zip',
                'pic' => '10张',
            ], [
                'url' => 'http://static.hfriend.cn/zip/943908202video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/943908202video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '16.1M',
                'download' => 'http://static.hfriend.cn/zip/943908202.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/944008194video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/944008194video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '12.6M',
                'download' => 'http://static.hfriend.cn/zip/9440081947.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/944419361video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/944419361video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '21.5M',
                'download' => 'http://static.hfriend.cn/zip/944419361.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/960580708video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/960580708video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '6.9M',
                'download' => 'http://static.hfriend.cn/zip/960580708.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/963430366video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/963430366video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '7.3M',
                'download' => 'http://static.hfriend.cn/zip/963430366.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/966695429video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/966695429video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '19.8M',
                'download' => 'http://static.hfriend.cn/zip/966695429.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/968225930video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/968225930video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '3.3M',
                'download' => 'http://static.hfriend.cn/zip/968225930.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/969324742video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/969324742video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '9.9M',
                'download' => 'http://static.hfriend.cn/zip/969324742.zip',
                'pic' => '10张',
            ],
            [
                'url' => 'http://static.hfriend.cn/zip/969797508video.mp4',
                'cover' => 'http://static.hfriend.cn/zip/969797508video.mp4?x-oss-process=video/snapshot,t_1000,f_jpg,m_fast',
                'size' => '10.3M',
                'download' => 'http://static.hfriend.cn/zip/969797508.zip',
                'pic' => '10张',
            ]
        ];
        return $this->jsonExit(200, 'OK', array_merge($data, $base));
    }

    //获取推广报表 [ 地图 ]
    public function getPromoteData(Request $request)
    {
        $data = ClientLogModel::getGroupData($this->invite_code);
        return $this->jsonExit(200, 'OK', $data);
    }

    //分渠道的数据获取 [ 图表 ]
    public function getPromoteChartData(Request $request)
    {
        $by = $request->input('by', 'all');
        $data = ClientLogModel::getGroupChartData($this->invite_code, $by);
        return $this->jsonExit(200, 'OK', $data);
    }
}
