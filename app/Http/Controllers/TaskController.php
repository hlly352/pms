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
     * 获取任务列表 (支持筛选)
     */
    public function index(Request $request)
    {
        // 🌟 1. 预加载关联：把项目的嵌套关联和项目的时间账户一并查出来
        $query = Task::with([
            'rule', 
            'projectStageStep.stage.project.timeAccount'
        ]);

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

        // 6. 完成状态筛选 (completion_status)
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

        $tasks = $query->latest()->get();

        // 🌟 核心：手动映射非项目任务的时间账户
        // 去查我们之前创建的任务映射配置表
        $mappings = DB::table('task_account_mappings')->whereNotNull('time_account_id')->get()->keyBy('source');
        if ($mappings->isNotEmpty()) {
            $accountIds = $mappings->pluck('time_account_id')->unique();
            $timeAccounts = TimeAccount::whereIn('id', $accountIds)->get()->keyBy('id');

            $tasks->each(function($task) use ($mappings, $timeAccounts) {
                // 如果不是项目任务，且在映射表里有配置
                if ($task->source !== 'project' && isset($mappings[$task->source])) {
                    $accId = $mappings[$task->source]->time_account_id;
                    if (isset($timeAccounts[$accId])) {
                        // 把查到的时间账户动态注入到任务对象里，供前端 row.time_account 调用
                        $task->setAttribute('time_account', $timeAccounts[$accId]);
                    }
                }
            });
        }

        return $tasks;
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
        // 开启数据库事务，确保“改状态”和“扣时间”要么同时成功，要么同时失败
        return DB::transaction(function () use ($request, $id) {
            // 1. 找到对应的打卡记录，并加载对应的任务和项目
            $detail = TaskDetail::with('task.projectStageStep.stage.project')->findOrFail($id);
            $task = $detail->task;
            
            // 2. 获取新老状态和耗时
            $newStatus = $request->input('status'); // 'pending' or 'completed'
            $newActualHours = (float) $request->input('actual_hours', 0); 
            
            $oldStatus = $detail->status;
            $oldActualHours = (float) ($detail->actual_hours ?? 0);

            // 3. 找到应该操作哪一个时间账户
            $accountId = $this->determineTimeAccount($task);

            if ($accountId) {
                $timeAccount = TimeAccount::find($accountId);
                if ($timeAccount) {
                    // 场景 A：完成打卡 -> 从账户中【扣除】时间
                    if ($oldStatus !== 'completed' && $newStatus === 'completed') {
                        $timeAccount->decrement('balance_hours', $newActualHours);
                    }
                    // 场景 B：撤销打卡 -> 将之前扣除的时间【退还】给账户
                    elseif ($oldStatus === 'completed' && $newStatus !== 'completed') {
                        $timeAccount->increment('balance_hours', $oldActualHours);
                    }
                    // 场景 C：修改已完成打卡的耗时时间 -> 多退少补
                    elseif ($oldStatus === 'completed' && $newStatus === 'completed') {
                        $diff = $newActualHours - $oldActualHours;
                        if ($diff > 0) {
                            $timeAccount->decrement('balance_hours', $diff); // 变多了，多扣点
                        } elseif ($diff < 0) {
                            $timeAccount->increment('balance_hours', abs($diff)); // 变少了，退回去一点
                        }
                    }
                }
            }

            // 4. 更新打卡记录本身
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
        // 规则 1：如果是项目任务，顺藤摸瓜找项目绑定的时间账户
        if ($task->source === 'project') {
            if ($task->projectStageStep && $task->projectStageStep->stage && $task->projectStageStep->stage->project) {
                return $task->projectStageStep->stage->project->time_account_id;
            }
            return null;
        }

        // 规则 2：去我们配置的任务映射表里找对应的账户ID
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
     * [新接口] 获取所有任务详情（用于全局列表）
     */
    public function getAllDetails()
    {
        // 1. 预加载 task 以及它背后的项目级时间账户链条
        $details = TaskDetail::with([
            'task.projectStageStep.stage.project.timeAccount'
        ])->orderBy('task_time', 'desc')->get();

        // 2. 查出底层普通任务的映射表数据
        $mappings = \Illuminate\Support\Facades\DB::table('task_account_mappings')
                        ->whereNotNull('time_account_id')
                        ->get()
                        ->keyBy('source');
                        
        if ($mappings->isNotEmpty()) {
            $accountIds = $mappings->pluck('time_account_id')->unique();
            $timeAccounts = \App\Models\TimeAccount::whereIn('id', $accountIds)->get()->keyBy('id');

            // 3. 把映射的时间账户悄悄塞给对应的 task
            $details->each(function($detail) use ($mappings, $timeAccounts) {
                if ($detail->task && $detail->task->source !== 'project' && isset($mappings[$detail->task->source])) {
                    $accId = $mappings[$detail->task->source]->time_account_id;
                    if (isset($timeAccounts[$accId])) {
                        // 动态注入 time_account 属性供前端调用
                        $detail->task->setAttribute('time_account', $timeAccounts[$accId]);
                    }
                }
            });
        }

        return $details;
    }
}