<?php

namespace App\Http\Requests\System;

use Illuminate\Validation\Rule;
use App\Http\Requests\Request;

class FeedbackRequest extends Request
{
    public function rules()
    {
        return [
            'cont' => 'required|string|min:2|max:100',  //内容
            'type' => 'required|string|min:2|max:10',  //类别
            'cate' => 'required|string|min:2|max:50',  //分类细分内容
            'contact' => 'string|min:2|max:25',  //联系方式
            'shoots' => 'array',
            'type_id' => 'integer',
        ];
    }

    public function messages()
    {
        return [
            'cont.required' => '反馈内容不能为空',
            'cont.string' => '反馈内容必须是文字',
            'cont.min' => '反馈内容不能小于2个字符',
            'cont.max' => '反馈内容不能大于100个字符',
            'type.required' => '反馈类别不能为空',
            'type.min' => '反馈类别不能小于2个字',
            'type.max' => '反馈类别不能大于10个字',

            'cate.required' => '分类不能为空',
            'cate.string' => '分类必须是文字',
            'cate.min' => '分类不能小于2个字符',
            'cate.max' => '分类不能大于30个字符',

            'contact.min' => '联系方式最小不能少于2个字符',
            'contact.max' => '联系方式最大不能超过25个字符',
            'contact.string' => '联系方式格式错误',

            'shoots.array' => '截屏数据类型有误',
            'type_id.integer' => '内容id数据类型错误',
        ];
    }
}
