<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 给任务打卡表增加“实际耗时”
        Schema::table('task_details', function (Blueprint $table) {
            // decimal(6,1) 支持如 1.5 小时
            $table->decimal('actual_hours', 6, 1)->default(0)->after('status')->comment('单次打卡实际耗时(h)');
        });

        // 2. 给财务流水表增加“关联项目”
        // 假设你的流水表叫 transactions，如果叫别的请自行替换
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('subject_id')->comment('关联的项目ID');
            // 可选：建立外键约束，项目删除时把流水里的 project_id 置空
            // $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_details', function (Blueprint $table) {
            $table->dropColumn('actual_hours');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });
    }
};