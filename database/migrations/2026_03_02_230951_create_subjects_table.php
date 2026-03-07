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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0)->comment('父级ID，0表示顶级科目');
            $table->string('subject_name', 100)->comment('科目名称');
            $table->string('subject_code', 50)->nullable()->comment('科目编码');
            $table->integer('subject_order')->default(0)->comment('排序权重(数字越小越靠前)');
            $table->string('status', 2)->default('1')->comment('状态: 1-正常, 0-禁用');
            $table->timestamps();
            
            // 建立索引，提高查询效率
            $table->index('pid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
