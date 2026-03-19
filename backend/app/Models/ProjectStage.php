<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // 建议加上，虽然不是必须
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // 👈 必须加这行！

class ProjectStage extends Model
{
    use HasFactory; 

    protected $guarded = [];

    // 原有的 segments
    public function segments() {
        return $this->hasMany(ProjectStageSegment::class);
    }
    
    // 🌟 确保加上这个关联方法
    public function steps()
    {
        return $this->hasMany(ProjectStageStep::class, 'project_stage_id');
    }
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}