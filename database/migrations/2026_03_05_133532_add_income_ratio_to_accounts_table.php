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
        Schema::table('accounts', function (Blueprint $table) {
            // 新增一个整数类型的百分比字段，默认是 0
            $table->unsignedInteger('income_ratio')->default(0)->after('balance')->comment('收入分配比例(%)');
        });
    }

    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('income_ratio');
        });
    }
};
