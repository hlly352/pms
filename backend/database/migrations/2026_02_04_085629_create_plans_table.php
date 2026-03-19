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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // 计划标题
            $table->text('description')->nullable(); // 详细描述，允许为空
            $table->date('start_date')->nullable(); // 开始日期
            $table->date('end_date')->nullable();   // 结束日期
            $table->string('status')->default('pending'); // 状态：pending(未开始), in_progress(进行中), completed(已完成)
            $table->timestamps(); // 创建时间和更新时间
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
