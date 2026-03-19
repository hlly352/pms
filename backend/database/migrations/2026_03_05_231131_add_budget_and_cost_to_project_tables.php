<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 执行迁移 (新增字段)
     */
    public function up(): void
    {
        // 1. 给 projects (项目表) 新增总预算
        Schema::table('projects', function (Blueprint $table) {
            // decimal(10, 2) 表示最大 10 位数，其中 2 位是小数，最大支持 99,999,999.99
            $table->decimal('planned_budget', 10, 2)->nullable()->after('goal_weight')->comment('项目总预算');
        });

        // 2. 给 project_stage_steps (实施步骤表) 新增工时和资金
        Schema::table('project_stage_steps', function (Blueprint $table) {
            // decimal(8, 1) 表示最大 8 位数，1 位小数，比如支持 10.5 小时
            $table->decimal('planned_hours', 8, 1)->nullable()->after('weight')->comment('计划工时(小时)');
            $table->decimal('planned_cost', 10, 2)->nullable()->after('planned_hours')->comment('计划步骤预算(元)');
        });
    }

    /**
     * 回滚迁移 (删掉字段，当你运行 php artisan migrate:rollback 时执行)
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('planned_budget');
        });

        Schema::table('project_stage_steps', function (Blueprint $table) {
            $table->dropColumn(['planned_hours', 'planned_cost']);
        });
    }
};