<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LibRegionModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_region';
    public $timestamps = FALSE;

    public static function getRegionMap()
    {
        $reg = [];
        $rkey = 'region_city';
        $redis_data = R::gredis($rkey);
        if (empty($redis_data)) {
            $regions = self::whereIn('level_id', [1, 2])->orderBy('level_id', 'asc')->get();
            foreach ($regions as $region) {
                if ($region->level_id == 1) {
                    $reg[$region->id] = [
                        'id' => $region->id,
                        //'pid' => $region->pid,
                        'name' => $region->name,
                    ];
                }
                if ($region->level_id == 2) {
                    $reg[$region->pid]['son'][] = [
                        'id' => $region->id,
                        //'pid' => $region->pid,
                        'name' => $region->name,
                    ];
                }
            }
            R::sredis($reg, $rkey, 86400 * 100);
            return $reg;
        }
        return $redis_data;
    }
}
