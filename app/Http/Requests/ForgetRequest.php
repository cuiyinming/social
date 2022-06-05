<?php

namespace App\Http\Requests;

class ForgetRequest extends Request
{
    public function rules()
    {
        return [
            'mobile' => 'required|min:11',
            'code' => 'required|min:4',
            'password' => 'required|min:6',
            'cpassword' => 'required|min:6',
        ];
    }

    public function messages()
    {
        return [
            'mobile.required' => '电话号码不能为空',
            'mobile.min' => '手机号码错误',
            'code.required' => '验证码不能为空',
            'code.min' => '验证码长度错误',
            'password.required' => '密码不能为空',
            'password.min' => '密码最短需为6位',
            'cpassword.required' => '确认密码不能为空',
            'cpassword.min' => '确认密码最短需为6位',
        ];
    }
}
