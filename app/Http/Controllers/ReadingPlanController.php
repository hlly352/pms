<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlan;
use App\Models\ReadingSpeed;
use App\Models\Book;
use App\Models\Task;
use App\Models\TaskDetail;
use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReadingPlanController extends Controller
{
    // 获取表单下拉选项
    public function getOptions()
    {
        $plannedBookIds = ReadingPlan::pluck('book_id')->toArray();
        return response()->json([
            'books' => Book::whereNotIn('id', $plannedBookIds)->get(['id', 'title', 'word_count', 'page_count']),
            'speeds' => ReadingSpeed::all(['id', 'name', 'speed']),
            'rules' => Rule::where('module', 'like', '%阅读%')
                           ->orWhere('purpose', 'like', '%阅读%')
                           ->orWhere('name', 'like', '%阅读%')
                           ->get(['id', 'name', 'type', 'details']) 
        ]);
    }

    // 🌟 获取阅读计划列表 (包含高级查询与分页)
    public function index(Request $request)
    {
        // 1. 基础查询：带上书籍的分类、页数等信息
        $query = ReadingPlan::with(['book:id,title,category,cover_url,word_count,page_count', 'speed', 'rule'])
                            ->orderByDesc('id');

        // 2. 按书名模糊搜索
        if ($request->filled('book_title')) {
            $query->whereHas('book', function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->input('book_title') . '%');
            });
        }

        // 3. 按分类精准搜索
        if ($request->filled('category')) {
            $query->whereHas('book', function ($q) use ($request) {
                $q->where('category', $request->input('category'));
            });
        }

        // 4. 执行分页查询 (默认每页10条)
        $perPage = $request->input('per_page', 10);
        $plans = $query->paginate($perPage);

        // 5. 动态计算每本书的“已读页数”、“计划耗时”与“实际耗时”
        foreach ($plans as $plan) {
            $readPages = 0;
            $actualHours = 0;
            $plannedHours = 0;
            
            if ($plan->book) {
                $task = Task::where('source', 'reading')
                            ->where('name', "阅读《{$plan->book->title}》")
                            ->first();

                if ($task) {
                    // 计算计划总耗时
                    $totalDetailsCount = TaskDetail::where('task_id', $task->id)->count();
                    $plannedHours = ($totalDetailsCount * ($plan->daily_minutes ?? 30)) / 60;

                    // 查出已完成的子任务
                    $completedDetails = TaskDetail::where('task_id', $task->id)
                                                  ->where('status', 'completed') 
                                                  ->get();

                    foreach ($completedDetails as $detail) {
                        // 提取已读页数
                        if (preg_match('/第\s*(\d+)\s*页至第\s*(\d+)\s*页/', $detail->remark, $matches)) {
                            $startPage = (int)$matches[1];
                            $endPage = (int)$matches[2];
                            $readPages += ($endPage - $startPage + 1);
                        }
                        // 累加实际打卡耗时
                        $actualHours += (float) $detail->actual_hours;
                    }
                }
            }
            
            $plan->read_pages = $readPages;
            $plan->planned_total_hours = round($plannedHours, 1);
            $plan->actual_total_hours = round($actualHours, 1);
        }

        return response()->json($plans);
    }

    // 获取阅读计划对应的任务详情
    public function show($id)
    {
        $plan = ReadingPlan::with('book')->findOrFail($id);
        
        $task = Task::where('source', 'reading')
                    ->where('name', "阅读《{$plan->book->title}》")
                    ->first();

        if (!$task) return response()->json([]);

        $details = TaskDetail::where('task_id', $task->id)
                             ->orderBy('task_time', 'asc')
                             ->get();
                             
        return response()->json($details);
    }

    // 智能生成任务及详情
    public function store(Request $request)
    {
        $request->validate([
            'book_id' => 'required|exists:books,id',
            'speed_id' => 'required|exists:reading_speeds,id',
            'rule_id' => 'required|exists:rules,id',
        ]);

        DB::beginTransaction();
        try {
            $book = clone Book::findOrFail($request->book_id);
            $speedObj = clone ReadingSpeed::findOrFail($request->speed_id);
            $ruleObj = clone Rule::findOrFail($request->rule_id);

            if (!$book->word_count || !$book->page_count) {
                throw new \Exception('书籍必须填写字数和页数才能生成任务');
            }

            $ruleDetails = is_string($ruleObj->details) ? json_decode($ruleObj->details, true) : ($ruleObj->details ?? []);
            $durationMinutes = (int) ($ruleDetails['minutes'] ?? $ruleDetails['duration'] ?? 30); 
            $speedPerHour = $speedObj->speed; 
            
            $wordsPerPage = $book->word_count / $book->page_count; 
            if ($wordsPerPage <= 0) $wordsPerPage = 1;

            $exactPages = ($durationMinutes / 60) * $speedPerHour / $wordsPerPage;
            $dailyPages = (int) ceil($exactPages);
            if ($dailyPages < 1) $dailyPages = 1; 

            $ruleType = $ruleObj->type ?? 'loop'; 
            $rawDays = $ruleDetails['days'] ?? [];
            $allowedDays = [];
            
            $dayMap = [
                '周日' => 0, '星期日' => 0, '周一' => 1, '星期一' => 1,
                '周二' => 2, '星期二' => 2, '周三' => 3, '星期三' => 3,
                '周四' => 4, '星期四' => 4, '周五' => 5, '星期五' => 5,
                '周六' => 6, '星期六' => 6,
            ];

            if (!empty($rawDays)) {
                foreach ($rawDays as $dayStr) {
                    if (isset($dayMap[$dayStr])) $allowedDays[] = $dayMap[$dayStr];
                }
            }
            if (empty($allowedDays)) $allowedDays = [0, 1, 2, 3, 4, 5, 6];

            $task = Task::create([
                'name'              => "阅读《{$book->title}》",
                'content'           => "总页数：{$book->page_count}页 | 目标：完成全书阅读",
                'frequency'         => 'repeat',
                'rule_id'           => $ruleObj->id,
                'source'            => 'reading', 
                'status'            => 1,
                'reminder_time'     => '09:00:00', 
                'last_generated_at' => now(),
                'generate_deadline' => now()->toDateString(), 
                'execution_config'  => [],
            ]);
    
            $currentPage = 1;
            $currentDate = Carbon::today(); 
            $taskDetails = [];
            $lastDeadlineTime = null; 

            while ($currentPage <= $book->page_count) {
                $isReadingDay = true;

                if ($ruleType === 'loop' || $ruleType === '循环' || $ruleType === 'cycle') {
                    if (!in_array($currentDate->dayOfWeek, $allowedDays)) {
                        $isReadingDay = false; 
                    }
                }

                if ($isReadingDay) {
                    $endPage = min($currentPage + $dailyPages - 1, $book->page_count);
                    $readPagesCount = $endPage - $currentPage + 1;
                    $estimatedWords = round($readPagesCount * $wordsPerPage);
                    $taskTime = $currentDate->format('Y-m-d') . ' 09:00:00';

                    $taskDetails[] = [
                        'task_id'    => $task->id,
                        'task_time'  => $taskTime, 
                        'remark'     => "📖 第 {$currentPage} 页至第 {$endPage} 页 (约 {$estimatedWords} 字, ⏱️ {$durationMinutes} 分钟)",
                        'status'     => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    $lastDeadlineTime = $taskTime; 
                    $currentPage = $endPage + 1; 
                }

                $currentDate->addDay(); 
                if (count($taskDetails) > 3000) break; 
            }

            if (!empty($taskDetails)) TaskDetail::insert($taskDetails);
            
            if ($lastDeadlineTime) {
                $task->update([
                    'generate_deadline' => explode(' ', $lastDeadlineTime)[0], 
                    'execution_config' => [
                        'end_time' => $lastDeadlineTime, 
                        'end_date' => explode(' ', $lastDeadlineTime)[0] 
                    ]
                ]);
            }

            $planData = $request->all();
            $planData['daily_minutes'] = $durationMinutes;
            ReadingPlan::create($planData);

            $book->update(['status' => 'reading']);

            DB::commit();
            $finishDateStr = $lastDeadlineTime ? explode(' ', $lastDeadlineTime)[0] : '未知';
            return response()->json([
                'message' => "计划已生成！排期了 " . count($taskDetails) . " 天的任务，预计 {$finishDateStr} 读完。"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '生成失败: ' . $e->getMessage()], 500);
        }
    }

    // 删除计划及级联任务
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $plan = ReadingPlan::with('book')->findOrFail($id);
            
            $task = Task::where('source', 'reading')
                        ->where('name', "阅读《{$plan->book->title}》")
                        ->first();

            if ($task) {
                TaskDetail::where('task_id', $task->id)->delete();
                $task->delete();
            }

            if ($plan->book) {
                $plan->book->update(['status' => 'unread']);
            }

            $plan->delete();
            DB::commit();
            return response()->json(['message' => '计划及关联的排期任务已彻底清除']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '删除失败: ' . $e->getMessage()], 500);
        }
    }
}