<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'goal_id',
        'name',
        'expected_result',
        'remark',
        'goal_weight',
        'start_date',
        'end_date',
        'account_id',
        'time_account_id',
        'planned_budget', // 👈 一定要在模型里加上这行！
    ];

    // 关联目标
    public function goal() {
        return $this->belongsTo(Goal::class);
    }

    // 关联阶段
    public function stages() {
        return $this->hasMany(ProjectStage::class);
    }
    // 🌟 核心：告诉 Laravel 每次返回 JSON 时，自动带上这两个算好的虚拟字段
    protected $appends = ['actual_total_hours', 'actual_total_cost'];

    // 1. 自动计算：项目实际总耗时
    public function getActualTotalHoursAttribute()
    {
        // 跨越 4 层级进行求和 (Project -> Stage -> Step -> Task -> TaskDetail)
        // 找出这个项目下所有的阶段ID
        $stageIds = ProjectStage::where('project_id', $this->id)->pluck('id');
        if ($stageIds->isEmpty()) return 0;

        // 找出所有步骤ID
        $stepIds = ProjectStageStep::whereIn('project_stage_id', $stageIds)->pluck('id');
        if ($stepIds->isEmpty()) return 0;

        // 找出所有主任务ID
        $taskIds = Task::whereIn('project_stage_step_id', $stepIds)->pluck('id');
        if ($taskIds->isEmpty()) return 0;

        // 汇总所有“已完成”打卡的实际耗时
        return TaskDetail::whereIn('task_id', $taskIds)
                         ->where('status', 'completed')
                         ->sum('actual_hours');
    }

    // 2. 自动计算：项目实际总开销
    public function getActualTotalCostAttribute()
    {
        // 直接去财务流水表里，把关联了这个项目，且类型是支出 (expense) 的金额加起来
        // 这里假设你的模型叫 Transaction
        return \App\Models\Transaction::where('project_id', $this->id)
                                      ->where('type', 'expense')
                                      ->sum('amount');
    }
    // 🌟 新增：与时间账户的关联关系
    public function timeAccount()
    {
        return $this->belongsTo(TimeAccount::class, 'time_account_id');
    }
    //与财务账户的关联关系
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    // 🌟 在 Project.php 中确保有这个方法
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'project_id');
    }
}