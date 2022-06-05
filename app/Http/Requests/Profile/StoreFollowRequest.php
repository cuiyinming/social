<?php


namespace App\Http\Requests\Profile;

use Illuminate\Validation\Rule;
use App\Http\Requests\Request;

class StoreFollowRequest extends Request
{
    public function rules()
    {
        return [
            'follows' => 'required',
        ];
    }
}
