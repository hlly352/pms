<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Subject;
use App\Models\Account;
use Carbon\Carbon;

class FinanceOverviewController extends Controller
{
    public function getOverview(Request $request)
    {
        // 1. 🌟 智能账期判断：如果有传参就用传参，没传参就默认 25日~24日
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
        } else {
            $now = now();
            if ($now->day >= 25) {
                $startDate = $now->copy()->startOfDay()->day(25);
                $endDate = $now->copy()->addMonth()->endOfDay()->day(24);
            } else {
                $startDate = $now->copy()->subMonth()->startOfDay()->day(25);
                $endDate = $now->copy()->endOfDay()->day(24);
            }
        }
        $dateRange = [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];

        // 🌟 新增 A：生成 X 轴的日期序列
        $dates = [];
        $current = $startDate->copy();
        $end = $endDate->copy();
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }
        $dateIndexMap = array_flip($dates); // 用于快速定位日期的数组索引

        // 2. 左侧数据：【收入】主科目汇总
        $incomes = Transaction::with('subject')->where('type', 'income')
            ->whereBetween('transaction_date', $dateRange)->get();
            
        $incomeStats = [];
        foreach ($incomes as $t) {
            if (!$t->subject) continue;
            $mainSub = ($t->subject->pid > 0) ? Subject::find($t->subject->pid) : $t->subject;
            $name = $mainSub ? $mainSub->subject_name : '未分类';
            $incomeStats[$name] = ($incomeStats[$name] ?? 0) + $t->amount;
        }

        // 🌟 新增 B：为了折线图，需要建立“科目 ID” -> “账户 ID” 的映射关系
        $allSubjects = Subject::all();
        $subjectAccountMap = [];
        foreach ($allSubjects as $sub) {
            if ($sub->account_id) {
                $subjectAccountMap[$sub->id] = $sub->account_id;
            } elseif ($sub->pid) {
                // 如果是子科目，继承父科目的账户归属
                $parent = $allSubjects->firstWhere('id', $sub->pid);
                if ($parent && $parent->account_id) {
                    $subjectAccountMap[$sub->id] = $parent->account_id;
                }
            }
        }

        // 🌟 新增 C：初始化折线图的 Series 数据 (每个账户一条线)
        $activeAccounts = Account::where('status', 1)->get();
        $trendSeries = [];
        foreach ($activeAccounts as $acc) {
            $trendSeries[$acc->id] = [
                'name' => $acc->name,
                'type' => 'line',
                'smooth' => true,
                'data' => array_fill(0, count($dates), 0) // 初始化每天的支出为0
            ];
        }

        // 3. 右侧数据 与 折线图数据：【支出】汇总
        $expenses = Transaction::with('subject')->where('type', 'expense')
            ->whereBetween('transaction_date', $dateRange)->get();
            
        $expenseStats = [];
        foreach ($expenses as $t) {
            if (!$t->subject) continue;

            // --- 3.1 玫瑰图逻辑 (按科目汇总) ---
            $mainSub = ($t->subject->pid > 0) ? Subject::find($t->subject->pid) : $t->subject;
            $name = $mainSub ? $mainSub->subject_name : '未分类';
            $expenseStats[$name] = ($expenseStats[$name] ?? 0) + $t->amount;

            // --- 3.2 🌟 折线图逻辑 (按日期、按账户汇总) ---
            $dateStr = Carbon::parse($t->transaction_date)->format('Y-m-d');
            if (isset($dateIndexMap[$dateStr])) {
                $idx = $dateIndexMap[$dateStr];
                $accountId = $subjectAccountMap[$t->subject_id] ?? null;

                if ($accountId && isset($trendSeries[$accountId])) {
                    $trendSeries[$accountId]['data'][$idx] += $t->amount;
                }
            }
        }

        // 折线图数据保留两位小数
        foreach ($trendSeries as &$series) {
            foreach ($series['data'] as &$val) {
                $val = round($val, 2);
            }
        }

        // 4. 中间数据：各个账户的动态与余额
        $accounts = $activeAccounts->map(function ($acc) use ($startDate, $endDate) {
            $monthInflow = \App\Models\AllocationLogItem::where('account_id', $acc->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('allocated_amount');

            $subjectIds = Subject::where('account_id', $acc->id)->pluck('id')->toArray();
            $childSubjectIds = Subject::whereIn('pid', $subjectIds)->pluck('id')->toArray();
            $allSubIds = array_merge($subjectIds, $childSubjectIds);

            $monthOutflow = Transaction::where('type', 'expense')
                ->whereIn('subject_id', $allSubIds)
                ->whereBetween('transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->sum('amount');

            return [
                'id' => $acc->id,
                'name' => $acc->name,
                'color' => $acc->color,
                'balance' => $acc->balance, // 当前总蓄水
                'month_inflow' => $monthInflow, // 选定时间段内的流入
                'month_outflow' => $monthOutflow, // 选定时间段内的流出
            ];
        });

        return response()->json([
            'date_range' => $dateRange,
            'income_stats' => collect($incomeStats)->map(fn($v, $k) => ['name' => $k, 'value' => round($v, 2)])->values(),
            'expense_stats' => collect($expenseStats)->map(fn($v, $k) => ['name' => $k, 'value' => round($v, 2)])->values(),
            'accounts' => $accounts,
            // 🌟 终于把折线图的数据喂给前端了
            'trend_data' => [
                'dates' => $dates,
                'series' => array_values($trendSeries)
            ]
        ]);
    }
}