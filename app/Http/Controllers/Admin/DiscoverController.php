<?php

namespace App\Http\Controllers\Admin;


use App\Components\ESearch\ESearch;
use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Models\Discover\DiscoverCmtModel;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Discover\DiscoverTopicModel;
use App\Http\Models\Discover\DiscoverTopicUserModel;
use App\Http\Models\Discover\DiscoverZanCmtModel;
use App\Http\Models\Discover\DiscoverZanModel;
use App\Http\Models\EsDataModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersSettingsModel;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\CommonModel;
use App\Http\Models\Login\LoginErrModel;
use App\Http\Models\Login\LoginLogModel;
use App\Http\Models\Admin\ActiveLogModel;
use App\Http\Models\Logs\LogSmsModel;
use App\Http\Models\Resource\{AlbumModel, AvatarModel, ResourceModel, UploadModel};
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RongCloud;

class DiscoverController extends AuthAdmController
{
    //获取动态列表
    public function discoverList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q'); //筛选用户
        $sex = $request->input('sex'); //性别
        $status = $request->input('status'); //状态
        $comment_on = $request->input('comment_on'); //评论
        $show_on = $request->input('show_on'); //同性展示
        $type = $request->input('type');  //类型
        $private = $request->input('private');
        $date = $request->input('dates', []);
        $id = $request->input('id');
        $discovers = DiscoverModel::getDataByPage($q, $status, $comment_on, $show_on, $date, $type, $sex, $private, $page, $size, $id);
        return $this->jsonExit(200, 'OK', $discovers);
    }

    public function topicList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q'); //筛选用户
        $status = $request->input('status'); //状态
        $date = $request->input('date', []); //状态
        $discovers = DiscoverTopicModel::getDataByPage($q, $status, $date, $page, $size);
        return $this->jsonExit(200, 'OK', $discovers);
    }

    //话题
    public function topicUpdate(Request $request)
    {
        $data = $request->all();
        $id = $request->input('id', 0);
        $act = $request->input('act', '');
        try {
            DB::beginTransaction();
            $model = DiscoverTopicModel::find($id);
            if (!$model) {
                return $this->jsonExit(201, '记录不存在，请检查');
            }
            if ($act == 'delete') {
                $model->delete();
                //删除相关
                DiscoverTopicUserModel::where('topic_id', $model->topic_id)->delete();
                DB::commit();
                (new ESearch('tags:tags'))->deleteSingle([['id' => $id]]);
                return $this->jsonExit(200, 'OK');
            }
            //更新
            $esVipArr[0]['id'] = $id;
            $esVipArr[0][$act] = $data[$act];
            (new ESearch('tags:tags'))->updateSingle($esVipArr);
            $model->$act = $data[$act];
            $model->save();
            DB::commit();
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(209, $e->getMessage());
        }
    }

    //动态信息更新
    public function discoverUpdate(Request $request)
    {
        $data = $request->all();
        $id = $request->input('id', 0);
        $act = $request->input('act', '');
        $status = $request->input('status', 0);
        try {
            DB::beginTransaction();
            $model = DiscoverModel::find($data['id']);
            if (!$model) {
                return $this->jsonExit(201, '记录不存在，请检查');
            }
            if ($act == 'delete') {
                //删除附件资源
                ResourceModel::deleteResource($model, 'discover');
                $model->delete();
                //删除相关评论
                DiscoverZanCmtModel::where('discover_id', $id)->delete();
                //删除点赞记录
                DiscoverZanModel::where('discover_id', $id)->delete();
                //删除评论相关的赞
                DiscoverCmtModel::where('discover_id', $id)->delete();
                DB::commit();
                // (new ESearch('discover:discover'))->deleteSingle([['id' => $data['id']]]);
                return $this->jsonExit(200, 'OK');
            }
            $model->$act = $status;
            $model->save();
            DB::commit();
            return $this->jsonExit(200, 'OK');
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
            return $this->jsonExit(209, $e->getMessage());
        }
    }

    /*-----*评论*------*/
    public function discoverCmt(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $post_id = $request->input('post_id');
        $id = $request->input('id');
        $q = $request->input('q');
        $status = $request->input('status');
        $data = DiscoverCmtModel::getDataByPage($page, $size, $status, $q, $post_id, $id);
        return $this->jsonExit(200, 'OK', $data);
    }

    //更新 & 删除
    public function discoverUpdateCmt(Request $request)
    {
        $val = $request->input('value');
        $id = $request->input('id');
        $act = $request->input('act', '');
        $discoverCmt = DiscoverCmtModel::find($id);
        if (!$discoverCmt) {
            return $this->jsonExit(201, '记录不存在');
        }
        if ($act == 'delete') {
            $discoverCmt->delete();
            //删除后更新评论数
            $discover = DiscoverModel::where('id', $discoverCmt->discover_id)->first();
            if ($discover) {
                $cmtNum = DiscoverCmtModel::where('discover_id', $discoverCmt->discover_id)->count();
                $discover->num_cmt = $cmtNum;
                $discover->save();
            }
            return $this->jsonExit(200, 'OK');
        }
        $discoverCmt->$act = $val;
        $discoverCmt->save();
        return $this->jsonExit(200, 'OK');
    }

    /*----* 点赞 *----*/
    public function discoverZan(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $post_id = $request->input('post_id');
        $data = DiscoverZanModel::getDataByPage($page, $size, $post_id, 1);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function setDiscoverIllegal(Request $request)
    {
        $userId = $request->input('user_id');
        $albumId = $request->input('album_id');
        $status = $request->input('status');
        $id = $request->input('id');
        $userModel = UsersModel::find($userId);
        if (!$userModel) {
            return $this->jsonExit(201, '用户不存在');
        }
        $uploadModel = UploadModel::find($albumId);
        if (!$uploadModel) {
            return $this->jsonExit(202, '图片不存在');
        }
        $dis = DiscoverModel::find($id);
        if (!$dis) {
            return $this->jsonExit(203, '动态不存在');
        }
        $uploadModel->is_illegal = $status == true ? 1 : 0;
        $uploadModel->save();
        $album = $dis->album;
        if ($album) {
            foreach ($album as $key => $albumArr) {
                if ($albumArr['id'] == $albumId) {
                    if ($status) UsersSettingsModel::setViolation($dis->user_id, 'violation_image');
                    $album[$key]['is_illegal'] = $status == true ? 1 : 0;
                }
            }
            //入库
            $dis->album = $album;
            $dis->save();
            //二次渲染
            foreach ($album as $k => $item) {
                $album[$k]['is_illegal'] = isset($item['is_illegal']) && $item['is_illegal'] == 1;
            }
        }
        return $this->jsonExit(200, 'OK', $album);
    }
}
