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
        Schema::create('goal_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // 英文标识 (health)
            $table->string('title'); // 中文显示 (健康)
            $table->string('color')->nullable(); // 标签颜色
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_types');
    }
};
