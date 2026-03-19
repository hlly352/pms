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
        Schema::create('reading_notes', function (Blueprint $table) {
            $table->id();
            // 关联书籍ID，如果书籍被删，感悟也级联删除
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade')->comment('关联书籍ID');
            $table->text('content')->comment('感悟内容');
            $table->timestamps(); // 自动包含 created_at 和 updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_notes');
    }
};
