<?php

namespace App\Http\Models\Payment\AppleLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class AppleIapLatestReceiptInfoModel extends Model
{
    protected $guarded = [];
    protected $table = 'apple_iap_latest_receipt_info';

    public static function batchInsert($albums)
    {
        $sql = 'INSERT INTO `soul_apple_iap_latest_receipt_info` (`quantity`,`product_id`,`transaction_id`,`original_transaction_id`,`purchase_date`,`purchase_date_ms`,`purchase_date_pst`,`original_purchase_date`,`original_purchase_date_ms`,`original_purchase_date_pst`,`expires_date`,`expires_date_ms`,`expires_date_pst`,`cancellation_date`,`cancellation_date_ms`,`cancellation_date_pst`,`web_order_line_item_id`,`is_trial_period`,`is_in_intro_offer_period`,`subscription_group_identifier`) VALUES';
        $params = [];
        foreach ($albums as $i => $val) {
            $sql_arr[] = "(:quantity{$i},:product_id{$i},:transaction_id{$i},:original_transaction_id{$i},:purchase_date{$i},:purchase_date_ms{$i},:purchase_date_pst{$i},:original_purchase_date{$i},:original_purchase_date_ms{$i},:original_purchase_date_pst{$i},:expires_date{$i},:expires_date_ms{$i},:expires_date_pst{$i},:cancellation_date{$i},:cancellation_date_ms{$i},:cancellation_date_pst{$i},:web_order_line_item_id{$i},:is_trial_period{$i},:is_in_intro_offer_period{$i},:subscription_group_identifier{$i})";
            $params[':quantity' . $i] = $val['quantity'];
            $params[':product_id' . $i] = $val['product_id'];
            $params[':transaction_id' . $i] = $val['transaction_id'];
            $params[':original_transaction_id' . $i] = $val['original_transaction_id'];
            $params[':purchase_date' . $i] = $val['purchase_date'];
            $params[':purchase_date_ms' . $i] = $val['purchase_date_ms'];
            $params[':purchase_date_pst' . $i] = $val['purchase_date_pst'];
            $params[':original_purchase_date' . $i] = $val['original_purchase_date'];
            $params[':original_purchase_date_ms' . $i] = $val['original_purchase_date_ms'];
            $params[':original_purchase_date_pst' . $i] = $val['original_purchase_date_pst'];
            $params[':expires_date' . $i] = $val['expires_date'];
            $params[':expires_date_ms' . $i] = $val['expires_date_ms'];
            $params[':expires_date_pst' . $i] = $val['expires_date_pst'];

            $params[':cancellation_date' . $i] = isset($val['cancellation_date']) ? $val['cancellation_date'] : '';
            $params[':cancellation_date_ms' . $i] = isset($val['cancellation_date_ms']) ? $val['cancellation_date_ms'] : '';
            $params[':cancellation_date_pst' . $i] = isset($val['cancellation_date_pst']) ? $val['cancellation_date_pst'] : '';

            $params[':web_order_line_item_id' . $i] = $val['web_order_line_item_id'];
            $params[':is_trial_period' . $i] = $val['is_trial_period'] == false ? 0 : 1;
            $params[':is_in_intro_offer_period' . $i] = $val['is_in_intro_offer_period'] == false ? 0 : 1;
            $params[':subscription_group_identifier' . $i] = $val['subscription_group_identifier'];
        }
        DB::statement($sql . implode(',', $sql_arr) . " ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`),`product_id` = VALUES(`product_id`),`transaction_id` = VALUES(`transaction_id`),`original_transaction_id` = VALUES(`original_transaction_id`),`purchase_date` = VALUES(`purchase_date`),`purchase_date_ms` = VALUES(`purchase_date_ms`),`purchase_date_pst` = VALUES(`purchase_date_pst`),`original_purchase_date` = VALUES(`original_purchase_date`),`original_purchase_date_ms` = VALUES(`original_purchase_date_ms`),`original_purchase_date_pst` = VALUES(`original_purchase_date_pst`),`expires_date` = VALUES(`expires_date`),`expires_date_ms` = VALUES(`expires_date_ms`),`expires_date_pst` = VALUES(`expires_date_pst`),`cancellation_date` = VALUES(`cancellation_date`),`cancellation_date_ms` = VALUES(`cancellation_date_ms`),`cancellation_date_pst` = VALUES(`cancellation_date_pst`),`web_order_line_item_id` = VALUES(`web_order_line_item_id`),`is_trial_period` = VALUES(`is_trial_period`),`is_in_intro_offer_period` = VALUES(`is_in_intro_offer_period`),`subscription_group_identifier` = VALUES(`subscription_group_identifier`)", $params);
    }

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
            'subscription_group_identifier' => $in_app['subscription_group_identifier']
        ]);
    }

    public static function getDataByPage($page, $size, $q)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('original_transaction_id', $q)->orWhere('transaction_id', $q)->orWhere('web_order_line_item_id', $q)->orWhere('subscription_group_identifier', $q);
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
