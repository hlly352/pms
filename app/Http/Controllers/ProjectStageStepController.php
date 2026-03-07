<?php

namespace App\Http\Controllers;

use App\Models\ProjectStage;
use App\Models\ProjectStageStep;
use App\Models\Task;       // 引入 Task 模型
use App\Models\TaskDetail; // 引入 TaskDetail 模型
use Illuminate\Http\Request;
use Carbon\Carbon;         // 引入时间处理库
use Illuminate\Support\Facades\DB; // 引入数据库门面(用于事务)

class ProjectStageStepController extends Controller
{
    // 获取某个阶段的所有步骤
    public function index(Request $request)
    {
        $request->validate(['stage_id' => 'required|exists:project_stages,id']);
        
        return ProjectStageStep::where('project_stage_id', $request->stage_id)
            ->orderBy('start_date')
            ->get();
    }

    // 新增步骤
    public function store(Request $request)
    {
        // 1. 验证参数 (🌟 新增了 planned_hours 和 planned_cost)
        $request->validate([
            'project_stage_id' => 'required|exists:project_stages,id',
            'name' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'weight' => 'nullable|integer',
            'frequency' => 'nullable', // 允许为空
            'reminder_time' => 'nullable', // 提醒时间
            'planned_hours' => 'nullable|numeric|min:0', // 👈 验证计划工时
            'planned_cost' => 'nullable|numeric|min:0',  // 👈 验证计划资金
        ]);

        // 使用事务，确保步骤创建和任务生成原子性
        return DB::transaction(function () use ($request) {
            
            // A. 创建实施步骤 (依赖模型中的 $fillable 白名单)
            $step = ProjectStageStep::create($request->all());

            // B. 自动生成任务及详情 (仅当填写了频率时才生成)
            if (!empty($request->frequency)) {
                $this->autoGenerateTaskFromStep($step, $request);
            }

            return $step;
        });
    }

    // 删除步骤 (实现级联删除)
    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            // 1. 获取步骤
            $step = ProjectStageStep::findOrFail($id);

            // 2. 查找关联的任务 (通过我们新增的 project_stage_step_id 字段)
            $tasks = Task::where('project_stage_step_id', $id)->get();

            foreach ($tasks as $task) {
                // 2.1 删除该任务下的所有详情 (TaskDetail)
                $task->details()->delete();

                // 2.2 删除任务本身 (Task)
                $task->delete();
            }

            // 3. 最后删除步骤本身
            $step->delete();

            return response()->noContent();
        });
    }

    /**
     * 辅助方法：根据步骤生成任务逻辑
     */
    private function autoGenerateTaskFromStep($step, $request)
    {
        // 1. 获取关联的项目和阶段名称
        $stage = ProjectStage::with('project')->find($request->project_stage_id);
        
        $projectName = $stage->project ? $stage->project->name : '未知项目';
        $stageName = $stage->name;
        
        // 2. 准备星期映射表
        $weekMap = [
            '周一' => 'Mon', '周二' => 'Tue', '周三' => 'Wed',
            '周四' => 'Thu', '周五' => 'Fri', '周六' => 'Sat', '周日' => 'Sun',
            '1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun'
        ];

        // 3. 解析频率
        $inputDays = is_array($request->frequency) ? $request->frequency : explode(',', $request->frequency);
        $targetWeekDays = [];
        
        foreach ($inputDays as $d) {
            $d = trim($d); 
            if (isset($weekMap[$d])) {
                $targetWeekDays[] = $weekMap[$d];
            }
        }

        // 如果解析不出有效的星期，直接返回
        if (empty($targetWeekDays)) {
            return;
        }

        // 4. 创建主任务 (Task)
        $task = Task::create([
            'name' => $step->name,         // 任务名 = 步骤名
            'content' => "{$projectName} -> {$stageName}", // 内容
            'frequency' => 'weekly',       // 保持 'weekly' 以便前端显示正常
            'reminder_time' => $request->reminder_time ?? '09:00',
            'status' => true,              // 默认开启
            'execution_config' => [
                'days' => $targetWeekDays  // 存入 ['Tue', 'Wed']
            ],
            'generate_deadline' => $step->end_date, // 截止日期
            'last_generated_at' => now(),

            // 添加来源和关联ID
            'source' => 'project', // 标记来源为项目
            'project_stage_step_id' => $step->id, // 记录步骤ID，用于删除时查找
        ]);

        // 5. 立即生成日期范围内的任务详情 (TaskDetail)
        $start = Carbon::parse($step->start_date);
        $end = Carbon::parse($step->end_date);
        $timeStr = $task->reminder_time ? substr($task->reminder_time, 0, 5) : '09:00';

        // 循环每一天
        while ($start->lte($end)) {
            if (in_array($start->format('D'), $targetWeekDays)) {
                TaskDetail::create([
                    'task_id' => $task->id,
                    'task_time' => $start->format('Y-m-d') . ' ' . $timeStr,
                    'status' => 'pending',
                    'remark' => '自动生成: ' . $step->name
                ]);
            }
            $start->addDay();
        }
    }

    // ==========================================
    // 获取步骤关联的每日打卡任务详情
    // ==========================================
    public function getTaskDetails($id)
    {
        // 1. 通过步骤 ID 找到对应的主任务
        $task = \App\Models\Task::where('project_stage_step_id', $id)->first();

        if (!$task) {
            return response()->json([]); // 如果这个步骤没有设置频率（没生成任务），返回空
        }

        // 2. 获取该任务下的所有每日打卡详情
        $details = \App\Models\TaskDetail::where('task_id', $task->id)
                                         ->orderBy('task_time', 'asc')
                                         ->get();

        return response()->json($details);
    }
}