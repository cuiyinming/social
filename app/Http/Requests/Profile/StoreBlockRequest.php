<?php

namespace App\Http\Requests\Profile;

use Illuminate\Validation\Rule;
use App\Http\Requests\Request;
class StoreBlockRequest extends Request
{
    public function rules()
    {
        return [
            'user_id' => 'required|integer|min:10|max:1000000000',
            'status' => [   //状态显示限制
                'required',
                'integer',
                Rule::in([0, 1]),
            ],
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => '用户id不能空',
            'user_id.integer' => '用户id错误',
            'user_id.min' => '用户id错误',
            'user_id.max' => '用户id错误',
            'status.required' => '拉黑状态不能为空',
            'status.integer' => '拉黑状态错误',
            'status.in' => '拉黑状态传值错误',
        ];
    }
}
