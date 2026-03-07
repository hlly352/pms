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
        Schema::create('reading_speeds', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('速度名称，如：小说、技术书');
            $table->integer('speed')->comment('阅读速度：字/小时');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_speeds');
    }
};
