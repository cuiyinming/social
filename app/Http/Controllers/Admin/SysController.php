<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Models\Lib\LibChatModel;
use App\Http\Models\Logs\ApiLeftModel;
use App\Http\Models\MessageModel;
use App\Http\Models\System\SysMessageModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Helpers\{R, H, HR};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SysController extends AuthAdmController
{
    /*---** 消息管理 ***/
    public function userMessageLog(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type', 100);
        $q = $request->input('q');
        $msg_type = $request->input('msg_type');
        $data = SysMessageModel::getAdminPageItems($page, $size, $q, $type, $msg_type);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function userMessageAdd(Request $request)
    {
        $data = $request->all();
        $type = $request->input('type', 0);
        $user_id = $request->input('user_id');
        $data['adm_id'] = $this->uid;
        if (empty($data['title']) || empty($data['cont'])) {
            return $this->jsonExit(201, '标题和内容不能为空');
        }
        if (in_array($data['msg_type'], [1]) && empty($data['auth'])) {
            return $this->jsonExit(202, '定时消息和每日定时消息，时间必须设置');
        }
        if (in_array($data['msg_type'], [0]) && !empty($data['auth'])) {
            return $this->jsonExit(202, '实时消息不必设置时间');
        }
        $sendUser = false;
        if (!is_null($user_id) && $user_id > 0) {
            $user = UsersModel::getUserInfo($user_id);
            if (empty($user)) {
                return $this->jsonExit(201, '用户不存在');
            }
            $sendUser = true;
        }
        $title = $data['title'];
        $cont = $data['cont'];
        try {
            if (in_array($type, [0, 2])) {
                //极光消息
                if ($sendUser) {
                    $pushMsg = [
                        "alert" => [
                            'title' => $title,
                            'body' => $cont,
                        ],
                        'badge' => '+1',
                        'extras' => [
                            'ext' => [
                                'text' => $title,
                                'type' => 'admin_push'
                            ],
                        ],
                        'content-available' => true,
                        'sound' => 'default',
                    ];
                    (AuroraPush::getInstance())->aliasPush($user_id, $pushMsg);
                } else {
                    //群发
                    $pushMsg = [
                        'alert' => [
                            'title' => $title,
                            'body' => $cont,
                        ],
                        'ios' => [
                            "badge" => (int)1,
                            'sound' => 'default',
                            'extras' => [
                                'content' => $cont,
                            ]
                        ],
                    ];
                    if ($data['msg_type'] == 0) (AuroraPush::getInstance())->batchPush($pushMsg);
                }
            }
            if (in_array($type, [1, 2])) {
                //全量系统消息
                if ($sendUser) {
                    $users = UsersModel::where([['status', 1], ['user_id', $user_id]])->get();
                } else {
                    $users = UsersModel::where('status', 1)->get();
                }
                if (!$users->isEmpty()) {
                    foreach ($users as $user) {
                        $sysMsgData = [
                            'user_id' => $user->id,
                            'event_id' => 0,
                            'event' => 'admin_sys_msg',
                            'title' => $title,
                            'cont' => $cont,
                        ];
                        UsersMsgSysModel::create($sysMsgData);
                        $sysMsg = ['content' => $cont, 'title' => $title, 'extra' => ""];
                        RongCloud::messageSystemPublish(101, [$user_id], 'RC:TxtMsg', json_encode($sysMsg));
                    }
                }
            }
            SysMessageModel::create($data);
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(202, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    public function userMessageDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $model = SysMessageModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '记录不存在');
        }
        $model->delete = 1;
        $model->save();
        return $this->jsonExit(200, 'OK');
    }
}
