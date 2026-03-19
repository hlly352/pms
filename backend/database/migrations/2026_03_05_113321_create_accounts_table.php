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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('账户名称(如: 生活账户, 副业实验)');
            $table->decimal('balance', 10, 2)->default(0.00)->comment('账户余额');
            $table->string('icon', 50)->nullable()->default('Wallet')->comment('前端图标');
            $table->string('color', 20)->nullable()->default('#409EFF')->comment('前端主题色');
            $table->string('remark', 255)->nullable()->comment('账户用途说明');
            $table->tinyInteger('status')->default(1)->comment('状态: 1-正常, 0-停用');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
