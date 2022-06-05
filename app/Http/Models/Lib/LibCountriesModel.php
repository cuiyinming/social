<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;

class LibCountriesModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_countries';
    protected $hidden = ['created_at', 'updated_at'];
}
