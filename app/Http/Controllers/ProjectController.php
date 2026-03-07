<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStageStep;
use App\Models\Task;
use App\Models\TaskDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    // ==========================================
    // 获取列表
    // ==========================================
    public function index(Request $request)
    {
        // 1. 初始化查询构造器，并加载所需的所有关联关系
        $query = Project::with([
            'goal.goalType', 
            'stages.segments', 
            'stages.steps.task' => function($q) {
                $q->withCount([
                    'details as total_details',
                    'details as completed_details' => function($query) {
                        $query->where('status', 'completed');
                    }
                ]);
            }
        ]);

        // 2. 动态搜索条件
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }
        
        // 搜索目标类型 (因为 type_id 在目标表，需要用到 whereHas)
        if ($request->filled('type_id')) {
            $query->whereHas('goal', function($q) use ($request) {
                $q->where('goal_type_id', $request->input('type_id'));
            });
        }

        // 搜索具体目标
        if ($request->filled('goal_id')) {
            $query->where('goal_id', $request->input('goal_id'));
        }

        // 3. 执行分页 (前端传入 per_page，默认为10)
        $perPage = $request->input('per_page', 10);
        $projects = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($projects);
    }

    // ==========================================
    // 🌟 编辑/更新项目 (采用精准更新，完美级联删除)
    // ==========================================
    public function update(Request $request, Project $project)
    {
        // 1. 验证数据 (🌟 增加了 planned_budget 验证)
        $validated = $request->validate([
            'goal_id' => 'required|exists:goals,id',
            'name' => 'required|string',
            'planned_budget' => 'nullable|numeric|min:0', // 👈 新增总预算验证
            'expected_result' => 'nullable|string',
            'remark' => 'nullable|string',
            'goal_weight' => 'required|integer|min:1|max:100',
            'stages' => 'required|array',
            'stages.*.name' => 'required|string',
            'stages.*.segments' => 'required|array',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return DB::transaction(function () use ($request, $project) {
            // A. 更新项目主表信息 (🌟 增加了 planned_budget)
            $project->update([
                'goal_id' => $request->goal_id,
                'name' => $request->name,
                'planned_budget' => $request->planned_budget, // 👈 保存总预算
                'expected_result' => $request->expected_result,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'remark' => $request->remark,
                'goal_weight' => $request->goal_weight
            ]);

            // B. 提取前端传来的、已经存在的阶段 ID 数组
            $stageIdsInRequest = collect($request->stages)
                                ->pluck('id')
                                ->filter() // 过滤掉 null
                                ->toArray();

            // C. 找到被用户在前端点“删除”去掉的旧阶段，进行彻底的【级联清理】
            $stagesToDelete = $project->stages()->whereNotIn('id', $stageIdsInRequest)->get();
            foreach ($stagesToDelete as $stage) {
                
                // 1. 查出该阶段对应的所有实施步骤
                $stepIds = ProjectStageStep::where('project_stage_id', $stage->id)->pluck('id');
                
                if ($stepIds->isNotEmpty()) {
                    // 2. 查出实施步骤对应的所有主任务
                    $tasks = Task::whereIn('project_stage_step_id', $stepIds)->get();
                    
                    foreach ($tasks as $task) {
                        // 3. 彻底删除任务底下的【任务打卡详情】
                        TaskDetail::where('task_id', $task->id)->delete();
                        // 4. 删除【主任务】本身
                        $task->delete();
                    }
                    // 5. 删除【实施步骤】
                    ProjectStageStep::where('project_stage_id', $stage->id)->delete();
                }
                
                // 6. 删除关联的【时间段】
                $stage->segments()->delete();
                
                // 7. 最后删除【阶段】本身
                $stage->delete();
            }

            // D. 遍历前端传来的阶段，执行更新或创建
            foreach ($request->stages as $stageIndex => $stageData) {
                if (isset($stageData['id']) && $stageData['id']) {
                    // 已有阶段 -> 执行更新
                    $stage = ProjectStage::find($stageData['id']);
                    if ($stage) {
                        $stage->update([
                            'name' => $stageData['name'],
                            'sort' => $stageIndex
                        ]);
                    }
                } else {
                    // 新增阶段 -> 执行创建
                    $stage = $project->stages()->create([
                        'name' => $stageData['name'],
                        'sort' => $stageIndex
                    ]);
                }

                if ($stage) {
                    // E. 处理阶段下的时间段 (Segments)
                    $segmentIdsInRequest = collect($stageData['segments'])
                                            ->pluck('id')
                                            ->filter()
                                            ->toArray();

                    // 级联删除被删掉的时间段
                    $stage->segments()->whereNotIn('id', $segmentIdsInRequest)->delete();

                    foreach ($stageData['segments'] as $segmentData) {
                        if (isset($segmentData['id']) && $segmentData['id']) {
                            // 更新已有时间段
                            $stage->segments()->where('id', $segmentData['id'])->update([
                                'weight' => $segmentData['weight'],
                                'start_date' => $segmentData['start_date'],
                                'end_date' => $segmentData['end_date'],
                            ]);
                        } else {
                            // 创建新时间段
                            $stage->segments()->create([
                                'weight' => $segmentData['weight'],
                                'start_date' => $segmentData['start_date'],
                                'end_date' => $segmentData['end_date'],
                            ]);
                        }
                    }
                }
            }

            return $project->load('stages.segments');
        });
    }

    // ==========================================
    // 🌟 创建新项目 
    // ==========================================
    public function store(Request $request)
    {
        // 1. 验证 (🌟 增加了 planned_budget 验证)
        $validated = $request->validate([
            'goal_id' => 'required|exists:goals,id',
            'name' => 'required|string',
            'planned_budget' => 'nullable|numeric|min:0', // 👈 新增总预算验证
            'expected_result' => 'required|string',
            'stages' => 'required|array',
            'stages.*.name' => 'required|string',
            'stages.*.segments' => 'required|array',
            'stages.*.segments.*.weight' => 'required|numeric',
            'stages.*.segments.*.start_date' => 'required|date',
            'stages.*.segments.*.end_date' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'goal_weight' => 'required|integer|min:1|max:100',
        ]);

        // 2. 事务处理 (保证数据一致性)
        return DB::transaction(function () use ($request) {
            // A. 创建项目主表 (🌟 增加了 planned_budget)
            $project = Project::create([
                'goal_id' => $request->goal_id,
                'name' => $request->name,
                'planned_budget' => $request->planned_budget, // 👈 保存总预算
                'expected_result' => $request->expected_result,
                'goal_weight' => $request->goal_weight,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'remark' => $request->remark
            ]);

            // B. 循环创建阶段
            foreach ($request->stages as $stageIndex => $stageData) {
                $stage = $project->stages()->create([
                    'name' => $stageData['name'],
                    'sort' => $stageIndex
                ]);

                // C. 循环创建时间段
                foreach ($stageData['segments'] as $segmentData) {
                    $stage->segments()->create([
                        'weight' => $segmentData['weight'],
                        'start_date' => $segmentData['start_date'],
                        'end_date' => $segmentData['end_date'],
                    ]);
                }
            }

            return $project->load('stages.segments');
        });
    }

    // ==========================================
    // 🌟 删除项目 (级联删除阶段、步骤、任务、任务详情)
    // ==========================================
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $project = Project::findOrFail($id);

            // 1. 获取该项目下的所有阶段 ID 集合
            $stageIds = ProjectStage::where('project_id', $id)->pluck('id');

            if ($stageIds->isNotEmpty()) {
                // 2. 获取这些阶段下的所有实施步骤 ID 集合
                $stepIds = ProjectStageStep::whereIn('project_stage_id', $stageIds)->pluck('id');

                if ($stepIds->isNotEmpty()) {
                    // 3. 查找由这些步骤自动生成的所有主任务
                    $tasks = Task::whereIn('project_stage_step_id', $stepIds)->get();

                    foreach ($tasks as $task) {
                        // 3.1 彻底删除任务下的每日打卡详情
                        TaskDetail::where('task_id', $task->id)->delete();
                        // 3.2 删除主任务本身
                        $task->delete();
                    }

                    // 4. 任务删干净了，现在删除所有实施步骤
                    ProjectStageStep::whereIn('project_stage_id', $stageIds)->delete();
                }

                // 5. 删除阶段关联的时间段 (Segments)
                DB::table('project_stage_segments')->whereIn('project_stage_id', $stageIds)->delete();

                // 6. 删除所有的项目阶段
                ProjectStage::where('project_id', $id)->delete();
            }

            // 7. 所有子节点都清理干净了，最后删除项目本身
            $project->delete();

            DB::commit();
            return response()->json([
                'message' => '项目及其所有阶段、步骤和排期打卡记录已彻底清除'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }
}