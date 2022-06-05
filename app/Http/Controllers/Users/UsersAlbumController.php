<?php

namespace App\Http\Controllers\Users;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Libraries\Tools\AliyunOss;
use App\Http\Libraries\Tools\GraphCompare;
use App\Http\Controllers\AuthController;
use App\Http\Models\SettingsModel;
use Illuminate\Support\Facades\DB;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Resource\{AlbumModel, UploadModel, AvatarModel};
use App\Http\Models\{CommonModel,
    Logs\LogAlbumBuyModel,
    Logs\LogAlbumViewModel,
    Logs\LogBalanceModel,
    MessageModel,
    Users\UsersModel,
    Users\UsersMsgModel,
    Users\UsersRewardModel,
    Users\UsersSettingsModel
};
use App\Http\Models\Logs\LogChangeModel;
use Illuminate\Http\Request;
use Image;

class UsersAlbumController extends AuthController
{
    /* * 图片上传 */
    public function uploadImg(Request $request, $dir = 'appicon')
    {
        $tt = microtime(1);
        $usefor = $request->input('ufor', 'avatar');
        if (!in_array($dir, ['album', 'auth', 'upload', 'feedback', 'avatar', 'background'])) {  //一共三个目录 认证 动态 头像 问题反馈 背景图设置
            return $this->jsonExit(202, '目录不合法');
        }
        if (!in_array($usefor, ['album', 'auth', 'upload', 'sound', 'feedback', 'avatar', 'background'])) {  //用途 album 相册 auth 认证 upload 动态 背景图设置
            return $this->jsonExit(202, '用途不合法');
        }
        $data = [];
        //验签
        if ($this->uid == 0 && $dir == 'avatar') {
            $token = $request->input('token');
            if (empty($token)) {
                return $this->jsonExit(204, '上传凭证缺失');
            }
            $token = H::deciphering($token);
            if (stripos($token, '_') === false) {
                return $this->jsonExit(204, '上传凭证无效');
            }
            $expArr = explode('_', $token);
            if ($expArr[1] < (time() - 500)) {
                return $this->jsonExit(204, '凭证过期请重新获取');
            }
        }
        try {
            $files = $request->file('file');
            $prcessFile = function ($file) use ($dir, $usefor) {
                $local_size = $is_video = 0;
                $ext = $file->getClientOriginalExtension();
                $localPath = $dir . DIRECTORY_SEPARATOR . date('Ymd') . DIRECTORY_SEPARATOR . date('H');
                $joinDir = storage_path('app/public/') . $localPath;
                if ($ext == 'gif') {
                    throw new \Exception('gif动图暂不允许上传');
                }
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
                        throw new \Exception('视频音频文件不能超过20M');
                    }
                }
                if (H::soundIs($mime)) {
                    $is_video = 2;
                    if ($size >= 15 * 1024 * 1024) {
                        throw new \Exception('音频文件不能超过15M');
                    }
                } else {
                    if ($size >= 5 * 1024 * 1024) {
                        throw new \Exception('图片大小不能超过5M');
                    }
                }

                if ($aliyunOs) {
                    if ($dir == 'upload' && $usefor == 'sound') {
                        $dir = 'sound';
                    }
                    $aliyunBuket = $dir . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('md') . DIRECTORY_SEPARATOR . $name;
                    $img_path = '/tmp/album/' . $name;
                    H::mkdirs(dirname($img_path));
                    move_uploaded_file($file_tmp_path, $img_path);
                    $setting = config('settings.upload_oss');
                    if ($setting == 'async' && $dir == 'upload') {
                        \App\Jobs\asyncUpload::dispatch($aliyunBuket, $img_path)->onQueue('im');
                        $localRule = config('app.cdn_source_url') . DIRECTORY_SEPARATOR . $aliyunBuket;
                    } else {
                        $localRule = (AliyunOss::getInstance())->uploadToOss($aliyunBuket, $img_path);
                    }
                    if (config('app.cnd_on')) {
                        $localRule = str_replace(config('app.cdn_source_url'), config('app.cdn_url'), $localRule);
                    }
                    //这里加入安全监测
                } else {
                    //制作路径
                    H::mkdirs($joinDir);
                    $img_path = $joinDir . DIRECTORY_SEPARATOR . $name;
                    //接收文件裁剪并保存
                    if (H::imageIs($mime)) {
                        Image::make($file)->save($img_path);
                        $local_size = filesize($img_path);
                    } elseif (H::videoIs($mime)) {
                        //音频及视频的处理
                        $is_video = 1;
                        move_uploaded_file($file_tmp_path, $img_path);
                    } else {
                        $is_video = 2;
                        move_uploaded_file($file_tmp_path, $img_path);
                    }
                }
                try {
                    //审核
                    $setting = config('settings.scan_type');
                    if ($setting == 'sync') {
                        CommonModel::greenScan($this->uid, $is_video, $localRule);
                    }
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage());
                }
                //入库图片资源
                $insertArr = [
                    'user_id' => $this->uid,
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
                $realCheck = $insertId = $processed = 0;
                if (in_array($usefor, ['auth', 'avatar'])) {
                    //逻辑判断图片是不是本人  只有个人中心的相册才对比真人
                    $checkSetting = SettingsModel::getSigConf('check');
                    //如果开启且认证过了就判断 & 同时指定必须是图片资源
                    if (isset($checkSetting['real_check']) && $checkSetting['real_check'] == 1 && $dir == 'avatar' && stripos($mime, 'image') !== false && $this->uid > 0) {
                        $userProfileModel = UsersProfileModel::where('user_id', $this->uid)->first();
                        $srcPic = $userProfileModel->auth_pic ? $userProfileModel->auth_pic : '';
                        if (!empty($srcPic) && $userProfileModel->real_is == 1) {
                            //阿里云两张图片对比接口
                            $res = (new GraphCompare())->faceCheck($srcPic, H::path($localRule));
                            if ($res > 80) {  //大于80 判定为本人
                                $realCheck = 1;
                            }
                            $processed = 1;
                        }
                    }
                    if ($usefor == 'auth') {
                        $insertArr['used'] = 0;
                    }
                    $insertArr['is_real'] = $realCheck;
                    $insertArr['processed'] = $processed;
                    $insertId = AvatarModel::insertGetId($insertArr);
                }
                //相册
                if (in_array($usefor, ['album', 'background'])) {
                    //在这里查询操作的人是否是vip
//                    if ($usefor == 'background') {
//                        $profile = UsersProfileModel::where('user_id', $this->uid)->first();
//                        if ($profile->vip_is != 1 || $profile->vip_level < 10) {
//                            return $this->jsonExit(80000, 'OK');
//                        }
//                    }
                    $insertId = AlbumModel::insertGetId($insertArr);
                }
                //动态
                if ($usefor == 'upload' || $usefor == 'video') {
                    $insertId = UploadModel::insertGetId($insertArr);
                }
                return [
                    'resource_id' => $insertId,  //如果是问题反馈就直接不入库
                    'path' => $localRule,
                    'name' => $name,
                    'usefor' => $usefor,
                    'is_real' => $realCheck,
                    'img_url' => H::path($localRule)
                ];
            };

            if (is_array($files)) {
                foreach ($files as $file) {
                    $data[] = $prcessFile($file);
                }
            } else {
                $data[] = $prcessFile($files);
            }

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    //查看相册图片
    public function albumGet(Request $request)
    {
        // 2 实名认证 6 真人认证
        $profile = UsersProfileModel::where('user_id', $this->uid)->first();
        $auth = true;
        $jump = 0;
        if ($profile->real_is == 0 || $profile->identity_is == 0) {
            $auth = !config('settings.burn_limit');
        }
        if ($profile->real_is == 0) {
            $jump = 6;
        }
        if ($profile->identity_is == 0) {
            $jump = 2;
        }
        $res = [
            'burn' => [
                'tip_str' => !$auth ? '实名及真人认证后可开启阅后即焚功能' : '',
                'burn' => $auth,
                'jump' => !$auth ? [] : UsersMsgModel::schemeUrl('', $jump, '立即认证', 0, '立即认证'),
            ],
        ];
        $albums = $profile->album;
        if (!empty($albums)) {
            foreach ($albums as $k => $items) {
                if ($items['is_illegal'] == 1) $albums[$k]['img_url'] = H::errUrl('album');
            }
            $profile->album = $albums;
        }
        $res['album'] = $albums;
        $base = VER > 1.9 ? $res : $albums;
        return $this->jsonExit(200, 'OK', $base);
    }

    //查看视频相册
    public function albumVideoGet(Request $request)
    {
        $profileModel = UsersProfileModel::where('user_id', $this->uid)->first();
        $albums = $profileModel->album_video;
        if (!empty($albums)) {
            foreach ($albums as $ks => $items) {
                if ($items['is_illegal'] == 1) $albums[$ks]['img_url'] = H::errUrl('album');
            }
            $profileModel->album_video = $albums;
        }
        return $this->jsonExit(200, 'OK', $albums);
    }

    //更新编辑相册 目前覆盖到的情况是 相册不变编辑顺序 + 相册删除 + 相册增加
    public function albumEdit(Request $request)
    {
        if (!$request->has('album')) {
            return $this->jsonExit(201, '参数错误');
        }
        $albums = $request->input('album');
        $albums = !empty($albums) ? $albums : [];
        //获取相册并进行排序操作
        $userProfileModel = UsersProfileModel::where('user_id', $this->uid)->first();
        $userAlbums = $userProfileModel->album ?: [];
        $newSort = $oldSort = $newAppend = $newAppendPrivate = [];
        if ($userAlbums) {
            foreach ($userAlbums as $userAlbum) {
                $oldSort[$userAlbum['id']] = $userAlbum;
            }
        }

        foreach ($albums as $album) {
            $albumArr = json_decode($album, 1);
            if (isset($albumArr['id']) && $albumArr['id'] > 0) {
                if (isset($oldSort[$albumArr['id']])) {
                    //追加隐私设置 [原来的数组是完整的]
                    $newItem = $oldSort[$albumArr['id']];
                    $newItem['is_private'] = $albumArr['private'] ?? 0;
                    $newSort[] = $newItem;
                } else {
                    $newAppend[] = $albumArr['id'];
                    $newAppendPrivate[$albumArr['id']] = $albumArr['private'] ?? 0;
                }
            }
        }
        //如果老的数组没有则为新增需要查询并压入数组
        if (count($newAppend) > 0) {
            $newAppendArr = AlbumModel::select(['id', 'path', 'location', 'is_real', 'is_private', 'price', 'is_free', 'is_video', 'is_illegal'])->where('user_id', $this->uid)->whereIn('id', $newAppend)->get();
            if (!$newAppendArr->isEmpty()) {
                //获取原始相册信息
                foreach ($newAppendArr as $append) {
                    if (in_array($append->id, $oldSort)) continue;
                    $newSort[] = [
                        'id' => $append->id,
                        'img_url' => H::path($append->path),
                        'price' => $append->price, //价格在非免费时生效
                        'is_real' => $append->is_real,
                        'is_private' => $newAppendPrivate[$append->id] ?? $append->is_private,  //隐私做模糊处理
                        'is_free' => $append->is_free, //是否免费相册
                        'is_video' => $append->is_video, //是否视频
                        'is_illegal' => $append->is_illegal, //是否非法
                    ];
                }
            }
        }
        $max_album = config('settings.album_max');
        if (count($newSort) > $max_album) {
            return $this->jsonExit(202, '相册照片最大不能超过' . $max_album . '张');
        }
        $userProfileModel->album = $newSort;
        $userProfileModel->save();
        //进入机器审核异步进程
        $setting = config('settings.scan_type');
        if ($setting == 'async' || true) {
            \App\Jobs\greenScan::dispatch($userProfileModel, 'album', $newAppend, $this->sex)->delay(now()->addSeconds(15))->onQueue('register');
        }
        //发放奖励
        if (count($newSort) >= 4) {
            UsersRewardModel::userRewardSet($this->uid, 'xiangce');
        }
        //更新用户相册数目到ES
        $album_count = count($newSort);
        HR::userAlbumNumUpdate($this->uid, $album_count, true);
        if ($album_count > 0) LogChangeModel::gainLog($this->uid, 'album', json_encode($newSort));
        return $this->jsonExit(200, 'OK');
    }

    //更新编辑视频
    public function albumVideoEdit(Request $request)
    {
        if (!$request->has('album')) {
            return $this->jsonExit(201, '参数错误');
        }
        $albums = $request->input('album');
        $albums = !empty($albums) ? $albums : [];
        //获取相册并进行排序操作
        $userProfileModel = UsersProfileModel::where('user_id', $this->uid)->first();
        $userAlbums = $userProfileModel->album_video ? $userProfileModel->album_video : [];
        $newSort = $oldSort = [];
        if ($userAlbums) {
            foreach ($userAlbums as $userAlbum) {
                $oldSort[$userAlbum['id']] = $userAlbum;
            }
        }
        $postIdArr = [];
        try {
            foreach ($albums as $album) {
                $albumArr = json_decode($album, 1);
                if (isset($albumArr['id']) && $albumArr['id'] > 0 && isset($oldSort[$albumArr['id']])) {
                    $newSort[] = $oldSort[$albumArr['id']];
                }
                if (isset($albumArr['id']) && $albumArr['id'] > 0) {
                    $postIdArr[] = $albumArr['id'];
                }
            }
            //全部视频处理 /判断是不是有视频文件存在
            $videoFind = AlbumModel::where('is_video', 1)->whereIn('id', $postIdArr)->orderBy('id', 'desc')->get();
            if (!$videoFind->isEmpty()) {
                foreach ($videoFind as $video) {
                    $videoMap = [
                        'id' => $video->id,
                        'img_url' => H::path($video->path),
                        'price' => $video->price, //价格在非免费时生效
                        'is_real' => $video->is_real,
                        'is_private' => $video->is_private,  //隐私做模糊处理
                        'is_free' => $video->is_free, //是否免费相册
                        'is_video' => $video->is_video, //是否视频
                        'is_illegal' => $video->is_illegal, //是否非法
                    ];
                    if (!empty($newSort)) {
                        if (!in_array($video->id, array_column($newSort, 'id'))) {
                            array_push($newSort, $videoMap);
                        }
                    } else {
                        $newSort[] = [
                            'id' => $video->id,
                            'img_url' => H::path($video->path),
                            'price' => $video->price, //价格在非免费时生效
                            'is_real' => $video->is_real,
                            'is_private' => $video->is_private,  //隐私做模糊处理
                            'is_free' => $video->is_free, //是否免费相册
                            'is_video' => $video->is_video, //是否视频
                            'is_illegal' => $video->is_illegal, //是否非法
                        ];
                    }
                }

            }
            $userProfileModel->album_video = $newSort;
            $userProfileModel->save();
            //进入机器审核异步进程
            $setting = config('settings.scan_type');
            if ($setting == 'async') {
                \App\Jobs\greenScan::dispatch($userProfileModel, 'album_video')->delay(now()->addSeconds(15))->onQueue('register');
            }
            if (count($newSort) > 0) LogChangeModel::gainLog($this->uid, 'video', json_encode($newSort));

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }


    //删除相册
    public function userAlbumDelete(Request $request)
    {
        if (!$request->has('album')) {
            return $this->jsonExit(202, '参数错误');
        }
        $albums = $request->input('album', []);
        try {
            DB::beginTransaction();
            $idArr = [];
            if (!empty($albums) && count($albums) > 0) {
                foreach ($albums as $datum) {
                    $item = @json_decode($datum, 1);
                    $idArr[] = isset($item['id']) ? $item['id'] : 0;
                }
                //更新使用状态
                AlbumModel::whereIn('id', $idArr)->where('user_id', $this->uid)->update(['used' => 0]);
            }

            //更新相册照片
            $userProfileModel = UsersProfileModel::where('user_id', $this->uid)->first();
            $album = $userProfileModel->album ? $userProfileModel->album : [];
            if ($album) {
                foreach ($album as $key => $item) {
                    if (in_array($item['id'], $idArr)) {
                        unset($album[$key]);
                    }
                }
            }
            $userProfileModel->album = count($album) > 0 ? array_values($album) : [];
            $userProfileModel->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }


    public function userVideoDelete(Request $request)
    {
        if (!$request->has('album')) {
            return $this->jsonExit(202, '参数错误');
        }
        $albums = $request->input('album', []);
        try {
            DB::beginTransaction();
            $idArr = [];
            if (!empty($albums) && count($albums) > 0) {
                foreach ($albums as $datum) {
                    $item = @json_decode($datum, 1);
                    $idArr[] = isset($item['id']) ? $item['id'] : 0;
                }
                //更新使用状态
                AlbumModel::whereIn('id', $idArr)->where('user_id', $this->uid)->update(['used' => 0]);
            }

            //更新相册照片
            $userProfileModel = UsersProfileModel::where('user_id', $this->uid)->first();
            $album_video = $userProfileModel->album_video ? $userProfileModel->album_video : [];
            if (!empty($album_video)) {
                foreach ($album_video as $key => $item) {
                    if (in_array($item['id'], $idArr)) {
                        unset($album_video[$key]);
                    }
                }
            }
            $userProfileModel->album_video = count($album_video) > 0 ? array_values($album_video) : [];
            $userProfileModel->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage() . $e->getLine());
        }
        return $this->jsonExit(200, 'OK');
    }

    //用户的图片完善
    public function userAlbumComplete(Request $request)
    {
        $type = $request->input('type', 'album');
        if (!in_array($type, ['album', 'album_video'])) {
            return $this->jsonExit(201, '参数类型错误');
        }
        if (!$request->has('album')) {
            return $this->jsonExit(202, '参数错误');
        }
        $albums = $request->input('album', []);
        try {
            DB::beginTransaction();
            if (!empty($albums) && count($albums) > 0) {
                foreach ($albums as $dat => $datum) {
                    $albums[$dat] = @json_decode($datum, 1);
                }
                //更新相册的数据库属性[采用批量更新的方式处理]
                AlbumModel::batchUpdateAlbum($albums);

                //这里直接存全部信息
                $albumIdArr = array_column($albums, 'id');
                $albumArrs = AlbumModel::select(['id', 'path', 'location', 'is_real', 'is_private', 'price', 'is_free', 'is_video', 'is_illegal'])->where('user_id', $this->uid)->whereIn('id', $albumIdArr)->get();
                if (!$albumArrs->isEmpty()) {
                    //获取原始相册信息
                    $userProfileModel = UsersProfileModel::where('user_id', $this->uid)->first();
                    $album = is_array($userProfileModel->$type) ? $userProfileModel->$type : [];
                    $idArr = @array_column($album, 'id');
                    foreach ($albumArrs as $albumArr) {
                        if (in_array($albumArr->id, $idArr)) continue;
                        $album[] = [
                            'id' => $albumArr->id,
                            'img_url' => H::path($albumArr->path),
                            'price' => $albumArr->price, //价格在非免费时生效
                            'is_real' => $albumArr->is_real,
                            'is_private' => $albumArr->is_private,  //隐私做模糊处理
                            'is_free' => $albumArr->is_free, //是否免费相册
                            'is_video' => $albumArr->is_video, //是否视频
                            'is_illegal' => $albumArr->is_illegal, //是否非法
                        ];
                    }

                    $checkSetting = SettingsModel::getSigConf('check');
                    if (isset($checkSetting["{$type}_limit"]) && $checkSetting["{$type}_limit"] > 0 && count($album) > intval($checkSetting["{$type}_limit"])) {
                        return $this->jsonExit(202, "最大不能超过{$checkSetting["{$type}_limit"]}张|个");
                    }
                    $userProfileModel->$type = $album;
                    $userProfileModel->save();
                    DB::commit();
                    LogChangeModel::gainLog($this->uid, 'album', json_encode($album));

                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }


    //阅后即焚设置
    public function albumFireEdit(Request $request)
    {
        //0是图片相册 1是视频相册
        $type = $request->input('type', 'album');
        if (!in_array($type, ['album', 'album_video'])) {
            return $this->jsonExit(203, '类型错误');
        }
        if (!$request->has('album')) {
            return $this->jsonExit(201, '参数错误');
        }
        $albums = $request->input('album', []);
        if (!is_array($albums)) {
            return $this->jsonExit(202, '参数格式错误');
        }
        //获取相册并进行排序操作
        $userProfileModel = UsersProfileModel::where('user_id', $this->uid)->first();
        $userAlbums = $userProfileModel->$type;

        $newSort = $oldSort = $onIds = $offIds = [];
        if ($userAlbums) {
            foreach ($userAlbums as $userAlbum) {
                $oldSort[$userAlbum['id']] = $userAlbum;
            }
        }
        foreach ($albums as $album) {
            $albumArr = json_decode($album, 1);
            if (isset($albumArr['is_private']) && $albumArr['is_private'] == 1) {
                $onIds[] = $albumArr['id'];
            } else {
                $offIds[] = $albumArr['id'];
            }
            if (isset($albumArr['id']) && $albumArr['id'] > 0 && isset($oldSort[$albumArr['id']])) {
                //替换阅后即焚字段
                $oldSort[$albumArr['id']]['is_private'] = isset($albumArr['is_private']) ? $albumArr['is_private'] : 0;
                $oldSort[$albumArr['id']]['is_free'] = isset($albumArr['is_free']) ? $albumArr['is_free'] : 0;
                $oldSort[$albumArr['id']]['price'] = isset($albumArr['price']) ? $albumArr['price'] : 0;
                //$newSort[] = $oldSort[$albumArr['id']];
            }
        }
        $userProfileModel->$type = array_values($oldSort);
        $userProfileModel->save();
        //更新相册相关的裱
        AlbumModel::whereIn('id', $onIds)->update(['is_private' => 1]);
        AlbumModel::whereIn('id', $offIds)->update(['is_private' => 0]);
        return $this->jsonExit(200, 'OK');
    }

    public function albumView(Request $request)
    {
        $profile = UsersProfileModel::getUserInfo($this->uid);
        $album_id = $request->input('album_id', 0);
        //入库查看记录
        try {
            LogAlbumViewModel::updateOrCreate([
                'user_id' => $this->uid,
                'album_id' => $album_id,
            ], [
                'user_id' => $this->uid,
                'album_id' => $album_id,
                'view_at' => CORE_TIME,
                'price' => 0,
                'payed' => 0,
            ]);
            if ($profile->vip_is == 1) {
                HR::updateUniqueNum($this->uid, $album_id, 'album-view-ids');
            } else {
                HR::updateUniqueNum($this->uid, $album_id, 'album-view-ids', true, 864000); //10天
            }

        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }


    public function albumPrivateBuy(Request $request)
    {
        $user_id = $request->input('user_id', 0);
        $settings = UsersSettingsModel::getUserSettings($user_id);
        $profile = UsersProfileModel::getUserInfo($user_id);
        $user = UsersModel::getUserInfo($user_id);
        $price = $settings['album_private'];
        if ($price < 2) {
            return $this->jsonExit(201, '购买错误');
        }
        //入库查看记录
        $share_cost = $price * config('settings.album_private_benefit');
        $selfUser = UsersModel::getUserInfo($this->uid);
        if ($selfUser->sweet_coin < $price) {
            return $this->jsonExit(202, '友币不足');
        }
        try {
            LogAlbumBuyModel::updateOrCreate([
                'user_id' => $this->uid,
                'album_user' => $user_id,
            ], [
                'user_id' => $this->uid,
                'album_user' => $user_id,
                'cost' => $price,
                'share_cost' => $share_cost,
            ]);
            HR::updateUniqueNum($this->uid, $user_id, 'album-private-unlock', false);
            //扣除用户金币
            DB::beginTransaction();
            $before = $selfUser->sweet_coin;
            $selfUser->sweet_coin -= $price;
            $selfUser->save();
            $desc = '解锁用户' . $user_id . '相册';
            $remark = '解锁用户 ' . $user_id . ' 相册花费' . $price . '友币';
            LogBalanceModel::gainLogBalance($this->uid, $before, $price, $selfUser->sweet_coin, 'album_unlock', $desc, $remark);
            //给妹子分成
            //付费解锁分成 在这里处理解锁分成操作 [未认证不分成]
            $config = config('settings.album_private_benefit');
            $authed = $profile->real_is == 1 && $profile->identity_is == 1;
            if ($authed) {
                $get_price = floor($price * $config);
                $desc = "用户 {$this->uid} 付费解锁了您的相册，获得奖励 {$get_price} 心钻";
                $remark = "用户 {$this->uid} 付费解锁了您的相册，获得奖励 {$get_price} 心钻";
                $before = $user->jifen;
                $user->jifen += $get_price;
                $user->save();
                LogBalanceModel::gainLogBalance($user_id, $before, $get_price, $user->jifen, 'album_unlock', $desc, $remark, 0, 'log_jifen');
            }
            DB::commit();
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }
}
