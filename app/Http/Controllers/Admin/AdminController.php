<?php

namespace App\Http\Controllers\Admin;


use App\Http\Libraries\Tools\AliyunOss;
use App\Http\Libraries\Tools\AuroraPush;
use App\Http\Libraries\Tools\GraphCompare;
use App\Http\Models\JobsModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Models\Resource\AlbumModel;
use App\Http\Models\Lib\LibChatModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Logs\LogPushModel;
use App\Http\Helpers\{R, H, HR, S};
use App\Http\Models\CommonModel;
use App\Http\Models\Admin\AdminModel;
use App\Http\Models\Admin\AdmNodeModel;
use App\Http\Models\Admin\AdmRoleModel;
use App\Http\Models\Admin\AdmRoleUserModel;
use App\Http\Models\System\BlackIpModel;
use App\Http\Models\Admin\AdmRoleListModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Admin\ActiveLogModel;
use \App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Admin\AuthAdmController;
use Image;
use App\Http\Models\Logs\LogActionModel;

class AdminController extends AuthAdmController
{
    //获取登录用户基本信息
    public function baseInfo()
    {
        $data = auth()->guard('admin')->user();
        return $this->jsonExit(200, 'OK', $data);
    }

    public function userMinBaseInfo(Request $request)
    {
        $col = $request->input('col', 'mobile');
        $info = AdminModel::getUserInfo($this->uid);
        $data = [];
        if (is_array($col)) {
            foreach ($col as $k => $v) {
                $data[$v] = $info->$v;
                if ($v == 'mobile') {
                    $data[$v] = H::hideStr($info->mobile, 3, 4);
                }
            }
        } else {
            try {
                $val = $info->$col;
            } catch (\Exception $e) {
                $val = '';
            }
            $data = [
                $col => $val
            ];
        }
        $data['manager'] = $this->role;
        return $this->jsonExit(200, 'OK', $data);
    }

    //用户权限
    public function adminPermission(Request $request)
    {
        $res = [];
        $admin = AdminModel::find($this->uid);
        if (!$admin) {
            return $this->jsonExit(201, '用户不存在');
        }
        $res['is_supper'] = $admin->supper;
        //权限节点
        $role_id = AdmRoleUserModel::where('user_id', $this->uid)->first();
        $res['role_id'] = $role_id ? $role_id->role_id : 0;
        //查询权限节点信息
        $nodeIdArr = AdmRoleModel::where('role_id', $res['role_id'])->pluck('node_id');
        $nodeArrs = AdmNodeModel::whereIn('id', $nodeIdArr)->get();
        $nodes = [];
        if ($nodeArrs) {
            foreach ($nodeArrs as $nodeArr) {
                // $nodes[] = [
                //  'id' => $nodeArr->id,
                //  'title' => $nodeArr->title,
                //  'vue_name' => $nodeArr->vue_name,
                //  ];
                $nodes[] = $nodeArr->vue_name;
            }
        }
        $res['nodes'] = $nodes;
        return $this->jsonExit(200, 'OK', $res);
    }

    /*** 获取网站全部配置 ***/
    private function _private()
    {
        return [
            'sms_virtual',
            'user_reg_open',
            'view_limit_on',
            'ip_limit',
            'admin_unique',
            'user_unique',
            'login_sms_notice',
            'dingTalk_virtual',
            'real_check',
            'view_limit_on',
            'view_time_on',
            'chat_limit_on',
            'daily_view_limit_on',
            'daily_contact_view_limit_on',
            'aliyun',
            'jpush_on',
            'update_on',
            'video_limit_on',
            'super_show_on',
            'invite_on',
            'real_add_on',
            'batch_say_hi_on',
            'real_add_sweet_on',
            'say_hi_gift',
            'force_complete',
            'goddess_add_on',
            'goddess_add_sweet_on',
            'force_real',
            'say_hi',
            'register_say_hi',
            'cmt_us_on',
            'jpush_pro',
            'update_on_android',
            'login_fast',
            'recover_data',
            'discover_cmt',
            'vip_center',
            'register_say_hi'
        ];
    }

    public function settingsGet(Request $request, $option)
    {
        $res = [];
        $settings = SettingsModel::select(['key', 'value'])->where([['status', 1], ['option', $option]])->get();
        foreach ($settings as $setting) {
            $eArr = $this->_private();
            if (in_array($setting->key, $eArr)) {
                $setting->value = $setting->value == 1;
            }
            $res[$setting->key] = $setting->value;
        }
        return $this->jsonExit(200, 'OK', $res);
    }

    public function settingsSave(Request $request, $option)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        $datas = $request->all();
        foreach ($datas as $key => $data) {
            $eArr = $this->_private();
            if (in_array($key, $eArr)) {
                $data = $data == true ? 1 : 0;
            }
            SettingsModel::updateOrCreate([
                'option' => $option,
                'status' => 1,
                'key' => $key
            ], [
                'value' => trim($data)
            ]);
        }
        R::dredis('settings_' . $option);
        return $this->jsonExit(200, 'OK');
    }

    /*** ip名单添加*/
    public function blackIpAdd(Request $request)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        if (!$request->has('ip1') || !$request->has('ip2') || !$request->has('ip3') || !$request->has('ip4')) {
            return $this->jsonExit(201, '必选参数未传递');
        }
        if ($request->input('ip1') == '*') {
            return $this->jsonExit(202, 'ip1不能为通配*');
        }
        if ($request->input('ip2') == '*') {
            return $this->jsonExit(203, 'ip2不能为通配*');
        }
        $data = $request->all();
        BlackIpModel::updateOrCreate($data, $data + ['desc' => '手动添加']);
        JobsModel::InsertNewJob(5);
        return $this->jsonExit(200, 'OK');
    }

    public function blackIpList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $data = BlackIpModel::getAdminPageItems($page, $size);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function blackIpUpdate(Request $request)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        $data = $request->all();
        $model = BlackIpModel::find($data['id']);
        if (!$model) {
            return $this->jsonExit(201, '记录不存在，请检查');
        }
        $model->delete();
        JobsModel::InsertNewJob(5);
        return $this->jsonExit(200, 'OK');
    }

    public function adminList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $status = $request->input('status', '');
        $q = $request->input('q', '');
        $data = AdminModel::getPageAdminItems($page, $size, $status, $q, $this->uid);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function adminDelete(Request $request)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        $usermodel = AdminModel::find($this->uid);
        if ($usermodel->supper != 1) {
            return $this->jsonExit(201, '只有超级管理员才能做此操作');
        }
        $id = $request->input('id', 0);
        $adminModel = AdminModel::find($id);
        $adminModel->delete = 1;
        $adminModel->save();
        AdmRoleUserModel::where('user_id', $adminModel->id)->delete();
        return $this->jsonExit(200, 'OK');
    }

    public function adminUpdate($type = 'status', Request $request)
    {
        $usermodel = AdminModel::find($this->uid);
        if ($usermodel->supper != 1) {
            return $this->jsonExit(201, '您无权限做此操作');
        }
        $data = $request->all();
        $model = AdminModel::find($data['id']);
        $model->$type = $data[$type];
        $model->save();
        return $this->jsonExit(200, 'OK');
    }

    //添加管理员
    public function adminAdd(Request $request)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        $username = $request->input('username', '');
        $password = $request->input('password', '');
        $status = $request->input('status', '');
        $supper = $request->input('supper', '');
        $role = $request->input('role', 0);
        $remark = $request->input('remark', '');
        $usermodel = AdminModel::find($this->uid);
        if ($usermodel->supper != 1) {
            return $this->jsonExit(201, '您无权限做此操作');
        }
        $adminModel = AdminModel::where('username', $username)->first();
        if ($adminModel) {
            return $this->jsonExit(202, '该账号已经存在或删除锁定');
        }
        $adminModel = AdminModel::create([
            'username' => $username,
            'password' => Hash::make($password),
            'safepassword' => Hash::make($password),
            'register_time' => CORE_TIME,
            'last_login' => CORE_TIME,
            'last_city' => H::Ip2City(IP),
            'status' => $status,
            'supper' => $supper,
            'remark' => $remark
        ]);
        AdmRoleUserModel::updateOrCreate([
            'user_id' => $adminModel->id,
            'role_id' => $role
        ], [
            'user_id' => $adminModel->id,
            'role_id' => $role
        ]);
        //记录管理员日志日志
        LogUserModel::gainLog($this->uid, '添加管理员', '---', '添加管理员' . $username, '后台添加管理员，添加人：' . $this->user->username, 1, 1);
        return $this->jsonExit(200, 'OK');
    }

    public function adminRoleUpdate(Request $request)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        $role_id = $request->input('role_id', 0);
        $adm_id = $request->input('adm_id', 0);
        $adminModel = AdminModel::find($adm_id);
        if (!$adminModel) {
            return $this->jsonExit(201, '用户不存在');
        }
        AdmRoleUserModel::where('user_id', $adm_id)->delete();
        AdmRoleUserModel::insert([
            'user_id' => $adm_id,
            'role_id' => $role_id
        ]);
        return $this->jsonExit(200, 'OK');
    }

    //角色列表&添加&删除
    public function roleList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $data = AdmRoleListModel::getPageAdminItems($page, $size);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function roleAdd(Request $request)
    {
        $data = $request->all();
        if (!isset($data['name']) || empty($data['name'])) {
            return $this->jsonExit(201, '角色名称不能为空');
        }
        if (!isset($data['str']) || empty($data['str'])) {
            return $this->jsonExit(202, '角色英文拼音不能为空');
        }
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }
        $exit = AdmRoleListModel::where('name', $data['name'])->orWhere('str', $data['str'])->first();
        if ($exit) {
            return $this->jsonExit(203, '角色名称或拼写重复，请检查');
        }
        $item = [
            'name' => $data['name'],
            'str' => $data['str'],
            'created_at' => CORE_TIME,
            'updated_at' => CORE_TIME,
        ];
        AdmRoleListModel::insert($item);
        return $this->jsonExit(200, 'OK');
    }

    public function roleDelete(Request $request)
    {
        $vote = AdmRoleModel::getRoleRightByUserId($this->uid);
        if (!$vote) {
            return $this->jsonExit(203, '您暂无权限进行此操作');
        }

        $id = $request->input('id', 1);
        $model = AdmRoleListModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '记录不存在');
        }
        //查询该角色下用户
        $list = AdmRoleUserModel::where('role_id', $id)->get();
        if (!$list->isEmpty()) {
            return $this->jsonExit(202, '该角色下存在用户，请处理完成后再删除');
        }
        //删除节点
        AdmRoleModel::where('role_id', $id)->delete();
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }

    //节点列表&添加&删除
    public function nodeList(Request $request)
    {
        $role = $request->input('role', 0);
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $data = AdmNodeModel::getPageAdminItems($page, $size, $role);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function nodeAdd(Request $request)
    {
        $data = $request->all();
        if (!isset($data['title']) || empty($data['title'])) {
            return $this->jsonExit(201, '节点名称不能为空');
        }
        if (!isset($data['url']) || empty($data['url'])) {
            return $this->jsonExit(201, '节点地址不能为空');
        }
        if (!isset($data['level']) || empty($data['level'])) {
            return $this->jsonExit(201, '菜单类型不能为空');
        }
        if (!isset($data['vue_name']) || empty($data['vue_name'])) {
            return $this->jsonExit(201, '对应vue名称不能为空');
        }
        $item = [
            'title' => $data['title'],
            'url' => $data['url'],
            'sort_order' => $data['sort_order'],
            'pid' => $data['pid'],
            'vue_name' => $data['vue_name'],
            'created_at' => CORE_TIME,
            'updated_at' => CORE_TIME,
        ];
        AdmNodeModel::insert($item);
        return $this->jsonExit(200, 'OK');
    }

    public function nodeDelete(Request $request)
    {
        $id = $request->input('id', 1);
        $model = AdmNodeModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '记录不存在');
        }
        //查询该角色下用户
        $list = AdmRoleModel::where('node_id', $id)->get();
        if (!$list->isEmpty()) {
            return $this->jsonExit(202, '存在正在使用的节点，请处理完成后再删除');
        }
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }

    //删除角色权限
    public function roleNodeDelete(Request $request)
    {
        $id = $request->input('id', 1);
        $role_id = $request->input('role_id', 0);
        $exit = AdmRoleModel::where([['node_id', $id], ['role_id', $role_id]])->first();
        if (!$exit) {
            return $this->jsonExit(201, '记录不存在');
        }
        $exit->delete();
        return $this->jsonExit(200, 'OK');
    }

    public function roleNodeList(Request $request)
    {
        $role_id = $request->input('role_id', 0);
        $nodes = AdmNodeModel::getAvilableNodes($role_id);
        return $this->jsonExit(200, 'OK', $nodes);
    }

    public function roleNodeAdd(Request $request)
    {
        $role_id = $request->input('role_id', 0);
        $node_id = $request->input('node_id', 0);
        $nodes = AdmRoleModel::updateOrCreate([
            'role_id' => $role_id,
            'node_id' => $node_id
        ], [
            'role_id' => $role_id,
            'node_id' => $node_id
        ]);
        return $this->jsonExit(200, 'OK', $nodes);
    }

    /**
     * 图片上传
     */
    public function uploadImg(Request $request, $dir = 'appicon')
    {
        $file = $request->file('file');
        $local_size = $is_video = 0;
        $ext = $file->getClientOriginalExtension();

        $localPath = $dir . DIRECTORY_SEPARATOR . date('Ymd') . DIRECTORY_SEPARATOR . date('H');
        $joinDir = storage_path('app/public/') . $localPath;
        $name = uniqid() . '.' . $ext;
        $localRule = $localPath . DIRECTORY_SEPARATOR . $name;
        //上传阿里OS
        $checkBase = SettingsModel::getSigConf('check');
        $aliyunOs = isset($checkBase['aliyun']) && $checkBase['aliyun'] == 1;
        $mime = $file->getClientMimeType();
        $size = $file->getClientSize();
        $file_tmp_path = $file->getRealPath();
        //判断文件超限 视频最大20M 图片最大5M
        if (H::videoIs($mime)) {
            $is_video = 1;
            if ($size >= 20 * 1024 * 1024) {
                throw new \Exception(trans('album.file_size_err'));
            }
        } else {
            if ($size >= 5 * 1024 * 1024) {
                throw new \Exception(trans('album.image_size_err'));
            }
        }
        if ($aliyunOs) {
            $aliyunBuket = $dir . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('md') . DIRECTORY_SEPARATOR . $name;
            $localRule = (AliyunOss::getInstance())->uploadToOss($aliyunBuket, $file_tmp_path);
            if (config('app.cnd_on')) {
                $localRule = str_replace(config('app.cdn_source_url'), config('app.cdn_url'), $localRule);
            }
        } else { //制作路径
            H::mkdirs($joinDir);
            $img_path = $joinDir . DIRECTORY_SEPARATOR . $name;
            //接收文件裁剪并保存
            if (H::imageIs($mime)) {
                Image::make($file)->save($img_path);
                $local_size = filesize($img_path);
            } else {
                //音频及视频的处理
                $is_video = 1;
                move_uploaded_file($file_tmp_path, $img_path);
            }
        }
        $usefor = 'album';
        //入库图片资源
        $insertArr = [
            'user_id' => 0,
            'used' => 1,
            'usefor' => $usefor,
            'location' => $aliyunOs ? 'aliyun' : 'local',
            'type' => $mime,
            'size' => $size,
            'local_size' => $local_size,
            'is_video' => $is_video,
            'path' => $localRule,
            'user_ip' => IP,
            'created_at' => CORE_TIME,
            'updated_at' => CORE_TIME
        ];

        if (in_array($usefor, ['album', 'auth'])) {
            //逻辑判断图片是不是本人  只有个人中心的相册才对比真人
            if ($usefor == 'auth') {
                $insertArr['used'] = 0;
            }
            $insertArr['is_real'] = 0;
            $insertArr['processed'] = 0;
            $insertId = AlbumModel::insertGetId($insertArr);
        }
        $data = [
            'id' => $insertId,  //如果是问题反馈就直接不入库
            'path' => $localRule,
            'name' => $name,
            'usefor' => $usefor,
            'is_real' => 0,
            'img_url' => H::path($localRule)
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    //新增客服相关内容
    public function serverInfoGet(Request $request)
    {
        $base = config('latrell-rcloud');
        $data = [
            'service_account' => $base['app_key'],
            'service_uid' => $base['service_uid'],
            'service_nick' => $base['service_nick'],
            'service_token' => $base['service_token'],
            'service_avatar' => $base['service_avatar'],
        ];
        return $this->jsonExit(200, 'OK', $data);
    }

    public function serverUserList(Request $request)
    {
        $id = $request->input('id');
        $data = [];
        $info = S::imInfoGetOne($id);
        if ($info) {
            $item = json_decode($info, 1);
            $data['id'] = $item['user_id'];
            $data['nickname'] = $item['nick'];
            $data['portraitUri'] = $item['avatar'];
        }
        return $this->jsonExit(200, 'OK', $data);
    }
}
