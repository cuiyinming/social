<?php

namespace App\Http\Models\Resource;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class AvatarModel extends Model
{
    protected $guarded = [];
    protected $table = 'resource_avatar';

    //根据id 获取资源全路径
    public static function getFilePathById($id, $usefor = 'album')
    {
        $rowModel = self::where('usefor', $usefor)->find($id);
        if ($rowModel) {
            if ($rowModel->location == 'local') {
                return H::path($rowModel->path);
            }
        }
        return $rowModel->path;
    }


    public static function getDataByPage($page, $size, $q)
    {
        $orders = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $orders = $orders->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    $query->where('user_id', $q);
                } else {
                    $query->where('path', 'like', '%' . $q . '%');
                }
            });
        }
        $count = $orders->count();
        $datas = $orders->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$data) {
                $data->full_path = H::path($data->path);
                $data->size = H::getFileSize($data->size);
            }
        }
        return [
            'items' => $datas ? $datas : [],
            'count' => $count,
        ];
    }
}
