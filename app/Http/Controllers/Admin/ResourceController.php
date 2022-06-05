<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Libraries\Tools\AliyunOss;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\Resource\{AvatarModel, AlbumModel, ResourceModel, UploadModel};
use Illuminate\Http\Request;

class ResourceController extends AuthAdmController
{
    public function listAlbum(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = AlbumModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function listUpload(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = UploadModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function listAvatar(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = AvatarModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function resourceUpdate(Request $request)
    {
        $stype = $request->input('stype');
        if (!in_array($stype, [1, 2, 3])) {
            return $this->jsonExit(201, '资源类型错误');
        }
        try {
            $source = null;
            $id = $request->input('id', 0);
            if ($stype == 1) $source = AlbumModel::find($id);
            if ($stype == 2) $source = AvatarModel::find($id);
            if ($stype == 3) $source = UploadModel::find($id);
            if (!$source) {
                return $this->jsonExit(202, '资源不存在');
            }
            //删除本地图片资源
            ResourceModel::deleteResource($source);
            $source->delete();
        } catch (\Exception $e) {
            return $this->jsonExit(208, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }
}
