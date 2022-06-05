<?php

namespace App\Http\Models\Discover;

use App\Http\Helpers\R;
use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;

class DiscoverIgnoreModel extends Model
{
    protected $guarded = [];
    protected $table = 'discover_ignore';
    protected $hidden = ['created_at', 'updated_at'];

    public static function getIgnoreIdArr(int $user_id, int $status = 1)
    {
        return self::where([['status', $status], ['user_id', $user_id]])->pluck('ignore_user_id')->toArray();
    }
}
