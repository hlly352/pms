<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStageStep extends Model
{
    protected $fillable = [
        'project_stage_id', 
        'name', 
        'description', 
        'start_date', 
        'end_date', 
        'weight', 
        'frequency', 
        'reminder_time',
        'planned_hours', // 👈 必须有
        'planned_cost'   // 👈 必须有
    ];
    protected $casts = [
        'frequency' => 'array', // 自动转 JSON
    ];
    // 🌟 新增：定义与任务表的关联关系
    public function task()
    {
        // 步骤表的主键，对应任务表里的 project_stage_step_id
        return $this->hasOne(Task::class, 'project_stage_step_id');
    }
}