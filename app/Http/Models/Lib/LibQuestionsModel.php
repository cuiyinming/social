<?php

namespace App\Http\Models\Lib;

use App\Http\Helpers\R;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class LibQuestionsModel extends Model
{
    protected $guarded = [];
    protected $table = 'lib_questions';
    protected $hidden = ['updated_at'];

    public static function getAllData()
    {
        $res = [];
        $items = self::where('status', 1)->get();
        foreach ($items as $item) {
            $res[$item->type]['title'] = $item->name;
            $res[$item->type]['questions'][] = [
                'question' => $item->question,
                'answer' => $item->answer,
            ];
        }
        return $res;
    }

    public static function getAdminPageItems($page, $size, $q, $type)
    {
        $builder = self::orderBy('id', 'desc');
        if (!is_null($type)) {
            $builder->where('type', $type);
        }
        if (!is_null($q)) {
            $builder->where(function ($query) use ($q) {
                $query->where('question', 'like', '%' . $q . '%')->orWhere('answer', 'like', '%' . $q . '%');
            });
        }
        $count = $builder->count();
        $logs = $builder->skip(($page - 1) * $size)->take($size)->get();
        foreach ($logs as &$log) {
            $log->status = $log->status == 1;
        }
        return [
            'count' => $count,
            'items' => $logs ? $logs : []
        ];
    }
}
