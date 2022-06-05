<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RegisterRequest extends Request
{
    public function rules()
    {
        return [
            'avatar' => 'required|string|min:1|max:80',
            'sex' => [
                'required',
                Rule::in([0, 1, 2])
            ],
            'birthday' => 'required|date'
        ];

    }

    public function messages()
    {
        return [
            'nick.required' => '昵称不能为空',
            'avatar.required' => '头像不能为空',
            'birthday.required' => '生日不能为空',
            'birthday.date' => '生日格式错误',
            'sex.required' => '性别未选择',
        ];
    }
}
