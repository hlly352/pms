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
        Schema::create('recitations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('背诵标题');
            $table->longText('content')->comment('Markdown内容');
            
            // 关联重复规则
            $table->unsignedBigInteger('rule_id')->nullable()->comment('关联的重复规则ID');
            
            // 复习进度字段
            $table->integer('stage')->default(0)->comment('当前复习阶段');
            $table->timestamp('next_review_time')->nullable()->comment('下一次复习时间');
            $table->timestamp('last_reviewed_at')->nullable()->comment('上次复习时间');
            
            $table->timestamps();
            
            // 索引
            $table->index('rule_id');
            $table->index('next_review_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recitations');
    }
};
