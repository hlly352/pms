<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskDetail;
use App\Models\TimeAccount; // 🌟 引入时间账户模型
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * 获取任务列表 (支持筛选和🌟分页)
     */
    public function index(Request $request)
    {
        // 1. 预加载关联
        $query = Task::with([
            'rule', 
            'projectStageStep.stage.project.timeAccount'
        ]);

        // --- 筛选逻辑保持不变 ---
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }
        if ($request->filled('content')) {
            $query->where('content', 'like', '%' . $request->input('content') . '%');
        }
        if ($request->filled('frequency')) {
            $query->where('frequency', $request->input('frequency'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }
        if ($request->filled('date_start') && $request->filled('date_end')) {
            $query->whereBetween('created_at', [
                $request->input('date_start') . ' 00:00:00',
                $request->input('date_end') . ' 23:59:59'
            ]);
        }
        if ($request->filled('completion_status')) {
            $status = $request->input('completion_status');
            if ($status === 'completed') {
                $query->whereHas('details')
                      ->whereDoesntHave('details', function($q) {
                          $q->where('status', '!=', 'completed');
                      });
            } elseif ($status === 'not_started') {
                $query->whereDoesntHave('details', function($q) {
                    $q->where('status', 'completed');
                });
            } elseif ($status === 'in_progress') {
                $query->whereHas('details', function($q) {
                    $q->where('status', 'completed');
                })->whereHas('details', function($q) {
                    $q->where('status', '!=', 'completed');
                });
            }
        }

        // 🌟 核心修改 1：获取前端传来的 per_page 参数，默认10条，并使用 paginate() 替代 get()
        $perPage = $request->input('per_page', 10);
        $tasks = $query->latest()->paginate($perPage);

        // 🌟 核心修改 2：手动映射非项目任务的时间账户（适配分页对象）
        $mappings = DB::table('task_account_mappings')->whereNotNull('time_account_id')->get()->keyBy('source');
        if ($mappings->isNotEmpty()) {
            $accountIds = $mappings->pluck('time_account_id')->unique();
            $timeAccounts = TimeAccount::whereIn('id', $accountIds)->get()->keyBy('id');

            // 注意这里：使用了 ->getCollection()->each() 来遍历当前页的数据
            $tasks->getCollection()->each(function($task) use ($mappings, $timeAccounts) {
                if ($task->source !== 'project' && isset($mappings[$task->source])) {
                    $accId = $mappings[$task->source]->time_account_id;
                    if (isset($timeAccounts[$accId])) {
                        $task->setAttribute('time_account', $timeAccounts[$accId]);
                    }
                }
            });
        }

        // 🌟 核心修改 3：Laravel 的 paginate() 会自动返回包含 data, total, current_page 等字段的标准 JSON 结构
        return response()->json($tasks);
    }

    /**
     * 新增任务
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'frequency' => 'required|in:once,repeat,weekly,monthly,yearly',
            'rule_id' => 'required_if:frequency,repeat|nullable|exists:rules,id',
            'execution_config' => 'required_unless:frequency,repeat|nullable|array',
        ]);

        $data = $request->except('rule');
        $data['source'] = 'manual'; 
        $data['status'] = true;     

        $task = Task::create($data);

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
        $task->update($request->except('rule'));
        return $task;
    }

    /**
     * 删除任务
     */
    public function destroy(Task $task)
    {
        $task->details()->delete();
        $task->delete();
        return response()->noContent();
    }

    /**
     * 一键生成未来任务 (周期性任务)
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
     * 获取某个任务的执行详情列表
     */
    public function getDetails($taskId)
    {
        return TaskDetail::where('task_id', $taskId)
            ->orderBy('task_time')
            ->get();
    }


    /**
     * 🌟 核心：更新任务详情的状态，并联动扣除时间账户余额
     */
    public function updateDetailStatus(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $detail = TaskDetail::with('task.projectStageStep.stage.project')->findOrFail($id);
            $task = $detail->task;
            
            $newStatus = $request->input('status');
            $newActualHours = (float) $request->input('actual_hours', 0); 
            
            $oldStatus = $detail->status;
            $oldActualHours = (float) ($detail->actual_hours ?? 0);

            $accountId = $this->determineTimeAccount($task);

            if ($accountId) {
                $timeAccount = TimeAccount::find($accountId);
                if ($timeAccount) {
                    if ($oldStatus !== 'completed' && $newStatus === 'completed') {
                        $timeAccount->decrement('balance_hours', $newActualHours);
                    }
                    elseif ($oldStatus === 'completed' && $newStatus !== 'completed') {
                        $timeAccount->increment('balance_hours', $oldActualHours);
                    }
                    elseif ($oldStatus === 'completed' && $newStatus === 'completed') {
                        $diff = $newActualHours - $oldActualHours;
                        if ($diff > 0) {
                            $timeAccount->decrement('balance_hours', $diff);
                        } elseif ($diff < 0) {
                            $timeAccount->increment('balance_hours', abs($diff));
                        }
                    }
                }
            }

            $detail->update([
                'status' => $newStatus,
                'finished_at' => $newStatus === 'completed' ? now() : null,
                'actual_hours' => $newStatus === 'completed' ? $newActualHours : 0,
            ]);

            return response()->json($detail);
        });
    }

    /**
     * 内部辅助方法：根据来源判定该扣哪个时间账户
     */
    private function determineTimeAccount($task)
    {
        if ($task->source === 'project') {
            if ($task->projectStageStep && $task->projectStageStep->stage && $task->projectStageStep->stage->project) {
                return $task->projectStageStep->stage->project->time_account_id;
            }
            return null;
        }

        $mapping = DB::table('task_account_mappings')->where('source', $task->source)->first();
        if ($mapping && $mapping->time_account_id) {
            return $mapping->time_account_id;
        }

        return null;
    }

    /**
     * 更新任务详情的备注
     */
    public function updateDetailRemark(Request $request, $id)
    {
        $detail = TaskDetail::findOrFail($id);
        
        $request->validate(['remark' => 'nullable|string|max:255']);
        $detail->update(['remark' => $request->input('remark')]);

        return $detail;
    }

    /**
     * 获取日历视图的任务事件
     */
    public function getCalendarEvents(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $start = $request->input('start');
        $end = $request->input('end');

        return TaskDetail::with('task')
            ->whereBetween('task_time', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->get();
    }

    /**
     * 获取仪表盘数据：今日待办任务
     */
    public function getTodayPending()
    {
        $today = \Carbon\Carbon::now()->format('Y-m-d');

        return TaskDetail::with('task')
            ->whereDate('task_time', $today)
            ->where('status', 'pending')
            ->orderBy('task_time')
            ->get();
    }
    
    /**
     * 获取所有任务详情（用于全局列表）
     */
    public function getAllDetails()
    {
        $details = TaskDetail::with([
            'task.projectStageStep.stage.project.timeAccount'
        ])->orderBy('task_time', 'desc')->get();

        $mappings = \Illuminate\Support\Facades\DB::table('task_account_mappings')
                        ->whereNotNull('time_account_id')
                        ->get()
                        ->keyBy('source');
                        
        if ($mappings->isNotEmpty()) {
            $accountIds = $mappings->pluck('time_account_id')->unique();
            $timeAccounts = \App\Models\TimeAccount::whereIn('id', $accountIds)->get()->keyBy('id');

            $details->each(function($detail) use ($mappings, $timeAccounts) {
                if ($detail->task && $detail->task->source !== 'project' && isset($mappings[$detail->task->source])) {
                    $accId = $mappings[$detail->task->source]->time_account_id;
                    if (isset($timeAccounts[$accId])) {
                        $detail->task->setAttribute('time_account', $timeAccounts[$accId]);
                    }
                }
            });
        }

        return $details;
    }
}