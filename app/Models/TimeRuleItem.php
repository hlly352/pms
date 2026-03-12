<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TimeRuleItem extends Model {
    protected $guarded = [];

    // 🌟 告诉 Laravel 自动把数据库的 json 字符串转成 Array 数组
    protected $casts = [
        'days_of_week' => 'array',
        'allocate_hours' => 'float', // 顺便把小时转为浮点数
    ];

    public function account() {
        return $this->belongsTo(TimeAccount::class, 'time_account_id');
    }
}