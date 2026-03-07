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
        Schema::table('transactions', function (Blueprint $table) {
            // 删掉旧的字符串字段
            $table->dropColumn('category');
            // 新增正规的 ID 关联字段
            $table->unsignedBigInteger('subject_id')->after('type')->comment('关联的科目ID');
            
            // 可选：添加索引提升查询速度
            $table->index('subject_id');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('subject_id');
            $table->string('category')->comment('分类, 如: 餐饮, 工资等');
        });
    }
};
