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
        Schema::table('recitations', function (Blueprint $table) {
            // 父级ID，允许为空（根节点）
            $table->unsignedBigInteger('parent_id')->nullable()->default(0)->after('id')->index();
            // 类型：folder=文件夹, doc=文档
            $table->string('type')->default('doc')->after('title')->comment('folder/doc'); 
        });
    }

    public function down()
    {
        Schema::table('recitations', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'type']);
        });
    }

 
};
