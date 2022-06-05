<?php

namespace App\Jobs;

use App\Http\Helpers\HR;
use App\Http\Models\Discover\DiscoverModel;
use App\Http\Models\Lib\LibGiftModel;
use App\Http\Models\Logs\LogBalanceModel;
use App\Http\Models\Logs\LogGiftReceiveModel;
use App\Http\Models\Logs\LogGiftSendModel;
use App\Http\Models\Logs\LogSweetModel;
use App\Http\Models\Logs\LogSweetUniqueModel;
use App\Http\Models\MessageModel;
use App\Http\Models\SettingsModel;
use App\Http\Models\Users\UsersModel;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class sayHi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cont;
    protected $from_id;
    protected $from;
    protected $uid;
    protected $gift;
    protected $user_id;

    public function __construct($uid, $user_id, $cont, LibGiftModel $gift, $from, $from_id)
    {
        $this->uid = $uid;
        $this->user_id = $user_id;
        $this->cont = $cont;
        $this->gift = $gift;
        $this->from = $from;
        $this->from_id = $from_id;
    }

    public function handle()
    {
        try {
            DB::beginTransaction();
            $toUserIds = [$this->user_id];
            $giftMsg = ['content' => $this->cont];
            //step 0 不管什么情况 先统计打招呼次数
            if ($this->from == 'discover' && $this->from_id > 0) {
                DiscoverModel::where('id', $this->from_id)->increment('num_say_hi');
            }
            $num = 1;  //赠送礼物个数
            $smsSetting = SettingsModel::getSigConf('sms');
            if (isset($smsSetting['say_hi_gift']) && $smsSetting['say_hi_gift'] == 1) {
                //step 1 记录礼物收发记录
                $gift = $this->gift;
                LogGiftReceiveModel::gainLog($this->user_id, $gift, 1);
                LogGiftSendModel::gainLog($this->uid, $this->user_id, $gift, 1, $this->cont);
                //添加搭讪次数累计进用户数据
                UsersProfileModel::where('user_id', $this->uid)->increment('accost');
                UsersProfileModel::where('user_id', $this->user_id)->increment('be_accost');
                //step 4 更新彼此之间的关系热度
                $sweet = LogSweetModel::where([['user_id', $this->uid], ['user_id_receive', $this->user_id]])->first();
                if ($sweet) {
                    $sweet->num += $num;
                    $sweet->sweet += $gift->friendly * $num;
                    $sweet->save();
                } else {
                    LogSweetModel::create([
                        'user_id' => $this->uid,
                        'user_id_receive' => $this->user_id,
                        'num' => 1,
                        'sweet' => $gift->friendly
                    ]);
                }
                //step 5 更新彼此之间的关系热度唯一记录
                $sweet_unique = LogSweetUniqueModel::where([['user_both', $this->uid], ['both_user', $this->user_id]])->orWhere([['both_user', $this->uid], ['user_both', $this->user_id]])->first();
                if ($sweet_unique) {
                    $sweet_unique->num += $num;
                    $sweet_unique->sweet += $gift->friendly * $num;
                    $sweet_unique->save();
                } else {
                    LogSweetUniqueModel::create([
                        'user_both' => $this->uid,
                        'both_user' => $this->user_id,
                        'num' => $num,
                        'sweet' => $gift->friendly * $num
                    ]);
                }
                $path = storage_path('app/public/') . $gift->path;
                $web_path = config('app.url') . '/storage/' . $gift->path;
                $giftMsg = [
                    'content' => $this->cont,
                    'extra' => [
                        'gift_num' => 1,
                        'git_name' => $gift->name,
                        'git_img' => $web_path,
                        'git_img_base64' => base64_encode($path),
                    ],
                ];
            }
            DB::commit();
            //step 2 发送融云礼物消息
            \App\Jobs\imSender::dispatch($this->uid, $toUserIds, 'RC:TxtMsg', json_encode($giftMsg))->onQueue('im');
            //最后更新下redis中打招呼的记录
            HR::updateUniqueNum($this->uid, $this->user_id, 'say-hi-num');
        } catch (\Exception $e) {
            DB::rollBack();
            MessageModel::gainLog($e, __FILE__, __LINE__);
        }
    }
}
