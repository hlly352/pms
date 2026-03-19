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
        Schema::create('project_stage_steps', function (Blueprint $table) {
            $table->id();
            // 关联到阶段表
            $table->foreignId('project_stage_id')->constrained('project_stages')->onDelete('cascade');
            
            $table->string('name'); // 步骤名称
            $table->text('description')->nullable(); // 步骤详情
            $table->date('start_date'); // 开始日期
            $table->date('end_date'); // 结束日期
            $table->integer('weight')->default(0); // 权重
            
            // 截图里的高级字段
            $table->json('frequency')->nullable(); // 频率 (周一,周二...) 存JSON
            $table->time('reminder_time')->nullable(); // 提醒时间
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_stage_steps');
    }
};
