<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\DingTalk;
use App\Http\Libraries\Sms\MsgSend;
use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;
use App\Http\Models\SettingsModel;

class LogSmsModel extends Model
{

    protected $guarded = [];
    protected $table = 'log_sms';

    public static function sendMsg($mobile, $type = 'verify_code', $content = '')
    {
        $codeLength = 4;
        $code = $content == '' ? H::randstr($codeLength) : $content;
        $msgContent = config('common.msg_template');
        $sms = SettingsModel::getSigConf('sms');
        $sendStatus = self::gainRecord($mobile, $code, $type);
        //如果是虚拟发送
        if ($sms['sms_virtual'] == 1) {
            self::notice($mobile . '  验证码内容为：' . $code);
            return true;
        }
        if ($sendStatus) {
            $sendInfo = [
                'username' => $sms['account'],
                'password' => $sms['password'],
                'sign' => $sms['sms_sign'],
            ];
            $templateId = config('common.msg_template')[$type . '_template'];
            if ($sms['sms_channel'] == 'duanxinbao') {
                $cont = sprintf($msgContent[$type], $code);
                $cont = $sms['sms_sign'] . $cont;
                $sendResult = (new MsgSend($sendInfo, $templateId))->doSend($mobile, $cont, 'dxbSend');
            }

            if ($sms['sms_channel'] == 'aliyun') {
                if (in_array($type, ['verify_code', 'ask_draw', 'find_password', 'profile_code'])) $cont = ['code' => $code];
                if ($type == 'modify_user_pwd') $cont = ['password' => $code];
                if ($type == 'notice') $cont = ['url' => $code];
                if ($type == 'reg_notice') $cont = ['url' => $code];
                if ($type == 'invite_auth') $cont = ['url' => $code];
                if ($type == 'invite_contact') $cont = ['url' => $code];
                $sendResult = (new MsgSend($sendInfo, $templateId))->doSend($mobile, $cont, 'aliyunSend');
                if ($sendResult) {
                    ApiLeftModel::where('type', 'sms')->decrement('left_num');
                }
            }
            $sendStatus->status = 1;
            $sendStatus->save();
            return true;
        } else {
            return false;
        }
    }

    /*** 创建一条发送记录 **/
    public static function gainRecord($mobile, $code, $type = '')
    {
        return self::create(
            [
                'type' => $type,
                'status' => 1,
                'mobile' => $mobile,
                'code' => $code,
                'ip' => IP,
            ]
        );
    }

    /*** 提示 ***/
    public static function notice($msg)
    {
        (new DingTalk())->sendTextMessage($msg);
    }

    /**
     * 校验短信是否合法
     */
    public static function checkCode($mobile, $code, $type = 'verify_code')
    {
        $lastRecord = self::where([['mobile', $mobile], ['status', 1], ['type', $type], ['code', $code]])->orderBy('id', 'desc')->first();
        if (!$lastRecord || empty($code)) {
            return false;
        }
//        if ($lastRecord->code != $code) {
//            return false;
//        }
        if ((time() - strtotime($lastRecord->created_at)) > 300) {
            return false;
        }
        return true;
    }

    /**
     * 获取指定时间段内发送短信的总数
     */
    public static function geSmsNum($mobile, $type = 'verify_code', $minute = 5)
    {
        return intval(self::where([
            ['status', 1],
            ['type', $type],
            ['mobile', $mobile],
            ['created_at', '>', date('Y-m-d H:i:s', time() - 60 * $minute)]
        ])->count());
    }


    public static function getAdminPageSms($page = 1, $size = 20, $q = '', $type = '')
    {
        $builder = self::orderBy('id', 'desc');
        if ($q != '') {
            $builder->where('mobile', 'like', '%' . $q . '%');
        }
        if ($type != '') {
            $builder->where('type', $type);
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($logs) {
            $Arr = config('common.msg_template');
            foreach ($logs as &$log) {
                if ($log->type == 'admin_modify_user_pwd') $log->code = '******';
                try {
                    $log->code = sprintf($Arr[$log->type], $log->code);
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }


}
