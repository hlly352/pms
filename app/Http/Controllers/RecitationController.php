<?php

namespace App\Http\Controllers;

use App\Models\Recitation;
use App\Models\Task;
use App\Models\TaskDetail;
use App\Models\Rule;
use App\Services\TaskService; // 👈 引入服务
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecitationController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index(Request $request)
    {
        $notebookId = $request->input('notebook_id');
        $query = Recitation::select('id', 'parent_id', 'title', 'type', 'rule_id', 'notebook_id');
        if ($notebookId) $query->where('notebook_id', $notebookId);
        return $query->orderBy('type', 'desc')->orderBy('created_at', 'desc')->get();
    }

    public function show($id)
    {
        return Recitation::findOrFail($id);
    }
       /**
     * 获取今日待背诵列表 (包含逾期未完成的)
     */
    public function todayTasks(Request $request)
    {
        $today = now()->format('Y-m-d');
        
        // 查询逻辑：
        // 1. 状态是 pending (未完成)
        // 2. 时间 <= 今天 (包含以前没背完的)
        // 3. 关联的主任务来源是 recitation (排除其他普通任务)
        $details = TaskDetail::where('status', 'pending')
            ->whereDate('task_time', '<=', $today)
            ->whereHas('task', function($q) {
                $q->where('source', 'recitation'); 
            })
            ->with(['task.recitation']) // 预加载关联的文档
            ->orderBy('task_time', 'asc') // 按时间排序，逾期的在前面
            ->get();

        // 格式化返回数据
        $result = $details->map(function ($detail) {
            return [
                'detail_id' => $detail->id,
                'task_id'   => $detail->task_id,
                'doc_id'    => $detail->task->recitation->id ?? null,
                'title'     => $detail->task->name,
                'path'      => $detail->task->content, // 笔记本 -> 文件夹
                'stage'     => $detail->remark,        // 例如：艾宾浩斯 - 第1次复习
                'plan_date' => \Carbon\Carbon::parse($detail->task_time)->format('Y-m-d H:m'),
                'is_overdue'=> \Carbon\Carbon::parse($detail->task_time)->isPast() && !\Carbon\Carbon::parse($detail->task_time)->isToday(),
                // 顺便把文档内容带上，方便直接背诵
                'doc_content' => $detail->task->recitation->content ?? '' 
            ];
        });

        return $result;
    }

    // ✅ 修改 Store：接收 reminder_time
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'type' => 'required|in:folder,doc',
            'parent_id' => 'nullable|integer',
            'rule_id' => 'nullable',
            'content' => 'nullable',
            'notebook_id' => 'required|exists:notebooks,id',
            'reminder_time' => 'nullable|date_format:H:i', // 验证时间格式
        ]);
        
        if (!isset($data['content']) || is_null($data['content'])) $data['content'] = '';
        $data['parent_id'] = $data['parent_id'] ?? 0;
        
        // 提取时间，不存入 recitations 表
        $reminderTime = $request->input('reminder_time');
        unset($data['reminder_time']);

        return DB::transaction(function () use ($data, $reminderTime) {
            $recitation = Recitation::create($data);
            $this->syncTask($recitation, $reminderTime); // 传参
            return $recitation;
        });
    }

    // ✅ 修改 Update：接收 reminder_time
    public function update(Request $request, $id)
    {
        $recitation = Recitation::findOrFail($id);
        
        // 验证
        $request->validate([
            'title' => 'sometimes|required|string', 
            'rule_id' => 'nullable',
            'reminder_time' => 'nullable|string' // 允许传时间字符串
        ]);
        
        $data = $request->except(['id', 'created_at', 'updated_at', 'next_review_time', 'last_reviewed_at', 'reminder_time']);
        
        // 获取时间
        $reminderTime = $request->input('reminder_time');

        return DB::transaction(function () use ($recitation, $data, $reminderTime) {
            $recitation->update($data);
            $this->syncTask($recitation, $reminderTime); // 传参
            return $recitation;
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $recitation = Recitation::findOrFail($id);
            $task = Task::where('recitation_id', $id)->first();
            if ($task) {
                $task->details()->delete();
                $task->delete();
            }
            $recitation->delete();
            return response()->noContent();
        });
    }

    /**
     * 🚀 核心逻辑：带时间的任务同步
     * @param Recitation $recitation
     * @param string|null $reminderTime 用户传来的时间 (HH:mm)
     */
    private function syncTask(Recitation $recitation, $reminderTime = null)
    {
        // 1. 查找旧任务
        $oldTask = Task::where('recitation_id', $recitation->id)->first();

        // 2. 无规则则删除
        if ($recitation->type === 'folder' || empty($recitation->rule_id)) {
            if ($oldTask) {
                $oldTask->details()->delete();
                $oldTask->delete();
            }
            return;
        }

        // 3. 验证规则有效性
        $rule = Rule::find($recitation->rule_id);
        if (!$rule) return;

        // 4. 彻底重置：删除旧任务
        if ($oldTask) {
             $oldTask->details()->delete();
             $oldTask->delete();
        }

        // 5. 准备时间 (如果用户没传，默认 09:00)
        // 注意：前端传的是 HH:mm，数据库如果是 time 类型，通常需要 HH:mm:ss，或者直接存字符串
        $finalTime = $reminderTime ? $reminderTime . ':00' : '09:00:00';
        
        // 修正：有时候前端可能已经带了秒，做个兼容
        if (strlen($reminderTime) > 5) {
            $finalTime = $reminderTime;
        }

        // 6. 创建新任务 (存入 reminder_time)
        $pathString = $this->buildPathString($recitation);
        
        $newTask = Task::create([
            'recitation_id' => $recitation->id,
            'name'          => $recitation->title,
            'content'       => $pathString,
            'frequency'     => 'repeat',
            'rule_id'       => $rule->id,
            'source'        => 'recitation',
            'status'        => true,
            'last_generated_at' => now(),
            
            // 🔴🔴🔴 关键：保存时间到 Task 表
            'reminder_time' => $finalTime, 
            
            'execution_config' => [],
        ]);

        // 7. 生成详情 (TaskService 会读取 $newTask->reminder_time 来生成具体时间)
        $this->taskService->generateOnCreate($newTask);
    }

    private function buildPathString(Recitation $recitation)
    {
        $paths = [];
        $current = $recitation;
        $maxDepth = 10;
        while ($current->parent_id != 0 && $maxDepth > 0) {
            $parent = Recitation::find($current->parent_id);
            if (!$parent) break;
            array_unshift($paths, $parent->title);
            $current = $parent;
            $maxDepth--;
        }
        $notebookName = $recitation->notebook ? $recitation->notebook->name : '默认笔记本';
        array_unshift($paths, $notebookName);
        return implode(' -> ', $paths);
    }
}