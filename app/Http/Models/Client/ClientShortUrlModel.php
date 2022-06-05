<?php

namespace App\Http\Models\Client;

use App\Http\Models\Users\UsersModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClientShortUrlModel extends Model
{
    protected $guarded = [];
    protected $table = 'client_short_url';
}
