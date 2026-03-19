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
        Schema::table('tasks', function (Blueprint $table) {
                       
            // 用于关联项目步骤，方便查找和删除
            $table->unsignedBigInteger('project_stage_step_id')->nullable()->after('source')->comment('关联的项目步骤ID');
            // 加个索引查得快
            $table->index('project_stage_step_id');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['project_stage_step_id']);
        });
    }
};
