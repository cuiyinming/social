<?php

namespace App\Http\Models\Client;

use App\Http\Helpers\S;
use App\Http\Models\Users\UsersProfileModel;
use App\Http\Models\Users\UsersSettingsModel;
use Illuminate\Database\Eloquent\Model;
use App\Http\Helpers\H;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;


class ClientUsersModel extends Authenticatable implements JWTSubject
{

    use Notifiable;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected $table = 'client_users';
    protected $guarded = [];

    protected $hidden = [
        'password', 'remember_token', 'updated_at'
    ];

    #获取列表信息
    public static function getUserInfo($id = 0)
    {
        return self::find($id);
    }

    //  获取用户资料完成度
    public static function infoComplete($uid): int
    {
        $info = self::find($uid);
        $score = 0;
        if ($info) {
            if ($info->mobile) $score++;
            if ($info->head_img) $score++;
            if ($info->qq) $score++;
            if ($info->wechat) $score++;
            if ($info->email) $score++;
            if ($info->name) $score++;
            if ($info->wechat_qr) $score++;
            if ($info->alipay_qr) $score++;
        }
        return intval($score / 8 * 100);
    }

    //获取代理下级
    public static function getClientAgent($page = 1, $size = 20, $client_code = 'abc'): array
    {
        $levela = $levelb = $levelc = $invitea = $inviteb = $invitec = [];
        $first = self::where([['invited', $client_code], ['status', 1]])->get();
        if (!$first->isEmpty()) {
            foreach ($first as $fir) {
                $levela[] = $fir->id;
                $invitea[] = $fir->uinvite_code;
            }
            if (count($invitea) > 0) {
                //第二层
                $second = self::where('status', 1)->whereIn('invited', $invitea)->get();
                if (!$second->isEmpty()) {
                    foreach ($second as $sec) {
                        $levelb[] = $sec->id;
                        $inviteb[] = $sec->uinvite_code;
                    }
                    if (count($inviteb) > 0) {
                        //第三层
                        $third = self::where('status', 1)->whereIn('invited', $inviteb)->get();
                        if (!$third->isEmpty()) {
                            foreach ($third as $thi) {
                                $levelc[] = $thi->id;
                            }
                        }
                    }
                }
            }

            $idArr = array_unique(array_merge($levela, $levelb, $levelc));
            $builder = self::select(['id', 'mobile', 'last_login', 'created_at'])->whereIn('id', $idArr)->orderBy('id', 'desc');
            $items = $builder->skip(($page - 1) * $size)->take($size)->get();
            if (!$items->isEmpty()) {
                foreach ($items as $item) {
                    $item->mobile = H::hideStr(H::decrypt($item->mobile), 3, 3);
                    if (in_array($item->id, $levela)) {
                        $item->level = '一级';
                    }
                    if (in_array($item->id, $levelb)) {
                        $item->level = '二级';
                    }
                    if (in_array($item->id, $levelc)) {
                        $item->level = '三级';
                    }
                }
            }
        }
        return [
            'count' => count($idArr),
            'items' => $items ? $items : []
        ];
    }


    public static function getDataByPage($q, $status, $unlock_time, $date, $page, $size, $id, $invited): array
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($q)) {
            $builder = $builder->where(function ($query) use ($q) {
                if (is_numeric($q)) {
                    //判断手机号
                    $query->where('mobile', 'like', '%' . $q . '%')->orWhere('id', $q)->orWhere('uinvite_code', $q);
                }
            });
        }
        if (!is_null($invited)) {
            $builder->where('invited', $invited);
        }
        if (!is_null($id) && $id != 0) {
            $builder->where('id', $id);
        }
        if (!is_null($status)) {
            $builder->where('status', $status);
        }
        if (!is_null($unlock_time)) {
            if ($unlock_time == 0) {
                $builder->whereNull('unlock_time');
            } else {
                $builder->whereNotNull('unlock_time');
            }
        }
        if (count($date) > 0) {
            $builder->whereBetween('created_at', [$date[0], $date[1]]);
        }
        $count = $builder->count();
        $datas = $builder->skip(($page - 1) * $size)->take($size)->get();
        if (!$datas->isEmpty()) {
            foreach ($datas as &$item) {
                $item->status = $item->status == 1 ? false : true;
                $item->unlock = is_null($item->unlock_time) ? false : true;
            }
        }
        $summary = self::getSummary();
        return [
            'data' => $datas ? $datas : [],
            'count' => $count,
            'summary' => $summary,
        ];
    }

    public static function getSummary()
    {
        $stime = date('Y-m-d 00:00:00');
        $etime = date('Y-m-d 23:59:59');
        $todayNum = self::where([['created_at', '>', $stime], ['created_at', '<', $etime]])->count();
        return [
            'today' => $todayNum,
        ];
    }

}
