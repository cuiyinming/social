<?php

namespace App\Jobs;

use App\Http\Models\JpushModel;
use App\Http\Models\Logs\LogSignModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersRewardModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RongCloud;

class rewardSet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uid;
    protected $title;
    protected $cont;
    protected $reward;
    protected $channel;
    protected $sex;

    public function __construct($uid, $channel = 107, $title = '', $cont = '', $reward = 0, $sex = 1)
    {
        $this->uid = $uid;
        $this->title = $title;
        $this->cont = $cont;
        $this->reward = $reward;
        $this->channel = $channel;
        $this->sex = $sex;
    }


    public function handle()
    {
        try {
            $sender = true;
            //判断资料完善情况 104 完善资料,如果已经完善则不再有信息推送
            $register = UsersProfileModel::where('user_id', $this->uid)->first();
            //定义channel
            if (!defined('CHANNEL')) define('CHANNEL', strtolower($register->register_channel));
            if ($this->channel == 104) {
                $complete = $register->complete;
                if ($complete == 1) {
                    $sender = false;
                }
                //推送融云系统消息
                $avatar = UsersModel::where('id', $this->uid)->first()->avatar;
                if (stripos($avatar, '/ava/') !== false) {
                    //每日只推送一次，所以需要复查
                    $noticed = UsersMsgSysModel::where([['user_id', $this->uid], ['event', 'avatar_cmp'],
                        ['created_at', '>=', date('Y-m-d 00:00:00')],
                        ['created_at', '<=', date('Y-m-d 23:59:59')]])->first();
                    if (!$noticed) {
                        JpushModel::JpushCheck($this->uid, '', 0, 23);
                    }
                }
            }
            //105批量打招呼 【注册前两天或者是超过三天未登陆了】
            if ($this->channel == 105) {
                if ($register) {
                    $register_date = $register->register_date;
                    $last_live = UsersModel::where('id', $this->uid)->first();
                    if ($last_live) {
                        $live_time_latest = $last_live->live_time_latest;
                        //注册小于2天或是 距离上次活跃超过3天
                        if ((time() - strtotime($register_date)) > 86400 * 2 && (time() - strtotime($live_time_latest)) < 86400 * 3) {
                            $sender = false;
                        }
                    }
                }
            }
            //106 每日签到推送 [注册后且未签到过]
            if ($this->channel == 106) {
                $sign = LogSignModel::where([['user_id', $this->uid], ['last_date', date('Y-m-d')]])->first();
                //注册时间超过2天
                if ($register) {
                    $register_date = $register->register_date;
                    //如果签到完成或任务完成或注册时间小于15s 则不推送
                    $signSeven = LogSignModel::where([['user_id', $this->uid], ['serial', 7]])->first();
                    if ($sign || $signSeven || (time() - strtotime($register_date)) < 15) {
                        $sender = false;
                    }
                }
            }
            //资料完善发送后10分钟进行推送
            if ($sender) UsersRewardModel::sendImMsg($this->uid, $this->title, $this->cont, $this->reward, $this->channel, $this->sex);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
