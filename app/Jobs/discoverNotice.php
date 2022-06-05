<?php

namespace App\Jobs;

use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\JpushModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgModel;
use App\Http\Models\Users\UsersMsgNoticeModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RongCloud;

class discoverNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $discover;
    protected $event;
    protected $status;
    protected $cmt;
    protected $uid;

    public function __construct(DiscoverModel $discover, $uid, $event, $status = 1, $cmt = '')
    {
        $this->discover = $discover;
        $this->event = $event;
        $this->status = $status;
        $this->cmt = $cmt;
        $this->uid = $uid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->event == 'discover_zan') {
                $opt = $this->status == 1 ? 1 : 0;
                UsersMsgNoticeModel::gainNoticeLog($this->discover->user_id, 'zan', 1, $opt);
            }
            if ($this->event == 'discover_cmt') {
                UsersMsgNoticeModel::gainNoticeLog($this->discover->user_id, 'comment', 1);
            }
            //添加系统的动态消息
            $exist = UsersMsgModel::where([['user_id', $this->discover->user_id], ['trigger_id', $this->uid], ['event_id', $this->discover->id], ['event', $this->event]])->first();
            if ($this->status == 1 && !$exist) {
                $nick = UsersModel::find($this->uid)->nick;
                $title = $this->event == 'discover_zan' ? '动态点赞通知' : '动态评论通知';
                $cont = $this->event == 'discover_zan' ? "{$nick} 点赞了您的动态，快去看看吧。" : '评论：' . $this->cmt;
                $sysMsgData = [
                    'user_id' => $this->discover->user_id,
                    'event_id' => $this->discover->id,
                    'event' => $this->event,
                    'trigger_id' => $this->uid,
                    'title' => $title,
                    'cont' => $cont,
                    'type' => 'notice',
                ];
                UsersMsgModel::create($sysMsgData);
                //极光推送
                if ($this->event == 'discover_cmt') JpushModel::JpushCheck($this->discover->user_id, $nick, 0, 5, $this->discover->id);
                if ($this->event == 'discover_zan') JpushModel::JpushCheck($this->discover->user_id, $nick, 0, 4, $this->discover->id);
                //协定消息推送 [10000] 系统协定推送
                $toUserIds = [$this->discover->user_id];
                $notice = [
                    'content' => '消息推送',
                    "title" => "消息推送",
                    'extra' => [
                        'discover' => 1,
                    ],
                ];
                //100 代表点赞+评论的红点推送  签到 一键咋呼 引导完善资料
                RongCloud::messageSystemPublish(103, $toUserIds, 'RC:TxtMsg', json_encode($notice));
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
