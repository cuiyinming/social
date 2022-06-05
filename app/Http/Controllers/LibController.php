<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Libraries\Tools\AliyunCloud;
use App\Http\Models\EsDataModel;
use App\Http\Models\Lib\LibBannersModel;
use App\Http\Models\Lib\LibBioModel;
use App\Http\Models\Lib\LibBioTextModel;
use App\Http\Models\Lib\LibChatModel;
use App\Http\Models\Lib\LibCountriesModel;
use App\Http\Models\Lib\LibRegionModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Users\UsersMsgGovModel;
use App\Http\Models\Users\UsersMsgSysModel;
use App\Http\Models\Users\UsersSettingsModel;
use App\Http\Models\Users\UsersModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LibController extends Controller
{
    public function getCountries(Request $request)
    {
        $data = LibCountriesModel::where('status', 1)->orderBy('code', 'asc')->get()->map(function ($item) {
            $item->code_str = '+' . $item->code;
            return $item;
        });
        return $this->jsonExit(200, 'OK', $data);
    }

    public function profession()
    {
        $data = config('self.profession');
        return $this->jsonExit(200, 'OK', $data);
    }

    public function options()
    {
        $data = config('self.options');
        return $this->jsonExit(200, 'OK', $data);
    }

    public function textBio(Request $request)
    {
        $cat = $request->input('cat', '');
        $q = $request->input('q');
        $num = $request->input('num', 30);
        $num = $num > 30 ? 30 : $num;
        $bio = LibBioTextModel::getRandTextBio($num, $q, $cat);
        return $this->jsonExit(200, 'OK', $bio);
    }

    public function cities()
    {
        $data = LibRegionModel::getRegionMap();
        return $this->jsonExit(200, 'OK', array_values($data));
    }

    public function sounds()
    {
        $data = config('self.sounds');
        $bios = LibBioModel::orderBy(DB::Raw('RAND()'))->limit(30)->get();
        foreach ($bios as $bio) {
            $data['slogans'][] = [
                'title' => $bio->title,
                'content' => $bio->content,
                'bottom_slogan' => $bio->bottom_slogan
            ];
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    public function chatAdvice(Request $request)
    {
        $data = [];
        $user_id = Auth::id();
        $userData = LibChatModel::select(['id', 'advice', 'type'])->where([['type', 0], ['user_id', $user_id]])->get();
        if (!$userData->isEmpty()) {
            foreach ($userData as $user) {
                $data[] = $user->advice;
            }
        }
        $advices = LibChatModel::select(['id', 'advice', 'type'])->where('type', 1)->orderBy(DB::Raw('RAND()'))->limit(20)->get();
        if (!$advices->isEmpty()) {
            foreach ($advices as $advice) {
                $data[] = $advice->advice;
            }
        }
        return $this->jsonExit(200, 'OK', $data);
    }

    public function chatAdviceAdd(Request $request)
    {
        $advice = $request->input('advice');
        if (mb_strlen($advice) < 3) {
            return $this->jsonExit(201, '不能少于3个字');
        }
        //文本检测
        $res = (new AliyunCloud())->GreenScanText($advice);
        if ($res != 'pass') {
            return $this->jsonExit(204, '存在非法词汇，请检查');
        }
        $user_id = Auth::id();
        $sign = md5($advice . $user_id);

        $sum = LibChatModel::where([['user_id', $user_id], ['type', 0]])->count();
        if ($sum > 15) {
            return $this->jsonExit(202, '自定义快捷聊天不能超过15条');
        }
        $chat_add = UsersSettingsModel::getSingleUserSettings($user_id, 'chat_add');
        if ($chat_add == 0) {
            return $this->jsonExit(203, '自定义快捷聊天暂未开放');
        }
        LibChatModel::updateOrCreate([
            'sign' => $sign,
            'user_id' => $user_id
        ], [
            'sign' => $sign,
            'user_id' => $user_id,
            'advice' => $advice,
            'type' => 0
        ]);
        return $this->jsonExit(200, 'OK');
    }

    public function chatAdviceDelete(Request $request)
    {
        $id = $request->input('id');
        $chat = LibChatModel::where('id', $id)->first();
        if (!$chat) {
            return $this->jsonExit(201, '自定义快捷聊天不存在');
        }
        if ($chat->user_id != Auth::id() || $chat->type != 0) {
            return $this->jsonExit(202, '您暂无权限删除');
        }
        $chat->delete();
        return $this->jsonExit(200, 'OK');
    }

    public function bannerList(Request $request)
    {
        $position = $request->input('position', 'discover');
        $banners = LibBannersModel::where([['status', 1], ['position', $position]])->orderBy('id', 'desc')->get();
        return $this->jsonExit(200, 'OK', $banners);
    }

    public function sysNotice(Request $request)
    {
        $page = $request->input('page', 1);
        $page = $page < 1 ? 1 : $page;
        $items = UsersMsgSysModel::getUserMsgPageData($page);
        return $this->jsonExit(200, 'OK', $items);
    }

    public function govNotice(Request $request)
    {
        $page = $request->input('page', 1);
        $page = $page < 1 ? 1 : $page;
        $items = UsersMsgGovModel::getUserMsgPageData($page);
        return $this->jsonExit(200, 'OK', $items);
    }

    public function positiveEnergy(Request $request)
    {
        $data = [
            'count' => 4,
            'items' => [
                [
                    'img' => 'http://static.hfriend.cn/vips/z1.jpeg',
                    'url' => 'http://agreement.fletter.cn/fangpianb'
                ], [
                    'img' => 'http://static.hfriend.cn/vips/z2.png',
                    'url' => 'http://agreement.fletter.cn/fangpianc'
                ], [
                    'img' => 'http://static.hfriend.cn/vips/z3.jpeg',
                    'url' => 'http://agreement.fletter.cn/fangpiand'
                ],
                [
                    'img' => 'http://static.hfriend.cn/vips/z4.jpeg',
                    'url' => 'http://agreement.fletter.cn/fangpiane'
                ]
            ],
        ];
        return $this->jsonExit(200, 'OK', $data);
    }
}
