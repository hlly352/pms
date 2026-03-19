<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Http\Controllers\TimeAllocationController;//分配时间控制器

// 每月1号的 03:00 自动清理
Schedule::command('pms:clean-images')->monthlyOn(8, '15:49');
// 每天凌晨 01:00 执行任务自动生成
Schedule::command('task:generate-future')->dailyAt('01:00');

// 或者每周日运行一次
// Schedule::command('pms:clean-images')->weekly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ==========================================
// 🌟 你的时间自动化引擎定时任务
// ==========================================
Schedule::call(function () {
    // 调用控制器里的核心发放方法
    app(TimeAllocationController::class)->runDailyAutoAllocation();
})->dailyAt('00:05')                   // 每天凌晨 00:05 执行
  ->name('auto_allocate_time')         // 给任务起个名字（防止重复执行）
  ->withoutOverlapping()               // 防止任务重叠运行
  ->onOneServer();                     // 如果你有多个服务器，确保只执行一次（单机可省略）
