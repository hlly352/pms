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
        Schema::table('rules', function (Blueprint $table) {
            // 在 type 字段后面加上 module 字段，默认为 'common' (通用)
            $table->string('module')->default('common')->after('type')->comment('适用模块: task, goal, project等');
        });
    }

    public function down(): void
    {
        Schema::table('rules', function (Blueprint $table) {
            $table->dropColumn('module');
        });
    }
};
