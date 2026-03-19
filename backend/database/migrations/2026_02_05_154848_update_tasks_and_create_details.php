<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 修改 tasks 表
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('source')->default('manual')->comment('来源: manual, auto');
            $table->boolean('status')->default(true)->comment('状态: 1开启 0关闭');
            $table->timestamp('last_generated_at')->nullable()->comment('最后生成时间');
            $table->date('generate_deadline')->nullable()->comment('生成截止日期');
        });

        // 2. 新建 task_details 表
        Schema::create('task_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->dateTime('task_time'); // 具体执行时间 (日期+提醒时间)
            $table->dateTime('finished_at')->nullable(); // 完成时间
            $table->enum('status', ['pending', 'completed', 'expired'])->default('pending');
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_details');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['source', 'status', 'last_generated_at', 'generate_deadline']);
        });
    }
};