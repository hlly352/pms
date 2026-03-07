<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// 每月1号的 03:00 自动清理
Schedule::command('pms:clean-images')->monthlyOn(8, '15:49');

// 或者每周日运行一次
// Schedule::command('pms:clean-images')->weekly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
