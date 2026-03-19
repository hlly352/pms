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
        Schema::table('subjects', function (Blueprint $table) {
            $table->enum('subject_type', ['expense', 'income', 'transfer'])->default('expense')->after('pid')->comment('科目类型: expense-支出, income-收入, transfer-内部流转/资产转移');
        });
    }

    public function down()
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('subject_type');
        });
    }
};
