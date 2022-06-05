<?php

namespace App\Http\Models\Payment\AppleLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class AppleIapModel extends Model
{
    protected $guarded = [];
    protected $table = 'apple_iap';

    public static function storeAppleIap($receiptData, $data, $uid = 0)
    {
        if ($uid < 1) {
            throw new \Exception('用户参数有误');
        }
        //入库票据
        self::upAndInsert($receiptData, $data);   //票据;

        //入库in_app
        if (isset($data['receipt']['in_app']) && count($data['receipt']['in_app'])) {
            AppleIapInAppModel::batchInsert($data['receipt']['in_app']);
//            foreach ($data['receipt']['in_app'] as $in_app) {
//                 AppleIapInAppModel::upAndInsert($in_app);
//            }
        }

        //入库latest_receipt_info
        if (isset($data['latest_receipt_info']) && count($data['latest_receipt_info'])) {
            AppleIapLatestReceiptInfoModel::batchInsert($data['latest_receipt_info']);
//            foreach ($data['latest_receipt_info'] as $in_app) {
//                AppleIapLatestReceiptInfoModel::upAndInsert($in_app);
//            }
        }

        //入库pending_renewal_info
        if (isset($data['pending_renewal_info']) && count($data['pending_renewal_info'])) {
            AppleIapPendingRenewalInfoModel::batchInsert($uid, $data['pending_renewal_info']);
//            foreach ($data['pending_renewal_info'] as $in_app) {
//                AppleIapPendingRenewalInfoModel::upAndInsert($uid, $in_app);
//            }
        }
    }

    public static function upAndInsert($receiptData, $data)
    {
        $sign = md5($receiptData);
        self::updateOrCreate([
            'sign' => $sign,   //票据
        ], [
            'receipt_type' => $data['receipt']['receipt_type'],
            'environment' => isset($data['environment']) ? $data['environment'] : '',
            'adam_id' => $data['receipt']['adam_id'],
            'app_item_id' => isset($data['receipt']['app_item_id']) ? $data['receipt']['app_item_id'] : '',//沙箱数据中不存在此值
            'bundle_id' => $data['receipt']['bundle_id'],//唯一标识符
            'application_version' => $data['receipt']['application_version'],
            'download_id' => $data['receipt']['download_id'],
            'version_external_identifier' => $data['receipt']['version_external_identifier'],//版本外部的标识，沙箱环境下其值为：0正式环境其值为一个数字，会变，原因未知。是否和修改价格有关？

            'receipt_creation_date' => $data['receipt']['receipt_creation_date'],//太平洋标准时间
            'receipt_creation_date_ms' => $data['receipt']['receipt_creation_date_ms'],//时间毫秒
            'receipt_creation_date_pst' => $data['receipt']['receipt_creation_date_pst'],//时间,太平洋标准时间

            'request_date' => $data['receipt']['request_date'],//太平洋标准时间
            'request_date_ms' => $data['receipt']['request_date_ms'],//时间毫秒
            'request_date_pst' => $data['receipt']['request_date_pst'],//时间,太平洋标准时间

            'original_purchase_date' => $data['receipt']['original_purchase_date'],//原始购买时间
            'original_purchase_date_ms' => $data['receipt']['original_purchase_date_ms'],//毫秒
            'original_purchase_date_pst' => $data['receipt']['original_purchase_date_pst'],//购买时间,太平洋标准时间

            'original_application_version' => $data['receipt']['original_application_version'],//iPhone程序的版本号
            'receipt' => $receiptData,   //票据
            'latest_receipt' => $data['latest_receipt'],
            'sign' => $sign,   //签名
        ]);
    }


    public static function getDataByPage($page, $size, $q)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                $query->where('receipt', 'like', '%' . $q . '%')->orWhere('latest_receipt', 'like', '%' . $q . '%');
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {

            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
