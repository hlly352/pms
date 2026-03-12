<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TimeAccount;
use App\Models\TaskDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TimeDashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $rawAccounts = TimeAccount::where('status', 1)->get();

        // 🌟 1. 生成日期序列 (X轴数据)
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while($current <= $end) {
            $dates[] = $current->toDateString();
            $current->addDay();
        }
        $dateIndexMap = array_flip($dates);

        // =========================================================
        // 2. 计算【时间注入总计】
        // =========================================================
        $inflows = DB::table('time_allocation_log_items')
            ->join('time_allocation_logs', 'time_allocation_log_items.time_allocation_log_id', '=', 'time_allocation_logs.id')
            ->whereBetween('time_allocation_log_items.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();

        $accountInflows = [];
        $totalInflow = 0;

        foreach ($inflows as $item) {
            $hours = (float) $item->allocated_hours;
            $accountId = $item->time_account_id;
            
            if (!isset($accountInflows[$accountId])) $accountInflows[$accountId] = 0;
            $accountInflows[$accountId] += $hours;
            $totalInflow += $hours;
        }

        // =========================================================
        // 3. 计算【时间消耗分类】和【每日消耗折线图数据】
        // =========================================================
        $completedDetails = TaskDetail::with(['task.projectStageStep.stage.project'])
            ->where('status', 'completed')
            ->whereBetween('finished_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();

        $mappings = DB::table('task_account_mappings')->get()->keyBy('source');

        $accountOutflows = []; 
        $outflowStatsRaw = [];

        // 🌟 初始化折线图系列数据
        $trendSeries = [];
        foreach ($rawAccounts as $acc) {
            $trendSeries[$acc->id] = [
                'name' => $acc->name,
                'type' => 'line',
                'smooth' => true,
                'data' => array_fill(0, count($dates), 0)
            ];
        }

        foreach ($completedDetails as $detail) {
            $task = $detail->task;
            if (!$task) continue;

            $hours = (float) $detail->actual_hours;
            $accountId = null;
            $categoryName = '其他杂项';

            if ($task->source === 'project' && $task->projectStageStep && $task->projectStageStep->stage && $task->projectStageStep->stage->project) {
                $project = $task->projectStageStep->stage->project;
                $accountId = $project->time_account_id;
                $categoryName = '项目: ' . $project->name;
            } else {
                if (isset($mappings[$task->source])) {
                    $accountId = $mappings[$task->source]->time_account_id;
                }
                $sourceMap = [
                    'reading' => '📚 阅读学习',
                    'recitation' => '🗣️ 背诵记忆',
                    'manual' => '✍️ 零散杂活',
                    'auto' => '🤖 自动待办'
                ];
                $categoryName = $sourceMap[$task->source] ?? '未分类任务';
            }

            if ($accountId) {
                if (!isset($accountOutflows[$accountId])) $accountOutflows[$accountId] = 0;
                $accountOutflows[$accountId] += $hours;

                // 🌟 累加折线图当天数据
                $dateStr = Carbon::parse($detail->finished_at)->toDateString();
                if (isset($dateIndexMap[$dateStr]) && isset($trendSeries[$accountId])) {
                    $idx = $dateIndexMap[$dateStr];
                    $trendSeries[$accountId]['data'][$idx] += $hours;
                }
            }

            if (!isset($outflowStatsRaw[$categoryName])) $outflowStatsRaw[$categoryName] = 0;
            $outflowStatsRaw[$categoryName] += $hours;
        }

        // =========================================================
        // 4. 获取【今日待办任务】
        // =========================================================
        $today = Carbon::now()->toDateString();
        $todayPendingTasks = TaskDetail::with(['task'])
            ->whereDate('task_time', $today)
            ->where('status', 'pending')
            ->orderBy('task_time')
            ->get()
            ->map(function($detail) {
                return [
                    'id' => $detail->id,
                    'task_name' => $detail->task ? $detail->task->name : '未知任务',
                    'task_time' => Carbon::parse($detail->task_time)->format('H:i'),
                    'remark' => $detail->remark,
                ];
            });

        // =========================================================
        // 5. 数据格式化返回
        // =========================================================
        
        // 格式化折线图保留一位小数
        foreach ($trendSeries as &$series) {
            foreach ($series['data'] as &$val) {
                $val = round($val, 1);
            }
        }

        $accounts = $rawAccounts->map(function($acc) use ($accountInflows, $accountOutflows) {
            return [
                'id' => $acc->id,
                'name' => $acc->name,
                'color' => $acc->color ?? '#7b61ff', 
                'balance_hours' => $acc->balance_hours,
                'month_inflow' => round($accountInflows[$acc->id] ?? 0, 1),
                'month_outflow' => round($accountOutflows[$acc->id] ?? 0, 1),
            ];
        });

        $formatChartData = function($rawArray) {
            $result = [];
            foreach ($rawArray as $name => $value) {
                if ($value > 0) $result[] = ['name' => $name, 'value' => round($value, 1)];
            }
            usort($result, function($a, $b) { return $b['value'] <=> $a['value']; });
            return $result;
        };

        return response()->json([
            'date_range' => [$startDate, $endDate],
            'total_inflow' => round($totalInflow, 1),
            'accounts' => $accounts,
            'outflow_stats' => $formatChartData($outflowStatsRaw),
            'today_pending_tasks' => $todayPendingTasks,
            // 🌟 终于把折线图数据补回来了！
            'trend_data' => [
                'dates' => $dates,
                'series' => array_values($trendSeries)
            ]
        ]);
    }
}