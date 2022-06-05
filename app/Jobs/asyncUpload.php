<?php

namespace App\Jobs;

use App\Http\Libraries\Tools\AliyunOss;
use App\Http\Models\MessageModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class asyncUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bucket;
    protected $tmp_path;

    public function __construct($bucket, $tmp_path)
    {
        $this->bucket = $bucket;
        $this->tmp_path = $tmp_path;
    }

    public function handle()
    {
        try {
            (AliyunOss::getInstance())->uploadToOss($this->bucket, $this->tmp_path);
            unlink($this->tmp_path);
        } catch (\Exception $e) {
            MessageModel::gainLog($e,__FILE__, __LINE__);
        }
    }
}
