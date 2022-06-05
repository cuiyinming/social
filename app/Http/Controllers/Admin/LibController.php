<?php

namespace App\Http\Controllers\Admin;


use App\Components\ESearch\ESearch;
use App\Http\Controllers\Admin\AuthAdmController;
use App\Http\Models\Lib\LibBannersModel;
use App\Http\Models\Lib\LibBioModel;
use App\Http\Models\Lib\LibChatModel;
use App\Http\Models\Lib\LibCodeModel;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\Lib\LibNickModel;
use App\Http\Models\Lib\LibQuestionsModel;
use App\Http\Models\Logs\LogActionModel;
use App\Http\Models\Logs\LogChangeModel;
use App\Http\Models\Logs\LogPushModel;
use App\Http\Models\Logs\LogUserModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Helpers\{R, H, HR};
use App\Http\Models\CommonModel;
use App\Http\Models\SettingsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LibController extends AuthAdmController
{
    private $del = false;

    public function libChatList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type');
        $sex = $request->input('sex');
        $var = $request->input('var');
        $period = $request->input('period');
        $q = $request->input('q');
        $data = LibChatModel::getAdminPageItems($page, $size, $q, $type, $sex, $period, $var);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function libChatAdd(Request $request)
    {
        $data = $request->all();
        if (empty($data['advice'])) {
            return $this->jsonExit(201, '内容不能为空');
        }
        $sign = md5(json_encode($data));
        if (LibChatModel::where('sign', $sign)->first()) {
            return $this->jsonExit(202, '已存在相同内容');
        }
        $data['sign'] = $sign;
        LibChatModel::create($data);
        return $this->jsonExit(200, 'OK');
    }

    public function libChatDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $model = LibChatModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '记录不存在');
        }
        if (!$this->del) {
            return $this->jsonExit(203, '暂不支持删除');
        }
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }


    /*-----签名管理-----*/
    public function libBioList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $data = LibBioModel::getAdminPageItems($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function libBioAdd(Request $request)
    {
        $data = $request->all();
        if (empty($data['content'])) {
            return $this->jsonExit(201, '内容不能为空');
        }
        if (stripos($data['title'], '歌词') !== false) {
            $data['bottom_slogan'] = '点击按键开始唱歌';
        }
        if (stripos($data['title'], '读一段话') !== false) {
            $data['bottom_slogan'] = '点击按键开始朗读或自由发挥';
        }
        $sign = md5(json_encode($data));
        if (LibBioModel::where('sign', $sign)->first()) {
            return $this->jsonExit(202, '已存在相同内容');
        }
        $data['sign'] = $sign;
        LibBioModel::create($data);
        return $this->jsonExit(200, 'OK');
    }

    public function libBioDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $model = LibBioModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '记录不存在');
        }
        if (!$this->del) {
            return $this->jsonExit(203, '暂不支持删除');
        }
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }

    /*-----昵称管理-----*/
    public function libNickList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $sex = $request->input('sex');
        $data = LibNickModel::getAdminPageItems($page, $size, $q, $sex);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function libNickAdd(Request $request)
    {
        $data = $request->all();
        if (empty($data['nick'])) {
            return $this->jsonExit(201, '内容不能为空');
        }
        $sign = md5($data['nick']);
        if (LibNickModel::where('sign', $sign)->first()) {
            return $this->jsonExit(202, '已存在相同内容');
        }
        $data['sign'] = $sign;
        LibNickModel::create($data);
        return $this->jsonExit(200, 'OK');
    }

    public function libNickDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $model = LibNickModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '记录不存在');
        }
        if (!$this->del) {
            return $this->jsonExit(203, '暂不支持删除');
        }
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }


    /*-----常见问题管理-----*/
    public function libQuestionList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $type = $request->input('type');
        $data = LibQuestionsModel::getAdminPageItems($page, $size, $q, $type);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function libQuestionAdd(Request $request)
    {
        $data = $request->all();
        if (empty($data['answer']) || empty($data['question'])) {
            return $this->jsonExit(201, '问题和答案不能为空');
        }
        $sign = md5($data['question']);
        if (LibQuestionsModel::where('sign', $sign)->first()) {
            return $this->jsonExit(202, '已存在相同内容');
        }
        $map = [
            0 => '账号问题',
            1 => '功能问题',
            2 => '产品建议',
        ];
        $data['name'] = $map[$data['type']];
        $data['sign'] = $sign;
        $data['status'] = 1;
        LibQuestionsModel::create($data);
        return $this->jsonExit(200, 'OK');
    }

    public function libQuestionDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $act = $request->input('act', 'status');
        $status = $request->input('status', 1);
        $model = LibQuestionsModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '问题记录不存在');
        }
        if ($act == 'status') {
            $model->status = $status;
            $model->save();
            return $this->jsonExit(200, 'OK');
        }
        if (!$this->del) {
            return $this->jsonExit(203, '暂不支持删除');
        }
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }

    /*-----广告banner管理-----*/
    public function libBannerList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $position = $request->input('position');
        $data = LibBannersModel::getAdminPageItems($page, $size, $q, $position);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function libBannerAdd(Request $request)
    {
        $data = $request->all();
        if (empty($data['title']) || empty($data['cont'])) {
            return $this->jsonExit(201, '标题和内容不能为空');
        }
        $data['status'] = 1;
        LibBannersModel::create($data);
        return $this->jsonExit(200, 'OK');
    }

    public function libBannerDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $act = $request->input('act', 'status');
        $status = $request->input('status', 1);
        $model = LibBannersModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '问题记录不存在');
        }
        if ($act == 'status') {
            $model->status = $status;
            $model->save();
            return $this->jsonExit(200, 'OK');
        }
        if (!$this->del) {
            return $this->jsonExit(203, '暂不支持删除');
        }
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }

    /*-----礼物管理-----*/
    public function libGiftList(Request $request)
    {
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $q = $request->input('q');
        $type = $request->input('type');
        $jifen = $request->input('jifen');
        $data = LibGiftModel::getAdminPageItems($page, $size, $q, $type, $jifen);
        return $this->jsonExit(200, 'OK', $data);
    }

    public function libGiftUpdate(Request $request)
    {
        $data = $request->all();
        if (empty($data['name']) || empty($data['price'])) {
            return $this->jsonExit(201, '名称和价格不能为空');
        }
        //根据type_name 获取name
        $maps = LibGiftModel::getTypeMap();
        if (count($maps) > 0) {
            foreach ($maps as $map) {
                if ($map['type_id'] == $data['type_id']) {
                    $data['type_name'] = $map['type_name'];
                }
            }
        }
        $data['friendly'] = round($data['price'] / 10, 1);
        LibGiftModel::where('id', $data['id'])->update($data);
        return $this->jsonExit(200, 'OK');
    }

    public function libGiftDelete(Request $request)
    {
        $id = $request->input('id', 0);
        $act = $request->input('act', 'status');
        $status = $request->input('status', 1);
        $model = LibGiftModel::find($id);
        if (!$model) {
            return $this->jsonExit(201, '问题记录不存在');
        }
        if ($act == 'status') {
            $model->status = $status;
            $model->save();
            return $this->jsonExit(200, 'OK');
        }
        if (!$this->del) {
            return $this->jsonExit(203, '暂不支持删除');
        }
        $model->delete();
        return $this->jsonExit(200, 'OK');
    }


    /*-----会员兑换码------*/
    public function libCodeGain(Request $request)
    {
        $act = $request->input('act', '');
        if ($act == 'gain') {
            $level = $request->input('level', 0);
            LibCodeModel::gain($level);
        }
        if ($act == 'delete') {
            $id = $request->input('id', 0);
            $invite = LibCodeModel::find($id);
            if ($invite->user_id > 0) {
                return $this->jsonExit(201, '已经使用不能删除');
            }
            $invite->delete();
        }
        return $this->jsonExit(200, 'OK');
    }

    public function libCodeList(Request $request)
    {
        $q = $request->input('q');
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $data = LibCodeModel::getPageAdminItems($page, $size, $q);
        return $this->jsonExit(200, 'OK', $data);
    }
}
