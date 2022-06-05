<?php

namespace App\Http\Models\Resource;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class AlbumModel extends Model
{
    protected $guarded = [];
    protected $table = 'resource_album';

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

    //批量更新相册信息
    public static function batchUpdateAlbum(array $albums)
    {
        $sql = 'INSERT INTO `soul_resource_album` (`id`, `is_free`,`is_private`,`price`) VALUES';
        $params = [];
        //print_r($bathArrs);
        foreach ($albums as $i => $bval) {
            $sql_arr[] = "(:id{$i}, :is_free{$i}, :is_private{$i}, :price{$i})";
            $params[':id' . $i] = $bval['id'];
            $params[':is_free' . $i] = $bval['is_free'];
            $params[':is_private' . $i] = $bval['is_private'];
            $params[':price' . $i] = $bval['price'];
        }
        DB::statement($sql . implode(',', $sql_arr) . " ON DUPLICATE KEY UPDATE `is_free` = VALUES(`is_free`), `is_private` = VALUES(`is_private`), `price` = VALUES(`price`) ", $params);
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
