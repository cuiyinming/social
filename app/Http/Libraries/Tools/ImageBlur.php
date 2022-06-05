<?php

namespace App\Http\Libraries\Tools;

use App\Http\Models\Logs\LogAuthModel;
use Curl\Curl;
use App\Http\Helpers\H;
use Illuminate\Support\Facades\Log;

/***********************************************************************
 * ************************* 图片模糊处理 **************************
 ***********************************************************************/
class ImageBlur
{
    /**
     * 图片高斯模糊（适用于png/jpg/gif格式）
     * @param $srcImg 原图片
     * @param $savepath 保存路径
     * @param $savename 保存名字
     * @param $positon 模糊程度
     *
     *基于Martijn Frazer代码的扩充， 感谢 Martijn Frazer
     */
    public function gaussian_blur($srcImg, $savepath = null, $savename = null, $blurFactor = 3)
    {
        $gdImageResource = $this->image_create_from_ext($srcImg);
        $srcImgObj = $this->blur($gdImageResource, $blurFactor);
        $temp = pathinfo($srcImg);
        $name = $temp['basename'];
        $path = $temp['dirname'];
        $exte = $temp['extension'];
        $savename = $savename ? $savename : $name;
        $savepath = $savepath ? $savepath : $path;
        $savefile = $savepath . '/' . $savename;
        $srcinfo = @getimagesize($srcImg);
        switch ($srcinfo[2]) {
            case 1:
                imagegif($srcImgObj, $savefile);
                break;
            case 2:
                imagejpeg($srcImgObj, $savefile);
                break;
            case 3:
                imagepng($srcImgObj, $savefile);
                break;
            default:
                return '保存失败'; //保存失败
        }
        return $savefile;
        imagedestroy($srcImgObj);
    }

    /**
     * Strong Blur
     *
     * @param $gdImageResource 图片资源
     * @param $blurFactor   可选择的模糊程度
     * 可选择的模糊程度 0使用 3默认 超过5时 极其模糊
     * @return GD image 图片资源类型
     * @author Martijn Frazer, idea based on http://stackoverflow.com/a/20264482
     */
    private function blur($gdImageResource, $blurFactor = 3)
    {
        // blurFactor has to be an integer
        $blurFactor = round($blurFactor);
        $originalWidth = imagesx($gdImageResource);
        $originalHeight = imagesy($gdImageResource);
        $smallestWidth = ceil($originalWidth * pow(0.5, $blurFactor));
        $smallestHeight = ceil($originalHeight * pow(0.5, $blurFactor));
        // for the first run, the previous image is the original input
        $prevImage = $gdImageResource;
        $prevWidth = $originalWidth;
        $prevHeight = $originalHeight;
        // scale way down and gradually scale back up, blurring all the way
        for ($i = 0; $i < $blurFactor; $i += 1) {
            // determine dimensions of next image
            $nextWidth = $smallestWidth * pow(2, $i);
            $nextHeight = $smallestHeight * pow(2, $i);
            // resize previous image to next size
            $nextImage = imagecreatetruecolor($nextWidth, $nextHeight);
            imagecopyresized($nextImage, $prevImage, 0, 0, 0, 0,
                $nextWidth, $nextHeight, $prevWidth, $prevHeight);
            // apply blur filter
            imagefilter($nextImage, IMG_FILTER_GAUSSIAN_BLUR);
            // now the new image becomes the previous image for the next step
            $prevImage = $nextImage;
            $prevWidth = $nextWidth;
            $prevHeight = $nextHeight;
        }
        // scale back to original size and blur one more time
        imagecopyresized($gdImageResource, $nextImage,
            0, 0, 0, 0, $originalWidth, $originalHeight, $nextWidth, $nextHeight);
        imagefilter($gdImageResource, IMG_FILTER_GAUSSIAN_BLUR);
        // clean up
        imagedestroy($prevImage);
        // return result
        return $gdImageResource;
    }

    private function image_create_from_ext($imgfile)
    {
        $info = getimagesize($imgfile);
        $im = null;
        switch ($info[2]) {
            case 1:
                $im = imagecreatefromgif($imgfile);
                break;
            case 2:
                $im = imagecreatefromjpeg($imgfile);
                break;
            case 3:
                $im = imagecreatefrompng($imgfile);
                break;
        }
        return $im;
    }

    //添加生成验证码名称生成逻辑
    private $width = 160;//宽度  40-100
    private $height = 24;//高度
    private $img;//图形资源句柄
    private $font;//指定的字体
    private $fontsize = 14;//指定字体大小
    private $fontcolor;//指定字体颜色
    private $code;//验证码
    private $path;


    private function createImage()
    {
        //创建真彩图像资源
        $this->img = imagecreatetruecolor($this->width, $this->height);
        //分配一个随机色
        $color = imagecolorallocatealpha($this->img, 0, 0, 0, 127);
        //画一矩形并填充随机色
        $this->fontcolor = imagecolorallocate($this->img, 25, 25, 25);
        imagealphablending($this->img, false);//显示透明背景
        imagefill($this->img, 0, 0, $color);//填充背景
        //绘制验证码 可以绘制汉字
        imagettftext($this->img, $this->fontsize, 0, 4, 18, $this->fontcolor, $this->font, $this->code);
        imagesavealpha($this->img, true);
        //输出
        imagepng($this->img, $this->path);
        imagedestroy($this->img);
    }

    //对外生成
    public function createBlurNick($nick = '')
    {
        //第一步去掉表情符号
        $nickStr = '';
        for ($i = 0; $i < mb_strlen($nick); $i++) {
            $str = mb_substr($nick, $i, 1);
            if (strlen($str) != 4) {
                $nickStr .= $str;
            }
        }
        //如果存在直接返回，不存在上传处理后返回
        $buket = 'nick/' . date('ym') . '/' . md5($nickStr) . '.png';
        if ($handle = @fopen(config('app.cdn_url') . '/' . $buket, 'r')) {
            return config('app.cdn_url') . '/' . $buket . '!nick';
        }
        $this->code = $nickStr;
        $this->font = base_path('resources/data/pf.ttf');
        $this->path = './nick/nick.png';
        $this->createImage();

        $localRule = (AliyunOss::getInstance())->uploadToOss($buket, $this->path);
        if (config('app.cnd_on')) {
            $localRule = str_replace(config('app.cdn_source_url'), config('app.cdn_url'), $localRule);
        }
        @unlink($this->path);
        return $localRule . '!nick';
    }
}
