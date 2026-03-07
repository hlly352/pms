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
            $table->id(); // 任务ID
            
            $table->string('title'); // 任务标题
            $table->boolean('is_completed')->default(false); // 是否完成
            
            // 关键字段：所属用户 (必填)
            // constrained() 会自动找 users 表
            // onDelete('cascade') 意思是：如果人没了，任务也没了
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // 关键字段：所属计划 (选填，所以加 nullable)
            // 只有当你之前创建了 plans 表，这行才能跑通。如果没有 plans 表，请先注释掉这一行
            $table->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('cascade');

            $table->timestamps(); // 创建时间、更新时间
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
