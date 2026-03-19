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
        Schema::create('reading_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('book_id')->unique()->comment('关联的书籍ID');
            $table->integer('speed_pages')->comment('阅读速度：每天多少页');
            $table->string('recurrence_rule')->comment('重复规则：daily(每天), workdays(工作日)');
            $table->string('status')->default('active')->comment('计划状态：active, completed');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_plans');
    }
};
