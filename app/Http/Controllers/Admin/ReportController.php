<?php

namespace App\Http\Controllers\Admin;

use App\Components\ESearch\ESearch;
use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Report\ReportDailyModel;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\CommonModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Admin\ActiveLogModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Resource\{AlbumModel, AvatarModel, UploadModel};
use App\Http\Models\SettingsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RongCloud;

class ReportController extends AuthAdmController
{
    public function dailyReport(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $date = $request->input('date', []);
        $data = ReportDailyModel::getAdminPageItems($page, $size, $date);
        return $this->jsonExit(200, 'OK', $data);
    }
}
