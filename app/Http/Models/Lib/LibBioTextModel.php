<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use App\Http\Models\EsDataModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LibBioTextModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_bio_text';
    protected $hidden = ['updated_at'];

    public static function getRandTextBio($num = 1, $q = '', $cat = '')
    {
        $es = true;
        if ($es) {
            $bio = EsDataModel::getEsBio($num, $q, $cat);
            return $bio;
        }
        if (empty($cat)) {
            $rand = [];
            for ($i = 1; $i <= $num; $i++) {
                $rand[] = mt_rand(1, 50000);
            }
            $bio = self::whereIn('id', array_unique($rand))->pluck('content')->toArray();
            return $num == 1 ? $bio[0] : $bio;
        } else {
            $bio = self::where('desc', $cat)->orderBy(DB::Raw('RAND()'))->limit($num)->pluck('content')->toArray();
            return $num == 1 ? $bio[0] : $bio;
        }
    }
}
