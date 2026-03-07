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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable(); // 父菜单ID (支持无限极分类)
            $table->string('title');      // 菜单名称
            $table->string('icon')->nullable(); // 图标
            $table->string('path')->nullable(); // 路由路径
            $table->string('name')->nullable(); // 路由名称
            $table->string('component')->nullable(); // 前端组件路径
            $table->string('permission')->nullable(); // 绑定权限标识 (如 'user.view')
            $table->integer('sort')->default(0); // 排序
            $table->boolean('hidden')->default(false); // 是否隐藏
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
