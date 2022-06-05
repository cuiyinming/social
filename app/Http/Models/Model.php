<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel
{
    protected $suffix = null;

    // 设置表后缀
    public function setSuffix($suffix)
    {
        $this->suffix = $suffix;
        if ($suffix !== null) {
            $this->table = $suffix;
        }
    }

    // 提供一个静态方法设置表后缀
    public static function suffix($suffix)
    {
        $instance = new static;
        $instance->setSuffix($suffix);
        return $instance->newQuery();
    }

    public static function suffixDuplicate($suffix)
    {
        $instance = new static;
        $instance->setSuffix($suffix);
        return $instance;
    }

    // 创建新的"chapters_{$suffix}"的模型实例并返回
    public function newInstanceModel($attributes = [], $exists = false)
    {
        $model = parent::newInstance($attributes, $exists);
        $model->setSuffix($this->suffix);
        return $model;
    }
}
