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

        // 3. 右侧数据：【支出】主科目汇总
        $expenses = Transaction::with('subject')->where('type', 'expense')
            ->whereBetween('transaction_date', $dateRange)->get();
            
        $expenseStats = [];
        foreach ($expenses as $t) {
            if (!$t->subject) continue;
            $mainSub = ($t->subject->pid > 0) ? Subject::find($t->subject->pid) : $t->subject;
            $name = $mainSub ? $mainSub->subject_name : '未分类';
            $expenseStats[$name] = ($expenseStats[$name] ?? 0) + $t->amount;
        }

        // 4. 中间数据：各个账户的动态与余额
        $accounts = Account::where('status', 1)->get()->map(function ($acc) use ($startDate, $endDate) {
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
            'accounts' => $accounts
        ]);
    }
}