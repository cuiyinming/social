<?php

namespace App\Http\Controllers;

use App\Http\Helpers\H;
use App\Http\Helpers\HR;
use App\Http\Helpers\T;
use App\Http\Models\Client\ClientShortUrlModel;
use App\Http\Models\Lib\LibBannersModel;
use App\Http\Models\MessageModel;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return view('welcome');
    }

    public function short(Request $request, $short_code)
    {
        $short = ClientShortUrlModel::where('short_code', $short_code)->first();
        if ($short) {
            $base_url = $short->base_url;
            $short->click += 1;
            //添加ip统计
            $ip = $request->ip();
            $prefix = 'short-url-code-ip-counter-';
            HR::updateUniqueNum($short->id, $ip, $prefix, false);
            $short->ip = HR::getUniqueNum($short->id, $prefix);
            $short->save();
        } else {
            $base_url = 'http://www.hfriend.cn/';
        }
        //跳转之前添加统计
        Header("HTTP/1.1 301 Moved Permanently");
        Header("Location: " . $base_url);
        exit;
    }

    public function shortGain(Request $request)
    {
        $base_url = 'http://d.hfriend.cn/';
        $url = $request->input('url', '');
        $desc = $request->input('desc', '');
        if (empty($url) || empty($desc)) {
            echo '网址或者渠道描述不能为空';
        }
        $shortCode = H::randstr(6, 'ALL');
        $shortModel = ClientShortUrlModel::where([['name', $desc], ['base_url', $url]])->first();
        if (!$shortModel) {
            $shortModel = ClientShortUrlModel::create([
                'name' => $desc,
                'base_url' => $url,
                'short_code' => $shortCode,
                'short_url' => $base_url . $shortCode,
            ]);
        }
        echo $shortModel->short_url;
    }
}
