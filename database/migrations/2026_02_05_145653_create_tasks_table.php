<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 任务名称
            $table->text('content')->nullable(); // 任务内容
            
            // 频率：一次、重复(调用规则)、每周、每月、每年
            $table->enum('frequency', ['once', 'repeat', 'weekly', 'monthly', 'yearly']);
            
            // 关联规则ID (仅当 frequency = repeat 时使用)
            $table->foreignId('rule_id')->nullable()->constrained('rules')->onDelete('set null');
            
            // 执行配置 (JSON存储)：
            // once: { date: "2023-12-01" }
            // weekly: { days: ["Mon", "Wed"] }
            // monthly: { days: [1, 15, 30] }
            // yearly: [ { date: "05-12", is_lunar: false }, ... ]
            $table->json('execution_config')->nullable();
            
            $table->time('reminder_time')->nullable(); // 提醒时间 (HH:mm:ss)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
