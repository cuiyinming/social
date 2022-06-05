<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\{Discover\DiscoverZanCmtModel,
    Discover\DiscoverZanModel,
    Logs\LogBrowseModel,
    Logs\LogContactUnlockModel
};
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersBlackListModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BrowseController extends AuthAdmController
{
    public function listBrowse(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = LogBrowseModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function listContact(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = LogContactUnlockModel::getDataByPage($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);

    }

    public function listZanDiscover(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $data = DiscoverZanModel::getDataByPage($page, $size, $q, $status);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function listZanCmt(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $data = DiscoverZanCmtModel::getDataByPage($page, $size, $q, $status);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function listBlackUser(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $status = $request->input('status');
        $data = UsersBlackListModel::getDataByPage($page, $size, $q, $status);
        return $this->jsonExit(200, 'OK', $data);

    }
}
