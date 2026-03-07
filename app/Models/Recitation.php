<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recitation extends Model
{
    protected $guarded = [];
    //告诉 Laravel 这些字段是日期类型
    protected $casts = [
        'next_review_time' => 'datetime',
        'last_reviewed_at' => 'datetime',
    ];

    // 关联重复规则
    public function rule()
    {
        return $this->belongsTo(Rule::class); // 假设你的规则模型叫 Rule
    }
}