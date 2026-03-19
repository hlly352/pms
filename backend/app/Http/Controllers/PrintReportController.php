<?php

namespace App\Http\Controllers;

use App\Models\TaskDetail;
use App\Models\ReadingPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF; 

class PrintReportController extends Controller
{
    // ==========================================
    // 打印日清单
    // ==========================================
    public function dailyTasks(Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));
        
        // 1. 获取今日任务
        $todayTasks = TaskDetail::with('task')
            ->whereDate('task_time', $date)
            ->orderBy('task_time', 'asc')
            ->get();

        // 2. 获取昨日（及以前）未完成的任务
        $overdueTasks = TaskDetail::with('task')
            ->whereDate('task_time', '<', $date)
            ->where('status', 'pending')
            ->orderBy('task_time', 'asc')
            ->get();

        // 3. 生成 PDF
        $pdf = PDF::loadView('reports.daily_tasks', [
            'todayTasks'   => $todayTasks,
            'overdueTasks' => $overdueTasks,
            'date'         => $date,
        ]);

        $filename = "daily_tasks_{$date}.pdf";

        // 🌟 核心分发逻辑：如果 URL 带有 download=1，则强制作为附件下载（供本地脚本使用）
        if ($request->input('download') == 1) {
            return $pdf->download($filename);
        }

        // 🌟 默认情况：在浏览器中直接内嵌打开预览（供前端 Vue 点击使用）
        return $pdf->stream($filename);
    }

    // ==========================================
    // 打印月清单
    // ==========================================
    public function monthlyTasks(Request $request)
    {
        $month = $request->input('month', date('Y-m'));
        
        // 1. 加载关联任务
        $tasks = TaskDetail::with('task')
            ->where('task_time', 'like', $month . '%')
            ->where('status', 'pending')
            ->orderBy('task_time', 'asc')
            ->get();

        // 2. 统计逻辑
        $totalCount = $tasks->count();
        
        $projectCount = $tasks->filter(function ($detail) {
            return optional($detail->task)->source === 'project';
        })->count();

        $readingCount = $tasks->filter(function ($detail) {
            return optional($detail->task)->source === 'reading';
        })->count();

        $recitationCount = $tasks->filter(function ($detail) {
            return optional($detail->task)->source === 'recitation';
        })->count();

        $manualCount = $tasks->filter(function ($detail) {
            return optional($detail->task)->source === 'manual';
        })->count();

        // 3. 生成 PDF
        $pdf = PDF::loadView('reports.monthly_tasks', [
            'tasks'           => $tasks,
            'month'           => $month,
            'totalCount'      => $totalCount,
            'projectCount'    => $projectCount,
            'readingCount'    => $readingCount,
            'recitationCount' => $recitationCount,
            'manualCount'     => $manualCount,
        ], [], [
            'format'           => 'A4-L',
            'margin_left'      => 5,
            'margin_right'     => 5,
            'margin_top'       => 10,
            'margin_bottom'    => 10,
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ]);

        $filename = "monthly_tasks_{$month}.pdf";

        // 🌟 核心分发逻辑同上
        if ($request->input('download') == 1) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}