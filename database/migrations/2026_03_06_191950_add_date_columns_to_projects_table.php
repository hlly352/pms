<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 运行迁移
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 新增项目起止时间字段 (允许为空，防止老数据报错)
            $table->date('start_date')->nullable()->comment('项目开始日期')->after('name');
            $table->date('end_date')->nullable()->comment('项目结束日期')->after('start_date');
        });
    }

    /**
     * 回滚迁移
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 如果回滚，删除这两个字段
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};