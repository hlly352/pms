<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('reading_plans', function (Blueprint $table) {
            // 删除旧字段
            $table->dropColumn(['speed_pages', 'recurrence_rule']);
            
            // 添加新字段
            $table->unsignedBigInteger('speed_id')->comment('阅读速度ID')->after('book_id');
            $table->unsignedBigInteger('rule_id')->comment('规则ID')->after('speed_id');
            $table->integer('daily_minutes')->default(60)->comment('每日计划阅读时长(分钟)')->after('rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reading_plans', function (Blueprint $table) {
            //
        });
    }
};
