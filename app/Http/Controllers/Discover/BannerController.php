<?php

namespace App\Http\Controllers\Discover;

use App\Http\Helpers\H;
use App\Http\Models\Lib\LibBannersModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersMsgGovModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BannerController extends Controller
{
    //banner净网
    public function getBanner($id, Request $request)
    {
        $data = LibBannersModel::where([['id', $id], ['status', 1]])->first();
        if (!$data) {
            return $this->jsonExit(201, '相关文章不存在');
        }
        return $this->jsonExit(200, 'OK', $data);
    }


    public function getGov($id, Request $request)
    {
        $data = UsersMsgGovModel::where([['id', $id], ['status', 1]])->first();
        if (!$data) {
            return $this->jsonExit(201, '相关文章不存在');
        }
        return $this->jsonExit(200, 'OK', $data);
    }

}
