<?php

namespace App\Http\Controllers\Discover;

use App\Http\Helpers\H;
use App\Http\Models\BannersModel;
use App\Http\Models\Discover\DiscoverTopicModel;
use App\Http\Models\Discover\DiscoverTopicUserModel;
use App\Http\Models\Lib\LibCountriesModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Controllers\AuthController;
use App\Http\Models\MessageModel;
use App\Http\Libraries\Tools\{ApplePay, BaiduCloud};
use App\Http\Models\EsDataModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TopicController extends AuthController
{
    //话题列表
    public function topicList(Request $request)
    {
        $db = $request->input('db', 'es');
        $page = 1;
        $from = $request->input('from', 'recommend');
        $q = $request->input('q');
        if ($from == 'recommend') {
            $size = 100;
        } elseif ($from == 'hot') {
            $size = 5;
        } else {
            $size = 20;
        }
        $sortArr = [];
        $sortArr['created_at'] = 1; //0不排序 1倒序 2正序
        $sortArr['total'] = 1; //0不排序 1倒序 2正序
        $sort = $this->getSort($sortArr);
        if ($db == 'es') {
            $data = EsDataModel::getEsTopic($q, $sort, $page, $size, $this->uid);
        } else {
            $data = DiscoverTopicModel::getPageItems($page, $size, $q, $this->uid);
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    //关注和取消关注指定话题
    public function topicFollow(Request $request)
    {
        $stid = $request->input('stid', '');
        $status = $request->input('status', 1);
        $topic = DiscoverTopicModel::where([['stid', $stid], ['status', 1]])->first();
        if (!$topic) {
            return $this->jsonExit(201, '话题不存在');
        }
        try {
            DB::beginTransaction();
            DiscoverTopicUserModel::batchIntoFollow($this->uid, $stid, $status);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(202, $e->getMessage());
        }
        return $this->jsonExit(200, 'OK');
    }

    public function userTopic(Request $request)
    {
        $page = 1;
        $size = $request->input('size', 9);
        $data = DiscoverTopicUserModel::getUserTopic($this->uid, $size, $page);
        return $this->jsonExit(200, 'OK', $data);
    }
}
