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
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 规则名称
            $table->enum('type', ['fixed', 'loop']); // 类型：固定、循环
            $table->string('purpose')->nullable(); // 用处
            $table->json('details')->nullable(); // 详情 (核心字段，存JSON)
            $table->text('remark')->nullable(); // 备注
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
