<?php

namespace App\Http\Models;

use App\Http\Helpers\H;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Models\System\SysMessageModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use App\Http\Models\Users\UsersMsgSysModel;
use Illuminate\Database\Eloquent\Model;
use App\Http\Models\Logs\LogPushModel;
use Illuminate\Support\Facades\DB;
use RongCloud;

class JpushModel extends Model
{
    protected $guarded = [];

    public static function JpushCheck($user_id, $nick = '', $silent = 0, $msgType = 1, $event_id = 0)
    {
        try {
            if ($msgType > 28) return false;
            $msgArr = config('self.check_list');
            $msg = $msgArr[$msgType];
            if (!empty($nick)) $msg['cont'] = sprintf($msg['cont'], $nick);
            //发送站内信
            if (in_array($msgType, [6, 7, 8, 9, 12, 14, 15, 20, 21, 23, 24, 25])) {
                UsersMsgSysModel::storeMsg($user_id, $msg, $event_id = 0);
            }
            //指定跳转
            $jump = [];
            if (isset($msg['scheme']['scheme']) && $msg['scheme']['scheme'] > 0) {
                $jump = UsersMsgModel::schemeUrl('', $msg['scheme']['scheme'], $msg['scheme']['title'], $event_id, $msg['scheme']['button']);
            }
            if ($msg['cont']) {
                //格式化数据类型
                $auroraPush = AuroraPush::getInstance();
                $pushMsg = [
                    "alert" => [],
                    'extras' => [
                        'jump' => $jump
                    ],
                    'sound' => 'default',
                ];
                if ($silent == 0) {
                    $pushMsg = array_merge($pushMsg, [
                        'badge' => '+1',
                        "alert" => [
                            'title' => $msg['title'],
                            'body' => $msg['cont']
                        ],
                    ]);
                }
                //仅针对喜欢操作
                if (in_array($msgType, [2, 3, 4, 5])) {
                    $pushLogModel = LogPushModel::where([['user_id', $user_id], ['msg', $msg['cont']], ['status', 1], ['type', 3]])->orderBy('id', 'desc')->first();
                    //此处是为了防止点击喜欢多次重复推送
                    $auroraPush->aliasPush($user_id, $pushMsg);
                    if ($pushLogModel && (time() - strtotime($pushLogModel->created_at)) > 21600) {
                        $auroraPush->aliasPush($user_id, $pushMsg);
                        //入库推送消息
                        LogPushModel::storeToDb($user_id, $msg, 3, $msgType);
                    }
                    if (!$pushLogModel) {
                        $auroraPush->aliasPush($user_id, $pushMsg);
                        //入库推送消息
                        LogPushModel::storeToDb($user_id, $msg, 3, $msgType);
                    }
                } else {
                    //入库推送消息
//                    file_put_contents('/tmp/sprint.log', print_r([$user_id, $pushMsg], 1) . PHP_EOL, FILE_APPEND);
                    $res = $auroraPush->aliasPush($user_id, $pushMsg);
                    LogPushModel::storeToDb($user_id, $msg, 3, $msgType);
                }
            }
        } catch (\Exception $e) {
//            file_put_contents('/tmp/sprint.log', print_r([$msgType, $nick, $msg], 1) . PHP_EOL, FILE_APPEND);
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }

    public static function pushSender($user_id, $data, $jump = [])
    {
        //指定跳转
        $silent = 0;
        $pushMsg = [
            "alert" => [],
            'extras' => [
                'jump' => $jump
            ],
            'content-available' => true,
            'sound' => 'default',
        ];
        if ($silent == 0) {
            $pushMsg = array_merge($pushMsg, [
                'badge' => '+1',
                "alert" => [
                    'title' => $data['title'],
                    'body' => $data['cont']
                ],
                'content-available' => false
            ]);
        }
        $res = (AuroraPush::getInstance())->aliasPush($user_id, $pushMsg);
        return $res;
    }

    //***三连送 1 发送系统通知101 2发送信息提醒  3 发送极光通知 4写系统消息库
    public static function senderMaster($user_id, $title, $cont, $type = 'feed_back', $send_type = 'all', $scheme = null)
    {
        $jump = [];
        if (!is_null($scheme) && !empty($scheme)) {
            $jump = UsersMsgModel::schemeUrl('', intval($scheme), $title, 0, '立即查看');
        }
        if (in_array($send_type, ['all', 'sys'])) {
            //推送融云系统消息
            $sysMsg = ['content' => $cont, 'title' => $title, 'extra' => ""];
            //RongCloud::messageSystemPublish(101, [$user_id], 'RC:TxtMsg', json_encode($sysMsg));
            //未读消息更新
            UsersMsgNoticeModel::gainNoticeLog($user_id, 'site_notice', 1);
            //系统消息
            $sysMsgData = [
                'user_id' => $user_id,
                'event_id' => 0,
                'jump_scheme' => $scheme,
                'event' => $type,
                'title' => $title,
                'cont' => $cont,
            ];
            UsersMsgSysModel::create($sysMsgData);
        }
        if (in_array($send_type, ['all', 'push'])) {
            self::pushSender($user_id, ['title' => $title, 'cont' => $cont], $jump); //极光推送
        }
    }
}
