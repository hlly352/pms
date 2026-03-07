<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskDetail;
use App\Services\TaskService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    protected $taskService;

    /**
     * 注入 TaskService 用于处理复杂的日期生成逻辑
     */
    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * 获取任务列表 (支持筛选)
     */
    public function index(Request $request)
    {
        $query = Task::with('rule');

        // 1. 名称搜索
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        // 2. 内容搜索
        if ($request->filled('content')) {
            $query->where('content', 'like', '%' . $request->input('content') . '%');
        }

        // 3. 频率筛选
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->input('frequency'));
        }

        // 4. 状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        // 5. 日期范围
        if ($request->filled('date_start') && $request->filled('date_end')) {
            $query->whereBetween('created_at', [
                $request->input('date_start') . ' 00:00:00',
                $request->input('date_end') . ' 23:59:59'
            ]);
        }

        // 👇 6. 修改核心：完成状态筛选 (completion_status)
        if ($request->filled('completion_status')) {
            $status = $request->input('completion_status');

            if ($status === 'completed') {
                // 完成: 有详情 且 没有"非完成"状态的详情 (全绿)
                $query->whereHas('details')
                      ->whereDoesntHave('details', function($q) {
                          $q->where('status', '!=', 'completed');
                      });
            } elseif ($status === 'not_started') {
                // 未开始: 没有"已完成"状态的详情 (全灰或无记录)
                $query->whereDoesntHave('details', function($q) {
                    $q->where('status', 'completed');
                });
            } elseif ($status === 'in_progress') {
                // 进行中: 既有"已完成" 也有 "非完成" (半绿半灰)
                $query->whereHas('details', function($q) {
                    $q->where('status', 'completed');
                })->whereHas('details', function($q) {
                    $q->where('status', '!=', 'completed');
                });
            }
        }

        return $query->latest()->get();
    }

    /**
     * 新增任务
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'frequency' => 'required|in:once,repeat,weekly,monthly,yearly',
            // 如果是 repeat，必须传 rule_id
            'rule_id' => 'required_if:frequency,repeat|nullable|exists:rules,id',
            // 如果不是 repeat，必须传 execution_config
            'execution_config' => 'required_unless:frequency,repeat|nullable|array',
        ]);

        // 1. 准备数据：排除 rule 对象，设置默认值
        $data = $request->except('rule');
        $data['source'] = 'manual'; // 默认为手动创建
        $data['status'] = true;     // 默认为开启状态

        // 2. 创建主任务
        $task = Task::create($data);

        // 3. 触发生成逻辑
        // 如果是一次性任务 或 基于规则的重复任务，创建时立即生成详情
        if (in_array($task->frequency, ['once', 'repeat'])) {
            $this->taskService->generateOnCreate($task);
        }

        return $task;
    }

    /**
     * 更新任务
     */
    public function update(Request $request, Task $task)
    {
        // 使用 except('rule') 防止前端回传的关联对象导致 SQL 报错
        $task->update($request->except('rule'));
        
        return $task;
    }

    /**
     * 删除任务
     */
    /**
     * 删除任务
     */
    public function destroy(Task $task)
    {
        // 1. 显式删除关联的详情 (应用层级联，双重保险)
        // 这样可以确保先把子表数据清空，再删主表
        $task->details()->delete();

        // 2. 删除主任务
        $task->delete();
        
        return response()->noContent();
    }

    /**
     * [新接口] 一键生成未来任务 (周期性任务)
     * 逻辑：扫描所有开启的周/月/年任务，补齐未来2个月的详情
     */
    public function generateBatch()
    {
        $count = $this->taskService->generateScheduledTasks();
        
        return response()->json([
            'message' => "成功生成 {$count} 条任务详情", 
            'count' => $count
        ]);
    }

    /**
     * [新接口] 获取某个任务的执行详情列表
     */
    public function getDetails($taskId)
    {
        return TaskDetail::where('task_id', $taskId)
            ->orderBy('task_time') // 按执行时间正序排列
            ->get();
    }
    /**
     * [新接口] 获取所有任务详情（用于全局列表）
     */
    public function getAllDetails()
    {
        // 预加载 task 关联，按执行时间倒序排列（最近的在前面）
        return TaskDetail::with('task')
            ->orderBy('task_time', 'desc')
            ->get();
    }
    /**
     * [新接口] 更新任务详情的状态
     */
    public function updateDetailStatus(Request $request, $id)
    {
        // 1. 找到对应的打卡记录
        $detail = TaskDetail::findOrFail($id);
        
        // 2. 获取前端传来的状态，以及新增的实际耗时（如果没有传，默认给 0）
        $status = $request->input('status'); // 'pending' or 'completed'
        $actualHours = $request->input('actual_hours', 0); 
        
        // 3. 组装要更新的数据
        $updateData = [
            'status' => $status,
            // 保持原有逻辑：如果标记为完成，填充完成时间；否则清空
            'finished_at' => $status === 'completed' ? now() : null,
            // 🌟 新增逻辑：如果是完成状态，存入前端填写的耗时；如果是撤销，强制归零
            'actual_hours' => $status === 'completed' ? $actualHours : 0,
        ];

        // 4. 执行更新写入数据库
        $detail->update($updateData);

        return response()->json($detail);
    }
    /**
     * [新接口] 更新任务详情的备注
     */
    public function updateDetailRemark(Request $request, $id)
    {
        $detail = TaskDetail::findOrFail($id);
        
        // 验证（允许为空）
        $request->validate(['remark' => 'nullable|string|max:255']);
        
        $detail->update(['remark' => $request->input('remark')]);

        return $detail;
    }
    /**
     * [新接口] 获取日历视图的任务事件
     */
    public function getCalendarEvents(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $start = $request->input('start');
        $end = $request->input('end');

        // 查询指定日期范围内的详情，并关联任务名称
        $events = TaskDetail::with('task')
            ->whereBetween('task_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->get();

        return $events;
    }
    /**
     * [新接口] 获取仪表盘数据：今日待办任务
     */
    public function getTodayPending()
    {
        // 获取今天的日期 (YYYY-MM-DD)
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        // 查询条件：
        // 1. 任务时间是今天 (忽略时分秒差异，只比对日期)
        // 2. 状态是待办 (pending)
        // 3. 按时间排序
        $details = TaskDetail::with('task')
            ->whereDate('task_time', $today)
            ->where('status', 'pending')
            ->orderBy('task_time')
            ->get();

        return $details;
    }
}