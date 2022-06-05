<?php

namespace App\Http\Libraries\Sms;


use App\Http\Helpers\H;
use Curl\Curl;

/**
 * 详见最后的测试脚本
 * User: jewdore
 * Date: 2018/1/3
 * Time: 下午4:03
 */
class DingTalk
{
    private $send_url = "";
    private $curlBuilder = null;

    public function __construct($send = '')
    {
        $this->send_url = env('ERROR_HANDLER', "");
        if ($send) {
            $this->send_url = $send;
        }
        $this->curlBuilder = new Curl();
        $this->curlBuilder->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        $this->curlBuilder->setOpt(CURLOPT_TIMECONDITION, 5);
    }

    public function sendMessage($content)
    {
        $header = [
            'Content-Type:application/json;charset=utf-8',
        ];
        $this->curlBuilder->setOpt(CURLOPT_HTTPHEADER, $header);
        $res = $this->curlBuilder->post($this->send_url, json_encode($content));
        $res = H::object2array($res);

        if (isset($res['errcode']) && $res['errcode'] == 0) {
            return true;
        } else {
            return isset($res['errmsg']) ? $res['errmsg'] : '';
        }
    }

    /**
     * @https://open-doc.dingtalk.com/docs/doc.htm?spm=a219a.7629140.0.0.karFPe&treeId=257&articleId=105735&docType=1
     * 发送文字信息
     * @param string $content 发送的文字内容
     * @param array $ats 需要@的人
     * @param bool $is_at_all 是不是要@所有人
     * @return bool 返回成功与失败
     */
    public function sendTextMessage($content, $ats = [], $is_at_all = false)
    {
        $msg = array(
            'msgtype' => 'text',
            'text' => array(
                'content' => $content
            ),
            'at' => array(
                'atMobiles' => $ats,
                'isAtAll' => $is_at_all ? true : false
            )
        );
        return $this->sendMessage($msg);
    }

    /**
     * 发送链接信息
     * @param $title 链接信息的title
     * @param $text 链接信息的body
     * @param $pic_url 图片url
     * @param $message_url 信息跳转
     * @param array $ats
     * @param bool $is_at_all
     * @return bool
     */

    public function sendLinkMessage($title, $text, $message_url, $pic_url = "", $ats = [], $is_at_all = false)
    {
        $msg = array(
            'msgtype' => 'link',
            'link' => array(
                'text' => $text,
                'title' => $title,
                'picUrl' => $pic_url,
                'messageUrl' => $message_url
            ),
            'at' => array(
                'atMobiles' => $ats,
                'isAtAll' => $is_at_all ? true : false
            )
        );
        return $this->sendMessage($msg);

    }

    /**
     * markdown 格式内容编写的时候  在md标签和内容之间要留有一个空格,前面有回车
     * @param $title 信息的title
     * @param $text  信息的body
     * @param array $ats
     * @param bool $is_at_all
     * @return bool|string
     */

    public function sendMdMessage($title, $text, $ats = [], $is_at_all = false)
    {
        $msg = array(
            'msgtype' => 'markdown',
            'markdown' => array(
                'text' => $text,
                'title' => $title
            ),
            'at' => array(
                'atMobiles' => $ats,
                'isAtAll' => $is_at_all ? true : false
            )
        );
        return $this->sendMessage($msg);
    }

    /**
     *最下面按钮只有一个
     * @param $title
     * @param $text 支持markdown
     * @param $singleTitle 按钮文字
     * @param $singleUrl 按钮url
     * @param int $hideAvatar 0
     * @param int $btnOrientation 0
     * @return bool|string
     */

    public function sendSingleActionCard($title, $text, $singleTitle, $singleUrl, $hideAvatar = 0, $btnOrientation = 0)
    {
        $msg = array(
            "actionCard" => array(
                "title" => $title,
                "text" => $text,
                "hideAvatar" => $hideAvatar,
                "btnOrientation" => $btnOrientation,
                "singleTitle" => $singleTitle,
                "singleURL" => $singleUrl
            ),
            "msgtype" => "actionCard"
        );

        return $this->sendMessage($msg);
    }

    public function __destruct()
    {
        $this->curlBuilder->close();
    }

}
