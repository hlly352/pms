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
        // 1. 允许 frequency 存中文名称 (如 '艾宾浩斯')
        DB::statement("ALTER TABLE tasks MODIFY COLUMN frequency VARCHAR(255) DEFAULT NULL COMMENT '规则名称'");

        // 2. 添加关联ID
        if (!Schema::hasColumn('tasks', 'recitation_id')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('recitation_id')->nullable()->after('id')->index();
            });
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
