<?php

namespace App\Http\Models\Logs;

use App\Http\Helpers\H;
use App\Http\Helpers\R;
use App\Http\Models\Model;

class LogBalanceModel extends Model
{
    protected $guarded = [];
    protected $table = 'log_balance';

    //添加变动记录
    public static function gainLogBalance($uid, $before, $change, $after, $type, $desc, $remark, $admin = 0, $table = 'log_balance', $order_sn = null)
    {
        $operate = $before - $after > 0 ? '-' : '+';
        $inviteUserInfo = [
            'amount' => $after,
            'before_amount' => $before,
            'change_amount' => $change,
            'order_sn' => is_null($order_sn) ? H::genOrderSn(5) : $order_sn,
            'adm_id' => $admin,
            'user_id' => $uid,
            'desc' => $desc,
            'type' => $type,
            'operate' => $operate,
            'remark' => $remark,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        self::suffix($table)->insert($inviteUserInfo);
    }

    //获取全部应用
    public static function getPageItems($page = 1, $size = 20, $dates = [], $type = '', $operate = '', $q = '', $uid = 0)
    {
        $builder = self::where('uid', $uid)->orderBy('id', 'desc');
        if ($q !== '') {
            $builder->where('order_sn', 'like', '%' . $q . '%');
        }
        if ($type != '') {
            $builder->where('type', $type);
        }
        if ($operate != '') {
            $builder->where('operate', $operate);
        }
        if (!empty($dates)) {
            $builder->whereBetween('created_at', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($items) {
            //渲染
            foreach ($items as &$item) {
                $item->type = $item->type == 0 ? '充值' : ($item->type == 1 ? '提现' : '订单');
            }
        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }

    public static function getPageAdminItems($table, $page, $size, $dates, $type, $operate, $id, $q)
    {
        $builder = self::suffix($table)->orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder->where('order_sn', 'like', '%' . $q . '%')->orWhere('user_id', $q);
        }
        if (intval($id) != 0 && !is_null($id)) {
            $builder->where('user_id', $id);
        }
        if (!is_null($type)) {
            $builder->where('type', $type);
        }
        if (!is_null($operate)) {
            $builder->where('operate', $operate);
        }
        if (!empty($dates)) {
            $builder->whereBetween('created_at', [$dates[0], $dates[1]]);
        }
        $count = $builder->count();
        $items = $builder->skip(($page - 1) * $size)->take($size)->get();
        if ($items) {
            //渲染银行
            foreach ($items as &$item) {
                $item->type_name = '';
                if ($table == 'client_balance') {
                    if ($item->type == 'draw') $item->type_name = '提现';
                    if ($item->type == 'admin_add') $item->type_name = '平台调整';
                    if ($item->type == 'order') $item->type_name = '订单';
                }
            }
        }
        return [
            'count' => $count,
            'items' => $items ? $items : []
        ];
    }
}
