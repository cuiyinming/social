<?php

namespace App\Http\Models\Payment;

use App\Http\Helpers\H;
use App\Http\Libraries\Sms\MsgSend;
use Illuminate\Database\Eloquent\Model;

class OrderSyncModel extends Model
{
    protected $guarded = [];
    protected $table = 'order_sync';
}
