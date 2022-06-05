<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\MessageModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends AuthController
{
    //消息聊天
    public function event(Request $request)
    {
//        $event = $request->input('event', 'active');
//        //事件统计
//        if ($event == 'active' && !empty(DEVICE)) {
//            HR::updateUniqueNum('counter', DEVICE, 'daily-active-');
//        }

        return $this->jsonExit(200, 'OK');
    }
}
