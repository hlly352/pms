<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Models\Transaction;
use App\Observers\TransactionObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 👇 2. 添加这一行，限制默认字符串长度为 191
        Schema::defaultStringLength(191);
        // 🌟 2. 注册流水观察者 (强制唤醒它)
        Transaction::observe(TransactionObserver::class);
    }
}
