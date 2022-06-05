<?php

namespace App\Jobs;

use App\Http\Models\CommonModel;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\MessageModel;
use App\Http\Models\Resource\AlbumModel;
use App\Http\Models\Resource\UploadModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class greenScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $process;
    protected $type;
    protected $processIds;
    protected $sex = 0;

    public function __construct($process, $type = 'discover', $processIds = [], $sex = 0)
    {
        $this->process = $process;
        $this->type = $type;
        $this->processIds = $processIds;
        $this->sex = $sex;
    }


    public function handle()
    {
        if ($this->type == 'discover') {
            //处理图片
            if (!empty($this->process->album)) {
                $idArr = $this->_green('album', 0);
                UploadModel::whereIn('id', $idArr)->update(['is_illegal' => 1]);
            }
            //处理音频
            if (!empty($this->process->sound)) {
                $this->_greenAudio();
            }
        }
        if ($this->type == 'album') {
            //审核图片
            if (!empty($this->process->album)) {
                $idArr = $this->_green('album', 0);
                AlbumModel::whereIn('id', $idArr)->update(['is_illegal' => 1]);
                //入库 = 在这里吧动态的更新也放到动态里面
                try {
                    $newAlbum = [];
                    $album = $this->process->album;
                    //file_put_contents('/tmp/dis.log', print_r($album, 1) . PHP_EOL, 8);
                    foreach ($album as $alb) {
                        if (!in_array($alb['id'], $idArr)) {
                            if ($alb['is_private'] != 1 && $alb['is_private'] != true && $alb['is_private'] != 'true') {
                                $newAlbum[] = [
                                    'id' => $alb['id'],
                                    'img_url' => $alb['img_url'],
                                    'is_illegal' => $alb['is_illegal'],
                                ];
                            }
                        }
                    }
                    if (count($newAlbum) > 0) {
                        $insertData['user_id'] = $this->process->user_id;
                        $insertData['sex'] = $this->sex;
                        $insertData['cont'] = '我刚刚更新了相册,快来看看吧~';
                        $insertData['cmt_on'] = 1;
                        $insertData['show_on'] = 0;
                        $insertData['status'] = 1;
                        $insertData['private'] = 0;
                        $insertData['location'] = null;
                        $insertData['lat'] = null;
                        $insertData['lng'] = null;
                        $insertData['tags'] = null;
                        $insertData['album'] = $newAlbum;
                        $insertData['sound'] = null;
                        $insertData['num_cmt'] = 0;
                        $insertData['num_zan'] = 0;
                        $insertData['num_view'] = 5;
                        $insertData['num_share'] = 0;
                        $insertData['num_say_hi'] = 0;
                        $insertData['post_at'] = date('Y-m-d H:i:s');
                        $insertData['online'] = 0;
                        $insertData['type'] = 1;
                        $insertData['channel'] = strtolower($this->process->register_channel);
                        $insertData['created_at'] = date('Y-m-d H:i:s');
                        $insertData['updated_at'] = date('Y-m-d H:i:s');
                        DiscoverModel::where([
                            ['type', 1],
                            ['user_id', $this->process->user_id],
                            ['created_at', '>', date('Y-m-d 00:00:00')],
                            ['created_at', '<', date('Y-m-d 23:59:59')]
                        ])->delete();
                        $insert = DiscoverModel::create($insertData);
                    }
                } catch (\Exception $e) {
                    MessageModel::gainLog($e, __FILE__, __LINE__);
                }
            }
            //审核语音
        }
        if ($this->type == 'album_video') {
            //审核图片
            if (!empty($this->process->album_video)) {
                $idArr = $this->_green('album_video', 1);
                AlbumModel::whereIn('id', $idArr)->update(['is_illegal' => 1]);
            }
            //审核语音
        }
    }

    private function _green($column, $is_video = 0): array
    {
        $albums = $this->process->$column;
        $idArr = [];
        foreach ($albums as &$album) {
            if (count($this->processIds) > 0 && !in_array($album['id'], $this->processIds)) continue;
            try {
                CommonModel::greenScan($this->process->user_id, $is_video, $album['img_url']);
            } catch (\Exception $e) {
                $album['is_illegal'] = 1;
                $idArr['id'] = $album['id'];
                MessageModel::gainLog($e, __FILE__, __LINE__);
            }
        }
        $this->process->$column = $albums;
        $this->process->save();
        return $idArr;
    }

    private function _greenAudio($column = 'sound')
    {
        $sound = $this->process->$column;
        try {
            CommonModel::greenScan($this->process->user_id, 2, $sound['url']);
        } catch (\Exception $e) {
            $sound['is_illegal'] = 1;
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
        $this->process->$column = $sound;
        $this->process->save();
    }
}
