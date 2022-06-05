<?php

namespace App\Http\Models\Resource;

use App\Http\Libraries\Tools\AliyunOss;
use App\Http\Models\MessageModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Http\Helpers\H;

class ResourceModel extends Model
{
    protected $guarded = [];

    public static function deleteResource($source, $type = 'resource')
    {
        try {
            if ($type == 'resource') {
                if ($source->location == 'local') {
                    $local_path = storage_path('app/public/') . $source->path;
                    @unlink($local_path);
                }
                if ($source->location == 'aliyun') {
                    $path = H::getOssPath($source->path);
                    (AliyunOss::getInstance())->deleteOssObj($path);
                }
            }
            //删除资源
            if ($type == 'discover') {
                if (!empty($source->album)) {
                    $albums = $source->album;
                    if (count($albums) > 0) {
                        foreach ($albums as $album) {
                            (AliyunOss::getInstance())->deleteOssObj(H::getOssPath($album['img_url']));
                            UploadModel::where('id', $album['id'])->delete();
                        }
                    }
                }
                if (!empty($source->sound)) {
                    $sound = $source->sound;
                    (AliyunOss::getInstance())->deleteOssObj(H::getOssPath($sound['url']));
                }
            }
        } catch (\Exception $e) {
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
