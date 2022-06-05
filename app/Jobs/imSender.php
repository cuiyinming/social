<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RongCloud;

class imSender implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fromUser;
    protected $toUser;
    protected $msgType;
    protected $msg;
    protected $channel;

    public function __construct($fromUser, $toUser, $msgType, $msg, $channel = 'private')
    {
        $this->fromUser = $fromUser;
        $this->toUser = $toUser;
        $this->msgType = $msgType;
        $this->msg = $msg;
        $this->channel = $channel;
    }


    public function handle()
    {
        if ($this->channel == 'private') {
             $rong = RongCloud::messagePrivatePublish($this->fromUser, $this->toUser, $this->msgType, $this->msg);
        }
    }
}
