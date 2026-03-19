<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\GoalType; 
use Illuminate\Http\Request;

class GoalController extends Controller
{
    // 获取列表
    public function index()
    {
        return Goal::with([
                'goalType',
                // 🌟 新增：预加载该目标下所有的项目、阶段、步骤、以及任务，并统计打卡详情
                'projects.stages.steps.task' => function($q) {
                    $q->withCount([
                        'details as total_details',
                        'details as completed_details' => function($query) {
                            $query->where('status', 'completed');
                        }
                    ]);
                }
            ])
            ->withSum('projects', 'goal_weight') // 保留：自动计算计划进度(项目占比之和)
            ->latest()
            ->get();
    }

    // 获取所有目标类型 (供前端下拉框使用)
    public function getTypes()
    {
        return GoalType::all();
    }

    // 新增
    public function store(Request $request)
    {
        $request->validate([
            'goal_type_id' => 'required|exists:goal_types,id',
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return Goal::create($request->all());
    }

    // 更新
    public function update(Request $request, Goal $goal)
    {
        $request->validate([
            'goal_type_id' => 'sometimes|required|exists:goal_types,id',
            'name' => 'sometimes|required|string|max:255',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
        ]);

        $goal->update($request->all());
        return $goal;
    }

    // 删除
    public function destroy(Goal $goal)
    {
        $goal->delete();
        return response()->noContent();
    }
}