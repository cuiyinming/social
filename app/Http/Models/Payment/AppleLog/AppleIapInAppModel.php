<?php

namespace App\Http\Models\Payment\AppleLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class AppleIapInAppModel extends Model
{
    protected $guarded = [];
    protected $table = 'apple_iap_in_app';

    public static function batchInsert($albums)
    {
        $sql = 'INSERT INTO `soul_apple_iap_in_app` (`quantity`,`product_id`,`transaction_id`,`original_transaction_id`,`purchase_date`,`purchase_date_ms`,`purchase_date_pst`,`original_purchase_date`,`original_purchase_date_ms`,`original_purchase_date_pst`,`expires_date`,`expires_date_ms`,`expires_date_pst`,`web_order_line_item_id`,`is_trial_period`,`is_in_intro_offer_period`) VALUES';
        $params = [];
        foreach ($albums as $i => $item) {
            $sql_arr[] = "(:quantity{$i},:product_id{$i},:transaction_id{$i},:original_transaction_id{$i},:purchase_date{$i},:purchase_date_ms{$i},:purchase_date_pst{$i},:original_purchase_date{$i},:original_purchase_date_ms{$i},:original_purchase_date_pst{$i},:expires_date{$i},:expires_date_ms{$i},:expires_date_pst{$i},:web_order_line_item_id{$i},:is_trial_period{$i},:is_in_intro_offer_period{$i})";
            $params[':quantity' . $i] = $item['quantity'];
            $params[':product_id' . $i] = $item['product_id'];
            $params[':transaction_id' . $i] = $item['transaction_id'];
            $params[':original_transaction_id' . $i] = $item['original_transaction_id'];
            $params[':purchase_date' . $i] = $item['purchase_date'];
            $params[':purchase_date_ms' . $i] = $item['purchase_date_ms'];
            $params[':purchase_date_pst' . $i] = $item['purchase_date_pst'];
            $params[':original_purchase_date' . $i] = $item['original_purchase_date'];
            $params[':original_purchase_date_ms' . $i] = $item['original_purchase_date_ms'];
            $params[':original_purchase_date_pst' . $i] = $item['original_purchase_date_pst'];
            $params[':expires_date' . $i] = isset($item['expires_date']) ? $item['expires_date'] : null;
            $params[':expires_date_ms' . $i] = isset($item['expires_date_ms']) ? $item['expires_date_ms'] : null;
            $params[':expires_date_pst' . $i] = isset($item['expires_date_pst']) ? $item['expires_date_pst'] : null;
            $params[':web_order_line_item_id' . $i] = isset($item['web_order_line_item_id']) ? $item['web_order_line_item_id'] : 0;
            $params[':is_trial_period' . $i] = isset($item['is_trial_period']) && $item['is_trial_period'] == false ? 0 : 1;
            $params[':is_in_intro_offer_period' . $i] = isset($item['is_in_intro_offer_period']) && $item['is_in_intro_offer_period'] == false ? 0 : 1;
        }
        DB::statement($sql . implode(',', $sql_arr) . " ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`),`product_id` = VALUES(`product_id`),`transaction_id` = VALUES(`transaction_id`),`original_transaction_id` = VALUES(`original_transaction_id`),`purchase_date` = VALUES(`purchase_date`),`purchase_date_ms` = VALUES(`purchase_date_ms`),`purchase_date_pst` = VALUES(`purchase_date_pst`),`original_purchase_date` = VALUES(`original_purchase_date`),`original_purchase_date_ms` = VALUES(`original_purchase_date_ms`),`original_purchase_date_pst` = VALUES(`original_purchase_date_pst`),`expires_date` = VALUES(`expires_date`),`expires_date_ms` = VALUES(`expires_date_ms`),`expires_date_pst` = VALUES(`expires_date_pst`),`web_order_line_item_id` = VALUES(`web_order_line_item_id`),`is_trial_period` = VALUES(`is_trial_period`),`is_in_intro_offer_period` = VALUES(`is_in_intro_offer_period`)", $params);
    }

    //更新入库
    public static function upAndInsert($in_app)
    {
        self::updateOrCreate([
            'transaction_id' => $in_app['transaction_id'],
            'original_transaction_id' => $in_app['original_transaction_id'],
        ], [
            'quantity' => $in_app['quantity'],
            'product_id' => $in_app['product_id'],
            'transaction_id' => $in_app['transaction_id'],
            'original_transaction_id' => $in_app['original_transaction_id'],
            'purchase_date' => $in_app['purchase_date'],//原始购买时间
            'purchase_date_ms' => $in_app['purchase_date_ms'],//毫秒
            'purchase_date_pst' => $in_app['purchase_date_pst'],//购买时间,太平洋标准时间
            'original_purchase_date' => $in_app['original_purchase_date'],//原始购买时间
            'original_purchase_date_ms' => $in_app['original_purchase_date_ms'],//毫秒
            'original_purchase_date_pst' => $in_app['original_purchase_date_pst'],//购买时间,太平洋标准时间
            'expires_date' => $in_app['expires_date'],
            'expires_date_ms' => $in_app['expires_date_ms'],
            'expires_date_pst' => $in_app['expires_date_pst'],
            'web_order_line_item_id' => $in_app['web_order_line_item_id'],
            'is_trial_period' => $in_app['is_trial_period'] == true ? 1 : 0,
            'is_in_intro_offer_period' => $in_app['is_in_intro_offer_period'] == true ? 1 : 0,
        ]);
    }


    public static function getDataByPage($page, $size, $q)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('original_transaction_id', $q)->orWhere('transaction_id', $q)->orWhere('web_order_line_item_id', $q);
                } else {
                    $query->where('product_id', 'like', '%' . $q . '%');
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
