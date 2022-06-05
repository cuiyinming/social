<?php

namespace App\Http\Controllers;

use App\Http\Models\Payment\SubscribeModel;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function jsonExit($code = 200, $msg = "OK", $data = [], $took = 0)
    {
//        if (empty($data)) {
//            $data = new \stdClass();
//        }
        //这里有一个弹出vip充值的需求
//        if ($code == 80000) {
//            $data = SubscribeModel::fastPayRight('swordsman', 1);
//        }
        $result = [
            'time' => number_format(microtime(1) - CORE_MICRO, 2),
            'code' => $code,
            'data' => empty($data) ? null : $data,
            'msg' => $msg,
        ];
        if ($took > 0) {
            $result = ['took' => $took] + $result;
        }

        //没有encrypt或者encrypt为真，默认加密
        $obj = json_encode($result, JSON_UNESCAPED_UNICODE);
        if (isset($_GET['callback'])) {
            return $_GET['callback'] . '(' . $obj . ')';
        }
        //针对ios 1.92以上版本进行加密
        //if (defined('CRYPT') && CHANNEL == 'ios') {
        //    $obj = openssl_encrypt($obj, "AES-128-CBC", CRYPT, 0, "2451404985763848");
        //}
        return $obj;
    }

    protected function getSort(array $params)
    {
        //规定所有的0代表不排序 1代表倒序 2代表正序
        $sort = [];
        foreach ($params as $key => $param) {
            if ($params[$key] !== 0) {
                $sort[] = [
                    $key => [
                        'order' => $params[$key] == 1 ? 'desc' : 'asc',
                    ]
                ];
            }
        }
        return $sort;
    }
}
