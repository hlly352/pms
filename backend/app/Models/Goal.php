<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    // 👇 修改 fillable: 去掉 type, related_project，加上 goal_type_id
    protected $fillable = [
        'goal_type_id', 'name', 'content', 
        'start_date', 'end_date', 'progress', 'status'
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    // 👇 定义关联：一个目标属于一个类型
    public function goalType()
    {
        return $this->belongsTo(GoalType::class);
    }
    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}