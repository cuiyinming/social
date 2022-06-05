<?php

namespace App\Http\Models\Payment\AppleLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class AppleIapPendingRenewalInfoModel extends Model
{
    protected $guarded = [];
    protected $table = 'apple_iap_pending_renewal_info';

    public static function batchInsert($uid, $albums)
    {
        $sql = 'INSERT INTO `soul_apple_iap_pending_renewal_info` (`auto_renew_product_id`,`original_transaction_id`,`product_id`,`auto_renew_status`) VALUES';
        $params = [];
        foreach ($albums as $i => $val) {
            $sql_arr[] = "(:auto_renew_product_id{$i},:original_transaction_id{$i},:product_id{$i},:auto_renew_status{$i})";
            $params[':auto_renew_product_id' . $i] = $val['auto_renew_product_id'];
            $params[':original_transaction_id' . $i] = $val['original_transaction_id'];
            $params[':product_id' . $i] = $val['product_id'];
            $params[':auto_renew_status' . $i] = $val['auto_renew_status'];
        }
        DB::statement($sql . implode(',', $sql_arr) . " ON DUPLICATE KEY UPDATE `auto_renew_product_id` = VALUES(`auto_renew_product_id`),`original_transaction_id` = VALUES(`original_transaction_id`),`product_id` = VALUES(`product_id`),`auto_renew_status` = VALUES(`auto_renew_status`)", $params);
    }

    public static function upAndInsert($uid, $in_app)
    {
        self::updateOrCreate([
            'auto_renew_product_id' => $in_app['auto_renew_product_id'],
            'original_transaction_id' => $in_app['original_transaction_id'],
        ], [
            'auto_renew_product_id' => $in_app['auto_renew_product_id'],
            'original_transaction_id' => $in_app['original_transaction_id'],
            'product_id' => $in_app['product_id'],
            'auto_renew_status' => $in_app['auto_renew_status'],
        ]);
    }

    public static function getDataByPage($page, $size, $q)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('original_transaction_id', $q);
                } else {
                    $query->where('product_id', 'like', '%' . $q . '%')->orWhere('auto_renew_product_id', $q);
                }
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

