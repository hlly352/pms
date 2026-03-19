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
            $table->decimal('actual_hours', 6, 2)->default(0)->after('status')->comment('单次打卡实际耗时(h)');
        });

        // 2. 给财务流水表增加“关联项目”和“财务账户”
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('subject_id')->comment('关联的项目ID');
            
            // 🌟 新增：财务账户ID字段
            $table->unsignedBigInteger('account_id')->nullable()->after('project_id')->comment('绑定的财务账户ID');
        });
    }

    public function down(): void
    {
        Schema::table('task_details', function (Blueprint $table) {
            $table->dropColumn('actual_hours');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // 🌟 撤销迁移时，把 project_id 和 account_id 一起删掉
            $table->dropColumn(['project_id', 'account_id']);
        });
    }
};