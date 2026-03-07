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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // 键：比如 'site_title', 'theme_color'。设为唯一，防止重复
            $table->string('key')->unique(); 
            // 值：比如 'MyLife', '#ffffff'。用 text 类型以防内容很长，允许为空
            $table->text('value')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
