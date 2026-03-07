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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // 书名
            $table->string('author')->nullable(); // 作者
            $table->string('cover_url')->nullable(); // 封面图片地址 (暂时存网络图片链接)
            $table->integer('rating')->default(0); // 评分 (0-5)
            $table->string('status')->default('unread'); // unread(想读), reading(在读), finished(读过)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
