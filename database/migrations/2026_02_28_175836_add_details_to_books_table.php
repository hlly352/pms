<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('category')->nullable()->comment('书籍分类')->after('author');
            $table->integer('word_count')->nullable()->comment('字数')->after('category');
            $table->integer('page_count')->nullable()->comment('页数')->after('word_count');
        });
    }

    public function down()
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['word_count', 'page_count', 'category']);
        });
    }
};
